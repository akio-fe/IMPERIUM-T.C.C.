<?php
/**
 * Arquivo: favoritos.php
 * Propósito: API REST para gerenciamento de produtos favoritos do usuário
 * 
 * Endpoints:
 * - GET  /api/favoritos.php       Lista todos os favoritos do usuário logado
 * - POST /api/favoritos.php       Adiciona produto aos favoritos
 * - DELETE /api/favoritos.php     Remove produto dos favoritos
 * 
 * Requisitos:
 * - Usuário deve estar autenticado (sessão ativa)
 * - Todas as respostas são em formato JSON
 * - Validação de produto existente antes de favoritar
 * 
 * Exemplo de uso (JavaScript):
 * // Listar favoritos
 * fetch('/api/favoritos.php').then(r => r.json())
 * 
 * // Adicionar favorito
 * fetch('/api/favoritos.php', {
 *   method: 'POST',
 *   body: JSON.stringify({produtoId: 123})
 * })
 * 
 * // Remover favorito
 * fetch('/api/favoritos.php', {
 *   method: 'DELETE',
 *   body: JSON.stringify({produtoId: 123})
 * })
 */

// ===== INICIALIZAÇÃO =====
/**
 * Inicia sessão para acessar dados de autenticação do usuário.
 */
session_start();

/**
 * Carrega dependências: conexão DB, helpers, autoloader.
 */
require_once dirname(__DIR__) . '/bootstrap.php';

// ===== CONFIGURAÇÃO DE HEADERS =====
/**
 * Define Content-Type como JSON para todas as respostas.
 * Cliente sempre receberá dados estruturados em formato JSON.
 */
header('Content-Type: application/json');

// ===== DETECÇÃO DO MÉTODO HTTP =====
/**
 * Identifica qual operação cliente deseja executar.
 * 
 * @var string $method Método HTTP (GET, POST, DELETE)
 */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ===== FUNÇÃO AUXILIAR: RESPONDER COM JSON =====
/**
 * Envia resposta JSON padronizada ao cliente e encerra script.
 * 
 * Centraliza lógica de resposta para garantir:
 * - Status HTTP correto
 * - JSON válido e bem formatado
 * - Caracteres Unicode preservados (acentos, emojis)
 * - URLs sem escape excessivo
 * 
 * @param int $status Código HTTP (200, 400, 401, 404, 500, etc)
 * @param array $payload Dados a serem enviados como JSON
 * @return void (never returns - encerra script com exit)
 * 
 * @example respond(200, ['success' => true, 'data' => $favoritos]);
 * @example respond(404, ['success' => false, 'message' => 'Produto não encontrado']);
 */
function respond(int $status, array $payload): void
{
	// Define código de status HTTP da resposta
	http_response_code($status);
	
	/**
	 * Converte array PHP para JSON com flags especiais:
	 * - JSON_UNESCAPED_UNICODE: Mantém acentos e caracteres especiais literais
	 *   "Calça" em vez de "Cal\u00e7a"
	 * - JSON_UNESCAPED_SLASHES: Mantém barras em URLs literais
	 *   "http://site.com/path" em vez de "http:\/\/site.com\/path"
	 */
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	
	// Encerra script imediatamente (não executa código após respond())
	exit;
}

// ===== FUNÇÃO: GARANTIR USUÁRIO AUTENTICADO =====
/**
 * Valida autenticação do usuário e retorna seu ID do banco de dados.
 * 
 * Processo de validação:
 * 1. Verifica flag logged_in na sessão
 * 2. Verifica presença de email na sessão
 * 3. Tenta usar user_id cacheado na sessão (performance)
 * 4. Se não cacheado, busca ID no banco pelo email
 * 5. Cacheia ID na sessão para próximas requisições
 * 
 * Segurança:
 * - Email validado pelo Firebase no login
 * - ID buscado apenas do banco (não aceita ID fornecido pelo cliente)
 * - Retorna 401 se sessão inválida ou expirada
 * 
 * @param mysqli $conn Conexão ativa com banco de dados
 * @return int ID do usuário no banco (UsuId)
 * 
 * @throws void (não retorna em caso de falha - chama respond() que faz exit)
 */
