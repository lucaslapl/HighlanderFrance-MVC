<?php

declare(strict_types=1);

namespace App\Services\Crons;

use App\Services\AdminLogger;
use App\Services\SteamId;

/**
 * Génération des caches JSON de classement (leaderboard) (generate_json.php).
 */
final class GenerateJsonService
{
    private const SCRIPT_NAME = 'generate_json.php';

    private const HISTORY_FILE = DATA_DIR . '/log_generate_json.txt';

    private const MIN_MATCHES = 5;

    public function __construct(private readonly \PDO $db)
    {
    }

    public function run(): string
    {
        $logToken = AdminLogger::log(self::SCRIPT_NAME);

        $dapmAvailable = $this->columnExists('length') && $this->columnExists('dapm');

        $modes = ['6s', '9v9'];
        $updatedModes = [];

        $categories = [
            'matches' => [
                'value_key' => 'count',
                'suffix'    => '',
                'sql'       => "SELECT COALESCE(p.display_name, p.name) AS name,
                                       p.avatar, p.steamid, s.count AS value
                                FROM player_stats s
                                JOIN players_info p ON s.steamid = p.steamid
                                WHERE s.game_mode = ?
                                ORDER BY s.count DESC LIMIT 18",
            ],
            'kills' => [
                'value_key' => 'value',
                'suffix'    => '_kills',
                'sql'       => "SELECT COALESCE(p.display_name, p.name) AS name,
                                       p.avatar, p.steamid, SUM(pm.kills) AS value
                                FROM player_matches pm
                                JOIN players_info p ON p.steamid = pm.steamid
                                WHERE pm.game_mode = ? AND p.created_at IS NOT NULL
                                GROUP BY pm.steamid
                                HAVING COUNT(*) >= " . self::MIN_MATCHES . "
                                ORDER BY value DESC LIMIT 18",
            ],
            'heal' => [
                'value_key' => 'value',
                'suffix'    => '_heal',
                'sql'       => "SELECT COALESCE(p.display_name, p.name) AS name,
                                       p.avatar, p.steamid, SUM(pm.heal) AS value
                                FROM player_matches pm
                                JOIN players_info p ON p.steamid = pm.steamid
                                WHERE pm.game_mode = ? AND p.created_at IS NOT NULL
                                GROUP BY pm.steamid
                                HAVING COUNT(*) >= " . self::MIN_MATCHES . "
                                ORDER BY value DESC LIMIT 18",
            ],
            'dpm' => [
                'value_key' => 'value',
                'suffix'    => '_dpm',
                'sql'       => "SELECT COALESCE(p.display_name, p.name) AS name,
                                       p.avatar, p.steamid,
                                       AVG(CASE WHEN pm.length > 0 THEN pm.dapm END) AS value
                                FROM player_matches pm
                                JOIN players_info p ON p.steamid = pm.steamid
                                WHERE pm.game_mode = ? AND p.created_at IS NOT NULL
                                GROUP BY pm.steamid
                                HAVING COUNT(*) >= " . self::MIN_MATCHES . " AND value IS NOT NULL
                                ORDER BY value DESC LIMIT 18",
            ],
        ];

        if (!$dapmAvailable) {
            unset($categories['dpm']);
        }

        foreach ($modes as $mode) {
            foreach ($categories as $category) {
                $stmt = $this->db->prepare($category['sql']);
                $stmt->execute([$mode]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $finalResults = [];
                foreach ($rows as $row) {
                    $finalResults[] = [
                        'name' => $row['name'],
                        'avatar' => $row['avatar'],
                        'steamid' => SteamId::toSteamId64((string)$row['steamid']),
                        $category['value_key'] => $row['value'],
                    ];
                }

                $filePath = DATA_DIR . '/leaderboard_cache_' . $mode . $category['suffix'] . '.json';
                $writeResult = file_put_contents($filePath, json_encode($finalResults), LOCK_EX);

                if ($writeResult === false) {
                    throw new \RuntimeException("Impossible d'écrire le fichier de cache JSON pour le mode : " . $mode);
                }

                $updatedModes[] = $mode . $category['suffix'];
            }
        }

        $statusMsg = 'SUCCESS (Classements mis à jour : ' . implode(', ', $updatedModes) . ')';
        AdminLogger::log(self::SCRIPT_NAME, $logToken, $statusMsg);

        file_put_contents(self::HISTORY_FILE, date('Y-m-d H:i:s') . " OK\n", FILE_APPEND);

        return 'Cache mis à jour avec succès.';
    }

    private function columnExists(string $column): bool
    {
        $cols = [];
        foreach ($this->db->query('PRAGMA table_info(player_matches)')->fetchAll(\PDO::FETCH_ASSOC) as $c) {
            $cols[$c['name']] = true;
        }

        return isset($cols[$column]);
    }
}
