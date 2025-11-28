<?php
declare(strict_types=1);

session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

function getSessionUserId(mysqli $conn): ?int
{
    $email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';
    if ($email === '') {
        return null;
    }

    $stmt = $conn->prepare  ('SELECT UsuId FROM usuario WHERE UsuEmail = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($userId);
    $found = $stmt->fetch() ? (int) $userId : null;
    $stmt->close();

    return $found;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para adicionar itens ao carrinho.']);
    exit;
}

$userId = getSessionUserId($conn);
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
$produtoId = isset($payload['produtoId']) ? (int) $payload['produtoId'] : 0;
$quantidade = isset($payload['quantidade']) ? (int) $payload['quantidade'] : 1;
$tamanho = isset($payload['tamanho']) ? strtoupper(trim((string) $payload['tamanho'])) : '';

if ($produtoId <= 0 || $quantidade <= 0 || $tamanho === '' || strlen($tamanho) > 10) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos para o carrinho.']);
    exit;
}

$stmtProduto = $conn->prepare('SELECT RoupaValor FROM roupa WHERE RoupaId = ? LIMIT 1');
if (!$stmtProduto) {
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro ao preparar consulta do produto.']);
    exit;
}

$stmtProduto->bind_param('i', $produtoId);
$stmtProduto->execute();
$stmtProduto->bind_result($valorUnitario);
if (!$stmtProduto->fetch()) {
    $stmtProduto->close();
    http_response_code(404);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Produto não encontrado.']);
    exit;
}
$stmtProduto->close();

$precoUnitario = (float) $valorUnitario;

$conn->begin_transaction();

try {
    $stmtBusca = $conn->prepare('SELECT CarID, CarQtd FROM carrinho WHERE RoupaId = ? AND UsuId = ? AND CarTam = ? FOR UPDATE');
    if (!$stmtBusca) {
        throw new RuntimeException('Erro ao preparar verificação do carrinho.');
    }

    $stmtBusca->bind_param('iis', $produtoId, $userId, $tamanho);
    $stmtBusca->execute();
    $stmtBusca->bind_result($carId, $quantidadeAtual);
    $existe = $stmtBusca->fetch();
    $stmtBusca->close();

    if ($existe) {
        $novaQuantidade = $quantidadeAtual + $quantidade;
        $stmtUpdate = $conn->prepare('UPDATE carrinho SET CarQtd = ?, CarPreco = ? WHERE CarID = ?');
        if (!$stmtUpdate) {
            throw new RuntimeException('Erro ao preparar atualização do carrinho.');
        }
        $stmtUpdate->bind_param('idi', $novaQuantidade, $precoUnitario, $carId);
        $stmtUpdate->execute();
        $stmtUpdate->close();
    } else {
        $stmtInsert = $conn->prepare('INSERT INTO carrinho (CarQtd, CarPreco, CarTam, RoupaId, UsuId) VALUES (?, ?, ?, ?, ?)');
        if (!$stmtInsert) {
            throw new RuntimeException('Erro ao preparar inserção no carrinho.');
        }
        $stmtInsert->bind_param('idsii', $quantidade, $precoUnitario, $tamanho, $produtoId, $userId);
        $stmtInsert->execute();
        $stmtInsert->close();
    }

    $conn->commit();

    echo json_encode(['sucesso' => true, 'mensagem' => 'Produto adicionado ao carrinho.']);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Não foi possível salvar o item no carrinho.',
        'detalhe' => $exception->getMessage(),
    ]);
}
