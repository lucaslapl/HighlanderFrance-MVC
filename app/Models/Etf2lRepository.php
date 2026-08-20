<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\SteamId;
use App\Services\MatchFormat;

final class Etf2lRepository
{
    public function __construct(private readonly \PDO $db)
    {
    }

    /**
     * Prochains matchs des équipes françaises, du plus proche au plus lointain.
     */
    public function upcomingMatches(int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM etf2l_matches
            WHERE match_date >= :current_time
            ORDER BY match_date ASC
            LIMIT " . (int)$limit
        );
        $stmt->execute([':current_time' => time()]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Matchs ETF2L pour le sitemap (id + horodatage).
     *
     * @return array<int, array{match_id: int, match_date: int}>
     */
    public function sitemapMatches(): array
    {
        return $this->db
            ->query('SELECT match_id, match_date FROM etf2l_matches ORDER BY match_date ASC')
            ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Détail d'un match ETF2L avec le roster des deux équipes.
     */
    public function etf2lMatchDetail(int $matchId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM etf2l_matches WHERE match_id = ?');
        $stmt->execute([$matchId]);
        $match = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$match) {
            return null;
        }

        $teams = [];
        $steamid64s = [];

        foreach ([1, 2] as $n) {
            $teamId = (int)($match['team' . $n . '_id'] ?? 0);

            $teamStmt = $this->db->prepare('SELECT * FROM etf2l_teams WHERE team_id = ?');
            $teamStmt->execute([(int)$teamId]);
            $team = $teamStmt->fetch(\PDO::FETCH_ASSOC);

            // Équipe absente de la table (roster indisponible, ex. historique) :
            // on s'appuie sur les infos du match pour toujours afficher les deux camps.
            if (!$team) {
                $team = [
                    'team_id' => $teamId,
                    'name' => $n === 1 ? ($match['team1_name'] ?? 'TBD') : ($match['team2_name'] ?? 'TBD'),
                    'country' => $n === 1 ? ($match['team1_country'] ?? 'unknown') : ($match['team2_country'] ?? 'unknown'),
                    'tag' => null,
                ];
            }

            if ($teamId > 0) {
                $playerStmt = $this->db->prepare('SELECT * FROM etf2l_players WHERE team_id = ?');
                $playerStmt->execute([$teamId]);
                $team['players'] = $this->sortPlayers($playerStmt->fetchAll(\PDO::FETCH_ASSOC));
            } else {
                $team['players'] = [];
            }

            foreach ($team['players'] as $p) {
                if (!empty($p['steamid64'])) {
                    $steamid64s[$p['steamid64']] = true;
                }
            }

            $team['key'] = 'team' . $n;
            $team['side'] = $n === 1 ? $match['team1_name'] : $match['team2_name'];
            $teams[] = $team;
        }

        $sitePlayers = $this->existingOnSite(array_keys($steamid64s));

        foreach ($teams as &$team) {
            foreach ($team['players'] as &$p) {
                $steamid64 = !empty($p['steamid64']) ? $p['steamid64'] : null;
                $p['steamid64'] = $steamid64;
                $p['exists_on_site'] = (bool)($steamid64 !== null && isset($sitePlayers[$steamid64]));
                $p['profile_url'] = $p['exists_on_site']
                    ? '/profile/' . $steamid64
                    : 'https://etf2l.org/forum/user/' . (int)$p['player_id'] . '/';
            }
            unset($p);
        }
        unset($team);

        return [
            'match' => $match,
            'teams' => $teams,
            'maps' => $this->buildMaps($match),
        ];
    }

    /**
     * Construit la liste des cartes avec leurs scores (par carte et global).
     */
    private function buildMaps(array $match): array
    {
        $maps = json_decode((string)($match['maps'] ?? 'null'), true) ?? [];
        $results = json_decode((string)($match['map_results'] ?? 'null'), true) ?? [];
        $resultsByOrder = [];

        foreach ($results as $r) {
            $resultsByOrder[(int)($r['match_order'] ?? 0)] = $r;
        }

        $list = [];
        foreach ($maps as $i => $map) {
            $order = $i + 1;
            $result = $resultsByOrder[$order] ?? null;

            $entry = [
                'order' => $order,
                'map' => (string)$map,
                'map_display' => MatchFormat::mapDisplay((string)$map),
            ];

            if ($result !== null) {
                $entry['team1'] = (int)($result['clan1'] ?? 0);
                $entry['team2'] = (int)($result['clan2'] ?? 0);
                $entry['golden_cap'] = (bool)($result['golden_cap'] ?? false);
            }

            $list[] = $entry;
        }

        return [
            'maps' => $list,
            'r1' => isset($match['r1']) && $match['r1'] !== null ? (int)$match['r1'] : null,
            'r2' => isset($match['r2']) && $match['r2'] !== null ? (int)$match['r2'] : null,
        ];
    }

    /**
     * Trie les joueurs d'une équipe : les Leaders (et assimilés) d'abord, puis
     * le reste par ordre alphabétique.
     */
    private function sortPlayers(array $players): array
    {
        usort($players, static function (array $a, array $b): int {
            $ra = (strtolower((string)($a['role'] ?? '')) === 'leader') ? 0 : 1;
            $rb = (strtolower((string)($b['role'] ?? '')) === 'leader') ? 0 : 1;

            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });

        return $players;
    }

    /**
     * @return array<string, bool> map steamid64 => true pour les joueurs présents sur le site.
     */
    private function existingOnSite(array $steamid64s): array
    {
        if ($steamid64s === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($steamid64s), '?'));
        $stmt = $this->db->prepare(
            "SELECT steamid FROM players_info WHERE steamid IN ({$placeholders})"
        );
        $stmt->execute(array_map([SteamId::class, 'toSteamId3'], $steamid64s));

        $map = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $steamid3) {
            $steamid64 = SteamId::toSteamId64($steamid3);
            if ($steamid64 !== null) {
                $map[$steamid64] = true;
            }
        }

        return $map;
    }
}
