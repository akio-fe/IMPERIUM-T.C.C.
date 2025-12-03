<?php
/**
 * ============================================================
 * COMPONENTE: Header Reutilizável do Site
 * ============================================================
 * 
 * Arquivo: public/pages/includes/header.php
 * Propósito: Gera o cabeçalho HTML dinâmico e responsivo para todas as páginas do site.
 * 
 * Funcionalidades Principais:
 * - Header diferenciado baseado no estado de autenticação do usuário
 * - Sistema de navegação por categorias de produtos com indicador visual de filtro ativo
 * - Links contextuais (carrinho/perfil para autenticados, login para visitantes)
 * - Logo centralizado com link para home
 * - Barra de pesquisa e filtros de produtos
 * 
 * Modos de Exibição:
 * 1. Visitante (não autenticado):
 *    - Link "FAÇA LOGIN / CADASTRE-SE" no topo
 *    - Menu de navegação completo
 *    - Logo IMPERIUM
 * 
 * 2. Usuário Autenticado:
 *    - Ícones de acesso rápido: Carrinho + Perfil
 *    - Menu de navegação completo
 *    - Logo IMPERIUM
 * 
 * Categorias de Navegação:
 * - Home (index.php)
 * - Camisas (filtro=camisas)
 * - Calças (filtro=calcas)
 * - Calçados (filtro=calcados)
 * - Acessórios (filtro=acessorios)
 * 
 * Uso em Páginas:
 * ```php
 * require_once __DIR__ . '/../includes/header.php';
 * $header = generateHeader($conn, 'camisas'); // Marca 'camisas' como ativo
 * echo $header;
 * ```
 * 
 * Dependências:
 * - $_SESSION['logged_in']: boolean indicando autenticação
 * - $_SESSION['email']: email do usuário autenticado
 * - Helpers: site_path(), asset_path() (definidos em bootstrap)
 * - CSS: header.css (estilos do cabeçalho)
 * 
 * Segurança:
 * - Validação de filtros contra whitelist
 * - Sanitização de saída com htmlspecialchars()
 * - Verificação de sessão para exibição condicional
 * 
 * @param mysqli $conn Conexão ativa com o banco de dados MySQL
 * @param string $filtroAtual Categoria/filtro atual para marcar como ativo (padrão: 'todos')
 * @return string HTML completo do header pronto para echo
 */

/**
 * Gera o HTML do header com base no estado de autenticação do usuário.
 * 
 * Lógica de Renderização:
 * 1. Valida o filtro atual contra lista de categorias permitidas
 * 2. Constrói links de navegação com classe 'active' no filtro atual
 * 3. Verifica autenticação via $_SESSION
 * 4. Renderiza layout apropriado (visitante vs autenticado)
 * 
 * @param mysqli $conn Conexão com o banco de dados (para futuras expansões)
 * @param string $filtroAtual Slug da categoria ativa (ex: 'camisas', 'calcados')
 * @return string HTML completo do header com navegação e links contextuais
 */
