<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\Crons\UpdateIndexStatsService;

require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../config/app.php';

try {
    echo (new UpdateIndexStatsService(Database::connection()))->run() . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Erreur script : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
