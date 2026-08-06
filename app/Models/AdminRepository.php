<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\SteamId;

/**
 * Données et actions du panel d'administration.
 */
final class AdminRepository
{
    public function __construct(private readonly \PDO $db)
    {
    }

    /**
     * Indicateurs + séries pour les graphiques du dashboard.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $sql = fn (string $q): int => (int)$this->db->query($q)->fetchColumn();

        return [
            'totalPlayers' => $sql('SELECT COUNT(*) FROM players_info'),
            'totalRegistered' => $sql('SELECT COUNT(*) FROM players_info WHERE created_at IS NOT NULL'),
            'totalStaff' => $sql('SELECT COUNT(*) FROM players_info WHERE is_admin = 1 OR is_founder = 1 OR is_moderator = 1 OR is_mentor = 1 OR is_mixer = 1'),
            'registrations' => $this->registrations(),
            'matchesPerDay' => $this->matchesPerDay(),
            'modes' => $this->modes(),
            'recentUsers' => $this->recentUsers(),
        ];
    }

    /**
     * @return array<int, array{d: string, nb: int}>
     */
    public function registrations(): array
    {
        $stmt = $this->db->query("
            SELECT date(created_at) AS d, COUNT(*) AS nb
            FROM players_info
            WHERE created_at IS NOT NULL AND created_at >= date('now', '-12 months')
            GROUP BY date(created_at)
            ORDER BY d ASC
        ");

        $rows = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[] = ['d' => $row['d'], 'nb' => (int)$row['nb']];
        }

        return $rows;
    }

    /**
     * @return array<int, array{d: string, nb: int}>
     */
    public function matchesPerDay(): array
    {
        $stmt = $this->db->prepare("
            SELECT date(ld.date, 'unixepoch') AS d, COUNT(DISTINCT pm.match_id) AS nb
            FROM player_matches pm
            JOIN log_dates ld ON ld.log_id = pm.match_id
            WHERE ld.date IS NOT NULL AND ld.date >= ?
            GROUP BY date(ld.date, 'unixepoch')
            ORDER BY d ASC
        ");
        $stmt->execute([strtotime('-12 months')]);

        $rows = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $rows[] = ['d' => $row['d'], 'nb' => (int)$row['nb']];
        }

        return $rows;
    }

    /**
     * Nombre de matchs distincts par mode.
     *
     * @return array<string, int>
     */
    public function modes(): array
    {
        $modes = [];
        foreach ($this->db->query('SELECT game_mode, COUNT(DISTINCT match_id) AS nb FROM player_matches GROUP BY game_mode')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $modes[$row['game_mode']] = (int)$row['nb'];
        }

        return $modes;
    }