function ensureLoggedUserId(mysqli $conn): int
{
	// Verifica se flag de login está presente e ativa
	$isLogged = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
	
	// Extrai email da sessão (definido no login.php após validação Firebase)
	$email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';

	// Valida precondições de autenticação
	if (!$isLogged || $email === '') {
		// Retorna 401 Unauthorized com mensagem amigável
		respond(401, [
			'success' => false,
			'message' => 'Você precisa estar logado para acessar os favoritos.',
		]);
		// respond() faz exit, código abaixo não executa
	}

	// ==== CACHE DE PERFORMANCE ====
	/**
	 * Verifica se ID já foi buscado anteriormente e está cacheado na sessão.
	 * Evita query desnecessária ao banco em cada requisição.
	 */
	if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
		return (int) $_SESSION['user_id'];
	}

	// ==== BUSCA DE ID NO BANCO ====
	/**
	 * ID não cacheado: precisa consultar banco de dados.
	 * Busca UsuId pelo email que foi validado pelo Firebase.
	 */
	$stmt = $conn->prepare('SELECT UsuId FROM usuario WHERE UsuEmail = ? LIMIT 1');
	
	// Valida se prepared statement foi criado com sucesso
	if (!$stmt) {
		respond(500, [
			'success' => false,
			'message' => 'Não foi possível identificar o usuário logado.',
		]);
	}

	// Vincula email como parâmetro (previne SQL injection)
	$stmt->bind_param('s', $email);
	
	// Executa query
	$stmt->execute();
	
	// Vincula coluna UsuId à variável $userId
	$stmt->bind_result($userId);
	
	// Tenta buscar resultado (true se encontrou, false se não)
	$found = $stmt->fetch();
	
	// Libera recursos do statement
	$stmt->close();

	// Valida se usuário foi encontrado no banco
	if (!$found) {
		/**
		 * Email existe no Firebase mas não no banco local.
		 * Pode indicar:
		 * - Inconsistência entre sistemas
		 * - Usuário deletado do banco mas não do Firebase
		 * - Sessão corrompida
		 */
		respond(401, [
			'success' => false,
			'message' => 'Sessão inválida. Faça login novamente.',
		]);
	}

	// ==== ARMAZENAMENTO EM CACHE ====
	/**
	 * Salva ID na sessão para evitar busca repetida no banco.
	 * Próximas chamadas a esta função usarão cache (return na linha 85).
	 */
	$_SESSION['user_id'] = (int) $userId;
	
	return (int) $userId;
}

// ===== FUNÇÃO: LER CORPO DA REQUISIÇÃO JSON =====
/**
 * Extrai e decodifica JSON enviado no corpo da requisição HTTP.
 * 
 * Em requisições POST/DELETE, dados são enviados no corpo (body) e não
 * em $_POST. Para APIs REST, cliente geralmente envia JSON:
 * fetch('/api', {body: JSON.stringify({produtoId: 123})})
 * 
 * Esta função:
 * 1. Lê stream php://input (corpo bruto da requisição)
 * 2. Decodifica JSON para array associativo PHP
 * 3. Retorna array vazio em caso de falha (segurança)
 * 
 * @return array Dados decodificados do JSON ou [] se inválido/vazio
 * 
 * @example 
 * // Cliente envia: {"produtoId": 123, "cor": "azul"}
 * $body = readJsonBody();
 * // $body = ['produtoId' => 123, 'cor' => 'azul']
 */
function readJsonBody(): array
{
	/**
	 * Lê corpo bruto da requisição HTTP.
	 * php://input é um stream read-only que fornece dados POST brutos.
	 * 
	 * Diferença de $_POST:
	 * - $_POST: apenas funciona com application/x-www-form-urlencoded
	 * - php://input: funciona com application/json e outros formatos
	 */
	$raw = file_get_contents('php://input');
	
	// Valida se leitura foi bem-sucedida e corpo não está vazio
	if ($raw === false || $raw === '') {
		return []; // Retorna array vazio (safe default)
	}
	
	/**
	 * Decodifica JSON para array associativo PHP.
	 * 
	 * Segundo parâmetro true = array associativo
	 * false/omitido = stdClass object
	 */
	$decoded = json_decode($raw, true);
	
	/**
	 * Valida tipo do resultado.
	 * json_decode() retorna null em caso de JSON inválido.
	 * Garante sempre retornar array (nunca null).
	 */
	return is_array($decoded) ? $decoded : [];
}

