<?php
/**
 * Página: Callback de Retorno do Mercado Pago
 * Propósito: Processa o retorno do pagamento após checkout no Mercado Pago.
 * 
 * Fluxo:
 * 1. Recebe parâmetros da URL (payment_id, external_reference, status)
 * 2. Valida existência do pedido no banco de dados
 * 3. Consulta API do Mercado Pago para confirmar status real do pagamento
 * 4. Insere/atualiza registro de pagamento na tabela 'pagamento'
 * 5. Atualiza status do pedido conforme resultado
 * 6. Redireciona para lista de pedidos com mensagem flash
 * 
 * Integrações:
 * - MercadoPago SDK (PaymentClient para consulta de pagamentos)
 * - Transações MySQL para atomicidade
 * - Sistema de mensagens flash para feedback ao usuário
 */

// Força tipagem estrita em todo o arquivo
declare(strict_types=1);

// Classes do SDK do Mercado Pago
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

// Inicia sessão para armazenar mensagens flash
session_start();
// Carrega configurações, conexão com banco e helpers
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// URL para redirecionamento após processamento (sempre vai para lista de pedidos)
$pedidosUrl = site_path('public/pages/account/pedidos.php');

/**
 * Define mensagem flash para exibição na página de pedidos.
 * 
 * Armazena mensagem temporária na sessão que será consumida e
 * removida na próxima renderização da página de pedidos.
 * 
 * @param string $mensagem Texto da mensagem a ser exibida.
 * @param string $tipo Tipo da mensagem: 'sucesso', 'erro', 'info' (padrão).
 * @return void
 */
function pedidosSetFlash(string $mensagem, string $tipo = 'info'): void
{
    $_SESSION['pedidos_flash'] = [
        'mensagem' => $mensagem,
        'tipo' => $tipo,
    ];
}

/**
 * Converte status do Mercado Pago em status interno do pedido.
 * 
 * Mapeamento de status:
 * - 'approved' → Pedido 1 (Processando) - Pagamento confirmado
 * - 'pending'/'in_process' → Pedido 0 (Aguardando) - Ainda não confirmado
 * - 'rejected'/'cancelled'/'charged_back'/'refunded' → Pedido 0 (Aguardando) - Falhou
 * - Outros → Pedido 0 (Aguardando) - Status desconhecido
 * 
 * Também define mensagem e tipo de alerta apropriados para cada caso.
 * 
 * @param string $status Status retornado pelo Mercado Pago.
 * @return array Contém: pedStatus (int), flashTipo (string), flashMensagem (string).
 */
function interpretarStatusPagamento(string $status): array
{
    // Normaliza para minúsculas para comparação case-insensitive
    $status = strtolower($status);

    switch ($status) {
        // Pagamento aprovado: pedido pode prosseguir para envio
        case 'approved':
            return [
                'pedStatus' => 1,
                'flashTipo' => 'sucesso',
                'flashMensagem' => 'Pagamento aprovado com sucesso! Estamos preparando o envio do seu pedido.',
            ];
        // Pagamento pendente: aguardando confirmação do Mercado Pago
        case 'pending':
        case 'in_process':
            return [
                'pedStatus' => 0,
                'flashTipo' => 'info',
                'flashMensagem' => 'Pagamento pendente/ em análise. Atualizaremos seu pedido assim que o Mercado Pago finalizar a avaliação.',
            ];
        // Pagamento falhou: rejeitado, cancelado, estornado ou devolvido
        case 'rejected':
        case 'cancelled':
        case 'charged_back':
        case 'refunded':
            return [
                'pedStatus' => 0,
                'flashTipo' => 'erro',
                'flashMensagem' => 'Pagamento não foi aprovado. Verifique os dados utilizados ou tente outra forma de pagamento.',
            ];
        // Status desconhecido ou não mapeado
        default:
            return [
                'pedStatus' => 0,
                'flashTipo' => 'info',
                'flashMensagem' => 'Status do pagamento: ' . ($status !== '' ? $status : 'desconhecido') . '.',
            ];
    }
}

