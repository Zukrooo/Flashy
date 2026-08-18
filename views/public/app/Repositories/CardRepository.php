<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class CardRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function forSet(int $setId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM cards WHERE set_id = :set_id ORDER BY created_at DESC, id DESC'
        );
        $statement->execute(['set_id' => $setId]);

        return $statement->fetchAll();
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM cards WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $card = $statement->fetch();

        return $card === false ? null : $card;
    }

    public function create(
        int $setId,
        string $gaidhlig,
        string $english,
        string $languageAliases = '',
        string $englishAliases = ''
    ): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO cards (set_id, gaidhlig, english, language_aliases, english_aliases, created_at)
             VALUES (:set_id, :gaidhlig, :english, :language_aliases, :english_aliases, :created_at)'
        );

        $statement->execute([
            'set_id' => $setId,
            'gaidhlig' => $gaidhlig,
            'english' => $english,
            'language_aliases' => $languageAliases,
            'english_aliases' => $englishAliases,
            'created_at' => date('c'),
        ]);
    }

    public function update(
        int $id,
        string $gaidhlig,
        string $english,
        string $languageAliases = '',
        string $englishAliases = ''
    ): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE cards
             SET gaidhlig = :gaidhlig,
                 english = :english,
                 language_aliases = :language_aliases,
                 english_aliases = :english_aliases
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $id,
            'gaidhlig' => $gaidhlig,
            'english' => $english,
            'language_aliases' => $languageAliases,
            'english_aliases' => $englishAliases,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM cards WHERE id = :id');
        $statement->execute(['id' => $id]);
    }
}
