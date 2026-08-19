<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\SteamId;

final class PlayerRepository
{
    public function __construct(private readonly \PDO $db)
    {
    }

    /**
     * Tous les membres ayant au moins un rôle actif dans le staff.
     */
    public function staffMembers(): array
    {
        $stmt = $this->db->query("
            SELECT steamid, name, display_name, avatar,
                   is_founder, is_mentor, is_mixer, is_moderator
            FROM players_info
            WHERE is_founder = 1 OR is_mentor = 1 OR is_mixer = 1 OR is_moderator = 1
            ORDER BY display_name ASC, name ASC
        ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findById(string $steamid3): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM players_info WHERE steamid = ?');
        $stmt->execute([$steamid3]);
        $player = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $player ?: null;
    }

    public function findBySteamId64(string $steamid64): ?array
    {
        return $this->findById(SteamId::toSteamId3($steamid64));
    }

    /**
     * Recherche de joueurs par pseudo / pseudo d'affichage (Hall of Fame).
     *
     * @return array<int, array{steamid: int, name: string, display_name: string|null, avatar: string|null}>
     */
    public function search(string $query): array
    {
        $stmt = $this->db->prepare("
            SELECT steamid, name, display_name, avatar
            FROM players_info
            WHERE name LIKE :q OR display_name LIKE :q
            ORDER BY display_name ASC, name ASC
            LIMIT 10
        ");
        $stmt->execute([':q' => '%' . $query . '%']);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Tous les SteamID (format steamid3) des joueurs indexés, pour le sitemap.
     *
     * @return array<int, string>
     */
    public function allSteamIds(): array
    {
        return $this->db
            ->query('SELECT steamid FROM players_info ORDER BY steamid ASC')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Insère le joueur s'il n'existe pas (idempotent).
     */
    public function createIfMissing(string $steamid3): void
    {
        $stmt = $this->db->prepare('INSERT OR IGNORE INTO players_info (steamid, display_name, created_at) VALUES (?, ?, CURRENT_TIMESTAMP)');
        $stmt->execute([$steamid3, 'Nouveau Joueur']);
    }

    /**
     * Renseigne created_at si vide (première connexion d'un compte ancien).
     */
    public function ensureCreatedAt(string $steamid3): void
    {
        $stmt = $this->db->prepare('UPDATE players_info SET created_at = CURRENT_TIMESTAMP WHERE steamid = ? AND (created_at IS NULL OR created_at = "")');
        $stmt->execute([$steamid3]);
    }

    public function hasNameChanged(string $steamid3): bool
    {
        $stmt = $this->db->prepare('SELECT name_changed FROM players_info WHERE steamid = ?');
        $stmt->execute([$steamid3]);

        return ((int)($stmt->fetch(\PDO::FETCH_ASSOC)['name_changed'] ?? 0)) === 1;
    }

    /**
     * Enregistre le pseudo d'affichage (unique et définitif).
     *
     * @return bool false si déjà modifié.
     */
    public function updateDisplayName(string $steamid3, string $name): bool
    {
        if ($this->hasNameChanged($steamid3)) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE players_info SET display_name = ?, name_changed = 1 WHERE steamid = ?');
        $stmt->execute([$name, $steamid3]);

        return true;
    }

    public function hasCountryLocked(string $steamid3): bool
    {
        $stmt = $this->db->prepare('SELECT country_locked FROM players_info WHERE steamid = ?');
        $stmt->execute([$steamid3]);

        return ((int)($stmt->fetch(\PDO::FETCH_ASSOC)['country_locked'] ?? 0)) === 1;
    }

    /**
     * Enregistre la nationalité (unique et définitive).
     *
     * @return bool false si déjà verrouillée.
     */
    public function updateCountry(string $steamid3, string $country): bool
    {
        if ($this->hasCountryLocked($steamid3)) {
            return false;
        }

        $stmt = $this->db->prepare('UPDATE players_info SET country = ?, country_locked = 1 WHERE steamid = ?');
        $stmt->execute([$country, $steamid3]);

        return true;
    }
}
