<?php
/**
 * ============================================================
 * PÁGINA: Favoritos do Usuário
 * ============================================================
 * 
 * Arquivo: public/pages/shop/favoritos.php
 * Propósito: Exibe lista de produtos favoritados pelo usuário autenticado.
 * 
 * Funcionalidades:
 * - Listagem dinâmica de favoritos (carregada via JavaScript)
 * - Remoção de favoritos com feedback visual
 * - Cards de produto com: imagem, nome, preço, botão "Ver produto"
 * - Sidebar de navegação da área do cliente
 * - Proteção: redireciona para login se não autenticado
 * 
 * Estrutura da Página:
 * - Sidebar: navegação (Dados pessoais, Endereços, Pedidos, Favoritos, Sair)
 * - Main content: título + grid de produtos favoritos
 * - Botão voltar (fixo no topo direito)
 * 
 * API Utilizada:
 * - /public/api/favoritos.php: GET lista favoritos, DELETE remove favorito
 * 
 * JavaScript:
 * - favoritos.js: carrega favoritos via API, renderiza cards, gerencia remoção
 * 
 * Segurança:
 * - Verifica autenticação via $_SESSION['logged_in']
 * - Redireciona para login se não autenticado
 * - API valida UsuId via sessão no backend
 * 
 * Integração:
 * - Tabela `favorito` no MySQL (UsuId + RoupaId)
 * - Relacionamento N:N entre Usuario e Roupa
 * 
 * CSS:
 * - perfil.css: estilos da sidebar e layout da área do cliente
 */

// ===== INICIALIZAÇÃO =====
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// ===== DEFINIÇÃO DE URLs =====
$loginPage = url_path('public/pages/auth/login.php');

// ===== PROTEÇÃO DE AUTENTICAÇÃO =====
// Verifica se usuário está logado
// Se não: redireciona para página de login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  header('Location: ' . $loginPage);
  exit();
}

// ===== PREPARAÇÃO DE DADOS DO USUÁRIO =====
// Nome do usuário para exibição na sidebar
// Fallback para 'Usuário' se nome não estiver na sessão
$userName = htmlspecialchars($_SESSION['user_nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <title>Favoritos</title>
  
  <!-- Fonte Inter (Google Fonts) para tipografia moderna -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  
  <!-- Favicon -->
  <link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
  
  <!-- Estilos da área do cliente (sidebar, layout, cards) -->
  <link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
  
  <!-- ===== Estilos inline do botão voltar ===== -->
  <style>
    /* Botão fixo no topo direito para retornar à home */
    .back-button {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 1000;
      background-color: var(--highlight); /* Cor de destaque (definida em perfil.css) */
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
      opacity: 0.85; /* Efeito hover: reduz opacidade */
    }
  </style>
</head>
<body>
  <!-- ===== BOTÃO VOLTAR (fixo topo direito) ===== -->
  <button class="back-button" onclick="window.location.href='<?= htmlspecialchars(url_path('index.php'), ENT_QUOTES, 'UTF-8'); ?>'">← Voltar</button>
  
  <!-- ===== SIDEBAR DE NAVEGAÇÃO DA ÁREA DO CLIENTE ===== -->
  <div class="sidebar">
    <!-- Saudação ao usuário -->
    <div class="profile">
      <p>Olá!</p>
      <p><?php echo htmlspecialchars($_SESSION['user_nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    
    <!-- Menu de navegação -->
    <nav>
      <!-- Link para Dados Pessoais (nome, email, CPF, etc) -->
      <a href="<?= htmlspecialchars(url_path('public/pages/account/perfil.php'), ENT_QUOTES, 'UTF-8'); ?>">Dados pessoais</a>
      
      <!-- Link para Endereços de Entrega cadastrados -->
      <a href="<?= htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8'); ?>">Endereços</a>
      
      <!-- Link para Histórico de Pedidos -->
      <a href="<?= htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8'); ?>">Pedidos</a>
      
      <!-- Link para Favoritos (página atual - classe 'active') -->
      <a href="<?= htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8'); ?>" class="active">Favoritos</a>
      
      <!-- Link para Logout (destroi sessão e redireciona para login) -->
      <a href="<?= htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Sair</a>
    </nav>
  </div>

  <!-- ===== CONTEÚDO PRINCIPAL: LISTA DE FAVORITOS ===== -->
  <div class="main-content">
    <!-- Título da página -->
    <h1>Favoritos</h1>
    
    <!-- Mensagem de status (loading, erro, vazio) -->
    <!-- Atualizada por favoritos.js -->
    <div id="favoritosStatus" class="status-message">Carregando favoritos...</div>
    
    <!-- Container onde favoritos.js renderiza os cards de produtos -->
    <!-- aria-live="polite": acessibilidade (leitores de tela anunciam mudanças) -->
    <div class="product-list" id="favoritosContainer" aria-live="polite"></div>
  </div>

  <!-- ===== SCRIPT PRINCIPAL ===== -->
  <!-- Módulo ES6: favoritos.js -->
  <!-- Funcionalidades: -->
  <!-- - Carrega favoritos via fetch(/public/api/favoritos.php) -->
  <!-- - Renderiza cards com: imagem, nome, preço, botão remover -->
  <!-- - Remove favorito via DELETE /api/favoritos.php -->
  <!-- - Atualiza UI em tempo real -->
  <script type="module" src="<?= htmlspecialchars(asset_path('js/favoritos.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
