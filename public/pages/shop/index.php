<?php
/**
 * ============================================================
 * PÁGINA: Catálogo de Produtos (Shop)
 * ============================================================
 * 
 * Arquivo: public/pages/shop/index.php
 * Propósito: Exibe catálogo completo de produtos com sistema de filtros por categoria.
 * 
 * Funcionalidades:
 * - Listagem de todos os produtos do banco de dados
 * - Filtro por categoria via parâmetro GET (?filtro=camisas)
 * - Filtro client-side via JavaScript (filtro.js)
 * - Busca por nome de produto (JavaScript)
 * - Header dinâmico (diferente para usuários logados e visitantes)
 * - Cards de produto com: imagem, nome, preço, botão "Comprar"
 * - Responsividade mobile-first
 * 
 * Fluxo de Dados:
 * 1. Recebe parâmetro ?filtro da URL (opcional)
 * 2. Valida filtro contra whitelist
 * 3. Consulta todos os produtos no MySQL (com JOIN em categorias)
 * 4. Renderiza HTML com atributos data-tipo para filtro JavaScript
 * 5. JavaScript (filtro.js) manipula visibilidade dos cards
 * 
 * Parâmetros GET:
 * - filtro: slug da categoria (ex: 'camisas', 'calcados', 'todos')
 * 
 * Dependências:
 * - bootstrap.php: conexão $conn, helpers, sessão
 * - includes/header.php: função generateHeader()
 * - assets/js/filtro.js: filtros client-side e busca
 * - CSS: styleProdutos.css, body.css, header.css
 * 
 * Segurança:
 * - Validação de filtro contra whitelist
 * - Prepared statements nas consultas SQL
 * - Sanitização de saída com htmlspecialchars()
 * - Verificação de tipos com type casting
 */

// ===== INICIALIZAÇÃO =====
session_start(); // Necessário para verificar autenticação no header
require_once dirname(__DIR__, 2) . '/bootstrap.php'; // Carrega $conn, helpers, config

// ===== CAPTURA E VALIDAÇÃO DO FILTRO =====
// Recebe parâmetro 'filtro' da URL (ex: index.php?filtro=camisas)
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';
$classActive = ''; // Variável legacy (não utilizada atualmente)

// Whitelist de filtros permitidos (previne SQL injection e valores arbitrários)
$filtrosPermitidos = ['todos', 'calcados', 'calcas', 'blusas', 'camisas', 'conjuntos', 'outros', 'acessorios'];

// Valida filtro: se não estiver na whitelist, fallback para 'todos'
if (!in_array($filtro, $filtrosPermitidos, true)) {
  $filtro = 'todos';
}

// ===== MAPEAMENTO DE CATEGORIAS =====
// Converte IDs numéricos do banco (CatRId) para slugs de URL
// Usado para adicionar atributo data-tipo aos cards de produto
$categoriaSlugMap = [
  1 => 'calcados',    // Tênis, sapatos, sandálias
  2 => 'calcas',      // Jeans, moletom, alfaiataria
  3 => 'blusas',      // Blusas femininas
  4 => 'camisas',     // Camisetas, polos, sociais
  5 => 'conjuntos',   // Coord sets, uniformes esportivos
  6 => 'acessorios',  // Bonés, bolsas, cintos
];

// ===== CARREGAMENTO DO HEADER REUTILIZÁVEL =====
require_once __DIR__ . '/../includes/header.php';

// ===== INICIALIZAÇÃO DE VARIÁVEIS =====
$produtos = [];        // Array que armazenará todos os produtos do banco
$erroProdutos = '';    // Mensagem de erro caso a consulta falhe

// ===== CONSULTA DE PRODUTOS =====
// Query com INNER JOIN para obter dados completos do produto + categoria
// Retorna: ID, nome, modelo 3D, imagem, preço, categoria (ID, tipo, sessão)
// Ordenação: por categoria primeiro, depois por nome (alfabética)
$sql = "SELECT r.RoupaId, r.RoupaNome, r.RoupaModelUrl, r.RoupaImgUrl, r.RoupaValor, r.CatRId, c.CatRTipo, c.CatRSessao\n        FROM roupa r\n        INNER JOIN catroupa c ON c.CatRId = r.CatRId\n        ORDER BY r.CatRId, r.RoupaNome";

// Executa query e processa resultados
if ($resultado = $conn->query($sql)) {
  // Itera sobre todos os produtos retornados
  while ($linha = $resultado->fetch_assoc()) {
    // Adiciona slug amigável de URL baseado no CatRId
    // Fallback para 'outros' se categoria não estiver mapeada
    $linha['slug'] = $categoriaSlugMap[$linha['CatRId']] ?? 'outros';
    $produtos[] = $linha;
  }
  // Libera memória do resultado
  $resultado->free();
} else {
  // Captura erro de consulta (problema de conexão, sintaxe SQL, etc)
  $erroProdutos = 'Não foi possível carregar os produtos. Tente novamente em instantes.';
}

