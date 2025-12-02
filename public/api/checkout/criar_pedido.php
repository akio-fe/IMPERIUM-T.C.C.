<?php
/**
 * Arquivo: criar_pedido.php
 * Propósito: API para criar pedido e gerar link de pagamento Mercado Pago
 * 
 * Endpoint: POST /api/checkout/criar_pedido.php
 * Body: {"enderecoId": 1, "freteValor": 15.50}
 * 
 * Processo completo:
 * 1. Valida autenticação e carrinho
 * 2. Valida endereço de entrega
 * 3. Cria pedido no banco (transação)
 * 4. Cria preferência de pagamento no Mercado Pago
 * 5. Limpa carrinho após sucesso
 * 6. Retorna URL de pagamento
 * 
 * Resposta de sucesso (200):
 * {
 *   "sucesso": true,
 *   "pedidoId": 42,
 *   "pagamentoUrl": "https://www.mercadopago.com.br/checkout/v1/redirect?pref_id=..."
 * }
 * 
 * Integrações:
 * - Mercado Pago SDK (pagamento)
 * - MySQL (persistência)
 * - Transações (consistência)
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

// ===== VALIDAÇÃO DO ENDEREÇO DE ENTREGA =====
/**
 * Busca e valida endereço no banco de dados.
 * 
 * Segurança:
 * - WHERE com duplo filtro (EndEntId E UsuId)
 * - Garante que usuário só usa endereços próprios
 * - Previne ataques IDOR (Insecure Direct Object Reference)
 * 
 * Exemplo de ataque prevenido:
 * Atacante envia enderecoId de outra pessoa e tenta criar pedido
 * com entrega no endereço da vítima.
 */
$enderecoStmt = $conn->prepare(
    'SELECT EndEntId, EndEntRef, EndEntRua, EndEntCep, EndEntNum, EndEntBairro, EndEntCid, EndEntEst, EndEntComple
     FROM EnderecoEntrega
     WHERE EndEntId = ? AND UsuId = ?
     LIMIT 1'
);
if (!$enderecoStmt) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível validar o endereço selecionado.']);
    exit;
}

$enderecoStmt->bind_param('ii', $enderecoId, $userId);
$enderecoStmt->execute();
$enderecoResultado = $enderecoStmt->get_result();
$endereco = $enderecoResultado->fetch_assoc();
$enderecoStmt->close();

// Endereço não existe OU pertence a outro usuário
if (!$endereco) {
    http_response_code(404); // Not Found
    echo json_encode(['sucesso' => false, 'mensagem' => 'Endereço não encontrado.']);
    exit;
}

// ===== CÁLCULO DE VALORES DO PEDIDO =====
/**
 * Estrutura de preços:
 * - Subtotal: soma dos itens do carrinho (quantidade × preço)
 * - Frete: valor calculado pelo front-end (API Correios/Melhor Envio)
 * - Total: subtotal + frete
 * 
 * Importante:
 * Preços são capturados no momento da criação do pedido.
 * Se preço mudar depois, pedido mantém valor original (snapshot).
 */
$subtotal = (float) ($dadosCarrinho['subtotal'] ?? 0.0);
$total = $subtotal + $freteValor;
$pedidoData = date('Y-m-d H:i:s'); // Timestamp da criação
$formaEntrega = 1; // 1 = Correios (enum pode ter: 1-Correios, 2-Transportadora, etc)
$statusInicial = 0; // 0 = Aguardando pagamento (workflow: 0→1→2→3)

