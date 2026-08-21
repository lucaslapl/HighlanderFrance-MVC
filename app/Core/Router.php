<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, array{0: string, 1: string}>> */
    private array $routes = ['GET' => [], 'POST' => [], 'PUT' => [], 'DELETE' => []];

    public function get(string $path, string $controller, string $action): void
    {
        $this->routes['GET'][$this->normalize($path)] = [$controller, $action];
    }

    public function post(string $path, string $controller, string $action): void
    {
        $this->routes['POST'][$this->normalize($path)] = [$controller, $action];
    }

    public function any(string $path, string $controller, string $action): void
    {
        foreach (array_keys($this->routes) as $method) {
            $this->routes[$method][$this->normalize($path)] = [$controller, $action];
        }
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $this->normalize($request->path());

        if (!isset($this->routes[$method])) {
            $this->abort(405);
        }

        [$route, $params] = $this->match($method, $path);

        if ($route === null) {
            $this->abort(404);
        }

        // Protection CSRF sur les requêtes mutantes (POST/PUT/DELETE).
        // Exceptions : les webhooks serveur sont authentifiés par token partagé
        // (SERVER_WEBHOOK_TOKEN) sans session utilisateur.
        if (in_array($method, ['POST', 'PUT', 'DELETE'], true) && !$this->isWebhook($path)) {
            $token = $request->header('X-CSRF-Token') ?? $request->post('_csrf');

            if (!\App\Services\Csrf::verify(is_string($token) ? $token : null)) {
                http_response_code(419);
                exit('Session expirée ou jeton CSRF invalide. Merci de réessayer.');
            }
        }

        $request->setParams($params);

        [$controllerClass, $action] = $route;
        $controller = new $controllerClass();
        $controller->setRequest($request);
        $controller->{$action}($request);
    }

    /**
     * Les endpoints webhooks serveurs et bot Discord utilisent un token
     * partagé et non la session utilisateur : ils sont exemptés de la
     * vérification CSRF.
     */
    private function isWebhook(string $path): bool
    {
        return str_starts_with($path, '/api/server/')
            || str_starts_with($path, '/api/discord/');
    }

    /**
     * @return array{0: array{0: string, 1: string}|null, 1: array<string, string>}
     */
    private function match(string $method, string $path): array
    {
        foreach ($this->routes[$method] as $pattern => $route) {
            if ($pattern === $path) {
                return [$route, []];
            }
        }

        foreach ($this->routes[$method] as $pattern => $route) {
            if (preg_match_all('/\{(\w+)\}/', $pattern, $matches) > 0) {
                $regex = '#^' . preg_replace('/\{(\w+)\}/', '([^/]+)', $pattern) . '$#';

                if (preg_match($regex, $path, $values) === 1) {
                    $params = [];
                    foreach ($matches[1] as $i => $name) {
                        $params[$name] = rawurldecode($values[$i + 1]);
                    }

                    return [$route, $params];
                }
            }
        }

        return [null, []];
    }

    private function normalize(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    private function abort(int $code): never
    {
        http_response_code($code);
        $controller = new \App\Controllers\ErrorController();
        $controller->handle($code);
        exit;
    }
}
