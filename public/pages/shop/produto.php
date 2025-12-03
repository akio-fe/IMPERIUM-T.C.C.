<?php
/**
 * ============================================================
 * PÁGINA: Detalhes do Produto
 * ============================================================
 * 
 * Arquivo: public/pages/shop/produto.php
 * Propósito: Página individual de produto com visualizador 3D interativo e opções de compra.
 * 
 * Funcionalidades Principais:
 * - Carregamento de dados completos do produto (nome, preço, categoria, modelos)
 * - Visualizador 3D interativo usando Three.js (GLB/GLTF)
 * - Controles de câmera: rotação, zoom, pan
 * - Sistema de seleção de tamanhos dinâmico
 * - Toggle de favoritos com feedback visual (coração)
 * - Botão adicionar ao carrinho com validação
 * - Botões especiais para conjuntos (exibe partes separadas do modelo 3D)
 * - Header dinâmico baseado em autenticação
 * - Navegação por categorias com link ativo
 * 
 * Tecnologias:
 * - Three.js: renderização 3D no canvas
 * - GLTFLoader: carregamento de modelos .glb/.gltf
 * - OrbitControls: controles de câmera interativos
 * - Firebase Authentication: favoritos requer login
 * - MySQL: busca produto e verifica favoritos
 * 
 * Fluxo de Dados:
 * 1. Recebe ID do produto via GET (?id=123)
 * 2. Valida ID (FILTER_VALIDATE_INT)
 * 3. Consulta produto no MySQL com JOIN em categorias
 * 4. Verifica se produto está nos favoritos do usuário
 * 5. Monta payload JSON com dados do produto
 * 6. JavaScript (produto.js) renderiza modelo 3D
 * 7. JavaScript gerencia favoritos, tamanhos, carrinho
 * 
 * Parâmetros GET:
 * - id: RoupaId do produto (obrigatório, inteiro)
 * 
 * APIs JavaScript Utilizadas:
 * - /public/api/favoritos.php: POST adiciona, DELETE remove favorito
 * - /public/api/carrinho/adicionar.php: POST adiciona item ao carrinho
 * 
 * Modelos 3D:
 * - Formato: GLB (Binary glTF) ou GLTF (JSON + binários)
 * - Armazenamento: storage/models/{categoria}/
 * - Carregamento: via GLTFLoader do Three.js
 * - Otimizações: compressão Draco, texturas comprimidas
 * 
 * Categorias Especiais (Conjuntos):
 * - CatRId 5 ou 11: exibe botões de alternância
 * - Botões: Completo | Parte Superior | Calça
 * - produto.js mostra/oculta meshes do modelo 3D
 * 
 * Segurança:
 * - Validação rigorosa de ID (FILTER_VALIDATE_INT)
 * - Prepared statements em todas as consultas SQL
 * - Sanitização de saída com htmlspecialchars()
 * - Verificação de autenticação para favoritos
 * - Validação de tipo nas variáveis (type casting)
 * 
 * Dependências:
 * - bootstrap.php: conexão $conn, helpers, sessão
 * - includes/header.php: função generateHeader()
 * - assets/js/produto.js: renderização 3D, favoritos, tamanhos
 * - CSS: header.css, produto.css
 * - Three.js: carregado via CDN em produto.js
 */

// Inicia sessão para verificar autenticação
session_start();
// Carrega configurações, helpers e conexão com banco de dados
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';

/**
 * Recupera o ID do usuário da sessão via busca no banco.
 * 
 * Estratégia:
 * 1. Verifica se há email na sessão
 * 2. Consulta banco para obter UsuId correspondente
 * 3. Retorna null se usuário não encontrado
 * 
 * @param mysqli $conn Conexão ativa com o banco de dados.
 * @return int|null ID do usuário ou null se não encontrado.
 */
