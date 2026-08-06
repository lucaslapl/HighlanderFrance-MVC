<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\Crons\GenerateJsonService;

require __DIR__ . '/../config/autoload.php';
require __DIR__ . '/../config/app.php';

try {
    echo (new GenerateJsonService(Database::connection()))->run() . PHP_EOL;
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, 'Erreur lors de la mise à jour des caches de classement : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