// ===== GERAÇÃO DO HEADER DINÂMICO =====
// Passa $filtro para marcar categoria ativa no menu de navegação
$header = generateHeader($conn, $filtro);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Imperium</title>
  
  <!-- Favicon do site -->
  <link rel="icon" href="<?= asset_path('img/catalog/icone.ico') ?>">
  
  <!-- Estilos do catálogo de produtos -->
  <link rel="stylesheet" href="<?= asset_path('css/styleProdutos.css') ?>"> <!-- Cards, grid, layout -->
  <link rel="stylesheet" href="<?= asset_path('css/body.css') ?>">          <!-- Estilos globais do body -->
  <link rel="stylesheet" href="<?= asset_path('css/header.css') ?>">        <!-- Header e navegação -->

</head>

<body>

  <!-- ===== HEADER DINÂMICO ===== -->
  <!-- Gerado por generateHeader($conn, $filtro) -->
  <!-- Varia conforme autenticação: visitante (link login) vs autenticado (ícones carrinho/perfil) -->
  <?= $header ?>

  <!-- ===== SEÇÃO DE FILTROS E BUSCA ===== -->
  <!-- Barra de pesquisa para buscar produtos por nome -->
  <!-- JavaScript (filtro.js) manipula visibilidade dos cards baseado em busca e filtros -->
  <section class="filtros">
    <!-- Barra de pesquisa (desktop e mobile) -->
    <div class="search-bar">
      <img src="<?= asset_path('img/catalog/pesquisar.png') ?>" alt="Lupa" class="search-icon">
      <!-- Input de busca: filtro.js escuta eventos 'input' para filtrar produtos em tempo real -->
      <input type="text" placeholder="O que você está buscando?">
      <!-- Botão para limpar busca -->
      <img src="<?= asset_path('img/catalog/fechar.png') ?>" alt="Fechar" class="fechar">
    </div>
    <!-- Ícone de pesquisa mobile (abre barra de busca em telas pequenas) -->
    <div class="icons">
      <img src="<?= asset_path('img/catalog/pesquisar.png') ?>" alt="Pesquisar" class="pesquisar">
    </div>
  </section>

  <!-- ===== GRID DE PRODUTOS ===== -->
  <!-- Layout responsivo: grid automático ajusta colunas baseado no tamanho da tela -->
  <main class="produtos">
    <?php if ($erroProdutos) : ?>
      <!-- Mensagem de erro caso a consulta SQL falhe -->
      <p class="estado-lista estado-erro"><?= htmlspecialchars($erroProdutos, ENT_QUOTES, 'UTF-8') ?></p>
    <?php elseif (empty($produtos)) : ?>
      <!-- Mensagem quando não há produtos cadastrados no banco -->
      <p class="estado-lista">Nenhum produto cadastrado no momento.</p>
    <?php else : ?>
      <!-- Loop pelos produtos retornados do banco -->
      <?php foreach ($produtos as $produto) :
        // ===== SANITIZAÇÃO E FORMATAÇÃO =====
        // Escapa caracteres especiais para prevenir XSS
        $nomeProduto = htmlspecialchars($produto['RoupaNome'], ENT_QUOTES, 'UTF-8');
        
        // Resolve URL completa da imagem usando helper asset_path()
        $imgProduto = asset_path((string) ($produto['RoupaImgUrl'] ?? ''));
        $imgProdutoEscaped = htmlspecialchars($imgProduto, ENT_QUOTES, 'UTF-8');
        
        // Slug para atributo data-tipo (usado por filtro.js)
        $slugTipo = htmlspecialchars($produto['slug'], ENT_QUOTES, 'UTF-8');
        
        // Formata preço para padrão brasileiro: R$ 99,90
        $precoFormatado = number_format((float) $produto['RoupaValor'], 2, ',', '.');
      ?>
        <!-- ===== CARD DE PRODUTO ===== -->
        <!-- data-tipo: usado por filtro.js para filtrar cards por categoria -->
        <!-- JavaScript mostra/oculta cards baseado em busca e filtro ativo -->
        <div class="produto" data-tipo="<?= $slugTipo ?>">
          <!-- Imagem do produto (thumbnail 2D) -->
          <img src="<?= $imgProdutoEscaped ?>" alt="<?= $nomeProduto ?>" />
          
          <!-- Nome do produto -->
          <h3><?= $nomeProduto ?></h3>
          
          <!-- Preço formatado (R$ 99,90) -->
          <div class="preco">R$ <?= $precoFormatado ?></div>
          
          <!-- Link para página de detalhes do produto -->
          <!-- produto.php?id={RoupaId} exibe visualizador 3D e opções de compra -->
          <a href="produto.php?id=<?= (int) $produto['RoupaId'] ?>" class="cta-comprar" data-produto-id="<?= (int) $produto['RoupaId'] ?>">
            <button type="button">Comprar</button>
          </a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </main>

  <!-- ===== SCRIPT DE FILTROS E BUSCA ===== -->
  <!-- Módulo ES6: filtro.js -->
  <!-- Funcionalidades: -->
  <!-- - Filtra produtos por categoria (lê data-tipo dos cards) -->
  <!-- - Busca em tempo real por nome do produto -->
  <!-- - Mostra/oculta barra de busca mobile -->
  <!-- - Limpa filtros e busca -->
  <script type="module" src="<?= asset_path('js/filtro.js') ?>"></script>
</body>

</html>