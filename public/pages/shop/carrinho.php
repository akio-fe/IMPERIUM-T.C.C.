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

// Carrega o header reutilizável
require_once __DIR__ . '/../includes/header.php';

// Gera o header com o filtro atual
$header = generateHeader($conn, $filtro);

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

$isAuthenticated = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$userId = $isAuthenticated ? getSessionUserId($conn) : null;
if ($userId === null) {
  $isAuthenticated = false;
}

$itensCarrinho = [];
$subtotal = 0.0;
$erroCarrinho = '';

if ($isAuthenticated) {
  $stmt = $conn->prepare(
    'SELECT c.CarID, c.CarQtd, c.CarTam, r.RoupaNome, r.RoupaImgUrl, r.RoupaValor
     FROM carrinho c
     INNER JOIN roupa r ON r.RoupaId = c.RoupaId
     WHERE c.UsuId = ?
     ORDER BY c.CarID DESC'
  );

  if ($stmt) {
    $stmt->bind_param('i', $userId);
    if ($stmt->execute()) {
      $resultado = $stmt->get_result();
      while ($row = $resultado->fetch_assoc()) {
        $quantidade = (int) $row['CarQtd'];
        $precoUnitario = (float) $row['RoupaValor'];
        $itemTotal = $precoUnitario * $quantidade;
        $imagemUrl = asset_path((string) $row['RoupaImgUrl']);

        $itensCarrinho[] = [
          'id' => (int) $row['CarID'],
          'nome' => $row['RoupaNome'],
          'imagem' => $imagemUrl,
          'quantidade' => $quantidade,
          'tamanho' => $row['CarTam'] ?? '',
          'precoFormatado' => number_format($precoUnitario, 2, ',', '.'),
          'totalFormatado' => number_format($itemTotal, 2, ',', '.'),
        ];

        $subtotal += $itemTotal;
      }
      $resultado->free();
    } else {
      $erroCarrinho = 'Não foi possível carregar os itens do carrinho.';
    }
    $stmt->close();
  } else {
    $erroCarrinho = 'Falha ao preparar a consulta do carrinho.';
  }
}

$freteEstimado = 0.00;
$total = $subtotal + $freteEstimado;

$subtotalFormatado = number_format($subtotal, 2, ',', '.');
$freteFormatado = number_format($freteEstimado, 2, ',', '.');
$totalFormatado = number_format($total, 2, ',', '.');