// ===== TRANSAÇÃO: CRIAÇÃO DE PEDIDO E ITENS =====
try {
    /**
     * Transação garante consistência:
     * - Pedido criado
     * - Itens vinculados
     * - Carrinho limpo
     * - Preferência Mercado Pago criada
     * 
     * Se qualquer etapa falhar, rollback desfaz TUDO.
     */
    $conn->begin_transaction();

    // ===== ETAPA 1: CRIAR REGISTRO DO PEDIDO =====
    /**
     * Insere pedido principal na tabela 'pedido'.
     * 
     * Campos:
     * - PedData: data/hora da criação
     * - PedValorTotal: subtotal + frete (snapshot do momento)
     * - PedFormEnt: forma de entrega (1=Correios)
     * - PedStatus: 0 (aguardando pagamento)
     * - PagId: NULL inicialmente (preenchido após webhook MP)
     * - UsuId: dono do pedido
     * - EndEntId: endereço de entrega
     */
    $stmtPedido = $conn->prepare(
        'INSERT INTO pedido (PedData, PedValorTotal, PedFormEnt, PedStatus, PagId, UsuId, EndEntId)
         VALUES (?, ?, ?, ?, NULL, ?, ?)'
    );
    if (!$stmtPedido) {
        throw new RuntimeException('Não foi possível registrar o pedido.');
    }

    // 's'=string, 'd'=double, 'i'=integer
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
    $pedidoId = (int) $conn->insert_id; // Auto-increment ID do pedido criado
    $stmtPedido->close();

    // ===== ETAPA 2: CRIAR ITENS DO PEDIDO =====
    /**
     * Copia itens do carrinho para pedidoproduto.
     * 
     * Importante:
     * - Armazena preço no momento da compra (snapshot)
     * - Se preço do produto mudar depois, pedido não é afetado
     * - Cada item vinculado ao PedId
     */
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
        
        // Ignora itens inválidos (não deveria acontecer)
        if ($produtoId <= 0) {
            continue;
        }
        
        $stmtItem->bind_param('idii', $quantidade, $precoUnitario, $pedidoId, $produtoId);
        $stmtItem->execute();
    }
    $stmtItem->close();

    // ===== ETAPA 3: LIMPAR CARRINHO =====
    /**
     * Após pedido criado com sucesso, esvazia carrinho do usuário.
     * 
     * Razão:
     * - Itens já foram capturados no pedido
     * - Evita confusão (usuário acha que precisa pagar de novo)
     * - Libera espaço no banco
     */
    $stmtLimpa = $conn->prepare('DELETE FROM carrinho WHERE UsuId = ?');
    if ($stmtLimpa) {
        $stmtLimpa->bind_param('i', $userId);
        $stmtLimpa->execute();
        $stmtLimpa->close();
    }

    // ===== ETAPA 4: CONFIGURAÇÃO MERCADO PAGO =====
    /**
     * Access Token autentica aplicação no Mercado Pago.
     * 
     * Segurança:
     * - Token deve estar em variável de ambiente (produção)
     * - Hardcoded aqui apenas para desenvolvimento/TCC
     * - Prefixo APP_USR indica token de produção (TEST_USR seria sandbox)
     */
    MercadoPagoConfig::setAccessToken("APP_USR-2804550627984030-113019-7b3e10564b79318bd813af3e497c5f4c-3029369382");

    // ===== ETAPA 5: MONTAGEM DOS ITENS PARA MERCADO PAGO =====
    /**
     * Converte itens do carrinho para formato da API Mercado Pago.
     * 
     * Estrutura de cada item:
     * - id: identificador do produto (RoupaId)
     * - title: nome do produto exibido no checkout MP
     * - quantity: quantidade de unidades
     * - unit_price: preço unitário (BRL)
     * - currency_id: moeda brasileira (BRL)
     */
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

    // Validação: deve haver ao menos 1 item
    if (empty($preferenceItems)) {
        throw new RuntimeException('Não foi possível montar os itens do pagamento.');
    }

    // ===== ETAPA 6: CRIAÇÃO DA PREFERÊNCIA DE PAGAMENTO =====
    /**
     * Preferência = configuração do checkout Mercado Pago.
     * 
     * Parâmetros importantes:
     * - items: lista de produtos
     * - external_reference: ID do pedido (usado no webhook)
     * - auto_return: redireciona automaticamente após aprovação
     * - back_urls: URLs de retorno (sucesso/falha/pendente)
     * - shipments: dados de frete e entrega
     * 
     * Fluxo:
     * 1. Cliente clica em "Pagar"
     * 2. Redirecionado para MP com preferência
     * 3. Paga no MP
     * 4. MP envia webhook para atualizar pedido
     * 5. Cliente redirecionado de volta (back_urls)
     */
    $preferencePayload = [
        'items' => $preferenceItems,
        'external_reference' => (string) $pedidoId, // Liga preferência ao pedido
        'auto_return' => 'approved', // Redireciona automaticamente se aprovado
        'back_urls' => [
            'success' => site_path('public/pages/account/pagamento_retorno.php'),
            'failure' => site_path('public/pages/account/pagamento_retorno.php'),
            'pending' => site_path('public/pages/account/pagamento_retorno.php'),
        ],
        'shipments' => [
            'cost' => $freteValor, // Custo do frete
            'receiver_address' => [ // Endereço de entrega
                'zip_code' => (string) ($endereco['EndEntCep'] ?? ''),
                'street_name' => (string) ($endereco['EndEntRua'] ?? ''),
                'street_number' => (string) ($endereco['EndEntNum'] ?? ''),
                'apartment' => (string) ($endereco['EndEntComple'] ?? ''),
                'city_name' => (string) ($endereco['EndEntCid'] ?? ''),
                'state_name' => (string) ($endereco['EndEntEst'] ?? ''),
            ],
        ],
    ];

    // Envia requisição para API Mercado Pago
    $client = new PreferenceClient();
    $preference = $client->create($preferencePayload);

    /**
     * init_point: URL de checkout para produção
     * sandbox_init_point: URL de checkout para testes
     * 
     * Cliente será redirecionado para esta URL.
     */
    $redirectUrl = $preference->init_point ?? $preference->sandbox_init_point ?? null;
    if (!$redirectUrl) {
        throw new RuntimeException('Não foi possível obter a URL de pagamento.');
    }

    // ===== SUCESSO: COMMIT E RESPOSTA =====
    /**
     * Se chegou até aqui sem exceções:
     * - Pedido criado no banco
     * - Itens vinculados
     * - Carrinho limpo
     * - Link de pagamento gerado
     * 
     * Commit confirma todas as alterações.
     */
    $conn->commit();

    echo json_encode([
        'sucesso' => true,
        'pedidoId' => $pedidoId,
        'pagamentoUrl' => $redirectUrl, // Front-end redireciona para esta URL
    ]);
