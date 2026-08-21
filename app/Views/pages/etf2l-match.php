<?php
/**
 * Détail d'un match ETF2L avec les rosters des équipes.
 * Variables attendues : $match, $teams, $mapsData, $result1, $result2, $dateMatch, $heureMatch.
 */
use App\Services\CountryFlags;

/** Badge Vainqueur/Perdant/Égalité ("win"|"loss"|"draw"|null). */
$resultBadge = static function (?string $result): string {
    if ($result === null) {
        return '';
    }
    $label = $result === 'win' ? 'Vainqueur' : ($result === 'loss' ? 'Perdant' : 'Égalité');

    return '<span class="team-result result-' . e($result) . '">' . e($label) . '</span>';
};
?>
<div class="etf2l-match-header">

    <a href="/" class="matchlog-back">
        <i class="fa-solid fa-arrow-left"></i> Retour à l'accueil
    </a>

    <div class="etf2l-match-title flex align-center gap-10 wrap">
        <h1>
            <span class="team-name"><?= e($match['team1_name']) ?></span>
            <span class="vs-separator">VS</span>
            <span class="team-name"><?= e($match['team2_name']) ?></span>
        </h1>
    </div>

    <div class="matchlog-meta flex align-center wrap">
        <span class="matchlog-meta-item">
            <i class="fa-regular fa-calendar"></i> <?= e($dateMatch) ?>
        </span>
        <span class="matchlog-meta-item">
            <i class="fa-regular fa-clock"></i> <?= e($heureMatch) ?>
        </span>
        <span class="matchlog-meta-item">
            <i class="fa-solid fa-trophy"></i> <?= e($match['competition_name']) ?>
        </span>
        <a href="https://etf2l.org/matches/<?= (int)$match['match_id'] ?>" target="_blank" rel="noopener" class="btn-match-link">
            <i class="fa-solid fa-arrow-up-right-from-square"></i> Voir sur ETF2L
        </a>
    </div>

</div>

<?php if (!empty($mapsData['maps'])): ?>
<div class="etf2l-maps-panel">
    <div class="etf2l-maps-head flex align-center gap-10">
        <span class="team-name"><?= e($match['team1_name']) ?></span>
        <?= $resultBadge($result1 ?? null) ?>
        <span class="vs-separator">VS</span>
        <?= $resultBadge($result2 ?? null) ?>
        <span class="team-name"><?= e($match['team2_name']) ?></span>
    </div>

    <div class="etf2l-maps-list">
        <?php foreach ($mapsData['maps'] as $map): ?>
            <div class="etf2l-map-row flex align-center">
                <span class="etf2l-map-label">
                    <?php if (count($mapsData['maps']) > 1): ?><b>M<?= (int)$map['order'] ?></b> — <?php endif; ?>
                    <?= e($map['map_display']) ?>
                </span>
                <?php if (isset($map['team1']) && isset($map['team2'])): ?>
                    <span class="etf2l-map-score">
                        <span class="score-value"><?= (int)$map['team1'] ?></span>
                        <span class="score-sep">-</span>
                        <span class="score-value"><?= (int)$map['team2'] ?></span>
                        <?php if ($map['golden_cap']): ?>
                            <span class="badge-gc">Golden Cap</span>
                        <?php endif; ?>
                    </span>
                <?php else: ?>
                    <span class="etf2l-map-score etf2l-map-pending">À jouer</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($mapsData['r1'] !== null && $mapsData['r2'] !== null): ?>
        <div class="etf2l-map-total flex align-center">
            <span class="etf2l-map-label"><b>Total</b></span>
            <span class="etf2l-map-score">
                <span class="score-value"><?= (int)$mapsData['r1'] ?></span>
                <span class="score-sep">-</span>
                <span class="score-value"><?= (int)$mapsData['r2'] ?></span>
            </span>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="etf2l-roster-grid">
    <?php foreach ($teams as $team): ?>
        <div class="etf2l-roster-panel">
            <div class="etf2l-roster-head flex align-center gap-10">
                <?php $flag = CountryFlags::flag($team['country'] ?? null); ?>
                <img loading="lazy" decoding="async" src="<?= e($flag) ?>" alt="<?= e($team['country'] ?? '') ?>" class="team-flag" title="<?= e($team['country'] ?? '') ?>">
                <span class="team-name"><?= e($team['name']) ?></span>
                <?= $resultBadge(($team['key'] ?? '') === 'team1' ? ($result1 ?? null) : ($result2 ?? null)) ?>
                <?php if (!empty($team['tag'])): ?>
                    <span class="badge-live-info">[<?= e($team['tag']) ?>]</span>
                <?php endif; ?>
            </div>

            <?php if (empty($team['players'])): ?>
                <p class="no-data">Aucun joueur répertorié pour cette équipe.</p>
            <?php else: ?>
                <ul class="etf2l-roster-list">
                    <?php foreach ($team['players'] as $player): ?>
                        <?php $pFlag = CountryFlags::flag($player['country'] ?? null); ?>
                        <li class="etf2l-roster-item flex align-center gap-10">
                            <img loading="lazy" decoding="async" src="<?= e($pFlag) ?>" alt="<?= e($player['country'] ?? '') ?>" class="team-flag" title="<?= e($player['country'] ?? '') ?>">
                            <a href="<?= e($player['profile_url']) ?>" class="roster-player-link" <?= $player['exists_on_site'] ? '' : 'target="_blank" rel="noopener"' ?>>
                                <?= e($player['name']) ?>
                                <?php if (!$player['exists_on_site']): ?>
                                    <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 0.6em; vertical-align: middle;"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-user" style="font-size: 0.6em; vertical-align: middle;"></i>
                                <?php endif; ?>
                            </a>
                            <span class="roster-player-role"><?= e($player['role']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>