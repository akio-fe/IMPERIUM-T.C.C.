<?php
declare(strict_types=1);

session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para remover itens do carrinho.']);
    exit;
}

$userId = carrinhoGetSessionUserId($conn);
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
$itemId = isset($payload['itemId']) ? (int) $payload['itemId'] : 0;

if ($itemId <= 0) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Item inválido para remoção.']);
    exit;
}

$stmt = $conn->prepare('DELETE FROM carrinho WHERE CarID = ? AND UsuId = ? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao preparar remoção do item.']);
    exit;
}

$stmt->bind_param('ii', $itemId, $userId);
$stmt->execute();

if ($stmt->affected_rows === 0) {
    $stmt->close();
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Item não encontrado no carrinho.']);
    exit;
}

$stmt->close();

$dados = carrinhoFetchSnapshot($conn, $userId);

echo json_encode([
    'sucesso' => true,
    'mensagem' => 'Item removido.',
    'itens' => $dados['itens'],
    'subtotal' => $dados['subtotal'],
    'subtotalFormatado' => $dados['subtotalFormatado'],
]);