// ===== TRATAMENTO DE ERROS MERCADO PAGO =====
} catch (MPApiException $mpException) {
    /**
     * Exceção específica da API Mercado Pago.
     * 
     * Causas comuns:
     * - Token inválido ou expirado
     * - Dados de preferência inválidos
     * - Falha na comunicação com servidores MP
     * - Limite de requisições excedido (rate limit)
     * 
     * Rollback desfaz:
     * - Inserção do pedido
     * - Inserção dos itens
     * - Limpeza do carrinho
     * 
     * Resultado: estado do banco como se nada tivesse acontecido.
     */
    $conn->rollback();
    
    // Extrai detalhes da resposta da API MP (se disponível)
    $apiResponse = method_exists($mpException, 'getApiResponse') ? $mpException->getApiResponse() : null;
    $apiStatusCode = $apiResponse && method_exists($apiResponse, 'getStatusCode')
        ? $apiResponse->getStatusCode()
        : null;
    $apiContent = $apiResponse && method_exists($apiResponse, 'getContent')
        ? $apiResponse->getContent()
        : null;

    http_response_code(502); // Bad Gateway (problema com serviço externo)
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Falha ao criar a preferência de pagamento.',
        'detalhe' => $mpException->getMessage(),
        'apiStatusCode' => $apiStatusCode, // Ex: 401, 400, 500
        'apiResponse' => $apiContent, // Resposta completa da API MP
    ]);
    
// ===== TRATAMENTO DE ERROS GENÉRICOS =====
} catch (Throwable $exception) {
    /**
     * Captura qualquer outra exceção:
     * - RuntimeException lançadas manualmente
     * - Erros de banco de dados
     * - Erros de memória, tipo, etc
     * 
     * Throwable captura tudo (Error + Exception).
     */
    $conn->rollback();
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $exception->getMessage() ?: 'Não foi possível finalizar o pedido.',
    ]);
}
