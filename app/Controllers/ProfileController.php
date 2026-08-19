<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\PlayerRepository;
use App\Models\PlayerStatsRepository;
use App\Services\Auth;
use App\Services\SteamApi;
use App\Services\SteamId;

/**
 * Profils joueurs : page publique, dashboard connecté, mises à jour et API stats.
 */
final class ProfileController extends Controller
{
    private const MODES = ['6s', '9v9'];

    private PlayerRepository $players;
    private PlayerStatsRepository $stats;

    public function __construct()
    {
        $this->players = new PlayerRepository(Database::connection());
        $this->stats = new PlayerStatsRepository(Database::connection());
    }

    /**
     * GET /profile/{steamid} — profil public d'un joueur.
     */
    public function profil(): void
    {
        $steamid64 = (string)$this->request->param('steamid', $this->request->get('steamid', ''));

        if (!preg_match('/^\d{17}$/', $steamid64)) {
            $this->abort(400);
        }

        $steamid3 = SteamId::toSteamId3($steamid64);
        $player = $this->players->findById($steamid3);

        if ($player === null) {
            $this->abort(404);
        }

        $playerName = $player['display_name'] ?? $player['name'];

        $this->view('pages/profile/profil', $this->pageData([
            'title' => 'Highlander France - Profil de ' . $playerName,
            'player' => $player,
            'playerName' => $playerName,
            'steamid64' => $steamid64,
            'steamid3' => $steamid3,
            'isOwnDashboard' => false,
        ]));
    }

    /**
     * GET /profile/dashboard — tableau de bord du joueur connecté.
     */
    public function dashboard(): void
    {
        if (!Auth::isLoggedIn()) {
            $this->redirect('/login');
        }

        $steamid64 = (string)Auth::steamId64();
        $steamid3 = SteamId::toSteamId3($steamid64);

        $user = $this->players->findById($steamid3);

        if ($user === null) {
            $this->players->createIfMissing($steamid3);
            $user = $this->players->findById($steamid3) ?? [];
        }

        // Synchronisation Steam si jamais faite ou périmée (> 24h)
        $lastUpdate = (int)($user['last_updated'] ?? 0);
        if (empty($user['name']) || $lastUpdate < time() - 86400) {
            (new SteamApi())->syncProfile($steamid3);
            $user = $this->players->findById($steamid3) ?? $user;
        }

        $playerName = $user['display_name'] ?? $user['name'] ?? 'Joueur';

        $this->view('pages/profile/dashboard', $this->pageData([
            'title' => 'Highlander France - Mon profil',
            'player' => $user,
            'playerName' => $playerName,
            'steamid64' => $steamid64,
            'steamid3' => $steamid3,
            'isOwnDashboard' => true,
            'isLocked' => (int)($user['country_locked'] ?? 0),
            'nameChanged' => (int)($user['name_changed'] ?? 0),
        ]));
    }

    /**
     * POST /profile/update-name — changement unique et définitif du pseudo.
     */
    public function updateName(): void
    {
        if (!Auth::isLoggedIn()) {
            $this->flashError("Action refusée : vous devez être connecté pour modifier votre nom d'affichage.", '/');
        }

        $steamid3 = SteamId::toSteamId3((string)Auth::steamId64());
        $newName = trim((string)$this->request->post('display_name', ''));

        if ($this->players->hasNameChanged($steamid3)) {
            $this->flashError("Vous avez déjà modifié votre nom d'affichage une fois. Action impossible.", '/profile/dashboard');
        }

        if ($newName === '') {
            $this->flashError("Le nom d'affichage ne peut pas être vide.", '/profile/dashboard');
        }

        if (mb_strlen($newName) > 32) {
            $this->flashError("Le nom d'affichage ne doit pas dépasser 32 caractères.", '/profile/dashboard');
        }

        $newName = strip_tags($newName);

        if ($this->players->updateDisplayName($steamid3, $newName)) {
            $this->flashSuccess("Votre nom d'affichage a été définitivement enregistré !", '/profile/dashboard');
        }

        $this->flashError("Une erreur est survenue lors de l'enregistrement.", '/profile/dashboard');
    }

