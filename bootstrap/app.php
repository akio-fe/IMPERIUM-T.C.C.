<?php
/**
 * Arquivo: bootstrap/app.php
 * Propósito: Bootstrap principal da aplicação IMPERIUM
 * 
 * Este é o ponto de inicialização base da aplicação. Deve ser incluído
 * no topo de todos os scripts PHP do projeto para garantir que:
 * - Dependências do Composer estejam carregadas
 * - Funções auxiliares globais estejam disponíveis
 * - Conexão com banco de dados esteja estabelecida
 * - Timezone esteja configurado corretamente
 * 
 * Serve como camada base até que um sistema de roteamento completo seja implementado.
 */

// ===== ATIVAÇÃO DE STRICT TYPES =====
/**
 * Ativa verificação estrita de tipos no PHP 7+
 * 
 * Com strict_types habilitado:
 * - Funções com type hints rejeitam tipos incompatíveis
 * - int, float, string, bool não são convertidos automaticamente
 * - Melhora segurança e previsibilidade do código
 * 
 * Exemplo:
 * function soma(int $a, int $b): int { return $a + $b; }
 * soma(5, "10"); // Sem strict: converte "10" para int
 *                // Com strict: TypeError exception
 */
declare(strict_types=1);

// ===== DEFINIÇÃO DO CAMINHO RAIZ =====
/**
 * Obtém o caminho absoluto do diretório raiz do projeto.
 * 
 * __DIR__ retorna o diretório onde este arquivo está (bootstrap/)
 * dirname(__DIR__) sobe um nível para obter o root (IMPERIUM/)
 * 
 * @var string $rootPath Caminho absoluto do root (ex: C:/laragon/www/IMPERIUM)
 */
$rootPath = dirname(__DIR__);

// ===== CARREGAMENTO DO AUTOLOADER DO COMPOSER =====
/**
 * Inicializa o autoloader do Composer para carregar dependências automaticamente.
 * 
 * O Composer gerencia bibliotecas de terceiros como:
 * - Firebase SDK (autenticação)
 * - Mercado Pago SDK (pagamentos)
 * - GuzzleHttp (requisições HTTP)
 * - Monolog (logging)
 * 
 * O autoloader permite usar classes dessas bibliotecas sem require manual:
 * use Kreait\Firebase\Factory; // Carregado automaticamente
 * 
 * @var string $autoloadPath Caminho para o arquivo autoload.php do Composer
 */
$autoloadPath = $rootPath . '/vendor/autoload.php';

// Verifica se o Composer foi instalado antes de tentar carregar
// Se vendor/ não existir, as bibliotecas de terceiros não estarão disponíveis
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// ===== CARREGAMENTO DE FUNÇÕES AUXILIARES =====
/**
 * Inclui arquivo com funções globais da aplicação.
 * 
 * helpers.php define funções como:
 * - asset_path(): Gera URLs para CSS, JS, imagens
 * - url_path(): Gera URLs para páginas
 * - site_path(): Resolve caminhos considerando subdiretórios
 * - resolve_firebase_credentials_path(): Localiza credenciais do Firebase
 * 
 * Essas funções são essenciais para o funcionamento correto em diferentes ambientes.
 */
require_once $rootPath . '/app/Support/helpers.php';

// ===== CONEXÃO COM BANCO DE DADOS =====
/**
 * Estabelece conexão MySQLi com o banco de dados.
 * 
 * Após esta linha, a variável global $conn está disponível em toda a aplicação:
 * $stmt = $conn->prepare("SELECT * FROM usuario WHERE UsuEmail = ?");
 * 
 * A conexão é mantida durante toda a execução do script e fechada automaticamente
 * ao final (através do destrutor MySQLi).
 */
require_once $rootPath . '/app/Database/connection.php';

// ===== CONFIGURAÇÃO DE TIMEZONE =====
/**
 * Define o fuso horário padrão para todas as operações de data/hora.
 * 
 * America/Sao_Paulo corresponde a:
 * - UTC-3 (horário de Brasília)
 * - Horário de verão quando aplicável
 * 
 * Afeta funções como:
 * - date(), time()
 * - DateTime objects
 * - Timestamps em logs
 * - NOW() do MySQL (se a conexão usar timezone do PHP)
 * 
 * Garante que datas de pedidos, logs e timestamps sejam consistentes
 * com o fuso horário do Brasil.
 */
date_default_timezone_set('America/Sao_Paulo');
