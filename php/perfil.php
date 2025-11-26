<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header('Location: login.php');
  exit();
}

function sanitizeField($value)
{
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function formatCpf($cpf)
{
  $digits = preg_replace('/\D+/', '', (string)$cpf);
  if (strlen($digits) === 11) {
    $formatted = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
    return sanitizeField($formatted);
  }
  return sanitizeField($cpf);
}

function formatDateBr($date)
{
  $rawDate = trim((string)$date);
  if ($rawDate === '') {
    return '';
  }
  try {
    $dateTime = new DateTime($rawDate);
    return sanitizeField($dateTime->format('d/m/Y'));
  } catch (Exception $e) {
    return sanitizeField($date);
  }
}

function formatPhone($phone)
{
  $digits = preg_replace('/\D+/', '', (string)$phone);
  if (strlen($digits) === 11) {
    $formatted = sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
    return sanitizeField($formatted);
  }
  if (strlen($digits) === 10) {
    $formatted = sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
    return sanitizeField($formatted);
  }
  return sanitizeField($phone);
}

$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';
$classActive = '';
$filtrosPermitidos = ['todos', 'calcados', 'calcas', 'blusas', 'camisas', 'conjuntos', 'outros', 'acessorios'];

if (!in_array($filtro, $filtrosPermitidos, true)) {
  $filtro = 'todos';
}


require_once __DIR__ . '/conn.php';

$categoriaSlugMap = [
  1 => 'calcados',
  2 => 'calcas',
  3 => 'blusas',
  4 => 'camisas',
  5 => 'conjuntos',
  6 => 'acessorios',
];

$navItems = [
  'todos' => 'Todos',
  'camisas' => 'Camisas',
  'calcas' => 'Calças',
  'calcados' => 'Calçados',
  'acessorios' => 'Acessórios',
];

$navLinksHtml = "<a href='../index.php'>Home</a>";
foreach ($navItems as $slug => $label) {
  $activeClass = $filtro === $slug ? 'active' : '';
  $navLinksHtml .= "<a href='PRODUTOS.php?filtro={$slug}' data-tipo='{$slug}' class='{$activeClass}'>{$label}</a>";
}

$produtos = [];
$erroProdutos = '';

$sql = "SELECT r.RoupaId, r.RoupaNome, r.RoupaModelUrl, r.RoupaImgUrl, r.RoupaValor, r.CatRId, c.CatRTipo, c.CatRSessao\n        FROM roupa r\n        INNER JOIN catroupa c ON c.CatRId = r.CatRId\n        ORDER BY r.CatRId, r.RoupaNome";

if ($resultado = $conn->query($sql)) {
  while ($linha = $resultado->fetch_assoc()) {
    $linha['slug'] = $categoriaSlugMap[$linha['CatRId']] ?? 'outros';
    $produtos[] = $linha;
  }
  $resultado->free();
} else {
  $erroProdutos = 'Não foi possível carregar os produtos. Tente novamente em instantes.';
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  // Cabeçalho para visitantes
  $header = "<header>
    <img src='../public/images/aguia.png' alt=''>
    <nav>{$navLinksHtml}</nav>
    <div class='linkLogin'>
      <a href='../html/cadastro_login.html'><i class='fa-solid fa-user'></i>FAÇA LOGIN / CADASTRE-SE</a>
    </div>
  </header>";
} else {
  // Cabeçalho para usuários autenticados
  $header = "<header>
    <img src='../public/images/aguia.png' alt=''>
    <nav>{$navLinksHtml}</nav>
    <div class='icons'>
      <a href='../html/carrinho.html'><img src='../img/carrin.png' alt='Carrinho'></a>
      <a href='../php/perfil.php'><img src='../img/perfilzin.png' alt='Perfil'></a>
    </div>
  </header>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <title>Perfil do Usuário</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="icon" href="../img/icone.ico">
  <link rel="stylesheet" href="../css/header.css">
  <link rel="stylesheet" href="../css/perfil.css">


</head>

<body>

  <div class="sidebar">
    <div class="profile">
      <p>Olá!</p>
      <p><?php echo htmlspecialchars($_SESSION['user_nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <nav>
      <a href="dados_pessoais.html" class="active">Dados pessoais</a>
      <a href="enderecos.php">Endereços</a>
      <a href="../html/pedidos.html">Pedidos</a>
      <a href="../html/cartoes.html">Cartões</a>
      <a href="favoritos.php">Favoritos</a>
      <a href="sair.php">Sair</a>
    </nav>
  </div>

  <div class="main-content">
    <h1>Dados pessoais</h1>
    <div class="info-section">
      <div class="card">
        <p>
          <strong>Nome</strong>
          <br>
          <span><?php echo sanitizeField($_SESSION['user_nome'] ?? ''); ?></span>
        </p>
        <p>
          <strong>Email</strong>
          <br>
          <span><?php echo sanitizeField($_SESSION['email'] ?? ''); ?></span>
        </p>
        <p><strong>CPF</strong>
          <br>
          <span><?php echo formatCpf($_SESSION['user_cpf'] ?? ''); ?></span>
        </p>
        <p><strong>Data de nascimento</strong>
          <br>
          <span><?php echo formatDateBr($_SESSION['user_data_nasc'] ?? ''); ?></span>
        </p>
        <p><strong>Telefone</strong>
          <br>
          <span><?php echo formatPhone($_SESSION['user_tel'] ?? ''); ?></span>
        </p>
        <div class="edit-btn">
          <a href="editar.php">EDITAR</a>
        </div>
        <div class="edit-btn">
          <a href="deletar.php">DELETAR</a>
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