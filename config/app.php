<?php

declare(strict_types=1);

/**
 * Constantes globales de l'application.
 * Le fichier config/.env (hors versionnement) peut surcharger les valeurs sensibles.
 */

define('APP_ROOT', dirname(__DIR__));

if (is_file(APP_ROOT . '/config/.env')) {
    // Parser minimaliste et robuste : parse_ini_file() échoue selon la version
    // de PHP sur certains commentaires `#` (ex. contenant des parenthèses).
    // Chaque ligne : KEY=VALUE, les lignes vides et les commentaires (# ou ;)
    // sont ignorés. Les valeurs restantes sont importées dans $_ENV.
    $lines = file(APP_ROOT . '/config/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if ($key !== '') {
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    $val = getenv($key);
    return $val === false ? $default : $val;
}

define('APP_NAME', 'Highlander France');
define('APP_DEBUG', (bool)env('APP_DEBUG', false));

// Affiche les erreurs uniquement en développement ; en production on les logge.
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// Répertoire de données de l'application : base SQLite, caches JSON, logs CRON.
define('DATA_DIR', APP_ROOT . '/_scripts');

define('APP_BASE_URL', rtrim((string)env('APP_URL', 'http://highlander-france-mvc.local'), '/'));

define('DB_PATH', env('DB_PATH', DATA_DIR . '/stats.db'));

define('SESSION_LIFETIME', 30 * 24 * 3600); // 30 jours
define('SESSION_SAVE_PATH', APP_ROOT . '/_sessions');

define('CACHE_DIR', APP_ROOT . '/_cache');

// Environnement WAMP : pas de bundle CA → vérification SSL désactivée pour les appels cURL.
// Passer à true en production.
define('CURL_VERIFY_SSL', (bool)env('CURL_VERIFY_SSL', false));

// Durée minimale d'un log (en secondes) : en dessous, blacklist automatique.
define('MIN_MATCH_LENGTH', 300);

// Nationalités proposées sur le profil (codes -> libellés).
define('COUNTRIES', [
    'fr' => 'France',
    'be' => 'Belgique',
    'sw' => 'Suisse',
    'lu' => 'Luxembourg',
    'uk' => 'Royaume-Uni',
    'eu' => 'Europe',
    'al' => 'Algérie',
    'mo' => 'Maroc',
    'tu' => 'Tunisie',
    'ca' => 'Canada',
    'breizh' => 'Bretagne',
]);
