<?php
declare(strict_types=1);

function carrinhoGetSessionUserId(mysqli $conn): ?int
{
    $email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';
    if ($email === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT UsuId FROM usuario WHERE UsuEmail = ? LIMIT 1');
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

function carrinhoFormatMoney(float $valor): string
{
    return number_format($valor, 2, ',', '.');
}

function carrinhoAssetPath(string $path): string
{
    if (function_exists('asset_path')) {
        return asset_path($path);
    }
    return $path;
}

function carrinhoFetchSnapshot(mysqli $conn, int $userId): array
{
    $dados = [
        'itens' => [],
        'subtotal' => 0.0,
    ];

    $sql = 'SELECT c.CarID, c.CarQtd, c.CarPreco, c.CarTam, c.RoupaId, r.RoupaNome, r.RoupaImgUrl
            FROM carrinho c
            INNER JOIN roupa r ON r.RoupaId = c.RoupaId
            WHERE c.UsuId = ?
            ORDER BY c.CarID DESC';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $resultado = $stmt->get_result();
            while ($row = $resultado->fetch_assoc()) {
                $quantidade = (int) $row['CarQtd'];
                $precoUnitario = (float) $row['CarPreco'];
                $itemTotal = $quantidade * $precoUnitario;

                $dados['itens'][] = [
                    'id' => (int) $row['CarID'],
                    'produtoId' => (int) $row['RoupaId'],
                    'nome' => $row['RoupaNome'],
                    'imagem' => carrinhoAssetPath((string) $row['RoupaImgUrl']),
                    'quantidade' => $quantidade,
                    'tamanho' => $row['CarTam'] ?? '',
                    'precoUnitario' => $precoUnitario,
                    'precoFormatado' => carrinhoFormatMoney($precoUnitario),
                    'total' => $itemTotal,
                    'totalFormatado' => carrinhoFormatMoney($itemTotal),
                ];

                $dados['subtotal'] += $itemTotal;
            }
            $resultado->free();
        }
        $stmt->close();
    }

    $dados['subtotalFormatado'] = carrinhoFormatMoney($dados['subtotal']);

    return $dados;
}
