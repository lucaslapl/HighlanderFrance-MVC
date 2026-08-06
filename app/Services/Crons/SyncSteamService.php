<?php

declare(strict_types=1);

namespace App\Services\Crons;

use App\Services\AdminLogger;
use App\Services\JsonClient;
use App\Services\SteamId;

/**
 * Synchronisation des profils Steam manquants (sync_steam.php).
 */
final class SyncSteamService
{
    private const SCRIPT_NAME = 'sync_steam.php';

    private const HISTORY_FILE = DATA_DIR . '/log_sync_steam.txt';

    public function __construct(private readonly \PDO $db)
    {
    }

    public function run(): string
    {
        $logToken = AdminLogger::log(self::SCRIPT_NAME);

        $apiKey = (string)env('STEAM_API_KEY', '');
        if ($apiKey === '') {
            throw new \RuntimeException("Clé d'API Steam manquante dans le fichier .env");
        }

        $missing = $this->db->query("SELECT DISTINCT s.steamid
                                     FROM player_stats s
                                     LEFT JOIN players_info p ON s.steamid = p.steamid
                                     WHERE p.steamid IS NULL")->fetchAll(\PDO::FETCH_COLUMN);

        if ($missing === []) {
            $this->logMsg('Aucun nouveau profil à traiter.');
            AdminLogger::log(self::SCRIPT_NAME, $logToken, 'SUCCESS (Aucun profil à synchroniser)');

            return "Aucun nouveau profil à traiter. \n";
        }

        $this->logMsg("Nombre d'IDs à traiter : " . count($missing));

        $chunks = array_chunk($missing, 100);
        $profilesAdded = 0;

        foreach ($chunks as $chunk) {
            $ids64 = [];
            foreach ($chunk as $steamid3) {
                $converted = SteamId::toSteamId64((string)$steamid3);
                if ($converted !== null) {
                    $ids64[] = $converted;
                }
            }

            $idsParam = implode(',', $ids64);
            $url = 'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=' . $apiKey . '&steamids=' . $idsParam;

            $data = JsonClient::get($url, 15);
            if ($data === null) {
                $this->logMsg("Erreur API Steam pour chunk : $idsParam");
                continue;
            }

            if (!isset($data['response']['players'])) {
                $this->logMsg("Réponse Steam invalide pour chunk : $idsParam");
                continue;
            }

            $insert = $this->db->prepare('INSERT INTO players_info (steamid, name, avatar, last_updated) VALUES (?, ?, ?, ?)');
            foreach ($data['response']['players'] as $p) {
                $originalId = SteamId::toSteamId3((string)$p['steamid']);

                $insert->execute([$originalId, (string)($p['personaname'] ?? ''), (string)($p['avatarfull'] ?? ''), time()]);

                $this->logMsg('Ajouté : ' . ($p['personaname'] ?? ''));
                $profilesAdded++;
            }

            sleep(1);
        }

        $this->logMsg('Synchronisation terminée avec succès.');

        $statusMsg = 'SUCCESS (' . $profilesAdded . ' profils Steam importés)';
        AdminLogger::log(self::SCRIPT_NAME, $logToken, $statusMsg);

        return 'Synchronisation terminée avec succès.';
    }

    private function logMsg(string $msg): void
    {
        file_put_contents(self::HISTORY_FILE, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
    }
}
