<?php
// php/logOut.php

require_once dirname(__DIR__, 2) . '/bootstrap.php';

session_start();

$hadSession = !empty($_SESSION);

$_SESSION = [];

if (PHP_SESSION_ACTIVE === session_status()) {
	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
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

	session_destroy();
}

$wantsJson = false;

if (!empty($_SERVER['HTTP_ACCEPT']) && stripos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
	$wantsJson = true;
}

if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
	$wantsJson = true;
}

if ($wantsJson) {
	header('Content-Type: application/json');
	echo json_encode([
		'success' => true,
		'message' => $hadSession ? 'Logout realizado com sucesso.' : 'Nenhuma sessão ativa encontrada.',
	]);
	exit;
}

$redirectTarget = url_path('public/pages/auth/cadastro_login.html');

if (!empty($_GET['redirect'])) {
	$candidate = filter_var($_GET['redirect'], FILTER_SANITIZE_URL);
	if ($candidate !== false && $candidate !== '') {
		$pathOnly = parse_url($candidate, PHP_URL_PATH);
		if ($pathOnly) {
			$redirectTarget = site_path(ltrim($pathOnly, '/'));
		}
	}
}

header('Location: ' . $redirectTarget);
exit;
