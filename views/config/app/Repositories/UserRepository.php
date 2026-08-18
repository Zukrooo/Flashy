<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function emailExistsForOtherUser(string $email, int $userId): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM users WHERE email = :email AND id != :id LIMIT 1'
        );
        $statement->execute([
            'email' => $email,
            'id' => $userId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return $user === false ? null : $user;
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function create(
        string $email,
        string $passwordHash,
        bool $isAdmin = false,
        string $firstName = '',
        string $lastName = ''
    ): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (first_name, last_name, email, password_hash, is_admin, created_at)
             VALUES (:first_name, :last_name, :email, :password_hash, :is_admin, :created_at)'
        );

        $statement->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password_hash' => $passwordHash,
            'is_admin' => $isAdmin ? 1 : 0,
            'created_at' => date('c'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateProfile(int $id, string $firstName, string $lastName, string $email): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users
             SET first_name = :first_name,
                 last_name = :last_name,
                 email = :email
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
        ]);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password_hash WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'password_hash' => $passwordHash,
        ]);
    }
}
