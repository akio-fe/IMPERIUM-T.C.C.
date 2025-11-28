<?php
session_start(); // Inicia a sessão para podermos acessar o carrinho

include 'conn.php';

$sql = "SELECT RoupaId, RoupaNome, RoupaValor FROM Roupa";
$resultado = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nossos Produtos</title>
    <style>
        body { font-family: sans-serif; }
        .container-produtos { display: flex; flex-wrap: wrap; gap: 15px; }
        .produto { border: 1px solid #ccc; padding: 10px; width: 200px; display: flex; flex-direction: column; justify-content: space-between; }
        .produto h3 { margin-top: 0; height: 40px; overflow: hidden; font-size: 1em; }
        .produto p { font-weight: bold; color: #333; margin: 10px 0; }
        .produto button { width: 100%; padding: 8px; background-color: #28a745; color: white; border: none; cursor: pointer; }
        .produto button:hover { background-color: #218838; }
    </style>
</head>
<body>

    <h1>Nossos Produtos</h1>
    <a href="carrinho.php">Ver Carrinho (<?php echo isset($_SESSION['carrinhoProduto']) ? count($_SESSION['carrinhoProduto']) : 0; ?> itens)</a>
    <hr>

    <div class="container-produtos">
        <?php if ($resultado && $resultado->num_rows > 0): ?>
            <?php while($produto = $resultado->fetch_assoc()): ?>
                <div class="produto">
                    <h3><?php echo htmlspecialchars($produto['RoupaNome']); ?></h3>
                    <p>R$ <?php echo number_format($produto['RoupaValor'], 2, ',', '.'); ?></p>
                    <form action="adicionar_carrinho.php" method="post">
                        <input type="hidden" name="id_produto" value="<?php echo $produto['RoupaId']; ?>">
                        <input type="hidden" name="nome_produto" value="<?php echo htmlspecialchars($produto['RoupaNome']); ?>">
                        <input type="hidden" name="preco_produto" value="<?php echo $produto['RoupaValor']; ?>">
                        <button type="submit">Adicionar ao Carrinho</button>
                    </form>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Nenhum produto encontrado no momento.</p>
        <?php endif; ?>
    </div>

    <?php $conn->close(); // Fecha a conexão com o banco de dados ?>

</body>
</html>