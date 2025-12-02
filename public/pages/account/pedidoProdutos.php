<?php
/**
 * Página: Detalhes do Pedido
 * Propósito: Exibe informações completas de um pedido específico do usuário.
 * 
 * Mostra:
 * - Resumo do pedido (número, data, status, valor total)
 * - Endereço de entrega com mapa integrado do Google Maps
 * - Lista de produtos comprados com quantidades e preços
 * 
 * Validações:
 * - Usuário deve estar autenticado
 * - Pedido deve pertencer ao usuário logado
 * - ID do pedido deve ser válido (> 0)
 */

// Inicia sessão para acesso aos dados do usuário autenticado
session_start();
// Carrega configurações, helpers e conexão com o banco de dados
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// URL da página de login para redirecionamentos
$loginPage = url_path('public/pages/auth/login.php');

// Valida se o usuário está autenticado, caso contrário redireciona para login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header('Location: ' . $loginPage);
  exit();
}

/**
 * Recupera o ID do usuário da sessão ou busca no banco via e-mail.
 * 
 * Estratégia:
 * 1. Verifica se user_id já está disponível na sessão
 * 2. Caso contrário, faz consulta ao banco usando o e-mail da sessão
 * 3. Retorna null se não conseguir identificar o usuário
 * 
 * @param mysqli $conn Conexão ativa com o banco de dados.
 * @return int|null ID do usuário ou null se não encontrado.
 */
