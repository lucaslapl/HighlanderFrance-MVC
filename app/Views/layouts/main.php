<?php
/**
 * Layout principal du site.
 * Variables attendues : $title, $description, $styles[], $scripts[], $pageScripts, $content.
 */
$title = $title ?? APP_NAME;
$description = $description ?? 'Highlander France est une communauté compétitive francophone de Team Fortress 2, offrant un espace pour les joueurs de tous niveaux pour apprendre, jouer et progresser ensemble.';
$styles = $styles ?? [];
$scripts = $scripts ?? [];
$pageScripts = $pageScripts ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://highlanderfrance.tf/">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">

    <!-- Facebook Meta Tags -->
    <meta property="og:url" content="https://highlanderfrance.tf/">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= e($title) ?>">
    <meta property="og:description" content="<?= e($description) ?>">
    <meta property="og:image" content="https://highlanderfrance.tf/_img/meta-bg-hlfr.jpg">

    <!-- Twitter Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta property="twitter:domain" content="highlanderfrance.tf">
    <meta property="twitter:url" content="https://highlanderfrance.tf/">
    <meta name="twitter:title" content="<?= e($title) ?>">
    <meta name="twitter:description" content="<?= e($description) ?>">
    <meta name="twitter:image" content="https://highlanderfrance.tf/_img/meta-bg-hlfr.jpg">

    <!-- Favicon standard -->
    <link rel="shortcut icon" href="https://highlanderfrance.tf/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="https://highlanderfrance.tf/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://highlanderfrance.tf/favicon-16x16.png">
    <link rel="icon" type="image/x-icon" href="https://highlanderfrance.tf/favicon.ico">

    <!-- Apple Touch Icon (iPhone/iPad) -->
    <link rel="apple-touch-icon" href="https://highlanderfrance.tf/apple-touch-icon.png">

    <!-- Android Chrome -->
    <link rel="icon" type="image/png" sizes="192x192" href="https://highlanderfrance.tf/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="https://highlanderfrance.tf/android-chrome-512x512.png">

    <!-- Web App Manifest -->
    <link rel="manifest" href="/site.webmanifest">

    <link rel="stylesheet" href="/_css/main.css">
    <?php foreach ($styles as $style): ?>
    <link rel="stylesheet" href="<?= e($style) ?>">
    <?php endforeach; ?>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-30553SX3GJ"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', 'G-30553SX3GJ');
    </script>
</head>
<body>
<?php if (isset($_SESSION['error'])): ?>
    <div style="background: #3d1c1c; color: #e74c3c; border: 1px solid #c0392b; padding: 12px 15px; border-radius: 4px; margin: 20px auto; max-width: 1200px; font-size: 14px;">
        <i class="fa-solid fa-circle-xmark"></i> <?= e($_SESSION['error']); unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>
<?php if (isset($_SESSION['success'])): ?>
    <div style="background: #1c3d1c; color: #2ecc71; border: 1px solid #27ae60; padding: 12px 15px; border-radius: 4px; margin: 20px auto; max-width: 1200px; font-size: 14px;">
        <i class="fa-solid fa-circle-check"></i> <?= e($_SESSION['success']); unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php echo partial('partials/header'); ?>

<main id="main">
    <section id="content">
        <?= $content ?>
    </section>
</main>

<?php echo partial('partials/footer'); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://kit.fontawesome.com/2f306d349c.js" crossorigin="anonymous"></script>
<script src="/_js/main.js"></script>
<script src="/_js/live_match.js"></script>
<?php foreach ($scripts as $script): ?>
<script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
<?= $pageScripts ?>
</body>
</html>
