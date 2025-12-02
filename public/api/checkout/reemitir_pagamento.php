<?php
/**
 * Arquivo: reemitir_pagamento.php
 * Propósito: Gerar novo link de pagamento para pedido existente
 * 
 * Endpoint: POST /api/checkout/reemitir_pagamento.php
 * Body: {"pedidoId": 42}
 * 
 * Casos de uso:
 * - Link de pagamento expirou
 * - Usuário fechou janela sem pagar
 * - Erro no processamento anterior
 * 
 * Validações:
 * - Pedido pertence ao usuário logado
 * - Pedido está com status "Aguardando Pagamento" (0)
 * - Pedido possui itens válidos
 * 
 * Resposta de sucesso (200):
 * {
 *   "sucesso": true,
 *   "pagamentoUrl": "https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=..."
 * }
 * 
 * Resposta de erro comum (409 Conflict):
 * {"sucesso": false, "mensagem": "Este pedido já foi processado ou pago."}
 */
declare(strict_types=1);

// Importa classes do Mercado Pago SDK
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

// Inicializa sessão e carrega dependências
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/../carrinho/helpers.php';

// Define resposta como JSON
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

// Vincula parâmetros e busca pedido
$stmtPedido->bind_param('ii', $pedidoId, $userId);
$stmtPedido->execute();
$resultPedido = $stmtPedido->get_result();
$pedido = $resultPedido->fetch_assoc();
$stmtPedido->close();

// ===== VALIDAÇÃO DE EXISTÊNCIA E PROPRIEDADE =====
/**
 * Verifica se pedido existe E pertence ao usuário logado.
 * 
 * Segurança:
 * JOIN com enderecoentrega garante dados completos.
 * Filtro duplo (PedId E UsuId) previne acesso a pedidos de outros.
 */
if (!$pedido) {
    http_response_code(404); // Not Found
    echo json_encode(['sucesso' => false, 'mensagem' => 'Pedido não encontrado.']);
    exit;
}

// ===== VALIDAÇÃO DO STATUS DO PEDIDO =====
/**
 * Só permite reemissão para pedidos com status 0 (Aguardando Pagamento).
 * 
 * Estados do pedido:
 * - 0: Aguardando Pagamento (permite reemissão)
 * - 1: Pago (não faz sentido gerar novo link)
 * - 2: Em Preparação (já pago, pedido em processo)
 * - 3: Enviado (já despachado)
 * - 4: Entregue (finalizado)
 * - 5: Cancelado (não pode ser reativado assim)
 * 
 * 409 Conflict: recurso em estado que impede operação.
 */
if ((int) $pedido['PedStatus'] !== 0) {
    http_response_code(409); // Conflict
    echo json_encode(['sucesso' => false, 'mensagem' => 'Este pedido já foi processado ou pago.']);
    exit;
}

// ===== BUSCA DOS ITENS DO PEDIDO =====
/**
 * Recupera itens armazenados na tabela pedidoproduto.
 * 
 * Importante:
 * - Usa preço snapshot (PedProPrecoUnitario) do momento da compra
 * - NÃO usa preço atual da tabela roupa
 * - JOIN com roupa apenas para obter nome atualizado
 * 
 * Razão:
 * Se preço mudou desde criação do pedido, mantém valor original.
 */
$sqlItens = 'SELECT pp.PedProQtd, pp.PedProPrecoUnitario, r.RoupaNome, r.RoupaId
             FROM pedidoproduto pp
             INNER JOIN roupa r ON r.RoupaId = pp.RoupaId
             WHERE pp.PedId = ?';

$stmtItens = $conn->prepare($sqlItens);
if (!$stmtItens) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível recuperar os itens do pedido.']);
    exit;
}

$stmtItens->bind_param('i', $pedidoId);
$stmtItens->execute();
$resultItens = $stmtItens->get_result();

// ===== MONTAGEM DE ITENS PARA MERCADO PAGO =====
/**
 * Converte itens do pedido para formato da API MP.
 * Simultaneamente calcula subtotal para derivar frete.
 */
$preferenceItems = [];
$subtotal = 0.0;
while ($item = $resultItens->fetch_assoc()) {
    $quantidade = max(1, (int) ($item['PedProQtd'] ?? 1));
    $precoUnitario = (float) ($item['PedProPrecoUnitario'] ?? 0); // Preço snapshot
    $subtotal += $quantidade * $precoUnitario;

    $preferenceItems[] = [
        'id' => (string) ($item['RoupaId'] ?? ''),
        'title' => (string) ($item['RoupaNome'] ?? 'Produto'),
        'quantity' => $quantidade,
        'unit_price' => $precoUnitario, // Preço original do pedido
        'currency_id' => 'BRL',
    ];
}

$resultItens->free();
$stmtItens->close();

// Pedido sem itens = inconsistência (não deveria acontecer)
if (empty($preferenceItems)) {
    http_response_code(422); // Unprocessable Entity
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não há itens vinculados a este pedido.']);
    exit;
}

// ===== CÁLCULO DO FRETE =====
/**
 * Frete = PedValorTotal - Subtotal
 * 
 * Lógica:
 * PedValorTotal armazenado = subtotal + frete no momento da criação.
 * Subtraindo subtotal, encontramos frete original.
 * 
 * max(0, ...) previne valores negativos (proteção contra dados corrompidos).
 */
$freteValor = (float) $pedido['PedValorTotal'] - $subtotal;
$freteValor = $freteValor < 0 ? 0.0 : $freteValor;

