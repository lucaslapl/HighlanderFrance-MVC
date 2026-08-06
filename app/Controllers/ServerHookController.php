<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AdminLogger;
use App\Services\Crons\GenerateJsonService;
use App\Services\Crons\UpdateIndexStatsService;
use App\Services\Crons\UpdateStatsService;

/**
 * Endpoint public appelé par le plugin SourceMod (hlfr_match_log) à la fin
 * d'un match. Authentifié par un token partagé (SERVER_WEBHOOK_TOKEN),
 * sans session utilisateur. Déclenche la chaîne de mise à jour des stats
 * et du leaderboard (même pipeline que le CRON, en temps réel).
 */
final class ServerHookController extends Controller
{
    private const LOCK_FILE = DATA_DIR . '/webhook_match.lock';

    /**
     * POST /api/server/match-ended
     * Body (JSON) : { token, server, map }
     */
    public function matchEnded(): void
    {
        $server = (string)($this->payload('server') ?? 'inconnu');
        $map = (string)($this->payload('map') ?? '');

        if (!$this->authenticate()) {
            $this->json(['success' => false, 'message' => 'Non autorisé.'], 403);

            return;
        }

        if (!$this->ipAllowed()) {
            $this->json(['success' => false, 'message' => 'IP non autorisée.'], 403);

            return;
        }

        // Anti-concurrence : si une mise à jour est déjà en cours, on répond 202
        // (le plugin SourceMod ne considère pas ça comme un échec).
        $lock = fopen(self::LOCK_FILE, 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false) {
                fclose($lock);
            }
            $this->json(['success' => true, 'message' => 'Mise à jour déjà en cours.'], 202);

            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }

        $logToken = AdminLogger::log('webhook_match_ended');

        try {
            $db = Database::connection();

            $updateStats = (new UpdateStatsService($db))->run();
            $generateJson = (new GenerateJsonService($db))->run();
            $indexStats = (new UpdateIndexStatsService($db))->run();

            AdminLogger::log('webhook_match_ended', $logToken, 'SUCCESS (via ' . $server . ($map !== '' ? ' - ' . $map : '') . ')');

            $this->json([
                'success' => true,
                'message' => 'Mise à jour déclenchée par webhook (' . $server . ').',
                'details' => [$updateStats, $generateJson, $indexStats],
            ]);
        } catch (\Throwable $e) {
            error_log('Webhook match ended : ' . $e->getMessage());
            AdminLogger::log('webhook_match_ended', $logToken, 'FAILED (' . $e->getMessage() . ')');
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Valide le token partagé (comparaison à temps constant).
     */
    private function authenticate(): bool
    {
        $expected = (string)env('SERVER_WEBHOOK_TOKEN', '');

        if ($expected === '') {
            return false;
        }

        $token = (string)($this->payload('token') ?? '');

        return hash_equals($expected, $token);
    }

    /**
     * Filtrage par IP optionnel (liste séparée par des virgules).
     * Vide = aucune restriction.
     */
    private function ipAllowed(): bool
    {
        $allowed = (string)env('SERVER_WEBHOOK_ALLOWED_IPS', '');

        if ($allowed === '') {
            return true;
        }

        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        foreach (array_map('trim', explode(',', $allowed)) as $entry) {
            if ($entry !== '' && $entry === $ip) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lit une valeur dans le corps JSON (php://input), puis $_POST, puis $_GET.
     * Compatible avec le POST JSON du plugin et les tests via curl.
     */
    private function payload(string $key, mixed $default = null): mixed
    {
        $raw = file_get_contents('php://input');

        if ($raw !== false && $raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data) && array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
}