    /**
     * POST /profile/update-country — choix unique et définitif de la nationalité.
     */
    public function updateCountry(): void
    {
        if (!Auth::isLoggedIn()) {
            $this->flashError("Action refusée : vous devez être connecté pour modifier votre nationalité.", '/');
        }

        $steamid3 = SteamId::toSteamId3((string)Auth::steamId64());
        $chosenCountry = strtolower(trim((string)$this->request->post('country', '')));

        if ($chosenCountry === '' || !in_array($chosenCountry, array_keys(COUNTRIES), true)) {
            $this->flashError('Pays invalide.', '/profile/dashboard');
        }

        if ($this->players->hasCountryLocked($steamid3)) {
            $this->flashError("Votre nationalité est déjà verrouillée et ne peut plus être modifiée.", '/profile/dashboard');
        }

        if ($this->players->updateCountry($steamid3, $chosenCountry)) {
            $this->flashSuccess("Votre nationalité a été enregistrée avec succès !", '/profile/dashboard');
        }

        $this->flashError("Une erreur est survenue lors de l'enregistrement.", '/profile/dashboard');
    }

    /**
     * GET /api/profile-stats?steamid=...&mode=... — statistiques JSON pour le profil.
     */
    public function profileStats(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $steamid64 = (string)$this->request->get('steamid', '');
        $mode = (string)$this->request->get('mode', '9v9');

        if ($steamid64 === '' || !preg_match('/^\d{17}$/', $steamid64) || !in_array($mode, self::MODES, true)) {
            $this->json(['error' => 'Paramètres invalides ou SteamID manquant.'], 400);

            return;
        }

        try {
            $this->json($this->statsForMode(SteamId::toSteamId3($steamid64), $mode));
        } catch (\PDOException) {
            $this->json(['error' => 'Erreur lors de la récupération des données.'], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function statsForMode(string $steamid3, string $mode): array
    {
        $matchStats = $this->stats->aggregate($steamid3, $mode);

        return [
            'total_matches'  => $this->stats->totalMatches($steamid3, $mode),
            'top_maps'       => $this->stats->topMaps($steamid3, $mode),
            'classes_played' => $this->stats->classesPlayed($steamid3, $mode),
            'recent_matches' => $this->stats->recentMatches($steamid3, $mode),
            'average_dpm'    => $matchStats['average_dpm'],
            'average_dtpm'   => $matchStats['average_dtpm'],
            'total_airshots' => $matchStats['total_airshots'],
            'total_captures' => $matchStats['total_captures'],
            'total_kills'    => $matchStats['total_kills'],
            'total_deaths'   => $matchStats['total_deaths'],
            'total_assists'  => $matchStats['total_assists'],
            'kd_ratio'       => $matchStats['kd_ratio'],
            'classes_killed' => $matchStats['classes_killed'],
        ];
    }

    /**
     * Données communes aux pages profil / dashboard (stats 9v9 + activité).
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function pageData(array $data): array
    {
        $steamid3 = $data['steamid3'];

        $rawDate = $data['player']['created_at'] ?? null;

        return array_merge($data, [
            'description' => APP_NAME . ' est une communauté compétitive francophone de Team Fortress 2, offrant un espace pour les joueurs de tous niveaux pour apprendre, jouer et progresser ensemble.',
            'styles' => ['/_css/profile.css'],
            'scripts' => [
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
                '/_js/profil.js',
            ],
            'stats' => $this->statsForMode($steamid3, '9v9'),
            'activityData' => $this->stats->activity($steamid3),
            'dateFormatee' => !empty($rawDate) ? date('d/m/Y', strtotime((string)$rawDate)) : false,
            'countries' => COUNTRIES,
            'country' => $data['player']['country'] ?? null,
        ]);
    }
}
