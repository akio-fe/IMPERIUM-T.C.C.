<?php
/**
 * Página: Perfil do Usuário - Dados Pessoais
 * Propósito: Exibe informações cadastrais do usuário autenticado.
 * 
 * Funcionalidades:
 * - Visualização de dados pessoais (nome, email, CPF, telefone, data de nascimento)
 * - Formatação automática de CPF, telefone e datas para padrão brasileiro
 * - Links para edição e exclusão de conta
 * - Opção de newsletter (preferência de recebimento de e-mails promocionais)
 * - Menu lateral de navegação entre seções da conta
 * - Integração com sistema de header reutilizável
 * 
 * Segurança:
 * - Requer autenticação (sessão ativa)
 * - Sanitização de todas as saídas HTML (proteção XSS)
 * - Validação de filtros contra injeção
 * 
 * Dependências:
 * - bootstrap.php: Configurações, conexão DB, helpers
 * - header.php: Header reutilizável da aplicação
 * - perfil.css: Estilos da página de perfil
 * - delete.js: Script para deleção de conta
 */

// Inicia a sessão para recuperar os dados do usuário autenticado
session_start();
// Carrega o bootstrap com configurações, helpers e conexão com o banco
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// Caminho da tela de login utilizada nos redirecionamentos
$loginPage = url_path('public/pages/auth/login.php');

// Validação de autenticação: redireciona para login se não autenticado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
  // Impede acesso direto ao perfil redirecionando para a tela de login
  header('Location: ' . $loginPage);
  exit();
}

// Helpers responsáveis por tratar e exibir os dados com segurança e formatação

/**
 * Escapa valores antes de exibi-los no HTML para evitar XSS.
 *
 * @param mixed $value Valor bruto vindo da sessão/banco.
 * @return string Valor sanitizado pronto para output.
 */
