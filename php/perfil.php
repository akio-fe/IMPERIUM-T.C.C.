<?php
  session_start();

  if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    session_destroy();
    exit();
  }
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>Perfil do Usuário</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="icon" href="/img/icone.ico">
  <link rel="stylesheet" href="../css/perfil.css">
  
</head>
<body>
  <div class="sidebar">
    <div class="profile">
      <p>Olá!</p>
      <p>Nome do cliente</p>
    </div>
    <nav>
      <a href="dados_pessoais.html" class="active">Dados pessoais</a>
      <a href="enderecos.html">Endereços</a>
      <a href="pedidos.html">Pedidos</a>
      <a href="cartoes.html">Cartões</a>
      <a href="organizacao.html">Minha organização</a>
     <a href="favoritos.html">Favoritos</a>
      <a href="sair.html">Sair</a>
    </nav>
  </div>

  <div class="main-content">
    <h1>Dados pessoais</h1>
    <div class="info-section">
      <div class="card">
        <p>
          <strong>Nome</strong>
          <br>
          <span><?php echo $_SESSION['user_nome']; ?></span>
        </p>
        <p>
          <strong>Email</strong>
          <br>
          <span><?php echo $_SESSION['user_email']; ?></span>
        </p>
        <p><strong>CPF</strong>
          <br>
          <span><?php echo $_SESSION['user_cpf']; ?></span>
        </p>
        <p><strong>Data de nascimento</strong>
          <br>
          <span><?php echo $_SESSION['user_data_nasc']; ?></span>
        </p>
        <p><strong>Telefone</strong>
          <br>
          <span><?php echo $_SESSION['user_tel']; ?></span>
        </p>
        <div class="edit-btn">
          <a href="editar.php"></a>
        </div>
        <div class="edit-btn">
          <a href="deletar.php"></a>
        </div>
      </div>

      <div class="card" style="max-width: 320px;">
        <h2>Newsletter</h2>
        <p>Deseja receber e-mails com promoções?</p>
        <div class="checkbox-container">
          <input type="checkbox" id="newsletter">
          <label for="newsletter">Quero receber e-mails com promoções.</label>
        </div>
      </div>
    </div>

   

</body>
</html>
