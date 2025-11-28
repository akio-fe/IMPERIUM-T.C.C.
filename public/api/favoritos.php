<?php
session_start();
require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function respond(int $status, array $payload): void
{
	http_response_code($status);
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
}

function ensureLoggedUserId(mysqli $conn): int
{
	$isLogged = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
	$email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';

	if (!$isLogged || $email === '') {
		respond(401, [
			'success' => false,
			'message' => 'Você precisa estar logado para acessar os favoritos.',
		]);
	}

	if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
		return (int) $_SESSION['user_id'];
	}

	$stmt = $conn->prepare('SELECT UsuId FROM usuario WHERE UsuEmail = ? LIMIT 1');
	if (!$stmt) {
		respond(500, [
			'success' => false,
			'message' => 'Não foi possível identificar o usuário logado.',
		]);
	}

	$stmt->bind_param('s', $email);
	$stmt->execute();
	$stmt->bind_result($userId);
	$found = $stmt->fetch();
	$stmt->close();

	if (!$found) {
		respond(401, [
			'success' => false,
			'message' => 'Sessão inválida. Faça login novamente.',
		]);
	}

	$_SESSION['user_id'] = (int) $userId;
	return (int) $userId;
}

function readJsonBody(): array
{
	$raw = file_get_contents('php://input');
	if ($raw === false || $raw === '') {
		return [];
	}
	$decoded = json_decode($raw, true);
	return is_array($decoded) ? $decoded : [];
}

function fetchFavoritos(mysqli $conn, int $userId): array
{
	$sql = 'SELECT f.FavProId, f.FavProData, r.RoupaId, r.RoupaNome, r.RoupaValor, r.RoupaImgUrl, r.CatRId
			FROM favorito f
			INNER JOIN roupa r ON r.RoupaId = f.RoupaId
			WHERE f.UsuId = ?
			ORDER BY f.FavProData DESC, f.FavProId DESC';

	$stmt = $conn->prepare($sql);
	if (!$stmt) {
		respond(500, [
			'success' => false,
			'message' => 'Não foi possível consultar os favoritos.',
		]);
	}

	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$result = $stmt->get_result();
	$items = [];
	while ($row = $result->fetch_assoc()) {
		$productLink = url_path('public/pages/shop/produto.php') . '?id=' . (int) $row['RoupaId'];
		$items[] = [
			'favoritoId' => (int) $row['FavProId'],
			'data' => $row['FavProData'],
			'produto' => [
				'id' => (int) $row['RoupaId'],
				'nome' => $row['RoupaNome'],
				'valor' => (float) $row['RoupaValor'],
				'valorFormatado' => 'R$ ' . number_format((float) $row['RoupaValor'], 2, ',', '.'),
				'imagem' => asset_path((string) $row['RoupaImgUrl']),
				'categoriaId' => (int) $row['CatRId'],
				'link' => $productLink,
			],
		];
	}
	$stmt->close();

	return $items;
}

function ensureProdutoExiste(mysqli $conn, int $produtoId): void
{
	$stmt = $conn->prepare('SELECT 1 FROM roupa WHERE RoupaId = ? LIMIT 1');
	if (!$stmt) {
		respond(500, [
			'success' => false,
			'message' => 'Não foi possível validar o produto informado.',
		]);
	}
	$stmt->bind_param('i', $produtoId);
	$stmt->execute();
	$stmt->store_result();
	$exists = $stmt->num_rows > 0;
	$stmt->close();

	if (!$exists) {
		respond(404, [
			'success' => false,
			'message' => 'Produto não encontrado.',
		]);
	}
}

$userId = ensureLoggedUserId($conn);

switch ($method) {
	case 'GET':
		$favoritos = fetchFavoritos($conn, $userId);
		respond(200, [
			'success' => true,
			'data' => $favoritos,
		]);

	case 'POST':
		$body = readJsonBody();
		$produtoId = isset($body['produtoId']) ? (int) $body['produtoId'] : 0;
		if ($produtoId <= 0) {
			respond(400, [
				'success' => false,
				'message' => 'Produto inválido.',
			]);
		}

		ensureProdutoExiste($conn, $produtoId);

		$stmt = $conn->prepare('SELECT FavProId FROM favorito WHERE RoupaId = ? AND UsuId = ? LIMIT 1');
		if (!$stmt) {
			respond(500, [
				'success' => false,
				'message' => 'Não foi possível favoritar este produto.',
			]);
		}
		$stmt->bind_param('ii', $produtoId, $userId);
		$stmt->execute();
		$stmt->store_result();
		$alreadyFavorited = $stmt->num_rows > 0;
		$stmt->close();

		if ($alreadyFavorited) {
			respond(200, [
				'success' => true,
				'message' => 'Produto já estava nos favoritos.',
			]);
		}

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

		respond(201, [
			'success' => true,
			'message' => 'Produto adicionado aos favoritos.',
		]);

	case 'DELETE':
		$body = readJsonBody();
		$produtoId = isset($body['produtoId']) ? (int) $body['produtoId'] : 0;
		if ($produtoId <= 0) {
			respond(400, [
				'success' => false,
				'message' => 'Produto inválido.',
			]);
		}

		$stmt = $conn->prepare('DELETE FROM favorito WHERE RoupaId = ? AND UsuId = ?');
		if (!$stmt) {
			respond(500, [
				'success' => false,
				'message' => 'Não foi possível remover este favorito.',
			]);
		}
		$stmt->bind_param('ii', $produtoId, $userId);
		$stmt->execute();
		$removed = $stmt->affected_rows > 0;
		$stmt->close();

		respond($removed ? 200 : 404, [
			'success' => $removed,
			'message' => $removed ? 'Produto removido dos favoritos.' : 'Favorito não encontrado.',
		]);

	default:
		respond(405, [
			'success' => false,
			'message' => 'Método não suportado.',
		]);
}
