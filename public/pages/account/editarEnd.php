<?php
/**
 * Página: Edição de Endereço
 * Propósito: Permite editar um endereço de entrega existente.
 * 
 * Funcionalidades:
 * - Carrega dados do endereço especificado por ID na URL
 * - Valida que o endereço pertence ao usuário logado (segurança)
 * - Formulário pré-preenchido com dados atuais
 * - Validações server-side: campos obrigatórios, formato CEP, estado válido
 * - Integração com API ViaCEP via JavaScript (consulta automática)
 * - Atualiza registro na tabela EnderecoEntrega
 * - Redireciona para lista de endereços com mensagem de sucesso
 * 
 * Segurança:
 * - Requer autenticação (sessão ativa)
 * - Verifica propriedade do endereço (UsuId na consulta)
 * - Prepared statements em todas as queries
 * - Sanitização de todos os campos exibidos
 */

// Inicia sessão para acesso aos dados do usuário autenticado
session_start();
// Carrega configurações, helpers e conexão com banco de dados
require_once dirname(__DIR__, 2) . '/bootstrap.php';

// URLs para redirecionamentos
$loginPage = url_path('public/pages/auth/login.php');
$enderecosPage = url_path('public/pages/account/enderecos.php');

// Valida autenticação do usuário
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
	header('Location: ' . $loginPage);
	exit();
}

/**
 * Sanitiza campo para exibição segura em HTML.
 * 
 * Converte caracteres especiais em entidades HTML para prevenir
 * ataques XSS (Cross-Site Scripting).
 * 
 * @param mixed $value Valor a ser sanitizado (null será convertido para string vazia).
 * @return string Valor sanitizado e seguro para exibição.
 */
function sanitizeField($value)
{
	return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Inicializa variáveis com dados da sessão
$userEmail = $_SESSION['email'] ?? '';
$userName = $_SESSION['user_nome'] ?? 'Usuário';
$userId = null; // Será preenchido com consulta ao banco
$errors = []; // Array para acumular mensagens de erro

// Busca o ID do usuário no banco através do email armazenado na sessão
if ($userEmail !== '') {
	// Prepara consulta segura para evitar SQL injection
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
	// Email não está na sessão (situação anômala)
	$errors[] = 'Sessão inválida. Faça login novamente.';
}

// Mapa de estados brasileiros (sigla => nome completo)
// Usado para validar se o estado fornecido é válido
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

// Captura ID do endereço (vem da URL em GET ou do formulário em POST)
$addressId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['endereco_id'] ?? 0);
// Valida se o ID é válido (obrigatório para prosseguir)
if ($addressId <= 0) {
	// ID inválido: redireciona para lista de endereços com erro
	header('Location: ' . $enderecosPage . '?status=error');
	exit();
}

// Array com valores padrão para todos os campos do formulário
// Será preenchido com dados do banco ou mantido em caso de erro de validação
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

// Flag para verificar se o endereço existe e pertence ao usuário
$addressExists = false;
if ($userId !== null) {
	// Busca o endereço no banco
	// WHERE com dupla condição: EndEntId e UsuId (segurança - só acessa próprios endereços)
	$stmt = $conn->prepare('SELECT EndEntRef, EndEntRua, EndEntCep, EndEntNum, EndEntBairro, EndEntCid, EndEntEst, EndEntComple FROM EnderecoEntrega WHERE EndEntId = ? AND UsuId = ? LIMIT 1');
	if ($stmt) {
		$stmt->bind_param('ii', $addressId, $userId);
		$stmt->execute();
		$result = $stmt->get_result();
		if ($row = $result->fetch_assoc()) {
			// Endereço encontrado: preenche array com dados atuais
			$addressExists = true;
			$values = [
				'referencia' => $row['EndEntRef'],
				'rua' => $row['EndEntRua'],
				'numero' => (string)$row['EndEntNum'],
				'bairro' => $row['EndEntBairro'],
				'cidade' => $row['EndEntCid'],
				'estado' => $row['EndEntEst'],
				'cep' => $row['EndEntCep'],
				'complemento' => $row['EndEntComple'] ?? ''
			];
		}
		$stmt->close();
	}
}

// Se o endereço não existe ou não pertence ao usuário, redireciona com erro
if (!$addressExists) {
	header('Location: ' . $enderecosPage . '?status=error');
	exit();
}

