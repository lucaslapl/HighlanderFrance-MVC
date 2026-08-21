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

            // WAL : les lectures du site ne sont plus bloquées par les écritures
            // des scripts CRON (sync_etf2l, update_stats...) qui durent plusieurs minutes.
            self::$pdo->exec('PRAGMA journal_mode=WAL');
            self::$pdo->exec('PRAGMA busy_timeout=5000');

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

        // Table des équipes ETF2L (créée si absente, pour les rosters FR)
        $db->exec("CREATE TABLE IF NOT EXISTS etf2l_teams (
            team_id INTEGER PRIMARY KEY,
            name    TEXT,
            country TEXT,
            tag     TEXT
        )");

        // Table des joueurs ETF2L (rosters des équipes FR)
        $db->exec("CREATE TABLE IF NOT EXISTS etf2l_players (
            team_id   INTEGER,
            player_id INTEGER,
            name      TEXT,
            role      TEXT,
            country   TEXT,
            steamid64 TEXT,
            PRIMARY KEY (team_id, player_id)
        )");

        // Ajout des colonnes IDs d'équipes sur les matchs (idempotent)
        $matchCols = $db->query("PRAGMA table_info(etf2l_matches)")->fetchAll(\PDO::FETCH_ASSOC);
        $existing = array_column($matchCols, 'name');
        if (!in_array('team1_id', $existing, true)) {
            $db->exec('ALTER TABLE etf2l_matches ADD COLUMN team1_id INTEGER DEFAULT NULL');
        }
        if (!in_array('team2_id', $existing, true)) {
            $db->exec('ALTER TABLE etf2l_matches ADD COLUMN team2_id INTEGER DEFAULT NULL');
        }
        if (!in_array('maps', $existing, true)) {
            $db->exec('ALTER TABLE etf2l_matches ADD COLUMN maps TEXT DEFAULT NULL');
        }
        if (!in_array('r1', $existing, true)) {
            $db->exec('ALTER TABLE etf2l_matches ADD COLUMN r1 INTEGER DEFAULT NULL');
        }
        if (!in_array('r2', $existing, true)) {
            $db->exec('ALTER TABLE etf2l_matches ADD COLUMN r2 INTEGER DEFAULT NULL');
        }
        if (!in_array('map_results', $existing, true)) {
            $db->exec('ALTER TABLE etf2l_matches ADD COLUMN map_results TEXT DEFAULT NULL');
        }

        // Cache des réponses API ETF2L (sync_etf2l.php) : évite de re-interroger
        // l'API à chaque exécution du script.
        $db->exec("CREATE TABLE IF NOT EXISTS etf2l_api_cache (
            url        TEXT PRIMARY KEY,
            payload    TEXT NOT NULL,
            fetched_at INTEGER NOT NULL
        )");

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
