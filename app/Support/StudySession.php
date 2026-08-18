<?php

declare(strict_types=1);

namespace App\Support;

final class StudySession
{
    private const KEY = 'study_sessions';
    public const MODE_BILINGUAL = 'bilingual';
    public const MODE_TO_ENGLISH = 'to_english';
    public const MODE_TO_LANGUAGE = 'to_language';
    public const STUDY_MODE_INFINITE = 'infinite';
    public const STUDY_MODE_FINITE = 'finite';
    public const WRONG_MODE_STAY = 'stay';
    public const WRONG_MODE_ADVANCE = 'advance';

    public function start(
        string $sessionKey,
        array $cards,
        string $mode = self::MODE_BILINGUAL,
        string $studyMode = self::STUDY_MODE_FINITE,
        string $wrongMode = self::WRONG_MODE_STAY
    ): void
    {
        $normalizedCards = $this->normalizeCards($cards);
        $_SESSION[self::KEY][$sessionKey] = [
            'cards' => $normalizedCards,
            'card_order' => $this->buildCardOrder($normalizedCards),
            'mode' => $this->normalizeMode($mode),
            'study_mode' => $this->normalizeStudyMode($studyMode),
            'wrong_mode' => $this->normalizeWrongMode($wrongMode),
            'started_at' => time(),
            'score' => 0,
            'asked' => 0,
            'card_total' => count($normalizedCards),
            'results' => [],
            'current_card' => null,
            'complete' => false,
            'completion_recorded' => false,
        ];

        $this->advance($sessionKey);
    }