// Extrai ID do pedido (external_reference é definido ao criar preferência MP)
$pedidoId = isset($_GET['external_reference']) ? (int) $_GET['external_reference'] : 0;
// Busca ID do pagamento (payment_id ou collection_id, dependendo do fluxo)
$paymentIdParam = $_GET['payment_id'] ?? $_GET['collection_id'] ?? null;
// Sanitiza payment_id removendo caracteres não numéricos
$paymentId = $paymentIdParam !== null ? (int) preg_replace('/[^0-9]/', '', (string) $paymentIdParam) : 0;
// Captura status do pagamento (pode vir como 'status' ou 'collection_status')
$statusParam = strtolower((string) ($_GET['status'] ?? $_GET['collection_status'] ?? ''));

// Valida se o ID do pedido é válido (obrigatório para prosseguir)
if ($pedidoId <= 0) {
    pedidosSetFlash('Não foi possível identificar o pedido retornado pelo Mercado Pago.', 'erro');
    header('Location: ' . $pedidosUrl);
    exit;
}

// Busca o pedido no banco para validar existência e obter valor total
$stmtPedido = $conn->prepare('SELECT PedId, PedValorTotal FROM pedido WHERE PedId = ? LIMIT 1');
if (!$stmtPedido) {
    pedidosSetFlash('Não foi possível localizar o pedido informado.', 'erro');
    header('Location: ' . $pedidosUrl);
    exit;
}

$stmtPedido->bind_param('i', $pedidoId);
$stmtPedido->execute();
$pedidoResultado = $stmtPedido->get_result();
$pedido = $pedidoResultado->fetch_assoc();
$stmtPedido->close();

// Verifica se o pedido realmente existe no banco
if (!$pedido) {
    pedidosSetFlash('Pedido não encontrado.', 'erro');
    header('Location: ' . $pedidosUrl);
    exit;
}

// Inicializa variáveis com valores padrão (serão sobrescritos se consulta à API funcionar)
$paymentStatus = $statusParam !== '' ? $statusParam : 'pending';
$paymentAmount = (float) $pedido['PedValorTotal'];
$paymentDate = date('Y-m-d H:i:s'); // Data/hora atual como fallback
$alertaConsulta = ''; // Armazena avisos se houver erro na consulta à API

// Obtém access token do Mercado Pago (prioriza variável de ambiente)
$accessToken = getenv('MERCADO_PAGO_ACCESS_TOKEN');
if (!$accessToken) {
    // Fallback: token hardcoded (não recomendado para produção)
    $accessToken = 'APP_USR-2804550627984030-113019-7b3e10564b79318bd813af3e497c5f4c-3029369382';
}

// Tenta consultar dados reais do pagamento diretamente na API do Mercado Pago
if ($paymentId > 0 && $accessToken) {
    try {
        // Configura SDK com o access token
        MercadoPagoConfig::setAccessToken($accessToken);
        $paymentClient = new PaymentClient();
        // Busca detalhes completos do pagamento pelo ID
        $payment = $paymentClient->get($paymentId);

        if ($payment) {
            // Sobrescreve variáveis com dados oficiais da API (mais confiáveis que URL params)
            $paymentStatus = strtolower((string) ($payment->status ?? $paymentStatus));
            $paymentAmount = (float) ($payment->transaction_amount ?? $paymentAmount);
            // Prioriza date_approved, se não houver usa date_created ou date_last_updated
            $paymentDateSource = $payment->date_approved ?? $payment->date_created ?? $payment->date_last_updated ?? null;
            if ($paymentDateSource) {
                $paymentDate = date('Y-m-d H:i:s', strtotime((string) $paymentDateSource));
            }
        }
    } catch (MPApiException $exception) {
        // Erro específico da API do Mercado Pago (ex: pagamento não encontrado, token inválido)
        $alertaConsulta = 'Não foi possível validar o pagamento diretamente no Mercado Pago: ' . $exception->getMessage();
    } catch (Throwable $exception) {
        // Qualquer outro erro (rede, timeout, etc)
        $alertaConsulta = 'Não foi possível consultar os detalhes do pagamento: ' . $exception->getMessage();
    }
}

// Converte status do MP em status interno e mensagens para o usuário
$statusInfo = interpretarStatusPagamento($paymentStatus);

