<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class SetRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function allPublishedForLanguage(int $languageId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT sets.*, languages.name AS language_name, COUNT(cards.id) AS card_count
             FROM sets
             INNER JOIN languages ON languages.id = sets.language_id
             LEFT JOIN cards ON cards.set_id = sets.id
             WHERE sets.published = 1 AND sets.language_id = :language_id
             GROUP BY sets.id
             ORDER BY sets.created_at DESC'
        );
        $statement->execute(['language_id' => $languageId]);

        return $statement->fetchAll();
    }

    public function allWithCounts(): array
    {
        $statement = $this->pdo->query(
            'SELECT sets.*, languages.name AS language_name, COUNT(cards.id) AS card_count
             FROM sets
             INNER JOIN languages ON languages.id = sets.language_id
             LEFT JOIN cards ON cards.set_id = sets.id
             GROUP BY sets.id
             ORDER BY languages.name ASC, sets.created_at DESC'
        );

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT sets.*, languages.name AS language_name
             FROM sets
             INNER JOIN languages ON languages.id = sets.language_id
             WHERE sets.id = :id
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $set = $statement->fetch();

        return $set === false ? null : $set;
    }

    public function create(int $languageId, string $name, string $description, bool $published): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO sets (language_id, name, description, published, created_at)
             VALUES (:language_id, :name, :description, :published, :created_at)'
        );

        $statement->execute([
            'language_id' => $languageId,
            'name' => $name,
            'description' => $description,
            'published' => $published ? 1 : 0,
            'created_at' => date('c'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $languageId, string $name, string $description, bool $published): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE sets
             SET language_id = :language_id, name = :name, description = :description, published = :published
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'language_id' => $languageId,
            'name' => $name,
            'description' => $description,
            'published' => $published ? 1 : 0,
        ]);
    }

    public function setPublished(int $id, bool $published): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE sets SET published = :published WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'published' => $published ? 1 : 0,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM sets WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function cardsForPublishedLanguage(int $languageId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT cards.*, sets.name AS set_name
             FROM cards
             INNER JOIN sets ON sets.id = cards.set_id
             WHERE sets.published = 1 AND sets.language_id = :language_id
             ORDER BY cards.created_at DESC, cards.id DESC'
        );
        $statement->execute(['language_id' => $languageId]);

        return $statement->fetchAll();
    }
}
