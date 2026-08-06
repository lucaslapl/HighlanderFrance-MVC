<?php
use App\Core\Request;
use App\Services\Auth;

$currentPath = Request::currentPath();
$currentPath = ($currentPath === '/index' || $currentPath === '/index.php') ? '/' : $currentPath;
$isLoggedIn = Auth::isLoggedIn();
?>
<header id="header" fetchpriority="high">
    <div class="head-content flex space-between align-center">
        <div class="flex justify-center align-center">
            <a href="https://highlanderfrance.tf">
                <img class="header-logo" src="/_img/hf.webp" alt="Logo Highlander France" aria-label="Redirection vers la page d'accueil">
            </a>
            <h1>
                Highlander France
            </h1>
        </div>
    </div>

    <nav id="nav">
        <div class="nav-content flex space-between align-center">
            <button class="burger-menu" id="burgerToggle" aria-label="Ouvrir le menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </button>

            <ul class="nav-links flex justify-center align-center">
                <li><a href="/index" class="<?= $currentPath === '/' ? 'active' : '' ?>">Accueil</a></li>
                <li><a href="/staff" class="<?= $currentPath === '/staff' ? 'active' : '' ?>">L'équipe</a></li>
                <li><a href="/hall-of-fame" class="<?= $currentPath === '/hall-of-fame' ? 'active' : '' ?>">Hall of Fame</a></li>
                <li><a href="/match-logs" class="<?= $currentPath === '/match-logs' ? 'active' : '' ?>">Match Stats</a></li>
            </ul>
            <div class="nav-right flex justify-center align-center">
                <div id="session-profile" class="flex justify-center align-center">
                    <?php if ($isLoggedIn): ?>
                        <a href="/profile/dashboard" class="<?= $currentPath === '/profile/dashboard' ? 'active' : '' ?>">Mon Profil</a>
                        <a href="/logout">Déconnexion</a>
                    <?php else: ?>
                        <a href="/login" class="btn-steam-login">
                            <i class="fa-brands fa-steam"></i>
                            <span>Connexion via Steam</span>
                        </a>
                    <?php endif; ?>
                </div>
                <a class="nav-discord discord-link" href="https://discord.gg/BMuj3cqUFt">
                    <i class="fa-brands fa-discord"></i> Discord
                </a>
            </div>
        </div>
    </nav>
</header>
