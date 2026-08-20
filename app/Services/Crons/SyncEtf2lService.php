<?php

declare(strict_types=1);

namespace App\Services\Crons;

use App\Services\AdminLogger;
use App\Services\JsonClient;

/**
 * Synchronisation de l'agenda des matchs ETF2L français (sync_etf2l.php).
 */
final class SyncEtf2lService
{
    private const SCRIPT_NAME = 'sync_etf2l.php';

    private const API_URL = 'https://api-v2.etf2l.org/matches?scheduled=1';

    /** Fenêtre (en jours) de rattrapage des matchs terminés pour alimenter l'historique. */
    private const HISTORY_WINDOW_DAYS = 180;

    /** IDs des équipes françaises sans drapeau "France". */
    private const WHITELISTED_TEAMS = [
        37618,
    ];

    public function __construct(private readonly \PDO $db)
    {
    }

    private function fetchAllPages(string $baseUrl): array
    {
        $matches = [];

        for ($page = 1; ; $page++) {
            $responseObj = JsonClient::get($baseUrl . '&page=' . $page, 10, 'Highlander France Bot/1.0', ['Accept: application/json']);

            if ($responseObj === null) {
                throw new \RuntimeException('Erreur cURL API ETF2L : appel impossible.');
            }

            if (!isset($responseObj['status']['code']) || (int)$responseObj['status']['code'] !== 200) {
                $msg = (string)($responseObj['status']['message'] ?? 'Réponse invalide/inaccessible');
                throw new \RuntimeException("L'API ETF2L a répondu négativement : " . $msg);
            }

            $results = $responseObj['results'] ?? [];
            $pageMatches = $results['data'] ?? [];

            if ($pageMatches !== []) {
                $matches[] = $pageMatches;
            }

            $lastPage = (int)($results['last_page'] ?? $page);
            if ($page >= $lastPage) {
                break;
            }
        }

        return array_merge(...$matches);
    }

    public function run(): string
    {
        $logToken = AdminLogger::log(self::SCRIPT_NAME);

        // Historique durable : on cumule matchs à venir + matchs passés récents,
        // et on upsert au lieu de tout effacer (les URLs /match/{id} restent valides).
        $from = time() - self::HISTORY_WINDOW_DAYS * 86400;
        $matches = array_merge(
            $this->fetchAllPages(self::API_URL),
            $this->fetchAllPages('https://api-v2.etf2l.org/matches?scheduled=0&from=' . $from)
        );

        $upsertedCount = 0;
        $stmt = $this->db->prepare('
            INSERT OR REPLACE INTO etf2l_matches (match_id, team1_name, team2_name, match_date, competition_name, team1_country, team2_country, team1_id, team2_id, maps, r1, r2, map_results)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');

        $matchTeamIds = [];

        foreach ($matches as $m) {
            $t1 = $m['clan1'] ?? null;
            $t2 = $m['clan2'] ?? null;

            if (!$t1 || !$t2) {
                continue;
            }

            $t1Id = (int)($t1['id'] ?? 0);
            $t2Id = (int)($t2['id'] ?? 0);

            $isFr1 = (isset($t1['country']) && strtolower((string)$t1['country']) === 'france');
            $isFr2 = (isset($t2['country']) && strtolower((string)$t2['country']) === 'france');
            $isWhitelisted1 = in_array($t1Id, self::WHITELISTED_TEAMS, true);
            $isWhitelisted2 = in_array($t2Id, self::WHITELISTED_TEAMS, true);

            if (!$isFr1 && !$isFr2 && !$isWhitelisted1 && !$isWhitelisted2) {
                continue;
            }

            $stmt->execute([
                $m['id'] ?? null,
                $t1['name'] ?? 'TBD',
                $t2['name'] ?? 'TBD',
                (int)($m['time'] ?? time()),
                $m['competition']['name'] ?? 'Compétition ETF2L',
                isset($t1['country']) ? strtolower((string)$t1['country']) : 'unknown',
                isset($t2['country']) ? strtolower((string)$t2['country']) : 'unknown',
                $t1Id,
                $t2Id,
                isset($m['maps']) ? json_encode(array_values((array)$m['maps']), JSON_THROW_ON_ERROR) : null,
                isset($m['r1']) ? (int)$m['r1'] : null,
                isset($m['r2']) ? (int)$m['r2'] : null,
                isset($m['map_results']) ? json_encode($m['map_results'], JSON_THROW_ON_ERROR) : null,
            ]);

            // Les 2 équipes du match (FR et adverse) sont stockées pour comparer les rosters.
            if ($t1Id > 0) {
                $matchTeamIds[$t1Id] = true;
            }
            if ($t2Id > 0) {
                $matchTeamIds[$t2Id] = true;
            }

            $upsertedCount++;
        }

        if ($matchTeamIds !== []) {
            $this->syncRosters(array_keys($matchTeamIds));
        }

        $statusMsg = 'SUCCESS (' . $upsertedCount . ' match(s) français synchronisé(s))';
        AdminLogger::log(self::SCRIPT_NAME, $logToken, $statusMsg);

        return 'Agenda synchronisé ! ' . $upsertedCount . ' match(s) français ajouté(s) en base de données.';
    }

    /**
     * Récupère et stocke les rosters des équipes (FR et adverses) impliquées
     * dans les matchs français, afin de pouvoir comparer les deux équipes.
     */
    private function syncRosters(array $teamIds): void
    {
        $teamStmt = $this->db->prepare('
            INSERT OR REPLACE INTO etf2l_teams (team_id, name, country, tag)
            VALUES (?, ?, ?, ?)
        ');
        $playerStmt = $this->db->prepare('
            INSERT OR REPLACE INTO etf2l_players (team_id, player_id, name, role, country, steamid64)
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        foreach ($teamIds as $teamId) {
            $responseObj = JsonClient::get(
                'https://api-v2.etf2l.org/team/' . (int)$teamId,
                10,
                'Highlander France Bot/1.0',
                ['Accept: application/json']
            );

            if ($responseObj === null) {
                continue;
            }

            if (!isset($responseObj['status']['code']) || (int)$responseObj['status']['code'] !== 200) {
                continue;
            }

            $team = $responseObj['team'] ?? null;
            if ($team === null) {
                continue;
            }

            $teamStmt->execute([
                (int)$teamId,
                $team['name'] ?? 'TBD',
                isset($team['country']) ? strtolower((string)$team['country']) : 'unknown',
                $team['tag'] ?? null,
            ]);

            $players = $team['players'] ?? [];
            foreach ($players as $p) {
                $playerStmt->execute([
                    (int)$teamId,
                    (int)($p['id'] ?? 0),
                    $p['name'] ?? 'Joueur ETF2L',
                    $p['role'] ?? 'Member',
                    isset($p['country']) ? strtolower((string)$p['country']) : 'unknown',
                    isset($p['steam']['id64']) ? (string)$p['steam']['id64'] : null,
                ]);
            }
        }
    }
}
