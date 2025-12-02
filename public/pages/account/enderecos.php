<?php
/**
 * Página: Gerenciamento de Endereços
 * Propósito: Lista e permite gerenciar endereços de entrega do usuário.
 * 
 * Funcionalidades:
 * - Exibe todos os endereços cadastrados do usuário logado
 * - Links para adicionar novo endereço
 * - Botões para editar endereços existentes
 * - Formulário inline para exclusão de endereços (com confirmação)
 * - Sistema de notificações (flash messages) para feedback de operações
 * 
 * Validações:
 * - Requer autenticação (redireciona para login se não autenticado)
 * - Busca userId via email da sessão
 * - Tratamento de erros em consultas ao banco
 */

// Inicia sessão para acesso aos dados do usuário autenticado
session_start();
// Carrega configurações, helpers e conexão com banco de dados
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// URL da página de login para redirecionamento
$loginPage = url_path('public/pages/auth/login.php');

// Valida autenticação do usuário
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header('Location: ' . $loginPage);
  exit();
}

/**
 * Sanitiza campo para exibição segura em HTML.
 * 
 * Converte caracteres especiais em entidades HTML para prevenir
 * ataques XSS (Cross-Site Scripting).
 * 
 * @param mixed $value Valor a ser sanitizado (null será convertido para string vazia).
 * @return string Valor sanitizado e seguro para exibição.
 */
function sanitizeField($value)
{
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Inicializa variáveis com dados da sessão
$userEmail = $_SESSION['email'] ?? '';
$userName = $_SESSION['user_nome'] ?? 'Usuário';
$userId = null; // Será preenchido com consulta ao banco
$statusNotice = null; // Armazena notificação de status (sucesso/erro)
$loadError = ''; // Mensagem de erro no carregamento

// Busca o ID do usuário no banco através do email armazenado na sessão
if ($userEmail !== '') {
  // Prepara consulta segura para evitar SQL injection
  $stmtUser = $conn->prepare('SELECT UsuId FROM Usuario WHERE UsuEmail = ? LIMIT 1');
  if ($stmtUser) {
    $stmtUser->bind_param('s', $userEmail);
    $stmtUser->execute();
    $stmtUser->bind_result($foundUserId);
    if ($stmtUser->fetch()) {
      $userId = (int)$foundUserId;
    } else {
      $loadError = 'Usuário não encontrado no banco de dados.';
    }
    $stmtUser->close();
  } else {
    $loadError = 'Erro ao preparar consulta de usuário.';
  }
} else {
  // Email não está na sessão (situação anômala)
  $loadError = 'Sessão inválida. Faça login novamente.';
}

// Array para armazenar todos os endereços do usuário
$enderecos = [];
if ($userId !== null) {
  // Busca todos os endereços de entrega do usuário
  // ORDER BY DESC para mostrar endereços mais recentes primeiro
  $stmt = $conn->prepare('SELECT EndEntId, EndEntRef, EndEntRua, EndEntCep, EndEntNum, EndEntBairro, EndEntCid, EndEntEst, EndEntComple FROM EnderecoEntrega WHERE UsuId = ? ORDER BY EndEntId DESC');
  if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    // Itera sobre todos os endereços e adiciona ao array
    while ($row = $result->fetch_assoc()) {
      $enderecos[] = $row;
    }
    $stmt->close();
  } else {
    $loadError = 'Erro ao carregar os endereços. Tente novamente.';
  }
}

// Mapa de status para mensagens de feedback ao usuário
// Recebidos via query string após operações (adicionar, editar, excluir)
$statusMap = [
  'added' => ['type' => 'success', 'text' => 'Endereço adicionado com sucesso.'],
  'updated' => ['type' => 'success', 'text' => 'Endereço atualizado com sucesso.'],
  'deleted' => ['type' => 'success', 'text' => 'Endereço excluído com sucesso.'],
  'error' => ['type' => 'error', 'text' => 'Não foi possível concluir a operação.']
];

// Verifica se há parâmetro de status na URL (?status=added, ?status=updated, etc)
if (isset($_GET['status']) && isset($statusMap[$_GET['status']])) {
  $statusNotice = $statusMap[$_GET['status']];
}

