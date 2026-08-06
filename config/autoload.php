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
