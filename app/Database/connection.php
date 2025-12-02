<?php
/**
 * Arquivo: connection.php
 * Propósito: Estabelecer conexão com banco de dados MySQL/MariaDB
 * 
 * Este arquivo é o ponto central de acesso ao banco de dados da aplicação.
 * Deve ser incluído em todos os scripts que necessitam interagir com o banco.
 * A conexão é mantida durante toda a execução do script PHP.
 */

// ===== CONFIGURAÇÕES DE CONEXÃO =====
// Endereço do servidor MySQL (localhost para servidor local, ou IP/domínio para servidor remoto)
$servername = "localhost";

// Nome de usuário do banco de dados (padrão do Laragon/XAMPP é 'root')
$username = "root";

// Senha do banco de dados (vazia por padrão em ambientes de desenvolvimento local)
$password = "";

// Nome do banco de dados específico da aplicação IMPERIUM
$database = "imperium";

// ===== ESTABELECIMENTO DA CONEXÃO =====
/**
 * Cria instância MySQLi usando o construtor com parâmetros de conexão.
 * MySQLi (MySQL Improved) oferece:
 * - Suporte a prepared statements (prevenção de SQL injection)
 * - Suporte a transações
 * - Melhor performance que a extensão mysql antiga
 * 
 * @var mysqli $conn Objeto de conexão usado globalmente na aplicação
 */
$conn = new mysqli($servername, $username, $password, $database);

// ===== TRATAMENTO DE ERRO DE CONEXÃO =====
/**
 * Verifica se houve erro na tentativa de conexão.
 * Em caso de falha, interrompe a execução e exibe mensagem de erro.
 * 
 * Erros comuns:
 * - Servidor MySQL não iniciado
 * - Credenciais incorretas
 * - Banco de dados não existe
 * - Firewall bloqueando conexão
 */
if ($conn->connect_error) {
    // die() interrompe imediatamente a execução do script
    // Em produção, considere registrar erro em log ao invés de exibir detalhes
    die("Conexão falhou: " . $conn->connect_error);
}

// ===== FUNÇÃO AUXILIAR PARA MENSAGENS =====
/**
 * Exibe mensagens de feedback ao usuário usando classes Bootstrap.
 * 
 * Esta função gera HTML com classes CSS do Bootstrap para exibir
 * alertas visuais na interface. Útil para páginas legadas que não
 * usam resposta JSON.
 * 
 * @param bool $success Indica se é mensagem de sucesso (true) ou erro (false)
 * @param string $message Texto da mensagem a ser exibida ao usuário
 * 
 * @return void Imprime HTML diretamente na saída
 * 
 * @example showMessage(true, "Produto adicionado com sucesso!");
 *          // Gera: <div class='alert alert-success' role='alert'>Produto adicionado com sucesso!</div>
 * 
 * @example showMessage(false, "Erro ao processar pagamento.");
 *          // Gera: <div class='alert alert-danger' role='alert'>Erro ao processar pagamento.</div>
 */
function showMessage($success, $message) {
    // Define classe CSS baseada no tipo de mensagem
    // 'success' = verde (operação bem-sucedida)
    // 'danger' = vermelho (erro ou falha)
    $status = $success ? "success" : "danger";
    
    // Imprime div com classes Bootstrap para estilização automática
    // role='alert' melhora acessibilidade para leitores de tela
    echo "<div class='alert alert-$status' role='alert'>$message</div>";
}
?>