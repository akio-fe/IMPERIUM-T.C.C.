<?php
/**
 * API de Exclusão de Conta
 * 
 * Endpoint responsável por excluir permanentemente a conta do usuário.
 * Realiza a exclusão tanto no banco de dados local (MySQL) quanto no Firebase Authentication.
 * 
 * Requisitos de Segurança:
 * 1. Usuário deve estar autenticado (Token JWT válido).
 * 2. Email do usuário DEVE estar verificado no Firebase.
 * 3. Operação atômica (transação): ou exclui tudo ou nada.
 */

// Configurações de erro e headers
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\AuthException;

// 1. Inicialização do Firebase
$serviceAccountPath = resolve_firebase_credentials_path();
if (!$serviceAccountPath) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro de configuração do servidor (credenciais).']);
    exit;
}

try {
    $factory = (new Factory)->withServiceAccount($serviceAccountPath);
    $auth = $factory->createAuth();
} catch (\Throwable $e) {
    error_log('Erro Firebase: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno ao conectar com autenticação.']);
    exit;
}

// 2. Extração do Token JWT
$headers = function_exists('getallheaders') ? getallheaders() : [];
$idToken = null;

if (!empty($headers)) {
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            $idToken = preg_replace('/^Bearer\s+/i', '', trim($value));
            break;
        }
    }
}
if (!$idToken && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $idToken = preg_replace('/^Bearer\s+/i', '', trim((string) $_SERVER['HTTP_AUTHORIZATION']));
}

if (!$idToken) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token de autenticação não fornecido.']);
    exit;
}

// 3. Validação do Token e Verificação de Email
try {
    $verifiedIdToken = $auth->verifyIdToken($idToken);
    $uid = $verifiedIdToken->claims()->get('sub');
    $emailVerified = $verifiedIdToken->claims()->get('email_verified');

    // REQUISITO: Solicite uma verificação no email
    // Se o email não estiver verificado, bloqueia a exclusão e solicita que o usuário verifique.
    // O front-end deve tratar o código 'EMAIL_NOT_VERIFIED' e oferecer a opção de re-enviar o email.
    if (!$emailVerified) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'Para excluir sua conta, é necessário verificar seu email primeiro. Verifique sua caixa de entrada.',
            'code' => 'EMAIL_NOT_VERIFIED'
        ]);
        exit;
    }

} catch (\Throwable $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida ou expirada: ' . $e->getMessage()]);
    exit;
}

// 4. Exclusão no Banco de Dados (MySQL)
// Inicia transação para garantir integridade: ou exclui tudo ou nada.
$conn->begin_transaction();

try {
    // Busca ID local do usuário (UsuId) usando o UID do Firebase
    $stmt = $conn->prepare("SELECT UsuId FROM usuario WHERE UsuUID = ?");
    $stmt->bind_param("s", $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 0) {
        // Usuário não existe no banco local, mas existe no Firebase.
        // Prossegue para excluir do Firebase para manter consistência entre os sistemas.
        $userId = null;
    } else {
        $row = $res->fetch_assoc();
        $userId = (int) $row['UsuId'];
    }
    $stmt->close();

    if ($userId) {
        // Exclui dados relacionados em ordem específica para respeitar Chaves Estrangeiras (Foreign Keys)
        
        // 1. Carrinho: Remove itens do carrinho do usuário
        $conn->query("DELETE FROM carrinho WHERE UsuId = $userId");
        
        // 2. Favoritos: Remove lista de desejos
        $conn->query("DELETE FROM favorito WHERE UsuId = $userId");
        
        // 3. Pedidos e Pagamentos (Cascata manual)
        // Pedidos possuem FK para Endereços, então devem ser excluídos antes dos Endereços.
        // Pagamentos e Itens possuem FK para Pedidos, então devem ser excluídos antes dos Pedidos.
        
        // Busca IDs dos pedidos do usuário
        $pedidosRes = $conn->query("SELECT PedId FROM pedido WHERE UsuId = $userId");
        $pedidosIds = [];
        while ($p = $pedidosRes->fetch_assoc()) {
            $pedidosIds[] = (int) $p['PedId'];
        }
        
        if (!empty($pedidosIds)) {
            $idsStr = implode(',', $pedidosIds);
            
            // Deleta Pagamentos vinculados aos pedidos
            $conn->query("DELETE FROM pagamento WHERE PedId IN ($idsStr)");
            
            // Deleta Itens (PedidoProduto) vinculados aos pedidos
            $conn->query("DELETE FROM pedidoproduto WHERE PedId IN ($idsStr)");
            
            // Deleta os Pedidos (Cabeçalho)
            $conn->query("DELETE FROM pedido WHERE UsuId = $userId");
        }
        
        // 4. Endereços: Agora seguro excluir, pois não há pedidos referenciando
        $conn->query("DELETE FROM enderecoentrega WHERE UsuId = $userId");
        
        // 5. CaixaMovimento: Se for funcionário, remove registros de auditoria (opcional, mas solicitado "excluir conta")
        $conn->query("DELETE FROM caixamovimento WHERE UsuId = $userId");
        
        // 6. Funcionario: Se existir registro na tabela extendida
        $conn->query("DELETE FROM funcionario WHERE UsuId = $userId");
        
        // 7. Usuario: Finalmente, remove o registro principal
        $conn->query("DELETE FROM usuario WHERE UsuId = $userId");
    }

    // 5. Exclusão no Firebase Authentication
    // Remove permanentemente a conta do provedor de identidade
    $auth->deleteUser($uid);

    // Commit da transação: Confirma todas as alterações no banco
    $conn->commit();

    // Limpa sessão PHP atual
    session_destroy();

    echo json_encode(['success' => true, 'message' => 'Conta excluída com sucesso.']);

} catch (\Throwable $e) {
    // Rollback: Desfaz alterações no banco em caso de erro (mantém consistência)
    $conn->rollback();
    
    $errorMessage = $e->getMessage();
    error_log('Erro ao excluir conta: ' . $errorMessage);
    
    // Diagnóstico específico para invalid_grant (Erro comum de credenciais/relógio)
    if (strpos($errorMessage, 'invalid_grant') !== false) {
        $msg = 'Erro de autenticação do servidor (invalid_grant). Verifique: 1. Se o relógio do servidor está correto. 2. Se o arquivo de credenciais do Firebase (JSON) é válido e não expirou.';
    } else {
        $msg = 'Erro ao processar exclusão da conta: ' . $errorMessage;
    }

    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $msg]);
}
