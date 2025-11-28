<?php
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

$loginPage = url_path('public/pages/auth/login.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header('Location: ' . $loginPage);
  exit();
}

$userName = htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>Favoritos</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
  <style>
    .back-button {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      background-color: var(--highlight);
      color: var(--bg-color);
      border: none;
      padding: 10px 20px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.95rem;
      transition: opacity 0.2s;
    }
    .back-button:hover {
      opacity: 0.85;
    }
  </style>
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
      <a href="<?= htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8'); ?>">Endereços</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8'); ?>">Pedidos</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/account/cartoes.html'), ENT_QUOTES, 'UTF-8'); ?>">Cartões</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8'); ?>" class="active">Favoritos</a>
      <a href="<?= htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Sair</a>
    </nav>
  </div>

  <div class="main-content">
    <h1>Favoritos</h1>
    <div id="favoritosStatus" class="status-message">Carregando favoritos...</div>
    <div class="product-list" id="favoritosContainer" aria-live="polite"></div>
  </div>

  <script type="module" src="<?= htmlspecialchars(asset_path('js/favoritos.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
