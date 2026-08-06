<?php
/**
 * En-tête d'un profil joueur : avatar, pseudo, nationalité, date d'inscription, badges.
 * Variables attendues : $player, $playerName, $country, $countries, $dateFormatee.
 */
$rolesConfig = [
    'is_founder'   => ['label' => 'Fondateur',   'class' => 'badge-founder'],
    'is_admin'     => ['label' => 'Admin',       'class' => 'badge-admin'],
    'is_moderator' => ['label' => 'Modérateur',  'class' => 'badge-moderator'],
    'is_mentor'    => ['label' => 'Mentor',      'class' => 'badge-mentor'],
    'is_mixer'     => ['label' => 'Mixer',       'class' => 'badge-mixer'],
];
?>
<div class="personnal-info__top">
    <div class="profile-header flex align-center">
        <img src="<?= e($player['avatar']) ?>" alt="Avatar de <?= e($playerName) ?>" class="profile-avatar">

        <div class="flex flex-column justify-center gap-5" style="align-items: flex-start;">
            <div class="flex align-center gap-10">
                <h2 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <?= e($playerName) ?>
                    <?php if (!empty($country)): ?>
                        <img src="/_img/flags/<?= e($country) ?>.gif" alt="<?= e($countries[$country] ?? $country) ?>" class="flag-icon">
                    <?php endif; ?>
                </h2>
                <?php if ($dateFormatee): ?>
                    <span style="font-size: 0.85rem; color: #888;">inscrit le <?= e($dateFormatee) ?></span>
                <?php endif; ?>
            </div>

            <div class="staff-badges-container">
                <?php foreach ($rolesConfig as $dbKey => $badgeInfo): ?>
                    <?php if (isset($player[$dbKey]) && ($player[$dbKey] == 1 || $player[$dbKey] === true)): ?>
                        <span class="badge-staff <?= $badgeInfo['class'] ?>">
                            <?= e($badgeInfo['label']) ?>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php echo partial('partials/activity_calendar'); ?>
</div>
