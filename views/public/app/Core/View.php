<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewPath = base_path('views/' . $template . '.php');

        require base_path('views/layouts/app.php');
        clear_old_input();
    }
}
