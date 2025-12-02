<?php
/**
 * Arquivo: logout.php
 * Propósito: Endpoint para encerrar sessão do usuário
 * 
 * Este script realiza logout completo destruindo a sessão PHP e
 * removendo cookies de sessão. Suporta dois modos de operação:
 * 1. Requisições AJAX/API: retorna JSON com status
 * 2. Requisições browser: redireciona para página de login
 * 
 * Endpoints:
 * - GET/POST /public/api/auth/logout.php (JSON)
 * - GET /public/api/auth/logout.php?redirect=/caminho (redirecionamento customizado)
 * 
 * Uso:
 * - AJAX: fetch('/public/api/auth/logout.php', {headers: {'Accept': 'application/json'}})
 * - Link: <a href="/public/api/auth/logout.php">Sair</a>
 */

// ===== CARREGAMENTO DE DEPENDÊNCIAS =====
/**
 * Carrega bootstrap que inicializa:
 * - Conexão com banco de dados
 * - Funções helpers (url_path, site_path)
 * - Autoloader do Composer
 */
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// ===== INICIALIZAÇÃO DE SESSÃO =====
/**
 * Inicia ou retoma sessão PHP existente.
 * Necessário para acessar e limpar dados de $_SESSION.
 */
session_start();

// ===== VERIFICAÇÃO DE SESSÃO ATIVA =====
/**
 * Registra se havia sessão ativa antes da destruição.
 * Usado para personalizar mensagem de feedback ao usuário.
 * 
 * @var bool $hadSession True se havia dados de sessão, false se sessão já estava vazia
 */
$hadSession = !empty($_SESSION);

// ===== LIMPEZA DE DADOS DA SESSÃO =====
/**
 * Esvazia o array $_SESSION removendo todos os dados do usuário:
 * - firebase_uid
 * - logged_in
 * - email
 * - user_nome, user_cpf, etc
 * 
 * Importante: isso não destrói a sessão, apenas remove os dados.
 */
$_SESSION = [];

// ===== DESTRUIÇÃO COMPLETA DA SESSÃO =====
/**
 * Remove cookie de sessão e destrói a sessão no servidor.
 * Garante logout completo mesmo se usuário voltar com botão "voltar" do browser.
 */
if (PHP_SESSION_ACTIVE === session_status()) {
	// Verifica se a aplicação usa cookies para armazenar ID de sessão
	if (ini_get('session.use_cookies')) {
		// Obtém parâmetros do cookie de sessão atual
		$params = session_get_cookie_params();
		
		/**
		 * Remove cookie de sessão do navegador setando expiração no passado.
		 * 
		 * Parâmetros:
		 * - session_name(): Nome do cookie (geralmente PHPSESSID)
		 * - '': Valor vazio
		 * - time() - 42000: Timestamp no passado (força expiração imediata)
		 * - $params: Path, domain, secure, httponly do cookie original
		 */
		setcookie(
			session_name(),
			'',
			time() - 42000,
			$params['path'],
			$params['domain'],
			$params['secure'],
			$params['httponly']
		);
	}

	/**
	 * Destrói dados da sessão no servidor.
	 * Remove arquivo de sessão em /tmp ou storage de sessão configurado.
	 */
	session_destroy();
}

// ===== DETECÇÃO DO TIPO DE RESPOSTA =====
/**
 * Determina se cliente espera resposta JSON (API) ou HTML (browser).
 * 
 * @var bool $wantsJson True para requisições AJAX/API, false para navegação normal
 */
$wantsJson = false;

// Método 1: Verifica header Accept contendo application/json
if (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
	$wantsJson = true;
}

// Método 2: Verifica header X-Requested-With (padrão jQuery/Axios)
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
	$wantsJson = true;
}

// ===== RESPOSTA PARA REQUISIÇÕES API (JSON) =====
/**
 * Se cliente espera JSON, retorna resposta estruturada e encerra.
 */
if ($wantsJson) {
	// Define Content-Type correto para JSON
	header('Content-Type: application/json');
	
	// Retorna objeto JSON com status e mensagem personalizada
	echo json_encode([
		'success' => true,
		'message' => $hadSession ? 'Logout realizado com sucesso.' : 'Nenhuma sessão ativa encontrada.',
	]);
	exit; // Encerra script aqui para evitar redirecionamento
}

// ===== RESPOSTA PARA NAVEGAÇÃO BROWSER (REDIRECT) =====
/**
 * Para requisições normais do navegador, redireciona para página de login.
 */

// Define URL padrão de redirecionamento (página de login)
$redirectTarget = url_path('public/pages/auth/cadastro_login.html');

// ===== REDIRECIONAMENTO CUSTOMIZADO =====
/**
 * Permite especificar URL de redirecionamento via query string.
 * 
 * Exemplo: logout.php?redirect=/public/pages/shop/index.php
 * 
 * Sanitização aplicada para prevenir:
 * - Open redirect attacks
 * - XSS via URL
 * - Redirecionamento para domínios externos
 */
if (!empty($_GET['redirect'])) {
	// Sanitiza URL removendo caracteres perigosos
	$candidate = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
	
	if ($candidate !== false && $candidate !== '') {
		// Extrai apenas o path, ignorando protocolo e domínio (segurança)
		$pathOnly = parse_url($candidate, PHP_URL_PATH);
		
		if ($pathOnly) {
			// Converte path para URL completa considerando subdiretórios
			$redirectTarget = site_path(ltrim($pathOnly, '/'));
		}
	}
}

// ===== EXECUÇÃO DO REDIRECIONAMENTO =====
/**
 * Envia header Location para redirecionar navegador.
 * HTTP 302 (redirect temporário) é usado por padrão.
 */
header('Location: ' . $redirectTarget);
exit; // Encerra script após redirecionamento
