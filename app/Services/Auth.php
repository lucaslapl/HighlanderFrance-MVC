<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class Auth
{
    public static function steamId64(): ?string
    {
        return $_SESSION['steamid'] ?? null;
    }

    public static function isLoggedIn(): bool
    {
        return self::steamId64() !== null;
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['is_admin'] ?? false) === true;
    }

    /**
     * Bloque immédiatement l'accès si le visiteur n'est pas un administrateur authentifié.
     * Répond en JSON (403) pour les endpoints API.
     */
    public static function requireAdmin(): void
    {
        if (self::isAdmin() && self::steamId64() !== null) {
            return;
        }

        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Accès refusé.']);
        exit;
    }

    /**
     * Profil complet du joueur connecté (table players_info), ou null.
     */
    public static function user(): ?array
    {
        $steamid64 = self::steamId64();

        if ($steamid64 === null) {
            return null;
        }

        $stmt = Database::connection()->prepare('SELECT * FROM players_info WHERE steamid = ?');
        $stmt->execute([SteamId::toSteamId3($steamid64)]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $user ?: null;
    }
}