function sanitizeField($value)
{
  return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Formata um CPF para o padrão brasileiro, mantendo fallback para valores inválidos.
 *
 * @param mixed $cpf Documento informado pelo usuário.
 * @return string CPF formatado ou valor original sanitizado.
 */
function formatCpf($cpf)
{
  $digits = preg_replace('/\D+/', '', (string)$cpf);
  if (strlen($digits) === 11) {
    $formatted = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
    return sanitizeField($formatted);
  }
  return sanitizeField($cpf);
}

/**
 * Converte datas diversas para o formato brasileiro DD/MM/AAAA.
 *
 * @param mixed $date Data em formato aceito por DateTime.
 * @return string Data formatada ou conteúdo original quando inválido.
 */
function formatDateBr($date)
{
  $rawDate = trim((string)$date);
  if ($rawDate === '') {
    return '';
  }
  try {
    $dateTime = new DateTime($rawDate);
    return sanitizeField($dateTime->format('d/m/Y'));
  } catch (Exception $e) {
    return sanitizeField($date);
  }
}

/**
 * Adapta telefones fixos e celulares para os padrões (XX) XXXX-XXXX ou (XX) XXXXX-XXXX.
 *
 * @param mixed $phone Número informado pelo usuário.
 * @return string Telefone formatado ou valor original sanitizado.
 */
function formatPhone($phone)
{
  // Remove todos os caracteres não numéricos do telefone
  $digits = preg_replace('/\D+/', '', (string)$phone);
  
  // Celular: 11 dígitos - formato (XX) XXXXX-XXXX
  if (strlen($digits) === 11) {
    $formatted = sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
    return sanitizeField($formatted);
  }
  // Fixo: 10 dígitos - formato (XX) XXXX-XXXX
  if (strlen($digits) === 10) {
    $formatted = sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
    return sanitizeField($formatted);
  }
  // Retorna valor original se não corresponder aos formatos esperados
  return sanitizeField($phone);
}

/**
 * Sistema de filtros para integração com header da loja.
 * Permite que a página de perfil mantenha o contexto de navegação
 * caso o usuário tenha vindo de uma categoria específica.
 */

// Define o filtro atual a partir da query string, caindo em "todos" se nada vier
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';
$classActive = '';
// Lista branca de filtros permitidos (validação de segurança)
$filtrosPermitidos = ['todos', 'calcados', 'calcas', 'blusas', 'camisas', 'conjuntos', 'outros', 'acessorios'];

// Valida o filtro contra a lista branca
if (!in_array($filtro, $filtrosPermitidos, true)) {
  // Caso o parâmetro seja inválido, mantém o estado padrão
  $filtro = 'todos';
}

// Relaciona o id da categoria ao slug utilizado nos filtros front-end
// Usado para sincronizar filtros entre diferentes páginas da aplicação
$categoriaSlugMap = [
  1 => 'calcados',
  2 => 'calcas',
  3 => 'blusas',
  4 => 'camisas',
  5 => 'conjuntos',
  6 => 'acessorios',
];

// Carrega o header reutilizável (incluído em várias páginas da aplicação)
require_once __DIR__ . '/../includes/header.php';

// Gera o header com o filtro atual (mantém contexto de navegação)
$header = generateHeader($conn, $filtro);
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <title>Perfil do Usuário</title>
  <!-- Fontes externas e folhas de estilo globais -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="icon" href="<?php echo htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_path('css/account-edit.css'), ENT_QUOTES, 'UTF-8'); ?>">
  
</head>

<body>
  <!-- Botão para retornar rapidamente à página inicial -->
  <button class="back-button" onclick="window.location.href='<?= htmlspecialchars(url_path('index.php'), ENT_QUOTES, 'UTF-8'); ?>'">← Voltar</button>
  <!-- Menu lateral com atalhos da área logada -->
  <div class="sidebar">
    <div class="profile">
      <p>Olá!</p>
      <p><?php echo htmlspecialchars($_SESSION['user_nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <!-- Navegação entre as seções da conta do usuário -->
    <nav>
      <a href="<?= htmlspecialchars(url_path('public/pages/account/perfil.php'), ENT_QUOTES, 'UTF-8'); ?>" class="active">Dados pessoais</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8'); ?>">Endereços</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8'); ?>">Pedidos</a>
      <a href="<?= htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8'); ?>">Favoritos</a>
      <a href="<?= htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Sair</a>
    </nav>
  </div>

  <!-- Área que agrupa as informações exibidas ao usuário -->
  <div class="main-content">
    <h1>Dados pessoais</h1>
    <!-- Agrupamento dos cards informativos -->
    <div class="info-section">
      <!-- Card principal: Dados cadastrais do usuário -->
      <div class="card">
        <!-- Bloco com os dados básicos do perfil -->
        <!-- Nome completo do usuário -->
        <p>
          <strong>Nome</strong>
          <br>
          <span><?php echo sanitizeField($_SESSION['user_nome'] ?? ''); ?></span>
        </p>
        <!-- Email de cadastro/login -->
        <p>
          <strong>Email</strong>
          <br>
          <span><?php echo sanitizeField($_SESSION['email'] ?? ''); ?></span>
        </p>
        <!-- CPF formatado automaticamente para padrão brasileiro -->
        <p><strong>CPF</strong>
          <br>
          <span><?php echo formatCpf($_SESSION['user_cpf'] ?? ''); ?></span>
        </p>
        <!-- Data de nascimento convertida para formato DD/MM/AAAA -->
        <p><strong>Data de nascimento</strong>
          <br>
          <span><?php echo formatDateBr($_SESSION['user_data_nasc'] ?? ''); ?></span>
        </p>
        <!-- Telefone formatado: (XX) XXXXX-XXXX ou (XX) XXXX-XXXX -->
        <p><strong>Telefone</strong>
          <br>
          <span><?php echo formatPhone($_SESSION['user_tel'] ?? ''); ?></span>
        </p>
        <!-- Botão para acessar página de edição de dados -->
        <div class="edit-btn">
          <a href="editar.php">EDITAR</a>
        </div>
        <!-- Botão para deletar conta (confirmação via JavaScript) -->
        <div class="edit-btn">
          <a href="#" id="btn-delete-account">DELETAR</a>
        </div>
      </div>

      <!-- Card secundário: Preferências de newsletter -->
      <!-- Preferência para recebimento de e-mails promocionais -->
      <div class="card" style="max-width: 320px;">
        <h2>Newsletter</h2>
        <p>Deseja receber e-mails com promoções?</p>
        <!-- Checkbox para opt-in de emails promocionais -->
        <!-- Estado persistido via JavaScript (localStorage ou API) -->
        <div class="checkbox-container">
          <input type="checkbox" id="newsletter">
          <label for="newsletter">Quero receber e-mails com promoções.</label>
        </div>
      </div>
    </div>
  </div>
  <!-- Script responsável pela funcionalidade de deleção de conta -->
  <!-- Implementa modal de confirmação e requisição AJAX para API -->
  <script src="<?= htmlspecialchars(asset_path('js/delete.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>

</html>