<?php
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

$loginPage = url_path('public/pages/auth/login.php');
$enderecosPage = url_path('public/pages/account/enderecos.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
	header('Location: ' . $loginPage);
	exit();
}

function sanitizeField($value)
{
	return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$userEmail = $_SESSION['email'] ?? '';
$userName = $_SESSION['user_nome'] ?? 'Usuário';
$userId = null;
$errors = [];

if ($userEmail !== '') {
	$stmtUser = $conn->prepare('SELECT UsuId FROM Usuario WHERE UsuEmail = ? LIMIT 1');
	if ($stmtUser) {
		$stmtUser->bind_param('s', $userEmail);
		$stmtUser->execute();
		$stmtUser->bind_result($foundId);
		if ($stmtUser->fetch()) {
			$userId = (int)$foundId;
		} else {
			$errors[] = 'Usuário não encontrado.';
		}
		$stmtUser->close();
	} else {
		$errors[] = 'Erro ao buscar usuário.';
	}
} else {
	$errors[] = 'Sessão inválida. Faça login novamente.';
}

$states = [
	'AC' => 'Acre',
	'AL' => 'Alagoas',
	'AP' => 'Amapá',
	'AM' => 'Amazonas',
	'BA' => 'Bahia',
	'CE' => 'Ceará',
	'DF' => 'Distrito Federal',
	'ES' => 'Espírito Santo',
	'GO' => 'Goiás',
	'MA' => 'Maranhão',
	'MT' => 'Mato Grosso',
	'MS' => 'Mato Grosso do Sul',
	'MG' => 'Minas Gerais',
	'PA' => 'Pará',
	'PB' => 'Paraíba',
	'PR' => 'Paraná',
	'PE' => 'Pernambuco',
	'PI' => 'Piauí',
	'RJ' => 'Rio de Janeiro',
	'RN' => 'Rio Grande do Norte',
	'RS' => 'Rio Grande do Sul',
	'RO' => 'Rondônia',
	'RR' => 'Roraima',
	'SC' => 'Santa Catarina',
	'SP' => 'São Paulo',
	'SE' => 'Sergipe',
	'TO' => 'Tocantins'
];

$values = [
	'referencia' => '',
	'rua' => '',
	'numero' => '',
	'bairro' => '',
	'cidade' => '',
	'estado' => '',
	'cep' => '',
	'complemento' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null) {
	foreach ($values as $field => $default) {
		$values[$field] = trim($_POST[$field] ?? '');
	}

	if ($values['referencia'] === '') {
		$errors[] = 'Informe o nome do endereço (ex: Casa, Trabalho).';
	}
	if ($values['rua'] === '') {
		$errors[] = 'Informe a rua.';
	}

	$numero = filter_var($values['numero'], FILTER_VALIDATE_INT);
	if ($numero === false || $numero < 0) {
		$errors[] = 'Número inválido.';
	}

	$cepDigits = preg_replace('/\D+/', '', $values['cep']);
	if (strlen($cepDigits) !== 8) {
		$errors[] = 'CEP deve conter 8 dígitos.';
	} else {
		$values['cep'] = substr($cepDigits, 0, 5) . '-' . substr($cepDigits, 5);
	}

	if ($values['bairro'] === '') {
		$errors[] = 'Informe o bairro.';
	}
	if ($values['cidade'] === '') {
		$errors[] = 'Informe a cidade.';
	}
	if ($values['estado'] === '' || !array_key_exists(strtoupper($values['estado']), $states)) {
		$errors[] = 'Selecione um estado válido.';
	} else {
		$values['estado'] = strtoupper($values['estado']);
	}

	if (empty($errors)) {
		$stmt = $conn->prepare('INSERT INTO EnderecoEntrega (EndEntRef, EndEntRua, EndEntCep, EndEntNum, EndEntBairro, EndEntCid, EndEntEst, EndEntComple, UsuId) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
		if ($stmt) {
			$complemento = $values['complemento'];
			$stmt->bind_param(
				'sssissssi',
				$values['referencia'],
				$values['rua'],
				$values['cep'],
				$numero,
				$values['bairro'],
				$values['cidade'],
				$values['estado'],
				$complemento,
				$userId
			);

			if ($stmt->execute()) {
				$stmt->close();
				header('Location: ' . $enderecosPage . '?status=added');
				exit();
			}

			$errors[] = 'Erro ao salvar o endereço. Tente novamente.';
			$stmt->close();
		} else {
			$errors[] = 'Não foi possível preparar a inserção.';
		}
	}
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
	<meta charset="UTF-8" />
	<title>Adicionar endereço</title>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
	<link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
	<link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
	<style>
		.back-button {
			position: fixed;
			top: 20px;
			right: 20px;
			z-index: 1000;
			background-color: var(--highlight);
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
			opacity: 0.85;
		}
	</style>
</head>

<body>
	<button class="back-button" onclick="window.location.href='<?= htmlspecialchars(url_path('index.php'), ENT_QUOTES, 'UTF-8'); ?>'">← Voltar</button>
	<div class="sidebar">
		<div class="profile">
			<p>Olá!</p>
			<p><?php echo htmlspecialchars($_SESSION['user_nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
		</div>
		<nav>
			<a href="<?= htmlspecialchars(url_path('public/pages/account/perfil.php'), ENT_QUOTES, 'UTF-8'); ?>" class="active">Dados pessoais</a>
			<a href="<?= htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8'); ?>" class="active">Endereços</a>
			<a href="<?= htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8'); ?>">Pedidos</a>
			<a href="<?= htmlspecialchars(url_path('public/pages/account/cartoes.html'), ENT_QUOTES, 'UTF-8'); ?>">Cartões</a>
			<a href="<?= htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8'); ?>">Favoritos</a>
			<a href="<?= htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Sair</a>
		</nav>
	</div>

	<div class="main-content">
		<h1>Novo endereço</h1>

		<?php if (!empty($errors)) : ?>
			<div class="status-message status-error">
				<?php foreach ($errors as $error) : ?>
					<p><?php echo sanitizeField($error); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="card form-card">
			<form class="address-form" method="POST" action="addEnd.php" data-endereco-form>
				<div class="field">
					<label for="referencia">Nome do endereço</label>
					<input type="text" id="referencia" name="referencia" maxlength="50" value="<?php echo sanitizeField($values['referencia']); ?>" required>
				</div>

				<div class="field">
					<label for="rua">Rua</label>
					<input type="text" id="rua" name="rua" maxlength="150" value="<?php echo sanitizeField($values['rua']); ?>" readonly data-field="rua" required>
				</div>

				<div class="form-row">
					<div class="field">
						<label for="numero">Número</label>
						<input type="number" id="numero" name="numero" min="0" value="<?php echo sanitizeField($values['numero']); ?>" required>
					</div>
					<div class="field">
						<label for="cep">CEP</label>
						<input type="text" id="cep" name="cep" maxlength="9" placeholder="00000-000" value="<?php echo sanitizeField($values['cep']); ?>" required data-cep-input>
						<div class="cep-feedback" data-cep-feedback></div>
						<a class="cep-helper" href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank" rel="noopener noreferrer">Não sabe o CEP? Consulte nos Correios</a>
					</div>
				</div>

				<div class="form-row">
					<div class="field">
						<label for="bairro">Bairro</label>
						<input type="text" id="bairro" name="bairro" maxlength="100" value="<?php echo sanitizeField($values['bairro']); ?>" readonly data-field="bairro" required>
					</div>
					<div class="field">
						<label for="cidade">Cidade</label>
						<input type="text" id="cidade" name="cidade" maxlength="150" value="<?php echo sanitizeField($values['cidade']); ?>" readonly data-field="cidade" required>
					</div>
					<div class="field">
						<label for="estado">Estado</label>
						<input type="text" id="estado" name="estado" maxlength="2" value="<?php echo sanitizeField($values['estado']); ?>" readonly data-field="estado" required>
					</div>
				</div>

				<div class="field">
					<label for="complemento">Complemento (opcional)</label>
					<input type="text" id="complemento" name="complemento" maxlength="100" value="<?php echo sanitizeField($values['complemento']); ?>">
				</div>

				<div class="submit-row">
					<a class="secondary-btn" href="enderecos.php">Cancelar</a>
					<button class="primary-btn" type="submit">Salvar</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		const savedTheme = localStorage.getItem('theme');
		if (savedTheme === 'dark') {
			document.body.classList.add('dark');
		}
	</script>
	<script src="<?= htmlspecialchars(asset_path('js/endereco-form.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script>
		window.addEventListener('DOMContentLoaded', () => {
			const savedTheme = localStorage.getItem('theme');
			if (savedTheme === 'light') {
				document.body.classList.add('light');
			}
		});
	</script>

</body>

</html>