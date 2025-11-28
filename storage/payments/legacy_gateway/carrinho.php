<?php
session_start();

// Pega o carrinho da sessão
$carrinho = $_SESSION['carrinhoProduto'] ?? [];

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Carrinho de Compras</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .total { font-weight: bold; font-size: 1.2em; }
        .actions { margin-top: 20px; }
        .checkout-button {
            display: inline-block; padding: 12px 25px; margin-bottom: 10px;
            background-color: #007bff; color: white; text-decoration: none;
            border-radius: 5px; font-weight: bold; text-align: center;
        }
        .checkout-button:hover { background-color: #0056b3; }
    </style>
</head>
<body>

    <h1>Meu Carrinho</h1>

    <?php if (empty($carrinho)): ?>
        <p>Seu carrinho está vazio.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Preço</th>
                    <th>Quantidade</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php $total = 0; ?>
                <?php foreach ($carrinho as $id => $produto): ?>
                    <?php $subtotal = $produto['preco'] * $produto['quantidade']; ?>
                    <?php $total += $subtotal; ?>
                    <tr>
                        <td><?php echo htmlspecialchars($produto['nome']); ?></td>
                        <td>R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></td>
                        <td><?php echo $produto['quantidade']; ?></td>
                        <td>R$ <?php echo number_format($subtotal, 2, ',', '.'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="total">Total do Pedido: R$ <?php echo number_format($total, 2, ',', '.'); ?></p>
        <div class="actions">
            <a href="criar_pagamento.php" class="checkout-button">Finalizar Compra</a>
            <p><a href="limpar_carrinho.php">Limpar Carrinho</a></p>
        </div>
    <?php endif; ?>

    <p><a href="index.php">Continuar Comprando</a></p>

</body>
</html>
