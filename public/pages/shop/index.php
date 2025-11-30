<?php

session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// Determina o filtro de categoria a partir do parâmetro GET
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';
$classActive = '';
$filtrosPermitidos = ['todos', 'calcados', 'calcas', 'blusas', 'camisas', 'conjuntos', 'outros', 'acessorios'];

if (!in_array($filtro, $filtrosPermitidos, true)) {
  $filtro = 'todos';
}
// Mapeamento de IDs de categoria para slugs
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

$navLinksHtml = "<a href='" . site_path('index.php') . "'>Home</a>";
foreach ($navItems as $slug => $label) {
  $activeClass = $filtro === $slug ? 'active' : '';
  $navLinksHtml .= "<a href='index.php?filtro={$slug}' data-tipo='{$slug}' class='{$activeClass}'>{$label}</a>";
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
$loginUrl = site_path('public/pages/auth/cadastro_login.html');
$cartIcon = asset_path('img/carrin.png');
$profileIcon = asset_path('img/perfilzin.png');
$profileLink = site_path('public/pages/account/perfil.php');
$cartLink = site_path('public/pages/shop/carrinho.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  $header = "<header>
        <div class='linkLogin'>
            <a href='{$loginUrl}'><i class='fa-solid fa-user'></i>FAÇA LOGIN / CADASTRE-SE</a>
        </div>
        <nav>
            {$navLinksHtml}
        </nav>
        <img src='{$logoSrc}' alt='Imperium'>
    </header>";
} else {
  $header = "<header>
        <div class='acicons'>
                <a href='{$cartLink}''><img src='{$cartIcon}' alt='Carrinho'></a>
                <a href='{$profileLink}'><img src='{$profileIcon}' alt='Perfil'></a>
            </div> 
        
        <nav>{$navLinksHtml}</nav>

        <img src='{$logoSrc}' alt='Imperium'>
                   
    </header>";
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Imperium</title>
  <link rel="icon" href="<?= asset_path('img/catalog/icone.ico') ?>">
  <link rel="stylesheet" href="<?= asset_path('css/styleProdutos.css') ?>">
  <link rel="stylesheet" href="<?= asset_path('css/body.css') ?>">
  <link rel="stylesheet" href="<?= asset_path('css/header.css') ?>">

</head>

<body>

  <?= $header ?>

  <!-- FILTROS -->
  <section class="filtros">
    <div class="search-bar">
      <img src="<?= asset_path('img/catalog/pesquisar.png') ?>" alt="Lupa" class="search-icon">
      <input type="text" placeholder="O que você está buscando?">
      <img src="<?= asset_path('img/catalog/fechar.png') ?>" alt="Fechar" class="fechar">
    </div>
    <div class="icons">
      <img src="<?= asset_path('img/catalog/pesquisar.png') ?>" alt="Pesquisar" class="pesquisar">
    </div>
  </section>

  <main class="produtos">
    <?php if ($erroProdutos) : ?>
      <p class="estado-lista estado-erro"><?= htmlspecialchars($erroProdutos, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif (empty($produtos)) : ?>
      <p class="estado-lista">Nenhum produto cadastrado no momento.</p>
    <?php else : ?>
      <?php foreach ($produtos as $produto) :
        $nomeProduto = htmlspecialchars($produto['RoupaNome'], ENT_QUOTES, 'UTF-8');
        $imgProduto = asset_path((string) ($produto['RoupaImgUrl'] ?? ''));
        $imgProdutoEscaped = htmlspecialchars($imgProduto, ENT_QUOTES, 'UTF-8');
        $slugTipo = htmlspecialchars($produto['slug'], ENT_QUOTES, 'UTF-8');
        $precoFormatado = number_format((float) $produto['RoupaValor'], 2, ',', '.');
      ?>
        <div class="produto" data-tipo="<?= $slugTipo ?>">
          <img src="<?= $imgProdutoEscaped ?>" alt="<?= $nomeProduto ?>" />
          <h3><?= $nomeProduto ?></h3>
          <div class="preco">R$ <?= $precoFormatado ?></div>
          <a href="produto.php?id=<?= (int) $produto['RoupaId'] ?>" class="cta-comprar" data-produto-id="<?= (int) $produto['RoupaId'] ?>">
            <button type="button">Comprar</button>
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <script type="module" src="<?= asset_path('js/filtro.js') ?>"></script>
</body>

</html>