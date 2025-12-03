<?php
/**
 * ============================================================
 * PÁGINA: Carrinho de Compras
 * ============================================================
 * 
 * Arquivo: public/pages/shop/carrinho.php
 * Propósito: Interface completa do carrinho com checkout em 3 etapas.
 * 
 * Funcionalidades Principais:
 * - Exibição de itens do carrinho (produto, quantidade, tamanho, preço)
 * - Cálculo de subtotal, frete e total
 * - Consulta de frete via API dos Correios (CEP)
 * - Seleção de endereço de entrega cadastrado
 * - Finalização de pedido com redirecionamento para Mercado Pago
 * - Atualização de quantidade via JavaScript
 * - Remoção de itens do carrinho
 * 
 * Fluxo de Checkout (3 Telas):
 * 1. TELA CARRINHO:
 *    - Lista itens, preços, consulta frete
 *    - Botão "Finalizar compra" (requer login)
 * 
 * 2. TELA ENDEREÇOS:
 *    - Carrega endereços salvos do usuário via API
 *    - Permite cadastrar novo endereço
 *    - Seleção de endereço para entrega
 *    - Botões: Voltar | Continuar para pagamento
 * 
 * 3. TELA PROCESSAMENTO:
 *    - Cria pedido no banco via API
 * - Redireciona para Mercado Pago
 *    - Loader animado
 *    - Botão cancelar
 * 
 * APIs Utilizadas:
 * - /public/api/carrinho/listar.php: lista itens do carrinho
 * - /public/api/carrinho/atualizar.php: atualiza quantidade
 * - /public/api/carrinho/remover.php: remove item
 * - /public/api/checkout/enderecos.php: lista endereços do usuário
 * - /public/api/checkout/criar_pedido.php: cria pedido e preference MP
 * 
 * Integrações Externas:
 * - API ViaCEP: busca endereço por CEP
 * - API Correios: cálculo de frete (SEDEX, PAC)
 * - Mercado Pago: gateway de pagamento
 * 
 * JavaScript:
 * - carrinho.js: gerencia estado, navegação entre telas, API calls
 * - form-mask.js + jQuery Mask: máscaras de CEP
 * 
 * Segurança:
 * - Verifica autenticação via sessão
 * - Validação de UsuId no banco
 * - Prepared statements em todas as queries
 * - Sanitização de saída com htmlspecialchars()
 */

// ===== INICIALIZAÇÃO =====
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// ===== VALIDAÇÃO DO FILTRO (para header dinâmico) =====
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';
$classActive = '';
$filtrosPermitidos = ['todos', 'calcados', 'calcas', 'blusas', 'camisas', 'conjuntos', 'outros', 'acessorios'];

if (!in_array($filtro, $filtrosPermitidos, true)) {
  $filtro = 'todos';
}

// Mapeamento de IDs de categoria para slugs (usado no header)
$categoriaSlugMap = [
  1 => 'calcados',
  2 => 'calcas',
  3 => 'blusas',
  4 => 'camisas',
  5 => 'conjuntos',
  6 => 'acessorios',
];

// ===== CARREGAMENTO DO HEADER =====
require_once __DIR__ . '/../includes/header.php';
$header = generateHeader($conn, $filtro);

/**
 * Recupera o ID do usuário da sessão via consulta no banco.
 * 
 * Estratégia de Autenticação:
 * 1. Verifica se há email na sessão ($_SESSION['email'])
 * 2. Consulta banco para obter UsuId correspondente
 * 3. Retorna null se não encontrar (email inválido ou sessão corrompida)
 * 
 * Uso:
 * - Validar se usuário está realmente autenticado (não só $_SESSION)
 * - Obter UsuId para consultas de carrinho, endereços, pedidos
 * 
 * @param mysqli $conn Conexão ativa com o banco de dados
 * @return int|null ID do usuário ou null se não encontrado/não autenticado
 */
function getSessionUserId(mysqli $conn): ?int
{
  // Obtém email da sessão (definido em api/auth/login.php)
  $email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';
  if ($email === '') {
    return null; // Sessão sem email
  }

  // Busca UsuId no banco de dados
  $stmt = $conn->prepare('SELECT UsuId FROM usuario WHERE UsuEmail = ? LIMIT 1');
  if (!$stmt) {
    return null; // Erro ao preparar statement
  }

  $stmt->bind_param('s', $email);
  $stmt->execute();
  $stmt->bind_result($userId);
  $foundId = $stmt->fetch() ? (int) $userId : null;
  $stmt->close();

  return $foundId; // Retorna UsuId ou null
}

