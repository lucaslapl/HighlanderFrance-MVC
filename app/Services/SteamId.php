<?php

declare(strict_types=1);

namespace App\Services;

final class SteamId
{
    private const STEAMID64_CONSTANT = '76561197960265728';

    public static function toSteamId3(string $steamid64): string
    {
        return '[U:1:' . bcsub($steamid64, self::STEAMID64_CONSTANT) . ']';
    }

    public static function toSteamId64(string $steamid3): ?string
    {
        if (preg_match('/\[U:1:(\d+)\]/', $steamid3, $matches)) {
            return bcadd($matches[1], self::STEAMID64_CONSTANT);
        }

        return null;
    }
}
