# Changelog

Toutes les modifications notables de ce projet sont documentées dans ce
fichier, suivant le format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Le projet respecte le [Semantic Versioning](https://semver.org/lang/fr/).

## [Unreleased]

### Ajouté
- `sitemap.xml` avec les nouvelles routes propres (`.php` supprimés).
- `LICENSE` (tous droits réservés).
- Accueil : les matchs ETF2L terminés depuis moins de 48 h restent affichés
  dans l'encadré de l'agenda (version compacte) avec leur score, un dégradé
  vert côté vainqueur / rouge côté perdant, et un lien vers le détail du
  match.

### Modifié
- `robots.txt` : référence le sitemap.
- `README.md` : bandeau portfolio, badges, section Licence.
- Normalisation des fins de ligne des fichiers `_js/*` (CRLF → LF).

## [1.0.0] - 2026-08-06

### Ajouté
- Refonte complète de l'ancien site PHP procédural vers une **architecture
  MVC maison** (sans framework, autoloader PSR-4, zéro Composer).
- Front controller via `.htaccess` + blocage des dossiers internes
  (`app/`, `config/`, `bin/`, `_cache/`, `_sessions/`, `_scripts/`).
- Pages publiques : accueil, staff, hall-of-fame, matchs, détail d'un match,
  page confidentialité.
- Authentification Steam (OAuth via `/auth/callback`).
- Profils joueurs : dashboard, consultation, mise à jour du nom et du pays.
- Intégrations API : Steam, logs.tf (stats de matchs), ETF2L (agenda).
- Classement (leaderboard) 6v6 et 9v9 avec caches JSON générés par CRON.
- Panel admin : gestion du staff, blacklist, matchs, joueurs, logs CRON,
  exécution manuelle des CRON.
- Scripts CRON en CLI (`bin/`) avec audit des exécutions dans
  `_scripts/cron_debug.log`.
- Plugin `plugins/hlfr_match_log`.

[Unreleased]: #unreleased
[1.0.0]: #100
