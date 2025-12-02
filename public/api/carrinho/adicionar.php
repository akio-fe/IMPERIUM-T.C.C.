<?php
/**
 * Arquivo: adicionar.php
 * Propósito: API para adicionar produtos ao carrinho de compras
 * 
 * Endpoint: POST /api/carrinho/adicionar.php
 * Body: {"produtoId": 123, "quantidade": 2, "tamanho": "M"}
 * 
 * Funcionalidades:
 * - Valida autenticação do usuário
 * - Verifica existência do produto
 * - Usa transação para garantir atomicidade
 * - Atualiza quantidade se produto já está no carrinho
 * - Insere novo item se produto ainda não adicionado
 * 
 * Segurança:
 * - Prepared statements (previne SQL injection)
 * - Validação de tipos e valores
 * - FOR UPDATE lock (previne race conditions)
 */
declare(strict_types=1);

// Inicializa sessão para acessar dados de autenticação
session_start();

// Carrega dependências e configurações
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// Define resposta como JSON UTF-8
header('Content-Type: application/json; charset=UTF-8');

/**
 * Valida método HTTP.
 * Este endpoint aceita apenas POST para adicionar itens.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

/**
 * Obtém ID do usuário logado a partir do email da sessão.
 * 
 * Processo:
 * 1. Extrai email da sessão (definido no login)
 * 2. Busca UsuId no banco pelo email
 * 3. Retorna ID ou null se não encontrado
 * 
 * @param mysqli $conn Conexão com banco de dados
 * @return int|null ID do usuário ou null se não autenticado
 */
function getSessionUserId(mysqli $conn): ?int
{
    // Extrai e limpa email da sessão
    $email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';
    
    // Sessão sem email = não autenticado
    if ($email === '') {
        return null;
    }

    // Busca ID do usuário no banco pelo email
    $stmt = $conn->prepare  ('SELECT UsuId FROM usuario WHERE UsuEmail = ? LIMIT 1');
    if (!$stmt) {
        return null; // Erro ao preparar query
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($userId);
    
    // Retorna ID se encontrado, null caso contrário
    $found = $stmt->fetch() ? (int) $userId : null;
    $stmt->close();

    return $found;
}

// ===== VALIDAÇÃO DE AUTENTICAÇÃO =====
/**
 * Verifica se usuário está logado.
 * Flag logged_in é definida no login.php após validação Firebase.
 */
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); // Unauthorized
    echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para adicionar itens ao carrinho.']);
    exit;
}

/**
 * Busca ID do usuário no banco.
 * Necessário para associar item do carrinho ao usuário correto.
 */
$userId = getSessionUserId($conn);
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

// ===== EXTRAÇÃO E VALIDAÇÃO DE DADOS =====
/**
 * Lê corpo JSON da requisição.
 * Espera: {"produtoId": 123, "quantidade": 2, "tamanho": "M"}
 */
$payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];

// Extrai e sanitiza parâmetros
$produtoId = isset($payload['produtoId']) ? (int) $payload['produtoId'] : 0;
$quantidade = isset($payload['quantidade']) ? (int) $payload['quantidade'] : 1; // Quantidade padrão: 1
$tamanho = isset($payload['tamanho']) ? strtoupper(trim((string) $payload['tamanho'])) : ''; // Normaliza para maiúscula

/**
 * Valida dados obrigatórios:
 * - produtoId: deve ser positivo
 * - quantidade: deve ser positiva
 * - tamanho: obrigatório, máximo 10 caracteres (P, M, G, GG, 38, 40, etc)
 */
if ($produtoId <= 0 || $quantidade <= 0 || $tamanho === '' || strlen($tamanho) > 10) {
    http_response_code(422); // Unprocessable Entity
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos para o carrinho.']);
    exit;
}

$stmtProduto = $conn->prepare('SELECT RoupaId FROM roupa WHERE RoupaId = ? LIMIT 1');
if (!$stmtProduto) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao preparar consulta do produto.']);
    exit;
}

$stmtProduto->bind_param('i', $produtoId);
$stmtProduto->execute();
$stmtProduto->store_result();
if ($stmtProduto->num_rows === 0) {
    $stmtProduto->close();
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Produto não encontrado.']);
    exit;
}
$stmtProduto->close();

// ===== TRANSAÇÃO PARA GARANTIR ATOMICIDADE =====
/**
 * Inicia transação para garantir consistência.
 * Se erro ocorrer, rollback desfaz alterações.
 * Previne race conditions com FOR UPDATE lock.
 */
$conn->begin_transaction();

try {
    // Verifica se combinação produto+usuário+tamanho já existe (com lock)
    $stmtBusca = $conn->prepare('SELECT CarID, CarQtd FROM carrinho WHERE RoupaId = ? AND UsuId = ? AND CarTam = ? FOR UPDATE');
    if (!$stmtBusca) {
        throw new RuntimeException('Erro ao preparar verificação do carrinho.');
    }

    $stmtBusca->bind_param('iis', $produtoId, $userId, $tamanho);
    $stmtBusca->execute();
    $stmtBusca->bind_result($carId, $quantidadeAtual);
    $existe = $stmtBusca->fetch();
    $stmtBusca->close();

    // Se item já existe, incrementa quantidade
    if ($existe) {
        $novaQuantidade = $quantidadeAtual + $quantidade;
        $stmtUpdate = $conn->prepare('UPDATE carrinho SET CarQtd = ?, CarDataAtu = NOW() WHERE CarID = ?');
        if (!$stmtUpdate) {
            throw new RuntimeException('Erro ao preparar atualização do carrinho.');
        }
        $stmtUpdate->bind_param('ii', $novaQuantidade, $carId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    } else {
        // Se item não existe, insere novo registro
        $stmtInsert = $conn->prepare('INSERT INTO carrinho (CarQtd, CarTam, RoupaId, UsuId, CarDataCre, CarDataAtu) VALUES (?, ?, ?, ?, NOW(), NOW())');
        if (!$stmtInsert) {
            throw new RuntimeException('Erro ao preparar inserção no carrinho.');
        }
        $stmtInsert->bind_param('isii', $quantidade, $tamanho, $produtoId, $userId);
        $stmtInsert->execute();
        $stmtInsert->close();
    }

    $conn->commit();

    echo json_encode(['sucesso' => true, 'mensagem' => 'Produto adicionado ao carrinho.']);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Não foi possível salvar o item no carrinho.',
        'detalhe' => $exception->getMessage(),
    ]);
}
