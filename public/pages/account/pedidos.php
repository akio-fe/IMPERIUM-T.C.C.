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

$userId = getSessionUserId($conn);
if ($userId === null) {
  header('Location: ' . $loginPage);
  exit();
}

$pedidos = [];
$erroPedidos = '';

$sql = 'SELECT p.PedId, p.PedData, p.PedValorTotal, p.PedStatus,
               e.EndEntRua, e.EndEntNum, e.EndEntBairro, e.EndEntCid, e.EndEntEst, e.EndEntCep
        FROM pedido p
        INNER JOIN enderecoentrega e ON p.EndEntId = e.EndEntId
        WHERE p.UsuId = ?
        ORDER BY p.PedData DESC';

$stmt = $conn->prepare($sql);
if ($stmt) {
  $stmt->bind_param('i', $userId);
  if ($stmt->execute()) {
    $resultado = $stmt->get_result();
    while ($row = $resultado->fetch_assoc()) {
      $pedidos[] = $row;
    }
    $resultado->free();
  } else {
    $erroPedidos = 'Não foi possível carregar seus pedidos. Tente novamente em instantes.';
  }
  $stmt->close();
} else {
  $erroPedidos = 'Não foi possível acessar seus pedidos neste momento.';
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

$userName = htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8');
$homeUrl = htmlspecialchars(url_path('index.php'), ENT_QUOTES, 'UTF-8');
$perfilUrl = htmlspecialchars(url_path('public/pages/account/perfil.php'), ENT_QUOTES, 'UTF-8');
$enderecosUrl = htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8');
$pedidosUrl = htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8');
$cartoesUrl = htmlspecialchars(url_path('public/pages/account/cartoes.html'), ENT_QUOTES, 'UTF-8');
$favoritosUrl = htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8');
$logoutUrl = htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8');
$detalheBaseUrl = url_path('public/pages/account/pedidoProdutos.php');
$reemitirPagamentoUrl = htmlspecialchars(url_path('public/api/checkout/reemitir_pagamento.php'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>Pedidos</title>
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

    .card-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 16px;
      margin-top: 24px;
    }

    .order-card {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 12px;
      padding: 18px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .order-card h2 {
      margin: 0;
      font-size: 1.1rem;
    }

    .primary-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-top: 8px;
      padding: 10px;
      border-radius: 6px;
      background: var(--highlight);
      color: var(--bg-color);
      text-decoration: none;
      font-weight: 600;
      transition: opacity 0.2s;
    }

    .primary-link:hover {
      opacity: 0.85;
    }

    .secondary-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-top: 8px;
      padding: 10px;
      border-radius: 6px;
      background: transparent;
      color: var(--highlight);
      border: 1px solid rgba(255, 255, 255, 0.2);
      text-decoration: none;
      font-weight: 600;
      transition: opacity 0.2s;
    }

    .secondary-link:hover {
      opacity: 0.85;
    }

    .order-card-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-top: 12px;
    }
  </style>
</head>
<body>
  <button class="back-button" onclick="window.location.href='<?= $homeUrl ?>'">← Voltar</button>
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
    <h1>Pedidos</h1>
    <?php if ($erroPedidos !== ''): ?>
      <div class="status-message estado-erro"><?= htmlspecialchars($erroPedidos, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (empty($pedidos)): ?>
      <div class="status-message">Nenhum pedido realizado.</div>
    <?php else: ?>
      <div class="card-grid">
        <?php foreach ($pedidos as $pedido): ?>
          <div class="order-card">
            <h2>Pedido #<?= (int) $pedido['PedId'] ?></h2>
            <p><strong>Data:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($pedido['PedData'])), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Valor Total:</strong> R$ <?= number_format((float) $pedido['PedValorTotal'], 2, ',', '.') ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars(statusPedido((int) $pedido['PedStatus']), ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Entrega:</strong> <?= htmlspecialchars($pedido['EndEntRua'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($pedido['EndEntNum'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($pedido['EndEntBairro'], ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars($pedido['EndEntCid'], ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($pedido['EndEntEst'], ENT_QUOTES, 'UTF-8') ?>, CEP: <?= htmlspecialchars($pedido['EndEntCep'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php $detalheUrl = htmlspecialchars($detalheBaseUrl . '?pedido=' . (int) $pedido['PedId'], ENT_QUOTES, 'UTF-8'); ?>
            <div class="order-card-actions">
              <?php if ((int) $pedido['PedStatus'] === 0): ?>
                <button type="button" class="primary-link js-pay-pedido" data-pay-pedido="<?= (int) $pedido['PedId'] ?>">Ir para pagamento</button>
              <?php endif; ?>
              <a class="secondary-link" href="<?= $detalheUrl ?>">Ver detalhes</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <script>
    (function () {
      const endpoint = '<?= $reemitirPagamentoUrl ?>';
      const payButtons = document.querySelectorAll('.js-pay-pedido');
      if (!payButtons.length || !endpoint) {
        return;
      }

      const solicitarPagamento = async (pedidoId) => {
        const resposta = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ pedidoId })
        });

        const payload = await resposta.json().catch(() => null);
        if (!resposta.ok || !payload) {
          const mensagem = payload && payload.mensagem ? payload.mensagem : 'Falha ao gerar o link de pagamento.';
          throw new Error(mensagem);
        }

        if (!payload.pagamentoUrl) {
          throw new Error('Retorno inválido do serviço de pagamento.');
        }

        return payload.pagamentoUrl;
      };

      payButtons.forEach((botao) => {
        botao.addEventListener('click', async () => {
          if (botao.disabled) {
            return;
          }

          const pedidoId = Number(botao.dataset.payPedido || 0);
          if (!pedidoId) {
            alert('Pedido inválido.');
            return;
          }

          const textoOriginal = botao.textContent;
          botao.disabled = true;
          botao.textContent = 'Gerando pagamento...';

          try {
            const pagamentoUrl = await solicitarPagamento(pedidoId);
            window.location.href = pagamentoUrl;
          } catch (erro) {
            alert(erro.message || 'Não foi possível redirecionar para o pagamento.');
            botao.disabled = false;
            botao.textContent = textoOriginal;
          }
        });
      });
    })();
  </script>
</body>
</html>
