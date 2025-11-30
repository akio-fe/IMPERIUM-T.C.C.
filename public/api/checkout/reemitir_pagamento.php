<?php

declare(strict_types=1);

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/../carrinho/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para continuar.']);
    exit;
}

$userId = carrinhoGetSessionUserId($conn);
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
$pedidoId = isset($payload['pedidoId']) ? (int) $payload['pedidoId'] : 0;
if ($pedidoId <= 0) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Pedido inválido.']);
    exit;
}

$sqlPedido = 'SELECT p.PedId, p.PedValorTotal, p.PedStatus,
                     e.EndEntRua, e.EndEntNum, e.EndEntBairro, e.EndEntCid, e.EndEntEst, e.EndEntCep, e.EndEntComple
              FROM pedido p
              INNER JOIN enderecoentrega e ON e.EndEntId = p.EndEntId
              WHERE p.PedId = ? AND p.UsuId = ?
              LIMIT 1';

$stmtPedido = $conn->prepare($sqlPedido);
if (!$stmtPedido) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível validar o pedido informado.']);
    exit;
}

$stmtPedido->bind_param('ii', $pedidoId, $userId);
$stmtPedido->execute();
$resultPedido = $stmtPedido->get_result();
$pedido = $resultPedido->fetch_assoc();
$stmtPedido->close();

if (!$pedido) {
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Pedido não encontrado.']);
    exit;
}

if ((int) $pedido['PedStatus'] !== 0) {
    http_response_code(409);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Este pedido já foi processado ou pago.']);
    exit;
}

$sqlItens = 'SELECT pp.PedProQtd, pp.PedProPrecoUnitario, r.RoupaNome, r.RoupaId
             FROM pedidoproduto pp
             INNER JOIN roupa r ON r.RoupaId = pp.RoupaId
             WHERE pp.PedId = ?';

$stmtItens = $conn->prepare($sqlItens);
if (!$stmtItens) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível recuperar os itens do pedido.']);
    exit;
}

$stmtItens->bind_param('i', $pedidoId);
$stmtItens->execute();
$resultItens = $stmtItens->get_result();

$preferenceItems = [];
$subtotal = 0.0;
while ($item = $resultItens->fetch_assoc()) {
    $quantidade = max(1, (int) ($item['PedProQtd'] ?? 1));
    $precoUnitario = (float) ($item['PedProPrecoUnitario'] ?? 0);
    $subtotal += $quantidade * $precoUnitario;

    $preferenceItems[] = [
        'id' => (string) ($item['RoupaId'] ?? ''),
        'title' => (string) ($item['RoupaNome'] ?? 'Produto'),
        'quantity' => $quantidade,
        'unit_price' => $precoUnitario,
        'currency_id' => 'BRL',
    ];
}

$resultItens->free();
$stmtItens->close();

if (empty($preferenceItems)) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não há itens vinculados a este pedido.']);
    exit;
}

$freteValor = (float) $pedido['PedValorTotal'] - $subtotal;
$freteValor = $freteValor < 0 ? 0.0 : $freteValor;

$accessToken = getenv('MERCADO_PAGO_ACCESS_TOKEN');
if (!$accessToken) {
    $accessToken = 'APP_USR-2804550627984030-113019-7b3e10564b79318bd813af3e497c5f4c-3029369382';
}

if (!$accessToken) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Token do Mercado Pago não configurado.']);
    exit;
}

MercadoPagoConfig::setAccessToken($accessToken);

$preferencePayload = [
    'items' => $preferenceItems,
    'external_reference' => (string) $pedidoId,
    'back_urls' => [
        'success' => site_path('public/pages/account/pedidos.php'),
        'failure' => site_path('public/pages/account/pedidos.php?status=falhou'),
        'pending' => site_path('public/pages/account/pedidos.php'),
    ],
    'shipments' => [
        'cost' => $freteValor,
        'receiver_address' => [
            'zip_code' => (string) ($pedido['EndEntCep'] ?? ''),
            'street_name' => (string) ($pedido['EndEntRua'] ?? ''),
            'street_number' => (string) ($pedido['EndEntNum'] ?? ''),
            'apartment' => (string) ($pedido['EndEntComple'] ?? ''),
            'city_name' => (string) ($pedido['EndEntCid'] ?? ''),
            'state_name' => (string) ($pedido['EndEntEst'] ?? ''),
        ],
    ],
];

try {
    $client = new PreferenceClient();
    $preference = $client->create($preferencePayload);

    $redirectUrl = $preference->init_point ?? $preference->sandbox_init_point ?? null;
    if (!$redirectUrl) {
        throw new RuntimeException('Não foi possível obter a URL de pagamento.');
    }

    echo json_encode([
        'sucesso' => true,
        'pagamentoUrl' => $redirectUrl,
    ]);
} catch (MPApiException $mpException) {
    $apiResponse = method_exists($mpException, 'getApiResponse') ? $mpException->getApiResponse() : null;
    $apiStatusCode = $apiResponse && method_exists($apiResponse, 'getStatusCode')
        ? $apiResponse->getStatusCode()
        : null;
    $apiContent = $apiResponse && method_exists($apiResponse, 'getContent')
        ? $apiResponse->getContent()
        : null;

    http_response_code(502);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Falha ao gerar o link de pagamento.',
        'detalhe' => $mpException->getMessage(),
        'apiStatusCode' => $apiStatusCode,
        'apiResponse' => $apiContent,
    ]);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $exception->getMessage() ?: 'Não foi possível gerar o link de pagamento.',
    ]);
}
