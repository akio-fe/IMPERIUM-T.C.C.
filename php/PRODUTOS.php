<?php

session_start();



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
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Imperium</title>
  <link rel="icon" href="/img/icone.ico">
  <link rel="stylesheet" href="../css/styleProdutos.css">
  <link rel="stylesheet" href="../css/body.css">
  <link rel="stylesheet" href="../css/header.css">

</head>

<body>

  <?= $header ?>

  <!-- FILTROS -->
  <section class="filtros">
    <div class="search-bar">
      <img src="../img/pesquisar.png" alt="Lupa" class="search-icon">
      <input type="text" placeholder="O que você está buscando?">
      <img src="../img/fechar.png" alt="Fechar" class="fechar">
    </div>
    <div class="icons">
      <img src="../img/pesquisar.png" alt="Pesquisar" class="pesquisar">
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
        $imgProduto = htmlspecialchars($produto['RoupaImgUrl'], ENT_QUOTES, 'UTF-8');
        $slugTipo = htmlspecialchars($produto['slug'], ENT_QUOTES, 'UTF-8');
        $precoFormatado = number_format((float) $produto['RoupaValor'], 2, ',', '.');
      ?>
        <div class="produto" data-tipo="<?= $slugTipo ?>">
          <img src="../<?= $imgProduto ?>" alt="<?= $nomeProduto ?>" />
          <h3><?= $nomeProduto ?></h3>
          <div class="preco">R$ <?= $precoFormatado ?></div>
          <a href="produto.php?id=<?= (int) $produto['RoupaId'] ?>" class="cta-comprar" data-produto-id="<?= (int) $produto['RoupaId'] ?>">
            <button type="button">Comprar</button>
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <script type="module" src="../js/filtro.js"></script>
</body>

</html>