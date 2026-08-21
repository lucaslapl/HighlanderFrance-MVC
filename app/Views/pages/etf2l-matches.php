<?php
/**
 * Historique des matchs passés des équipes FR (ETF2L).
 * Variables attendues : $matches, $currentPage, $totalPages, $totalMatches.
 */
use App\Services\CountryFlags;
?>
<div class="matchlog-back-wrap">
    <a href="/" class="matchlog-back">
        <i class="fa-solid fa-arrow-left"></i> Retour à l'accueil
    </a>
</div>

<div class="etf2l-agenda-container">
    <div class="agenda-header flex space-between align-center">
        <h3><i class="fa-solid fa-calendar-days"></i> Matchs Équipes FR (ETF2L)</h3>
        <span class="badge-live-info"><?= (int)$totalMatches ?> match(s)</span>
    </div>

    <?php if (empty($matches)): ?>
        <div class="agenda-empty">
            <p><i class="fa-solid fa-circle-info"></i> Aucun match passé pour le moment.</p>
        </div>
    <?php else: ?>
        <div class="agenda-list">
            <?php foreach ($matches as $match): ?>
                <?php
                $dt = new DateTime('@' . (int)$match['match_date']);
                $dt->setTimezone(new DateTimeZone('Europe/Paris'));
                $dateMatch = $dt->format('d/m/Y');
                $heureMatch = $dt->format('H:i');
                $flag1 = CountryFlags::flag($match['team1_country'] ?? null);
                $flag2 = CountryFlags::flag($match['team2_country'] ?? null);

                $r1 = isset($match['r1']) && $match['r1'] !== null ? (int)$match['r1'] : null;
                $r2 = isset($match['r2']) && $match['r2'] !== null ? (int)$match['r2'] : null;
                $hasScore = $r1 !== null && $r2 !== null;
                $win1 = $hasScore && $r1 > $r2;
                $win2 = $hasScore && $r2 > $r1;
                ?>
                <div class="agenda-item flex align-center">

                    <div class="match-date-box text-center">
                        <span class="match-date"><?= $dateMatch ?></span>
                        <span class="match-hour"><?= $heureMatch ?></span>
                    </div>

                    <div class="match-details flex-1">
                        <div class="competition-title"><?= e($match['competition_name']) ?></div>
                        <div class="teams-line flex align-center">

                            <span class="team-name text-right flex align-center justify-end gap-10<?= $win1 ? ' winner' : '' ?>">
                                <img loading="lazy" decoding="async" src="<?= e($flag1) ?>" alt="<?= ucfirst(e($match['team1_country'])) ?>" class="team-flag" title="<?= ucfirst(e($match['team1_country'])) ?>">
                                <span class="truncate-text"><?= e($match['team1_name']) ?></span>
                            </span>

                            <?php if ($hasScore): ?>
                                <span class="agenda-score flex align-center gap-10">
                                    <span class="score-value<?= $win1 ? ' score-winner' : '' ?>"><?= $r1 ?></span>
                                    <span class="score-sep">-</span>
                                    <span class="score-value<?= $win2 ? ' score-winner' : '' ?>"><?= $r2 ?></span>
                                </span>
                            <?php else: ?>
                                <span class="vs-separator">VS</span>
                            <?php endif; ?>

                            <span class="team-name text-left flex align-center gap-10<?= $win2 ? ' winner' : '' ?>">
                                <span class="truncate-text"><?= e($match['team2_name']) ?></span>
                                <img loading="lazy" decoding="async" src="<?= e($flag2) ?>" alt="<?= ucfirst(e($match['team2_country'])) ?>" class="team-flag" title="<?= ucfirst(e($match['team2_country'])) ?>">
                            </span>

                        </div>
                    </div>

                    <div class="match-action">
                        <a href="/match/<?= (int)$match['match_id'] ?>" class="btn-match-link" title="Voir le match et les rosters">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $pageUrl = static fn(int $p): string => '/matchs' . ($p > 1 ? '?page=' . $p : '');
            $start = max(1, $currentPage - 2);
            $end = min($totalPages, $currentPage + 2);
            ?>
            <?php if ($currentPage > 1): ?>
                <a href="<?= $pageUrl($currentPage - 1) ?>" class="page-btn nav">&laquo; Précédent</a>
            <?php endif; ?>
            <?php if ($start > 1): ?>
                <a href="<?= $pageUrl(1) ?>" class="page-btn">1</a>
                <?php if ($start > 2): ?><span class="page-ellipsis">…</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($p = $start; $p <= $end; $p++): ?>
                <?php if ($p === $currentPage): ?>
                    <span class="page-btn active"><?= $p ?></span>
                <?php else: ?>
                    <a href="<?= $pageUrl($p) ?>" class="page-btn"><?= $p ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><span class="page-ellipsis">…</span><?php endif; ?>
                <a href="<?= $pageUrl($totalPages) ?>" class="page-btn"><?= $totalPages ?></a>
            <?php endif; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= $pageUrl($currentPage + 1) ?>" class="page-btn nav">Suivant &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
