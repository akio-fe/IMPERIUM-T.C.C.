<?php
/**
 * Header Reutilizável
 * Propósito: Gera o cabeçalho HTML dinâmico para as páginas do site.
 * 
 * Funcionalidades:
 * - Header diferenciado para usuários logados e não logados
 * - Navegação por categorias com filtro ativo
 * - Links para carrinho e perfil (quando autenticado)
 * - Link para login/cadastro (quando não autenticado)
 * 
 * Uso:
 * require_once __DIR__ . '/includes/header.php';
 * echo generateHeader($conn, $filtroAtual);
 * 
 * @param mysqli $conn Conexão ativa com o banco de dados
 * @param string $filtroAtual Filtro de categoria atual (opcional, padrão: 'todos')
 * @return string HTML do header completo
 */

/**
 * Gera o HTML do header com base no estado de autenticação do usuário.
 * 
 * @param mysqli $conn Conexão com o banco de dados
 * @param string $filtroAtual Categoria ativa no momento
 * @return string HTML do header
 */
function generateHeader(mysqli $conn, string $filtroAtual = 'todos'): string
{
  // Determina o filtro de categoria
  $filtro = $filtroAtual;
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

  // Itens do menu de navegação
  $navItems = [
    'todos' => 'Todos',
    'camisas' => 'Camisas',
    'calcas' => 'Calças',
    'calcados' => 'Calçados',
    'acessorios' => 'Acessórios',
  ];

  // Constrói os links de navegação
  $navLinksHtml = "<a href='" . site_path('index.php') . "'>Home</a>";
  foreach ($navItems as $slug => $label) {
    $activeClass = $filtro === $slug ? 'active' : '';
    $navLinksHtml .= "<a href='" . site_path('public/pages/shop/index.php') . "?filtro={$slug}' data-tipo='{$slug}' class='{$activeClass}'>{$label}</a>";
  }

  // URLs dos recursos
  $logoSrc = asset_path('img/aguia.png');
  $loginUrl = site_path('public/pages/auth/cadastro_login.html');
  $cartIcon = asset_path('img/carrin.png');
  $profileIcon = asset_path('img/perfilzin.png');
  $profileLink = site_path('public/pages/account/perfil.php');
  $cartLink = site_path('public/pages/shop/carrinho.php');

  // Verifica se o usuário está autenticado
  if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Header para visitantes: mostra link de login/cadastro
    return "<header>
        <div class='linkLogin'>
            <a href='{$loginUrl}'><i class='fa-solid fa-user'></i>FAÇA LOGIN / CADASTRE-SE</a>
        </div>
        <nav>
            {$navLinksHtml}
        </nav>
        <img src='{$logoSrc}' alt='Imperium'>
    </header>";
  } else {
    // Header para usuários autenticados: mostra ícones de carrinho e perfil
    return "<header>
        <div class='acicons'>
                <a href='{$cartLink}'><img src='{$cartIcon}' alt='Carrinho'></a>
                <a href='{$profileLink}'><img src='{$profileIcon}' alt='Perfil'></a>
            </div> 
        
        <nav>{$navLinksHtml}</nav>

        <img src='{$logoSrc}' alt='Imperium'>
                   
    </header>";
  }
}
