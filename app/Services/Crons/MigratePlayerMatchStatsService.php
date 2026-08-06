<?php

declare(strict_types=1);

namespace App\Services\Crons;

use App\Services\AdminLogger;

/**
 * Migration des colonnes de stats dans player_matches (migrate_player_match_stats.php).
 */
final class MigratePlayerMatchStatsService
{
    private const SCRIPT_NAME = 'migrate_player_match_stats.php';

    private const NEW_COLUMNS = [
        'dmg'                => 'INTEGER DEFAULT 0',
        'kills'              => 'INTEGER DEFAULT 0',
        'deaths'             => 'INTEGER DEFAULT 0',
        'assists'            => 'INTEGER DEFAULT 0',
        'suicides'           => 'INTEGER DEFAULT 0',
        'heal'               => 'INTEGER DEFAULT 0',
        'medkits'            => 'INTEGER DEFAULT 0',
        'ubers'              => 'INTEGER DEFAULT 0',
        'drops'              => 'INTEGER DEFAULT 0',
        'backstabs'          => 'INTEGER DEFAULT 0',
        'headshots'          => 'INTEGER DEFAULT 0',
        'longest_killstreak' => 'INTEGER DEFAULT 0',
        'classes_killed'     => 'TEXT DEFAULT NULL',
        'length'             => 'INTEGER DEFAULT 0',
        'dapm'               => 'INTEGER DEFAULT 0',
        'dmg_taken'          => 'INTEGER DEFAULT 0',
        'medkits_hp'         => 'INTEGER DEFAULT 0',
        'airshots'           => 'INTEGER DEFAULT 0',
        'captures'           => 'INTEGER DEFAULT 0',
        'won'                => 'INTEGER DEFAULT NULL',
    ];

    public function __construct(private readonly \PDO $db)
    {
    }

    public function run(): string
    {
        $logToken = AdminLogger::log(self::SCRIPT_NAME);

        $existing = [];
        foreach ($this->db->query('PRAGMA table_info(player_matches)')->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            $existing[$col['name']] = true;
        }

        $added = 0;
        foreach (self::NEW_COLUMNS as $name => $definition) {
            if (!isset($existing[$name])) {
                $this->db->exec("ALTER TABLE player_matches ADD COLUMN $name $definition");
                $added++;
            }
        }

        AdminLogger::log(self::SCRIPT_NAME, $logToken, "SUCCESS ($added colonnes ajoutées)");

        return "Migration terminée : $added colonnes ajoutées à player_matches.";
    }
}
