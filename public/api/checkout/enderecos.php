<?php
/**
 * ============================================================
 * API: Listagem de Endereços de Entrega para Checkout
 * ============================================================
 * 
 * Arquivo: enderecos.php
 * Propósito: Retorna todos os endereços cadastrados pelo usuário autenticado
 * 
 * Endpoint: GET /api/checkout/enderecos.php
 * Autenticação: Requerida (sessão PHP)
 * Content-Type: application/json
 * 
 * Casos de Uso:
 * - Tela de checkout: usuário seleciona endereço de entrega
 * - Página de gerenciamento de endereços
 * - Cálculo de frete (integração com Correios/Melhor Envio)
 * 
 * Fluxo de Checkout:
 * 1. Usuário adiciona produtos ao carrinho
 * 2. Clica em "Finalizar Compra"
 * 3. Esta API retorna endereços cadastrados
 * 4. Usuário seleciona endereço desejado
 * 5. Sistema calcula frete para CEP selecionado
 * 6. Usuário confirma e cria pedido
 * 
 * Resposta de Sucesso (200):
 * {
 *   "sucesso": true,
 *   "enderecos": [
 *     {
 *       "id": 1,
 *       "referencia": "Casa",
 *       "rua": "Rua das Flores",
 *       "cep": "12345-678",
 *       "numero": "123",
 *       "bairro": "Centro",
 *       "cidade": "São Paulo",
 *       "estado": "SP",
 *       "complemento": "Apto 45"
 *     }
 *   ]
 * }
 * 
 * Resposta de Erro (401):
 * {
 *   "sucesso": false,
 *   "mensagem": "Faça login para continuar."
 * }
 * 
 * Ordenação:
 * Endereços retornados do mais recente (DESC) para facilitar seleção
 * do último endereço cadastrado (geralmente o mais usado).
 */
declare(strict_types=1);

// ===== INICIALIZAÇÃO =====
/**
 * Carrega sessão para verificar autenticação do usuário.
 * Sessão criada em /api/auth/login.php após validação Firebase.
 */
session_start();

/**
 * Importa dependências:
 * - bootstrap.php: conexão MySQL, autoloader Composer
 * - helpers.php: função carrinhoGetSessionUserId()
 */
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once __DIR__ . '/../carrinho/helpers.php';

// ===== CONFIGURAÇÃO DE RESPOSTA =====
/**
 * Define Content-Type como JSON com charset UTF-8.
 * Garante acentuação correta em endereços brasileiros.
 */
header('Content-Type: application/json; charset=UTF-8');

// ===== VALIDAÇÃO DO MÉTODO HTTP =====
/**
 * Aceita apenas GET (leitura de dados).
 * 
 * POST/PUT/DELETE não fazem sentido neste endpoint:
 * - POST: adicionar endereço → /api/checkout/adicionar_endereco.php
 * - PUT: editar endereço → /api/checkout/editar_endereco.php
 * - DELETE: remover endereço → /api/checkout/deletar_endereco.php
 */
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.']);
    exit;
}

// ===== VALIDAÇÃO DE AUTENTICAÇÃO - NÍVEL 1 =====
/**
 * Verifica flag logged_in na sessão.
 * 
 * Flag definida em /api/auth/login.php após:
 * 1. Validação do token JWT do Firebase
 * 2. Verificação do usuário no banco MySQL
 * 3. Criação da sessão PHP
 */
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); // Unauthorized
    echo json_encode(['sucesso' => false, 'mensagem' => 'Faça login para continuar.']);
    exit;
}

// ===== VALIDAÇÃO DE AUTENTICAÇÃO - NÍVEL 2 =====
/**
 * Busca ID do usuário no banco usando email da sessão.
 * 
 * carrinhoGetSessionUserId():
 * - Valida email na sessão
 * - Busca UsuId no banco
 * - Cacheia ID na sessão (performance)
 * - Retorna null se sessão inválida/expirada
 * 
 * Dupla validação garante segurança:
 * Mesmo que atacante manipule sessão PHP, ID vem do banco.
 */
$userId = carrinhoGetSessionUserId($conn);
if ($userId === null) {
    http_response_code(401); // Unauthorized
    echo json_encode(['sucesso' => false, 'mensagem' => 'Sessão expirada. Faça login novamente.']);
    exit;
}

