<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class LanguageRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function allWithCounts(bool $publishedOnly = false): array
    {
        $condition = $publishedOnly ? 'AND sets.published = 1' : '';

        $statement = $this->pdo->query(
            'SELECT languages.*,
                    COUNT(DISTINCT sets.id) AS set_count,
                    COUNT(cards.id) AS card_count
             FROM languages
             LEFT JOIN sets ON sets.language_id = languages.id ' . $condition . '
             LEFT JOIN cards ON cards.set_id = sets.id
             GROUP BY languages.id
             ORDER BY languages.name ASC'
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT languages.*,
                    COUNT(DISTINCT CASE WHEN sets.published = 1 THEN sets.id END) AS set_count,
                    COUNT(CASE WHEN sets.published = 1 THEN cards.id END) AS card_count
             FROM languages
             LEFT JOIN sets ON sets.language_id = languages.id
             LEFT JOIN cards ON cards.set_id = sets.id
             WHERE languages.id = :id
             GROUP BY languages.id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $language = $statement->fetch();

        return $language === false ? null : $language;
    }

    public function create(string $name, string $description): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO languages (name, description, created_at)
             VALUES (:name, :description, :created_at)'
        );

        $statement->execute([
            'name' => $name,
            'description' => $description,
            'created_at' => date('c'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $description): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE languages SET name = :name, description = :description WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'name' => $name,
            'description' => $description,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM languages WHERE id = :id');
        $statement->execute(['id' => $id]);
    }
}