    public function current(string $sessionKey): ?array
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return null;
        }

        return $session['current_card'] ?? null;
    }

    public function mode(string $sessionKey): string
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return self::MODE_BILINGUAL;
        }

        return $this->normalizeMode((string) ($session['mode'] ?? self::MODE_BILINGUAL));
    }

    public function studyMode(string $sessionKey): string
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return self::STUDY_MODE_INFINITE;
        }

        return $this->normalizeStudyMode((string) ($session['study_mode'] ?? self::STUDY_MODE_INFINITE));
    }

    public function wrongMode(string $sessionKey): string
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return self::WRONG_MODE_STAY;
        }

        return $this->normalizeWrongMode((string) ($session['wrong_mode'] ?? self::WRONG_MODE_STAY));
    }

    public function setMode(string $sessionKey, string $mode): void
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return;
        }

        $session['mode'] = $this->normalizeMode($mode);
        $_SESSION[self::KEY][$sessionKey] = $session;

        if (($session['cards'] ?? []) !== []) {
            $this->advance($sessionKey);
        }
    }

    public function lockMode(string $sessionKey, string $mode): void
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return;
        }

        $session['mode'] = $this->normalizeMode($mode);

        if (is_array($session['current_card'] ?? null)) {
            $currentCardId = (int) ($session['current_card']['id'] ?? 0);
            $sourceCard = $this->findCardById($session['cards'] ?? [], $currentCardId);

            if ($sourceCard !== null) {
                $askForEnglish = match ($session['mode']) {
                    self::MODE_TO_ENGLISH => true,
                    self::MODE_TO_LANGUAGE => false,
                    default => $this->inferAskForEnglish($session['current_card']),
                };

                $session['current_card'] = $this->buildCurrentCard($sourceCard, $askForEnglish);
            }
        }

        $_SESSION[self::KEY][$sessionKey] = $session;
    }

    public function setStudyMode(string $sessionKey, string $studyMode): void
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return;
        }

        $normalizedCards = $session['cards'] ?? [];

        if (!is_array($normalizedCards) || $normalizedCards === []) {
            return;
        }

        $_SESSION[self::KEY][$sessionKey] = [
            'cards' => $normalizedCards,
            'card_order' => $this->buildCardOrder($normalizedCards),
            'mode' => $this->normalizeMode((string) ($session['mode'] ?? self::MODE_BILINGUAL)),
            'study_mode' => $this->normalizeStudyMode($studyMode),
            'wrong_mode' => $this->normalizeWrongMode((string) ($session['wrong_mode'] ?? self::WRONG_MODE_STAY)),
            'started_at' => time(),
            'score' => 0,
            'asked' => 0,
            'card_total' => count($normalizedCards),
            'results' => [],
            'current_card' => null,
            'complete' => false,
            'completion_recorded' => false,
        ];

        $this->advance($sessionKey);
    }

    public function setWrongMode(string $sessionKey, string $wrongMode): void
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return;
        }

        $session['wrong_mode'] = $this->normalizeWrongMode($wrongMode);
        $_SESSION[self::KEY][$sessionKey] = $session;
    }

    public function syncCards(string $sessionKey, array $cards): void
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return;
        }

        $session['cards'] = $this->normalizeCards($cards);
        $session['card_total'] = count($session['cards']);

        $studyMode = $this->normalizeStudyMode((string) ($session['study_mode'] ?? self::STUDY_MODE_INFINITE));

        if ($studyMode === self::STUDY_MODE_FINITE && ($session['complete'] ?? false) === true && !is_array($session['current_card'] ?? null)) {
            $_SESSION[self::KEY][$sessionKey] = $session;
            return;
        }

        $session['card_order'] = $this->syncCardOrder(
            $session['cards'],
            $session['card_order'] ?? [],
            (int) (($session['current_card']['id'] ?? 0)),
            $studyMode
        );

        if (is_array($session['current_card'] ?? null)) {
            $currentCardId = (int) $session['current_card']['id'];
            $mode = $this->normalizeMode((string) ($session['mode'] ?? self::MODE_BILINGUAL));
            $direction = $mode === self::MODE_BILINGUAL
                ? (($session['current_card']['answer_language'] ?? 'English') === 'English' ? self::MODE_TO_ENGLISH : self::MODE_TO_LANGUAGE)
                : $mode;

            foreach ($session['cards'] as $sourceCard) {
                if ((int) $sourceCard['id'] !== $currentCardId) {
                    continue;
                }

                $session['current_card'] = $this->buildCurrentCard($sourceCard, $direction === self::MODE_TO_ENGLISH);
                $_SESSION[self::KEY][$sessionKey] = $session;
                return;
            }
        }

        $_SESSION[self::KEY][$sessionKey] = $session;
    }

    public function answer(string $sessionKey, string $answer): ?array
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return null;
        }

        $card = $session['current_card'] ?? null;

        if (!is_array($card)) {
            return null;
        }

        $normalizedAnswer = self::normalize($answer);
        $correct = in_array($normalizedAnswer, $card['accepted_answers'], true);
        $wrongMode = $this->normalizeWrongMode((string) ($session['wrong_mode'] ?? self::WRONG_MODE_STAY));

        if ($correct) {
            $session['score']++;
        }

        $session['asked']++;
        $session['results'][] = [
            'card_id' => (int) $card['id'],
            'prompt' => $card['prompt'],
            'expected' => $card['expected'],
            'answer' => $normalizedAnswer,
            'prompt_language' => $card['prompt_language'],
            'answer_language' => $card['answer_language'],
            'translation_direction' => $card['translation_direction'],
            'correct' => $correct,
            'set_name' => $card['set_name'],
        ];

        $_SESSION[self::KEY][$sessionKey] = $session;

        if ($correct || $wrongMode === self::WRONG_MODE_ADVANCE) {
            $this->advance($sessionKey);
        }

        return end($_SESSION[self::KEY][$sessionKey]['results']) ?: null;
    }

    public function skip(string $sessionKey): ?array
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return null;
        }

        $card = $session['current_card'] ?? null;

        if (!is_array($card)) {
            return null;
        }

        $session['asked']++;
        $session['results'][] = [
            'card_id' => (int) $card['id'],
            'prompt' => $card['prompt'],
            'expected' => $card['expected'],
            'answer' => 'skipped',
            'prompt_language' => $card['prompt_language'],
            'answer_language' => $card['answer_language'],
            'translation_direction' => $card['translation_direction'],
            'correct' => false,
            'skipped' => true,
            'set_name' => $card['set_name'],
        ];

        $_SESSION[self::KEY][$sessionKey] = $session;
        $this->advance($sessionKey);

        return end($_SESSION[self::KEY][$sessionKey]['results']) ?: null;
    }

    public function summary(string $sessionKey): ?array
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return null;
        }

        return [
            'total' => (int) $session['asked'],
            'score' => (int) $session['score'],
            'card_total' => (int) ($session['card_total'] ?? count($session['cards'] ?? [])),
            'study_mode' => $this->normalizeStudyMode((string) ($session['study_mode'] ?? self::STUDY_MODE_INFINITE)),
            'elapsed_seconds' => $this->elapsedSeconds($session),
            'results' => array_reverse($session['results']),
            'complete' => (bool) ($session['complete'] ?? false)
                || (
                    $this->normalizeStudyMode((string) ($session['study_mode'] ?? self::STUDY_MODE_INFINITE)) === self::STUDY_MODE_FINITE
                    && !is_array($session['current_card'] ?? null)
                    && (int) ($session['asked'] ?? 0) >= (int) ($session['card_total'] ?? 0)
                ),
        ];
    }

    public function completionRecorded(string $sessionKey): bool
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        return is_array($session) && (bool) ($session['completion_recorded'] ?? false);
    }

    public function markCompletionRecorded(string $sessionKey): void
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session)) {
            return;
        }

        $session['completion_recorded'] = true;
        $_SESSION[self::KEY][$sessionKey] = $session;
    }

    public function clear(string $sessionKey): void
    {
        unset($_SESSION[self::KEY][$sessionKey]);
    }

    private function advance(string $sessionKey): void
    {
        $session = $_SESSION[self::KEY][$sessionKey] ?? null;

        if (!is_array($session) || $session['cards'] === []) {
            return;
        }

        $studyMode = $this->normalizeStudyMode((string) ($session['study_mode'] ?? self::STUDY_MODE_INFINITE));
        $currentCardId = (int) (($session['current_card']['id'] ?? 0));
        $session['card_order'] = $this->syncCardOrder($session['cards'], $session['card_order'] ?? [], $currentCardId, $studyMode);

        if (($session['card_order'] ?? []) === []) {
            $session['current_card'] = null;
            $session['complete'] = $studyMode === self::STUDY_MODE_FINITE;
            $_SESSION[self::KEY][$sessionKey] = $session;
            return;
        }

        $nextCardId = array_shift($session['card_order']);
        $sourceCard = $this->findCardById($session['cards'], (int) $nextCardId);

        if ($sourceCard === null) {
            $_SESSION[self::KEY][$sessionKey] = $session;
            $this->advance($sessionKey);
            return;
        }

        $session['complete'] = false;
        $mode = $this->normalizeMode((string) ($session['mode'] ?? self::MODE_BILINGUAL));
        $askForEnglish = match ($mode) {
            self::MODE_TO_ENGLISH => true,
            self::MODE_TO_LANGUAGE => false,
            default => random_int(0, 1) === 1,
        };

        $session['current_card'] = $this->buildCurrentCard($sourceCard, $askForEnglish);
        $_SESSION[self::KEY][$sessionKey] = $session;
    }

    private function normalizeCards(array $cards): array
    {
        return array_map(static function (array $card): array {
            return [
                'id' => (int) $card['id'],
                'language_word' => self::normalize($card['gaidhlig']),
                'english_word' => self::normalize($card['english']),
                'language_aliases' => (string) ($card['language_aliases'] ?? ''),
                'english_aliases' => (string) ($card['english_aliases'] ?? ''),
                'set_name' => $card['set_name'] ?? null,
            ];
        }, $cards);
    }

    private function syncCardOrder(
        array $cards,
        array $existingOrder,
        int $currentCardId = 0,
        string $studyMode = self::STUDY_MODE_INFINITE
    ): array
    {
        $availableIds = array_map(static fn (array $card): int => (int) $card['id'], $cards);
        $availableLookup = array_fill_keys($availableIds, true);

        $order = array_values(array_filter(
            array_map('intval', $existingOrder),
            static fn (int $id): bool => isset($availableLookup[$id]) && $id !== $currentCardId
        ));
        $order = array_values(array_unique($order));

        $queuedLookup = array_fill_keys($order, true);
        $missing = array_values(array_filter(
            $availableIds,
            static fn (int $id): bool => !isset($queuedLookup[$id]) && $id !== $currentCardId
        ));

        if ($missing !== [] && $studyMode === self::STUDY_MODE_INFINITE) {
            shuffle($missing);
            $order = [...$order, ...$missing];
        }

        if ($order === [] && $studyMode === self::STUDY_MODE_INFINITE && $currentCardId !== 0) {
            if (count($availableIds) === 1) {
                return [$availableIds[0]];
            }

            if (count($availableIds) > 1) {
                $recycled = array_values(array_filter(
                    $availableIds,
                    static fn (int $id): bool => $id !== $currentCardId
                ));
                shuffle($recycled);
                return $recycled;
            }
        }

        return $order;
    }

    private function buildCardOrder(array $cards): array
    {
        $ids = array_map(static fn (array $card): int => (int) $card['id'], $cards);
        shuffle($ids);

        return $ids;
    }

    private function findCardById(array $cards, int $cardId): ?array
    {
        foreach ($cards as $card) {
            if ((int) $card['id'] === $cardId) {
                return $card;
            }
        }

        return null;
    }

    private function elapsedSeconds(array $session): int
    {
        $startedAt = (int) ($session['started_at'] ?? time());

        return max(0, time() - $startedAt);
    }

    private function buildCurrentCard(array $sourceCard, bool $askForEnglish): array
    {
        return [
            'id' => $sourceCard['id'],
            'prompt' => $askForEnglish ? $sourceCard['language_word'] : $sourceCard['english_word'],
            'expected' => $askForEnglish ? $sourceCard['english_word'] : $sourceCard['language_word'],
            'accepted_answers' => $askForEnglish
                ? self::acceptedAnswers($sourceCard['english_word'], $sourceCard['english_aliases'])
                : self::acceptedAnswers($sourceCard['language_word'], $sourceCard['language_aliases']),
            'prompt_language' => $askForEnglish ? 'Language' : 'English',
            'answer_language' => $askForEnglish ? 'English' : 'Target',
            'translation_direction' => $askForEnglish ? self::MODE_TO_ENGLISH : self::MODE_TO_LANGUAGE,
            'set_name' => $sourceCard['set_name'],
        ];
    }

    private function inferAskForEnglish(array $card): bool
    {
        $direction = (string) ($card['translation_direction'] ?? '');

        if ($direction === self::MODE_TO_ENGLISH) {
            return true;
        }

        if ($direction === self::MODE_TO_LANGUAGE) {
            return false;
        }

        return ($card['answer_language'] ?? 'English') === 'English';
    }

    private static function acceptedAnswers(string $primary, string $aliases): array
    {
        $answers = [self::normalize($primary)];

        foreach (preg_split('/\r\n|\r|\n|\|/', $aliases) ?: [] as $alias) {
            $alias = self::normalize($alias);

            if ($alias !== '') {
                $answers[] = $alias;
            }
        }

        return array_values(array_unique($answers));
    }

    private static function normalize(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }

    private function normalizeMode(string $mode): string
    {
        return match ($mode) {
            self::MODE_TO_ENGLISH,
            self::MODE_TO_LANGUAGE => $mode,
            default => self::MODE_BILINGUAL,
        };
    }

    private function normalizeStudyMode(string $studyMode): string
    {
        return match ($studyMode) {
            self::STUDY_MODE_FINITE => self::STUDY_MODE_FINITE,
            default => self::STUDY_MODE_INFINITE,
        };
    }

    private function normalizeWrongMode(string $wrongMode): string
    {
        return match ($wrongMode) {
            self::WRONG_MODE_ADVANCE => self::WRONG_MODE_ADVANCE,
            default => self::WRONG_MODE_STAY,
        };
    }
}
