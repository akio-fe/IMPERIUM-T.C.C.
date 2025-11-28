<?php
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

$loginPage = url_path('public/pages/auth/login.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header('Location: ' . $loginPage);
  exit();
}

function getSessionUserId(mysqli $conn): ?int
{
  if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
    return (int) $_SESSION['user_id'];
  }

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

$pedidoId = isset($_GET['pedido']) ? (int) $_GET['pedido'] : 0;
$userId = getSessionUserId($conn);

if ($userId === null || $pedidoId <= 0) {
  header('Location: ' . url_path('public/pages/account/pedidos.php'));
  exit();
}

$pedido = null;
$produtos = [];
$erroPedido = '';

$sqlPedido = 'SELECT p.PedId, p.PedData, p.PedValorTotal, p.PedStatus, p.PedFormPag,
                     e.EndEntRua, e.EndEntNum, e.EndEntBairro, e.EndEntCid, e.EndEntEst, e.EndEntCep, e.EndEntComple
              FROM pedido p
              INNER JOIN enderecoentrega e ON p.EndEntId = e.EndEntId
              WHERE p.PedId = ? AND p.UsuId = ?';

$stmtPedido = $conn->prepare($sqlPedido);
if ($stmtPedido) {
  $stmtPedido->bind_param('ii', $pedidoId, $userId);
  if ($stmtPedido->execute()) {
    $resPedido = $stmtPedido->get_result();
    $pedido = $resPedido->fetch_assoc();
    $resPedido->free();
  } else {
    $erroPedido = 'Não foi possível carregar o pedido solicitado.';
  }
  $stmtPedido->close();
} else {
  $erroPedido = 'Não foi possível acessar os detalhes do pedido.';
}

if ($pedido) {
  $sqlProdutos = 'SELECT pp.PedProQtd, pp.PedProPrecoUnitario, r.RoupaNome
                  FROM pedidoproduto pp
                  INNER JOIN roupa r ON pp.RoupaId = r.RoupaId
                  WHERE pp.PedId = ?';

  $stmtProdutos = $conn->prepare($sqlProdutos);
  if ($stmtProdutos) {
    $stmtProdutos->bind_param('i', $pedidoId);
    if ($stmtProdutos->execute()) {
      $resProdutos = $stmtProdutos->get_result();
      while ($item = $resProdutos->fetch_assoc()) {
        $produtos[] = $item;
      }
      $resProdutos->free();
    } else {
      $erroPedido = 'Não foi possível carregar os produtos deste pedido.';
    }
    $stmtProdutos->close();
  } else {
    $erroPedido = 'Não foi possível acessar os produtos deste pedido.';
  }
}

if (!$pedido && $erroPedido === '') {
  $erroPedido = 'Pedido não encontrado.';
}

$userName = htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8');
$homeUrl = htmlspecialchars(url_path('index.php'), ENT_QUOTES, 'UTF-8');
$perfilUrl = htmlspecialchars(url_path('public/pages/account/perfil.php'), ENT_QUOTES, 'UTF-8');
$enderecosUrl = htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8');
$pedidosUrl = htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8');
$cartoesUrl = htmlspecialchars(url_path('public/pages/account/cartoes.html'), ENT_QUOTES, 'UTF-8');
$favoritosUrl = htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8');
$logoutUrl = htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8');
$pedidoListUrl = htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8');

