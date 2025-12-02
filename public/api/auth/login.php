<?php
/**
 * API de Login - Handler de Autenticação Firebase
 * 
 * Endpoint responsável por validar tokens JWT do Firebase Authentication,
 * criar sessões PHP e carregar dados complementares do usuário do banco local.
 * Retorna JSON em todas as respostas para integração com front-end.
 */

// Configura relatório de erros: registra tudo no log mas não exibe na saída
// Essencial para APIs que retornam JSON, evitando quebra do formato
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Inicia sessão PHP para armazenar dados do usuário autenticado
session_start();
// Carrega dependências do Composer (Firebase SDK e outras bibliotecas)
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
// Carrega configurações da aplicação, helpers e conexão com banco
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// Define que todas as respostas serão em formato JSON
header('Content-Type: application/json');
// Headers CORS: permite requisições de qualquer origem (ajustar em produção)
header('Access-Control-Allow-Origin: *');
// Métodos HTTP permitidos neste endpoint
header('Access-Control-Allow-Methods: POST, OPTIONS');
// Headers permitidos nas requisições do cliente
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Responde requisições OPTIONS (preflight CORS) com sucesso imediato
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Importa exceções específicas do Firebase para tratamento granular de erros
use Firebase\Auth\Token\Exception\ExpiredToken; // Token expirado
use Firebase\Auth\Token\Exception\InvalidToken as FirebaseInvalidToken; // Token inválido ou mal formatado
use Kreait\Firebase\Exception\AuthException; // Erros genéricos de autenticação
use Kreait\Firebase\Exception\Auth\RevokedIdToken; // Token revogado pelo usuário/admin
use Kreait\Firebase\Factory; // Factory para inicializar serviços Firebase
use Lcobucci\JWT\UnencryptedToken; // Representação do token JWT validado

// Localiza o arquivo JSON de credenciais da Service Account do Firebase
// Busca em variável de ambiente ou diretório padrão (storage/credentials)
$serviceAccountPath = resolve_firebase_credentials_path();

if (!$serviceAccountPath) {
    // Falha crítica: sem credenciais, impossível validar tokens
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Arquivo de credenciais do Firebase não foi encontrado no servidor. Defina FIREBASE_CREDENTIALS ou coloque o JSON em storage/credentials.'
    ]);
    exit;
}

try {
    // Cria factory do Firebase usando as credenciais da Service Account
    $factory = (new Factory)->withServiceAccount($serviceAccountPath);
    // Inicializa o componente de autenticação para validação de tokens
    $auth = $factory->createAuth();
} catch (\Throwable $e) {
    // Registra erro detalhado no log do servidor
    error_log('Falha ao inicializar Firebase Auth: ' . $e->getMessage());
    // Retorna erro genérico ao cliente (não expõe detalhes internos)
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao inicializar autenticação do Firebase no servidor.'
    ]);
    exit;
}

// Tenta recuperar headers HTTP (compatibilidade com diferentes ambientes)
$headers = function_exists('getallheaders') ? getallheaders() : [];
$idToken = null;

// Primeira tentativa: busca Authorization nos headers normalizados
if (!empty($headers)) {
    foreach ($headers as $name => $value) {
        // Comparação case-insensitive do nome do header
        if (strcasecmp($name, 'Authorization') === 0) {
            // Remove prefixo "Bearer " do token
            $idToken = preg_replace('/^Bearer\s+/i', '', trim($value));
            break;
        }
    }
}

// Segunda tentativa: busca em $_SERVER['HTTP_AUTHORIZATION'] (alguns servidores)
if (!$idToken && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $idToken = preg_replace('/^Bearer\s+/i', '', trim((string) $_SERVER['HTTP_AUTHORIZATION']));
}

// Terceira tentativa: busca em REDIRECT_HTTP_AUTHORIZATION (mod_rewrite/proxy)
if (!$idToken && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
    $idToken = preg_replace('/^Bearer\s+/i', '', trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']));
}

// Verifica se conseguiu extrair o token de alguma das fontes
if (!$idToken) {
    // Retorna 401 Unauthorized: cliente não enviou credenciais válidas
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token de autenticação não fornecido.']);
    exit;
}

try {
    /** @var UnencryptedToken $verifiedIdToken */
    // Valida o token JWT com o Firebase Auth
    // Verifica assinatura, expiração e revogação
    $verifiedIdToken = $auth->verifyIdToken($idToken);
    
    // A partir daqui, o token é considerado válido e seguro para uso.
    // As chamadas para ->claims() agora estão seguras.
    // Extrai o UID (identificador único do usuário no Firebase)
    $uid = $verifiedIdToken->claims()->get('sub');

    // Inicia sessão PHP armazenando dados básicos do Firebase
    $_SESSION['firebase_uid'] = $uid;
    $_SESSION['logged_in'] = true; // Flag indicando autenticação bem-sucedida
    $_SESSION['email'] = $verifiedIdToken->claims()->get('email');

    // Enriquece a sessão com dados complementares do banco de dados local
    // Firebase armazena apenas dados básicos; info adicional vem do MySQL
    $sql = "SELECT UsuNome, UsuCpf, UsuTel, UsuDataNasc, UsuFuncao FROM usuario WHERE UsuEmail = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        // Falha na preparação indica erro de sintaxe SQL ou problema de conexão
        error_log('Falha ao preparar statement: ' . $conn->error);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao preparar consulta de usuário.'
        ]);
        exit;
    }

    // Busca usuário pelo e-mail validado pelo Firebase
    $email = $_SESSION['email'];
    $stmt->bind_param('s', $email);
    $stmt->execute();
    // Vincula colunas do resultado a variáveis PHP
    $stmt->bind_result($nome, $cpf, $telefone, $dataNasc, $funcao);

    if ($stmt->fetch()) {
        // Usuário encontrado: armazena todos os dados na sessão
        $_SESSION['user_nome'] = $nome;
        $_SESSION['user_cpf'] = $cpf;
        $_SESSION['user_tel'] = $telefone;
        $_SESSION['user_data_nasc'] = $dataNasc;
        $_SESSION['user_funcao'] = $funcao; // Perfil/role (admin, cliente, etc)
    } else {
        // Token válido no Firebase mas usuário não existe no banco local
        // Isso pode indicar inconsistência entre sistemas
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Usuário não encontrado no banco de dados.'
        ]);
        $stmt->close();
        exit;
    }

    $stmt->close();

    // Login completo: retorna sucesso com o UID para o front-end
    echo json_encode([
        'success' => true,
        'message' => 'Sessão iniciada com sucesso.',
        'uid' => $uid // Permite ao front identificar o usuário logado
    ]);

} catch (ExpiredToken $e) {
    // Token expirado: usuário precisa fazer login novamente
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
} catch (RevokedIdToken $e) {
    // Token revogado: usuário ou admin invalidou a sessão manualmente
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão revogada. Faça login novamente.']);
} catch (FirebaseInvalidToken $e) {
    // Token com assinatura inválida, claims incorretos ou modificado
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token de autenticação inválido: ' . $e->getMessage()]);
} catch (AuthException $e) {
    // Outros erros de autenticação do Firebase (conectividade, config, etc)
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Erro de autenticação: ' . $e->getMessage()]);
} catch (\InvalidArgumentException $e) {
    // Token mal formatado ou argumentos inválidos passados às funções
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de token inválido: ' . $e->getMessage()]);
} catch (\Exception $e) {
    // Captura qualquer outro erro inesperado (banco, lógica, etc)
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}