<?php
/**
 * Arquivo: helpers.php
 * Propósito: Funções auxiliares globais para geração de URLs, caminhos e configurações.
 * 
 * Principais responsabilidades:
 * - Detecção automática do prefixo base da URL considerando subdiretórios
 * - Normalização de caminhos de assets (CSS, JS, imagens)
 * - Resolução de credenciais Firebase
 * - Suporte a ambientes locais e produção sem configuração manual
 */

/**
 * Detecta o prefixo da URL base do projeto automaticamente.
 * 
 * Essencial para aplicações servidas em subdiretórios (ex: http://localhost/IMPERIUM).
 * Analisa $_SERVER['SCRIPT_NAME'] para identificar o caminho correto.
 * 
 * Lógica:
 * 1. Se encontrar '/public/' no caminho, usa tudo que vem antes como prefixo
 * 2. Caso contrário, usa o diretório pai do script atual
 * 3. Cache estático para evitar recálculos em múltiplas chamadas
 * 
 * @return string Prefixo da URL base (ex: '/IMPERIUM' ou '' para raiz)
 */
function base_url_prefix(): string
{
    // Cache estático: calcula apenas uma vez por requisição
    static $prefix = null;

    if ($prefix !== null) {
        return $prefix;
    }

    // Normaliza barras invertidas para Unix-style
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');

    // Tenta localizar '/public/' no caminho do script
    $publicPos = stripos($script, '/public/');
    if ($publicPos !== false) {
        // Se encontrou, o prefixo é tudo antes de '/public/'
        // Ex: /IMPERIUM/public/index.php → /IMPERIUM
        $prefix = rtrim(substr($script, 0, $publicPos), '/');

        return $prefix;
    }

    // Fallback: usa o diretório do script atual
    $dir = str_replace('\\', '/', dirname($script ?: '/'));
    // Se for raiz ou diretório atual, considera vazio
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $dir = '';
    }

    $prefix = rtrim($dir, '/');

    return $prefix;
}

/**
 * Gera URLs completas ou relativas para recursos do site.
 * 
 * Constrói URLs considerando:
 * - Prefixo de subdiretório (detectado automaticamente)
 * - Protocolo HTTP/HTTPS (baseado em $_SERVER)
 * - Host atual
 * - Caminhos relativos ou absolutos
 * 
 * Suporta modo CLI (sem HTTP_HOST) retornando apenas path relativo.
 * 
 * @param string $path Caminho relativo ao root do projeto (ex: 'public/index.php')
 * @return string URL completa (http://host/IMPERIUM/path) ou relativa (/IMPERIUM/path)
 * 
 * @example site_path('public/pages/shop/index.php') → 'http://localhost/IMPERIUM/public/pages/shop/index.php'
 * @example site_path('') → 'http://localhost/IMPERIUM/'
 */
function site_path(string $path = ''): string
{
    // Normaliza o caminho fornecido: remove barras iniciais e inverte barras do Windows
    $clean = ltrim(str_replace('\\', '/', $path), '/');
    // Obtém o prefixo base (ex: '/IMPERIUM' ou '')
    $prefix = base_url_prefix();

    // Reservado para forçar uma URL base específica (atualmente não usado)
    $forcedBase = '';

    // Constrói o caminho relativo com o prefixo
    if ($clean === '') {
        // Caminho vazio: retorna apenas o prefixo com barra final
        $relative = $prefix === '' ? '/' : $prefix . '/';
    } else {
        // Caminho fornecido: combina prefixo + path
        $relative = ($prefix === '' ? '' : $prefix) . '/' . $clean;
    }

    // Se houver base forçada (configuração manual), usa ela
    if ($forcedBase !== '') {
        $normalizedBase = rtrim(str_replace('\\', '/', $forcedBase), '/');
        return $normalizedBase . $relative;
    }

    // Tenta obter o host da requisição
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        // Modo CLI ou sem host: retorna apenas path relativo
        return $relative;
    }

    // Detecta o protocolo correto (HTTP ou HTTPS)
    $scheme = 'http';
    // Verifica variável HTTPS (Apache/Nginx)
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        $scheme = 'https';
    } elseif (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
        // Fallback: porta 443 indica HTTPS
        $scheme = 'https';
    }

    // Retorna URL completa: protocolo + host + caminho relativo
    return rtrim($scheme . '://' . $host, '/') . $relative;
}

