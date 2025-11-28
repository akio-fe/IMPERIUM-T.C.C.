<?php
// Base bootstrap for legacy pages until a routing layer is introduced.
declare(strict_types=1);

$rootPath = dirname(__DIR__);

$autoloadPath = $rootPath . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

require_once $rootPath . '/app/Support/helpers.php';
require_once $rootPath . '/app/Database/connection.php';

date_default_timezone_set('America/Sao_Paulo');
