<?php
session_start(); // Sempre inicie a sessão no topo

// Verifica se os dados do produto foram enviados via POST
if (isset($_POST['id_produto'], $_POST['nome_produto'], $_POST['preco_produto'])) {

    $id_produto = $_POST['id_produto'];
    $nome_produto = $_POST['nome_produto'];
    $preco_produto = (float)$_POST['preco_produto'];
    $quantidade = 1; // Quantidade padrão ao adicionar

    // Inicializa o carrinho na sessão se ele ainda não existir
    if (!isset($_SESSION['carrinhoProduto'])) {
        $_SESSION['carrinhoProduto'] = [];
    }

    // Verifica se o produto já está no carrinho
    if (isset($_SESSION['carrinhoProduto'][$id_produto])) {
        // Se sim, apenas aumenta a quantidade
        $_SESSION['carrinhoProduto'][$id_produto]['quantidade']++;
    } else {
        // Se não, adiciona o produto ao carrinho
        $_SESSION['carrinhoProduto'][$id_produto] = [
            'nome' => $nome_produto,
            'preco' => $preco_produto,
            'quantidade' => $quantidade
        ];
    }

    // Redireciona o usuário para a página do carrinho para ele ver o que adicionou
    header('Location: index.php');
    exit();

} else {
    // Se alguém tentar acessar este arquivo diretamente sem enviar dados, redireciona para a index
    header('Location: index.php');
    exit();
}
