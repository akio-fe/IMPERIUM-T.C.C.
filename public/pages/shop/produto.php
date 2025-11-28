<?php
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

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
$imgProduto = $produto ? asset_path((string) $produto['RoupaImgUrl']) : '';
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

$currentSlug = 'todos';
if ($produto && isset($produto['CatRId'])) {
  $currentSlug = $categoriaSlugMap[(int) $produto['CatRId']] ?? 'todos';
}

$navLinksHtml = "<a href='" . site_path('index.php') . "'>Home</a>";
$shopIndexBase = site_path('public/pages/shop/index.php');
foreach ($navItems as $slug => $label) {
  $activeClass = $slug === $currentSlug ? 'active' : '';
  $navLinksHtml .= "<a href='{$shopIndexBase}?filtro={$slug}' data-tipo='{$slug}' class='{$activeClass}'>{$label}</a>";
}

$logoSrc = asset_path('img/aguia.png');
$loginUrl = site_path('public/pages/auth/cadastro_login.html');
$profileLink = site_path('public/pages/account/perfil.php');
$cartLink = site_path('public/pages/shop/carrinho.php');
$cartIcon = asset_path('img/carrin.png');
$profileIcon = asset_path('img/perfilzin.png');

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
  <link rel="icon" href="<?= asset_path('img/catalog/camisa.ico') ?>">
  <link rel="stylesheet" href="<?= asset_path('css/header.css') ?>">
  <link rel="stylesheet" href="<?= asset_path('css/produto.css') ?>">
</head>

<body>
  <?= $header ?>
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
  <?php
  $loginUrl = site_path('public/pages/auth/cadastro_login.html');
  $addCarrinhoUrl = site_path('public/api/carrinho/adicionar.php');
  ?>
  <script type="module" src="<?= asset_path('js/produto.js') ?>"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const container = document.querySelector('.container-produto');
      const btnAddCart = document.getElementById('btn-add-cart');
      if (!container || !btnAddCart) {
        return;
      }

      let produtoData = null;
      try {
        produtoData = JSON.parse(container.dataset.produto ?? '{}');
      } catch (error) {
        produtoData = null;
      }

      const feedback = document.createElement('p');
      feedback.className = 'estado-lista';
      feedback.style.marginTop = '8px';
      btnAddCart.insertAdjacentElement('afterend', feedback);

      btnAddCart.addEventListener('click', async () => {
        if (!produtoData || !produtoData.id) {
          feedback.textContent = 'Produto indisponível.';
          return;
        }

        if (!produtoData.isAuthenticated) {
          window.location.href = <?= json_encode($loginUrl) ?>;
          return;
        }

        const tamanhoSelecionado = document.querySelector('.tamanhos button.selected');
        if (!tamanhoSelecionado) {
          feedback.textContent = 'Selecione um tamanho antes de adicionar.';
          feedback.classList.add('estado-erro');
          return;
        }

        const tamanho = tamanhoSelecionado.textContent.trim();
        if (!tamanho) {
          feedback.textContent = 'Selecione um tamanho válido.';
          feedback.classList.add('estado-erro');
          return;
        }

        feedback.classList.remove('estado-erro');

        btnAddCart.disabled = true;
        feedback.textContent = 'Adicionando ao carrinho...';

        try {
          const response = await fetch(<?= json_encode($addCarrinhoUrl) ?>, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              produtoId: produtoData.id,
              quantidade: 1,
              tamanho
            })
          });

          const payload = await response.json();

          if (response.status === 401) {
            window.location.href = <?= json_encode($loginUrl) ?>;
            return;
          }

          if (!response.ok || !payload.sucesso) {
            throw new Error(payload.mensagem || 'Erro ao salvar no carrinho.');
          }

          feedback.textContent = 'Produto adicionado! Abra o carrinho para finalizar.';
        } catch (error) {
          feedback.textContent = error.message || 'Não foi possível adicionar ao carrinho.';
        } finally {
          btnAddCart.disabled = false;
        }
      });
    });
  </script>
</body>

</html>