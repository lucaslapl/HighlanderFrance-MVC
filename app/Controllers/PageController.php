<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Etf2lRepository;
use App\Models\MatchLogRepository;
use App\Models\PlayerRepository;
use App\Services\Auth;
use App\Services\LiveMatches;
use App\Services\MatchFormat;
use App\Services\SteamId;

final class PageController extends Controller
{
    public function home(): void
    {
        $repo = new Etf2lRepository(Database::connection());
        $prochainsMatchs = $repo->upcomingMatches(5);

        $this->view('pages/home', [
            'title' => 'Highlander France - Communauté Compétitive de TF2',
            'description' => site_description(),
            'prochainsMatchs' => $prochainsMatchs,
            'pageScripts' => partial('partials/index_stats_script'),
        ]);
    }

    public function staff(): void
    {
        $repo = new PlayerRepository(Database::connection());
        $members = $repo->staffMembers();

        $groups = ['founders' => [], 'mentors' => [], 'mixers' => [], 'moderators' => []];
        $roleMap = [
            'founders' => 'is_founder',
            'mentors' => 'is_mentor',
            'mixers' => 'is_mixer',
            'moderators' => 'is_moderator',
        ];

        foreach ($members as $member) {
            $member['final_name'] = !empty($member['display_name']) ? $member['display_name'] : $member['name'];
            $member['profile_url'] = '/profile/' . SteamId::toSteamId64($member['steamid']);

            foreach ($roleMap as $group => $column) {
                if ((int)$member[$column] === 1) {
                    $groups[$group][] = $member;
                }
            }
        }

        $this->view('pages/staff', [
            'title' => "Highlander France - L'équipe",
            'description' => site_description(),
            'groups' => $groups,
            'pageScripts' => partial('partials/scroll_animation'),
        ]);
    }

    public function hallOfFame(): void
    {
        $this->view('pages/hall-of-fame', [
            'title' => 'Highlander France - Hall of Fame',
            'description' => site_description(),
            'scripts' => ['/_js/leaderboard.js', '/_js/search_players.js'],
            'pageScripts' => partial('partials/scroll_animation') . partial('partials/hall_of_fame_script'),
        ]);
    }

    public function matchLogs(): void
    {
        $this->view('pages/match-logs', [
            'title' => 'Highlander France - Logs des Matchs',
            'description' => site_description(),
            'pageScripts' => partial('partials/scroll_animation') . partial('partials/match_logs_script', [
                'isAdmin' => Auth::isAdmin(),
            ]),
        ]);
    }

    public function privacy(): void
    {
        $this->view('pages/privacy', [
            'title' => 'Highlander France - Politique de Confidentialité',
            'description' => site_description(),
        ]);
    }

    /**
     * GET /sitemap.xml — sitemap dynamique (pages statiques + logs + profils).
     */
    public function sitemap(): void
    {
        $db = Database::connection();

        $logs = (new MatchLogRepository($db))->sitemapLogs();
        $players = (new PlayerRepository($db))->allSteamIds();

        $base = site_url();

        // Pages statiques : [path, priority, changefreq]
        $staticPages = [
            '/'               => [1.0, 'always'],
            '/staff'          => [0.8, 'monthly'],
            '/hall-of-fame'   => [0.8, 'daily'],
            '/match-logs'     => [0.8, 'daily'],
            '/confidentialite' => [0.3, 'yearly'],
        ];

        $block = static function (string $url, ?string $lastmod, float $priority, string $changefreq): string {
            $out = "  <url>\n    <loc>" . e($url) . "</loc>\n";
            if ($lastmod !== null) {
                $out .= "    <lastmod>" . $lastmod . "</lastmod>\n";
            }
            $out .= "    <priority>" . rtrim(rtrim(number_format($priority, 1), '0'), '.') . "</priority>\n";
            $out .= "    <changefreq>" . $changefreq . "</changefreq>\n  </url>";

            return $out;
        };

        $lines = [];
        foreach ($staticPages as $path => [$priority, $change]) {
            $lines[] = $block($base . $path, null, $priority, $change);
        }

        // Dernier match comme référence de fraîcheur pour /match-logs et l'accueil.
        $lastMatchDate = null;
        if ($logs !== []) {
            foreach ($logs as $log) {
                if (is_int($log['date'])) {
                    $lastMatchDate = $log['date'];
                    break;
                }
            }
        }

        foreach ($logs as $log) {
            $lastmod = null;
            if (is_int($log['date']) && $log['date'] > 0) {
                $lastmod = date('Y-m-d', $log['date']);
            } elseif ($lastMatchDate !== null) {
                $lastmod = date('Y-m-d', $lastMatchDate);
            }
            $lines[] = $block($base . '/log/' . $log['id'], $lastmod, 0.5, 'weekly');
        }

        foreach ($players as $steamid3) {
            $steamid64 = SteamId::toSteamId64($steamid3);
            if ($steamid64 === null) {
                continue;
            }
            $lines[] = $block($base . '/profile/' . $steamid64, null, 0.4, 'monthly');
        }

        header('Content-Type: application/xml; charset=utf-8');
        echo self::XML_DECL . implode("\n", $lines) . "\n</urlset>";
    }

