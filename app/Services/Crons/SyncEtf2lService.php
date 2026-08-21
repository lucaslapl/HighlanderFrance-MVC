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

    /** Délai entre deux appels API (rate-limit ETF2L : 60 req/min). */
    private const API_CALL_DELAY_US = 1100000;

    /** Nombre maximal d'appels /matches/{id} par exécution (backfill progressif). */
    private const ENRICH_MAX_PER_RUN = 45;

    public function __construct(private readonly \PDO $db)
    {
    }

    private function fetchAllPages(string $baseUrl): array
    {
        $matches = [];

        for ($page = 1; ; $page++) {
            if ($page > 1) {
                usleep(self::API_CALL_DELAY_US);
            }

            $responseObj = $this->fetchWithRetry($baseUrl . '&page=' . $page);

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

        return $matches === [] ? [] : array_merge(...$matches);
    }

    /**
     * Appel API avec nouvelle tentative sur réponse transitoire
     * (payload throttlé type {"message":"Too Many Attempts."}, 429, erreur cURL ou 5xx).
     */
    private function fetchWithRetry(string $url, int $attempts = 2): array
    {
        for ($i = 1; $i <= $attempts; $i++) {
            if ($i > 1) {
                sleep(3);
            }

            $responseObj = JsonClient::get($url, 10, 'Highlander France Bot/1.0', ['Accept: application/json']);

            if ($responseObj === null) {
                continue;
            }

            $code = isset($responseObj['status']['code']) ? (int)$responseObj['status']['code'] : null;

            if ($code === 200) {
                return $responseObj;
            }

            // Pas de clé status (= throttle Laravel) ou code transitoire : on retente.
            if ($code === null || in_array($code, [429, 500, 502, 503, 504], true)) {
                continue;
            }

            $msg = (string)($responseObj['status']['message'] ?? 'Réponse invalide/inaccessible');
            throw new \RuntimeException("L'API ETF2L a répondu négativement : " . $msg);
        }

        throw new \RuntimeException('Erreur cURL API ETF2L : appel impossible après nouvelle tentative.');
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
            INSERT INTO etf2l_matches (match_id, team1_name, team2_name, match_date, competition_name, team1_country, team2_country, team1_id, team2_id, maps, r1, r2, map_results)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(match_id) DO UPDATE SET
                team1_name = excluded.team1_name,
                team2_name = excluded.team2_name,
                match_date = excluded.match_date,
                competition_name = excluded.competition_name,
                team1_country = excluded.team1_country,
                team2_country = excluded.team2_country,
                team1_id = excluded.team1_id,
                team2_id = excluded.team2_id,
                maps = COALESCE(excluded.maps, etf2l_matches.maps),
                r1 = COALESCE(excluded.r1, etf2l_matches.r1),
                r2 = COALESCE(excluded.r2, etf2l_matches.r2),
                map_results = COALESCE(excluded.map_results, etf2l_matches.map_results)
        ');

        $matchTeamIds = [];
        $frenchMatches = []; // match_id => ['time' => int, 'submitted' => ?int]

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

            if (isset($m['id'])) {
                $frenchMatches[(int)$m['id']] = [
                    'time' => (int)($m['time'] ?? 0),
                    'submitted' => isset($m['submitted']) ? (int)$m['submitted'] : null,
                ];
            }

            if ($t1Id > 0) {
                $matchTeamIds[$t1Id] = true;
            }
            if ($t2Id > 0) {
                $matchTeamIds[$t2Id] = true;
            }

            $upsertedCount++;
        }

        $enriched = 0;
        $toEnrich = $this->matchesNeedingEnrichment($frenchMatches);
        if ($toEnrich !== []) {
            $enriched = $this->enrichMapResults($toEnrich);
        }

        if ($matchTeamIds !== []) {
            $this->syncRosters(array_keys($matchTeamIds));
        }

        $statusMsg = 'SUCCESS (' . $upsertedCount . ' match(s) français synchronisé(s), ' . $enriched . ' enrichi(s) map_results)';
        AdminLogger::log(self::SCRIPT_NAME, $logToken, $statusMsg);

        return 'Agenda synchronisé ! ' . $upsertedCount . ' match(s) français ajouté(s) en base de données' . ($enriched > 0 ? ' (' . $enriched . ' map_results enrichis)' : '') . '.';
    }

    /**
     * Sélectionne les matchs terminés sans résultats par carte, les plus récents
     * d'abord, dans la limite du quota d'appels par exécution. Les matchs déjà
     * enrichis (map_results renseigné) sont exclus : régime permanent = 0 appel.
     */
    private function matchesNeedingEnrichment(array $frenchMatches): array
    {
        $now = time();
        $candidates = [];

        foreach ($frenchMatches as $matchId => $info) {
            $finished = $info['submitted'] !== null || $info['time'] <= $now;
            if ($finished) {
                $candidates[$matchId] = $info['time'];
            }
        }

        if ($candidates === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($candidates), '?'));
        $stmt = $this->db->prepare(
            "SELECT match_id FROM etf2l_matches WHERE match_id IN ({$placeholders}) AND map_results IS NULL"
        );
        $stmt->execute(array_keys($candidates));

        $pending = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        if ($pending === []) {
            return [];
        }

        // Les plus récents d'abord (visibles en premier sur le site).
        usort($pending, static fn(int $a, int $b): int => ($candidates[$b] ?? 0) <=> ($candidates[$a] ?? 0));

        return array_slice($pending, 0, self::ENRICH_MAX_PER_RUN);
    }

    private function enrichMapResults(array $matchIds): int
    {
        $updateStmt = $this->db->prepare('UPDATE etf2l_matches SET maps = COALESCE(?, maps), r1 = COALESCE(?, r1), r2 = COALESCE(?, r2), map_results = COALESCE(?, map_results) WHERE match_id = ?');
        $enriched = 0;

        foreach ($matchIds as $matchId) {
            usleep(self::API_CALL_DELAY_US);

            try {
                $responseObj = JsonClient::get(
                    'https://api-v2.etf2l.org/matches/' . (int)$matchId,
                    10,
                    'Highlander France Bot/1.0',
                    ['Accept: application/json']
                );

                if ($responseObj === null) {
                    continue;
                }

                $code = isset($responseObj['status']['code']) ? (int)$responseObj['status']['code'] : null;

                if ($code !== 200) {
                    // Payload throttlé (429 sans clé status) : pause puis reprise au suivant.
                    if ($code === null || $code === 429) {
                        sleep(5);
                    }
                    continue;
                }

                $match = $responseObj['match'] ?? null;
                if (!is_array($match)) {
                    continue;
                }

                $maps = $match['maps'] ?? null;
                $r1 = $match['r1'] ?? null;
                $r2 = $match['r2'] ?? null;
                $mapResults = $match['map_results'] ?? null;

                if ($maps === null && $r1 === null && $r2 === null && $mapResults === null) {
                    continue;
                }

                // Match terminé sans détail par carte (forfait...) : on marque avec un
                // tableau vide pour ne pas re-interroger l'API à chaque exécution.
                $mapResultsJson = $mapResults !== null
                    ? json_encode($mapResults, JSON_THROW_ON_ERROR)
                    : (($r1 !== null || $r2 !== null) ? '[]' : null);

                $updateStmt->execute([
                    $maps !== null ? json_encode(array_values((array)$maps), JSON_THROW_ON_ERROR) : null,
                    $r1 !== null ? (int)$r1 : null,
                    $r2 !== null ? (int)$r2 : null,
                    $mapResultsJson,
                    (int)$matchId,
                ]);

                $enriched++;
            } catch (\Throwable $e) {
                error_log('Enrichissement ETF2L match ' . $matchId . ' : ' . $e->getMessage());
                continue;
            }
        }

        return $enriched;
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
            usleep(self::API_CALL_DELAY_US);

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