$enderecoCompleto = '';
$enderecoUrl = '';
if ($pedido) {
  $enderecoCompleto = sprintf(
    '%s, %s, %s, %s - %s, CEP: %s%s',
    $pedido['EndEntRua'],
    $pedido['EndEntNum'],
    $pedido['EndEntBairro'],
    $pedido['EndEntCid'],
    $pedido['EndEntEst'],
    $pedido['EndEntCep'],
    $pedido['EndEntComple'] ? ' - ' . $pedido['EndEntComple'] : ''
  );
  $enderecoUrl = urlencode($pedido['EndEntRua'] . ', ' . $pedido['EndEntNum'] . ', ' . $pedido['EndEntBairro'] . ', ' . $pedido['EndEntCid'] . ' - ' . $pedido['EndEntEst'] . ', ' . $pedido['EndEntCep']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>Detalhes do Pedido</title>
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

    .status-message {
      background: rgba(255, 255, 255, 0.05);
      border: 1px solid rgba(255, 255, 255, 0.1);
      border-radius: 8px;
      padding: 16px;
      margin-top: 16px;
    }

    .status-message.estado-erro {
      border-color: var(--alert);
      color: var(--alert);
    }

    .details-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 16px;
      margin-top: 24px;
    }

    .info-block {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 12px;
      padding: 18px;
    }

    .info-block h2 {
      margin-top: 0;
      margin-bottom: 12px;
      font-size: 1.1rem;
    }

    .product-list {
      margin-top: 24px;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.08);
      overflow: hidden;
    }

    .product-item {
      display: flex;
      justify-content: space-between;
      padding: 14px 18px;
      background: rgba(255, 255, 255, 0.03);
    }

    .product-item:nth-child(even) {
      background: rgba(255, 255, 255, 0.05);
    }

    iframe {
      margin-top: 16px;
      border-radius: 12px;
    }
  </style>
</head>
<body>
  <button class="back-button" onclick="window.location.href='<?= $pedidoListUrl ?>'">← Voltar</button>
  <div class="sidebar">
    <div class="profile">
      <p>Olá!</p>
      <p><?= $userName ?></p>
    </div>
    <nav>
      <a href="<?= $perfilUrl ?>">Dados pessoais</a>
      <a href="<?= $enderecosUrl ?>">Endereços</a>
      <a href="<?= $pedidosUrl ?>" class="active">Pedidos</a>
      <a href="<?= $cartoesUrl ?>">Cartões</a>
      <a href="<?= $favoritosUrl ?>">Favoritos</a>
      <a href="<?= $logoutUrl ?>">Sair</a>
    </nav>
  </div>
  <div class="main-content">
    <h1>Detalhes do Pedido</h1>
    <?php if ($erroPedido !== ''): ?>
      <div class="status-message estado-erro"><?= htmlspecialchars($erroPedido, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (!$pedido): ?>
      <div class="status-message">Pedido não encontrado.</div>
    <?php else: ?>
      <div class="details-grid">
        <div class="info-block">
          <h2>Resumo</h2>
          <p><strong>Número:</strong> #<?= (int) $pedido['PedId'] ?></p>
          <p><strong>Data:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($pedido['PedData'])), ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Status:</strong> <?= htmlspecialchars(statusPedido((int) $pedido['PedStatus']), ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Valor Total:</strong> R$ <?= number_format((float) $pedido['PedValorTotal'], 2, ',', '.') ?></p>
        </div>
        <div class="info-block">
          <h2>Entrega</h2>
          <p><?= htmlspecialchars($enderecoCompleto, ENT_QUOTES, 'UTF-8') ?></p>
          <iframe width="100%" height="220" frameborder="0" style="border:0" src="https://www.google.com/maps?q=<?= htmlspecialchars($enderecoUrl, ENT_QUOTES, 'UTF-8') ?>&output=embed" allowfullscreen></iframe>
        </div>
      </div>

      <div class="info-block" style="margin-top:24px;">
        <h2>Produtos</h2>
        <?php if (empty($produtos)): ?>
          <p>Nenhum produto encontrado para este pedido.</p>
        <?php else: ?>
          <div class="product-list">
            <?php foreach ($produtos as $item): ?>
              <div class="product-item">
                <div>
                  <strong><?= htmlspecialchars($item['RoupaNome'], ENT_QUOTES, 'UTF-8') ?></strong><br />
                  Qtd: <?= (int) $item['PedProQtd'] ?>
                </div>
                <div>
                  R$ <?= number_format((float) $item['PedProPrecoUnitario'], 2, ',', '.') ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
