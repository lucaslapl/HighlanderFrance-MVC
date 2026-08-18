<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    public static function run(): void
    {
        date_default_timezone_set('Europe/Paris');

        // Capture toute erreur non gérée (dès le boot, avant le dispatch) et
        // répond une page 500 propre, sans exposer de détails en production.
        set_exception_handler([self::class, 'handleUncaught']);

        if (php_sapi_name() !== 'cli') {
            self::startSession();
        }

        try {
            /** @var Router $router */
            $router = require APP_ROOT . '/config/routes.php';
            $router->dispatch(new Request());
        } catch (\Throwable $e) {
            self::handleUncaught($e);
        }
    }

    /**
     * Handler global des exceptions non rattrapées.
     */
    public static function handleUncaught(\Throwable $e): void
    {
        error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

        if (php_sapi_name() === 'cli') {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            exit(1);
        }

        http_response_code(500);
        $controller = new \App\Controllers\ErrorController();
        $controller->handle(500, $e->getMessage());
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
        // Rejette les IDs de session fournis par le client qui n'existent pas
        // côté serveur (anti fixation, en complément de session_regenerate_id).
        ini_set('session.use_strict_mode', '1');

        session_name('HLFR_SESSION');

        // Cookie de session Secure uniquement en HTTPS : en HTTP (WAMP/dev)
        // le navigateur refuse de renvoyer un cookie Secure, ce qui casserait le login.
        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
