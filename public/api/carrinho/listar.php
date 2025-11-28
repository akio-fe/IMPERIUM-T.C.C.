<?php
declare(strict_types=1);

session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para visualizar o carrinho.']);
    exit;
}

$userId = carrinhoGetSessionUserId($conn);
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$dados = carrinhoFetchSnapshot($conn, $userId);

echo json_encode([
    'sucesso' => true,
    'itens' => $dados['itens'],
    'subtotal' => $dados['subtotal'],
    'subtotalFormatado' => $dados['subtotalFormatado'],
]);
