<?php

declare(strict_types=1);

/**
 * Autoloader PSR-4 maison (sans Composer).
 * Mappe le namespace App\ vers le dossier app/.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

/**
 * URL de base du site (sans slash final).
 * Priorité : variable d'environnement APP_URL, puis hôte de la requête.
 */
function site_url(): string
{
    $configured = (string)env('APP_URL', '');

    if ($configured !== '') {
        return rtrim($configured, '/');
    }

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');

    if ($host === '') {
        return APP_BASE_URL;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    return ($https ? 'https' : 'http') . '://' . $host;
}

/**
 * URL complète de la page courante (chemin + query string).
 */
function current_url(): string
{
    return site_url() . (string)($_SERVER['REQUEST_URI'] ?? '/');
}

/**
 * URL canonique de la page courante (sans query string ni fragment).
 */
function canonical_url(): string
{
    $path = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string)parse_url($path, PHP_URL_PATH);

    return site_url() . ($path !== '' ? $path : '/');
}

/**
 * Aide au rendu des vues : échappe une chaîne pour du HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Rend une vue partielle (retourne le HTML, ne l'affiche pas).
 */
function partial(string $template, array $data = []): string
{
    return \App\Core\View::partial($template, $data);
}
