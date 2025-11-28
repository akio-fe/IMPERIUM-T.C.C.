<?php
// php/login_handler.php

// Mantém erros no log sem quebrar a resposta JSON
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

use Firebase\Auth\Token\Exception\ExpiredToken;
use Firebase\Auth\Token\Exception\InvalidToken as FirebaseInvalidToken;
use Kreait\Firebase\Exception\AuthException;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;
use Kreait\Firebase\Factory;
use Lcobucci\JWT\UnencryptedToken;

// Descobre o caminho do arquivo de credenciais do Firebase
$serviceAccountPath = resolve_firebase_credentials_path();

if (!$serviceAccountPath) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Arquivo de credenciais do Firebase não foi encontrado no servidor. Defina FIREBASE_CREDENTIALS ou coloque o JSON em storage/credentials.'
    ]);
    exit;
}

try {
    $factory = (new Factory)->withServiceAccount($serviceAccountPath);
    $auth = $factory->createAuth();
} catch (\Throwable $e) {
    error_log('Falha ao inicializar Firebase Auth: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao inicializar autenticação do Firebase no servidor.'
    ]);
    exit;
}

$headers = getallheaders();
$idToken = null;

if (isset($headers['Authorization'])) {
    $idToken = str_replace('Bearer ', '', $headers['Authorization']);
}

if (!$idToken) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token de autenticação não fornecido.']);
    exit;
}

try {
    /** @var UnencryptedToken $verifiedIdToken */
    $verifiedIdToken = $auth->verifyIdToken($idToken);
    
    // A partir daqui, o token é considerado válido e seguro para uso.
    // As chamadas para ->claims() agora estão seguras.
    $uid = $verifiedIdToken->claims()->get('sub');

    // 2. O token é válido, inicia a sessão do PHP
    $_SESSION['firebase_uid'] = $uid;
    $_SESSION['logged_in'] = true;
    $_SESSION['email'] = $verifiedIdToken->claims()->get('email');

    // 3. adiciona outras informações na sessão vindo do banco de dados
    $sql = "SELECT UsuNome, UsuCpf, UsuTel, UsuDataNasc, UsuFuncao FROM usuario WHERE UsuEmail = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        error_log('Falha ao preparar statement: ' . $conn->error);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao preparar consulta de usuário.'
        ]);
        exit;
    }

    $email = $_SESSION['email'];
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($nome, $cpf, $telefone, $dataNasc, $funcao);

    if ($stmt->fetch()) {
        $_SESSION['user_nome'] = $nome;
        $_SESSION['user_cpf'] = $cpf;
        $_SESSION['user_tel'] = $telefone;
        $_SESSION['user_data_nasc'] = $dataNasc;
        $_SESSION['user_funcao'] = $funcao;
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Usuário não encontrado no banco de dados.'
        ]);
        $stmt->close();
        exit;
    }

    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'Sessão iniciada com sucesso.',
        'uid' => $uid
    ]);

} catch (ExpiredToken $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
} catch (RevokedIdToken $e) {
    // Captura se o token foi revogado
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão revogada. Faça login novamente.']);
} catch (FirebaseInvalidToken $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token de autenticação inválido: ' . $e->getMessage()]);
} catch (AuthException $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Erro de autenticação: ' . $e->getMessage()]);
} catch (\InvalidArgumentException $e) {
    // Captura tokens mal formatados ou outros erros de argumento
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de token inválido: ' . $e->getMessage()]);
} catch (\Exception $e) {
    // Captura qualquer outro erro inesperado
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}