<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use Firebase\Auth\Token\Exception\ExpiredToken;
use Firebase\Auth\Token\Exception\InvalidToken as FirebaseInvalidToken;
use Kreait\Firebase\Exception\Auth\RevokedIdToken;
use Kreait\Firebase\Exception\AuthException;
use Kreait\Firebase\Exception\FirebaseException;
use Kreait\Firebase\Exception\Auth\UserNotFound;
use Kreait\Firebase\Factory;
use Lcobucci\JWT\UnencryptedToken;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
    respondJson(405, [
        'success' => false,
        'message' => 'Método não permitido. Utilize POST ou DELETE.'
    ]);
}

$serviceAccountPath = resolve_firebase_credentials_path();
if (!$serviceAccountPath) {
    respondJson(500, [
        'success' => false,
        'message' => 'Credenciais do Firebase não encontradas no servidor.'
    ]);
}

try {
    $auth = (new Factory())->withServiceAccount($serviceAccountPath)->createAuth();
} catch (\Throwable $e) {
    error_log('Falha ao inicializar Firebase Auth: ' . $e->getMessage());
    respondJson(500, [
        'success' => false,
        'message' => 'Erro ao inicializar autenticação do Firebase.'
    ]);
}

$idToken = extractBearerToken();
if (!$idToken) {
    respondJson(401, [
        'success' => false,
        'message' => 'Token de autenticação não fornecido.'
    ]);
}

try {
    /** @var UnencryptedToken $verifiedIdToken */
    $verifiedIdToken = $auth->verifyIdToken($idToken);
    $uid = $verifiedIdToken->claims()->get('sub');
    $emailVerified = (bool) ($verifiedIdToken->claims()->get('email_verified') ?? false);

    if (!$emailVerified) {
        respondJson(403, [
            'success' => false,
            'code' => 'EMAIL_NOT_VERIFIED',
            'message' => 'É necessário confirmar seu email antes de excluir a conta.'
        ]);
    }
} catch (ExpiredToken $e) {
    respondJson(401, ['success' => false, 'message' => 'Sessão expirada. Faça login novamente.']);
} catch (RevokedIdToken $e) {
    respondJson(401, ['success' => false, 'message' => 'Sessão revogada. Faça login novamente.']);
} catch (FirebaseInvalidToken $e) {
    respondJson(401, ['success' => false, 'message' => 'Token de autenticação inválido: ' . $e->getMessage()]);
} catch (AuthException $e) {
    respondJson(401, ['success' => false, 'message' => 'Erro de autenticação: ' . $e->getMessage()]);
} catch (\InvalidArgumentException $e) {
    respondJson(400, ['success' => false, 'message' => 'Token mal formatado: ' . $e->getMessage()]);
}

$stmt = $conn->prepare('SELECT UsuId FROM Usuario WHERE UsuUID = ? LIMIT 1');
if (!$stmt) {
    respondJson(500, ['success' => false, 'message' => 'Erro ao preparar consulta de usuário.']);
}

$stmt->bind_param('s', $uid);
$stmt->execute();
$stmt->bind_result($userId);

if (!$stmt->fetch()) {
    $stmt->close();
    try {
        $auth->deleteUser($uid);
    } catch (UserNotFound $e) {
        error_log('Usuário não encontrado no Firebase durante exclusão sem registro local: ' . $uid);
    } catch (AuthException | FirebaseException $e) {
        error_log('Falha ao remover usuário no Firebase (sem registro local): ' . $e->getMessage());
        respondJson(500, [
            'success' => false,
            'message' => 'Não foi possível remover sua conta no Firebase. Tente novamente mais tarde.',
            'details' => $e->getMessage()
        ]);
    }

    respondJson(200, [
        'success' => true,
        'message' => 'Conta removida do Firebase. Nenhum dado local foi encontrado.'
    ]);
}

$stmt->close();

try {
    $conn->begin_transaction();

    $deleteStmt = $conn->prepare('DELETE FROM Usuario WHERE UsuId = ?');
    if (!$deleteStmt) {
        throw new RuntimeException('Erro ao preparar exclusão do usuário: ' . $conn->error);
    }

    $deleteStmt->bind_param('i', $userId);
    $deleteStmt->execute();

    if ($deleteStmt->affected_rows === 0) {
        $deleteStmt->close();
        throw new RuntimeException('Falha ao excluir usuário. Nenhuma linha afetada.');
    }

    $deleteStmt->close();

    // Exclui o mesmo usuário no Firebase (só confirma após sucesso nas duas camadas).
    try {
        $auth->deleteUser($uid);
    } catch (UserNotFound $e) {
        // Já removido no Firebase, prossegue com o commit mantendo consistência local.
        error_log('Usuário já inexistente no Firebase: ' . $uid);
    }

    $conn->commit();
} catch (AuthException | FirebaseException $e) {
    $conn->rollback();
    error_log('Não foi possível remover usuário no Firebase: ' . $e->getMessage());
    respondJson(500, [
        'success' => false,
        'message' => 'Falha ao remover usuário no Firebase Authentication. Nenhum dado foi apagado.',
        'details' => $e->getMessage()
    ]);
} catch (\Throwable $e) {
    $conn->rollback();
    error_log('Erro ao excluir conta: ' . $e->getMessage());
    respondJson(500, [
        'success' => false,
        'message' => 'Erro ao excluir conta. Tente novamente mais tarde.'
    ]);
}

session_unset();
session_destroy();

respondJson(200, [
    'success' => true,
    'message' => 'Conta excluída com sucesso.'
]);

function respondJson(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function extractBearerToken(): ?string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];

    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            return preg_replace('/^Bearer\s+/i', '', trim((string) $value));
        }
    }

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return preg_replace('/^Bearer\s+/i', '', trim((string) $_SERVER['HTTP_AUTHORIZATION']));
    }

    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return preg_replace('/^Bearer\s+/i', '', trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']));
    }

    return null;
}
