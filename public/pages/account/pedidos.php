<?php
// Inicia a sessão para acessar dados do usuário autenticado
session_start();
// Carrega configurações, helpers e conexão com o banco de dados
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// Caminho da página de login para redirecionamentos
$loginPage = url_path('public/pages/auth/login.php');

// Verifica se o usuário está autenticado, caso contrário redireciona para o login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header('Location: ' . $loginPage);
  exit();
}

/**
 * Recupera o ID do usuário da sessão ou busca no banco via e-mail.
 * 
 * Tenta primeiro usar o user_id da sessão. Se não existir, faz uma consulta
 * preparada ao banco usando o e-mail armazenado na sessão.
 * 
 * @param mysqli $conn Conexão ativa com o banco de dados.
 * @return int|null ID do usuário encontrado ou null se não localizado.
 */
function getSessionUserId(mysqli $conn): ?int
{
  // Verifica se o ID já está disponível na sessão
  if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
    return (int) $_SESSION['user_id'];
  }

  // Tenta recuperar o e-mail da sessão
  $email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';
  if ($email === '') {
    return null;
  }

  // Prepara consulta para buscar o ID pelo e-mail
  $stmt = $conn->prepare('SELECT UsuId FROM usuario WHERE UsuEmail = ? LIMIT 1');
  if (!$stmt) {
    return null;
  }

  $stmt->bind_param('s', $email);
  $stmt->execute();
  $stmt->bind_result($userId);
  $foundId = $stmt->fetch() ? (int) $userId : null;
  $stmt->close();

  return $foundId;
}

// Obtém o ID do usuário logado
$userId = getSessionUserId($conn);
if ($userId === null) {
  // Redireciona para o login se não conseguir identificar o usuário
  header('Location: ' . $loginPage);
  exit();
}

// Sistema de mensagens flash (exibidas uma única vez após redirecionamento)
$flashData = $_SESSION['pedidos_flash'] ?? null;
$flashMessage = '';
$flashType = 'info';
if ($flashData) {
  $flashMessage = (string) ($flashData['mensagem'] ?? '');
  $flashType = (string) ($flashData['tipo'] ?? 'info');
  // Remove a mensagem da sessão após leitura
  unset($_SESSION['pedidos_flash']);
}

// Estruturas para armazenar pedidos e possíveis mensagens de erro
$pedidos = [];
$erroPedidos = '';

// Consulta que busca todos os pedidos do usuário com informações de entrega
// Ordena do mais recente para o mais antigo
$sql = 'SELECT p.PedId, p.PedData, p.PedValorTotal, p.PedStatus,
               e.EndEntRua, e.EndEntNum, e.EndEntBairro, e.EndEntCid, e.EndEntEst, e.EndEntCep
        FROM pedido p
        INNER JOIN enderecoentrega e ON p.EndEntId = e.EndEntId
        WHERE p.UsuId = ?
        ORDER BY p.PedData DESC';

// Prepara e executa a consulta de forma segura usando prepared statements
$stmt = $conn->prepare($sql);
if ($stmt) {
  // Vincula o ID do usuário ao placeholder da query
  $stmt->bind_param('i', $userId);
  if ($stmt->execute()) {
    $resultado = $stmt->get_result();
    // Itera sobre cada pedido retornado e armazena no array
    while ($row = $resultado->fetch_assoc()) {
      $pedidos[] = $row;
    }
    $resultado->free();
  } else {
    $erroPedidos = 'Não foi possível carregar seus pedidos. Tente novamente em instantes.';
  }
  $stmt->close();
} else {
  $erroPedidos = 'Não foi possível acessar seus pedidos neste momento.';
}

/**
 * Converte o código numérico do status do pedido em texto legível.
 * 
 * Mapeia os valores armazenados no banco para descrições amigáveis
 * exibidas na interface do usuário.
 * 
 * @param int $status Código numérico do status (0-3).
 * @return string Descrição textual do status.
 */
function statusPedido(int $status): string
{
  switch ($status) {
    case 0:
      return 'Aguardando pagamento';
    case 1:
      return 'Processando';
    case 2:
      return 'Enviado';
    case 3:
      return 'Entregue';
    default:
      return 'Desconhecido';
  }
}

