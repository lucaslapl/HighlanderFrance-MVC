<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class ErrorController extends Controller
{
    private const MESSAGES = [
        400 => 'Requête invalide.',
        403 => 'Accès refusé.',
        404 => 'La page demandée est introuvable.',
        405 => 'Méthode non autorisée.',
    ];

    public function handle(int $code): void
    {
        http_response_code($code);

        $this->view('errors/error', [
            'title' => (self::MESSAGES[$code] ?? 'Erreur') . ' - ' . APP_NAME,
            'code' => $code,
            'message' => self::MESSAGES[$code] ?? 'Une erreur est survenue.',
        ]);
    }

    public function notFound(): void
    {
        $this->handle(404);
    }

    public function forbidden(): void
    {
        $this->handle(403);
    }

    public function badRequest(): void
    {
        $this->handle(400);
    }
}
