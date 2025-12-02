<?php
/**
 * Arquivo: listar.php
 * Propósito: API para listar itens do carrinho do usuário
 * 
 * Endpoint: GET /api/carrinho/listar.php
 * Retorna: Lista completa de itens com preços, quantidades e subtotal
 * 
 * Resposta de sucesso (200):
 * {
 *   "sucesso": true,
 *   "itens": [{...}],
 *   "subtotal": 179.80,
 *   "subtotalFormatado": "179,80"
 * }
 */
declare(strict_types=1);

// Inicializa sessão e carrega dependências
session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/helpers.php';

// Define resposta como JSON
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para visualizar o carrinho.']);
    exit;
}

$userId = carrinhoGetSessionUserId($conn);
if ($userId === null) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

// ===== BUSCA DO ESTADO COMPLETO DO CARRINHO =====
/**
 * carrinhoFetchSnapshot() retorna snapshot completo:
 * - Lista de itens (produto, quantidade, tamanho, preços)
 * - Subtotal calculado (soma de todos os itens)
 * - URLs de imagens resolvidas
 * - Valores formatados para exibição (R$ 1.234,56)
 * 
 * Esta função faz JOIN entre carrinho e roupa para obter:
 * - Dados atuais do produto (nome, preço, imagem)
 * - Dados do item no carrinho (quantidade, tamanho)
 * 
 * Importante: Preços são buscados em tempo real da tabela roupa.
 * Carrinho não armazena preço (diferente de pedido que faz snapshot).
 */
$dados = carrinhoFetchSnapshot($conn, $userId);

// ===== RESPOSTA JSON PADRONIZADA =====
/**
 * Estrutura de resposta:
 * {
 *   "sucesso": true,
 *   "itens": [
 *     {
 *       "id": 1,               // CarID (ID do item no carrinho)
 *       "produtoId": 10,        // RoupaId (ID do produto)
 *       "nome": "Camiseta",
 *       "imagem": "http://...", // URL completa da imagem
 *       "quantidade": 2,
 *       "tamanho": "M",
 *       "precoUnitario": 89.90,
 *       "precoFormatado": "89,90",
 *       "total": 179.80,        // quantidade × preço
 *       "totalFormatado": "179,80"
 *     }
 *   ],
 *   "subtotal": 179.80,        // Soma de todos os itens
 *   "subtotalFormatado": "179,80"
 * }
 */
echo json_encode([
    'sucesso' => true,
    'itens' => $dados['itens'],
    'subtotal' => $dados['subtotal'],
    'subtotalFormatado' => $dados['subtotalFormatado'],
]);
