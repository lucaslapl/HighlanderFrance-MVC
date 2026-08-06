<?php

declare(strict_types=1);

namespace App\Models;

final class MatchLogRepository
{
    public function __construct(private readonly \PDO $db)
    {
    }

    /**
     * IDs des logs logs.tf exclus des statistiques.
     *
     * @return int[]
     */
    public function blacklistedIds(): array
    {
        try {
            $rows = $this->db->query('SELECT log_id FROM log_blacklist')->fetchAll(\PDO::FETCH_COLUMN);

            return array_map('intval', $rows);
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * Ajoute un log à la blacklist (idempotent).
     *
     * @return bool true si le log vient d'être ajouté, false s'il y était déjà.
     */
    public function blacklist(int $logId, ?string $reason, string $addedBy): bool
    {
        $stmt = $this->db->prepare('INSERT OR IGNORE INTO log_blacklist (log_id, reason, added_by) VALUES (?, ?, ?)');
        $stmt->execute([$logId, $reason, $addedBy]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Retire un log de la blacklist.
     *
     * @return bool true si le log a été retiré, false s'il n'y était pas.
     */
    public function unblacklist(int $logId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM log_blacklist WHERE log_id = ?');
        $stmt->execute([$logId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Détail d'un match (page /log/match-log).
     *
     * @return array<string, mixed>|null null si le log n'a aucun joueur en base.
     */
    public function matchDetail(int $logId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT pm.steamid, pm.map_name, pm.game_mode, pm.class_played, pm.team,
                   pm.dmg, pm.kills, pm.deaths, pm.assists,
                   pm.suicides, pm.heal, pm.medkits, pm.ubers, pm.drops, pm.backstabs,
                   pm.headshots, pm.longest_killstreak,
                   pi.name, pi.display_name, pi.avatar
            FROM player_matches pm
            LEFT JOIN players_info pi ON pi.steamid = pm.steamid
            WHERE pm.match_id = ?
            ORDER BY pm.dmg DESC
        ");
        $stmt->execute([$logId]);
        $players = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($players === []) {
            return null;
        }

        $first = $players[0];

        $stmtDate = $this->db->prepare('SELECT date FROM log_dates WHERE log_id = ?');
        $stmtDate->execute([$logId]);
        $date = $stmtDate->fetchColumn();
        $date = $date !== false ? (int)$date : null;

        $length = 0;
        $stmtLen = $this->db->prepare('SELECT length FROM matches_cache WHERE match_id = ?');
        $stmtLen->execute([$logId]);
        $length = (int)$stmtLen->fetchColumn();
        if ($length <= 0) {
            $stmtLen2 = $this->db->prepare('SELECT length FROM log_length_cache WHERE log_id = ?');
            $stmtLen2->execute([$logId]);
            $length = (int)$stmtLen2->fetchColumn();
        }

        $redScore = null;
        $blueScore = null;
        $stmtScore = $this->db->prepare('SELECT red_score, blue_score FROM match_scores WHERE match_id = ?');
        $stmtScore->execute([$logId]);
        $scoreRow = $stmtScore->fetch(\PDO::FETCH_ASSOC);
        if ($scoreRow) {
            $redScore = (int)$scoreRow['red_score'];
            $blueScore = (int)$scoreRow['blue_score'];
        }

        return [
            'players' => $players,
            'map_name' => $first['map_name'] ?? '',
            'game_mode' => strtoupper($first['game_mode'] ?? '9v9'),
            'date' => $date,
            'length' => $length,
            'red_score' => $redScore,
            'blue_score' => $blueScore,
        ];
    }

    /**
     * Invalide le cache JSON des Match Stats.
     */
    public function invalidateLogsCache(): void
    {
        $cacheFile = DATA_DIR . '/cache_hlfr_logs.json';
        if (is_file($cacheFile)) {
            @unlink($cacheFile);
        }
    }
}
