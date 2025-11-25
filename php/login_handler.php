<?php
// php/login_handler.php

// Mantém erros no log sem quebrar a resposta JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Corrected path for vendor/autoload.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/conn.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\ExpiredIdToken;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;
use Kreait\Firebase\Exception\Auth\InvalidToken;
use Kreait\Firebase\Exception\Auth\InvalidArgumentException;

// Corrige caminho do arquivo de credenciais do Firebase
$serviceAccountPath ='../../imperium-0001-firebase-adminsdk-fbsvc-ffc86182cf.json';

if (!file_exists($serviceAccountPath)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Arquivo de credenciais do Firebase não foi encontrado no servidor.'
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
    // 1. Tenta verificar o token
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

} catch (\Kreait\Firebase\Exception\Auth\ExpiredIdToken $e) {
    // Captura se o token expirou
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
} catch (RevokedIdToken $e) {
    // Captura se o token foi revogado
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sessão revogada. Faça login novamente.']);
} catch (\Kreait\Firebase\Exception\Auth\InvalidToken $e) {
    // Captura tokens inválidos de forma geral
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token de autenticação inválido: ' . $e->getMessage()]);
} catch (\Kreait\Firebase\Exception\AuthException $e) {
    // Captura outras exceções de autenticação
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Erro de autenticação: ' . $e->getMessage()]);
} catch (InvalidArgumentException $e) {
    // Captura tokens mal formatados ou outros erros de argumento
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de token inválido: ' . $e->getMessage()]);
} catch (\Exception $e) {
    // Captura qualquer outro erro inesperado
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}