/**
 * Retorna o caminho absoluto do diretório raiz do projeto.
 * 
 * Calcula o root baseado na localização deste arquivo:
 * - helpers.php está em: /app/Support/
 * - Root do projeto: 2 níveis acima
 * 
 * Resultado cacheado estaticamente para performance.
 * 
 * @return string Caminho absoluto do root (ex: 'C:/laragon/www/IMPERIUM')
 * 
 * @example project_root_path() → 'C:/laragon/www/IMPERIUM'
 */
function project_root_path(): string
{
    // Cache estático: calcula apenas uma vez
    static $root = null;

    if ($root === null) {
        // __DIR__ aponta para /app/Support, sobe 2 níveis para chegar ao root
        $root = dirname(__DIR__, 2);
    }

    return $root;
}

/**
 * Localiza automaticamente o arquivo JSON de credenciais do Firebase.
 * 
 * Estratégia de busca em ordem de prioridade:
 * 1. Variáveis de ambiente (FIREBASE_CREDENTIALS ou GOOGLE_APPLICATION_CREDENTIALS)
 * 2. Diretório pai do projeto (../ )
 * 3. Root do projeto
 * 4. storage/credentials/ (recomendado para produção)
 * 5. config/ (alternativa)
 * 
 * Suporta múltiplos nomes de arquivo:
 * - Nome específico do projeto (imperium-0001-firebase...)
 * - Nome genérico (firebase-service-account.json)
 * 
 * Resultado cacheado estaticamente: primeira busca encontra, demais retornam cache.
 * 
 * @return string|null Caminho absoluto do arquivo JSON ou null se não encontrado
 * 
 * @example resolve_firebase_credentials_path() → 'C:/laragon/www/IMPERIUM/storage/credentials/firebase.json'
 */
function resolve_firebase_credentials_path(): ?string
{
    // Sistema de cache: evita múltiplas buscas no filesystem
    static $cached = false;
    static $resolved = null;

    if ($cached) {
        return $resolved;
    }

    // 1ª prioridade: variáveis de ambiente (padrão Google Cloud)
    $envKeys = ['FIREBASE_CREDENTIALS', 'GOOGLE_APPLICATION_CREDENTIALS'];
    $candidates = [];

    foreach ($envKeys as $key) {
        // Tenta getenv(), $_SERVER e $_ENV (compatibilidade)
        $value = getenv($key) ?: ($_SERVER[$key] ?? $_ENV[$key] ?? null);
        if ($value) {
            $candidates[] = $value;
        }
    }

    // 2ª prioridade: locais padrão do projeto
    $projectRoot = project_root_path();
    $defaultFile = 'imperium-0001-firebase-adminsdk-fbsvc-ffc86182cf.json';
    
    // Adiciona candidatos na ordem de busca
    $candidates[] = $projectRoot . '/../' . $defaultFile; // Diretório pai
    $candidates[] = $projectRoot . '/' . $defaultFile; // Root
    $candidates[] = $projectRoot . '/storage/credentials/' . $defaultFile; // storage/
    $candidates[] = $projectRoot . '/storage/credentials/firebase-service-account.json'; // Nome genérico
    $candidates[] = $projectRoot . '/config/firebase-service-account.json'; // config/

    // Itera candidatos até encontrar arquivo válido
    foreach ($candidates as $candidate) {
        if (!$candidate) {
            continue;
        }

        // Normaliza barras para compatibilidade cross-platform
        $normalized = str_replace('\\', '/', $candidate);
        if (is_file($normalized)) {
            // Arquivo encontrado: cacheia e retorna
            $resolved = $normalized;
            $cached = true;

            return $resolved;
        }
    }

    // Nenhum arquivo encontrado: cacheia null para evitar buscas futuras
    $cached = true;
    $resolved = null;

    return null;
}

