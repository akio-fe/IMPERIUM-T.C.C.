<?php
/**
 * Arquivo: remover.php
 * Propósito: API para remover itens do carrinho
 * 
 * Endpoint: POST /api/carrinho/remover.php
 * Body: {"itemId": 123}
 * 
 * Remove item específico do carrinho e retorna estado atualizado.
 * 
 * Resposta de sucesso (200):
 * {
 *   "sucesso": true,
 *   "mensagem": "Item removido.",
 *   "itens": [{...}],
 *   "subtotal": 89.90,
 *   "subtotalFormatado": "89,90"
 * }
 */
declare(strict_types=1);

// Inicializa sessão e carrega dependências
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/helpers.php';

// Define resposta como JSON
header('Content-Type: application/json; charset=UTF-8');

// ===== VALIDAÇÃO DE MÉTODO HTTP =====
/**
 * Aceita apenas POST para operações de remoção.
 * Segue padrões RESTful: POST/DELETE para modificação de dados.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

// ===== VALIDAÇÃO DE AUTENTICAÇÃO =====
/**
 * Verifica se usuário está autenticado via sessão.
 * Flag 'logged_in' é definida após validação do token Firebase em login.php.
 */
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); // Unauthorized
    echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para remover itens do carrinho.']);
    exit;
}

// ===== OBTENÇÃO DO ID DO USUÁRIO =====
/**
 * Busca UsuId no banco usando email da sessão.
 * Necessário para garantir que usuário só remove seus próprios itens.
 */
$userId = carrinhoGetSessionUserId($conn);
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

// ===== EXTRAÇÃO E VALIDAÇÃO DOS DADOS =====
/**
 * Lê corpo JSON da requisição e extrai itemId (CarID).
 * Cliente envia: {"itemId": 123}
 */
$payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
$itemId = isset($payload['itemId']) ? (int) $payload['itemId'] : 0;

/**
 * Valida itemId antes de tentar remover.
 * IDs devem ser positivos (auto_increment começa em 1).
 */
if ($itemId <= 0) {
    http_response_code(422); // Unprocessable Entity
    echo json_encode(['sucesso' => false, 'mensagem' => 'Item inválido para remoção.']);
    exit;
}

// ===== REMOÇÃO SEGURA DO ITEM =====
/**
 * DELETE com WHERE duplo garante segurança:
 * - CarID = ?: identifica o item específico
 * - UsuId = ?: garante que usuário só remove SEUS itens
 * - LIMIT 1: performance, para após primeira remoção
 * 
 * Segurança Crítica:
 * Sem o filtro UsuId, um usuário mal-intencionado poderia remover
 * itens do carrinho de outros usuários conhecendo apenas o CarID.
 * 
 * Exemplo de ataque prevenido:
 * DELETE FROM carrinho WHERE CarID = 123  -- Remove de QUALQUER usuário
 * vs
 * DELETE FROM carrinho WHERE CarID = 123 AND UsuId = 5  -- Só remove se for do usuário 5
 */
$stmt = $conn->prepare('DELETE FROM carrinho WHERE CarID = ? AND UsuId = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao preparar remoção do item.']);
    exit;
}

// Vincula parâmetros: itemId e userId (previne SQL injection)
$stmt->bind_param('ii', $itemId, $userId);
$stmt->execute();

// ===== VERIFICAÇÃO DO RESULTADO =====
/**
 * affected_rows indica quantas linhas foram deletadas:
 * - 0: Item não existe OU pertence a outro usuário
 * - 1: Item removido com sucesso
 * 
 * Não diferenciamos os dois casos por segurança (information disclosure):
 * não revelamos se o CarID existe para outro usuário.
 */
if ($stmt->affected_rows === 0) {
    $stmt->close();
    http_response_code(404); // Not Found
    echo json_encode(['sucesso' => false, 'mensagem' => 'Item não encontrado no carrinho.']);
    exit;
}

$stmt->close();

// ===== RETORNO DO ESTADO ATUALIZADO =====
/**
 * Após remoção bem-sucedida, busca e retorna estado completo do carrinho.
 * Isso permite ao front-end atualizar a UI sem fazer request adicional.
 * Inclui: lista de itens restantes, subtotal recalculado.
 */
$dados = carrinhoFetchSnapshot($conn, $userId);

echo json_encode([
    'sucesso' => true,
    'mensagem' => 'Item removido.',
    'itens' => $dados['itens'],
    'subtotal' => $dados['subtotal'],
    'subtotalFormatado' => $dados['subtotalFormatado'],
]);