// Prepara todas as variáveis de exibição sanitizadas para prevenir XSS
$userName = htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8');
// URLs da navegação principal da área logada
$homeUrl = htmlspecialchars(url_path('index.php'), ENT_QUOTES, 'UTF-8');
$perfilUrl = htmlspecialchars(url_path('public/pages/account/perfil.php'), ENT_QUOTES, 'UTF-8');
$enderecosUrl = htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8');
$pedidosUrl = htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8');
$favoritosUrl = htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8');
$logoutUrl = htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8');
// URLs específicas para ações de pedidos
$detalheBaseUrl = url_path('public/pages/account/pedidoProdutos.php');
$reemitirPagamentoUrl = htmlspecialchars(url_path('public/api/checkout/reemitir_pagamento.php'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <title>Pedidos</title>
  <!-- Fontes do Google e recursos visuais globais -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
  <!-- Estilos base do perfil e específicos da tela de pedidos -->
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/pedidos.css'), ENT_QUOTES, 'UTF-8'); ?>">

</head>

<body>
  <!-- Botão fixo para retornar à página inicial -->
  <button class="back-button" onclick="window.location.href='<?= $homeUrl ?>'" >← Voltar</button>
  <!-- Barra lateral com perfil e navegação da conta -->
  <div class="sidebar">
    <div class="profile">
      <p>Olá!</p>
      <p><?= $userName ?></p>
    </div>
    <!-- Menu de navegação entre seções da conta do usuário -->
    <nav>
      <a href="<?= $perfilUrl ?>">Dados pessoais</a>
      <a href="<?= $enderecosUrl ?>">Endereços</a>
      <a href="<?= $pedidosUrl ?>" class="active">Pedidos</a>
      <a href="<?= $favoritosUrl ?>">Favoritos</a>
      <a href="<?= $logoutUrl ?>">Sair</a>
    </nav>
  </div>
  <!-- Área principal que exibe a lista de pedidos -->
  <div class="main-content">
    <h1>Pedidos</h1>
    <?php if ($flashMessage !== ''): ?>
      <?php
      // Define a classe CSS apropriada baseada no tipo de mensagem
      $flashClass = '';
      if ($flashType === 'erro') {
        $flashClass = 'estado-erro';
      } elseif ($flashType === 'sucesso') {
        $flashClass = 'estado-sucesso';
      }
      ?>
      <!-- Mensagem flash temporária exibida após operações -->
      <div class="status-message <?= $flashClass ?>"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($erroPedidos !== ''): ?>
      <!-- Exibe mensagem de erro caso a consulta ao banco tenha falhado -->
      <div class="status-message estado-erro"><?= htmlspecialchars($erroPedidos, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (empty($pedidos)): ?>
      <!-- Informa quando o usuário ainda não possui pedidos -->
      <div class="status-message">Nenhum pedido realizado.</div>
    <?php else: ?>
      <!-- Grade de cards exibindo todos os pedidos do usuário -->
      <div class="card-grid">
        <?php foreach ($pedidos as $pedido): ?>
          <!-- Card individual de cada pedido com informações resumidas -->
          <div class="order-card">
            <h2>Pedido #<?= (int) $pedido['PedId'] ?></h2>
            <!-- Data e hora do pedido formatadas para o padrão brasileiro -->
            <p><strong>Data:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($pedido['PedData'])), ENT_QUOTES, 'UTF-8') ?></p>
            <!-- Valor total formatado com separadores brasileiros -->
            <p><strong>Valor Total:</strong> R$ <?= number_format((float) $pedido['PedValorTotal'], 2, ',', '.') ?></p>
            <!-- Status convertido para texto legível -->
            <p><strong>Status:</strong> <?= htmlspecialchars(statusPedido((int) $pedido['PedStatus']), ENT_QUOTES, 'UTF-8') ?></p>
            <!-- Endereço completo de entrega -->
            <p><strong>Entrega:</strong> <?= htmlspecialchars($pedido['EndEntRua'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($pedido['EndEntNum'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($pedido['EndEntBairro'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($pedido['EndEntCid'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($pedido['EndEntEst'], ENT_QUOTES, 'UTF-8') ?>, CEP: <?= htmlspecialchars($pedido['EndEntCep'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php $detalheUrl = htmlspecialchars($detalheBaseUrl . '?pedido=' . (int) $pedido['PedId'], ENT_QUOTES, 'UTF-8'); ?>
            <!-- Ações disponíveis para o pedido -->
            <div class="order-card-actions">
              <?php if ((int) $pedido['PedStatus'] === 0): ?>
                <!-- Botão de pagamento exibido apenas quando o pedido aguarda confirmação -->
                <button type="button" class="primary-link js-pay-pedido" data-pay-pedido="<?= (int) $pedido['PedId'] ?>">Ir para pagamento</button>
              <?php endif; ?>
              <!-- Link para visualizar produtos e informações completas do pedido -->
              <a class="secondary-link" href="<?= $detalheUrl ?>">Ver detalhes</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <!-- Script JavaScript que gerencia interações da página (pagamento, detalhes, etc) -->
  <script src="<?= htmlspecialchars(asset_path('js/pedidos.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>


</body>

</html>