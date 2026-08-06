<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    public static function run(): void
    {
        date_default_timezone_set('Europe/Paris');

        if (php_sapi_name() !== 'cli') {
            self::startSession();
        }

        /** @var Router $router */
        $router = require APP_ROOT . '/config/routes.php';
        $router->dispatch(new Request());
    }

    private static function startSession(): void
    {
        if (!is_dir(SESSION_SAVE_PATH)) {
            mkdir(SESSION_SAVE_PATH, 0755, true);
        }

        ini_set('session.save_path', SESSION_SAVE_PATH);
        ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');

        session_name('HLFR_SESSION');

        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
