<?php
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

$loginPage = url_path('public/pages/auth/login.php');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
	header('Location: ' . $loginPage);
	exit();
}

function formatCpf(string $value): string
{
	$digits = preg_replace('/\D+/', '', $value);
	if (strlen($digits) === 11) {
		return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
	}
	return $value;
}

function formatTelefone(string $value): string
{
	$digits = preg_replace('/\D+/', '', $value);
	if (strlen($digits) === 11) {
		return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits);
	}
	if (strlen($digits) === 10) {
		return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits);
	}
	return $value;
}

function formatDateDisplay(?string $value): string
{
	if (!$value) {
		return '';
	}
	$date = DateTime::createFromFormat('Y-m-d', $value);
	return $date ? $date->format('d/m/Y') : $value;
}

$errors = [];
$successMessage = '';

$nome = $_SESSION['user_nome'] ?? '';
$email = $_SESSION['email'] ?? '';
$cpf = $_SESSION['user_cpf'] ?? '';
$telefone = $_SESSION['user_tel'] ?? '';
$dataNascDb = $_SESSION['user_data_nasc'] ?? '';
$dataNascDisplay = formatDateDisplay($dataNascDb);
$dataNascFieldValue = $dataNascDisplay;

$currentEmail = $email;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$nome = trim($_POST['nome'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$cpf = preg_replace('/\D+/', '', $_POST['cpf'] ?? '');
	$telefone = preg_replace('/\D+/', '', $_POST['telefone'] ?? '');
	$dataNascInput = trim($_POST['data_nasc'] ?? '');
	$dataNascFieldValue = $dataNascInput;

	if ($nome === '') {
		$errors[] = 'Informe seu nome completo.';
	}

	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$errors[] = 'Informe um e-mail válido.';
	}

	if ($cpf === '' || strlen($cpf) !== 11) {
		$errors[] = 'O CPF deve conter 11 dígitos.';
	}

	if ($telefone === '' || (strlen($telefone) < 10 || strlen($telefone) > 11)) {
		$errors[] = 'Informe um telefone válido com DDD.';
	}

	$dataValida = null;
	if ($dataNascInput !== '') {
		$dataValida = DateTime::createFromFormat('Y-m-d', $dataNascInput);
		$isIso = $dataValida && $dataValida->format('Y-m-d') === $dataNascInput;
		if (!$isIso) {
			$dataValida = DateTime::createFromFormat('d/m/Y', $dataNascInput);
			$isIso = $dataValida && $dataValida->format('d/m/Y') === $dataNascInput;
		}
	}

	if (!$dataValida) {
		$errors[] = 'Informe uma data de nascimento válida.';
		$dataNascFieldValue = '';
		$dataNascDisplay = '';
	} else {
		$dataNascDb = $dataValida->format('Y-m-d');
		$dataNascDisplay = formatDateDisplay($dataNascDb);
		$dataNascFieldValue = $dataNascDisplay;
	}

	if (!$errors) {
		$updateSql = "UPDATE usuario SET UsuNome = ?, UsuEmail = ?, UsuCpf = ?, UsuTel = ?, UsuDataNasc = ? WHERE UsuEmail = ?";
		$stmt = $conn->prepare($updateSql);

		if ($stmt) {
			$stmt->bind_param('ssssss', $nome, $email, $cpf, $telefone, $dataNascDb, $currentEmail);

			if ($stmt->execute()) {
				$_SESSION['user_nome'] = $nome;
				$_SESSION['email'] = $email;
				$_SESSION['user_cpf'] = $cpf;
				$_SESSION['user_tel'] = $telefone;
				$_SESSION['user_data_nasc'] = $dataNascDb;
				$currentEmail = $email;
				$dataNascDisplay = formatDateDisplay($dataNascDb);
				$dataNascFieldValue = $dataNascDisplay;
				$successMessage = 'Dados atualizados com sucesso!';
			} else {
				$errors[] = 'Não foi possível atualizar seus dados. Tente novamente.';
			}

			$stmt->close();
		} else {
			$errors[] = 'Erro ao preparar a atualização dos dados.';
		}
	}
}