/**
 * Normaliza caminhos de assets para apontar ao diretório reorganizado public/assets.
 * 
 * Resolve caminhos legados e relativos, convertendo-os para a estrutura moderna:
 * - img/catalog/ → public/assets/img/catalog/
 * - img/ → public/assets/img/catalog/
 * - public/images/ → public/assets/img/ui/
 * - css/ → public/assets/css/
 * - js/ → public/assets/js/
 * - Outros → public/assets/
 * 
 * Garante compatibilidade com código antigo após reorganização do projeto.
 * Funciona corretamente mesmo com projeto em subdiretório (http://localhost/IMPERIUM).
 * 
 * @param string $path Caminho relativo do asset (legado ou moderno)
 * @return string URL completa do asset normalizado
 * 
 * @example asset_path('img/catalog/tenis.png') → 'http://localhost/IMPERIUM/public/assets/img/catalog/tenis.png'
 * @example asset_path('css/style.css') → 'http://localhost/IMPERIUM/public/assets/css/style.css'
 * @example asset_path('public/assets/js/app.js') → 'http://localhost/IMPERIUM/public/assets/js/app.js'
 */
function asset_path(string $path): string
{
    // Normaliza barras e remove barra inicial
    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    if ($normalized === '') {
        // Path vazio: retorna URL base
        return site_path('');
    }

    // Regras de mapeamento de caminhos legados para nova estrutura
    
    // 1. img/catalog/ → public/assets/img/catalog/ (específico)
    if (stripos($normalized, 'img/catalog/') === 0) {
        $normalized = 'public/assets/img/catalog/' . substr($normalized, strlen('img/catalog/'));
    } 
    // 2. img/ → public/assets/img/catalog/ (genérico, assume catálogo)
    elseif (stripos($normalized, 'img/') === 0) {
        $normalized = 'public/assets/img/catalog/' . substr($normalized, 4);
    } 
    // 3. public/images/ → public/assets/img/ui/ (imagens de interface)
    elseif (stripos($normalized, 'public/images/') === 0) {
        $normalized = 'public/assets/img/ui/' . substr($normalized, strlen('public/images/'));
    } 
    // 4. css/ → public/assets/css/
    elseif (stripos($normalized, 'css/') === 0) {
        $normalized = 'public/assets/css/' . substr($normalized, 4);
    } 
    // 5. js/ → public/assets/js/
    elseif (stripos($normalized, 'js/') === 0) {
        $normalized = 'public/assets/js/' . substr($normalized, 3);
    } 
    // 6. Qualquer caminho que não comece com public/assets/ recebe o prefixo
    elseif (stripos($normalized, 'public/assets/') !== 0) {
        $normalized = 'public/assets/' . $normalized;
    }

    // Converte para URL completa usando site_path
    return site_path($normalized);
}

/**
 * Alias para site_path() - gera URLs de páginas e rotas do site.
 * 
 * Função mantida para compatibilidade com código legado e clareza semântica.
 * Use para URLs de páginas HTML/PHP, não para assets (use asset_path).
 * 
 * @param string $path Caminho relativo da página
 * @return string URL completa da página
 * 
 * @see site_path() Para detalhes da implementação
 * 
 * @example url_path('public/pages/shop/index.php') → 'http://localhost/IMPERIUM/public/pages/shop/index.php'
 * @example url_path('index.php') → 'http://localhost/IMPERIUM/index.php'
 */
function url_path(string $path): string
{
    return site_path($path);
}
