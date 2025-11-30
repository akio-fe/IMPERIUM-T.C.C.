<?php
declare(strict_types=1);

session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/../carrinho/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$enderecos = [];

$stmt = $conn->prepare(
    'SELECT EndEntId, EndEntRef, EndEntRua, EndEntCep, EndEntNum, EndEntBairro, EndEntCid, EndEntEst, EndEntComple
     FROM EnderecoEntrega
     WHERE UsuId = ?
     ORDER BY EndEntId DESC'
);

if ($stmt) {
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
        $resultado = $stmt->get_result();
        while ($row = $resultado->fetch_assoc()) {
            $enderecos[] = [
                'id' => (int) $row['EndEntId'],
                'referencia' => (string) $row['EndEntRef'],
                'rua' => (string) $row['EndEntRua'],
                'cep' => (string) $row['EndEntCep'],
                'numero' => (string) $row['EndEntNum'],
                'bairro' => (string) $row['EndEntBairro'],
                'cidade' => (string) $row['EndEntCid'],
                'estado' => (string) $row['EndEntEst'],
                'complemento' => (string) ($row['EndEntComple'] ?? ''),
            ];
        }
        $resultado->free();
    } else {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível carregar seus endereços.']);
        exit;
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Falha ao preparar a consulta de endereços.']);
    exit;
}

echo json_encode([
    'sucesso' => true,
    'enderecos' => $enderecos,
]);
