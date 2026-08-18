<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function input(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token): void
    {
        $sessionToken = $_SESSION['_csrf'] ?? null;

        if (!is_string($sessionToken) || !is_string($token) || !hash_equals($sessionToken, $token)) {
            throw new RuntimeException('Invalid CSRF token.');
        }
    }
}