    private const XML_DECL = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    /**
     * Page d'un match en direct (GET /live/{server}).
     * Source : cache alimenté par le plugin hlfr_live_match.
     */
    public function liveMatch(): void
    {
        $server = (string)($this->request?->param('server', '') ?? '');
        $entry = $server !== '' ? LiveMatches::get($server) : null;

        if ($entry === null) {
            $this->abort(404);
        }

        $entry = LiveMatches::enrich($entry);
        $mapDisplay = MatchFormat::mapDisplay((string)($entry['map'] ?? ''));

        $redPlayers = [];
        $bluePlayers = [];

        foreach (($entry['players'] ?? []) as $p) {
            if (($p['team'] ?? '') === 'red') {
                $redPlayers[] = $p;
            } else {
                $bluePlayers[] = $p;
            }
        }

        $redScore = (int)($entry['scores']['red'] ?? 0);
        $blueScore = (int)($entry['scores']['blue'] ?? 0);

        $this->view('pages/live-match', [
            'title' => 'Highlander France - ' . $mapDisplay . ' | En direct',
            'description' => site_description(),
            'server' => $server,
            'entry' => $entry,
            'mapDisplay' => $mapDisplay,
            'redPlayers' => $redPlayers,
            'bluePlayers' => $bluePlayers,
            'redScore' => $redScore,
            'blueScore' => $blueScore,
            'playerCount' => count($redPlayers) + count($bluePlayers),
            'startedAt' => date('H:i', (int)($entry['started_at'] ?? time())),
        ]);
    }

    /**
     * Détail d'un match (GET /log/{id}).
     */
    public function matchLog(): void
    {
        $request = $this->request;
        $logId = (int)($request?->param('id', $request?->get('id', 0)) ?? 0);

        if ($logId <= 0) {
            $this->abort(400);
        }

        $repo = new MatchLogRepository(Database::connection());

        if (in_array($logId, $repo->blacklistedIds(), true)) {
            $this->abort(404);
        }

        $log = $repo->matchDetail($logId);

        if ($log === null) {
            $this->abort(404);
        }

        $players = $log['players'];
        $gameMode = $log['game_mode'] === '6S' ? '6S' : '9V9';
        $gameModeLabel = $gameMode === '6S' ? 'Sixes (6v6)' : 'Highlander (9v9)';

        $partition = MatchFormat::partitionPlayers($players);
        $redPlayers = $partition['red'];
        $bluePlayers = $partition['blue'];
        $otherPlayers = $partition['other'];
        $hasTeamData = $partition['hasTeams'];

        $redScore = $log['red_score'];
        $blueScore = $log['blue_score'];

        if ($redScore !== null || $blueScore !== null) {
            $hasTeamData = true;
        }

        $teamPanels = [
            ['key' => 'blue', 'name' => 'BLU', 'players' => $bluePlayers, 'score' => $blueScore, 'otherScore' => $redScore],
            ['key' => 'red', 'name' => 'RED', 'players' => $redPlayers, 'score' => $redScore, 'otherScore' => $blueScore],
        ];

        if ($redScore !== null && $blueScore !== null && $redScore > $blueScore) {
            $teamPanels = array_reverse($teamPanels);
        }

        foreach ($teamPanels as &$panel) {
            $panel['result'] = MatchFormat::teamResult($panel['score'], $panel['otherScore']);
        }
        unset($panel);

        $matchDate = $log['date'] !== null ? date('d/m/Y à H:i', $log['date']) : null;

        $this->view('pages/match-log', [
            'title' => 'Highlander France - ' . MatchFormat::mapDisplay((string)$log['map_name']) . ' | ' . $gameModeLabel,
            'description' => site_description(),
            'ogType' => 'article',
            'logId' => $logId,
            'mapDisplay' => MatchFormat::mapDisplay((string)$log['map_name']),
            'gameMode' => $gameMode,
            'gameModeLabel' => $gameModeLabel,
            'matchDate' => $matchDate,
            'durationDisplay' => MatchFormat::duration((int)$log['length']),
            'playerCount' => count($players),
            'hasTeamData' => $hasTeamData,
            'redScore' => $redScore,
            'blueScore' => $blueScore,
            'players' => $players,
            'teamPanels' => $teamPanels,
            'otherPlayers' => $otherPlayers,
            'isAdmin' => Auth::isAdmin(),
            'pageScripts' => partial('partials/match_log_script', ['isAdmin' => Auth::isAdmin()]),
        ]);
    }
}
