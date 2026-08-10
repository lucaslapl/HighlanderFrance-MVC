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
use App\Services\SteamId;

final class PageController extends Controller
{
    public function home(): void
    {
        $repo = new Etf2lRepository(Database::connection());
        $prochainsMatchs = $repo->upcomingMatches(5);

        $this->view('pages/home', [
            'title' => 'Highlander France - Communauté Compétitive de TF2',
            'description' => 'Highlander France est une communauté compétitive francophone de Team Fortress 2, offrant un espace pour les joueurs de tous niveaux pour apprendre, jouer et progresser ensemble.',
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
            'description' => 'Highlander France est une communauté compétitive francophone de Team Fortress 2, offrant un espace pour les joueurs de tous niveaux pour apprendre, jouer et progresser ensemble.',
            'groups' => $groups,
            'pageScripts' => partial('partials/scroll_animation'),
        ]);
    }

    public function hallOfFame(): void
    {
        $this->view('pages/hall-of-fame', [
            'title' => 'Highlander France - Hall of Fame',
            'description' => 'Highlander France est une communauté compétitive francophone de Team Fortress 2, offrant un espace pour les joueurs de tous niveaux pour apprendre, jouer et progresser ensemble.',
            'scripts' => ['/_js/leaderboard.js', '/_js/search_players.js'],
            'pageScripts' => partial('partials/scroll_animation') . partial('partials/hall_of_fame_script'),
        ]);
    }

    public function matchLogs(): void
    {
        $this->view('pages/match-logs', [
            'title' => 'Highlander France - Logs des Matchs',
            'description' => 'Highlander France est une communauté compétitive francophone de Team Fortress 2, offrant un espace pour les joueurs de tous niveaux pour apprendre, jouer et progresser ensemble.',
            'pageScripts' => partial('partials/scroll_animation') . partial('partials/match_logs_script', [
                'isAdmin' => Auth::isAdmin(),
            ]),
        ]);
    }

    public function privacy(): void
    {
        $this->view('pages/privacy', [
            'title' => 'Highlander France - Politique de Confidentialité',
            'description' => 'Highlander France est une communauté compétitive francophone de Team Fortress 2, offrant un espace pour les joueurs de tous niveaux pour apprendre, jouer et progresser ensemble.',
        ]);
    }

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
        $mapDisplay = self::mapDisplay((string)($entry['map'] ?? ''));

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
            'description' => 'Highlander France est une communauté compétitive francophone de Team Fortress 2, offrant un espace pour les joueurs de tous niveaux pour apprendre, jouer et progresser ensemble.',
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

        $redPlayers = [];
        $bluePlayers = [];
        $otherPlayers = [];
        $hasTeamData = false;

        foreach ($players as $p) {
            $team = $p['team'] ?? null;
            if ($team === 'red') {
                $redPlayers[] = $p;
                $hasTeamData = true;
            } elseif ($team === 'blue') {
                $bluePlayers[] = $p;
                $hasTeamData = true;
            } else {
                $otherPlayers[] = $p;
            }
        }

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
            $panel['result'] = self::teamResult($panel['score'], $panel['otherScore']);
        }
        unset($panel);

        $this->view('pages/match-log', [
            'title' => 'Highlander France - ' . self::mapDisplay((string)$log['map_name']) . ' | ' . $gameModeLabel,
            'description' => 'Highlander France est une communauté compétitive francophone de Team Fortress 2, offrant un espace pour les joueurs de tous niveaux pour apprendre, jouer et progresser ensemble.',
            'logId' => $logId,
            'mapDisplay' => self::mapDisplay((string)$log['map_name']),
            'gameMode' => $gameMode,
            'gameModeLabel' => $gameModeLabel,
            'matchDate' => $log['date'] !== null ? date('d/m/Y à H:i', $log['date']) : null,
            'durationDisplay' => self::duration((int)$log['length']),
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

    private static function mapDisplay(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '—';
        }

        $names = [];
        foreach (preg_split('/\s*\+\s*/', $raw) as $p) {
            $p = preg_replace('/_(final|rc|v|b|f)\d*$/i', '', $p);
            $p = preg_replace('/^(koth|cp|pl|plr|ctf|td|dom|tc|arena|mvm|sd|pass|rd|pd|vsh|ph|zr|dr|slay)_/i', '', $p);
            $p = ucwords(preg_replace('/_/', ' ', trim($p)));
            if ($p !== '') {
                $names[] = $p;
            }
        }

        return implode(' + ', $names);
    }

    private static function duration(int $seconds): ?string
    {
        if ($seconds <= 0) {
            return null;
        }

        return sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);
    }

    private static function teamResult(?int $score, ?int $otherScore): ?string
    {
        if ($score === null || $otherScore === null) {
            return null;
        }
        if ($score > $otherScore) {
            return 'win';
        }
        if ($score < $otherScore) {
            return 'loss';
        }

        return 'draw';
    }
}