// ===== FUNÇÃO: BUSCAR LISTA DE FAVORITOS =====
/**
 * Recupera todos os produtos favoritados por um usuário específico.
 * 
 * Retorna array completo com:
 * - Dados do favorito (ID, data de adição)
 * - Dados do produto (nome, preço, imagem, categoria)
 * - URLs formatadas (link do produto, caminho da imagem)
 * - Valor formatado em Real (R$ 1.234,56)
 * 
 * Query utiliza JOIN para combinar:
 * - Tabela favorito: relacionamento usuário-produto + timestamp
 * - Tabela roupa: informações completas do produto
 * 
 * Ordenação: Mais recentes primeiro (DESC por data e ID)
 * 
 * @param mysqli $conn Conexão ativa com banco de dados
 * @param int $userId ID do usuário (UsuId)
 * @return array Lista de favoritos com produtos completos
 * 
 * @example Estrutura de retorno:
 * [
 *   [
 *     'favoritoId' => 42,
 *     'data' => '2024-01-15 14:30:00',
 *     'produto' => [
 *       'id' => 10,
 *       'nome' => 'Camiseta Streetwear',
 *       'valor' => 89.90,
 *       'valorFormatado' => 'R$ 89,90',
 *       'imagem' => 'http://localhost/IMPERIUM/public/assets/img/catalog/camiseta.jpg',
 *       'categoriaId' => 4,
 *       'link' => 'http://localhost/IMPERIUM/public/pages/shop/produto.php?id=10'
 *     ]
 *   ],
 *   ...
 * ]
 */
function fetchFavoritos(mysqli $conn, int $userId): array
{
	/**
	 * Query SQL com JOIN entre favorito e roupa.
	 * 
	 * SELECT:
	 * - f.FavProId: ID único do registro de favorito
	 * - f.FavProData: Quando foi adicionado aos favoritos
	 * - r.*: Todos os dados do produto (RoupaId, RoupaNome, etc)
	 * 
	 * WHERE: Filtra apenas favoritos do usuário específico
	 * ORDER BY: Mais recentes primeiro (útil para UI "Adicionados recentemente")
	 */
	$sql = 'SELECT f.FavProId, f.FavProData, r.RoupaId, r.RoupaNome, r.RoupaValor, r.RoupaImgUrl, r.CatRId
			FROM favorito f
			INNER JOIN roupa r ON r.RoupaId = f.RoupaId
			WHERE f.UsuId = ?
			ORDER BY f.FavProData DESC, f.FavProId DESC';

	// Prepara statement (previne SQL injection)
	$stmt = $conn->prepare($sql);
	
	// Valida preparação do statement
	if (!$stmt) {
		respond(500, [
			'success' => false,
			'message' => 'Não foi possível consultar os favoritos.',
		]);
	}

	// Vincula ID do usuário como parâmetro seguro
	$stmt->bind_param('i', $userId);
	
	// Executa query
	$stmt->execute();
	
	// Obtém conjunto de resultados
	$result = $stmt->get_result();
	
	// Array para armazenar favoritos processados
	$items = [];
	
	/**
	 * Itera sobre cada linha do resultado, processando e formatando dados.
	 */
	while ($row = $result->fetch_assoc()) {
		/**
		 * Gera URL completa para a página de detalhes do produto.
		 * Usa helper url_path() para funcionar em subdiretórios.
		 */
		$productLink = url_path('public/pages/shop/produto.php') . '?id=' . (int) $row['RoupaId'];
		
		/**
		 * Estrutura cada favorito como objeto JSON amigável para front-end.
		 */
		$items[] = [
			'favoritoId' => (int) $row['FavProId'], // ID do favorito (para remoção)
			'data' => $row['FavProData'], // Timestamp ISO 8601
			'produto' => [
				'id' => (int) $row['RoupaId'], // ID do produto
				'nome' => $row['RoupaNome'], // Nome/título do produto
				'valor' => (float) $row['RoupaValor'], // Preço numérico (para cálculos)
				'valorFormatado' => 'R$ ' . number_format((float) $row['RoupaValor'], 2, ',', '.'), // Preço formatado para exibição
				'imagem' => asset_path((string) $row['RoupaImgUrl']), // URL completa da imagem
				'categoriaId' => (int) $row['CatRId'], // ID da categoria (calçados, camisas, etc)
				'link' => $productLink, // URL da página do produto
			],
		];
	}
	
	// Libera recursos do statement
	$stmt->close();

	// Retorna lista completa de favoritos
	return $items;
}

