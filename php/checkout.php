<?php
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
require __DIR__ . '/../vendor/autoload.php';
include "conn.php"; // Inclua sua conexão com o banco de dados aqui.

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\Auth\InvalidToken;

// 2. Configurar o Firebase Admin SDK
try {
    // Busca a credencial da variável de ambiente
    $firebaseCredentials = getenv('IMPERIUM_FIREBASE');

    if ($firebaseCredentials === false) {
        throw new \Exception('A variável de ambiente IMPERIUM_FIREBASE não está definida.');
    }

    // Inicializa o Factory com o conteúdo da credencial
    $factory = (new Factory)->withServiceAccount($firebaseCredentials);
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
    $verifiedIdToken = $auth->verifyIdToken($idToken);
    $uid = $verifiedIdToken->claims()->get('sub');
} catch (InvalidToken $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token de autenticação inválido.']);
    exit;
}

// 4. Receber os dados do corpo da requisição
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

// 5. Validar os dados de entrada
if (!is_array($data) || !isset($data['uid'], $data['nome'], $data['sobrenome'], $data['cpf'], $data['tel'], $data['email'], $data['datanasc'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Dados de entrada incompletos ou inválidos.']);
    exit;
}

// 6. Verificação de segurança: garantir que o UID do token corresponde ao UID dos dados
if ($uid !== $data['uid']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'UID do token não corresponde ao UID enviado.']);
    exit;
}

// 7. Sanitizar e preparar os dados para o banco de dados
$usuUid = $data['uid'];
$usuNome = htmlspecialchars(trim($data['nome']));
$usuSobrenome = htmlspecialchars(trim($data['sobrenome']));
$usuCpf = htmlspecialchars(trim($data['cpf']));
$usuTel = htmlspecialchars(trim($data['tel']));
$usuEmail = htmlspecialchars(trim($data['email']));
$usuDataNasc = htmlspecialchars(trim($data['datanasc']));
$loginNome = $usuNome . $usuSobrenome;

// 8. Inserir os dados no MySQL
try {
    // Verificação de duplicidade de CPF e Telefone
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM usuario WHERE UsuCpf = ? OR UsuTel = ?");
    $stmt_check->bind_param("ss", $usuCpf, $usuTel);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Telefone ou CPF já cadastrado.']);
        exit;
    }

    // Inserção dos dados
    $stmt_insert = $conn->prepare("
        INSERT INTO Usuario (
            UsuSenha, UsuNome, UsuSobrenome, UsuCpf, UsuTel, UsuEmail, UsuDataNasc, UsuLoginNome
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt_insert->bind_param(
        "ssssssss",
        $usuUid,
        $usuNome,
        $usuSobrenome,
        $usuCpf,
        $usuTel,
        $usuEmail,
        $usuDataNasc,
        $loginNome
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