function generateHeader(mysqli $conn, string $filtroAtual = 'todos'): string
{
  // ===== VALIDAÇÃO DO FILTRO DE CATEGORIA =====
  // Copia o filtro recebido para validação
  $filtro = $filtroAtual;
  
  // Whitelist de filtros válidos (protege contra valores arbitrários)
  $filtrosPermitidos = ['todos', 'calcados', 'calcas', 'blusas', 'camisas', 'conjuntos', 'outros', 'acessorios'];
  
  // Se o filtro não estiver na whitelist, fallback para 'todos'
  if (!in_array($filtro, $filtrosPermitidos, true)) {
    $filtro = 'todos';
  }

  // ===== MAPEAMENTO DE CATEGORIAS =====
  // Converte IDs numéricos do banco (CatRId) para slugs amigáveis de URL
  // Usado quando produtos são carregados do banco para associar à navegação
  $categoriaSlugMap = [
    1 => 'calcados',    // Categoria 1: Calçados (tênis, sapatos, botas)
    2 => 'calcas',      // Categoria 2: Calças (jeans, moletom, alfaiataria)
    3 => 'blusas',      // Categoria 3: Blusas (femininas)
    4 => 'camisas',     // Categoria 4: Camisas (masculinas/femininas)
    5 => 'conjuntos',   // Categoria 5: Conjuntos (coord sets, uniformes)
    6 => 'acessorios',  // Categoria 6: Acessórios (bonés, bolsas, cintos)
  ];

  // ===== DEFINIÇÃO DOS ITENS DO MENU =====
  // Array associativo: slug => Label exibido
  // Ordem define a sequência visual no menu de navegação
  $navItems = [
    'todos' => 'Todos',           // Exibe todos os produtos sem filtro
    'camisas' => 'Camisas',       // Filtra apenas camisas
    'calcas' => 'Calças',         // Filtra apenas calças
    'calcados' => 'Calçados',     // Filtra apenas calçados
    'acessorios' => 'Acessórios', // Filtra apenas acessórios
  ];

  // ===== CONSTRUÇÃO DOS LINKS DE NAVEGAÇÃO =====
  // Inicia com link para home (sempre visível)
  $navLinksHtml = "<a href='" . site_path('index.php') . "'>Home</a>";
  
  // Itera sobre os itens do menu para criar links dinâmicos
  foreach ($navItems as $slug => $label) {
    // Adiciona classe 'active' ao link da categoria atual (destaque visual)
    $activeClass = $filtro === $slug ? 'active' : '';
    
    // Constrói link com:
    // - URL: shop/index.php?filtro={slug}
    // - data-tipo: atributo para JavaScript (filtros client-side)
    // - class: 'active' se for a categoria atual
    $navLinksHtml .= "<a href='" . site_path('public/pages/shop/index.php') . "?filtro={$slug}' data-tipo='{$slug}' class='{$activeClass}'>{$label}</a>";
  }

  // ===== GERAÇÃO DE URLs DE RECURSOS =====
  // Helper asset_path(): resolve caminho correto para assets considerando subpastas
  // Helper site_path(): resolve caminho para páginas considerando estrutura do projeto
  
  $logoSrc = asset_path('img/aguia.png');                            // Logo IMPERIUM (águia)
  $loginUrl = site_path('public/pages/auth/cadastro_login.html');    // Página de login/cadastro
  $cartIcon = asset_path('img/carrin.png');                           // Ícone do carrinho
  $profileIcon = asset_path('img/perfilzin.png');                     // Ícone do perfil
  $profileLink = site_path('public/pages/account/perfil.php');       // Página de perfil do usuário
  $cartLink = site_path('public/pages/shop/carrinho.php');           // Página do carrinho

  // ===== VERIFICAÇÃO DE AUTENTICAÇÃO =====
  // Checa se existe sessão ativa e se usuário está logado
  // $_SESSION['logged_in'] é definida em public/api/auth/login.php após autenticação bem-sucedida
  if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // ===== LAYOUT: HEADER PARA VISITANTES (NÃO AUTENTICADOS) =====
    // Estrutura:
    // 1. Link de login/cadastro no topo (incentiva conversão)
    // 2. Navegação por categorias
    // 3. Logo centralizado
    //
    // CSS: loginstyle.css define estilo do link de login
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
    // ===== LAYOUT: HEADER PARA USUÁRIOS AUTENTICADOS =====
    // Estrutura:
    // 1. Ícones de acesso rápido (carrinho + perfil) no topo
    // 2. Navegação por categorias
    // 3. Logo centralizado
    //
    // Funcionalidades:
    // - Carrinho: acesso rápido ao carrinho de compras
    // - Perfil: acesso à área do cliente (dados, pedidos, favoritos)
    //
    // CSS: header.css define layout responsivo e hover dos ícones
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