// ===== CONFIGURAÇÃO DO TOKEN MERCADO PAGO =====
/**
 * Prioriza token de variável de ambiente (boas práticas).
 * Fallback para token hardcoded (desenvolvimento/TCC).
 * 
 * Produção:
 * export MERCADO_PAGO_ACCESS_TOKEN="seu_token_aqui"
 */
$accessToken = getenv('MERCADO_PAGO_ACCESS_TOKEN');
if (!$accessToken) {
    // Token hardcoded para desenvolvimento
    $accessToken = 'APP_USR-2804550627984030-113019-7b3e10564b79318bd813af3e497c5f4c-3029369382';
}

// Validação final (caso getenv falhe E string vazia)
if (!$accessToken) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['sucesso' => false, 'mensagem' => 'Token do Mercado Pago não configurado.']);
    exit;
}

// Autentica SDK com token
MercadoPagoConfig::setAccessToken($accessToken);

// ===== CRIAÇÃO DA NOVA PREFERÊNCIA =====
/**
 * Monta preferência NOVA para mesmo pedido.
 * 
 * Diferenças vs criar_pedido.php:
 * - Pedido já existe no banco (usa mesmo PedId)
 * - Itens vem de pedidoproduto (preços snapshot)
 * - Endereço vem do pedido original
 * - Frete recalculado a partir do PedValorTotal
 * 
 * external_reference: liga preferência ao pedido existente.
 * Webhook MP usará este valor para atualizar pedido correto.
 */
$preferencePayload = [
    'items' => $preferenceItems,
    'external_reference' => (string) $pedidoId, // Mesmo ID do pedido original
    'back_urls' => [
        'success' => site_path('public/pages/account/pagamento_retorno.php'),
        'failure' => site_path('public/pages/account/pagamento_retorno.php'),
        'pending' => site_path('public/pages/account/pagamento_retorno.php'),
    ],
    'shipments' => [
        'cost' => $freteValor, // Frete original recalculado
        'receiver_address' => [ // Endereço do pedido original
            'zip_code' => (string) ($pedido['EndEntCep'] ?? ''),
            'street_name' => (string) ($pedido['EndEntRua'] ?? ''),
            'street_number' => (string) ($pedido['EndEntNum'] ?? ''),
            'apartment' => (string) ($pedido['EndEntComple'] ?? ''),
            'city_name' => (string) ($pedido['EndEntCid'] ?? ''),
            'state_name' => (string) ($pedido['EndEntEst'] ?? ''),
        ],
    ],
];

// ===== CRIAÇÃO DA PREFERÊNCIA NO MERCADO PAGO =====
try {
    /**
     * Envia requisição para API Mercado Pago.
     * 
     * Comportamento:
     * - Cada requisição cria preferência NOVA (mesmo para mesmo pedido)
     * - Preferências antigas continuam válidas (podem expirar)
     * - external_reference permanece o mesmo (PedId)
     * 
     * Isso permite:
     * - Gerar múltiplos links para mesmo pedido
     * - Usuário pode tentar pagar várias vezes
     * - Webhook sempre atualiza pedido correto via external_reference
     */
    $client = new PreferenceClient();
    $preference = $client->create($preferencePayload);

    /**
     * init_point: URL de produção (pagamentos reais)
     * sandbox_init_point: URL de testes (sandbox)
     * 
     * SDK escolhe automaticamente baseado no token:
     * - APP_USR-*: produção (init_point)
     * - TEST_USR-*: sandbox (sandbox_init_point)
     */
    $redirectUrl = $preference->init_point ?? $preference->sandbox_init_point ?? null;
    if (!$redirectUrl) {
        throw new RuntimeException('Não foi possível obter a URL de pagamento.');
    }

    // ===== SUCESSO: RETORNA NOVA URL =====
    /**
     * Resposta contém apenas URL.
     * Front-end deve:
     * 1. Redirecionar usuário para pagamentoUrl
     * 2. Aguardar webhook MP atualizar status do pedido
     */
    echo json_encode([
        'sucesso' => true,
        'pagamentoUrl' => $redirectUrl, // Nova URL de pagamento
    ]);
    
// ===== TRATAMENTO DE ERROS MERCADO PAGO =====
} catch (MPApiException $mpException) {
    /**
     * Erros específicos da API Mercado Pago.
     * 
     * Diferença vs criar_pedido.php:
     * - Aqui NÃO faz rollback (pedido já existe)
     * - Apenas falha em gerar nova preferência
     * - Usuário pode tentar novamente
     */
    $apiResponse = method_exists($mpException, 'getApiResponse') ? $mpException->getApiResponse() : null;
    $apiStatusCode = $apiResponse && method_exists($apiResponse, 'getStatusCode')
        ? $apiResponse->getStatusCode()
        : null;
    $apiContent = $apiResponse && method_exists($apiResponse, 'getContent')
        ? $apiResponse->getContent()
        : null;

    http_response_code(502); // Bad Gateway (serviço externo falhou)
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Falha ao gerar o link de pagamento.',
        'detalhe' => $mpException->getMessage(),
        'apiStatusCode' => $apiStatusCode,
        'apiResponse' => $apiContent, // Debug: resposta completa da API MP
    ]);
    
// ===== TRATAMENTO DE ERROS GENÉRICOS =====
} catch (Throwable $exception) {
    /**
     * Captura qualquer outra exceção não tratada.
     * Menos detalhes expostos por segurança.
     */
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $exception->getMessage() ?: 'Não foi possível gerar o link de pagamento.',
    ]);
}
