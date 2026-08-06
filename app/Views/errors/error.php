<?php /** @var int $code @var string $message */ ?>
<div class="error-page" style="text-align: center; padding: 60px 20px;">
    <h2 style="font-size: 3rem; margin: 0;"><?= (int)($code ?? 500) ?></h2>
    <p><?= e($message ?? 'Une erreur est survenue.') ?></p>
    <p><a href="/" style="color: #ff7b00;">Retour à l'accueil</a></p>
</div>
