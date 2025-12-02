<?php
/**
 * Arquivo: helpers.php (Carrinho)
 * Propósito: Funções auxiliares compartilhadas entre endpoints do carrinho
 * 
 * Funções disponíveis:
 * - carrinhoGetSessionUserId(): Obtém ID do usuário pela sessão
 * - carrinhoFormatMoney(): Formata valores em Real (R$ 1.234,56)
 * - carrinhoAssetPath(): Resolve URLs de assets
 * - carrinhoFetchSnapshot(): Busca estado completo do carrinho
 * 
 * Usado por:
 * - adicionar.php, atualizar.php, listar.php, remover.php
 * - criar_pedido.php (checkout)
 */
declare(strict_types=1);

/**
 * Obtém ID do usuário autenticado a partir do email da sessão.
 * 
 * Processo:
 * 1. Extrai email de $_SESSION (definido no login)
 * 2. Busca UsuId correspondente no banco
 * 3. Retorna ID ou null se não encontrado/autenticado
 * 
 * @param mysqli $conn Conexão ativa com banco de dados
 * @return int|null ID do usuário ou null se não autenticado
 */
function carrinhoGetSessionUserId(mysqli $conn): ?int
{
    // Extrai e limpa email da sessão
    $email = isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '';
    if ($email === '') {
        return null; // Sessão sem email = não autenticado
    }

    // Busca UsuId no banco pelo email
    $stmt = $conn->prepare('SELECT UsuId FROM usuario WHERE UsuEmail = ? LIMIT 1');
    if (!$stmt) {
        return null; // Erro ao preparar query
    }

    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($userId);
    
    // Retorna ID se encontrado, null caso contrário
    $found = $stmt->fetch() ? (int) $userId : null;
    $stmt->close();

    return $found;
}

/**
 * Formata valor monetário no padrão brasileiro.
 * 
 * Converte float para string formatada:
 * - 2 casas decimais
 * - Vírgula como separador decimal
 * - Ponto como separador de milhares
 * 
 * @param float $valor Valor numérico (ex: 1234.56)
 * @return string Valor formatado (ex: "1.234,56")
 * 
 * @example carrinhoFormatMoney(89.90) → "89,90"
 * @example carrinhoFormatMoney(1234.56) → "1.234,56"
 */
function carrinhoFormatMoney(float $valor): string
{
    return number_format($valor, 2, ',', '.');
}

/**
 * Resolve caminho de asset usando helper global se disponível.
 * 
 * Tenta usar asset_path() do helpers.php principal para resolver
 * URLs considerando subdiretórios. Fallback para path original.
 * 
 * @param string $path Caminho relativo do asset
 * @return string URL completa ou path original
 */
function carrinhoAssetPath(string $path): string
{
    // Usa helper global se disponível
    if (function_exists('asset_path')) {
        return asset_path($path);
    }
    // Fallback: retorna path sem processamento
    return $path;
}

/**
 * Busca estado completo do carrinho de um usuário.
 * 
 * Retorna estrutura completa com:
 * - Lista de itens (produto, quantidade, tamanho, preços)
 * - Subtotal calculado
 * - URLs de imagens resolvidas
 * - Valores formatados para exibição
 * 
 * Query usa JOIN entre carrinho e roupa para obter:
 * - Dados do item (quantidade, tamanho, ID)
 * - Dados do produto (nome, preço, imagem)
 * 
 * @param mysqli $conn Conexão ativa com banco de dados
 * @param int $userId ID do usuário (UsuId)
 * @return array Estrutura com 'itens', 'subtotal', 'subtotalFormatado'
 * 
 * @example Estrutura de retorno:
 * [
 *   'itens' => [
 *     [
 *       'id' => 1,
 *       'produtoId' => 10,
 *       'nome' => 'Camiseta',
 *       'imagem' => 'http://localhost/IMPERIUM/public/assets/img/catalog/camiseta.jpg',
 *       'quantidade' => 2,
 *       'tamanho' => 'M',
 *       'precoUnitario' => 89.90,
 *       'precoFormatado' => '89,90',
 *       'total' => 179.80,
 *       'totalFormatado' => '179,80'
 *     ]
 *   ],
 *   'subtotal' => 179.80,
 *   'subtotalFormatado' => '179,80'
 * ]
 */
function carrinhoFetchSnapshot(mysqli $conn, int $userId): array
{
    // Estrutura inicial do resultado
    $dados = [
        'itens' => [],
        'subtotal' => 0.0,
    ];

    /**
     * Query com JOIN para combinar dados do carrinho e produto.
     * ORDER BY CarID DESC: Itens mais recentes primeiro
     */
    $sql = 'SELECT c.CarID, c.CarQtd, c.CarTam, c.RoupaId, r.RoupaNome, r.RoupaImgUrl, r.RoupaValor
            FROM carrinho c
            INNER JOIN roupa r ON r.RoupaId = c.RoupaId
            WHERE c.UsuId = ?
            ORDER BY c.CarID DESC';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $resultado = $stmt->get_result();
            
            // Processa cada item do carrinho
            while ($row = $resultado->fetch_assoc()) {
                $quantidade = (int) $row['CarQtd'];
                $precoUnitario = (float) $row['RoupaValor'];
                $itemTotal = $quantidade * $precoUnitario; // Total do item

                // Adiciona item processado ao array
                $dados['itens'][] = [
                    'id' => (int) $row['CarID'], // ID do registro no carrinho
                    'produtoId' => (int) $row['RoupaId'], // ID do produto
                    'nome' => $row['RoupaNome'],
                    'imagem' => carrinhoAssetPath((string) $row['RoupaImgUrl']), // URL completa
                    'quantidade' => $quantidade,
                    'tamanho' => $row['CarTam'] ?? '', // P, M, G, 38, etc
                    'precoUnitario' => $precoUnitario, // Valor numérico
                    'precoFormatado' => carrinhoFormatMoney($precoUnitario), // String formatada
                    'total' => $itemTotal, // Quantidade * Preço
                    'totalFormatado' => carrinhoFormatMoney($itemTotal),
                ];

                // Acumula total do carrinho
                $dados['subtotal'] += $itemTotal;
            }
            $resultado->free();
        }
        $stmt->close();
    }

    // Formata subtotal total do carrinho
    $dados['subtotalFormatado'] = carrinhoFormatMoney($dados['subtotal']);

    return $dados;
}
