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
    public const DIRECTION_TO_ENGLISH = 'to_english';
    public const DIRECTION_TO_LANGUAGE = 'to_language';
    private const DIRECTION_LEGACY = 'legacy';
    private const MIN_CLASSIFIED_ATTEMPTS = 3;
    private const REVIEW_AFTER_DAYS = 7;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function recordAnswer(
        int $userId,
        int $cardId,
        string $translationDirection,
        bool $correct,
        bool $skipped = false
    ): void {
        $existing = $this->find($userId, $cardId);
        $now = date('c');
        $this->recordAttempt($userId, $cardId, $translationDirection, $correct, $skipped, $now);

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
        return $this->buildCounts($this->cardsWithDirectionMetrics($userId, $this->cardsForPublishedLanguage($languageId)));
    }

    public function countsForSet(int $userId, int $setId): array
    {
        return $this->buildCounts($this->cardsWithDirectionMetrics($userId, $this->cardsForPublishedSet($setId)));
    }

    public function cardsForSmartSet(int $userId, int $languageId, string $smartSet): array
    {
        return $this->filterCardsForSmartSet(
            $this->cardsWithDirectionMetrics($userId, $this->cardsForPublishedLanguage($languageId)),
            $smartSet
        );
    }

    public function cardsForSetSmartSet(int $userId, int $setId, string $smartSet): array
    {
        return $this->filterCardsForSmartSet(
            $this->cardsWithDirectionMetrics($userId, $this->cardsForPublishedSet($setId)),
            $smartSet
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
        foreach ($this->directionStats($card) as $stats) {
            if ($this->totalAttempts($stats) < self::MIN_CLASSIFIED_ATTEMPTS) {
                return self::SMART_SET_NEW;
            }
        }

        $worstWrongRate = 0.0;

        foreach ($this->directionStats($card) as $stats) {
            $worstWrongRate = max($worstWrongRate, $this->wrongRate($stats));
        }

        if ($worstWrongRate >= 0.70) {
            return self::SMART_SET_DIFFICULT;
        }

        if ($worstWrongRate >= 0.35) {
            return self::SMART_SET_MEDIUM;
        }

        return self::SMART_SET_EASY;
    }

    private function isMastered(array $card): bool
    {
        foreach ($this->directionStats($card) as $stats) {
            if ($this->totalAttempts($stats) < 5 || (int) ($stats['current_streak'] ?? 0) < 5) {
                return false;
            }

            if ($this->wrongRate($stats) >= 0.35) {
                return false;
            }
        }

        return true;
    }

    private function needsReview(array $card): bool
    {
        foreach ($this->directionStats($card) as $stats) {
            $lastSeenAt = (string) ($stats['last_seen_at'] ?? '');

            if ($lastSeenAt === '' || $this->totalAttempts($stats) < self::MIN_CLASSIFIED_ATTEMPTS) {
                continue;
            }

            $lastSeenTimestamp = strtotime($lastSeenAt);

            if ($lastSeenTimestamp !== false && $lastSeenTimestamp <= strtotime('-' . self::REVIEW_AFTER_DAYS . ' days')) {
                return true;
            }
        }

        return false;
    }

    private function isImproving(array $card): bool
    {
        foreach ($this->directionStats($card) as $stats) {
            $totalAttempts = $this->totalAttempts($stats);

            if ($totalAttempts < 5) {
                continue;
            }

            $recentAttempts = $stats['recent_attempts'] ?? [];

            if (count($recentAttempts) < 3) {
                continue;
            }

            $lifetimeWrongRate = $this->wrongRate($stats);
            $recentWrongCount = count(array_filter(
                $recentAttempts,
                static fn (string $attempt): bool => in_array($attempt, ['incorrect', 'skipped'], true)
            ));
            $recentCorrectCount = count(array_filter(
                $recentAttempts,
                static fn (string $attempt): bool => $attempt === 'correct'
            ));
            $recentWrongRate = $recentWrongCount / count($recentAttempts);

            if ($lifetimeWrongRate >= 0.35 && $recentWrongRate <= 0.34 && $recentCorrectCount >= 3) {
                return true;
            }
        }

        return false;
    }

    private function isUnstable(array $card): bool
    {
        foreach ($this->directionStats($card) as $stats) {
            $recentAttempts = $stats['recent_attempts'] ?? [];

            if (count($recentAttempts) < 4) {
                continue;
            }

            $recentWrongCount = count(array_filter(
                $recentAttempts,
                static fn (string $attempt): bool => in_array($attempt, ['incorrect', 'skipped'], true)
            ));
            $recentCorrectCount = count(array_filter(
                $recentAttempts,
                static fn (string $attempt): bool => $attempt === 'correct'
            ));

            if ($recentCorrectCount >= 2 && $recentWrongCount >= 2) {
                return true;
            }
        }

        return false;
    }

    private function buildCounts(array $cards): array
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
            $counts[$this->classifyCard($card)]++;

            if ($this->hasRecentIncorrect($card)) {
                $counts[self::SMART_SET_INCORRECT_RECENTLY]++;
            }

            if ($this->isMastered($card)) {
                $counts[self::SMART_SET_MASTERED]++;
            }

            if ($this->hasRecentSkipped($card)) {
                $counts[self::SMART_SET_SKIPPED_RECENTLY]++;
            }

            if ($this->needsReview($card)) {
                $counts[self::SMART_SET_NEEDS_REVIEW]++;
            }

            if ($this->isImproving($card)) {
                $counts[self::SMART_SET_IMPROVING]++;
            }

            if ($this->isUnstable($card)) {
                $counts[self::SMART_SET_UNSTABLE]++;
            }
        }

        return $counts;
    }

    private function filterCardsForSmartSet(array $cards, string $smartSet): array
    {
        return array_values(array_filter($cards, function (array $card) use ($smartSet): bool {
            return match ($smartSet) {
                self::SMART_SET_NEW => $this->classifyCard($card) === self::SMART_SET_NEW,
                self::SMART_SET_DIFFICULT => $this->classifyCard($card) === self::SMART_SET_DIFFICULT,
                self::SMART_SET_MEDIUM => $this->classifyCard($card) === self::SMART_SET_MEDIUM,
                self::SMART_SET_EASY => $this->classifyCard($card) === self::SMART_SET_EASY,
                self::SMART_SET_INCORRECT_RECENTLY => $this->hasRecentIncorrect($card),
                self::SMART_SET_MASTERED => $this->isMastered($card),
                self::SMART_SET_SKIPPED_RECENTLY => $this->hasRecentSkipped($card),
                self::SMART_SET_NEEDS_REVIEW => $this->needsReview($card),
                self::SMART_SET_IMPROVING => $this->isImproving($card),
                self::SMART_SET_UNSTABLE => $this->isUnstable($card),
                default => false,
            };
        }));
    }

    private function cardsForPublishedLanguage(int $languageId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT cards.*, sets.name AS set_name
             FROM cards
             INNER JOIN sets ON sets.id = cards.set_id
             WHERE sets.published = 1
               AND sets.language_id = :language_id
             ORDER BY sets.name ASC, cards.id ASC'
        );
        $statement->execute(['language_id' => $languageId]);

        return $statement->fetchAll();
    }

    private function cardsForPublishedSet(int $setId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT cards.*, sets.name AS set_name
             FROM cards
             INNER JOIN sets ON sets.id = cards.set_id
             WHERE sets.published = 1
               AND sets.id = :set_id
             ORDER BY cards.id ASC'
        );
        $statement->execute(['set_id' => $setId]);

        return $statement->fetchAll();
    }

    private function cardsWithDirectionMetrics(int $userId, array $cards): array
    {
        if ($cards === []) {
            return [];
        }

        $cardIds = array_map(static fn (array $card): int => (int) $card['id'], $cards);
        $statsByCard = $this->directionMetricsForCards($userId, $cardIds);

        return array_map(function (array $card) use ($statsByCard): array {
            $card['direction_stats'] = $statsByCard[(int) $card['id']] ?? $this->emptyDirectionStats();
            return $card;
        }, $cards);
    }

    private function directionMetricsForCards(int $userId, array $cardIds): array
    {
        if ($cardIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($cardIds), '?'));
        $statement = $this->pdo->prepare(
            'SELECT card_id, outcome, translation_direction, created_at, id
             FROM user_card_attempts
             WHERE user_id = ?
               AND card_id IN (' . $placeholders . ')
             ORDER BY created_at ASC, id ASC'
        );
        $statement->execute([$userId, ...$cardIds]);

        $metrics = [];

        foreach ($cardIds as $cardId) {
            $metrics[(int) $cardId] = $this->emptyDirectionStats();
        }

        foreach ($statement->fetchAll() as $attempt) {
            $cardId = (int) $attempt['card_id'];
            $outcome = (string) $attempt['outcome'];
            $timestamp = (string) $attempt['created_at'];
            $directions = $this->directionsForAttempt((string) ($attempt['translation_direction'] ?? self::DIRECTION_LEGACY));

            foreach ($directions as $direction) {
                $stats = $metrics[$cardId][$direction];

                if ($outcome === 'correct') {
                    $stats['correct_count']++;
                    $stats['current_streak']++;
                    $stats['last_correct_at'] = $timestamp;
                } elseif ($outcome === 'skipped') {
                    $stats['skipped_count']++;
                    $stats['current_streak'] = 0;
                } else {
                    $stats['incorrect_count']++;
                    $stats['current_streak'] = 0;
                }

                $stats['last_seen_at'] = $timestamp;
                $stats['attempts'][] = $outcome;
                $metrics[$cardId][$direction] = $stats;
            }
        }

        foreach ($metrics as $cardId => $directions) {
            foreach ($directions as $direction => $stats) {
                $stats['recent_attempts'] = array_slice(array_reverse($stats['attempts']), 0, 5);
                unset($stats['attempts']);
                $metrics[$cardId][$direction] = $stats;
            }
        }

        return $metrics;
    }

    private function emptyDirectionStats(): array
    {
        return [
            self::DIRECTION_TO_ENGLISH => $this->emptyStatsBucket(),
            self::DIRECTION_TO_LANGUAGE => $this->emptyStatsBucket(),
        ];
    }

    private function emptyStatsBucket(): array
    {
        return [
            'correct_count' => 0,
            'incorrect_count' => 0,
            'skipped_count' => 0,
            'current_streak' => 0,
            'last_seen_at' => null,
            'last_correct_at' => null,
            'attempts' => [],
            'recent_attempts' => [],
        ];
    }

    private function directionStats(array $card): array
    {
        return is_array($card['direction_stats'] ?? null)
            ? $card['direction_stats']
            : $this->emptyDirectionStats();
    }

    private function totalAttempts(array $stats): int
    {
        return (int) ($stats['correct_count'] ?? 0)
            + (int) ($stats['incorrect_count'] ?? 0)
            + (int) ($stats['skipped_count'] ?? 0);
    }

    private function wrongRate(array $stats): float
    {
        $totalAttempts = $this->totalAttempts($stats);

        if ($totalAttempts === 0) {
            return 1.0;
        }

        return ((int) ($stats['incorrect_count'] ?? 0) + (int) ($stats['skipped_count'] ?? 0)) / $totalAttempts;
    }

    private function hasRecentIncorrect(array $card): bool
    {
        foreach ($this->directionStats($card) as $stats) {
            foreach (($stats['recent_attempts'] ?? []) as $attempt) {
                if (in_array($attempt, ['incorrect', 'skipped'], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasRecentSkipped(array $card): bool
    {
        foreach ($this->directionStats($card) as $stats) {
            foreach (($stats['recent_attempts'] ?? []) as $attempt) {
                if ($attempt === 'skipped') {
                    return true;
                }
            }
        }

        return false;
    }

    private function directionsForAttempt(string $direction): array
    {
        return match ($direction) {
            self::DIRECTION_TO_ENGLISH => [self::DIRECTION_TO_ENGLISH],
            self::DIRECTION_TO_LANGUAGE => [self::DIRECTION_TO_LANGUAGE],
            default => [self::DIRECTION_TO_ENGLISH, self::DIRECTION_TO_LANGUAGE],
        };
    }

    private function recordAttempt(
        int $userId,
        int $cardId,
        string $translationDirection,
        bool $correct,
        bool $skipped,
        string $timestamp
    ): void {
        $outcome = $correct ? 'correct' : ($skipped ? 'skipped' : 'incorrect');
        $statement = $this->pdo->prepare(
            'INSERT INTO user_card_attempts (user_id, card_id, outcome, translation_direction, created_at)
             VALUES (:user_id, :card_id, :outcome, :translation_direction, :created_at)'
        );
        $statement->execute([
            'user_id' => $userId,
            'card_id' => $cardId,
            'outcome' => $outcome,
            'translation_direction' => $this->normalizeDirection($translationDirection),
            'created_at' => $timestamp,
        ]);
    }

    private function normalizeDirection(string $direction): string
    {
        return match ($direction) {
            self::DIRECTION_TO_ENGLISH => self::DIRECTION_TO_ENGLISH,
            self::DIRECTION_TO_LANGUAGE => self::DIRECTION_TO_LANGUAGE,
            default => self::DIRECTION_LEGACY,
        };
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
