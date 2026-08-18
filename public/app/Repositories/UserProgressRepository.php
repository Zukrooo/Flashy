<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserProgressRepository
{
    public const SMART_SET_EASY = 'easy';
    public const SMART_SET_INCORRECT_RECENTLY = 'incorrect-recently';
    public const SMART_SET_DIFFICULT = 'difficult';
    public const SMART_SET_MEDIUM = 'medium';
    public const SMART_SET_NEW = 'new';
    public const SMART_SET_MASTERED = 'mastered';
    public const SMART_SET_SKIPPED_RECENTLY = 'skipped-recently';
    public const SMART_SET_NEEDS_REVIEW = 'needs-review';
    public const SMART_SET_IMPROVING = 'improving';
    public const SMART_SET_UNSTABLE = 'unstable';
    private const MIN_CLASSIFIED_ATTEMPTS = 3;
    private const REVIEW_AFTER_DAYS = 7;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function recordAnswer(int $userId, int $cardId, bool $correct, bool $skipped = false): void
    {
        $existing = $this->find($userId, $cardId);
        $now = date('c');
        $this->recordAttempt($userId, $cardId, $correct, $skipped, $now);

        if ($existing === null) {
            $statement = $this->pdo->prepare(
                'INSERT INTO user_card_progress
                 (user_id, card_id, correct_count, incorrect_count, skipped_count, current_streak, last_seen_at, last_correct_at, created_at, updated_at)
                 VALUES
                 (:user_id, :card_id, :correct_count, :incorrect_count, :skipped_count, :current_streak, :last_seen_at, :last_correct_at, :created_at, :updated_at)'
            );

            $statement->execute([
                'user_id' => $userId,
                'card_id' => $cardId,
                'correct_count' => $correct ? 1 : 0,
                'incorrect_count' => !$correct && !$skipped ? 1 : 0,
                'skipped_count' => $skipped ? 1 : 0,
                'current_streak' => $correct ? 1 : 0,
                'last_seen_at' => $now,
                'last_correct_at' => $correct ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        $correctCount = (int) $existing['correct_count'] + ($correct ? 1 : 0);
        $incorrectCount = (int) $existing['incorrect_count'] + (!$correct && !$skipped ? 1 : 0);
        $skippedCount = (int) $existing['skipped_count'] + ($skipped ? 1 : 0);
        $currentStreak = $correct ? ((int) $existing['current_streak'] + 1) : 0;

        $statement = $this->pdo->prepare(
            'UPDATE user_card_progress
             SET correct_count = :correct_count,
                 incorrect_count = :incorrect_count,
                 skipped_count = :skipped_count,
                 current_streak = :current_streak,
                 last_seen_at = :last_seen_at,
                 last_correct_at = :last_correct_at,
                 updated_at = :updated_at
             WHERE user_id = :user_id AND card_id = :card_id'
        );

        $statement->execute([
            'user_id' => $userId,
            'card_id' => $cardId,
            'correct_count' => $correctCount,
            'incorrect_count' => $incorrectCount,
            'skipped_count' => $skippedCount,
            'current_streak' => $currentStreak,
            'last_seen_at' => $now,
            'last_correct_at' => $correct ? $now : $existing['last_correct_at'],
            'updated_at' => $now,
        ]);
    }

    public function countsForLanguage(int $userId, int $languageId): array
    {
        $cards = $this->cardsWithProgressForLanguage($userId, $languageId);

        return $this->buildCounts(
            $userId,
            $cards,
            $this->recentIncorrectCardIdsForLanguage($userId, $languageId),
            $this->recentSkippedCardIdsForLanguage($userId, $languageId)
        );
    }

    public function countsForSet(int $userId, int $setId): array
    {
        $cards = $this->cardsWithProgressForSet($userId, $setId);

        return $this->buildCounts(
            $userId,
            $cards,
            $this->recentIncorrectCardIdsForSet($userId, $setId),
            $this->recentSkippedCardIdsForSet($userId, $setId)
        );
    }

    public function cardsForSmartSet(int $userId, int $languageId, string $smartSet): array
    {
        return $this->filterCardsForSmartSet(
            $userId,
            $this->cardsWithProgressForLanguage($userId, $languageId),
            $smartSet,
            $this->recentIncorrectCardIdsForLanguage($userId, $languageId),
            $this->recentSkippedCardIdsForLanguage($userId, $languageId)
        );
    }

    public function cardsForSetSmartSet(int $userId, int $setId, string $smartSet): array
    {
        return $this->filterCardsForSmartSet(
            $userId,
            $this->cardsWithProgressForSet($userId, $setId),
            $smartSet,
            $this->recentIncorrectCardIdsForSet($userId, $setId),
            $this->recentSkippedCardIdsForSet($userId, $setId)
        );
    }

    public function isValidSmartSet(string $smartSet): bool
    {
        return in_array($smartSet, [
            self::SMART_SET_EASY,
            self::SMART_SET_INCORRECT_RECENTLY,
            self::SMART_SET_DIFFICULT,
            self::SMART_SET_MEDIUM,
            self::SMART_SET_NEW,
            self::SMART_SET_MASTERED,
            self::SMART_SET_SKIPPED_RECENTLY,
            self::SMART_SET_NEEDS_REVIEW,
            self::SMART_SET_IMPROVING,
            self::SMART_SET_UNSTABLE,
        ], true);
    }

    public function clearUserData(int $userId): void
    {
        $deleteAttempts = $this->pdo->prepare('DELETE FROM user_card_attempts WHERE user_id = :user_id');
        $deleteAttempts->execute(['user_id' => $userId]);

        $deleteProgress = $this->pdo->prepare('DELETE FROM user_card_progress WHERE user_id = :user_id');
        $deleteProgress->execute(['user_id' => $userId]);
    }

    private function classifyCard(array $card): string
    {
        $correctCount = (int) ($card['correct_count'] ?? 0);
        $incorrectCount = (int) ($card['incorrect_count'] ?? 0);
        $skippedCount = (int) ($card['skipped_count'] ?? 0);
        $totalAttempts = $correctCount + $incorrectCount + $skippedCount;

        if ($totalAttempts < self::MIN_CLASSIFIED_ATTEMPTS) {
            return self::SMART_SET_NEW;
        }

        $wrongRate = ($incorrectCount + $skippedCount) / $totalAttempts;

        if ($wrongRate >= 0.70) {
            return self::SMART_SET_DIFFICULT;
        }

        if ($wrongRate >= 0.35) {
            return self::SMART_SET_MEDIUM;
        }

        return self::SMART_SET_EASY;
    }

    private function isMastered(array $card): bool
    {
        $correctCount = (int) ($card['correct_count'] ?? 0);
        $incorrectCount = (int) ($card['incorrect_count'] ?? 0);
        $skippedCount = (int) ($card['skipped_count'] ?? 0);
        $currentStreak = (int) ($card['current_streak'] ?? 0);
        $totalAttempts = $correctCount + $incorrectCount + $skippedCount;

        if ($totalAttempts < 5 || $currentStreak < 5) {
            return false;
        }

        $wrongRate = $totalAttempts === 0 ? 1.0 : ($incorrectCount + $skippedCount) / $totalAttempts;

        return $wrongRate < 0.35;
    }

    private function needsReview(array $card): bool
    {
        $lastSeenAt = (string) ($card['last_seen_at'] ?? '');
        $correctCount = (int) ($card['correct_count'] ?? 0);
        $incorrectCount = (int) ($card['incorrect_count'] ?? 0);
        $skippedCount = (int) ($card['skipped_count'] ?? 0);
        $totalAttempts = $correctCount + $incorrectCount + $skippedCount;

        if ($lastSeenAt === '' || $totalAttempts < self::MIN_CLASSIFIED_ATTEMPTS) {
            return false;
        }

        $lastSeenTimestamp = strtotime($lastSeenAt);

        if ($lastSeenTimestamp === false) {
            return false;
        }

        return $lastSeenTimestamp <= strtotime('-' . self::REVIEW_AFTER_DAYS . ' days');
    }

    private function isImproving(int $userId, array $card): bool
    {
        $correctCount = (int) ($card['correct_count'] ?? 0);
        $incorrectCount = (int) ($card['incorrect_count'] ?? 0);
        $skippedCount = (int) ($card['skipped_count'] ?? 0);
        $totalAttempts = $correctCount + $incorrectCount + $skippedCount;

        if ($totalAttempts < 5) {
            return false;
        }

        $lifetimeWrongRate = ($incorrectCount + $skippedCount) / $totalAttempts;
        $recentAttempts = $this->lastAttemptsForCard($userId, (int) $card['id'], 5);

        if (count($recentAttempts) < 3) {
            return false;
        }

        $recentWrongCount = count(array_filter(
            $recentAttempts,
            static fn (string $attempt): bool => in_array($attempt, ['incorrect', 'skipped'], true)
        ));
        $recentCorrectCount = count(array_filter(
            $recentAttempts,
            static fn (string $attempt): bool => $attempt === 'correct'
        ));
        $recentWrongRate = $recentWrongCount / count($recentAttempts);

        return $lifetimeWrongRate >= 0.35
            && $recentWrongRate <= 0.34
            && $recentCorrectCount >= 3;
    }

    private function isUnstable(int $userId, array $card): bool
    {
        $recentAttempts = $this->lastAttemptsForCard($userId, (int) $card['id'], 5);

        if (count($recentAttempts) < 4) {
            return false;
        }

        $recentWrongCount = count(array_filter(
            $recentAttempts,
            static fn (string $attempt): bool => in_array($attempt, ['incorrect', 'skipped'], true)
        ));
        $recentCorrectCount = count(array_filter(
            $recentAttempts,
            static fn (string $attempt): bool => $attempt === 'correct'
        ));

        return $recentCorrectCount >= 2 && $recentWrongCount >= 2;
    }

    private function buildCounts(int $userId, array $cards, array $recentIncorrectIds, array $recentSkippedIds): array
    {
        $counts = [
            self::SMART_SET_NEW => 0,
            self::SMART_SET_DIFFICULT => 0,
            self::SMART_SET_MEDIUM => 0,
            self::SMART_SET_EASY => 0,
            self::SMART_SET_INCORRECT_RECENTLY => 0,
            self::SMART_SET_MASTERED => 0,
            self::SMART_SET_SKIPPED_RECENTLY => 0,
            self::SMART_SET_NEEDS_REVIEW => 0,
            self::SMART_SET_IMPROVING => 0,
            self::SMART_SET_UNSTABLE => 0,
        ];

        foreach ($cards as $card) {
            $classification = $this->classifyCard($card);
            $counts[$classification]++;

            if (isset($recentIncorrectIds[(int) $card['id']])) {
                $counts[self::SMART_SET_INCORRECT_RECENTLY]++;
            }

            if ($this->isMastered($card)) {
                $counts[self::SMART_SET_MASTERED]++;
            }

            if (isset($recentSkippedIds[(int) $card['id']])) {
                $counts[self::SMART_SET_SKIPPED_RECENTLY]++;
            }

            if ($this->needsReview($card)) {
                $counts[self::SMART_SET_NEEDS_REVIEW]++;
            }

            if ($this->isImproving($userId, $card)) {
                $counts[self::SMART_SET_IMPROVING]++;
            }

            if ($this->isUnstable($userId, $card)) {
                $counts[self::SMART_SET_UNSTABLE]++;
            }
        }

        return $counts;
    }

    private function filterCardsForSmartSet(
        int $userId,
        array $cards,
        string $smartSet,
        array $recentIncorrectIds,
        array $recentSkippedIds
    ): array {
        return array_values(array_filter($cards, function (array $card) use ($smartSet, $recentIncorrectIds, $recentSkippedIds, $userId): bool {
            return match ($smartSet) {
                self::SMART_SET_NEW => $this->classifyCard($card) === self::SMART_SET_NEW,
                self::SMART_SET_DIFFICULT => $this->classifyCard($card) === self::SMART_SET_DIFFICULT,
                self::SMART_SET_MEDIUM => $this->classifyCard($card) === self::SMART_SET_MEDIUM,
                self::SMART_SET_EASY => $this->classifyCard($card) === self::SMART_SET_EASY,
                self::SMART_SET_INCORRECT_RECENTLY => isset($recentIncorrectIds[(int) $card['id']]),
                self::SMART_SET_MASTERED => $this->isMastered($card),
                self::SMART_SET_SKIPPED_RECENTLY => isset($recentSkippedIds[(int) $card['id']]),
                self::SMART_SET_NEEDS_REVIEW => $this->needsReview($card),
                self::SMART_SET_IMPROVING => $this->isImproving($userId, $card),
                self::SMART_SET_UNSTABLE => $this->isUnstable($userId, $card),
                default => false,
            };
        }));
    }

    private function cardsWithProgressForLanguage(int $userId, int $languageId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT cards.*,
                    sets.name AS set_name,
                    COALESCE(progress.correct_count, 0) AS correct_count,
                    COALESCE(progress.incorrect_count, 0) AS incorrect_count,
                    COALESCE(progress.skipped_count, 0) AS skipped_count,
                    COALESCE(progress.current_streak, 0) AS current_streak,
                    progress.last_seen_at
             FROM cards
             INNER JOIN sets ON sets.id = cards.set_id
             LEFT JOIN user_card_progress AS progress
               ON progress.card_id = cards.id AND progress.user_id = :user_id
             WHERE sets.published = 1
               AND sets.language_id = :language_id
             ORDER BY sets.name ASC, cards.id ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'language_id' => $languageId,
        ]);

        return $statement->fetchAll();
    }

    private function cardsWithProgressForSet(int $userId, int $setId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT cards.*,
                    sets.name AS set_name,
                    COALESCE(progress.correct_count, 0) AS correct_count,
                    COALESCE(progress.incorrect_count, 0) AS incorrect_count,
                    COALESCE(progress.skipped_count, 0) AS skipped_count,
                    COALESCE(progress.current_streak, 0) AS current_streak,
                    progress.last_seen_at
             FROM cards
             INNER JOIN sets ON sets.id = cards.set_id
             LEFT JOIN user_card_progress AS progress
               ON progress.card_id = cards.id AND progress.user_id = :user_id
             WHERE sets.published = 1
               AND sets.id = :set_id
             ORDER BY cards.id ASC'
        );
        $statement->execute([
            'user_id' => $userId,
            'set_id' => $setId,
        ]);

        return $statement->fetchAll();
    }

    private function recentIncorrectCardIdsForLanguage(int $userId, int $languageId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT recent.card_id
             FROM (
                 SELECT attempts.card_id, attempts.outcome
                 FROM user_card_attempts AS attempts
                 INNER JOIN cards ON cards.id = attempts.card_id
                 INNER JOIN sets ON sets.id = cards.set_id
                 WHERE attempts.user_id = :user_id
                   AND sets.published = 1
                   AND sets.language_id = :language_id
                 ORDER BY attempts.created_at DESC, attempts.id DESC
             ) AS recent
             WHERE recent.outcome IN ("incorrect", "skipped")
             GROUP BY recent.card_id
             HAVING SUM(CASE WHEN recent.outcome IN ("incorrect", "skipped") THEN 1 ELSE 0 END) >= 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'language_id' => $languageId,
        ]);

        $cardIds = [];

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $cardId) {
            $recentAttempts = $this->lastAttemptsForCard($userId, (int) $cardId, 5);

            foreach ($recentAttempts as $attempt) {
                if (in_array($attempt, ['incorrect', 'skipped'], true)) {
                    $cardIds[(int) $cardId] = true;
                    break;
                }
            }
        }

        return $cardIds;
    }

    private function recentIncorrectCardIdsForSet(int $userId, int $setId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT recent.card_id
             FROM (
                 SELECT attempts.card_id, attempts.outcome
                 FROM user_card_attempts AS attempts
                 INNER JOIN cards ON cards.id = attempts.card_id
                 INNER JOIN sets ON sets.id = cards.set_id
                 WHERE attempts.user_id = :user_id
                   AND sets.published = 1
                   AND sets.id = :set_id
                 ORDER BY attempts.created_at DESC, attempts.id DESC
             ) AS recent
             WHERE recent.outcome IN ("incorrect", "skipped")
             GROUP BY recent.card_id
             HAVING SUM(CASE WHEN recent.outcome IN ("incorrect", "skipped") THEN 1 ELSE 0 END) >= 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'set_id' => $setId,
        ]);

        $cardIds = [];

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $cardId) {
            $recentAttempts = $this->lastAttemptsForCard($userId, (int) $cardId, 5);

            foreach ($recentAttempts as $attempt) {
                if (in_array($attempt, ['incorrect', 'skipped'], true)) {
                    $cardIds[(int) $cardId] = true;
                    break;
                }
            }
        }

        return $cardIds;
    }

    private function recentSkippedCardIdsForLanguage(int $userId, int $languageId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT recent.card_id
             FROM (
                 SELECT attempts.card_id, attempts.outcome
                 FROM user_card_attempts AS attempts
                 INNER JOIN cards ON cards.id = attempts.card_id
                 INNER JOIN sets ON sets.id = cards.set_id
                 WHERE attempts.user_id = :user_id
                   AND sets.published = 1
                   AND sets.language_id = :language_id
                 ORDER BY attempts.created_at DESC, attempts.id DESC
             ) AS recent
             WHERE recent.outcome = "skipped"
             GROUP BY recent.card_id'
        );
        $statement->execute([
            'user_id' => $userId,
            'language_id' => $languageId,
        ]);

        $cardIds = [];

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $cardId) {
            $recentAttempts = $this->lastAttemptsForCard($userId, (int) $cardId, 5);

            foreach ($recentAttempts as $attempt) {
                if ($attempt === 'skipped') {
                    $cardIds[(int) $cardId] = true;
                    break;
                }
            }
        }

        return $cardIds;
    }

    private function recentSkippedCardIdsForSet(int $userId, int $setId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT DISTINCT recent.card_id
             FROM (
                 SELECT attempts.card_id, attempts.outcome
                 FROM user_card_attempts AS attempts
                 INNER JOIN cards ON cards.id = attempts.card_id
                 INNER JOIN sets ON sets.id = cards.set_id
                 WHERE attempts.user_id = :user_id
                   AND sets.published = 1
                   AND sets.id = :set_id
                 ORDER BY attempts.created_at DESC, attempts.id DESC
             ) AS recent
             WHERE recent.outcome = "skipped"
             GROUP BY recent.card_id'
        );
        $statement->execute([
            'user_id' => $userId,
            'set_id' => $setId,
        ]);

        $cardIds = [];

        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $cardId) {
            $recentAttempts = $this->lastAttemptsForCard($userId, (int) $cardId, 5);

            foreach ($recentAttempts as $attempt) {
                if ($attempt === 'skipped') {
                    $cardIds[(int) $cardId] = true;
                    break;
                }
            }
        }

        return $cardIds;
    }

    private function lastAttemptsForCard(int $userId, int $cardId, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT outcome
             FROM user_card_attempts
             WHERE user_id = :user_id AND card_id = :card_id
             ORDER BY created_at DESC, id DESC
             LIMIT :limit'
        );
        $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
        $statement->bindValue('card_id', $cardId, PDO::PARAM_INT);
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function recordAttempt(int $userId, int $cardId, bool $correct, bool $skipped, string $timestamp): void
    {
        $outcome = $correct ? 'correct' : ($skipped ? 'skipped' : 'incorrect');
        $statement = $this->pdo->prepare(
            'INSERT INTO user_card_attempts (user_id, card_id, outcome, created_at)
             VALUES (:user_id, :card_id, :outcome, :created_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'card_id' => $cardId,
            'outcome' => $outcome,
            'created_at' => $timestamp,
        ]);
    }

    private function find(int $userId, int $cardId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM user_card_progress WHERE user_id = :user_id AND card_id = :card_id LIMIT 1'
        );
        $statement->execute([
            'user_id' => $userId,
            'card_id' => $cardId,
        ]);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }
}