    /**
     * 5 derniers inscrits (steamid3 + steamid64 pour l'affichage).
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentUsers(): array
    {
        $stmt = $this->db->query('SELECT steamid, name, display_name, created_at FROM players_info ORDER BY created_at DESC LIMIT 5');

        $users = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $user) {
            $users[] = [
                'steamid' => $user['steamid'],
                'steamid64' => SteamId::toSteamId64($user['steamid']),
                'name' => $user['name'],
                'display_name' => $user['display_name'],
                'created_at' => $user['created_at'],
            ];
        }

        return $users;
    }

    /**
     * Admins (équipe technique).
     *
     * @return array<int, array<string, mixed>>
     */
    public function technicalTeam(): array
    {
        try {
            return $this->db->query("
                SELECT steamid, display_name, country
                FROM players_info
                WHERE is_admin = 1
                ORDER BY display_name ASC
            ")->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * Liste complète de la blacklist (id, raison, auteur, date).
     *
     * @return array<int, array<string, mixed>>
     */
    public function blacklist(): array
    {
        try {
            return $this->db->query('SELECT log_id, reason, added_by, created_at FROM log_blacklist ORDER BY created_at DESC, log_id DESC')->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * Résout le pseudo d'un admin à partir du SteamID64 stocké en base.
     * Les valeurs non-SteamID ('legacy', 'auto', 'Inconnu') sont retournées telles quelles.
     */
    public function adminDisplayName(string $addedBy): string
    {
        if ($addedBy === '' || !preg_match('/^\d{17}$/', $addedBy)) {
            return $addedBy;
        }

        $stmt = $this->db->prepare('SELECT display_name, name FROM players_info WHERE steamid = ?');
        $stmt->execute([SteamId::toSteamId3($addedBy)]);
        $p = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($p) {
            return !empty($p['display_name']) ? $p['display_name'] : $p['name'];
        }

        return $addedBy;
    }

    /**
     * Mode (6s/9v9) de chaque log traité en base.
     *
     * @return array<int, string>
     */
    public function dbModes(): array
    {
        $modes = [];
        foreach ($this->db->query('SELECT match_id, game_mode FROM player_matches GROUP BY match_id')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $modes[(int)$row['match_id']] = $row['game_mode'];
        }

        return $modes;
    }

    /**
     * Durées en cache (log_length_cache).
     *
     * @return array<int, int>
     */
    public function logLengths(): array
    {
        $lengths = [];
        foreach ($this->db->query('SELECT log_id, length FROM log_length_cache')->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $lengths[(int)$row['log_id']] = (int)$row['length'];
        }

        return $lengths;
    }

    /**
     * @param array<int, int> $lengths
     */
    public function saveLogLengths(array $lengths): void
    {
        $insert = $this->db->prepare('INSERT OR IGNORE INTO log_length_cache (log_id, length) VALUES (?, ?)');
        foreach ($lengths as $id => $length) {
            $insert->execute([$id, $length]);
        }
    }

    /**
     * Mise à jour globale du profil d'un joueur (pseudo, pays, rôles, verrous).
     */
    public function updatePlayer(
        string $steamid3,
        string $displayName,
        string $country,
        int $isFounder,
        int $isModerator,
        int $isMentor,
        int $isMixer,
        bool $resetNameChange,
        bool $resetCountryChange,
    ): bool {
        $sql = "UPDATE players_info
                SET display_name = ?,
                    country = ?,
                    is_founder = ?,
                    is_moderator = ?,
                    is_mentor = ?,
                    is_mixer = ?";
        $params = [$displayName, $country, $isFounder, $isModerator, $isMentor, $isMixer];

        if ($resetNameChange) {
            $sql .= ', name_changed = 0';
        }
        if ($resetCountryChange) {
            $sql .= ', country_locked = 0';
        }

        $sql .= ' WHERE steamid = ?';
        $params[] = $steamid3;

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Bascule le mode (6s/9v9) d'un log et ajuste les compteurs joueurs.
     *
     * @return array{success: bool, message: string}
     */
    public function switchMatchMode(int $logId, string $mode): array
    {
        $stmt = $this->db->prepare('SELECT game_mode FROM player_matches WHERE match_id = ? LIMIT 1');
        $stmt->execute([$logId]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            return ['success' => false, 'message' => "Ce log n'est pas encore traité en base de données (aucun joueur associé)."];
        }
        if ($current === $mode) {
            return ['success' => false, 'message' => "Le log #$logId est déjà en mode $mode."];
        }

        $stmtPlayers = $this->db->prepare('SELECT steamid FROM player_matches WHERE match_id = ?');
        $stmtPlayers->execute([$logId]);
        $steamids = $stmtPlayers->fetchAll(\PDO::FETCH_COLUMN);

        try {
            $this->db->beginTransaction();

            $this->db->prepare('UPDATE player_matches SET game_mode = ? WHERE match_id = ?')->execute([$mode, $logId]);

            $dec = $this->db->prepare('UPDATE player_stats SET count = count - 1 WHERE steamid = ? AND game_mode = ?');
            $inc = $this->db->prepare("INSERT INTO player_stats (steamid, count, game_mode) VALUES (?, 1, ?)
                                       ON CONFLICT(steamid, game_mode) DO UPDATE SET count = count + 1");
            foreach ($steamids as $steamid) {
                $dec->execute([$steamid, $current]);
                $inc->execute([$steamid, $mode]);
            }
            $this->db->exec('DELETE FROM player_stats WHERE count <= 0');

            $this->db->commit();

            return ['success' => true, 'message' => "Le log #$logId est passé du mode $current au mode $mode."];
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return ['success' => false, 'message' => 'Erreur BDD : ' . $e->getMessage()];
        }
    }
}
