<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Client cURL minimaliste pour récupérer du JSON.
 */
final class JsonClient
{
    /**
     * @return array|null Tableau décodé, ou null si l'appel échoue.
     */
    public static function get(string $url, int $timeout = 10, string $userAgent = 'Mozilla/5.0', array $headers = []): ?array
    {
        $ch = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => CURL_VERIFY_SSL,
            CURLOPT_USERAGENT      => $userAgent,
        ];

        if ($headers !== []) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            error_log('Erreur cURL (' . $url . ') : ' . $error);

            return null;
        }

        curl_close($ch);

        $data = json_decode((string)$response, true);

        return is_array($data) ? $data : null;
    }
}
