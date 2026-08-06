<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\Crons\BackfillMatchTeamsService;

require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../config/app.php';

try {
    echo (new BackfillMatchTeamsService(Database::connection()))->run() . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Erreur critique : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