// ===== FUNÇÃO: VALIDAR EXISTÊNCIA DE PRODUTO =====
/**
 * Verifica se produto existe no catálogo antes de permitir favoritar.
 * 
 * Validação essencial para prevenir:
 * - IDs inválidos/inexistentes sendo favoritados
 * - Foreign key constraint violations
 * - Referências órfãs no banco de dados
 * 
 * Se produto não existir, encerra script com erro 404.
 * 
 * @param mysqli $conn Conexão ativa com banco de dados
 * @param int $produtoId ID do produto a validar (RoupaId)
 * @return void (não retorna em caso de falha - chama respond() que faz exit)
 * 
 * @throws void (responde 404 se produto não existe, 500 se erro no banco)
 */
function ensureProdutoExiste(mysqli $conn, int $produtoId): void
{
	/**
	 * Query otimizada que retorna apenas 1 se produto existe.
	 * SELECT 1: Não busca colunas reais, apenas verifica existência (performance).
	 * LIMIT 1: Para assim que encontrar primeiro resultado.
	 */
	$stmt = $conn->prepare('SELECT 1 FROM roupa WHERE RoupaId = ? LIMIT 1');
	
	// Valida preparação do statement
	if (!$stmt) {
		respond(500, [
			'success' => false,
			'message' => 'Não foi possível validar o produto informado.',
		]);
	}
	
	// Vincula ID do produto como parâmetro
	$stmt->bind_param('i', $produtoId);
	
	// Executa query
	$stmt->execute();
	
	/**
	 * store_result() armazena resultado em memória para permitir num_rows.
	 * Necessário porque num_rows não funciona sem armazenar resultado primeiro.
	 */
	$stmt->store_result();
	
	// Verifica se alguma linha foi retornada (produto existe)
	$exists = $stmt->num_rows > 0;
	
	// Libera recursos
	$stmt->close();

	// Se produto não existe, retorna erro 404
	if (!$exists) {
		respond(404, [
			'success' => false,
			'message' => 'Produto não encontrado.',
		]);
		// respond() faz exit, código após não executa
	}
	
	// Se chegou aqui, produto existe (validação passou)
}

// ===== VALIDAÇÃO DE AUTENTICAÇÃO =====
/**
 * Garante que usuário está logado antes de processar qualquer operação.
 * Se não autenticado, ensureLoggedUserId() responde 401 e encerra script.
 * 
 * @var int $userId ID do usuário logado (UsuId do banco)
 */
$userId = ensureLoggedUserId($conn);

// ===== ROTEAMENTO POR MÉTODO HTTP =====
/**
 * Switch case implementa roteamento REST básico.
 * Cada método HTTP corresponde a uma operação CRUD:
 * - GET: Read (listar favoritos)
 * - POST: Create (adicionar favorito)
 * - DELETE: Delete (remover favorito)
 */