$btnFinalizarDisabled = !$isAuthenticated || empty($itensCarrinho);
$enderecosGerenciarUrl = site_path('public/pages/account/enderecos.php');
$novoEnderecoUrl = site_path('public/pages/account/addEnd.php');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Carrinho</title>
  <link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/carrinhoicone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/carrinho.css'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/header.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body>

  <?= $header ?>

  <div class="wrap">
    <!-- TELA DO CARRINHO -->
    <div id="tela-carrinho" class="carrinho-container">
      <h2>Seu Carrinho</h2>
      <div id="lista-carrinho">
        <?php if (!$isAuthenticated): ?>
          <p class="estado-lista">Faça login para visualizar seu carrinho.</p>
        <?php elseif ($erroCarrinho): ?>
          <p class="estado-lista estado-erro"><?= htmlspecialchars($erroCarrinho, ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif (empty($itensCarrinho)): ?>
          <p class="estado-lista">Seu carrinho ainda está vazio.</p>
        <?php else: ?>
          <?php foreach ($itensCarrinho as $item): ?>
            <div class="item-carrinho">
              <img src="<?= htmlspecialchars($item['imagem'], ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8') ?>">
              <div class="item-info">
                <h4><?= htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8') ?></h4>
                <?php if (!empty($item['tamanho'])): ?>
                  <p>Tamanho: <?= htmlspecialchars($item['tamanho'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <p>Quantidade: <?= $item['quantidade'] ?></p>
                <p>Preço unitário: R$ <?= htmlspecialchars($item['precoFormatado'], ENT_QUOTES, 'UTF-8') ?></p>
                <p>Total: R$ <?= htmlspecialchars($item['totalFormatado'], ENT_QUOTES, 'UTF-8') ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="total-area">
        <p>Subtotal: R$ <span id="subtotal"><?= $subtotalFormatado ?></span></p>
        <p>Frete: R$ <span id="frete-valor"><?= $freteFormatado ?></span></p>
        <h3>Total: R$ <span id="total"><?= $totalFormatado ?></span></h3>
      </div>

      <div class="frete">
        <label style="font-weight:700;color:var(--accent)">Consulte o frete</label>
        <div class="cep-row">
          <input id="input-cep" type="text" placeholder="Digite o CEP (somente números)">
          <button id="btn-cep">OK</button>
        </div>

        <input id="input-numero" type="text" placeholder="Número (obrigatório)">
        <input id="input-complemento" type="text" placeholder="Complemento (opcional)">

        <div id="resultado-frete" class="resultado-frete"></div>
        <div id="mapa-frete" class="mapa"></div>

        <a href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank"
          style="color:var(--accent)">Não sei meu CEP</a>
      </div>

      <button id="btn-finalizar" class="finalizar" <?= $btnFinalizarDisabled ? 'disabled' : '' ?>>Finalizar compra</button>
    </div>

    <!-- TELA DE ENDEREÇOS -->
    <div id="tela-enderecos" class="checkout-step" style="display:none;">
      <h2>Selecione o endereço de entrega</h2>
      <p class="estado-lista">Buscaremos seus endereços salvos automaticamente.</p>
      <div id="lista-enderecos" class="lista-enderecos"></div>
      <div class="acoes-endereco">
        <a class="button-link" href="<?= htmlspecialchars($novoEnderecoUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">Cadastrar novo endereço</a>
        <div class="acoes-endereco__buttons">
          <button type="button" id="btn-voltar-carrinho" class="secondary">Voltar</button>
          <button type="button" id="btn-ir-pagamento" class="primary" disabled>Continuar para pagamento</button>
        </div>
      </div>
    </div>

    <!-- TELA DE PROCESSAMENTO -->
    <div id="tela-processando" class="checkout-step" style="display:none;">
      <h2>Redirecionando para o Mercado Pago</h2>
      <p>Estamos registrando o pedido e você será encaminhado para finalizar o pagamento.</p>
      <div class="loader" aria-hidden="true"></div>
      <button type="button" id="btn-cancelar-processamento" class="secondary">Cancelar</button>
    </div>
  </div>

  <?php
  $listarCarrinhoUrl = site_path('public/api/carrinho/listar.php');
  $atualizarCarrinhoUrl = site_path('public/api/carrinho/atualizar.php');
  $removerCarrinhoUrl = site_path('public/api/carrinho/remover.php');
  $enderecosApiUrl = site_path('public/api/checkout/enderecos.php');
  $criarPedidoUrl = site_path('public/api/checkout/criar_pedido.php');
  ?>
  <script>
    window.CARRINHO_API = {
      listar: <?= json_encode($listarCarrinhoUrl) ?>,
      atualizar: <?= json_encode($atualizarCarrinhoUrl) ?>,
      remover: <?= json_encode($removerCarrinhoUrl) ?>
    };
    window.CARRINHO_LOGIN_URL = <?= json_encode($loginUrl) ?>;
    window.CARRINHO_IS_AUTHENTICATED = <?= $isAuthenticated ? 'true' : 'false' ?>;
    window.CHECKOUT_API = {
      enderecos: <?= json_encode($enderecosApiUrl) ?>,
      criarPedido: <?= json_encode($criarPedidoUrl) ?>
    };
    window.CHECKOUT_ADD_ADDRESS_URL = <?= json_encode($novoEnderecoUrl) ?>;
  </script>
  <script src="<?= htmlspecialchars(asset_path('js/carrinho.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

  <script src="https://code.jquery.com/jquery-3.0.0.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js"></script>
  <script src="<?= htmlspecialchars(asset_path('js/form-mask.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>

</html>