<?php
/**
 * Arquivo: public/bootstrap.php
 * Propósito: Bootstrap específico para scripts do diretório public/
 * 
 * Este arquivo serve como ponto de entrada para scripts localizados em:
 * - public/api/ (endpoints REST)
 * - public/pages/ (páginas da aplicação)
 * 
 * Resolve o caminho relativo correto para incluir o bootstrap principal,
 * independente da profundidade do subdiretório onde está o script que o chama.
 * 
 * Uso típico:
 * require_once dirname(__DIR__) . '/bootstrap.php'; // De dentro de public/api/
 * require_once __DIR__ . '/bootstrap.php'; // De dentro de public/ diretamente
 * 
 * Segurança e Boas Práticas:
 * - Este arquivo não deve produzir saída (echo/print) para evitar problemas de headers.
 * - Mantenha apenas inicialização e includes; lógica de negócio deve ficar em controllers/pages.
 * - Use caminhos relativos calculados via dirname para evitar acoplamento a ambientes locais.
 */

// ===== ATIVAÇÃO DE STRICT TYPES =====
/**
 * Habilita verificação estrita de tipos para este arquivo e scripts que o incluem.
 * 
 * Garante que passagem de parâmetros respeite os type hints declarados,
 * evitando conversões implícitas que podem causar bugs silenciosos.
 */
declare(strict_types=1);

// ===== INCLUSÃO DO BOOTSTRAP PRINCIPAL =====
/**
 * Inclui o bootstrap principal da aplicação localizado em bootstrap/app.php
 * 
 * Cálculo do caminho:
 * - dirname(__DIR__) = diretório pai de public/ (root do projeto)
 * - '/bootstrap/app.php' = caminho para o bootstrap principal
 * 
 * Resultado: C:/laragon/www/IMPERIUM/bootstrap/app.php
 * 
 * Após esta linha, todas as dependências, helpers e conexão DB estão disponíveis:
 * - $conn (objeto MySQLi)
 * - asset_path(), url_path(), site_path() (funções helpers)
 * - Classes do Composer (Firebase, MercadoPago, etc)
 * - Timezone configurado
	* 
	* Observação:
	* - Se você mover o diretório `public/`, ajuste apenas este arquivo.
	* - Scripts em `public/api/` e `public/pages/` devem incluir ESTE bootstrap, não o app.php diretamente.
	* - Isso garante padronização, evita caminhos quebrados e facilita manutenção.
 */
require_once dirname(__DIR__) . '/bootstrap/app.php';
