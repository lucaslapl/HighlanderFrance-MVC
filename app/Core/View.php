<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'main'): void
    {
        $content = self::partial($template, $data);
        $data['content'] = $content;

        self::include('layouts/' . $layout, $data);
    }

    public static function partial(string $template, array $data = []): string
    {
        ob_start();
        self::include($template, $data);

        return (string)ob_get_clean();
    }

    private static function include(string $template, array $data): void
    {
        extract($data, EXTR_SKIP);
        require APP_ROOT . '/app/Views/' . $template . '.php';
    }
}
