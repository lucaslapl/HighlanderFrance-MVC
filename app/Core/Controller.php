<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected ?Request $request = null;

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    protected function view(string $template, array $data = [], string $layout = 'main'): void
    {
        View::render($template, $data, $layout);
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Répond avec une page d'erreur HTTP (400/403/404/405…).
     */
    protected function abort(int $code): never
    {
        http_response_code($code);
        (new \App\Controllers\ErrorController())->handle($code);
        exit;
    }
}
