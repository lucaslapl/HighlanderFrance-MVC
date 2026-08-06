<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Client de l'API Steam (profils joueurs).
 */
final class SteamApi
{
    private function key(): string
    {
        return (string)env('STEAM_API_KEY', '');
    }

    private function request(string $url): ?array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => CURL_VERIFY_SSL,
            CURLOPT_USERAGENT      => 'Highlander France Bot/1.0',
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            curl_close($ch);

            return null;
        }
        curl_close($ch);

        $data = json_decode((string)$response, true);

        return is_array($data) ? $data : null;
    }

    /**
     * Met à jour le nom + l'avatar d'un joueur déjà présent en base (par SteamID3).
     * Utilisé par le dashboard pour rafraîchir le profil.
     */
    public function syncProfile(string $steamid3): bool
    {
        if ($this->key() === '') {
            return false;
        }

        $steamid64 = SteamId::toSteamId64($steamid3);
        if ($steamid64 === null) {
            return false;
        }

        $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=' . $this->key() . '&steamids=' . $steamid64;
        $data = $this->request($url);
        $player = $data['response']['players'][0] ?? null;

        if ($player === null) {
            return false;
        }

        $stmt = Database::connection()->prepare('UPDATE players_info SET name = ?, avatar = ?, last_updated = ? WHERE steamid = ?');
        $stmt->execute([$player['personaname'], $player['avatarfull'], time(), $steamid3]);

        return true;
    }

    /**
     * Met à jour le nom/l'avatar, et le display_name s'il est encore "Nouveau Joueur".
     * Utilisé après la connexion Steam (callback), comptes existants ou nouveaux.
     */
    public function syncOrCreatePlayer(string $steamid64): bool
    {
        if ($this->key() === '') {
            return false;
        }

        $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=' . $this->key() . '&steamids=' . $steamid64;
        $data = $this->request($url);
        $player = $data['response']['players'][0] ?? null;

        if ($player === null) {
            return false;
        }

        $steamName = $player['personaname'] ?? 'Joueur Steam';
        $steamAvatar = $player['avatarfull'] ?? '';
        $steamid3 = SteamId::toSteamId3($steamid64);

        $stmt = Database::connection()->prepare("
            UPDATE players_info
            SET name = ?,
                avatar = ?,
                display_name = CASE
                    WHEN display_name = 'Nouveau Joueur' OR display_name IS NULL OR display_name = '' THEN ?
                    ELSE display_name
                END
            WHERE steamid = ?
        ");
        $stmt->execute([$steamName, $steamAvatar, $steamName, $steamid3]);

        return true;
    }
}
