<?php
/**
 * Página: Adicionar Novo Endereço de Entrega
 * Propósito: Formulário para cadastro de novos endereços de entrega.
 * 
 * Funcionalidades:
 * - Formulário para cadastro de endereço completo
 * - Integração com API ViaCEP (via JavaScript) para preenchimento automático
 * - Validações server-side completas
 * - Lista de estados brasileiros com validação
 * - Suporte a complemento opcional
 * - Feedback de erros inline
 * 
 * Validações Implementadas:
 * - Nome do endereço: obrigatório (ex: Casa, Trabalho)
 * - Rua: obrigatória
 * - Número: inteiro não negativo
 * - CEP: exatamente 8 dígitos, auto-formatado para 00000-000
 * - Bairro: obrigatório
 * - Cidade: obrigatória
 * - Estado: deve ser sigla válida (AC, SP, RJ, etc)
 * - Complemento: opcional
 * 
 * Segurança:
 * - Requer autenticação (sessão ativa)
 * - Prepared statements em todas as queries
 * - Sanitização de todos os campos exibidos
 * - Validação tanto client-side (JS) quanto server-side (PHP)
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

/**
 * Lista completa de estados brasileiros (sigla => nome completo).
 * Usado para:
 * - Validar se o estado fornecido é válido
 * - Gerar select no frontend (se necessário)
 * - Normalizar siglas para maiúsculas
 */
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

/**
 * Array com valores padrão para todos os campos do formulário.
 * Será preenchido com dados do POST em caso de erro de validação
 * para reexibir os valores digitados pelo usuário.
 */
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

// Processamento do formulário quando submetido via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userId !== null) {
	// Atualiza array $values com dados enviados pelo formulário
	// trim() remove espaços em branco extras
	foreach ($values as $field => $default) {
		$values[$field] = trim($_POST[$field] ?? '');
	}

	// VALIDAÇÕES DE CAMPOS OBRIGATÓRIOS E FORMATOS

	// Validação: nome/referência do endereço é obrigatório
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
	// Remove todos os caracteres não numéricos
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
		// Normaliza para maiúsculas (AC, SP, RJ, etc)
		$values['estado'] = strtoupper($values['estado']);
	}

	// Se não há erros de validação, insere o novo endereço no banco
	if (empty($errors)) {
		// Prepara INSERT com prepared statement para segurança
		$stmt = $conn->prepare('INSERT INTO EnderecoEntrega (EndEntRef, EndEntRua, EndEntCep, EndEntNum, EndEntBairro, EndEntCid, EndEntEst, EndEntComple, UsuId) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
		if ($stmt) {
			// Complemento pode ser vazio (campo opcional)
			$complemento = $values['complemento'];
			// Vincula parâmetros: 8 campos do endereço + 1 UsuId
			// Tipos: s=string, i=integer
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
				// Inserção bem-sucedida: redireciona com mensagem de sucesso
				$stmt->close();
				header('Location: ' . $enderecosPage . '?status=added');
				exit();
			}

			// Falha na execução do INSERT
			$errors[] = 'Erro ao salvar o endereço. Tente novamente.';
			$stmt->close();
		} else {
			// Falha na preparação da query
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
	<!-- Fonte Google Inter para consistência visual -->
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
	<!-- Ícone da página exibido na aba do navegador -->
	<link rel="icon" href="<?= htmlspecialchars(asset_path('img/catalog/icone.ico'), ENT_QUOTES, 'UTF-8'); ?>">
	<!-- Estilos base do perfil: sidebar, layout, cards -->
	<link rel="stylesheet" href="<?= htmlspecialchars(asset_path('css/perfil.css'), ENT_QUOTES, 'UTF-8'); ?>">
	<!-- Estilos específicos para formulários de edição/adição -->
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

	<!-- Área principal com formulário de cadastro -->
	<div class="main-content">
		<h1>Novo endereço</h1>

		<?php if (!empty($errors)) : ?>
			<!-- Exibe mensagens de erro de validação se houver -->
			<div class="status-message status-error">
				<?php foreach ($errors as $error) : ?>
					<p><?php echo sanitizeField($error); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<!-- Card contendo o formulário -->
		<div class="card form-card">
			<!-- Formulário com atributo data-endereco-form para integração JavaScript -->
			<form class="address-form" method="POST" action="addEnd.php" data-endereco-form>
				<!-- Campo: Nome/Referência do endereço (ex: Casa, Trabalho) -->
				<div class="field">
					<label for="referencia">Nome do endereço</label>
					<input type="text" id="referencia" name="referencia" maxlength="50" value="<?php echo sanitizeField($values['referencia']); ?>" required>
				</div>

				<!-- Campo: Rua (readonly, será preenchido via API ViaCEP) -->
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

				<!-- Linha de botões de ação -->
				<div class="submit-row">
					<!-- Botão secundário: cancela adição e volta para lista -->
					<a class="secondary-btn" href="enderecos.php">Cancelar</a>
					<!-- Botão primário: submete formulário para inserção -->
					<button class="primary-btn" type="submit">Salvar</button>
				</div>
			</form>
		</div>
	</div>

	<script>
		// Restaura tema dark salvo no localStorage
		// Aplicado antes do carregamento completo para evitar flash
		const savedTheme = localStorage.getItem('theme');
		if (savedTheme === 'dark') {
			document.body.classList.add('dark');
		}
	</script>
	<!-- Script para busca automática de CEP via API ViaCEP -->
	<!-- Preenche automaticamente campos: rua, bairro, cidade, estado -->
	<script src="<?= htmlspecialchars(asset_path('js/endereco-form.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
	<script>
		// Aplica tema light se foi explicitamente salvo
		// (segundo script para compatibilidade)
		window.addEventListener('DOMContentLoaded', () => {
			const savedTheme = localStorage.getItem('theme');
			if (savedTheme === 'light') {
				document.body.classList.add('light');
			}
		});
	</script>

</body>

</html>