<?php
/**
 * Layout du panel d'administration.
 * Variables attendues : $title, $description, $styles[], $scripts[], $pageScripts, $content.
 */
$title = $title ?? APP_NAME;
$description = $description ?? 'Panel d\'administration Highlander France.';
$styles = $styles ?? [];
$scripts = $scripts ?? [];
$pageScripts = $pageScripts ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">

    <link rel="shortcut icon" href="https://highlanderfrance.tf/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="https://highlanderfrance.tf/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://highlanderfrance.tf/favicon-16x16.png">
    <link rel="apple-touch-icon" href="https://highlanderfrance.tf/apple-touch-icon.png">

    <link rel="stylesheet" href="<?= e(asset('/_css/main.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('/_css/admin.css')) ?>">
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

<main id="main" class="admin-main">
    <?= $content ?>
</main>

<?php echo partial('partials/footer'); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://kit.fontawesome.com/2f306d349c.js" crossorigin="anonymous"></script>
<script src="<?= e(asset('/_js/main.js')) ?>" defer></script>
<?= $pageScripts ?>
<?php foreach ($scripts as $script): ?>
<script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
