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

    /** IDs des équipes françaises sans drapeau "France". */
    private const WHITELISTED_TEAMS = [
        37618,
    ];

    public function __construct(private readonly \PDO $db)
    {
    }

    public function run(): string
    {
        $logToken = AdminLogger::log(self::SCRIPT_NAME);

        $responseObj = JsonClient::get(self::API_URL, 10, 'Highlander France Bot/1.0', ['Accept: application/json']);

        if ($responseObj === null) {
            throw new \RuntimeException('Erreur cURL API ETF2L : appel impossible.');
        }

        if (!isset($responseObj['status']['code']) || (int)$responseObj['status']['code'] !== 200) {
            $msg = (string)($responseObj['status']['message'] ?? 'Réponse invalide/inaccessible');
            throw new \RuntimeException("L'API ETF2L a répondu négativement : " . $msg);
        }

        $matches = $responseObj['results']['data'] ?? [];

        // On vide la table locale pour rafraîchir l'agenda.
        $this->db->exec('DELETE FROM etf2l_matches');

        $insertedCount = 0;
        $stmt = $this->db->prepare('
            INSERT OR REPLACE INTO etf2l_matches (match_id, team1_name, team2_name, match_date, competition_name, team1_country, team2_country)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

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
            ]);

            $insertedCount++;
        }

        $statusMsg = 'SUCCESS (' . $insertedCount . ' match(s) français synchronisé(s))';
        AdminLogger::log(self::SCRIPT_NAME, $logToken, $statusMsg);

        return 'Agenda synchronisé ! ' . $insertedCount . ' match(s) français ajouté(s) en base de données.';
    }
}
