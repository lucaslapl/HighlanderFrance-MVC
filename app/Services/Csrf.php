<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Protection CSRF : token par session, vérifié sur les requêtes mutantes.
 * Le token est stocké uniquement côté serveur (session) et comparé en
 * temps constant (hash_equals).
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Renvoie (ou génère) le token CSRF de la session courante.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Champ <input type="hidden"> à insérer dans les formulaires POST.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    /**
     * Vérifie le token reçu (POST/formulaire ou header X-CSRF-Token).
     */
    public static function verify(?string $token): bool
    {
        $expected = $_SESSION[self::SESSION_KEY] ?? '';

        if ($expected === '' || $token === null || $token === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    /**
     * Régénère le token (à appeler après connexion/déconnexion).
     */
    public static function regenerate(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        self::token();
    }
}