function getSessionUserId(mysqli $conn): ?int
{
  // Primeira tentativa: usa ID já armazenado na sessão
  if (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0) {
    return (int) $_SESSION['user_id'];
  }

  // Segunda tentativa: busca pelo e-mail
  $email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';
  if ($email === '') {
    return null;
  }

  // Prepara consulta segura (prepared statement)
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

/**
 * Converte código numérico do status em descrição textual.
 * 
 * Mapeamento:
 * 0 = Aguardando pagamento (pedido criado mas não pago)
 * 1 = Processando (pagamento confirmado, em separação)
 * 2 = Enviado (em trânsito para o cliente)
 * 3 = Entregue (finalizado com sucesso)
 * Outros = Desconhecido (valor inválido)
 * 
 * @param int $status Código numérico do status (0-3).
 * @return string Descrição legível do status.
 */
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

// Captura o ID do pedido da query string (?pedido=123)
$pedidoId = isset($_GET['pedido']) ? (int) $_GET['pedido'] : 0;
// Obtém o ID do usuário logado
$userId = getSessionUserId($conn);

// Valida se o usuário está identificado e se o ID do pedido é válido
if ($userId === null || $pedidoId <= 0) {
  // Redireciona para lista de pedidos se houver problema
  header('Location: ' . url_path('public/pages/account/pedidos.php'));
  exit();
}

// Estruturas para armazenar dados do pedido, produtos e mensagens de erro
$pedido = null;
$produtos = [];
$erroPedido = '';

// Consulta que busca dados do pedido com endereço de entrega
// JOIN garante que apenas pedidos com endereço válido sejam retornados
// WHERE com dupla condição: pedido específico + pertence ao usuário (segurança)
$sqlPedido = 'SELECT p.PedId, p.PedData, p.PedValorTotal, p.PedStatus,
                     e.EndEntRua, e.EndEntNum, e.EndEntBairro, e.EndEntCid, e.EndEntEst, e.EndEntCep, e.EndEntComple
              FROM pedido p
              INNER JOIN enderecoentrega e ON p.EndEntId = e.EndEntId
              WHERE p.PedId = ? AND p.UsuId = ?';

// Executa consulta preparada para buscar o pedido
$stmtPedido = $conn->prepare($sqlPedido);
if ($stmtPedido) {
  // Vincula parâmetros: ID do pedido e ID do usuário
  $stmtPedido->bind_param('ii', $pedidoId, $userId);
  if ($stmtPedido->execute()) {
    $resPedido = $stmtPedido->get_result();
    // fetch_assoc retorna null se nenhum registro for encontrado
    $pedido = $resPedido->fetch_assoc();
    $resPedido->free();
  } else {
    $erroPedido = 'Não foi possível carregar o pedido solicitado.';
  }
  $stmtPedido->close();
} else {
  // Falha na preparação da consulta
  $erroPedido = 'Não foi possível acessar os detalhes do pedido.';
}

// Se o pedido foi encontrado, busca os produtos associados
if ($pedido) {
  // Consulta que lista todos os itens do pedido com informações do produto
  // JOIN com tabela roupa para obter nome do produto
  // Traz quantidade, preço unitário congelado e nome
  $sqlProdutos = 'SELECT pp.PedProQtd, pp.PedProPrecoUnitario, r.RoupaNome
                  FROM pedidoproduto pp
                  INNER JOIN roupa r ON pp.RoupaId = r.RoupaId
                  WHERE pp.PedId = ?';

  $stmtProdutos = $conn->prepare($sqlProdutos);
  if ($stmtProdutos) {
    $stmtProdutos->bind_param('i', $pedidoId);
    if ($stmtProdutos->execute()) {
      $resProdutos = $stmtProdutos->get_result();
      // Itera sobre todos os produtos e armazena no array
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

// Se não encontrou o pedido e não há erro específico, define mensagem genérica
if (!$pedido && $erroPedido === '') {
  $erroPedido = 'Pedido não encontrado.';
}

// Prepara todas as variáveis de exibição sanitizadas para prevenir XSS
$userName = htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8');
// URLs da navegação principal da área logada
$homeUrl = htmlspecialchars(url_path('index.php'), ENT_QUOTES, 'UTF-8');
$perfilUrl = htmlspecialchars(url_path('public/pages/account/perfil.php'), ENT_QUOTES, 'UTF-8');
$enderecosUrl = htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8');
$pedidosUrl = htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8');
$favoritosUrl = htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8');
$logoutUrl = htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8');
// URL específica para voltar à lista de pedidos
$pedidoListUrl = htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8');

// Variáveis para armazenar endereço formatado e URL para Google Maps
$enderecoCompleto = '';
$enderecoUrl = '';
if ($pedido) {
  // Formata o endereço completo para exibição legível
  // Inclui complemento apenas se existir
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
  // Constrói URL codificada para Google Maps embed
  // urlencode garante que caracteres especiais sejam tratados corretamente
  $enderecoUrl = urlencode($pedido['EndEntRua'] . ', ' . $pedido['EndEntNum'] . ', ' . $pedido['EndEntBairro'] . ', ' . $pedido['EndEntCid'] . ' - ' . $pedido['EndEntEst'] . ', ' . $pedido['EndEntCep']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <title>Detalhes do Pedido</title>
  <!-- Fonte Google Inter para consistência visual -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <!-- Ícone da página exibido na aba do navegador -->
  <link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
  <!-- Estilos reutilizados do perfil (sidebar, layout, cards) -->
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body>
  <!-- Botão fixo para retornar à lista de pedidos -->
  <button class="back-button" onclick="window.location.href='<?= $pedidoListUrl ?>'" >← Voltar</button>
  <!-- Barra lateral com perfil e navegação da conta -->
  <div class="sidebar">
    <div class="profile">
      <p>Olá!</p>
      <p><?= $userName ?></p>
    </div>
    <!-- Menu de navegação entre seções da conta, Pedidos marcado como ativo -->
    <nav>
      <a href="<?= $perfilUrl ?>">Dados pessoais</a>
      <a href="<?= $enderecosUrl ?>">Endereços</a>
      <a href="<?= $pedidosUrl ?>" class="active">Pedidos</a>
      <a href="<?= $favoritosUrl ?>">Favoritos</a>
      <a href="<?= $logoutUrl ?>">Sair</a>
    </nav>
  </div>
  <!-- Área principal com detalhes do pedido -->
  <div class="main-content">
    <h1>Detalhes do Pedido</h1>
    <?php if ($erroPedido !== ''): ?>
      <!-- Exibe mensagem de erro se houver problema ao carregar dados -->
      <div class="status-message estado-erro"><?= htmlspecialchars($erroPedido, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif (!$pedido): ?>
      <!-- Informa quando o pedido não foi encontrado -->
      <div class="status-message">Pedido não encontrado.</div>
    <?php else: ?>
      <!-- Grade de informações do pedido -->
      <div class="details-grid">
        <!-- Bloco 1: Resumo do pedido com dados principais -->
        <div class="info-block">
          <h2>Resumo</h2>
          <p><strong>Número:</strong> #<?= (int) $pedido['PedId'] ?></p>
          <p><strong>Data:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($pedido['PedData'])), ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Status:</strong> <?= htmlspecialchars(statusPedido((int) $pedido['PedStatus']), ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Valor Total:</strong> R$ <?= number_format((float) $pedido['PedValorTotal'], 2, ',', '.') ?></p>
        </div>
        <!-- Bloco 2: Endereço de entrega com mapa do Google Maps integrado -->
        <div class="info-block">
          <h2>Entrega</h2>
          <p><?= htmlspecialchars($enderecoCompleto, ENT_QUOTES, 'UTF-8') ?></p>
          <!-- Iframe do Google Maps mostrando localização do endereço -->
          <iframe width="100%" height="220" frameborder="0" style="border:0" src="https://www.google.com/maps?q=<?= htmlspecialchars($enderecoUrl, ENT_QUOTES, 'UTF-8') ?>&output=embed" allowfullscreen></iframe>
        </div>
      </div>

      <!-- Bloco 3: Lista de produtos comprados no pedido -->
      <div class="info-block" style="margin-top:24px;">
        <h2>Produtos</h2>
        <?php if (empty($produtos)): ?>
          <!-- Mensagem quando não há produtos (situação anômala) -->
          <p>Nenhum produto encontrado para este pedido.</p>
        <?php else: ?>
          <!-- Container da lista de produtos -->
          <div class="produto-lista">
            <?php foreach ($produtos as $item): ?>
              <!-- Item individual: nome, quantidade e preço unitário congelado -->
              <div class="produto-item">
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