function getSessionUserId(mysqli $conn): ?int
{
  // Obtém email da sessão
  $email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';
  if ($email === '') {
    return null;
  }

  // Busca UsuId no banco através do email
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

// Captura e valida ID do produto da URL (retorna false se inválido)
$produtoId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$erroProduto = ''; // Mensagem de erro caso produto não seja encontrado
$produto = null; // Armazena dados do produto
$userId = null; // ID do usuário logado
// Verifica se usuário está autenticado
$isAuthenticated = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
if ($isAuthenticated) {
  // Busca userId no banco para confirmar autenticação válida
  $userId = getSessionUserId($conn);
  if ($userId === null) {
    // Email na sessão não corresponde a usuário válido
    $isAuthenticated = false;
  }
}

// Validação inicial: ID do produto deve ser válido
if (!$produtoId) {
  $erroProduto = 'Produto inválido ou não informado.';
} else {
  // Consulta produto com JOIN para obter dados da categoria
  // Traz: ID, nome, imagem, valor, modelo 3D, categoria
  $sql = "SELECT r.RoupaId, r.RoupaNome, r.RoupaImgUrl, r.RoupaValor, r.RoupaModelUrl, r.CatRId, c.CatRTipo FROM roupa r INNER JOIN catroupa c ON c.CatRId = r.CatRId WHERE r.RoupaId = ? LIMIT 1";
  if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param('i', $produtoId);
    if ($stmt->execute()) {
      $resultado = $stmt->get_result();
      // fetch_assoc retorna null se não encontrar registro
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

// Verifica se o produto está nos favoritos do usuário
$isFavorito = false;
if ($produto && $isAuthenticated && $userId !== null) {
  // Consulta tabela Favorito simplificada para verificar se existe registro
  $stmtFav = $conn->prepare('SELECT 1 FROM favorito WHERE RoupaId = ? AND UsuId = ? LIMIT 1');
  if ($stmtFav) {
    $stmtFav->bind_param('ii', $produto['RoupaId'], $userId);
    $stmtFav->execute();
    $stmtFav->store_result();
    // Se num_rows > 0, produto está favoritado
    $isFavorito = $stmtFav->num_rows > 0;
    $stmtFav->close();
  }
}

// Prepara variáveis de exibição sanitizadas
$nomeProduto = $produto ? htmlspecialchars($produto['RoupaNome'], ENT_QUOTES, 'UTF-8') : '';
$imgProduto = $produto ? asset_path((string) $produto['RoupaImgUrl']) : '';
$precoProduto = $produto ? (float) $produto['RoupaValor'] : 0.0;
// Formata preço no padrão brasileiro (R$ 99,90)
$precoFormatado = $produto ? 'R$ ' . number_format($precoProduto, 2, ',', '.') : '';

// Processa caminho do modelo 3D (.glb) para visualização
$modelPath = '';
if ($produto && !empty($produto['RoupaModelUrl'])) {
  // Normaliza barras invertidas para barras normais
  $rawModelUrl = str_replace('\\', '/', trim((string) $produto['RoupaModelUrl']));
  if ($rawModelUrl !== '') {
    // Extrai apenas o path se houver URL completa
    $parsed = parse_url($rawModelUrl);
    if (is_array($parsed) && !empty($parsed['path'])) {
      $rawModelUrl = $parsed['path'];
    }
    // Remove barra inicial
    $rawModelUrl = ltrim($rawModelUrl, '/');
    // Remove prefixo 'imperium/' se presente (evita duplicação)
    if (stripos($rawModelUrl, 'imperium/') === 0) {
      $rawModelUrl = substr($rawModelUrl, strlen('imperium/')) ?: '';
    }
    if ($rawModelUrl !== '') {
      // Obtém o prefixo base do projeto
      $prefix = base_url_prefix();
      // Constrói o caminho completo com o prefixo correto
      $modelPath = ($prefix === '' ? '' : $prefix) . '/' . $rawModelUrl;
    }
  }
}

// Mapa de IDs de categoria para slugs (usado na navegação)
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

// Determina categoria atual para marcar link ativo no menu
$currentSlug = 'todos';
if ($produto && isset($produto['CatRId'])) {
  $currentSlug = $categoriaSlugMap[(int) $produto['CatRId']] ?? 'todos';
}

// Gera o header com a categoria atual
$header = generateHeader($conn, $currentSlug);

// Constrói payload JSON com todos os dados do produto para uso em JavaScript
// Será inserido como data-attribute no HTML para acesso client-side
$produtoPayload = $produto ? [
  'id' => (int) $produto['RoupaId'],
  'nome' => $produto['RoupaNome'],
  'imagem' => $imgProduto,
  'preco' => $precoProduto,
  'precoFormatado' => $precoFormatado,
  'link' => 'produto.php?id=' . (int) $produto['RoupaId'],
  'modelPath' => $modelPath ?: null, // Caminho do modelo 3D (.glb)
  'favorito' => $isFavorito, // Estado atual de favorito
  'isAuthenticated' => $isAuthenticated, // Para validar ações restritas
  'categoriaId' => (int) $produto['CatRId'],
  'categoriaNome' => $produto['CatRTipo'],
] : null;

// Serializa payload para JSON e sanitiza para uso seguro em HTML
$produtoJson = $produtoPayload ? htmlspecialchars(json_encode($produtoPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <!-- Título dinâmico: nome do produto ou genérico -->
  <title><?= $produto ? $nomeProduto . ' - Imperium' : 'Produto - Imperium' ?></title>
  <!-- Ícone da página -->
  <link rel="icon" href="<?= asset_path('img/catalog/camisa.ico') ?>">
  <!-- Estilos do header (navegação, logo) -->
  <link rel="stylesheet" href="<?= asset_path('css/header.css') ?>">
  <!-- Estilos específicos da página de produto (visualizador 3D, detalhes) -->
  <link rel="stylesheet" href="<?= asset_path('css/produto.css') ?>">
</head>

<body>
  <!-- Header dinâmico (variável conforme autenticação) -->
  <?= $header ?>
  <main>
    <?php if ($erroProduto): ?>
      <!-- Mensagem de erro se produto não foi carregado -->
      <p class="estado-lista estado-erro"><?= htmlspecialchars($erroProduto, ENT_QUOTES, 'UTF-8') ?></p>
    <?php else: ?>
      <!-- Container principal do produto com payload JSON no data-attribute -->
      <div class="container-produto" data-produto='<?= $produtoJson ?>'>
        <!-- Container do visualizador 3D (renderizado por produto.js + Three.js) -->
        <div id="container3D" aria-live="polite"></div>
        <!-- Seção de detalhes do produto -->
        <div class="detalhes">
          <p class="categoria"><?= htmlspecialchars($produto['CatRTipo'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
          <h1><?= $nomeProduto ?></h1>
          <div class="preco"><?= $precoFormatado ?></div>
          <div class="parcelamento">Ou em até 12x sem juros</div>

          <?php if ($produto && ((int)$produto['CatRId'] === 5 || (int)$produto['CatRId'] === 11)): ?>
          <!-- Botões de alternância para conjuntos (categoria ID 5=Masculino, 11=Feminino) -->
          <!-- Permite visualizar modelo 3D completo, apenas parte superior ou apenas calça -->
          <div class="alternancia-conjunto" style="margin: 15px 0;">
            <button type="button" class="btn-parte" data-parte="completo" style="background: #2d3436; color: white; padding: 8px 16px; border: none; border-radius: 4px; margin-right: 8px; cursor: pointer;">Completo</button>
            <button type="button" class="btn-parte" data-parte="superior" style="background: #dfe6e9; color: #2d3436; padding: 8px 16px; border: none; border-radius: 4px; margin-right: 8px; cursor: pointer;">Parte Superior</button>
            <button type="button" class="btn-parte" data-parte="inferior" style="background: #dfe6e9; color: #2d3436; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">Calça</button>
          </div>
          <?php endif; ?>

          <!-- Container de tamanhos (botões inseridos dinamicamente por JS) -->
          <div class="tamanhos" aria-label="Selecione o tamanho">
            <!-- Tamanhos serão inseridos dinamicamente via JavaScript -->
          </div>
          <!-- Botão para adicionar produto ao carrinho -->
          <button class="btn-verde" id="btn-add-cart" type="button">
            Adicionar ao Carrinho
          </button>

          <!-- Toggle de favorito (checkbox estilizado como coração) -->
          <label class="toggle-favorito" aria-label="Favoritar produto">
            <!-- Checked se já está favoritado, data-requires-login se não autenticado -->
            <input type="checkbox" id="btn-favoritar" <?= $isFavorito ? 'checked' : '' ?> <?= $isAuthenticated ? '' : 'data-requires-login="1"' ?> />
            <span>❤️</span>
          </label>
        </div>
      </div>
    <?php endif; ?>
  </main>
  <?php
  // URLs para uso nos scripts JavaScript
  $loginUrl = url_path('public/pages/auth/cadastro_login.html');
$addCarrinhoUrl = url_path('public/api/carrinho/adicionar.php');
  ?>
  <!-- Script principal: renderiza modelo 3D, gerencia favoritos, tamanhos -->
  <script type="module" src="<?= asset_path('js/produto.js') ?>"></script>
  <!-- Script inline: gerencia adicionar ao carrinho com validações -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const container = document.querySelector('.container-produto');
      const btnAddCart = document.getElementById('btn-add-cart');
      if (!container || !btnAddCart) {
        return;
      }

      // Parse dos dados do produto do data-attribute
      let produtoData = null;
      try {
        produtoData = JSON.parse(container.dataset.produto ?? '{}');
      } catch (error) {
        produtoData = null;
      }

      // Cria elemento para feedback visual (mensagens de erro/sucesso)
      const feedback = document.createElement('p');
      feedback.className = 'estado-lista';
      feedback.style.marginTop = '8px';
      btnAddCart.insertAdjacentElement('afterend', feedback);

      // Handler do clique no botão adicionar ao carrinho
      btnAddCart.addEventListener('click', async () => {
        // Validação 1: produto deve estar disponível
        if (!produtoData || !produtoData.id) {
          feedback.textContent = 'Produto indisponível.';
          return;
        }

        // Validação 2: usuário deve estar autenticado
        if (!produtoData.isAuthenticated) {
          window.location.href = <?= json_encode($loginUrl) ?>;
          return;
        }

        // Validação 3: tamanho deve estar selecionado
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

        // Desabilita botão durante processamento
        btnAddCart.disabled = true;
        feedback.textContent = 'Adicionando ao carrinho...';

        try {
          // Envia requisição POST para API de adicionar ao carrinho
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

          // Status 401: sessão expirou, redireciona para login
          if (response.status === 401) {
            window.location.href = <?= json_encode($loginUrl) ?>;
            return;
          }

          // Verifica se operação foi bem-sucedida
          if (!response.ok || !payload.sucesso) {
            throw new Error(payload.mensagem || 'Erro ao salvar no carrinho.');
          }

          // Sucesso: exibe mensagem de confirmação
          feedback.textContent = 'Produto adicionado! Abra o carrinho para finalizar.';
        } catch (error) {
          // Captura e exibe erros (rede, servidor, etc)
          feedback.textContent = error.message || 'Não foi possível adicionar ao carrinho.';
        } finally {
          // Reabilita botão após processamento
          btnAddCart.disabled = false;
        }
      });
    });
  </script>
</body>

</html>