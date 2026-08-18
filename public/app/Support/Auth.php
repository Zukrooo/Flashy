<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UserRepository;

final class Auth
{
    private const ADMIN_SESSION_KEY = 'admin_user_id';
    private const USER_SESSION_KEY = 'user_id';
    private const USER_EMAIL_SESSION_KEY = 'user_email';
    private const USER_FIRST_NAME_SESSION_KEY = 'user_first_name';
    private const USER_LAST_NAME_SESSION_KEY = 'user_last_name';
    private const USER_IS_ADMIN_SESSION_KEY = 'user_is_admin';

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function attemptAdmin(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if (
            $user === null
            || (int) ($user['is_admin'] ?? 0) !== 1
            || !password_verify($password, $user['password_hash'])
        ) {
            return false;
        }

        $_SESSION[self::ADMIN_SESSION_KEY] = (int) $user['id'];

        return true;
    }

    public function attemptUser(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $this->syncUserSession($user);

        return true;
    }

    public function registerUser(string $firstName, string $lastName, string $email, string $password): ?int
    {
        if ($this->users->findByEmail($email) !== null) {
            return null;
        }

        $userId = $this->users->create($email, password_hash($password, PASSWORD_DEFAULT), false, $firstName, $lastName);
        $user = $this->users->find($userId);

        if ($user !== null) {
            $this->syncUserSession($user);
        }

        return $userId;
    }

    public function checkAdmin(): bool
    {
        if (isset($_SESSION[self::ADMIN_SESSION_KEY])) {
            return true;
        }

        if (
            (bool) ($_SESSION[self::USER_IS_ADMIN_SESSION_KEY] ?? false)
            && $this->userId() !== null
        ) {
            $_SESSION[self::ADMIN_SESSION_KEY] = $this->userId();
            return true;
        }

        return false;
    }

    public function checkUser(): bool
    {
        return isset($_SESSION[self::USER_SESSION_KEY]);
    }

    public function user(): ?array
    {
        $userId = $_SESSION[self::USER_SESSION_KEY] ?? null;

        if (!is_int($userId) && !is_numeric((string) $userId)) {
            return null;
        }

        return $this->users->find((int) $userId);
    }

    public function userId(): ?int
    {
        $userId = $_SESSION[self::USER_SESSION_KEY] ?? null;

        if (!is_int($userId) && !is_numeric((string) $userId)) {
            return null;
        }

        return (int) $userId;
    }

    public function requireAdmin(): void
    {
        if (!$this->checkAdmin()) {
            Flash::put('error', 'Please log in to access the admin panel.');
            redirect('/admin/login');
        }
    }

    public function requireUser(): void
    {
        if (!$this->checkUser()) {
            Flash::put('error', 'Please log in to track your study progress.');
            redirect('/login');
        }
    }

    public function logoutAdmin(): void
    {
        unset($_SESSION[self::ADMIN_SESSION_KEY]);
    }

    public function logoutUser(): void
    {
        unset($_SESSION[self::ADMIN_SESSION_KEY]);
        unset($_SESSION[self::USER_SESSION_KEY]);
        unset($_SESSION[self::USER_EMAIL_SESSION_KEY]);
        unset($_SESSION[self::USER_FIRST_NAME_SESSION_KEY]);
        unset($_SESSION[self::USER_LAST_NAME_SESSION_KEY]);
        unset($_SESSION[self::USER_IS_ADMIN_SESSION_KEY]);
    }

    public function syncUserSession(array $user): void
    {
        $_SESSION[self::USER_SESSION_KEY] = (int) $user['id'];
        $_SESSION[self::USER_EMAIL_SESSION_KEY] = (string) $user['email'];
        $_SESSION[self::USER_FIRST_NAME_SESSION_KEY] = trim((string) ($user['first_name'] ?? ''));
        $_SESSION[self::USER_LAST_NAME_SESSION_KEY] = trim((string) ($user['last_name'] ?? ''));
        $_SESSION[self::USER_IS_ADMIN_SESSION_KEY] = (int) ($user['is_admin'] ?? 0) === 1;
    }
}
