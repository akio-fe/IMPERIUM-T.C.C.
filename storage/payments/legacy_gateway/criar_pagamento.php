<?php
session_start(); // Inicia a sessão para acessar o carrinho

// Se o carrinho estiver vazio, não há o que pagar. Redireciona para a home.
if (empty($_SESSION['carrinhoProduto'])) {
    header('Location: index.php');
    exit();
}
// Carrega a biblioteca do Mercado Pago
require_once 'vendor/autoload.php';

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Exceptions\MPApiException;

// --- CONFIGURAÇÃO MERCADO PAGO ---
// Substitua 'SEU_ACCESS_TOKEN' pelo seu Access Token real do Mercado Pago
MercadoPagoConfig::setAccessToken("APP_USR-7782860401605497-090309-57dc3a818483897b864f86391571914e-2659102716");

// --- PREPARA OS ITENS PARA O PAGAMENTO ---
// Cria um array vazio para os itens
$items = [];
// Percorre os produtos no carrinho da sessão
foreach ($_SESSION['carrinhoProduto'] as $produto) {
    // Adiciona cada produto ao array de itens no formato que o Mercado Pago espera
    $items[] = [
        "title" => $produto['nome'],
        "quantity" => $produto['quantidade'],
        "unit_price" => $produto['preco'],
        "currency_id" => "BRL"
    ];
}

// Cria um cliente de preferência
$client = new PreferenceClient();

try {
    // Cria a preferência de pagamento com os itens do carrinho
    $preference = $client->create([
        "items" => $items, // Usa o array de itens criado dinamicamente
        "back_urls" => array(
            "success" => "https://090748e8b871.ngrok-free.app/pagamento/sucesso.php",
            "failure" => "https://090748e8b871.ngrok-free.app/Pagamento/falha.php", // Corrigido para a página de falha
            "pending" => "https://090748e8b871.ngrok-free.app/Pagamento/falha.php", // Pode criar uma página de pendente também
        ),
        "auto_return" => "approved"
    ]);

    // Redireciona o usuário para o link de pagamento gerado
    header("Location: " . $preference->init_point);
    exit();

} catch (MPApiException $e) {
    // O erro "Api error" vem daqui. Vamos detalhar a resposta da API para depuração.
    echo "Ocorreu um erro na API do Mercado Pago ao criar a preferência de pagamento.<br>";
    echo "<strong>Status Code:</strong> " . $e->getApiResponse()->getStatusCode() . "<br>";
    echo "<strong>Resposta da API:</strong><br>";
    echo "<pre>";
    print_r($e->getApiResponse()->getContent());
    echo "</pre>";
} catch (Exception $e) {
    // Em um ambiente de produção, é melhor logar o erro do que exibi-lo na tela.
    echo "Ocorreu um erro ao criar a preferência de pagamento: " . $e->getMessage();
}