switch ($method) {
	// ===== ENDPOINT: GET - LISTAR FAVORITOS =====
	/**
	 * Retorna lista completa de produtos favoritos do usuário.
	 * 
	 * Requisição:
	 * GET /api/favoritos.php
	 * 
	 * Resposta de sucesso (200):
	 * {
	 *   "success": true,
	 *   "data": [
	 *     {"favoritoId": 1, "produto": {...}},
	 *     ...
	 *   ]
	 * }
	 */
	case 'GET':
		// Busca favoritos do usuário no banco
		$favoritos = fetchFavoritos($conn, $userId);
		
		// Retorna lista com status 200 OK
		respond(200, [
			'success' => true,
			'data' => $favoritos,
		]);
		// respond() faz exit, não executa casos seguintes

	// ===== ENDPOINT: POST - ADICIONAR FAVORITO =====
	/**
	 * Adiciona produto aos favoritos do usuário.
	 * 
	 * Requisição:
	 * POST /api/favoritos.php
	 * Body: {"produtoId": 123}
	 * 
	 * Validações:
	 * - produtoId deve ser inteiro positivo
	 * - Produto deve existir no catálogo
	 * - Evita duplicatas (verifica se já favoritado)
	 * 
	 * Resposta de sucesso (201):
	 * {"success": true, "message": "Produto adicionado aos favoritos."}
	 */
	case 'POST':
		// Extrai dados JSON do corpo da requisição
		$body = readJsonBody();
		
		// Valida e sanitiza ID do produto
		$produtoId = isset($body['produtoId']) ? (int) $body['produtoId'] : 0;
		
		// Rejeita IDs inválidos (0, negativos)
		if ($produtoId <= 0) {
			respond(400, [
				'success' => false,
				'message' => 'Produto inválido.',
			]);
		}

		// Verifica se produto existe no catálogo (404 se não existir)
		ensureProdutoExiste($conn, $produtoId);

		// ==== VERIFICAÇÃO DE DUPLICATA ====
		/**
		 * Previne adicionar mesmo produto múltiplas vezes.
		 * Consulta se combinação produto+usuário já existe.
		 */
		$stmt = $conn->prepare('SELECT FavProId FROM favorito WHERE RoupaId = ? AND UsuId = ? LIMIT 1');
		if (!$stmt) {
			respond(500, [
				'success' => false,
				'message' => 'Não foi possível favoritar este produto.',
			]);
		}
		
		// Vincula produto e usuário
		$stmt->bind_param('ii', $produtoId, $userId);
		$stmt->execute();
		$stmt->store_result();
		
		// Verifica se já existe registro
		$alreadyFavorited = $stmt->num_rows > 0;
		$stmt->close();

		// Se já favoritado, retorna sucesso (operação idempotente)
		if ($alreadyFavorited) {
			respond(200, [
				'success' => true,
				'message' => 'Produto já estava nos favoritos.',
			]);
		}

		// ==== INSERÇÃO DO FAVORITO ====
		/**
		 * Cria novo registro na tabela favorito.
		 * FavProData: Timestamp atual (NOW())
		 * RoupaId: ID do produto
		 * UsuId: ID do usuário logado
		 */
		$stmtInsert = $conn->prepare('INSERT INTO favorito (FavProData, RoupaId, UsuId) VALUES (NOW(), ?, ?)');
		if (!$stmtInsert) {
			respond(500, [
				'success' => false,
				'message' => 'Não foi possível favoritar este produto.',
			]);
		}
		
		$stmtInsert->bind_param('ii', $produtoId, $userId);
		$stmtInsert->execute();
		$stmtInsert->close();

		// Retorna 201 Created (novo recurso criado)
		respond(201, [
			'success' => true,
			'message' => 'Produto adicionado aos favoritos.',
		]);

	// ===== ENDPOINT: DELETE - REMOVER FAVORITO =====
	/**
	 * Remove produto dos favoritos do usuário.
	 * 
	 * Requisição:
	 * DELETE /api/favoritos.php
	 * Body: {"produtoId": 123}
	 * 
	 * Resposta de sucesso (200):
	 * {"success": true, "message": "Produto removido dos favoritos."}
	 * 
	 * Resposta se não favoritado (404):
	 * {"success": false, "message": "Favorito não encontrado."}
	 */
	case 'DELETE':
		// Extrai dados JSON do corpo
		$body = readJsonBody();
		
		// Valida ID do produto
		$produtoId = isset($body['produtoId']) ? (int) $body['produtoId'] : 0;
		if ($produtoId <= 0) {
			respond(400, [
				'success' => false,
				'message' => 'Produto inválido.',
			]);
		}

		// ==== REMOÇÃO DO FAVORITO ====
		/**
		 * Deleta registro específico do favorito.
		 * WHERE garante que só remove favorito do usuário atual.
		 */
		$stmt = $conn->prepare('DELETE FROM favorito WHERE RoupaId = ? AND UsuId = ?');
		if (!$stmt) {
			respond(500, [
				'success' => false,
				'message' => 'Não foi possível remover este favorito.',
			]);
		}
		
		$stmt->bind_param('ii', $produtoId, $userId);
		$stmt->execute();
		
		/**
		 * affected_rows indica quantas linhas foram deletadas.
		 * > 0: Favorito foi removido
		 * = 0: Favorito não existia (produto não estava favoritado)
		 */
		$removed = $stmt->affected_rows > 0;
		$stmt->close();

		// Retorna status dinâmico: 200 se removido, 404 se não encontrado
		respond($removed ? 200 : 404, [
			'success' => $removed,
			'message' => $removed ? 'Produto removido dos favoritos.' : 'Favorito não encontrado.',
		]);

	// ===== MÉTODO NÃO SUPORTADO =====
	/**
	 * Trata requisições com métodos HTTP não implementados (PUT, PATCH, etc).
	 * Retorna 405 Method Not Allowed.
	 */
	default:
		respond(405, [
			'success' => false,
			'message' => 'Método não suportado.',
		]);
}
