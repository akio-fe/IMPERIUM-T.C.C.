<?php
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

$loginPage = url_path('public/pages/auth/login.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header('Location: ' . $loginPage);
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

$homeUrl = url_path('index.php');
$shopBaseUrl = url_path('public/pages/shop/index.php');
$navLinksHtml = "<a href='{$homeUrl}'>Home</a>";
foreach ($navItems as $slug => $label) {
  $activeClass = $filtro === $slug ? 'active' : '';
  $navLinksHtml .= "<a href='{$shopBaseUrl}?filtro={$slug}' data-tipo='{$slug}' class='{$activeClass}'>{$label}</a>";
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

$logoSrc = asset_path('img/aguia.png');
$cartIcon = asset_path('img/carrin.png');
$profileIcon = asset_path('img/perfilzin.png');
$loginUrl = url_path('public/pages/auth/cadastro_login.html');
$cartUrl = url_path('public/pages/shop/carrinho.php');
$profileUrl = url_path('public/pages/account/perfil.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  // Cabeçalho para visitantes
  $header = "<header>
    <img src='{$logoSrc}' alt=''>
    <nav>{$navLinksHtml}</nav>
    <div class='linkLogin'>
      <a href='{$loginUrl}'><i class='fa-solid fa-user'></i>FAÇA LOGIN / CADASTRE-SE</a>
    </div>
  </header>";
} else {
  // Cabeçalho para usuários autenticados
  $header = "<header>
    <img src='{$logoSrc}' alt=''>
    <nav>{$navLinksHtml}</nav>
    <div class='icons'>
      <a href='{$cartUrl}'><img src='{$cartIcon}' alt='Carrinho'></a>
      <a href='{$profileUrl}'><img src='{$profileIcon}' alt='Perfil'></a>
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
  <link rel="icon" href="<?php echo htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
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
      <a href="<?= htmlspecialchars(url_path('public/pages/account/perfil.php'), ENT_QUOTES, 'UTF-8'); ?>" class="active">Dados pessoais</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8'); ?>">Endereços</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8'); ?>">Pedidos</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/account/cartoes.html'), ENT_QUOTES, 'UTF-8'); ?>">Cartões</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8'); ?>">Favoritos</a>
      <a href="<?= htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Sair</a>
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