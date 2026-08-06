<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\Crons\SyncSteamAvatarsService;

require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../config/app.php';

set_time_limit(300); // 5 minutes max, évite les coupures si la base est grande.

try {
    echo (new SyncSteamAvatarsService(Database::connection()))->run() . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, '[ERREUR CRITIQUE] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