// Inicia transação para garantir atomicidade (tudo ou nada)
try {
    $conn->begin_transaction();

    // ID do registro de pagamento (será preenchido se encontrado ou inserido)
    $pagamentoId = null;
    if ($paymentId > 0) {
        // Verifica se já existe um registro de pagamento com este código de transação
        $stmtBuscaPag = $conn->prepare('SELECT PagId FROM pagamento WHERE PagTransacaoCod = ? LIMIT 1');
        if ($stmtBuscaPag) {
            $paymentIdString = (string) $paymentId;
            $stmtBuscaPag->bind_param('s', $paymentIdString);
            $stmtBuscaPag->execute();
            $resultadoPag = $stmtBuscaPag->get_result();
            $pagRegistro = $resultadoPag->fetch_assoc();
            if ($pagRegistro) {
                $pagamentoId = (int) $pagRegistro['PagId'];
            }
            $stmtBuscaPag->close();
        }

        if ($pagamentoId) {
            // Pagamento já existe: atualiza com dados mais recentes
            $stmtAtualizaPag = $conn->prepare('UPDATE pagamento SET PagDataHora = ?, PagValor = ?, PedId = ? WHERE PagId = ?');
            if ($stmtAtualizaPag) {
                $stmtAtualizaPag->bind_param('sdii', $paymentDate, $paymentAmount, $pedidoId, $pagamentoId);
                $stmtAtualizaPag->execute();
                $stmtAtualizaPag->close();
            }
        } else {
            // Primeiro retorno deste pagamento: cria novo registro
            $stmtInserePag = $conn->prepare('INSERT INTO pagamento (PagDataHora, PagValor, PagTransacaoCod, PedId) VALUES (?, ?, ?, ?)');
            if ($stmtInserePag) {
                $paymentIdString = (string) $paymentId;
                $stmtInserePag->bind_param('sdsi', $paymentDate, $paymentAmount, $paymentIdString, $pedidoId);
                $stmtInserePag->execute();
                // Captura ID do registro recém-inserido
                $pagamentoId = (int) $conn->insert_id;
                $stmtInserePag->close();
            }
        }
    }

    // Atualiza o pedido com o novo status e associa ao pagamento (se houver)
    if ($pagamentoId > 0) {
        // Vincula pedido ao pagamento registrado
        $stmtUpdatePedido = $conn->prepare('UPDATE pedido SET PedStatus = ?, PagId = ? WHERE PedId = ?');
        if ($stmtUpdatePedido) {
            $stmtUpdatePedido->bind_param('iii', $statusInfo['pedStatus'], $pagamentoId, $pedidoId);
            $stmtUpdatePedido->execute();
            $stmtUpdatePedido->close();
        }
    } else {
        // Não há payment_id válido: atualiza apenas status sem vincular pagamento
        $stmtUpdatePedido = $conn->prepare('UPDATE pedido SET PedStatus = ?, PagId = NULL WHERE PedId = ?');
        if ($stmtUpdatePedido) {
            $stmtUpdatePedido->bind_param('ii', $statusInfo['pedStatus'], $pedidoId);
            $stmtUpdatePedido->execute();
            $stmtUpdatePedido->close();
        }
    }

    // Confirma todas as operações (commit da transação)
    $conn->commit();
} catch (Throwable $exception) {
    // Em caso de erro, reverte todas as mudanças (rollback)
    $conn->rollback();
    pedidosSetFlash('Não foi possível registrar o retorno do pagamento: ' . $exception->getMessage(), 'erro');
    header('Location: ' . $pedidosUrl);
    exit;
}

// Constrói mensagem final para o usuário
$mensagemFinal = $statusInfo['flashMensagem'];
// Se houve algum problema ao consultar a API, inclui o aviso na mensagem
if ($alertaConsulta !== '') {
    $mensagemFinal .= ' (' . $alertaConsulta . ')';
}

// Define mensagem flash que será exibida na lista de pedidos
pedidosSetFlash($mensagemFinal, $statusInfo['flashTipo']);
// Redireciona para lista de pedidos (processamento concluído)
header('Location: ' . $pedidosUrl);
exit;