// ===== INICIALIZAÇÃO DO ARRAY DE ENDEREÇOS =====
/**
 * Array vazio para armazenar endereços encontrados.
 * Retornado mesmo se usuário não tiver endereços cadastrados.
 */
$enderecos = [];

// ===== CONSULTA DE ENDEREÇOS NO BANCO =====
/**
 * Busca todos os endereços cadastrados pelo usuário.
 * 
 * Query SQL:
 * - SELECT: Todos os campos da tabela EnderecoEntrega
 * - WHERE UsuId: Filtra apenas endereços do usuário logado (segurança)
 * - ORDER BY DESC: Mais recentes primeiro (último cadastrado aparece no topo)
 * 
 * Campos retornados:
 * - EndEntId: ID único do endereço (usado para seleção no checkout)
 * - EndEntRef: Referência/apelido (ex: "Casa", "Trabalho", "Casa dos Pais")
 * - EndEntRua: Logradouro completo
 * - EndEntCep: CEP formatado ou apenas dígitos
 * - EndEntNum: Número do imóvel
 * - EndEntBairro: Bairro/distrito
 * - EndEntCid: Cidade/município
 * - EndEntEst: Estado (UF - 2 caracteres)
 * - EndEntComple: Complemento (apartamento, bloco, ponto de referência)
 */
$stmt = $conn->prepare(
    'SELECT EndEntId, EndEntRef, EndEntRua, EndEntCep, EndEntNum, EndEntBairro, EndEntCid, EndEntEst, EndEntComple
     FROM EnderecoEntrega
     WHERE UsuId = ?
     ORDER BY EndEntId DESC'
);

// ===== PROCESSAMENTO DA CONSULTA =====
/**
 * Executa query e processa resultados com tratamento de erros robusto.
 */
if ($stmt) {
    // Vincula ID do usuário como parâmetro (previne SQL injection)
    $stmt->bind_param('i', $userId);
    
    // Executa query e verifica sucesso
    if ($stmt->execute()) {
        // Obtém conjunto de resultados
        $resultado = $stmt->get_result();
        
        /**
         * Itera sobre cada endereço encontrado e formata para JSON.
         * 
         * Formatação:
         * - Converte tipos explicitamente (int, string)
         * - Usa ?? '' para campos opcionais (complemento)
         * - Estrutura amigável para JavaScript front-end
         */
        while ($row = $resultado->fetch_assoc()) {
            $enderecos[] = [
                'id' => (int) $row['EndEntId'], // ID para seleção no checkout
                'referencia' => (string) $row['EndEntRef'], // "Casa", "Trabalho"
                'rua' => (string) $row['EndEntRua'], // Logradouro
                'cep' => (string) $row['EndEntCep'], // CEP para cálculo de frete
                'numero' => (string) $row['EndEntNum'], // Número do imóvel
                'bairro' => (string) $row['EndEntBairro'], // Bairro
                'cidade' => (string) $row['EndEntCid'], // Cidade
                'estado' => (string) $row['EndEntEst'], // UF (SP, RJ, MG...)
                'complemento' => (string) ($row['EndEntComple'] ?? ''), // Opcional
            ];
        }
        
        // Libera memória do resultado
        $resultado->free();
    } else {
        // Erro na execução da query (raro, problemas de conexão)
        $stmt->close();
        http_response_code(500); // Internal Server Error
        echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível carregar seus endereços.']);
        exit;
    }
    
    // Libera recursos do statement
    $stmt->close();
} else {
    // Erro na preparação do statement (sintaxe SQL ou conexão)
    http_response_code(500); // Internal Server Error
    echo json_encode(['sucesso' => false, 'mensagem' => 'Falha ao preparar a consulta de endereços.']);
    exit;
}

// ===== RESPOSTA DE SUCESSO =====
/**
 * Retorna lista de endereços (pode ser vazia se usuário não tem endereços).
 * 
 * Status 200 OK mesmo com array vazio:
 * - Requisição bem-sucedida
 * - Usuário pode não ter endereços cadastrados
 * - Front-end deve exibir mensagem "Nenhum endereço cadastrado"
 * 
 * JSON final:
 * {
 *   "sucesso": true,
 *   "enderecos": [...]  // Array com 0 ou mais endereços
 * }
 */
echo json_encode([
    'sucesso' => true,
    'enderecos' => $enderecos,
]);