// Processamento do formulário quando submetido via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null) {
	// Atualiza array $values com dados enviados pelo formulário
	// trim() remove espaços em branco extras
	foreach ($values as $field => $default) {
		$values[$field] = trim($_POST[$field] ?? $default);
	}

	// Validação: nome do endereço é obrigatório
	if ($values['referencia'] === '') {
		$errors[] = 'Informe o nome do endereço (ex: Casa, Trabalho).';
	}
	// Validação: rua é obrigatória
	if ($values['rua'] === '') {
		$errors[] = 'Informe a rua.';
	}

	// Validação: número deve ser inteiro não negativo
	$numero = filter_var($values['numero'], FILTER_VALIDATE_INT);
	if ($numero === false || $numero < 0) {
		$errors[] = 'Número inválido.';
	}

	// Validação e formatação do CEP
	// Remove caracteres não numéricos
	$cepDigits = preg_replace('/\D+/', '', $values['cep']);
	if (strlen($cepDigits) !== 8) {
		$errors[] = 'CEP deve conter 8 dígitos.';
	} else {
		// Formata para padrão 00000-000
		$values['cep'] = substr($cepDigits, 0, 5) . '-' . substr($cepDigits, 5);
	}

	// Validação: bairro é obrigatório
	if ($values['bairro'] === '') {
		$errors[] = 'Informe o bairro.';
	}
	// Validação: cidade é obrigatória
	if ($values['cidade'] === '') {
		$errors[] = 'Informe a cidade.';
	}
	// Validação: estado deve ser uma sigla válida do array $states
	if ($values['estado'] === '' || !array_key_exists(strtoupper($values['estado']), $states)) {
		$errors[] = 'Selecione um estado válido.';
	} else {
		// Normaliza para maiúsculas
		$values['estado'] = strtoupper($values['estado']);
	}

	// Se não há erros de validação, atualiza o endereço no banco
	if (empty($errors)) {
		// Prepara UPDATE com prepared statement para segurança
		// WHERE com dupla condição: EndEntId e UsuId (segurança adicional)
		$stmtUpdate = $conn->prepare('UPDATE EnderecoEntrega SET EndEntRef = ?, EndEntRua = ?, EndEntCep = ?, EndEntNum = ?, EndEntBairro = ?, EndEntCid = ?, EndEntEst = ?, EndEntComple = ? WHERE EndEntId = ? AND UsuId = ?');
		if ($stmtUpdate) {
			// Vincula parâmetros: 8 campos do endereço + 2 condições WHERE
			// Tipos: s=string, i=integer
			$stmtUpdate->bind_param(
				'sssissssii',
				$values['referencia'],
				$values['rua'],
				$values['cep'],
				$numero,
				$values['bairro'],
				$values['cidade'],
				$values['estado'],
				$values['complemento'],
				$addressId,
				$userId
			);

			if ($stmtUpdate->execute()) {
				// Atualização bem-sucedida: redireciona com mensagem de sucesso
				$stmtUpdate->close();
				header('Location: ' . $enderecosPage . '?status=updated');
				exit();
			}

			// Falha na execução do UPDATE
			$errors[] = 'Não foi possível atualizar o endereço. Tente novamente.';
			$stmtUpdate->close();
		} else {
			// Falha na preparação da query
			$errors[] = 'Erro ao preparar a atualização.';
		}
	}
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
	<meta charset="UTF-8" />
	<title>Editar endereço</title>
	<!-- Fonte Google Inter para consistência visual -->
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
	<!-- Ícone da página exibido na aba do navegador -->
	<link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
	<!-- Estilos base do perfil: sidebar, layout, cards -->
	<link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
	<!-- Estilos específicos para formulários de edição -->
	<link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/account-edit.css'), ENT_QUOTES, 'UTF-8'); ?>">
	
</head>

