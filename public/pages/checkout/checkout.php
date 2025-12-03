<?php
/**
 * Endpoint: api/cadastro.php
<?php

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
// api/cadastro.php
// Este é o endpoint que recebe os dados do usuário APÓS a verificação do e-mail.

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Apenas para ambiente de desenvolvimento.
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Trata requisições OPTIONS (usadas pelo navegador para verificar permissões de CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Incluir as dependências e a conexão com o banco de dados
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use Firebase\Auth\Token\Exception\ExpiredToken;
use Firebase\Auth\Token\Exception\InvalidToken as FirebaseInvalidToken;
use Kreait\Firebase\Factory;
use Lcobucci\JWT\UnencryptedToken;

// 2. Configurar o Firebase Admin SDK
try {
    $credentialsPath = resolve_firebase_credentials_path();

    if (!$credentialsPath) {
        throw new \RuntimeException('Credenciais do Firebase não foram encontradas. Configure FIREBASE_CREDENTIALS ou armazene o JSON em storage/credentials/.');
    }

    $factory = (new Factory)->withServiceAccount($credentialsPath);
    $auth = $factory->createAuth();
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro na configuração do Firebase: ' . $e->getMessage()]);
    exit;
}

// 3. Receber e verificar o ID Token
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
    $uid = $verifiedIdToken->claims()->get('sub');
} catch (ExpiredToken $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token expirado. Faça login novamente.']);
    exit;
} catch (FirebaseInvalidToken $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token de autenticação inválido.']);
    exit;
}

// 4. Receber os dados do corpo da requisição
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

// 5. Validar os dados de entrada (sobrenome pode não vir do Firestore)
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de payload inválido.']);
    exit;
}

$requiredFields = ['uid', 'nome', 'cpf', 'email'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => sprintf('Campo obrigatório ausente ou vazio: %s.', $field)
        ]);
        exit;
    }
}

// 6. Verificação de segurança: garantir que o UID do token corresponde ao UID dos dados
if ($uid !== $data['uid']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'UID do token não corresponde ao UID enviado.']);
    exit;
}

// 7. Sanitizar e preparar os dados para o banco de dados
$usuUid = trim($data['uid']);
$usuNome = htmlspecialchars(trim($data['nome']));
$usuCpf = preg_replace('/\D/', '', $data['cpf']);
$usuEmail = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);

$usuTel = null;
if (isset($data['tel'])) {
    $cleanTel = preg_replace('/\D/', '', $data['tel']);
    if ($cleanTel !== '') {
        $usuTel = $cleanTel;
    }
}

$usuDataNasc = null;
if (isset($data['datanasc'])) {
    $rawDate = trim($data['datanasc']);
    if ($rawDate !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $rawDate);
        if (!$date) {
            $date = DateTime::createFromFormat('d/m/Y', $rawDate);
        }

        if (!$date) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Formato de data de nascimento inválido.']);
            exit;
        }

        $usuDataNasc = $date->format('Y-m-d');
    }
}

// 8. Inserir os dados no MySQL
try {
    // Verificação de duplicidade (CPF e e-mail são obrigatórios; telefone só quando informado)
    $duplicateSql = "SELECT COUNT(*) FROM usuario WHERE UsuCpf = ? OR UsuEmail = ?";
    $duplicateTypes = "ss";
    $duplicateValues = [$usuCpf, $usuEmail];

    if ($usuTel !== null) {
        $duplicateSql .= " OR UsuTel = ?";
        $duplicateTypes .= "s";
        $duplicateValues[] = $usuTel;
    }

    $stmt_check = $conn->prepare($duplicateSql);
    if (!$stmt_check) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar validação de duplicidade: ' . $conn->error]);
        exit;
    }

    $bindParams = [$duplicateTypes];
    foreach ($duplicateValues as $key => $value) {
        $bindParams[] = &$duplicateValues[$key];
    }
    call_user_func_array([$stmt_check, 'bind_param'], $bindParams);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'CPF, e-mail ou telefone já cadastrado.']);
        exit;
    }

    // Inserção dos dados
    $stmt_insert = $conn->prepare("\n        INSERT INTO usuario (\n            UsuUID, UsuEmail, UsuNome, UsuCpf, UsuTel, UsuDataNasc\n        ) VALUES (?, ?, ?, ?, ?, ?)");

    if (!$stmt_insert) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao preparar inserção do usuário: ' . $conn->error]);
        exit;
    }

    $stmt_insert->bind_param(
        "ssssss",
        $usuUid,
        $usuEmail,
        $usuNome,
        $usuCpf,
        $usuTel,
        $usuDataNasc
    );

    if ($stmt_insert->execute()) {
        http_response_code(201); // Created
        echo json_encode(['success' => true, 'message' => 'Cliente inserido com sucesso.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao inserir cliente: ' . $stmt_insert->error]);
    }
    $stmt_insert->close();
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
