<?php
/**
 * Página: Edição de Dados Pessoais
 * Propósito: Permite ao usuário editar suas informações cadastrais.
 * 
 * Funcionalidades:
 * - Formulário pré-preenchido com dados da sessão
 * - Formatação automática de CPF e telefone (máscaras)
 * - Validações server-side (nome, email, CPF, telefone, data)
 * - Suporte a data de nascimento com Flatpickr (calendário)
 * - Atualização segura no banco via prepared statement
 * - Atualização da sessão após salvamento
 * - Mensagens de sucesso e erro
 * 
 * Validações:
 * - Nome completo obrigatório
 * - Email válido (FILTER_VALIDATE_EMAIL)
 * - CPF com 11 dígitos
 * - Telefone com 10 ou 11 dígitos (DDD + número)
 * - Data de nascimento válida (formato Y-m-d ou d/m/Y)
 * 
 * Segurança:
 * - Requer autenticação (sessão ativa)
 * - Prepared statements contra SQL injection
 * - Sanitização de todas as saídas HTML
 */

// Inicia sessão para acesso aos dados do usuário autenticado
session_start();
// Carrega configurações, helpers e conexão com banco de dados
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// URL da página de login para redirecionamento
$loginPage = url_path('public/pages/auth/login.php');

// Valida autenticação do usuário
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
	header('Location: ' . $loginPage);
	exit();
}

/**
 * Formata string de CPF no padrão brasileiro.
 * 
 * Remove caracteres não numéricos e aplica máscara 000.000.000-00
 * se tiver exatamente 11 dígitos. Caso contrário, retorna valor original.
 * 
 * @param string $value CPF em qualquer formato (com ou sem pontuação).
 * @return string CPF formatado ou valor original se inválido.
 */
function formatCpf(string $value): string
{
	// Remove tudo que não for dígito
	$digits = preg_replace('/\D+/', '', $value);
	if (strlen($digits) === 11) {
		// Aplica máscara 000.000.000-00
		return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits);
	}
	return $value;
}

/**
 * Formata string de telefone no padrão brasileiro.
 * 
 * Suporta dois formatos:
 * - 11 dígitos (celular): (00) 00000-0000
 * - 10 dígitos (fixo): (00) 0000-0000
 * 
 * @param string $value Telefone em qualquer formato (com ou sem pontuação).
 * @return string Telefone formatado ou valor original se inválido.
 */
function formatTelefone(string $value): string
{
	// Remove tudo que não for dígito
	$digits = preg_replace('/\D+/', '', $value);
	if (strlen($digits) === 11) {
		// Celular: (00) 00000-0000
		return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits);
	}
	if (strlen($digits) === 10) {
		// Fixo: (00) 0000-0000
		return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits);
	}
	return $value;
}

/**
 * Converte data do formato ISO (Y-m-d) para formato brasileiro (d/m/Y).
 * 
 * Usado para exibir datas do banco de dados em formato legível.
 * 
 * @param string|null $value Data no formato Y-m-d ou null.
 * @return string Data formatada em d/m/Y ou string vazia se inválida.
 */
function formatDateDisplay(?string $value): string
{
	if (!$value) {
		return '';
	}
	// Tenta criar DateTime do formato ISO
	$date = DateTime::createFromFormat('Y-m-d', $value);
	return $date ? $date->format('d/m/Y') : $value;
}

// Arrays para armazenar mensagens de erro e sucesso
$errors = [];
$successMessage = '';

// Carrega dados atuais do usuário da sessão
$nome = $_SESSION['user_nome'] ?? '';
$email = $_SESSION['email'] ?? '';
$cpf = $_SESSION['user_cpf'] ?? '';
$telefone = $_SESSION['user_tel'] ?? '';
$dataNascDb = $_SESSION['user_data_nasc'] ?? ''; // Formato Y-m-d do banco
$dataNascDisplay = formatDateDisplay($dataNascDb); // Converte para d/m/Y
$dataNascFieldValue = $dataNascDisplay; // Valor exibido no campo

// Armazena email atual para identificar registro no UPDATE
$currentEmail = $email;

