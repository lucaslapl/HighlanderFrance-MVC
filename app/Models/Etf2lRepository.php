<?php

declare(strict_types=1);

namespace App\Models;

final class Etf2lRepository
{
    public function __construct(private readonly \PDO $db)
    {
    }

    /**
     * Prochains matchs des équipes françaises, du plus proche au plus lointain.
     */
    public function upcomingMatches(int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM etf2l_matches
            WHERE match_date >= :current_time
            ORDER BY match_date ASC
            LIMIT " . (int)$limit
        );
        $stmt->execute([':current_time' => time()]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
