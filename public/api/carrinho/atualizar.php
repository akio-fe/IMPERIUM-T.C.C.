<?php
/**
 * Arquivo: atualizar.php
 * Propósito: API para atualizar quantidade de itens do carrinho
 * 
 * Endpoint: POST /api/carrinho/atualizar.php
 * Body: {"itemId": 123, "delta": 1} ou {"itemId": 123, "quantidade": 5}
 * 
 * Modos de operação:
 * - delta: Incrementa/decrementa quantidade (ex: +1, -2)
 * - quantidade: Define quantidade absoluta (ex: 5 unidades)
 * 
 * Usa transação com FOR UPDATE para prevenir race conditions.
 * Quantidade mínima: 1 (não permite zero ou negativo).
 * 
 * Resposta de sucesso (200):
 * {
 *   "sucesso": true,
 *   "mensagem": "Quantidade atualizada.",
 *   "itens": [{...}],
 *   "subtotal": 269.70,
 *   "subtotalFormatado": "269,70"
 * }
 */
declare(strict_types=1);

// Inicializa sessão e carrega dependências
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/helpers.php';

// Define resposta como JSON
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para atualizar o carrinho.']);
    exit;
}

$userId = carrinhoGetSessionUserId($conn);
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

// ===== EXTRAÇÃO E VALIDAÇÃO DE PARÂMETROS =====
/**
 * Lê corpo JSON e extrai parâmetros:
 * - itemId: ID do item no carrinho (obrigatório)
 * - delta: Mudança relativa na quantidade (opcional)
 * - quantidade: Quantidade absoluta desejada (opcional)
 * 
 * Modos de Operação:
 * 1. Delta: {"itemId": 5, "delta": 1}  → incrementa 1 unidade
 *           {"itemId": 5, "delta": -1} → decrementa 1 unidade
 * 2. Absoluto: {"itemId": 5, "quantidade": 10} → define como 10 unidades
 * 
 * Cliente escolhe um dos modos (delta OU quantidade, não ambos).
 */
$payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
$itemId = isset($payload['itemId']) ? (int) $payload['itemId'] : 0;
$delta = isset($payload['delta']) ? (int) $payload['delta'] : null;
$quantidadeInformada = isset($payload['quantidade']) ? (int) $payload['quantidade'] : null;

/**
 * Validação:
 * - itemId deve ser positivo
 * - Pelo menos um modo (delta OU quantidade) deve ser fornecido
 */
if ($itemId <= 0 || ($delta === null && $quantidadeInformada === null)) {
    http_response_code(422); // Unprocessable Entity
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos para atualização.']);
    exit;
}

// ===== INÍCIO DA TRANSAÇÃO =====
/**
 * Transação MySQL garante atomicidade (tudo ou nada):
 * - Se erro ocorrer, rollback desfaz alterações
 * - Se sucesso, commit persiste alterações
 * 
 * Essencial para prevenir inconsistências em operações concorrentes.
 */
$conn->begin_transaction();

