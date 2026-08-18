<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Migrator
{
    public static function migrate(Database $database): array
    {
        $pdo = $database->connection();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(255) NOT NULL DEFAULT "",
                last_name VARCHAR(255) NOT NULL DEFAULT "",
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                is_admin TINYINT(1) NOT NULL DEFAULT 0,
                created_at VARCHAR(50) NOT NULL,
                UNIQUE KEY users_email_unique (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS languages (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                created_at VARCHAR(50) NOT NULL,
                UNIQUE KEY languages_name_unique (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                language_id BIGINT UNSIGNED NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                published TINYINT(1) NOT NULL DEFAULT 1,
                created_at VARCHAR(50) NOT NULL,
                KEY sets_language_id_index (language_id),
                CONSTRAINT sets_language_id_fk FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS cards (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                set_id BIGINT UNSIGNED NOT NULL,
                gaidhlig TEXT NOT NULL,
                english TEXT NOT NULL,
                language_aliases TEXT NOT NULL,
                english_aliases TEXT NOT NULL,
                created_at VARCHAR(50) NOT NULL,
                KEY cards_set_id_index (set_id),
                CONSTRAINT cards_set_id_fk FOREIGN KEY (set_id) REFERENCES sets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_card_progress (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                card_id BIGINT UNSIGNED NOT NULL,
                correct_count INT NOT NULL DEFAULT 0,
                incorrect_count INT NOT NULL DEFAULT 0,
                skipped_count INT NOT NULL DEFAULT 0,
                current_streak INT NOT NULL DEFAULT 0,
                last_seen_at VARCHAR(50) NULL,
                last_correct_at VARCHAR(50) NULL,
                created_at VARCHAR(50) NOT NULL,
                updated_at VARCHAR(50) NOT NULL,
                UNIQUE KEY user_card_progress_user_card_unique (user_id, card_id),
                KEY user_card_progress_card_id_index (card_id),
                CONSTRAINT user_card_progress_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT user_card_progress_card_fk FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_card_attempts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                card_id BIGINT UNSIGNED NOT NULL,
                outcome VARCHAR(20) NOT NULL,
                created_at VARCHAR(50) NOT NULL,
                KEY user_card_attempts_user_id_index (user_id),
                KEY user_card_attempts_card_id_index (card_id),
                CONSTRAINT user_card_attempts_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT user_card_attempts_card_fk FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $databaseName = self::databaseName($pdo);

        if (!self::columnExists($pdo, $databaseName, 'sets', 'language_id')) {
            $pdo->exec('ALTER TABLE sets ADD COLUMN language_id BIGINT UNSIGNED NULL');
            $pdo->exec('ALTER TABLE sets ADD KEY sets_language_id_index (language_id)');
            $pdo->exec('ALTER TABLE sets ADD CONSTRAINT sets_language_id_fk FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE SET NULL');
        }

        if (!self::columnExists($pdo, $databaseName, 'users', 'is_admin')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0');
            $pdo->exec('UPDATE users SET is_admin = 1');
        }

        if (!self::columnExists($pdo, $databaseName, 'users', 'first_name')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN first_name VARCHAR(255) NOT NULL DEFAULT ""');
        }

        if (!self::columnExists($pdo, $databaseName, 'users', 'last_name')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_name VARCHAR(255) NOT NULL DEFAULT ""');
        }

        if (self::columnExists($pdo, $databaseName, 'users', 'name')) {
            $pdo->exec('UPDATE users SET first_name = name WHERE first_name = "" AND name <> ""');
        }

        if (!self::columnExists($pdo, $databaseName, 'cards', 'language_aliases')) {
            $pdo->exec('ALTER TABLE cards ADD COLUMN language_aliases TEXT NOT NULL');
        }

        if (!self::columnExists($pdo, $databaseName, 'cards', 'english_aliases')) {
            $pdo->exec('ALTER TABLE cards ADD COLUMN english_aliases TEXT NOT NULL');
        }

        return [
            'driver' => 'mysql',
            'database' => $database->config()->databaseName(),
            'host' => $database->config()->databaseHost(),
            'port' => $database->config()->databasePort(),
        ];
    }

    private static function columnExists(PDO $pdo, string $databaseName, string $table, string $column): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = :database_name
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'database_name' => $databaseName,
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private static function databaseName(PDO $pdo): string
    {
        return (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    }
}
