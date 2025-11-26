<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header('Location: login.php');
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
  <link rel="icon" href="../img/icone.ico">
  <link rel="stylesheet" href="../css/perfil.css">
</head>
<body>
  <div class="sidebar">
    <div class="profile">
      <a href="../index.php"><img src="../img/aguia.png" alt="Imperium Logo" class="logo"></a>
      <p>Olá!</p>
      <p><?php echo $userName; ?></p>
    </div>
    <nav>
      <a href="perfil.php">Dados pessoais</a>
      <a href="enderecos.html">Endereços</a>
      <a href="pedidos.html">Pedidos</a>
      <a href="cartoes.html">Cartões</a>
      <a href="autenticacao.html">Autenticação</a>
      <a href="organizacao.html">Minha organização</a>
      <a href="favoritos.php" class="active">Favoritos</a>
      <a href="sair.html">Sair</a>
    </nav>
  </div>

  <div class="main-content">
    <h1>Favoritos</h1>
    <div id="favoritosStatus" class="status-message">Carregando favoritos...</div>
    <div class="product-list" id="favoritosContainer" aria-live="polite"></div>
  </div>

  <script type="module" src="../js/favoritos.js"></script>
</body>
</html>
