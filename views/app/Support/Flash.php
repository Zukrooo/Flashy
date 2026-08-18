<?php

declare(strict_types=1);

namespace App\Support;

final class Flash
{
    public static function put(string $type, string $message): void
    {
        $_SESSION['_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    public static function pull(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);

        return is_array($flash) ? $flash : null;
    }
}

