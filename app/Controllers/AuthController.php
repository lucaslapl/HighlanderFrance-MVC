<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\PlayerRepository;
use App\Services\SteamApi;
use App\Services\SteamId;

/**
 * Authentification par OpenID Steam (login, callback, logout).
 */
final class AuthController extends Controller
{
    /**
     * GET /login — redirige vers l'OpenID Steam.
     */
    public function login(): void
    {
        try {
            $openid = $this->openid();
            $openid->returnUrl = $this->baseUrl() . '/auth/callback';
            $openid->identity = 'https://steamcommunity.com/openid';

            $this->redirect($openid->authUrl());
        } catch (\Throwable $e) {
            $this->view('pages/auth-error', [
                'title' => 'Erreur de connexion - ' . APP_NAME,
                'message' => 'Erreur : ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /auth/callback — retour de Steam OpenID.
     */
    public function callback(): void
    {
        $openid = $this->openid();

        if ($openid->mode == 'cancel') {
            $this->view('pages/auth-error', [
                'title' => 'Connexion annulée - ' . APP_NAME,
                'message' => "Connexion annulée par l'utilisateur.",
            ]);

            return;
        }

        if (!$openid->validate()) {
            $this->view('pages/auth-error', [
                'title' => 'Erreur de connexion - ' . APP_NAME,
                'message' => 'La validation a échoué.',
            ]);

            return;
        }

        $steamid64 = basename((string)$openid->identity);
        $steamid3 = SteamId::toSteamId3($steamid64);

        session_regenerate_id(true);
        $_SESSION['steamid'] = $steamid64;

        $repo = new PlayerRepository(Database::connection());
        $steamApi = new SteamApi();
        $user = $repo->findById($steamid3);

        if ($user === null) {
            // Nouvel inscrit : création puis synchronisation Steam
            $repo->createIfMissing($steamid3);
            $steamApi->syncOrCreatePlayer($steamid64);

            // Un nouvel inscrit n'est jamais admin par défaut
            $_SESSION['is_admin'] = false;
        } else {
            // Compte existant : created_at manquant = première connexion
            $repo->ensureCreatedAt($steamid3);

            // Joueur inscrit sans jamais avoir été synchronisé
            if (empty($user['name']) || $user['name'] === 'Nouveau Joueur') {
                $steamApi->syncOrCreatePlayer($steamid64);
            }

            $_SESSION['is_admin'] = (isset($user['is_admin']) && (int)$user['is_admin'] === 1);
        }

        $this->redirect('/profile/dashboard');
    }

    /**
     * GET /logout — détruit la session puis redirige vers l'accueil.
     */
    public function logout(): never
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        $this->redirect('/');
    }

    /**
     * Instance LightOpenID configurée pour l'environnement courant.
     */
    private function openid(): \LightOpenID
    {
        require_once APP_ROOT . '/_libs/openid.php';

        $openid = new \LightOpenID(parse_url($this->baseUrl(), PHP_URL_HOST));
        // Politique SSL commune : vérifié en production, désactivé en WAMP (pas de bundle CA).
        // LightOpenID passe par cURL (open_basedir ne s'applique pas à cURL),
        // qui utilise son propre bundle CA : aucune configuration de cafile n'est nécessaire.
        $openid->verify_peer = CURL_VERIFY_SSL;

        return $openid;
    }

    /**
     * URL de base du site.
     *
     * On privilégie APP_URL (config .env) quand elle est explicitement renseignée
     * (production) : évite l'injection de host / open redirect. Sinon on retombe
     * sur le host de la requête (dev WAMP sans .env), en ne faisant confiance au
     * header X-Forwarded-Proto que si un proxy est explicitement annoncé.
     * cf. helper site_url() (config/autoload.php).
     */
    private function baseUrl(): string
    {
        return site_url();
    }
}
