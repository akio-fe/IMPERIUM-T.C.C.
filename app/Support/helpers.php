<?php

function base_url_prefix(): string
{
    static $prefix = null;

    if ($prefix !== null) {
        return $prefix;
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');

    $publicPos = stripos($script, '/public/');
    if ($publicPos !== false) {
        $prefix = rtrim(substr($script, 0, $publicPos), '/');

        return $prefix;
    }

    $dir = str_replace('\\', '/', dirname($script ?: '/'));
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        $dir = '';
    }

    $prefix = rtrim($dir, '/');

    return $prefix;
}

function site_path(string $path = ''): string
{
    $clean = ltrim(str_replace('\\', '/', $path), '/');
    $prefix = base_url_prefix();

    if ($clean === '') {
        return $prefix === '' ? '/' : $prefix . '/';
    }

    return ($prefix === '' ? '' : $prefix) . '/' . $clean;
}

function project_root_path(): string
{
    static $root = null;

    if ($root === null) {
        $root = dirname(__DIR__, 2);
    }

    return $root;
}

function resolve_firebase_credentials_path(): ?string
{
    static $cached = false;
    static $resolved = null;

    if ($cached) {
        return $resolved;
    }

    $envKeys = ['FIREBASE_CREDENTIALS', 'GOOGLE_APPLICATION_CREDENTIALS'];
    $candidates = [];

    foreach ($envKeys as $key) {
        $value = getenv($key) ?: ($_SERVER[$key] ?? $_ENV[$key] ?? null);
        if ($value) {
            $candidates[] = $value;
        }
    }

    $projectRoot = project_root_path();
    $defaultFile = 'imperium-0001-firebase-adminsdk-fbsvc-ffc86182cf.json';
    $candidates[] = $projectRoot . '/../' . $defaultFile;
    $candidates[] = $projectRoot . '/' . $defaultFile;
    $candidates[] = $projectRoot . '/storage/credentials/' . $defaultFile;
    $candidates[] = $projectRoot . '/storage/credentials/firebase-service-account.json';
    $candidates[] = $projectRoot . '/config/firebase-service-account.json';

    foreach ($candidates as $candidate) {
        if (!$candidate) {
            continue;
        }

        $normalized = str_replace('\\', '/', $candidate);
        if (is_file($normalized)) {
            $resolved = $normalized;
            $cached = true;

            return $resolved;
        }
    }

    $cached = true;
    $resolved = null;

    return null;
}

/**
 * Normalize legacy relative asset paths so that they point to the reorganized public/assets directory
 * even when the project is served from a subdirectory (e.g., http://localhost/IMPERIUM).
 */
function asset_path(string $path): string
{
    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    if ($normalized === '') {
        return site_path('');
    }

    if (stripos($normalized, 'img/catalog/') === 0) {
        $normalized = 'public/assets/img/catalog/' . substr($normalized, strlen('img/catalog/'));
    } elseif (stripos($normalized, 'img/') === 0) {
        $normalized = 'public/assets/img/catalog/' . substr($normalized, 4);
    } elseif (stripos($normalized, 'public/images/') === 0) {
        $normalized = 'public/assets/img/ui/' . substr($normalized, strlen('public/images/'));
    } elseif (stripos($normalized, 'css/') === 0) {
        $normalized = 'public/assets/css/' . substr($normalized, 4);
    } elseif (stripos($normalized, 'js/') === 0) {
        $normalized = 'public/assets/js/' . substr($normalized, 3);
    } elseif (stripos($normalized, 'public/assets/') !== 0) {
        $normalized = 'public/assets/' . $normalized;
    }

    return site_path($normalized);
}

function url_path(string $path): string
{
    return site_path($path);
}
