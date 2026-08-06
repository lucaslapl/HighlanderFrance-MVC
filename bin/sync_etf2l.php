<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\Crons\SyncEtf2lService;

require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../config/app.php';

try {
    echo (new SyncEtf2lService(Database::connection()))->run() . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Erreur lors de la synchronisation de l\'agenda : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
