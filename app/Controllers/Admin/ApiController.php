<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Models\AdminRepository;
use App\Models\MatchLogRepository;
use App\Services\Auth;
use App\Services\SteamId;

/**
 * Endpoints JSON réservés aux administrateurs.
 * En cas de soumission de formulaire (non-AJAX), réponse via flash + redirection.
 */
final class ApiController extends Controller
{
    /**
     * Ajoute / retire un log de la blacklist (Match Stats).
     * POST /api/admin/blacklist  { action: add|remove, log_id, reason? }
     */
    public function blacklist(): void
    {
        Auth::requireAdmin();

        if (($this->request?->method() ?? 'GET') !== 'POST') {
            $this->json(['success' => false, 'message' => 'Méthode non autorisée.'], 405);

            return;
        }

        $action = (string)($this->request->post('action', '') ?? '');
        $logId = (string)($this->request->post('log_id', '') ?? '');
        $reason = trim((string)($this->request->post('reason', '') ?? ''));

        if (!ctype_digit($logId)) {
            $this->respond(false, 'ID de log invalide.');

            return;
        }

        $repo = new MatchLogRepository(Database::connection());
        $logId = (int)$logId;

        if ($action === 'add') {
            $added = $repo->blacklist($logId, $reason !== '' ? $reason : null, (string)Auth::steamId64());
            if ($added) {
                $repo->invalidateLogsCache();
            }

            $this->respond(
                $added,
                $added ? "Le log #$logId a été blacklisté avec succès." : "Le log #$logId est déjà blacklisté.",
            );
        } elseif ($action === 'remove') {
            $removed = $repo->unblacklist($logId);
            if ($removed) {
                $repo->invalidateLogsCache();
            }

            $this->respond(
                $removed,
                $removed ? "Le log #$logId a été retiré de la blacklist." : "Le log #$logId n'est pas dans la blacklist.",
            );
        } else {
            $this->respond(false, 'Action non reconnue.');
        }
    }

    /**
     * Change le mode (6s/9v9) d'un log en base.
     * POST /api/admin/match-mode  { action: switch_mode, log_id, mode }
     */
    public function matchMode(): void
    {
        Auth::requireAdmin();

        if (($this->request?->method() ?? 'GET') !== 'POST') {
            $this->json(['success' => false, 'message' => 'Méthode non autorisée.'], 405);

            return;
        }

        $action = (string)($this->request->post('action', '') ?? '');
        $logId = (string)($this->request->post('log_id', '') ?? '');
        $mode = strtolower(trim((string)($this->request->post('mode', '') ?? '')));

        if (!ctype_digit($logId)) {
            $this->respond(false, 'ID de log invalide.');

            return;
        }
        if (!in_array($mode, ['6s', '9v9'], true)) {
            $this->respond(false, 'Mode de jeu invalide (6s ou 9v9 attendu).');

            return;
        }
        if ($action !== 'switch_mode') {
            $this->respond(false, 'Action non reconnue.');

            return;
        }

        $result = (new AdminRepository(Database::connection()))->switchMatchMode((int)$logId, $mode);
        $this->respond($result['success'], $result['message']);
    }

    /**
     * Mise à jour globale du profil d'un joueur (pseudo, pays, rôles, verrous).
     * POST /api/admin/player-update  { target_steamid, display_name, country, is_*, reset_* }
     */
    public function playerUpdate(): void
    {
        Auth::requireAdmin();

        if (($this->request?->method() ?? 'GET') !== 'POST') {
            $this->json(['success' => false, 'message' => 'Méthode non autorisée.'], 405);

            return;
        }

        $targetSteamid = (string)($this->request->post('target_steamid', '') ?? '');

        if ($targetSteamid === '' || !preg_match('/^\d{17}$/', $targetSteamid)) {
            $_SESSION['error'] = 'Erreur : SteamID64 invalide.';
            $this->redirect('/admin/dashboard');
        }

        $displayName = trim((string)($this->request->post('display_name', '') ?? ''));
        $country = strtolower(trim((string)($this->request->post('country', 'unknown') ?? 'unknown')));

        if ($displayName === '') {
            $_SESSION['error'] = "Le pseudo d'affichage ne peut pas être vide.";
            $this->redirect('/admin/manage-player/' . urlencode($targetSteamid));
        }

        $steamid3 = SteamId::toSteamId3($targetSteamid);

        try {
            $updated = (new AdminRepository(Database::connection()))->updatePlayer(
                steamid3: $steamid3,
                displayName: $displayName,
                country: $country,
                isFounder: isset($_POST['is_founder']) ? 1 : 0,
                isModerator: isset($_POST['is_moderator']) ? 1 : 0,
                isMentor: isset($_POST['is_mentor']) ? 1 : 0,
                isMixer: isset($_POST['is_mixer']) ? 1 : 0,
                resetNameChange: isset($_POST['reset_name_change']),
                resetCountryChange: isset($_POST['reset_country_change']),
            );

            if ($updated) {
                $_SESSION['success'] = 'Le profil de ' . htmlspecialchars($displayName) . ' a été mis à jour avec succès !';
            } else {
                $_SESSION['error'] = 'Le joueur est introuvable ou aucune modification n\'a été détectée.';
            }
        } catch (\PDOException $e) {
            $_SESSION['error'] = 'Erreur BDD lors de l\'enregistrement : ' . $e->getMessage();
        }

        $this->redirect('/admin/manage-player/' . urlencode($targetSteamid));
    }

    /**
     * Répond en JSON pour les requêtes AJAX, sinon flash + redirection (formulaires).
     */
    private function respond(bool $success, string $message): never
    {
        if ($this->request?->isAjax()) {
            $this->json(['success' => $success, 'message' => $message]);
            exit;
        }

        $_SESSION[$success ? 'success' : 'error'] = $message;
        $this->redirect('/admin/manage-blacklist');
    }
}
