<?php
declare(strict_types=1);

session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para atualizar o carrinho.']);
    exit;
}

$userId = carrinhoGetSessionUserId($conn);
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
$itemId = isset($payload['itemId']) ? (int) $payload['itemId'] : 0;
$delta = isset($payload['delta']) ? (int) $payload['delta'] : null;
$quantidadeInformada = isset($payload['quantidade']) ? (int) $payload['quantidade'] : null;

if ($itemId <= 0 || ($delta === null && $quantidadeInformada === null)) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Dados inválidos para atualização.']);
    exit;
}

$conn->begin_transaction();

try {
    $stmtBusca = $conn->prepare('SELECT CarQtd FROM carrinho WHERE CarID = ? AND UsuId = ? FOR UPDATE');
    if (!$stmtBusca) {
        throw new RuntimeException('Erro ao preparar consulta do carrinho.');
    }

    $stmtBusca->bind_param('ii', $itemId, $userId);
    $stmtBusca->execute();
    $stmtBusca->bind_result($quantidadeAtual);
    if (!$stmtBusca->fetch()) {
        $stmtBusca->close();
        $conn->rollback();
        http_response_code(404);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Item não encontrado no carrinho.']);
        exit;
    }
    $stmtBusca->close();

    if ($quantidadeInformada !== null) {
        $novaQuantidade = max(1, $quantidadeInformada);
    } else {
        $delta = (int) $delta;
        if ($delta === 0) {
            throw new RuntimeException('Nada para atualizar.');
        }
        $novaQuantidade = max(1, $quantidadeAtual + $delta);
    }

    if ($novaQuantidade === (int) $quantidadeAtual) {
        $conn->commit();
        $dados = carrinhoFetchSnapshot($conn, $userId);
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Quantidade mantida.',
            'itens' => $dados['itens'],
            'subtotal' => $dados['subtotal'],
            'subtotalFormatado' => $dados['subtotalFormatado'],
        ]);
        exit;
    }

    $stmtAtualiza = $conn->prepare('UPDATE carrinho SET CarQtd = ? WHERE CarID = ? AND UsuId = ?');
    if (!$stmtAtualiza) {
        throw new RuntimeException('Erro ao preparar atualização do item.');
    }

    $stmtAtualiza->bind_param('iii', $novaQuantidade, $itemId, $userId);
    $stmtAtualiza->execute();
    $stmtAtualiza->close();

    $conn->commit();

    $dados = carrinhoFetchSnapshot($conn, $userId);

    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Quantidade atualizada.',
        'itens' => $dados['itens'],
        'subtotal' => $dados['subtotal'],
        'subtotalFormatado' => $dados['subtotalFormatado'],
    ]);
} catch (Throwable $exception) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Não foi possível atualizar o item do carrinho.',
        'detalhe' => $exception->getMessage(),
    ]);
}