// Processamento do formulário quando submetido via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Captura e limpa valores enviados
	$nome = trim($_POST['nome'] ?? '');
	$email = trim($_POST['email'] ?? '');
	// Remove formatação de CPF e telefone (mantém apenas dígitos)
	$cpf = preg_replace('/\D+/', '', $_POST['cpf'] ?? '');
	$telefone = preg_replace('/\D+/', '', $_POST['telefone'] ?? '');
	$dataNascInput = trim($_POST['data_nasc'] ?? '');
	$dataNascFieldValue = $dataNascInput; // Preserva valor para reexibição em caso de erro

	// Validação: nome completo é obrigatório
	if ($nome === '') {
		$errors[] = 'Informe seu nome completo.';
	}

	// Validação: email deve ter formato válido
	if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
		$errors[] = 'Informe um e-mail válido.';
	}

	// Validação: CPF deve ter exatamente 11 dígitos
	if ($cpf === '' || strlen($cpf) !== 11) {
		$errors[] = 'O CPF deve conter 11 dígitos.';
	}

	// Validação: telefone deve ter 10 (fixo) ou 11 (celular) dígitos
	if ($telefone === '' || (strlen($telefone) < 10 || strlen($telefone) > 11)) {
		$errors[] = 'Informe um telefone válido com DDD.';
	}

	// Validação: data de nascimento deve ser válida
	// Aceita dois formatos: Y-m-d (ISO) ou d/m/Y (brasileiro)
	$dataValida = null;
	if ($dataNascInput !== '') {
		// Primeira tentativa: formato ISO (Y-m-d)
		$dataValida = DateTime::createFromFormat('Y-m-d', $dataNascInput);
		$isIso = $dataValida && $dataValida->format('Y-m-d') === $dataNascInput;
		if (!$isIso) {
			// Segunda tentativa: formato brasileiro (d/m/Y)
			$dataValida = DateTime::createFromFormat('d/m/Y', $dataNascInput);
			$isIso = $dataValida && $dataValida->format('d/m/Y') === $dataNascInput;
		}
	}

	if (!$dataValida) {
		$errors[] = 'Informe uma data de nascimento válida.';
		$dataNascFieldValue = '';
		$dataNascDisplay = '';
	} else {
		// Converte para formato do banco (Y-m-d)
		$dataNascDb = $dataValida->format('Y-m-d');
		// Formata para exibição (d/m/Y)
		$dataNascDisplay = formatDateDisplay($dataNascDb);
		$dataNascFieldValue = $dataNascDisplay;
	}

	// Se não há erros de validação, atualiza no banco
	if (!$errors) {
		// Prepara UPDATE com prepared statement para segurança
		// WHERE usa email atual (antes da mudança) para identificar registro
		$updateSql = "UPDATE usuario SET UsuNome = ?, UsuEmail = ?, UsuCpf = ?, UsuTel = ?, UsuDataNasc = ? WHERE UsuEmail = ?";
		$stmt = $conn->prepare($updateSql);

		if ($stmt) {
			// Vincula parâmetros: 5 campos novos + 1 condição WHERE
			// Tipos: todos strings (s)
			$stmt->bind_param('ssssss', $nome, $email, $cpf, $telefone, $dataNascDb, $currentEmail);

			if ($stmt->execute()) {
				// Atualização bem-sucedida: atualiza dados na sessão
				$_SESSION['user_nome'] = $nome;
				$_SESSION['email'] = $email;
				$_SESSION['user_cpf'] = $cpf;
				$_SESSION['user_tel'] = $telefone;
				$_SESSION['user_data_nasc'] = $dataNascDb;
				$currentEmail = $email; // Atualiza referência para próximas alterações
				$dataNascDisplay = formatDateDisplay($dataNascDb);
				$dataNascFieldValue = $dataNascDisplay;
				$successMessage = 'Dados atualizados com sucesso!';
			} else {
				// Falha na execução do UPDATE
				$errors[] = 'Não foi possível atualizar seus dados. Tente novamente.';
			}

			$stmt->close();
		} else {
			// Falha na preparação da query
			$errors[] = 'Erro ao preparar a atualização dos dados.';
		}
	}
}