$cpfDisplay = formatCpf($cpf);
$telefoneDisplay = formatTelefone($telefone);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
	<meta charset="UTF-8" />
	<title>Editar dados pessoais</title>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
	<link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
	<link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/header.css'), ENT_QUOTES, 'UTF-8'); ?>">
	<link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
	<style>
		.card form {
			display: flex;
			flex-direction: column;
			gap: 16px;
		}

		.form-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
			gap: 16px;
		}

		.form-group label {
			display: block;
			font-size: 0.85rem;
			font-weight: 600;
			margin-bottom: 6px;
			color: var(--highlight);
		}

		.form-group input {
			width: 100%;
			padding: 10px 12px;
			border-radius: 6px;
			border: 1px solid var(--card-border);
			background-color: #111;
			color: var(--text-color);
			font-size: 0.95rem;
		}

		.form-actions {
			display: flex;
			gap: 12px;
			flex-wrap: wrap;
			justify-content: flex-end;
		}

		.btn-primary,
		.btn-secondary {
			padding: 10px 18px;
			border-radius: 6px;
			font-weight: 600;
			cursor: pointer;
			border: none;
			text-decoration: none;
			text-align: center;
		}

		.btn-primary {
			background-color: var(--highlight);
			color: #000;
		}

		.btn-secondary {
			background-color: transparent;
			color: var(--highlight);
			border: 1px solid var(--highlight);
		}

		.alert {
			border-radius: 6px;
			padding: 12px 16px;
			margin-bottom: 16px;
			font-size: 0.95rem;
		}

		.alert-success {
			background-color: rgba(46, 204, 113, 0.15);
			border: 1px solid #2ecc71;
			color: #2ecc71;
		}

		.alert-error {
			background-color: rgba(231, 76, 60, 0.15);
			border: 1px solid #e74c3c;
			color: #e74c3c;
		}

		.alert-error ul {
			margin: 8px 0 0;
			padding-left: 18px;
		}
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
			<a href="<?= htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8'); ?>">Endereços</a>
			<a href="<?= htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8'); ?>">Pedidos</a>
			<a href="<?= htmlspecialchars(url_path('public/pages/account/cartoes.html'), ENT_QUOTES, 'UTF-8'); ?>">Cartões</a>
			<a href="<?= htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8'); ?>">Favoritos</a>
			<a href="<?= htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Sair</a>
		</nav>
	</div>

	<div class="main-content">
		<h1>Editar dados pessoais</h1>
		<div class="info-section">
			<div class="card">

				<?php if ($successMessage) : ?>
					<div class="alert alert-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>

				<?php if ($errors) : ?>
					<div class="alert alert-error">
						<strong>Corrija os campos abaixo:</strong>
						<ul>
							<?php foreach ($errors as $error) : ?>
								<li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<form method="POST" novalidate>
					<div class="form-grid">
						<div class="form-group">
							<label for="nome">Nome completo</label>
							<input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>
						<div class="form-group">
							<label for="email">E-mail</label>
							<input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>
						<div class="form-group">
							<label for="cpf">CPF</label>
							<input type="text" id="cpf" name="cpf" maxlength="14" inputmode="numeric" value="<?php echo htmlspecialchars($cpfDisplay, ENT_QUOTES, 'UTF-8'); ?>" placeholder="000.000.000-00" required>
						</div>
						<div class="form-group">
							<label for="telefone">Telefone</label>
							<input type="text" id="telefone" name="telefone" maxlength="15" inputmode="tel" value="<?php echo htmlspecialchars($telefoneDisplay, ENT_QUOTES, 'UTF-8'); ?>" placeholder="(00) 00000-0000" required>
						</div>
						<div class="form-group">
							<label for="data_nasc">Data de nascimento</label>
							<input type="text" id="data_nasc" name="data_nasc" maxlength="10" inputmode="numeric" placeholder="dd/mm/aaaa" value="<?php echo htmlspecialchars($dataNascFieldValue, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" required>
						</div>
					</div>

					<div class="form-actions">
						<a class="btn-secondary" href="perfil.php">Cancelar</a>
						<button type="submit" class="btn-primary">Salvar alterações</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<script src="https://code.jquery.com/jquery-3.0.0.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js"></script>
	<script src="<?= htmlspecialchars(asset_path('js/form-mask.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
	<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
	<script>
		flatpickr('#data_nasc', {
			dateFormat: 'd/m/Y',
			allowInput: true,
			locale: flatpickr.l10ns.pt,
			defaultDate: <?php echo $dataNascDisplay ? json_encode($dataNascDisplay) : 'null'; ?>,
		});
	</script>

</body>

</html>