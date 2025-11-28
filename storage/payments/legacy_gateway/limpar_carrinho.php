<?php
session_start();

// Remove a variável do carrinho da sessão
unset($_SESSION['carrinhoProduto']);

// Redireciona de volta para a página do carrinho (que agora estará vazia)
header('Location: carrinho.php');
exit();