// Formata CPF e telefone para exibição nos campos (com máscara)
$cpfDisplay = formatCpf($cpf);
$telefoneDisplay = formatTelefone($telefone);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
	<meta charset="UTF-8" />
	<title>Editar dados pessoais</title>
	<!-- Fonte Google Inter para consistência visual -->
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
	<!-- Ícone da página exibido na aba do navegador -->
	<link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
	<!-- Estilos do header -->
	<link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/header.css'), ENT_QUOTES, 'UTF-8'); ?>">
	<!-- Estilos base do perfil: sidebar, layout, cards -->
	<link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
	<!-- Estilos específicos para formulários de edição -->
	<link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/account-edit.css'), ENT_QUOTES, 'UTF-8'); ?>">
	<!-- Estilos do Flatpickr (calendário de data) -->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
				   <!-- Cartões removido: pagamento via Mercado Pago -->
			<a href="<?= htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8'); ?>">Favoritos</a>
			<a href="<?= htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Sair</a>
		</nav>
	</div>

	<!-- Área principal com formulário de edição -->
	<div class="main-content">
		<h1>Editar dados pessoais</h1>
		<div class="info-section">
			<div class="card">

				<?php if ($successMessage) : ?>
					<!-- Mensagem de sucesso após atualização -->
					<div class="alert alert-success"><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>

				<?php if ($errors) : ?>
					<!-- Mensagens de erro de validação -->
					<div class="alert alert-error">
						<strong>Corrija os campos abaixo:</strong>
						<ul>
							<?php foreach ($errors as $error) : ?>
								<li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<!-- Formulário de edição (novalidate desabilita validação HTML5 para usar server-side) -->
				<form method="POST" novalidate>
					<!-- Grade de campos do formulário -->
					<div class="form-grid">
						<!-- Campo: Nome completo -->
						<div class="form-group">
							<label for="nome">Nome completo</label>
							<input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>
						<!-- Campo: Email -->
						<div class="form-group">
							<label for="email">E-mail</label>
							<input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
						</div>
						<!-- Campo: CPF (com máscara aplicada via JavaScript) -->
						<div class="form-group">
							<label for="cpf">CPF</label>
							<input type="text" id="cpf" name="cpf" maxlength="14" inputmode="numeric" value="<?php echo htmlspecialchars($cpfDisplay, ENT_QUOTES, 'UTF-8'); ?>" placeholder="000.000.000-00" required>
						</div>
						<!-- Campo: Telefone (com máscara aplicada via JavaScript) -->
						<div class="form-group">
							<label for="telefone">Telefone</label>
							<input type="text" id="telefone" name="telefone" maxlength="15" inputmode="tel" value="<?php echo htmlspecialchars($telefoneDisplay, ENT_QUOTES, 'UTF-8'); ?>" placeholder="(00) 00000-0000" required>
						</div>
						<!-- Campo: Data de nascimento (com calendário Flatpickr) -->
						<div class="form-group">
							<label for="data_nasc">Data de nascimento</label>
							<input type="text" id="data_nasc" name="data_nasc" maxlength="10" inputmode="numeric" placeholder="dd/mm/aaaa" value="<?php echo htmlspecialchars($dataNascFieldValue, ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" required>
						</div>
					</div>

					<!-- Botões de ação do formulário -->
					<div class="form-actions">
						<!-- Botão secundário: cancela edição e volta para perfil -->
						<a class="btn-secondary" href="perfil.php">Cancelar</a>
						<!-- Botão primário: submete formulário para atualização -->
						<button type="submit" class="btn-primary">Salvar alterações</button>
					</div>
				</form>
			</div>
		</div>
	</div>
	<!-- jQuery necessário para plugin de máscaras -->
	<script src="https://code.jquery.com/jquery-3.0.0.min.js"></script>
	<!-- Plugin jQuery Mask para máscaras de CPF e telefone -->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js"></script>
	<!-- Script customizado que aplica as máscaras nos campos -->
	<script src="<?= htmlspecialchars(asset_path('js/form-mask.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<!-- Flatpickr: biblioteca de calendário para seleção de data -->
	<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
	<!-- Localização em português do Flatpickr -->
	<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/pt.js"></script>
	<!-- Inicializa Flatpickr no campo de data de nascimento -->
	<script>
		flatpickr('#data_nasc', {
			dateFormat: 'd/m/Y', // Formato brasileiro
			allowInput: true, // Permite digitação manual
			locale: flatpickr.l10ns.pt, // Usa tradução portuguesa
			defaultDate: <?php echo $dataNascDisplay ? json_encode($dataNascDisplay) : 'null'; ?>, // Data atual do usuário
		});
	</script>

</body>

</html>