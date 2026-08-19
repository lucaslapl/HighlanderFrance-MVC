<?php

declare(strict_types=1);

use App\Controllers\Admin\AdminController;
use App\Controllers\Admin\ApiController as AdminApiController;
use App\Controllers\ApiController;
use App\Controllers\AuthController;
use App\Controllers\PageController;
use App\Controllers\ProfileController;
use App\Controllers\ServerHookController;

/** @var \App\Core\Router $router */
$router = new \App\Core\Router();

// --- Pages publiques ---
$router->get('/', PageController::class, 'home');
$router->get('/index', PageController::class, 'home');
$router->get('/index.php', PageController::class, 'home');
$router->get('/staff', PageController::class, 'staff');
$router->get('/hall-of-fame', PageController::class, 'hallOfFame');
$router->get('/match-logs', PageController::class, 'matchLogs');
$router->get('/log/{id}', PageController::class, 'matchLog');
$router->get('/log/match-log', PageController::class, 'matchLog');
$router->get('/confidentialite', PageController::class, 'privacy');
$router->get('/sitemap.xml', PageController::class, 'sitemap');

// --- API JSON ---
$router->get('/api/index-stats', ApiController::class, 'indexStats');
$router->get('/api/logs', ApiController::class, 'logs');
$router->get('/api/leaderboard', ApiController::class, 'leaderboard');
$router->get('/api/search-players', ApiController::class, 'searchPlayers');
$router->get('/api/live-matches', ApiController::class, 'liveMatches');

// --- Webhook serveurs de match (plugin SourceMod hlfr_match_log) ---
$router->post('/api/server/match-ended', ServerHookController::class, 'matchEnded');

// --- Live des serveurs de match (plugin SourceMod hlfr_live_match) ---
$router->post('/api/server/live-status', ServerHookController::class, 'liveStatus');

// --- Match en direct ---
$router->get('/live/{server}', PageController::class, 'liveMatch');

// --- API admin ---
$router->post('/api/admin/blacklist', AdminApiController::class, 'blacklist');
$router->post('/api/admin/match-mode', AdminApiController::class, 'matchMode');
$router->post('/api/admin/player-update', AdminApiController::class, 'playerUpdate');

// --- Panel admin ---
$router->get('/admin/dashboard', AdminController::class, 'dashboard');
$router->get('/admin/list-staff', AdminController::class, 'listStaff');
$router->get('/admin/manage-blacklist', AdminController::class, 'manageBlacklist');
$router->get('/admin/manage-player/{steamid}', AdminController::class, 'managePlayer');
$router->get('/admin/manage-player', AdminController::class, 'managePlayer');
$router->any('/admin/match-logs', AdminController::class, 'matchLogs');
$router->any('/admin/run-cron-manual', AdminController::class, 'runCronManual');
$router->any('/admin/view-logs', AdminController::class, 'viewLogs');

// --- Authentification ---
$router->get('/login', AuthController::class, 'login');
$router->get('/auth/callback', AuthController::class, 'callback');
$router->get('/logout', AuthController::class, 'logout');

// --- Profils ---
$router->get('/profile/dashboard', ProfileController::class, 'dashboard');
$router->get('/profile/{steamid}', ProfileController::class, 'profil');
$router->get('/profile/profil', ProfileController::class, 'profil');
$router->post('/profile/update-name', ProfileController::class, 'updateName');
$router->post('/profile/update-country', ProfileController::class, 'updateCountry');

// --- API profils ---
$router->get('/api/profile-stats', ProfileController::class, 'profileStats');

return $router;
