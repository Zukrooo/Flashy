<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Migrator
{
    public static function migrate(Database $database): array
    {
        return $database->config()->isSqlite()
            ? self::migrateSqlite($database)
            : self::migrateMysql($database);
    }

    private static function migrateSqlite(Database $database): array
    {
        $pdo = $database->connection();

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                first_name TEXT NOT NULL DEFAULT "",
                last_name TEXT NOT NULL DEFAULT "",
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                is_admin INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS languages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                description TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                language_id INTEGER,
                name TEXT NOT NULL,
                description TEXT NOT NULL DEFAULT "",
                published INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS cards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                set_id INTEGER NOT NULL,
                gaidhlig TEXT NOT NULL,
                english TEXT NOT NULL,
                language_aliases TEXT NOT NULL DEFAULT "",
                english_aliases TEXT NOT NULL DEFAULT "",
                created_at TEXT NOT NULL,
                FOREIGN KEY (set_id) REFERENCES sets(id) ON DELETE CASCADE
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_card_progress (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                card_id INTEGER NOT NULL,
                correct_count INTEGER NOT NULL DEFAULT 0,
                incorrect_count INTEGER NOT NULL DEFAULT 0,
                skipped_count INTEGER NOT NULL DEFAULT 0,
                current_streak INTEGER NOT NULL DEFAULT 0,
                last_seen_at TEXT,
                last_correct_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(user_id, card_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_card_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                card_id INTEGER NOT NULL,
                outcome TEXT NOT NULL,
                translation_direction TEXT NOT NULL DEFAULT "legacy",
                created_at TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
            )'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_set_finite_stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                set_id INTEGER NOT NULL,
                best_time_seconds INTEGER NOT NULL,
                completed_runs INTEGER NOT NULL DEFAULT 0,
                last_completed_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE(user_id, set_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (set_id) REFERENCES sets(id) ON DELETE CASCADE
            )'
        );

        if (!self::sqliteColumnExists($pdo, 'sets', 'language_id')) {
            $pdo->exec('ALTER TABLE sets ADD COLUMN language_id INTEGER');
        }

        if (!self::sqliteColumnExists($pdo, 'users', 'is_admin')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN is_admin INTEGER NOT NULL DEFAULT 0');
            $pdo->exec('UPDATE users SET is_admin = 1');
        }

        if (!self::sqliteColumnExists($pdo, 'users', 'first_name')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN first_name TEXT NOT NULL DEFAULT ""');
        }

        if (!self::sqliteColumnExists($pdo, 'users', 'last_name')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_name TEXT NOT NULL DEFAULT ""');
        }

        if (self::sqliteColumnExists($pdo, 'users', 'name')) {
            $pdo->exec('UPDATE users SET first_name = name WHERE first_name = "" AND name <> ""');
        }

        if (!self::sqliteColumnExists($pdo, 'cards', 'language_aliases')) {
            $pdo->exec('ALTER TABLE cards ADD COLUMN language_aliases TEXT NOT NULL DEFAULT ""');
        }

        if (!self::sqliteColumnExists($pdo, 'cards', 'english_aliases')) {
            $pdo->exec('ALTER TABLE cards ADD COLUMN english_aliases TEXT NOT NULL DEFAULT ""');
        }

        if (!self::sqliteColumnExists($pdo, 'user_card_attempts', 'translation_direction')) {
            $pdo->exec('ALTER TABLE user_card_attempts ADD COLUMN translation_direction TEXT NOT NULL DEFAULT "legacy"');
        }

        return [
            'driver' => 'sqlite',
            'path' => $database->config()->databasePath(),
        ];
    }

    private static function migrateMysql(Database $database): array
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
                translation_direction VARCHAR(20) NOT NULL DEFAULT "legacy",
                created_at VARCHAR(50) NOT NULL,
                KEY user_card_attempts_user_id_index (user_id),
                KEY user_card_attempts_card_id_index (card_id),
                CONSTRAINT user_card_attempts_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT user_card_attempts_card_fk FOREIGN KEY (card_id) REFERENCES cards(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS user_set_finite_stats (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                set_id BIGINT UNSIGNED NOT NULL,
                best_time_seconds INT NOT NULL,
                completed_runs INT NOT NULL DEFAULT 0,
                last_completed_at VARCHAR(50) NOT NULL,
                created_at VARCHAR(50) NOT NULL,
                updated_at VARCHAR(50) NOT NULL,
                UNIQUE KEY user_set_finite_stats_user_set_unique (user_id, set_id),
                KEY user_set_finite_stats_set_id_index (set_id),
                CONSTRAINT user_set_finite_stats_user_fk FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT user_set_finite_stats_set_fk FOREIGN KEY (set_id) REFERENCES sets(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $databaseName = self::databaseName($pdo);

        if (!self::mysqlColumnExists($pdo, $databaseName, 'sets', 'language_id')) {
            $pdo->exec('ALTER TABLE sets ADD COLUMN language_id BIGINT UNSIGNED NULL');
            $pdo->exec('ALTER TABLE sets ADD KEY sets_language_id_index (language_id)');
            $pdo->exec('ALTER TABLE sets ADD CONSTRAINT sets_language_id_fk FOREIGN KEY (language_id) REFERENCES languages(id) ON DELETE SET NULL');
        }

        if (!self::mysqlColumnExists($pdo, $databaseName, 'users', 'is_admin')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0');
            $pdo->exec('UPDATE users SET is_admin = 1');
        }

        if (!self::mysqlColumnExists($pdo, $databaseName, 'users', 'first_name')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN first_name VARCHAR(255) NOT NULL DEFAULT ""');
        }

        if (!self::mysqlColumnExists($pdo, $databaseName, 'users', 'last_name')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN last_name VARCHAR(255) NOT NULL DEFAULT ""');
        }

        if (self::mysqlColumnExists($pdo, $databaseName, 'users', 'name')) {
            $pdo->exec('UPDATE users SET first_name = name WHERE first_name = "" AND name <> ""');
        }

        if (!self::mysqlColumnExists($pdo, $databaseName, 'cards', 'language_aliases')) {
            $pdo->exec('ALTER TABLE cards ADD COLUMN language_aliases TEXT NOT NULL');
        }

        if (!self::mysqlColumnExists($pdo, $databaseName, 'cards', 'english_aliases')) {
            $pdo->exec('ALTER TABLE cards ADD COLUMN english_aliases TEXT NOT NULL');
        }

        if (!self::mysqlColumnExists($pdo, $databaseName, 'user_card_attempts', 'translation_direction')) {
            $pdo->exec('ALTER TABLE user_card_attempts ADD COLUMN translation_direction VARCHAR(20) NOT NULL DEFAULT "legacy"');
        }

        return [
            'driver' => 'mysql',
            'database' => $database->config()->databaseName(),
            'host' => $database->config()->databaseHost(),
            'port' => $database->config()->databasePort(),
        ];
    }

    private static function sqliteColumnExists(PDO $pdo, string $table, string $column): bool
    {
        $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
        $columnNames = array_column($columns, 'name');

        return in_array($column, $columnNames, true);
    }

    private static function mysqlColumnExists(PDO $pdo, string $databaseName, string $table, string $column): bool
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
