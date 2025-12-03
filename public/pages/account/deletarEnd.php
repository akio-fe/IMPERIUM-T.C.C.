<?php
/**
 * Página: Exclusão de Endereço de Entrega
 * Propósito: Processa a exclusão de um endereço cadastrado pelo usuário.
 * 
 * Funcionalidades:
 * - Recebe ID do endereço via POST
 * - Valida autenticação do usuário
 * - Verifica se o endereço pertence ao usuário (segurança)
 * - Remove registro da tabela EnderecoEntrega
 * - Redireciona com mensagem de sucesso/erro
 * 
 * Segurança:
 * - Requer autenticação (sessão ativa)
 * - Aceita apenas requisições POST (evita exclusão via link)
 * - Prepared statements contra SQL injection
 * - Dupla validação: ID do endereço + ID do usuário no WHERE
 * 
 * Fluxo:
 * 1. Valida sessão do usuário
 * 2. Valida método POST
 * 3. Busca ID do usuário no banco via email
 * 4. Valida ID do endereço recebido
 * 5. Executa DELETE com prepared statement
 * 6. Redireciona com status (deleted ou error)
 */

// Inicia sessão para acesso aos dados do usuário autenticado
session_start();
// Carrega configurações, helpers e conexão com banco de dados
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// URLs para redirecionamentos
$loginPage = url_path('public/pages/auth/login.php');
$enderecosPage = url_path('public/pages/account/enderecos.php');

// Valida autenticação: redireciona para login se não autenticado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ' . $loginPage);
    exit();
}

// Valida método HTTP: aceita apenas POST
// GET seria inseguro pois permitiria exclusão via simples link/URL
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $enderecosPage);
    exit();
}

// Recupera email do usuário da sessão
$userEmail = $_SESSION['email'] ?? '';
$userId = null; // Será preenchido com consulta ao banco

// Busca o ID do usuário no banco através do email armazenado na sessão
if ($userEmail !== '') {
    // Prepara consulta segura para evitar SQL injection
    $stmtUser = $conn->prepare('SELECT UsuId FROM Usuario WHERE UsuEmail = ? LIMIT 1');
    if ($stmtUser) {
        // Vincula parâmetro: email do usuário
        $stmtUser->bind_param('s', $userEmail);
        $stmtUser->execute();
        $stmtUser->bind_result($foundId);
        // fetch() retorna true se encontrou resultado
        if ($stmtUser->fetch()) {
            $userId = (int)$foundId;
        }
        $stmtUser->close();
    }
}

// Se não conseguiu identificar o usuário, redireciona com erro
if ($userId === null) {
    header('Location: ' . $enderecosPage . '?status=error');
    exit();
}

// Captura ID do endereço a ser excluído (vem do campo hidden do formulário)
$addressId = isset($_POST['endereco_id']) ? (int)$_POST['endereco_id'] : 0;
// Valida se o ID é válido (maior que zero)
if ($addressId <= 0) {
    header('Location: ' . $enderecosPage . '?status=error');
    exit();
}

// Prepara DELETE com prepared statement para segurança
// WHERE com dupla condição: EndEntId E UsuId
// Isso garante que usuário só pode excluir seus próprios endereços
$stmtDelete = $conn->prepare('DELETE FROM EnderecoEntrega WHERE EndEntId = ? AND UsuId = ?');
if ($stmtDelete) {
    // Vincula parâmetros: ID do endereço e ID do usuário
    $stmtDelete->bind_param('ii', $addressId, $userId);
    $stmtDelete->execute();

    // Verifica se alguma linha foi afetada (deletada)
    if ($stmtDelete->affected_rows > 0) {
        // Exclusão bem-sucedida
        $stmtDelete->close();
        header('Location: ' . $enderecosPage . '?status=deleted');
        exit();
    }

    // Nenhuma linha foi deletada (endereço não existe ou não pertence ao usuário)
    $stmtDelete->close();
}

// Se chegou aqui, houve erro na exclusão
header('Location: ' . $enderecosPage . '?status=error');
exit();
