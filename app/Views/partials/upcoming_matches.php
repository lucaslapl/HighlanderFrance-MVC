<?php /** @var array $prochainsMatchs */ ?>
<div class="etf2l-agenda-container">
    <div class="agenda-header flex space-between align-center">
        <h3><i class="fa-solid fa-calendar-days"></i> Matchs Équipes FR (ETF2L)</h3>
        <span class="badge-live-info">Prochains matchs</span>
    </div>

    <?php if (empty($prochainsMatchs)): ?>
        <div class="agenda-empty">
            <p><i class="fa-solid fa-circle-info"></i> Aucun match de prévu pour le moment.</p>
        </div>
    <?php else: ?>
        <div class="agenda-list">
            <?php foreach ($prochainsMatchs as $match): ?>
                <?php
                $dt = new DateTime('@' . $match['match_date']);
                $dt->setTimezone(new DateTimeZone('Europe/Paris'));
                $dateMatch = $dt->format('d/m');
                $heureMatch = $dt->format('H:i');
                $flag1 = ($match['team1_country'] === 'france') ? 'fr' : 'eu';
                $flag2 = ($match['team2_country'] === 'france') ? 'fr' : 'eu';
                ?>
                <div class="agenda-item flex align-center">

                    <div class="match-date-box text-center">
                        <span class="match-date"><?= $dateMatch ?></span>
                        <span class="match-hour"><?= $heureMatch ?></span>
                    </div>

                    <div class="match-details flex-1">
                        <div class="competition-title"><?= e($match['competition_name']) ?></div>
                        <div class="teams-line flex align-center">

                            <span class="team-name text-right flex align-center justify-end gap-10">
                                <img loading="lazy" decoding="async" src="/_img/flags/<?= $flag1 ?>.gif" alt="<?= $flag1 ?>" class="team-flag" title="<?= ucfirst(e($match['team1_country'])) ?>">
                                <span class="truncate-text"><?= e($match['team1_name']) ?></span>
                            </span>

                            <span class="vs-separator">VS</span>

                            <span class="team-name text-left flex align-center gap-10">
                                <span class="truncate-text"><?= e($match['team2_name']) ?></span>
                                <img loading="lazy" decoding="async" src="/_img/flags/<?= $flag2 ?>.gif" alt="<?= $flag2 ?>" class="team-flag" title="<?= ucfirst(e($match['team2_country'])) ?>">
                            </span>

                        </div>
                    </div>

                    <div class="match-action">
                        <a href="https://etf2l.org/matches/<?= (int)$match['match_id'] ?>" target="_blank" class="btn-match-link" title="Voir sur ETF2L">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
