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
$enderecoId = isset($payload['enderecoId']) ? (int) $payload['enderecoId'] : 0;
$freteValor = isset($payload['freteValor']) ? (float) $payload['freteValor'] : 0.0;
$freteValor = $freteValor < 0 ? 0.0 : $freteValor;

if ($enderecoId <= 0) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Selecione um endereço válido para prosseguir.']);
    exit;
}

$dadosCarrinho = carrinhoFetchSnapshot($conn, $userId);
if (empty($dadosCarrinho['itens'])) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Seu carrinho está vazio.']);
    exit;
}

$enderecoStmt = $conn->prepare(
    'SELECT EndEntId, EndEntRef, EndEntRua, EndEntCep, EndEntNum, EndEntBairro, EndEntCid, EndEntEst, EndEntComple
     FROM EnderecoEntrega
     WHERE EndEntId = ? AND UsuId = ?
     LIMIT 1'
);
if (!$enderecoStmt) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível validar o endereço selecionado.']);
    exit;
}

$enderecoStmt->bind_param('ii', $enderecoId, $userId);
$enderecoStmt->execute();
$enderecoResultado = $enderecoStmt->get_result();
$endereco = $enderecoResultado->fetch_assoc();
$enderecoStmt->close();

if (!$endereco) {
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Endereço não encontrado.']);
    exit;
}

$subtotal = (float) ($dadosCarrinho['subtotal'] ?? 0.0);
$total = $subtotal + $freteValor;
$pedidoData = date('Y-m-d H:i:s');
$formaEntrega = 1; // Correios padrão
$statusInicial = 0; // Aguardando pagamento

try {
    $conn->begin_transaction();

    $stmtPedido = $conn->prepare(
        'INSERT INTO pedido (PedData, PedValorTotal, PedFormEnt, PedStatus, PagId, UsuId, EndEntId)
         VALUES (?, ?, ?, ?, NULL, ?, ?)'
    );
    if (!$stmtPedido) {
        throw new RuntimeException('Não foi possível registrar o pedido.');
    }

    $stmtPedido->bind_param(
        'sdiiii',
        $pedidoData,
        $total,
        $formaEntrega,
        $statusInicial,
        $userId,
        $enderecoId
    );
    $stmtPedido->execute();
    $pedidoId = (int) $conn->insert_id;
    $stmtPedido->close();

    $stmtItem = $conn->prepare(
        'INSERT INTO pedidoproduto (PedProQtd, PedProPrecoUnitario, PedId, RoupaId)
         VALUES (?, ?, ?, ?)'
    );
    if (!$stmtItem) {
        throw new RuntimeException('Não foi possível registrar os itens do pedido.');
    }

    foreach ($dadosCarrinho['itens'] as $item) {
        $quantidade = max(1, (int) ($item['quantidade'] ?? 1));
        $precoUnitario = (float) ($item['precoUnitario'] ?? 0);
        $produtoId = (int) ($item['produtoId'] ?? 0);
        if ($produtoId <= 0) {
            continue;
        }
        $stmtItem->bind_param('idii', $quantidade, $precoUnitario, $pedidoId, $produtoId);
        $stmtItem->execute();
    }
    $stmtItem->close();

    $stmtLimpa = $conn->prepare('DELETE FROM carrinho WHERE UsuId = ?');
    if ($stmtLimpa) {
        $stmtLimpa->bind_param('i', $userId);
        $stmtLimpa->execute();
        $stmtLimpa->close();
    }

    MercadoPagoConfig::setAccessToken("APP_USR-2804550627984030-113019-7b3e10564b79318bd813af3e497c5f4c-3029369382");


    $preferenceItems = [];
    foreach ($dadosCarrinho['itens'] as $item) {
        $preferenceItems[] = [
            'id' => (string) ($item['produtoId'] ?? $item['id'] ?? ''),
            'title' => (string) ($item['nome'] ?? 'Produto'),
            'quantity' => max(1, (int) ($item['quantidade'] ?? 1)),
            'unit_price' => (float) ($item['precoUnitario'] ?? 0),
            'currency_id' => 'BRL',
        ];
    }

    if (empty($preferenceItems)) {
        throw new RuntimeException('Não foi possível montar os itens do pagamento.');
    }

    $preferencePayload = [
        'items' => $preferenceItems,
        'external_reference' => (string) $pedidoId,
        //'auto_return' => 'approved',
        'back_urls' => [
            'success' => site_path('public/pages/account/pedidos.php'),
            'failure' => site_path('public/pages/shop/carrinho.php?status=falhou'),
            'pending' => site_path('public/pages/account/pedidos.php'),
        ],
        'shipments' => [
            'cost' => $freteValor,
            'receiver_address' => [
                'zip_code' => (string) ($endereco['EndEntCep'] ?? ''),
                'street_name' => (string) ($endereco['EndEntRua'] ?? ''),
                'street_number' => (string) ($endereco['EndEntNum'] ?? ''),
                'apartment' => (string) ($endereco['EndEntComple'] ?? ''),
                'city_name' => (string) ($endereco['EndEntCid'] ?? ''),
                'state_name' => (string) ($endereco['EndEntEst'] ?? ''),
            ],
        ],
    ];

    $client = new PreferenceClient();
    $preference = $client->create($preferencePayload);

    $redirectUrl = $preference->init_point ?? $preference->sandbox_init_point ?? null;
    if (!$redirectUrl) {
        throw new RuntimeException('Não foi possível obter a URL de pagamento.');
    }

    $conn->commit();

    echo json_encode([
        'sucesso' => true,
        'pedidoId' => $pedidoId,
        'pagamentoUrl' => $redirectUrl,
    ]);
} catch (MPApiException $mpException) {
    $conn->rollback();
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
        'mensagem' => 'Falha ao criar a preferência de pagamento.',
        'detalhe' => $mpException->getMessage(),
        'apiStatusCode' => $apiStatusCode,
        'apiResponse' => $apiContent,
    ]);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $exception->getMessage() ?: 'Não foi possível finalizar o pedido.',
    ]);
}
