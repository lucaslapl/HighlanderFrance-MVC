<?php

declare(strict_types=1);

/**
 * Router de test pour `php -S` (le serveur intégré ne lit pas le .htaccess).
 * À SUPPRIMER en production (Apache utilise le .htaccess).
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';

$protected = ['/app', '/config', '/bin', '/_cache', '/_sessions', '/_scripts'];
foreach ($protected as $prefix) {
    if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
        http_response_code(403);
        exit('Forbidden');
    }
}

if ($path !== '/' && is_file(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/index.php';
