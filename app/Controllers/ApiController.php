<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\LogsTfApi;
use App\Services\SteamId;

final class ApiController extends Controller
{
    /**
     * Statistiques globales de la page d'accueil.
     * Pendant la migration, on lit le cache généré par les crons de l'ancien site.
     */
    public function indexStats(): void
    {
        $cacheFile = DATA_DIR . '/cache_hlfr_stats.json';

        $stats = null;
        if (is_file($cacheFile)) {
            $stats = json_decode((string)file_get_contents($cacheFile), true);
        }

        $this->json(['data' => $stats]);
    }

    /**
     * Logs "Highlander France" (Match Stats), hors blacklist.
     */
    public function logs(): void
    {
        $logs = (new LogsTfApi(Database::connection()))->filteredLogs();

        $this->json($logs);
    }

    /**
     * Cache du leaderboard (généré par les crons de l'ancien site).
     * GET /api/leaderboard?mode=9v9|6s&category=matches|kills|heal|dpm
     */
    public function leaderboard(): void
    {
        $mode = (string)($this->request?->get('mode', '9v9') ?? '9v9');
        $category = (string)($this->request?->get('category', 'matches') ?? 'matches');

        if (!in_array($mode, ['9v9', '6s'], true) || !in_array($category, ['matches', 'kills', 'heal', 'dpm'], true)) {
            $this->json(['error' => 'Paramètres invalides.'], 400);

            return;
        }

        $suffix = $category === 'matches' ? '' : '_' . $category;
        $file = DATA_DIR . '/leaderboard_cache_' . $mode . $suffix . '.json';

        if (!is_file($file)) {
            $this->json(['error' => 'Cache du leaderboard introuvable.'], 404);

            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo (string)file_get_contents($file);
    }

    /**
     * Recherche de joueurs (Hall of Fame).
     * GET /api/search-players?q=...
     */
    public function searchPlayers(): void
    {
        $query = trim((string)($this->request?->get('q', '') ?? ''));

        if (mb_strlen($query) < 2) {
            $this->json([]);

            return;
        }

        $stmt = Database::connection()->prepare("
            SELECT steamid, name, display_name, avatar
            FROM players_info
            WHERE name LIKE :q OR display_name LIKE :q
            ORDER BY display_name ASC, name ASC
            LIMIT 10
        ");
        $stmt->execute([':q' => '%' . $query . '%']);

        $results = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $player) {
            $results[] = [
                'steamid' => SteamId::toSteamId64($player['steamid']),
                'name' => !empty($player['display_name']) ? $player['display_name'] : $player['name'],
                'avatar' => $player['avatar'],
            ];
        }

        $this->json($results);
    }
}
