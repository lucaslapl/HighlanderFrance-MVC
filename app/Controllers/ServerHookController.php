<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AdminLogger;
use App\Services\Crons\GenerateJsonService;
use App\Services\Crons\UpdateIndexStatsService;
use App\Services\Crons\UpdateStatsService;
use App\Services\LiveMatches;

/**
 * Endpoint public appelé par le plugin SourceMod (hlfr_match_log) à la fin
 * d'un match. Authentifié par un token partagé (SERVER_WEBHOOK_TOKEN),
 * sans session utilisateur. Déclenche la chaîne de mise à jour des stats
 * et du leaderboard (même pipeline que le CRON, en temps réel) :
 * update_stats → sync_steam → generate_json → update_index_stats.
 */
final class ServerHookController extends Controller
{
    private const LOCK_FILE = DATA_DIR . '/webhook_match.lock';

    private ?array $cachedBody = null;

    /**
     * POST /api/server/live-status
     * Body (JSON) : { token, server, map, status: "live"|"ended", scores,
     *                players[], started_at, updated_at, stv }
     *
     * Léger (aucune pipeline stats) : écrit simplement l'état live dans le
     * cache des matchs en cours, consommé par /api/live-matches.
     */
    public function liveStatus(): void
    {
        $server = (string)($this->payload('server') ?? '');
        $status = (string)($this->payload('status') ?? '');
        $who = $server !== '' ? $server : 'inconnu';

        if (!$this->authenticate()) {
            AdminLogger::log('webhook_live_status', null, 'FAILED (token invalide - ' . $who . ')');
            $this->json(['success' => false, 'message' => 'Non autorisé.'], 403);

            return;
        }

        if (!$this->ipAllowed()) {
            AdminLogger::log('webhook_live_status', null, 'FAILED (IP non autorisée - ' . $who . ')');
            $this->json(['success' => false, 'message' => 'IP non autorisée.'], 403);

            return;
        }

        if ($server === '' || !in_array($status, ['live', 'ended'], true)) {
            $this->json(['success' => false, 'message' => 'Paramètres invalides.'], 400);

            return;
        }

        $accepted = LiveMatches::apply($server, $status, $this->body());

        $this->json([
            'success' => $accepted,
            'message' => $accepted ? 'Statut mis à jour.' : 'Statut obsolète ignoré.',
        ]);
    }

    /**
     * POST /api/discord/member-count
     * Body (JSON) : { token, member_count, guild_id? }
     *
     * Appelé par le bot Discord (octave.highlanderfrance.tf) à chaque
     * arrivée/départ de membre et en sync périodique. Écrit le dernier
     * compteur connu dans le cache consommé par /api/index-stats.
     */
    public function discordMemberCount(): void
    {
        if (!$this->discordAuthenticate()) {
            AdminLogger::log('webhook_discord_member_count', null, 'FAILED (token invalide)');
            $this->json(['success' => false, 'message' => 'Non autorisé.'], 403);

            return;
        }

        $count = $this->payload('member_count');

        if (!is_numeric($count)) {
            $this->json(['success' => false, 'message' => 'member_count invalide.'], 400);

            return;
        }

        $count = (int)$count;

        if ($count <= 0 || $count > 10000000) {
            $this->json(['success' => false, 'message' => 'member_count hors limites.'], 400);

            return;
        }

        // Vérification optionnelle du serveur concerné (DISCORD_GUILD_ID).
        $expectedGuild = (string)env('DISCORD_GUILD_ID', '');
        if ($expectedGuild !== '') {
            $guildId = (string)($this->payload('guild_id') ?? '');

            if ($guildId !== '' && !hash_equals($expectedGuild, $guildId)) {
                AdminLogger::log('webhook_discord_member_count', null, 'FAILED (guild_id inattendu - ' . $guildId . ')');
                $this->json(['success' => false, 'message' => 'Guild non autorisée.'], 403);

                return;
            }
        }

        $cache = [
            'members' => $count,
            'updated_at' => time(),
        ];

        if (file_put_contents(DATA_DIR . '/cache_discord_stats.json', json_encode($cache), LOCK_EX) === false) {
            AdminLogger::log('webhook_discord_member_count', null, 'FAILED (écriture cache impossible)');
            $this->json(['success' => false, 'message' => 'Écriture du cache impossible.'], 500);

            return;
        }

        AdminLogger::log('webhook_discord_member_count', null, 'SUCCESS (' . $count . ' membres)');

        $this->json(['success' => true, 'message' => 'Compteur mis à jour.', 'members' => $count]);
    }

    /**
     * Valide le token partagé du bot Discord (comparaison à temps constant).
     */
    private function discordAuthenticate(): bool
    {
        $expected = (string)env('DISCORD_WEBHOOK_TOKEN', '');

        if ($expected === '') {
            return false;
        }

        $token = (string)($this->payload('token') ?? '');

        return hash_equals($expected, $token);
    }

    /**
     * POST /api/server/match-ended
     * Body (JSON) : { token, server, map }
     */
    public function matchEnded(): void
    {
        $server = (string)($this->payload('server') ?? 'inconnu');
        $map = (string)($this->payload('map') ?? '');
        $who = $server . ($map !== '' ? ' - ' . $map : '');

        if (!$this->authenticate()) {
            AdminLogger::log('webhook_match_ended', null, 'FAILED (token invalide - ' . $who . ')');
            $this->json(['success' => false, 'message' => 'Non autorisé.'], 403);

            return;
        }

        if (!$this->ipAllowed()) {
            AdminLogger::log('webhook_match_ended', null, 'FAILED (IP non autorisée - ' . $who . ')');
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
            $syncSteam = (new SyncSteamService($db))->run();
            $generateJson = (new GenerateJsonService($db))->run();
            $indexStats = (new UpdateIndexStatsService($db))->run();

            AdminLogger::log('webhook_match_ended', $logToken, 'SUCCESS (via ' . $who . ')');

            $this->json([
                'success' => true,
                'message' => 'Mise à jour déclenchée par webhook (' . $server . ').',
                'processed_logs' => $this->extractProcessedLogs($updateStats),
                'details' => [$updateStats, $syncSteam, $generateJson, $indexStats],
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
     * Extrait le nombre de nouveaux logs traités du message renvoyé par
     * UpdateStatsService, pour l'exposer au plugin SourceMod (option A).
     * Renvoie -1 si le message ne peut pas être interprété.
     */
    private function extractProcessedLogs(string $message): int
    {
        if (preg_match('/Nouveaux logs traités\s*:\s*(\d+)/i', $message, $m) === 1) {
            return (int)$m[1];
        }

        return -1;
    }

    /**
     * Lit une valeur dans le corps JSON (php://input), puis $_POST, puis $_GET.
     * Compatible avec le POST JSON du plugin et les tests via curl.
     */
    private function payload(string $key, mixed $default = null): mixed
    {
        $body = $this->body();

        if (array_key_exists($key, $body)) {
            return $body[$key];
        }

        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /**
     * Corps JSON décodé, mis en cache pour éviter de relire php://input à
     * chaque appel de payload().
     */
    private function body(): array
    {
        if ($this->cachedBody !== null) {
            return $this->cachedBody;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        $this->cachedBody = is_array($data) ? $data : [];

        return $this->cachedBody;
    }
}