// Se houve erro no carregamento, sobrescreve notificação
if ($loadError !== '') {
  $statusNotice = ['type' => 'error', 'text' => $loadError];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <title>Endereços</title>
  <!-- Fonte Google Inter para consistência visual -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <!-- Ícone da página exibido na aba do navegador -->
  <link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
  <!-- Estilos base do perfil: sidebar, layout, cards -->
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
  <!-- Estilos específicos para páginas de edição de conta -->
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/account-edit.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body>
  <button class="back-button" onclick="window.location.href='<?= htmlspecialchars(url_path('index.php'), ENT_QUOTES, 'UTF-8'); ?>'">← Voltar</button>
  <div class="sidebar">
    <div class="profile">
      <p>Olá!</p>
      <p><?php echo htmlspecialchars($_SESSION['user_nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <nav>
      <a href="<?= htmlspecialchars(url_path('public/pages/account/perfil.php'), ENT_QUOTES, 'UTF-8'); ?>">Dados pessoais</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8'); ?>" class="active">Endereços</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8'); ?>">Pedidos</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8'); ?>">Favoritos</a>
      <a href="<?= htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Sair</a>
    </nav>
  </div>

  <!-- Área principal com gerenciamento de endereços -->
  <div class="main-content">
    <h1>Endereços</h1>

    <?php if ($statusNotice) : ?>
      <!-- Exibe notificação de status se houver (sucesso ou erro) -->
      <!-- Classe dinâmica: status-success ou status-error -->
      <div class="status-message status-<?php echo sanitizeField($statusNotice['type']); ?>" >
        <?php echo sanitizeField($statusNotice['text']); ?>
      </div>
    <?php endif; ?>

    <!-- Botão de ação para adicionar novo endereço -->
    <div class="actions">
      <a class="button-link" href="addEnd.php" >Adicionar endereço</a>
    </div>

    <?php if (empty($enderecos)) : ?>
      <!-- Estado vazio: nenhum endereço cadastrado -->
      <div class="card empty-state">
        <p>Nenhum endereço cadastrado ainda.</p>
        <p>Use o botão "Adicionar endereço" para cadastrar o primeiro.</p>
      </div>
    <?php else : ?>
      <!-- Lista de endereços cadastrados -->
      <div class="info-section">
        <?php foreach ($enderecos as $endereco) : ?>
          <!-- Card individual de endereço -->
          <div class="card address-card">
            <!-- Referência/apelido do endereço (ex: "Casa", "Trabalho") -->
            <p><strong><?php echo sanitizeField($endereco['EndEntRef']); ?></strong></p>
            <p class="address-meta">
              <?php
              // Sanitiza todos os campos do endereço para exibição segura
              $rua = sanitizeField($endereco['EndEntRua']);
              $numero = sanitizeField($endereco['EndEntNum']);
              $bairro = sanitizeField($endereco['EndEntBairro']);
              $cidade = sanitizeField($endereco['EndEntCid']);
              $estado = sanitizeField($endereco['EndEntEst']);
              $cep = sanitizeField($endereco['EndEntCep']);
              $complemento = sanitizeField($endereco['EndEntComple']);
              ?>
              <!-- Formata endereço em formato legível -->
              <?php echo "$rua, $numero"; ?><br>
              <?php echo "$bairro - $cidade / $estado"; ?><br>
              CEP: <?php echo $cep; ?><br>
              <?php if ($complemento !== '') : ?>
                Complemento: <?php echo $complemento; ?>
              <?php endif; ?>
            </p>
            <!-- Ações disponíveis para cada endereço -->
            <div class="address-actions">
              <!-- Link para página de edição passando ID do endereço -->
              <a class="button-link" href="editarEnd.php?id=<?php echo (int)$endereco['EndEntId']; ?>" >Editar</a>
              <!-- Formulário inline para exclusão com confirmação JavaScript -->
              <form action="deletarEnd.php" method="POST" onsubmit="return confirm('Deseja realmente excluir este endereço?');">
                <input type="hidden" name="endereco_id" value="<?php echo (int)$endereco['EndEntId']; ?>">
                <button type="submit">Excluir</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // Restaura tema salvo no localStorage ao carregar a página
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
      document.body.classList.add('dark');
    }

    /**
     * Alterna entre tema claro e escuro.
     * Persiste preferência no localStorage.
     */
    function toggleTheme() {
      document.body.classList.toggle('dark');
      const theme = document.body.classList.contains('dark') ? 'dark' : 'light';
      localStorage.setItem('theme', theme);
    }
  </script>
  <script>
    // Aplica tema light se foi explicitamente salvo
    // (segundo script para compatibilidade)
    window.addEventListener('DOMContentLoaded', () => {
      const savedTheme = localStorage.getItem('theme');
      if (savedTheme === 'light') {
        document.body.classList.add('light');
      }
    });
  </script>

</body>

</html>