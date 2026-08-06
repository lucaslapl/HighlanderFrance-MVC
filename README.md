# Highlander France — Architecture MVC

> **Projet de démonstration** — publié à titre de portfolio. Tous droits
> réservés, aucune réutilisation autorisée. Voir [LICENSE](LICENSE).

![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=fff)
![Licence](https://img.shields.io/badge/Licence-Tous%20droits%20r%C3%A9serv%C3%A9s-blue)

Refonte de l'ancien site PHP procédural vers une **architecture MVC maison**
(sans framework, PSR-4 maison, aucun Composer).

- **Dossier** : `highlander-france-mvc`
- **Base de données** : SQLite dans `_scripts/stats.db` (propre à l'application)
- **PHP requis** : 8.2+ (extensions `pdo_sqlite`, `curl`, `bcmath`)

## Structure

```
bin/                          Scripts CRON exécutables en CLI
config/
  app.php                     Constantes globales (DATA_DIR, DB_PATH, COUNTRIES…)
  autoload.php                Autoloader PSR-4 + helpers e() / partial()
  routes.php                  Toutes les routes de l'application
  .env                        Clés sensibles (HORS versionnement)
deploy/
  crontab.txt                 CRONTAB de production (à adapter puis installer)
app/
  Core/                       Router, Request, Response, Controller, View, Database
  Controllers/                PageController, AuthController, ProfileController,
                              Admin/AdminController, Admin/ApiController, ErrorController
  Models/                     Repositories (accès BDD)
  Services/                   Auth, SteamApi, SteamId, JsonClient, LogParser,
                              AdminLogger, ApiStatus, Crons/*
  Views/                      layouts/, pages/, partials/, errors/
_scripts/                     Données : base SQLite, caches JSON, logs CRON
                              (protégé par .htaccess, non versionné)
```

## Installation / Configuration

1. Copier `config/.env.example` → `config/.env` (ou créer le fichier) et renseigner :

   ```ini
   STEAM_API_KEY=<clé Steam API>
   APP_URL=http://localhost:8080
   # DB_PATH=…  (défaut : _scripts/stats.db)
   CURL_VERIFY_SSL=0           # 1 en production
   ```

2. Le vhost doit pointer vers la racine du projet (le `.htaccess` fait office
   de front controller et bloque l'accès à `app/`, `config/`, `bin/`,
   `_cache/`, `_sessions/`, `_scripts/`).

## CRON

Les scripts s'exécutent en CLI :

```bash
php bin/update_stats.php            # Stats des matchs joueurs (logs.tf)
php bin/update_index_stats.php      # Stats de la page d'accueil
php bin/sync_etf2l.php              # Agenda des matchs ETF2L FR
php bin/sync_steam.php              # Import des profils Steam manquants
php bin/sync_steam_avatars.php      # Réparation des profils Steam cassés
php bin/generate_json.php           # Caches JSON du classement (leaderboard)
php bin/backfill_log_dates.php      # Backfill des dates de matchs
php bin/migrate_player_match_stats.php
php bin/backfill_player_match_stats.php
php bin/backfill_match_teams.php
```

Chaque exécution est auditée dans `_scripts/cron_debug.log` (statut
STARTED → SUCCESS / FAILED) et consultable dans le panel admin
(`/admin/run-cron-manual`, `/admin/view-logs`).

**Crontab de production** : adapter `deploy/crontab.txt` (chemins + binaire PHP)
puis installer avec `crontab deploy/crontab.txt`. Les backfills/migrations sont
volontairement non programmés (opérations ponctuelles à lancer à la main).

## Routes principales

| Méthode | URI | Contrôleur |
|---|---|---|
| GET | `/` | PageController::home |
| GET | `/staff` | PageController::staff |
| GET | `/hall-of-fame` | PageController::hallOfFame |
| GET | `/match-logs` | PageController::matchLogs |
| GET | `/log/{id}` | PageController::matchLog (détail d'un match) |
| GET | `/confidentialite` | PageController::privacy |
| GET | `/login`, `/auth/callback`, `/logout` | AuthController |
| GET | `/profile/{steamid}` | ProfileController::profil |
| GET | `/api/index-stats`, `/api/logs`, `/api/leaderboard`, `/api/search-players`, `/api/profile-stats` | API |
| POST | `/api/server/match-ended` | Webhook fin de match (plugin SourceMod, token partagé) |
| GET/POST | `/admin/*` | AdminController (admin requis) |
| POST | `/api/admin/*` | Admin/ApiController (admin requis) |

## Webhook fin de match (plugin SourceMod)

Le plugin `plugins/hlfr_match_log` (source + binaire + config + README) détecte
la fin d'un match TF2 (TFTrue) et déclenche la mise à jour des stats et du
leaderboard **en temps réel**, au lieu d'attendre le CRON.

- Endpoint : `POST /api/server/match-ended` (JSON `{ token, server, map }`),
  sans session utilisateur.
- Authentification : token partagé `SERVER_WEBHOOK_TOKEN` (config `.env`),
  comparé via `hash_equals` ; option `SERVER_WEBHOOK_ALLOWED_IPS` (liste d'IP
  autorisées, vide = toutes).
- Chaîne exécutée : `update_stats.php` → `generate_json.php` →
  `update_index_stats.php` (mêmes services que le panel admin).
- Anti-concurrence : verrou `flock` sur `_scripts/webhook_match.lock` (réponse
  202 si une mise à jour est déjà en cours).

Les CRON des 3 scripts liés aux matchs passent en filet de sécurité (toutes les
3 h) dans `deploy/crontab.txt`.

## Notes

- L'application est **autonome** : base SQLite, caches et logs CRON vivent dans
  `_scripts/` (protégé par `.htaccess`, exclu du versionnement via `.gitignore`).
- Secrets (`config/.env`, `_cache/`, `_sessions/`, `_scripts/`) exclus du
  versionnement.
- L'ancien site (`highlander-france`) est retiré : pointez le vhost de
  production vers ce dossier.

## Licence

Ce projet est mis à disposition **uniquement à des fins de consultation**
(portfolio). Tous droits réservés — voir le fichier [LICENSE](LICENSE).
