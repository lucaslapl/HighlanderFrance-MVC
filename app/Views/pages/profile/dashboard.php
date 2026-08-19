<?php
/**
 * Dashboard du joueur connecté (pseudo, nationalité, statistiques).
 * Variables : $player, $playerName, $steamid64, $steamid3, $country, $countries, $dateFormatee,
 *             $stats, $activityData, $isOwnDashboard, $isLocked, $nameChanged.
 */
?>
<div class="personnal-info">

    <?php if (\App\Services\Auth::isAdmin()): ?>
        <div class="admin-profile-box" style="background: #2c1a1a; border: 1px solid #ff4444; padding: 15px; margin: 15px 0 15px 0; border-radius: 5px;">
            <a href="/admin/dashboard" class="btn-admin" style="background: #ff4444; color: white; padding: 8px 12px; text-decoration: none; border-radius: 4px; display: inline-block;">
                <i class="fa-solid fa-user-gear"></i> Panel d'administration
            </a>
        </div>
    <?php endif; ?>

    <?php echo partial('partials/profile_header', [
        'player' => $player,
        'playerName' => $playerName,
        'country' => $country,
        'countries' => $countries,
        'dateFormatee' => $dateFormatee,
    ]); ?>

    <h3>Informations personnelles</h3>
    <p>SteamID : <?= e($steamid3) ?></p>

    <br>

    <div class="dashboard-box">
        <h3>Votre pseudo</h3>

        <?php if ($nameChanged === 1): ?>
            <p>Pseudo enregistré : <strong><?= e($player['display_name']) ?></strong></p>
        <?php else: ?>
            <p class="info-text"><strong>Attention :</strong> Ce changement est <strong>unique et définitif</strong>. Vous ne pourrez plus le modifier par la suite.</p>

            <form action="/profile/update-name" method="POST" class="flex flex-column gap-10">
                <?= \App\Services\Csrf::field() ?>
                <div class="form-group">
                    <label for="display_name">Nouveau pseudo :</label>
                    <input
                        type="text"
                        id="display_name"
                        name="display_name"
                        value="<?= e($player['display_name'] ?? $player['name']) ?>"
                        maxlength="32"
                        required
                        class="form-control">
                </div>

                <button type="submit" name="action" value="update_name" class="btn-submit" onclick="return confirm('Êtes-vous sûr ? Ce changement est définitif et unique !');" style="background: #525252; border: 1px solid #333; color: white; padding: 8px; border-radius: 4px;width: 190px;">
                    <i class="fa-solid fa-floppy-disk"></i> Confirmer définitivement
                </button>
            </form>
        <?php endif; ?>
    </div>

    <h3>Nationalité</h3>

    <?php if ($isLocked && !empty($country)): ?>
        <div class="flex align-center gap-10">
            <img loading="lazy" decoding="async" src="/_img/flags/<?= e($country) ?>.gif" alt="<?= e($countries[$country] ?? $country) ?>" class="flag-icon">
            <span>Nationalité enregistrée : <strong><?= e($countries[$country] ?? strtoupper($country)) ?></strong></span>
        </div>
    <?php else: ?>
        <form action="/profile/update-country" method="POST" class="country-form">
            <?= \App\Services\Csrf::field() ?>
            <p>Sélectionnez votre nationalité (ce choix sera <strong>définitif</strong>) :</p>

            <div class="flex align-center gap-10">
                <select name="country" required class="select-country">
                    <option value="" disabled selected>Choisir un pays...</option>
                    <?php foreach ($countries as $code => $name): ?>
                        <option value="<?= e($code) ?>"><?= e($name) ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-submit-country">Confirmer</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<br>

<?php echo partial('partials/profile_initial_data', ['stats' => $stats, 'activityData' => $activityData]); ?>

<?php echo partial('partials/profile_stats', [
    'stats' => $stats,
    'steamid64' => $steamid64,
    'isOwnDashboard' => $isOwnDashboard,
]); ?>
