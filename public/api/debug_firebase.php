<?php
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json');

$path = resolve_firebase_credentials_path();
$serverTime = date('Y-m-d H:i:s');
$timezone = date_default_timezone_get();

echo json_encode([
    'resolved_path' => $path,
    'file_exists' => $path ? file_exists($path) : false,
    'server_time' => $serverTime,
    'timezone' => $timezone,
    'php_version' => phpversion(),
    'candidates_checked' => [
        'env' => getenv('FIREBASE_CREDENTIALS'),
        'root_default' => project_root_path() . '/imperium-0001-firebase-adminsdk-fbsvc-ffc86182cf.json',
        'storage_default' => project_root_path() . '/storage/credentials/imperium-0001-firebase-adminsdk-fbsvc-ffc86182cf.json',
        'storage_generic' => project_root_path() . '/storage/credentials/firebase-service-account.json',
    ]
], JSON_PRETTY_PRINT);