// ===== VERIFICAÇÃO DE AUTENTICAÇÃO =====
// Primeiro: verifica flag de sessão
$isAuthenticated = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
// Segundo: valida se usuário existe no banco (evita sessões inválidas)
$userId = $isAuthenticated ? getSessionUserId($conn) : null;
if ($userId === null) {
  $isAuthenticated = false; // Sessão inválida ou usuário deletado
}

// ===== INICIALIZAÇÃO DE VARIÁVEIS DO CARRINHO =====
$itensCarrinho = [];   // Array de itens do carrinho
$subtotal = 0.0;       // Soma dos preços (sem frete)
$erroCarrinho = '';    // Mensagem de erro caso consulta falhe

// ===== CONSULTA DE ITENS DO CARRINHO =====
// Apenas para usuários autenticados
if ($isAuthenticated) {
  // Query com INNER JOIN: carrega dados do produto junto com o item do carrinho
  // Retorna: CarID, quantidade, tamanho, nome do produto, imagem, preço unitário
  // Ordenação: DESC (itens mais recentes primeiro)
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
      
      // Processa cada item do carrinho
      while ($row = $resultado->fetch_assoc()) {
        // Type casting para garantir tipos corretos
        $quantidade = (int) $row['CarQtd'];
        $precoUnitario = (float) $row['RoupaValor'];
        $itemTotal = $precoUnitario * $quantidade;
        
        // Resolve URL completa da imagem
        $imagemUrl = asset_path((string) $row['RoupaImgUrl']);

        // Monta array do item com dados formatados
        $itensCarrinho[] = [
          'id' => (int) $row['CarID'],           // ID do item no carrinho (para update/delete)
          'nome' => $row['RoupaNome'],           // Nome do produto
          'imagem' => $imagemUrl,                 // URL completa da imagem
          'quantidade' => $quantidade,            // Quantidade selecionada
          'tamanho' => $row['CarTam'] ?? '',     // Tamanho (PP, P, M, G, GG, etc)
          'precoFormatado' => number_format($precoUnitario, 2, ',', '.'), // R$ 99,90
          'totalFormatado' => number_format($itemTotal, 2, ',', '.'),     // Preço * Qtd
        ];

        // Acumula subtotal (soma de todos os itens)
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

// ===== CÁLCULO DE TOTAIS =====
// Frete inicialmente zerado (calculado via API dos Correios após informação de CEP)
$freteEstimado = 0.00;

// Total = Subtotal (produtos) + Frete
$total = $subtotal + $freteEstimado;

// Formata valores para exibição no padrão brasileiro (R$ 1.234,56)
$subtotalFormatado = number_format($subtotal, 2, ',', '.');
$freteFormatado = number_format($freteEstimado, 2, ',', '.');
$totalFormatado = number_format($total, 2, ',', '.');

// ===== CONFIGURAÇÃO DE ESTADO DA UI =====
// Botão "Finalizar compra" desabilitado se:
// - Usuário não autenticado (precisa fazer login)
// - Carrinho vazio (nada para comprar)
$btnFinalizarDisabled = !$isAuthenticated || empty($itensCarrinho);

// URLs para gerenciamento de endereços
$enderecosGerenciarUrl = site_path('public/pages/account/enderecos.php'); // Lista de endereços
$novoEnderecoUrl = site_path('public/pages/account/addEnd.php');          // Cadastro de novo endereço
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Carrinho</title>
  
  <!-- Favicon do carrinho -->
  <link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/carrinhoicone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
  
  <!-- Estilos específicos do carrinho e checkout -->
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/carrinho.css'), ENT_QUOTES, 'UTF-8'); ?>"> <!-- Layout 3 telas, cards, totais -->
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/header.css'), ENT_QUOTES, 'UTF-8'); ?>">   <!-- Header dinâmico -->
</head>

<body>

  <!-- ===== HEADER DINÂMICO ===== -->
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