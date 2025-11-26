<?php
session_start();
require_once 'conn.php';

function getSessionUserId(mysqli $conn): ?int
{
  $email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';
  if ($email === '') {
    return null;
  }

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

$produtoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$erroProduto = '';
$produto = null;
$userId = null;
$isAuthenticated = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
if ($isAuthenticated) {
  $userId = getSessionUserId($conn);
  if ($userId === null) {
    $isAuthenticated = false;
  }
}

if (!$produtoId) {
    $erroProduto = 'Produto inválido ou não informado.';
} else {
    $sql = "SELECT r.RoupaId, r.RoupaNome, r.RoupaImgUrl, r.RoupaValor, r.RoupaModelUrl, r.CatRId, c.CatRTipo FROM roupa r INNER JOIN catroupa c ON c.CatRId = r.CatRId WHERE r.RoupaId = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $produtoId);
        if ($stmt->execute()) {
            $resultado = $stmt->get_result();
            $produto = $resultado->fetch_assoc() ?: null;
            if (!$produto) {
                $erroProduto = 'Produto não encontrado.';
            }
            $resultado->free();
        } else {
            $erroProduto = 'Não foi possível carregar este produto. Tente novamente.';
        }
        $stmt->close();
    } else {
        $erroProduto = 'Falha ao preparar a consulta do produto.';
    }
}

    $isFavorito = false;
    if ($produto && $isAuthenticated && $userId !== null) {
      $stmtFav = $conn->prepare('SELECT 1 FROM favorito WHERE RoupaId = ? AND UsuId = ? LIMIT 1');
      if ($stmtFav) {
        $stmtFav->bind_param('ii', $produto['RoupaId'], $userId);
        $stmtFav->execute();
        $stmtFav->store_result();
        $isFavorito = $stmtFav->num_rows > 0;
        $stmtFav->close();
      }
    }

$nomeProduto = $produto ? htmlspecialchars($produto['RoupaNome'], ENT_QUOTES, 'UTF-8') : '';
$imgProduto = $produto ? '/' . ltrim((string) $produto['RoupaImgUrl'], '/') : '';
$precoProduto = $produto ? (float) $produto['RoupaValor'] : 0.0;
$precoFormatado = $produto ? 'R$ ' . number_format($precoProduto, 2, ',', '.') : '';

$modelPath = '';
if ($produto && !empty($produto['RoupaModelUrl'])) {
  $rawModelUrl = str_replace('\\', '/', trim((string) $produto['RoupaModelUrl']));
  if ($rawModelUrl !== '') {
    $parsed = parse_url($rawModelUrl);
    if (is_array($parsed) && !empty($parsed['path'])) {
      $rawModelUrl = $parsed['path'];
    }
    $rawModelUrl = ltrim($rawModelUrl, '/');
    if (stripos($rawModelUrl, 'imperium/') === 0) {
      $rawModelUrl = substr($rawModelUrl, strlen('imperium/')) ?: '';
    }
    if ($rawModelUrl !== '') {
      // Force assets to load relative to the project root to avoid case-sensitive path mismatches.
      $modelPath = '/imperium/' . $rawModelUrl;
    }
  }
}

$produtoPayload = $produto ? [
  'id' => (int) $produto['RoupaId'],
  'nome' => $produto['RoupaNome'],
  'imagem' => $imgProduto,
  'preco' => $precoProduto,
  'precoFormatado' => $precoFormatado,
  'link' => 'produto.php?id=' . (int) $produto['RoupaId'],
  'modelPath' => $modelPath ?: null,
  'favorito' => $isFavorito,
  'isAuthenticated' => $isAuthenticated,
] : null;

$produtoJson = $produtoPayload ? htmlspecialchars(json_encode($produtoPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $produto ? $nomeProduto . ' - Imperium' : 'Produto - Imperium' ?></title>
  <link rel="icon" href="../img/camisa.ico">
  <link rel="stylesheet" href="../css/produto.css">
</head>

<body>
  <header>
    <a href="PRODUTOS.php"><img src="../img/aguia.png" alt="Imperium Logo" class="logo"></a>
    <div class="search-bar">
      <img src="../img/pesquisar.png" alt="Lupa" class="search-icon">
      <input type="text" placeholder="O que você está buscando?">
      <img src="../img/fechar.png" alt="Fechar" class="fechar">
    </div>
    <div class="icons">
      <img src="../img/pesquisar.png" alt="Pesquisar" class="pesquisar">
      <a href="../html/carrinho.html"><img src="../img/carrin.png" alt="Carrinho"></a>
      <a href="../php/perfil.php"><img src="../img/perfilzin.png" alt="Perfil"></a>
    </div>
  </header>
  <main>
    <?php if ($erroProduto): ?>
      <p class="estado-lista estado-erro"><?= htmlspecialchars($erroProduto, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <div class="container-produto" data-produto='<?= $produtoJson ?>'>
        <div id="container3D" aria-live="polite"></div>
        <div class="detalhes">
          <p class="categoria"><?= htmlspecialchars($produto['CatRTipo'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
          <h1><?= $nomeProduto ?></h1>
          <div class="preco"><?= $precoFormatado ?></div>
          <div class="parcelamento">Ou em até 12x sem juros</div>

          <div class="tamanhos" aria-label="Selecione o tamanho">
            <button type="button">PP</button>
            <button type="button">P</button>
            <button type="button">M</button>
            <button type="button">G</button>
            <button type="button">GG</button>
            <button type="button">XGG</button>
          </div>
          <button class="btn-verde" id="btn-add-cart" type="button">
            Adicionar ao Carrinho
          </button>

          <label class="toggle-favorito" aria-label="Favoritar produto">
            <input type="checkbox" id="btn-favoritar" <?= $isFavorito ? 'checked' : '' ?> <?= $isAuthenticated ? '' : 'data-requires-login="1"' ?> />
            <span>❤️</span>
          </label>
        </div>
      </div>
    <?php endif; ?>
  </main>
  <script type="module" src="../js/produto.js"></script>
</body>

</html>