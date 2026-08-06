<?php

declare(strict_types=1);

namespace App\Core;

final class Database
{
    private static ?\PDO $pdo = null;

    public static function connection(): \PDO
    {
        if (self::$pdo === null) {
            if (!is_file(DB_PATH)) {
                throw new \RuntimeException('Base de données introuvable : ' . DB_PATH);
            }

            self::$pdo = new \PDO('sqlite:' . DB_PATH);
            self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            self::migrate();
        }

        return self::$pdo;
    }

    /**
     * Schéma de la base (extrait de l'ancien _inc/config.php).
     * Les autres tables (players_info, player_stats, etf2l_matches,
     * processed_logs…) sont créées par les scripts de synchronisation.
     */
    private static function migrate(): void
    {
        $db = self::$pdo;

        // Table de blacklist des logs logs.tf
        $db->exec("CREATE TABLE IF NOT EXISTS log_blacklist (
            log_id     INTEGER PRIMARY KEY,
            reason     TEXT,
            added_by   TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Migration : IDs historiquement codés en dur, injectés une seule fois
        $db->exec("INSERT OR IGNORE INTO log_blacklist (log_id, added_by) VALUES
            (4040598, 'legacy'),
            (4062936, 'legacy'),
            (4062933, 'legacy'),
            (4062917, 'legacy'),
            (4062908, 'legacy'),
            (4062900, 'legacy'),
            (4062895, 'legacy')");

        // Cache des durées de logs logs.tf (page admin des logs de matchs)
        $db->exec("CREATE TABLE IF NOT EXISTS log_length_cache (
            log_id INTEGER PRIMARY KEY,
            length INTEGER
        )");

        // Cache des dates de logs logs.tf (graphiques du dashboard admin)
        $db->exec("CREATE TABLE IF NOT EXISTS log_dates (
            log_id INTEGER PRIMARY KEY,
            date   INTEGER
        )");

        $db->exec("CREATE TABLE IF NOT EXISTS player_matches (
            steamid            TEXT,
            match_id           INTEGER,
            map_name           TEXT,
            class_played       TEXT,
            game_mode          TEXT DEFAULT '9v9',
            dmg                INTEGER DEFAULT 0,
            kills              INTEGER DEFAULT 0,
            deaths             INTEGER DEFAULT 0,
            assists            INTEGER DEFAULT 0,
            suicides           INTEGER DEFAULT 0,
            heal               INTEGER DEFAULT 0,
            medkits            INTEGER DEFAULT 0,
            ubers              INTEGER DEFAULT 0,
            drops              INTEGER DEFAULT 0,
            backstabs          INTEGER DEFAULT 0,
            headshots          INTEGER DEFAULT 0,
            longest_killstreak INTEGER DEFAULT 0,
            classes_killed     TEXT DEFAULT NULL,
            PRIMARY KEY (steamid, match_id)
        )");
    }
}