try {
    // ===== BUSCA COM BLOQUEIO (FOR UPDATE) =====
    /**
     * FOR UPDATE bloqueia a linha selecionada até commit/rollback.
     * 
     * Cenário sem FOR UPDATE (race condition):
     * 1. Request A lê: quantidade = 5
     * 2. Request B lê: quantidade = 5
     * 3. Request A incrementa: UPDATE SET quantidade = 6
     * 4. Request B incrementa: UPDATE SET quantidade = 6
     * Resultado: apenas 1 incremento aplicado (deveria ser 7)
     * 
     * Com FOR UPDATE:
     * 1. Request A lê e bloqueia: quantidade = 5
     * 2. Request B aguarda bloqueio ser liberado
     * 3. Request A incrementa e faz commit: quantidade = 6
     * 4. Request B lê valor atualizado: quantidade = 6
     * 5. Request B incrementa: quantidade = 7
     * Resultado: ambos incrementos aplicados corretamente
     */
    $stmtBusca = $conn->prepare('SELECT CarQtd FROM carrinho WHERE CarID = ? AND UsuId = ? FOR UPDATE');
    if (!$stmtBusca) {
        throw new RuntimeException('Erro ao preparar consulta do carrinho.');
    }

    // Vincula parâmetros e executa busca
    $stmtBusca->bind_param('ii', $itemId, $userId);
    $stmtBusca->execute();
    $stmtBusca->bind_result($quantidadeAtual);
    
    /**
     * Valida se item existe E pertence ao usuário.
     * fetch() retorna false se nenhuma linha encontrada.
     */
    if (!$stmtBusca->fetch()) {
        $stmtBusca->close();
        $conn->rollback(); // Desfaz transação
        http_response_code(404); // Not Found
        echo json_encode(['sucesso' => false, 'mensagem' => 'Item não encontrado no carrinho.']);
        exit;
    }
    $stmtBusca->close();

    // ===== CÁLCULO DA NOVA QUANTIDADE =====
    /**
     * Modo 1: Quantidade absoluta (substitui valor atual)
     * Modo 2: Delta (soma/subtrai do valor atual)
     * 
     * max(1, ...) garante quantidade mínima de 1.
     * Para remover item, usar endpoint remover.php.
     */
    if ($quantidadeInformada !== null) {
        // Modo absoluto: define valor exato
        $novaQuantidade = max(1, $quantidadeInformada);
    } else {
        // Modo delta: incremento/decremento relativo
        $delta = (int) $delta;
        if ($delta === 0) {
            throw new RuntimeException('Nada para atualizar.');
        }
        $novaQuantidade = max(1, $quantidadeAtual + $delta);
    }

    // ===== OTIMIZAÇÃO: QUANTIDADE INALTERADA =====
    /**
     * Se nova quantidade = quantidade atual, evita UPDATE desnecessário.
     * 
     * Casos comuns:
     * - Delta +1 com quantidade já no máximo permitido
     * - Delta -1 com quantidade = 1 (max(1,...) impede zero)
     * - Quantidade absoluta igual à atual
     * 
     * Commit libera lock sem fazer UPDATE (performance).
     */
    if ($novaQuantidade === (int) $quantidadeAtual) {
        $conn->commit(); // Libera lock FOR UPDATE
        $dados = carrinhoFetchSnapshot($conn, $userId);
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Quantidade mantida.',
            'itens' => $dados['itens'],
            'subtotal' => $dados['subtotal'],
            'subtotalFormatado' => $dados['subtotalFormatado'],
        ]);
        exit;
    }

    // ===== ATUALIZAÇÃO DA QUANTIDADE =====
    /**
     * UPDATE com WHERE triplo para máxima segurança:
     * - CarQtd: nova quantidade calculada
     * - CarID: item específico
     * - UsuId: garante que só atualiza item do usuário logado
     */
    $stmtAtualiza = $conn->prepare('UPDATE carrinho SET CarQtd = ? WHERE CarID = ? AND UsuId = ?');
    if (!$stmtAtualiza) {
        throw new RuntimeException('Erro ao preparar atualização do item.');
    }

    $stmtAtualiza->bind_param('iii', $novaQuantidade, $itemId, $userId);
    $stmtAtualiza->execute();
    $stmtAtualiza->close();

    // ===== COMMIT DA TRANSAÇÃO =====
    /**
     * Confirma todas as alterações no banco.
     * Libera lock FOR UPDATE permitindo outros requests processarem.
     */
    $conn->commit();

    $dados = carrinhoFetchSnapshot($conn, $userId);

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Quantidade atualizada.',
        'itens' => $dados['itens'],
        'subtotal' => $dados['subtotal'],
        'subtotalFormatado' => $dados['subtotalFormatado'],
    ]);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Não foi possível atualizar o item do carrinho.',
        'detalhe' => $exception->getMessage(),
    ]);
}
