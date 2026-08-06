<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Opérations base de données partagées par les scripts CRON
 * (stats des matchs joueurs, purges, cache des dates).
 * Port des helpers legacy (_inc/functions.php).
 */
final class MatchStatsRepository
{
    public function __construct(private readonly \PDO $db)
    {
    }

    // --- Logs (processed_logs) ---

    public function isProcessed(int $logId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM processed_logs WHERE id = ?');
        $stmt->execute([$logId]);

        return $stmt->fetch() !== false;
    }

    public function markProcessed(int $logId): void
    {
        $this->db->prepare('INSERT OR IGNORE INTO processed_logs (id) VALUES (?)')->execute([$logId]);
    }

    // --- Cache des dates (log_dates) ---

    public function saveLogDate(int $logId, int $date): void
    {
        $this->db->prepare('INSERT OR IGNORE INTO log_dates (log_id, date) VALUES (?, ?)')->execute([$logId, $date]);
    }

    // --- Scores (match_scores) ---

    public function saveMatchScores(int $matchId, int $redScore, int $blueScore): void
    {
        $this->db->prepare('INSERT INTO match_scores (match_id, red_score, blue_score)
                            VALUES (?, ?, ?)
                            ON CONFLICT(match_id) DO UPDATE SET
                                red_score = excluded.red_score,
                                blue_score = excluded.blue_score')
            ->execute([$matchId, $redScore, $blueScore]);
    }

    // --- Compteurs (player_stats) ---

    public function incrementPlayerStat(string $steamid, string $gameMode): void
    {
        $this->db->prepare('INSERT INTO player_stats (steamid, count, game_mode) VALUES (?, 1, ?)
                            ON CONFLICT(steamid, game_mode) DO UPDATE SET count = count + 1')
            ->execute([$steamid, $gameMode]);
    }

    // --- Joueurs (players_info) ---

    public function playerExists(string $steamid): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM players_info WHERE steamid = ?');
        $stmt->execute([$steamid]);

        return $stmt->fetch() !== false;
    }

    public function insertPlayer(string $steamid, string $name, string $avatar): void
    {
        $this->db->prepare('INSERT INTO players_info (steamid, name, avatar, last_updated) VALUES (?, ?, ?, ?)')
            ->execute([$steamid, $name, $avatar, time()]);
    }

    // --- Détail d'un match joueur (player_matches) ---

    /**
     * Insère ou met à jour la ligne player_matches d'un joueur pour un log.
     * Ne modifie JAMAIS map_name/class_played/game_mode sur un conflit
     * (pour préserver les corrections manuelles des admins).
     *
     * @param array<string, mixed> $stats
     */
    public function upsertPlayerMatch(string $steamid, int $matchId, string $mapName, string $classPlayed, string $gameMode, array $stats): void
    {
        $this->db->prepare('INSERT INTO player_matches
            (steamid, match_id, map_name, class_played, game_mode,
             dmg, kills, deaths, assists, suicides, heal, medkits, ubers, drops,
             backstabs, headshots, longest_killstreak, classes_killed,
             length, dapm, dmg_taken, medkits_hp, airshots, captures, won, team)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(steamid, match_id) DO UPDATE SET
                dmg = excluded.dmg,
                kills = excluded.kills,
                deaths = excluded.deaths,
                assists = excluded.assists,
                suicides = excluded.suicides,
                heal = excluded.heal,
                medkits = excluded.medkits,
                ubers = excluded.ubers,
                drops = excluded.drops,
                backstabs = excluded.backstabs,
                headshots = excluded.headshots,
                longest_killstreak = excluded.longest_killstreak,
                classes_killed = excluded.classes_killed,
                length = excluded.length,
                dapm = excluded.dapm,
                dmg_taken = excluded.dmg_taken,
                medkits_hp = excluded.medkits_hp,
                airshots = excluded.airshots,
                captures = excluded.captures,
                won = excluded.won,
                team = excluded.team')
            ->execute([
                $steamid,
                $matchId,
                $mapName,
                $classPlayed,
                $gameMode,
                (int)($stats['dmg'] ?? 0),
                (int)($stats['kills'] ?? 0),
                (int)($stats['deaths'] ?? 0),
                (int)($stats['assists'] ?? 0),
                (int)($stats['suicides'] ?? 0),
                (int)($stats['heal'] ?? 0),
                (int)($stats['medkits'] ?? 0),
                (int)($stats['ubers'] ?? 0),
                (int)($stats['drops'] ?? 0),
                (int)($stats['backstabs'] ?? 0),
                (int)($stats['headshots'] ?? 0),
                (int)($stats['longest_killstreak'] ?? 0),
                (string)($stats['classes_killed'] ?? '[]'),
                (int)($stats['length'] ?? 0),
                (int)($stats['dapm'] ?? 0),
                (int)($stats['dmg_taken'] ?? 0),
                (int)($stats['medkits_hp'] ?? 0),
                (int)($stats['airshots'] ?? 0),
                (int)($stats['captures'] ?? 0),
                array_key_exists('won', $stats) ? (is_null($stats['won']) ? null : (int)$stats['won']) : null,
                (isset($stats['team']) && in_array($stats['team'], ['red', 'blue'], true)) ? $stats['team'] : null,
            ]);
    }

    // --- Purges rétroactives ---

    /**
     * Retire des stats les logs blacklistés déjà traités.
     *
     * @param int[] $blacklistedIds
     */
    public function purgeBlacklisted(array $blacklistedIds): int
    {
        if ($blacklistedIds === []) {
            return 0;
        }

        $ph = implode(',', array_fill(0, count($blacklistedIds), '?'));
        $stmtList = $this->db->prepare("SELECT match_id, steamid, game_mode FROM player_matches WHERE match_id IN ($ph)");
        $stmtList->execute($blacklistedIds);

        $dec = $this->db->prepare('UPDATE player_stats SET count = count - 1 WHERE steamid = ? AND game_mode = ?');
        foreach ($stmtList->fetchAll(\PDO::FETCH_ASSOC) as $m) {
            $dec->execute([$m['steamid'], $m['game_mode']]);
        }
        $this->db->exec('DELETE FROM player_stats WHERE count <= 0');

        $stmtDel = $this->db->prepare("DELETE FROM player_matches WHERE match_id IN ($ph)");
        $stmtDel->execute($blacklistedIds);

        return $stmtDel->rowCount();
    }

    /**
     * Retire des stats les matchs sans classe (undefined/unknown).
     */
    public function purgeInvalidClasses(): int
    {
        $rows = $this->db->query("
            SELECT steamid, game_mode, COUNT(*) AS cnt
            FROM player_matches
            WHERE class_played IN ('undefined', 'unknown')
            GROUP BY steamid, game_mode
        ")->fetchAll(\PDO::FETCH_ASSOC);

        $purged = 0;
        if ($rows !== []) {
            $dec = $this->db->prepare('UPDATE player_stats SET count = count - ? WHERE steamid = ? AND game_mode = ?');
            foreach ($rows as $row) {
                $dec->execute([(int)$row['cnt'], $row['steamid'], $row['game_mode']]);
            }

            $purged = (int)$this->db->exec("DELETE FROM player_matches WHERE class_played IN ('undefined', 'unknown')");
            $this->db->exec('DELETE FROM player_stats WHERE count <= 0');
        }

        return $purged;
    }
}