<body>
	<!-- Botão fixo para retornar à página inicial -->
	<button class="back-button" onclick="window.location.href='<?= htmlspecialchars(url_path('index.php'), ENT_QUOTES, 'UTF-8'); ?>'">← Voltar</button>
	<!-- Barra lateral com perfil e navegação da conta -->
	<div class="sidebar">
		<div class="profile">
			<p>Olá!</p>
			<p><?php echo htmlspecialchars($_SESSION['user_nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
		</div>
		<!-- Menu de navegação entre seções da conta -->
		<nav>
			<a href="<?= htmlspecialchars(url_path('public/pages/account/perfil.php'), ENT_QUOTES, 'UTF-8'); ?>" class="active">Dados pessoais</a>
			<a href="<?= htmlspecialchars(url_path('public/pages/account/enderecos.php'), ENT_QUOTES, 'UTF-8'); ?>" class="active">Endereços</a>
			<a href="<?= htmlspecialchars(url_path('public/pages/account/pedidos.php'), ENT_QUOTES, 'UTF-8'); ?>">Pedidos</a>
			<a href="<?= htmlspecialchars(url_path('public/pages/shop/favoritos.php'), ENT_QUOTES, 'UTF-8'); ?>">Favoritos</a>
			<a href="<?= htmlspecialchars(url_path('public/api/auth/logout.php'), ENT_QUOTES, 'UTF-8'); ?>">Sair</a>
		</nav>
	</div>

	<!-- Área principal com formulário de edição -->
	<div class="main-content">
		<h1>Editar endereço</h1>

		<?php if (!empty($errors)) : ?>
			<!-- Exibe mensagens de erro de validação se houver -->
			<div class="status-message status-error">
				<?php foreach ($errors as $error) : ?>
					<p><?php echo sanitizeField($error); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- Card contendo o formulário de edição -->
		<div class="card form-card">
			<!-- Formulário com atributo data-endereco-form para integração JavaScript -->
			<form class="address-form" method="POST" action="editarEnd.php" data-endereco-form>
				<!-- Campo hidden para manter o ID do endereço durante o POST -->
				<input type="hidden" name="endereco_id" value="<?php echo (int)$addressId; ?>">
				<!-- Campo: Nome/Referência do endereço (ex: Casa, Trabalho) -->
				<div class="field">
					<label for="referencia">Nome do endereço</label>
					<input type="text" id="referencia" name="referencia" maxlength="50" value="<?php echo sanitizeField($values['referencia']); ?>" required>
				</div>

				<!-- Campo: Rua (readonly, preenchido via API ViaCEP) -->
				<div class="field">
					<label for="rua">Rua</label>
					<input type="text" id="rua" name="rua" maxlength="150" value="<?php echo sanitizeField($values['rua']); ?>" readonly data-field="rua" required>
				</div>

				<!-- Linha do formulário com dois campos lado a lado -->
				<div class="form-row">
					<!-- Campo: Número do endereço -->
					<div class="field">
						<label for="numero">Número</label>
						<input type="number" id="numero" name="numero" min="0" value="<?php echo sanitizeField($values['numero']); ?>" required>
					</div>
					<!-- Campo: CEP com integração JavaScript para busca automática -->
					<div class="field">
						<label for="cep">CEP</label>
						<!-- Atributo data-cep-input usado pelo JS para detectar mudanças -->
						<input type="text" id="cep" name="cep" maxlength="9" placeholder="00000-000" value="<?php echo sanitizeField($values['cep']); ?>" required data-cep-input>
						<!-- Div para feedback da busca CEP (loading, erro, sucesso) -->
						<div class="cep-feedback" data-cep-feedback></div>
						<!-- Link auxiliar para busca manual no site dos Correios -->
						<a class="cep-helper" href="https://buscacepinter.correios.com.br/app/endereco/index.php" target="_blank" rel="noopener noreferrer">Não sabe o CEP? Consulte nos Correios</a>
					</div>
				</div>

				<!-- Linha com três campos: Bairro, Cidade, Estado -->
				<div class="form-row">
					<!-- Campo: Bairro (readonly, preenchido via API ViaCEP) -->
					<div class="field">
						<label for="bairro">Bairro</label>
						<input type="text" id="bairro" name="bairro" maxlength="100" value="<?php echo sanitizeField($values['bairro']); ?>" readonly data-field="bairro" required>
					</div>
					<!-- Campo: Cidade (readonly, preenchido via API ViaCEP) -->
					<div class="field">
						<label for="cidade">Cidade</label>
						<input type="text" id="cidade" name="cidade" maxlength="150" value="<?php echo sanitizeField($values['cidade']); ?>" readonly data-field="cidade" required>
					</div>
					<!-- Campo: Estado (readonly, preenchido via API ViaCEP) -->
					<div class="field">
						<label for="estado">Estado</label>
						<input type="text" id="estado" name="estado" maxlength="2" value="<?php echo sanitizeField($values['estado']); ?>" readonly data-field="estado" required>
					</div>
				</div>

				<!-- Campo: Complemento (opcional - apartamento, bloco, etc) -->
				<div class="field">
					<label for="complemento">Complemento (opcional)</label>
					<input type="text" id="complemento" name="complemento" maxlength="100" value="<?php echo sanitizeField($values['complemento']); ?>">
				</div>

				<!-- Linha de botões de ação -->
				<div class="submit-row">
					<!-- Botão secundário: cancela edição e volta para lista -->
					<a class="secondary-btn" href="enderecos.php">Cancelar</a>
					<!-- Botão primário: submete formulário para atualização -->
					<button class="primary-btn" type="submit">Salvar alterações</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		// Restaura tema dark salvo no localStorage
		const savedTheme = localStorage.getItem('theme');
		if (savedTheme === 'dark') {
			document.body.classList.add('dark');
		}
	</script>
	<!-- Script para busca automática de CEP via API ViaCEP -->
	<!-- Preenche campos rua, bairro, cidade, estado automaticamente -->
	<script src="<?= htmlspecialchars(asset_path('js/endereco-form.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script>
		// Aplica tema light se foi explicitamente salvo
		window.addEventListener('DOMContentLoaded', () => {
			const savedTheme = localStorage.getItem('theme');
			if (savedTheme === 'light') {
				document.body.classList.add('light');
			}
		});
	</script>

</body>

</html>