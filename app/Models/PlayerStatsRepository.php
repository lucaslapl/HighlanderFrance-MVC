<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Agrégations de statistiques de jeu par joueur (table player_matches).
 */
final class PlayerStatsRepository
{
    public function __construct(private readonly \PDO $db)
    {
    }

    public function totalMatches(string $steamid3, string $mode): int
    {
        $stmt = $this->db->prepare('SELECT count FROM player_stats WHERE steamid = ? AND game_mode = ?');
        $stmt->execute([$steamid3, $mode]);

        return (int)($stmt->fetch(\PDO::FETCH_ASSOC)['count'] ?? 0);
    }

    public function topMaps(string $steamid3, string $mode): array
    {
        $stmt = $this->db->prepare("
            SELECT map_name, COUNT(map_name) AS total
            FROM player_matches
            WHERE steamid = ? AND game_mode = ? AND map_name NOT LIKE '% + %'
            GROUP BY map_name
            ORDER BY total DESC
        ");
        $stmt->execute([$steamid3, $mode]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function classesPlayed(string $steamid3, string $mode): array
    {
        $stmt = $this->db->prepare("
            SELECT class_played, COUNT(class_played) AS total
            FROM player_matches
            WHERE steamid = ? AND game_mode = ?
            GROUP BY class_played
            ORDER BY total DESC
        ");
        $stmt->execute([$steamid3, $mode]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Statistiques globales d'un joueur pour un mode (DPM, kills, morts, K/D, classes tuées…).
     */
    public function aggregate(string $steamid3, string $mode): array
    {
        $empty = [
            'average_dpm'      => 0,
            'average_dtpm'     => 0,
            'total_dmg_taken'  => 0,
            'total_airshots'   => 0,
            'total_captures'   => 0,
            'total_medkits_hp' => 0,
            'total_kills'      => 0,
            'total_deaths'     => 0,
            'total_assists'    => 0,
            'kd_ratio'         => 0,
            'classes_killed'   => [],
        ];

        if (!$this->columnsAvailable()) {
            return $empty;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(AVG(CASE WHEN length > 0 THEN dapm END), 0)                        AS average_dpm,
                       COALESCE(AVG(CASE WHEN length > 0 THEN dmg_taken * 60.0 / length END), 0) AS average_dtpm,
                       COALESCE(SUM(dmg_taken), 0)  AS total_dmg_taken,
                       COALESCE(SUM(airshots), 0)   AS total_airshots,
                       COALESCE(SUM(captures), 0)   AS total_captures,
                       COALESCE(SUM(medkits_hp), 0) AS total_medkits_hp,
                       COALESCE(SUM(kills), 0)      AS total_kills,
                       COALESCE(SUM(deaths), 0)     AS total_deaths,
                       COALESCE(SUM(assists), 0)    AS total_assists
                FROM player_matches
                WHERE steamid = ? AND game_mode = ?
            ");
            $stmt->execute([$steamid3, $mode]);
            $t = $stmt->fetch(\PDO::FETCH_ASSOC);

            $kd = 0;
            if ((int)$t['total_deaths'] > 0) {
                $kd = round((int)$t['total_kills'] / (int)$t['total_deaths'], 2);
            } elseif ((int)$t['total_kills'] > 0) {
                $kd = (int)$t['total_kills'];
            }

            // Fusion des JSON "classes_killed" de chaque match
            $stmtCk = $this->db->prepare("
                SELECT classes_killed FROM player_matches
                WHERE steamid = ? AND game_mode = ?
                  AND classes_killed IS NOT NULL AND classes_killed != ''
            ");
            $stmtCk->execute([$steamid3, $mode]);

            $classesKilled = [];
            foreach ($stmtCk->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $decoded = json_decode((string)$row['classes_killed'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $class => $count) {
                        $classesKilled[$class] = ($classesKilled[$class] ?? 0) + (int)$count;
                    }
                }
            }
            arsort($classesKilled);

            return [
                'average_dpm'      => round((float)$t['average_dpm'], 1),
                'average_dtpm'     => round((float)$t['average_dtpm'], 1),
                'total_dmg_taken'  => (int)$t['total_dmg_taken'],
                'total_airshots'   => (int)$t['total_airshots'],
                'total_captures'   => (int)$t['total_captures'],
                'total_medkits_hp' => (int)$t['total_medkits_hp'],
                'total_kills'      => (int)$t['total_kills'],
                'total_deaths'     => (int)$t['total_deaths'],
                'total_assists'    => (int)$t['total_assists'],
                'kd_ratio'         => $kd,
                'classes_killed'   => $classesKilled,
            ];
        } catch (\PDOException) {
            return $empty;
        }
    }

    /**
     * Activité : nombre de matchs par jour sur les 3 derniers mois (tous modes confondus).
     *
     * @return array<string, int>
     */
    public function activity(string $steamid3): array
    {
        $stmt = $this->db->prepare("
            SELECT strftime('%Y-%m-%d', ld.date, 'unixepoch') AS day, COUNT(DISTINCT pm.match_id) AS matches
            FROM player_matches pm
            LEFT JOIN log_dates ld ON ld.log_id = pm.match_id
            WHERE pm.steamid = ? AND ld.date IS NOT NULL
              AND ld.date >= strftime('%s', 'now', '-3 months')
            GROUP BY day
        ");
        $stmt->execute([$steamid3]);

        $activity = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $activity[$row['day']] = (int)$row['matches'];
        }

        return $activity;
    }

    /**
     * Derniers matchs d'un joueur pour un mode (avec date issue de log_dates).
     */
    public function recentMatches(string $steamid3, string $mode, int $limit = 5): array
    {
        $extra = '';
        if ($this->columnsAvailable()) {
            $extra .= ', dmg, kills, deaths, assists';
            if ($this->columnExists('won')) {
                $extra .= ', won';
            }
        }

        $stmt = $this->db->prepare("
            SELECT pm.match_id, pm.map_name, pm.class_played$extra,
                   ld.date AS match_date
            FROM player_matches pm
            LEFT JOIN log_dates ld ON ld.log_id = pm.match_id
            WHERE pm.steamid = ? AND pm.game_mode = ?
            ORDER BY pm.match_id DESC
            LIMIT " . (int)$limit
        );
        $stmt->execute([$steamid3, $mode]);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['dmg'] = (int)($r['dmg'] ?? 0);
            $r['kills'] = (int)($r['kills'] ?? 0);
            $r['deaths'] = (int)($r['deaths'] ?? 0);
            $r['assists'] = (int)($r['assists'] ?? 0);
            $r['won'] = isset($r['won']) ? (is_null($r['won']) ? null : (int)$r['won']) : null;
            $r['match_date'] = !empty($r['match_date']) ? date('d/m/Y', (int)$r['match_date']) : null;
        }
        unset($r);

        return $rows;
    }

    /**
     * Vrai si les colonnes de stats ont été migrées dans player_matches.
     */
    private function columnsAvailable(): bool
    {
        static $available = null;

        if ($available === null) {
            $cols = $this->columns();
            $available = isset($cols['dmg'], $cols['kills'], $cols['deaths'], $cols['classes_killed']);
        }

        return $available;
    }

    private function columnExists(string $column): bool
    {
        return isset($this->columns()[$column]);
    }

    /**
     * @return array<string, true>
     */
    private function columns(): array
    {
        static $cols = null;

        if ($cols === null) {
            $cols = [];
            foreach ($this->db->query('PRAGMA table_info(player_matches)')->fetchAll(\PDO::FETCH_ASSOC) as $c) {
                $cols[$c['name']] = true;
            }
        }

        return $cols;
    }
}
