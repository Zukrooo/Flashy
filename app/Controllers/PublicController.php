<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Repositories\CardRepository;
use App\Repositories\LanguageRepository;
use App\Repositories\SetRepository;
use App\Repositories\UserProgressRepository;
use App\Support\Auth;
use App\Support\Flash;
use App\Support\StudySession;

final class PublicController
{
    public function __construct(
        private readonly LanguageRepository $languages,
        private readonly SetRepository $sets,
        private readonly CardRepository $cards,
        private readonly UserProgressRepository $progress,
        private readonly StudySession $studySession,
        private readonly Auth $auth
    ) {
    }

    public function home(): void
    {
        if (!$this->auth->checkUser()) {
            $this->resetGuestStudyState();
        }

        $languages = array_values(array_filter(
            $this->languages->allWithCounts(true),
            static fn (array $language): bool => (int) $language['set_count'] > 0
        ));

        View::render('public/home', [
            'title' => 'Learn Languages',
            'languages' => $languages,
            'is_logged_in' => $this->auth->checkUser(),
            'is_practice_mode' => false,
        ]);
    }

    public function practiceHome(): void
    {
        $languages = array_values(array_filter(
            $this->languages->allWithCounts(true),
            static fn (array $language): bool => (int) $language['set_count'] > 0
        ));

        View::render('public/home', [
            'title' => 'Practice Languages',
            'languages' => $languages,
            'is_logged_in' => $this->auth->checkUser(),
            'is_practice_mode' => true,
        ]);
    }

    public function language(int $languageId): void
    {
        if (!$this->auth->checkUser()) {
            $this->resetGuestStudyState();
        }

        $language = $this->languages->find($languageId);

        if ($language === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Language not found']);
            return;
        }

        $sets = $this->sets->allPublishedForLanguage($languageId);

        if ($sets === []) {
            Flash::put('error', 'This language has no published sets yet.');
            redirect('/');
        }

        $smartSets = [];
        $userId = $this->auth->userId();

        if ($userId !== null) {
            $counts = $this->progress->countsForLanguage($userId, $languageId);

            foreach ($this->smartSetDefinitions() as $key => $definition) {
                $smartSets[] = [
                    'key' => $key,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'count' => $counts[$key] ?? 0,
                ];
            }
        }

        View::render('public/language', [
            'title' => $language['name'],
            'language' => $language,
            'sets' => $sets,
            'smart_sets' => $smartSets,
            'show_smart_sets' => $userId !== null,
            'is_practice_mode' => false,
        ]);
    }

    public function practiceLanguage(int $languageId): void
    {
        $language = $this->languages->find($languageId);

        if ($language === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Language not found']);
            return;
        }

        $sets = $this->sets->allPublishedForLanguage($languageId);

        if ($sets === []) {
            Flash::put('error', 'This language has no published sets yet.');
            redirect('/practice');
        }

        View::render('public/language', [
            'title' => 'Practice ' . $language['name'],
            'language' => $language,
            'sets' => $sets,
            'smart_sets' => [],
            'show_smart_sets' => false,
            'is_practice_mode' => true,
        ]);
    }

    public function startSet(int $setId): void
    {
        $set = $this->sets->find($setId);

        if ($set === null || (int) $set['published'] !== 1) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        $cards = $this->cards->forSet($setId);

        if ($cards === []) {
            Flash::put('error', 'This set has no cards yet.');
            redirect('/');
        }

        $this->studySession->start(
            $this->setSessionKey($setId),
            $cards,
            (string) ($_POST['mode'] ?? StudySession::MODE_BILINGUAL),
            $this->sanitizeStudyMode((string) ($_POST['study_mode'] ?? StudySession::STUDY_MODE_FINITE)),
            (string) ($_POST['wrong_mode'] ?? StudySession::WRONG_MODE_STAY)
        );
        $this->rememberStudy('/study/set/' . $setId);
        redirect('/study/set/' . $setId);
    }

    public function startPracticeSet(int $setId): void
    {
        $set = $this->sets->find($setId);

        if ($set === null || (int) $set['published'] !== 1) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        $cards = $this->cards->forSet($setId);

        if ($cards === []) {
            Flash::put('error', 'This set has no cards yet.');
            redirect('/');
        }

        $recordsProgress = $this->auth->checkUser() && isset($_POST['records_progress']) && $_POST['records_progress'] === '1';

        $this->studySession->start(
            $this->practiceSetSessionKey($setId),
            $cards,
            (string) ($_POST['mode'] ?? StudySession::MODE_BILINGUAL),
            StudySession::STUDY_MODE_INFINITE,
            (string) ($_POST['wrong_mode'] ?? StudySession::WRONG_MODE_STAY),
            $recordsProgress
        );
        $this->rememberStudy('/practice/set/' . $setId);
        redirect('/practice/set/' . $setId);
    }

    public function set(int $setId): void
    {
        if (!$this->auth->checkUser()) {
            $this->resetGuestStudyState();
        }

        $set = $this->sets->find($setId);

        if ($set === null || (int) $set['published'] !== 1) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        $cards = $this->cards->forSet($setId);

        if ($cards === []) {
            Flash::put('error', 'This set has no cards yet.');
            redirect('/languages/' . $set['language_id']);
        }

        $smartSets = [];
        $userId = $this->auth->userId();

        if ($userId !== null) {
            $counts = $this->progress->countsForSet($userId, $setId);

            foreach ($this->smartSetDefinitions() as $key => $definition) {
                $smartSets[] = [
                    'key' => $key,
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'count' => $counts[$key] ?? 0,
                ];
            }
        }

        $finiteSetStat = $userId !== null ? $this->progress->finiteSetStat($userId, $setId) : null;

        View::render('public/set', [
            'title' => $set['name'],
            'set' => $set,
            'card_count' => count($cards),
            'smart_sets' => $smartSets,
            'show_smart_sets' => $userId !== null,
            'finite_set_stat' => $finiteSetStat,
            'is_practice_mode' => false,
        ]);
    }

    public function practiceSetLanding(int $setId): void
    {
        $set = $this->sets->find($setId);
        $cards = $this->cards->forSet($setId);

        if ($set === null || (int) $set['published'] !== 1) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        if ($cards === []) {
            Flash::put('error', 'This set has no cards yet.');
            redirect('/practice/languages/' . $set['language_id']);
        }

        View::render('public/set', [
            'title' => 'Practice ' . $set['name'],
            'set' => $set,
            'card_count' => count($cards),
            'smart_sets' => [],
            'show_smart_sets' => false,
            'finite_set_stat' => null,
            'is_practice_mode' => true,
        ]);
    }

    public function startLanguageAll(int $languageId): void
    {
        $language = $this->languages->find($languageId);

        if ($language === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Language not found']);
            return;
        }

        $cards = $this->sets->cardsForPublishedLanguage($languageId);

        if ($cards === []) {
            Flash::put('error', 'This language has no published cards yet.');
            redirect('/');
        }

        $this->studySession->start(
            $this->allSessionKey($languageId),
            $cards,
            (string) ($_POST['mode'] ?? StudySession::MODE_BILINGUAL),
            $this->sanitizeStudyMode((string) ($_POST['study_mode'] ?? StudySession::STUDY_MODE_FINITE)),
            (string) ($_POST['wrong_mode'] ?? StudySession::WRONG_MODE_STAY)
        );
        $this->rememberStudy('/study/language/' . $languageId . '/all');
        redirect('/study/language/' . $languageId . '/all');
    }

    public function startSmartSet(int $languageId, string $smartSet): void
    {
        $this->auth->requireUser();

        $language = $this->languages->find($languageId);

        if ($language === null || !$this->progress->isValidSmartSet($smartSet)) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Study set not found']);
            return;
        }

        $cards = $this->progress->cardsForSmartSet($this->auth->userId() ?? 0, $languageId, $smartSet);

        if ($cards === []) {
            Flash::put('error', 'This smart study set is empty right now.');
            redirect('/languages/' . $languageId);
        }

        $this->studySession->start(
            $this->smartSessionKey($languageId, $smartSet),
            $cards,
            StudySession::MODE_BILINGUAL,
            $this->sanitizeStudyMode((string) ($_POST['study_mode'] ?? StudySession::STUDY_MODE_FINITE)),
            (string) ($_POST['wrong_mode'] ?? StudySession::WRONG_MODE_STAY)
        );
        $this->rememberStudy('/study/language/' . $languageId . '/smart/' . $smartSet);
        redirect('/study/language/' . $languageId . '/smart/' . $smartSet);
    }

    public function startSetSmartSet(int $setId, string $smartSet): void
    {
        $this->auth->requireUser();
        $set = $this->sets->find($setId);

        if ($set === null || (int) $set['published'] !== 1 || !$this->progress->isValidSmartSet($smartSet)) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Study set not found']);
            return;
        }

        $cards = $this->progress->cardsForSetSmartSet($this->auth->userId() ?? 0, $setId, $smartSet);

        if ($cards === []) {
            Flash::put('error', 'This smart study set is empty right now.');
            redirect('/sets/' . $setId);
        }

        $path = '/study/set/' . $setId . '/smart/' . $smartSet;
        $this->studySession->start(
            $this->setSmartSessionKey($setId, $smartSet),
            $cards,
            StudySession::MODE_BILINGUAL,
            $this->sanitizeStudyMode((string) ($_POST['study_mode'] ?? StudySession::STUDY_MODE_FINITE)),
            (string) ($_POST['wrong_mode'] ?? StudySession::WRONG_MODE_STAY)
        );
        $this->rememberStudy($path);
        redirect($path);
    }

    public function studySet(int $setId): void
    {
        $set = $this->sets->find($setId);
        $cards = $this->cards->forSet($setId);
        $sessionKey = $this->setSessionKey($setId);
        $this->studySession->syncCards($sessionKey, $cards);
        $currentCard = $this->studySession->current($sessionKey);
        $summary = $this->studySession->summary($sessionKey);

        if (($summary['complete'] ?? false) === true) {
            redirect('/study/set/' . $setId . '/complete');
        }

        if ($set === null || $currentCard === null) {
            redirect('/');
        }

        $this->rememberStudy('/study/set/' . $setId);

        View::render('public/study', [
            'title' => 'Study ' . $set['name'],
            'context' => [
                'name' => $set['name'],
                'subtitle' => $set['language_name'],
                'scope_label' => 'Set',
                'pills' => [$set['language_name'], $set['name']],
                'back_path' => '/sets/' . $setId,
                'answer_path' => '/study/set/' . $setId . '/answer',
                'session_key' => $sessionKey,
                'mode' => $this->studySession->mode($sessionKey),
                'mode_path' => '/study/set/' . $setId . '/mode',
                'study_mode' => $this->studySession->studyMode($sessionKey),
                'study_mode_path' => '/study/set/' . $setId . '/study-mode',
                'wrong_mode' => $this->studySession->wrongMode($sessionKey),
                'wrong_mode_path' => '/study/set/' . $setId . '/wrong-mode',
                'reset_path' => '/study/set/' . $setId . '/reset',
                'restart_path' => '/sets/' . $setId . '/start',
                'finish_path' => '/sets/' . $setId,
                'best_finite_time_seconds' => ($this->auth->userId() !== null
                    ? (int) (($this->progress->finiteSetStat($this->auth->userId(), $setId)['best_time_seconds'] ?? 0))
                    : 0),
            ],
            'card' => $currentCard,
            'summary' => $summary,
        ]);
    }

    public function practiceSet(int $setId): void
    {
        $set = $this->sets->find($setId);
        $cards = $this->cards->forSet($setId);
        $sessionKey = $this->practiceSetSessionKey($setId);
        $this->studySession->syncCards($sessionKey, $cards);
        $currentCard = $this->studySession->current($sessionKey);
        $summary = $this->studySession->summary($sessionKey);

        if ($set === null || $currentCard === null) {
            redirect('/sets/' . $setId);
        }

        $this->rememberStudy('/practice/set/' . $setId);

        View::render('public/study', [
            'title' => 'Practice ' . $set['name'],
            'context' => [
                'name' => $set['name'],
                'subtitle' => $set['language_name'],
                'scope_label' => 'Practice set',
                'pills' => [$set['language_name'], $set['name']],
                'back_path' => '/sets/' . $setId,
                'answer_path' => '/practice/set/' . $setId . '/answer',
                'session_key' => $sessionKey,
                'mode' => $this->studySession->mode($sessionKey),
                'mode_path' => '/practice/set/' . $setId . '/mode',
                'show_study_mode' => false,
                'settings_title' => 'Practice Settings',
                'study_mode' => StudySession::STUDY_MODE_INFINITE,
                'wrong_mode' => $this->studySession->wrongMode($sessionKey),
                'wrong_mode_path' => '/practice/set/' . $setId . '/wrong-mode',
                'reset_path' => '/practice/set/' . $setId . '/reset',
                'restart_path' => '/practice/sets/' . $setId . '/start',
                'finish_path' => '/sets/' . $setId,
                'is_practice' => true,
                'practice_progress_path' => '/practice/set/' . $setId . '/smart-set-influence',
                'practice_records_progress' => $this->auth->checkUser() && $this->studySession->recordsProgress($sessionKey),
                'show_practice_progress_setting' => $this->auth->checkUser(),
            ],
            'card' => $currentCard,
            'summary' => $summary,
        ]);
    }

    public function studyLanguageAll(int $languageId): void
    {
        $language = $this->languages->find($languageId);
        $cards = $this->sets->cardsForPublishedLanguage($languageId);
        $sessionKey = $this->allSessionKey($languageId);
        $this->studySession->syncCards($sessionKey, $cards);
        $currentCard = $this->studySession->current($sessionKey);
        $summary = $this->studySession->summary($sessionKey);

        if (($summary['complete'] ?? false) === true) {
            redirect('/study/language/' . $languageId . '/all/complete');
        }

        if ($language === null || $currentCard === null) {
            redirect('/');
        }

        $this->rememberStudy('/study/language/' . $languageId . '/all');

        View::render('public/study', [
            'title' => 'Study ' . $language['name'],
            'context' => [
                'name' => 'All',
                'subtitle' => $language['name'],
                'scope_label' => 'All published sets',
                'pills' => [$language['name']],
                'scope_detail' => 'Studying every published set currently available in ' . $language['name'] . '.',
                'back_path' => '/languages/' . $languageId,
                'answer_path' => '/study/language/' . $languageId . '/all/answer',
                'session_key' => $sessionKey,
                'mode' => $this->studySession->mode($sessionKey),
                'mode_path' => '/study/language/' . $languageId . '/all/mode',
                'study_mode' => $this->studySession->studyMode($sessionKey),
                'study_mode_path' => '/study/language/' . $languageId . '/all/study-mode',
                'wrong_mode' => $this->studySession->wrongMode($sessionKey),
                'wrong_mode_path' => '/study/language/' . $languageId . '/all/wrong-mode',
                'reset_path' => '/study/language/' . $languageId . '/all/reset',
                'restart_path' => '/languages/' . $languageId . '/start-all',
                'finish_path' => '/languages/' . $languageId,
            ],
            'card' => $currentCard,
            'summary' => $summary,
        ]);
    }

    public function studySmartSet(int $languageId, string $smartSet): void
    {
        $this->auth->requireUser();

        $language = $this->languages->find($languageId);
        $cards = $this->progress->cardsForSmartSet($this->auth->userId() ?? 0, $languageId, $smartSet);
        $sessionKey = $this->smartSessionKey($languageId, $smartSet);
        $this->studySession->syncCards($sessionKey, $cards);
        $this->studySession->lockMode($sessionKey, StudySession::MODE_BILINGUAL);
        $currentCard = $this->studySession->current($sessionKey);
        $summary = $this->studySession->summary($sessionKey);

        if (($summary['complete'] ?? false) === true) {
            redirect('/study/language/' . $languageId . '/smart/' . $smartSet . '/complete');
        }

        if ($language === null || !$this->progress->isValidSmartSet($smartSet) || $currentCard === null) {
            redirect('/languages/' . $languageId);
        }

        $path = '/study/language/' . $languageId . '/smart/' . $smartSet;
        $this->rememberStudy($path);

        View::render('public/study', [
            'title' => 'Study ' . $language['name'],
            'context' => [
                'name' => $this->smartSetDefinitions()[$smartSet]['name'],
                'subtitle' => $language['name'],
                'scope_label' => 'Smart set',
                'pills' => [$language['name']],
                'scope_detail' => $this->smartSetDefinitions()[$smartSet]['description'],
                'back_path' => '/languages/' . $languageId,
                'answer_path' => $path . '/answer',
                'session_key' => $sessionKey,
                'mode' => $this->studySession->mode($sessionKey),
                'mode_path' => $path . '/mode',
                'show_translation_modes' => false,
                'study_mode' => $this->studySession->studyMode($sessionKey),
                'study_mode_path' => $path . '/study-mode',
                'wrong_mode' => $this->studySession->wrongMode($sessionKey),
                'wrong_mode_path' => $path . '/wrong-mode',
                'reset_path' => $path . '/reset',
                'restart_path' => '/languages/' . $languageId . '/smart/' . $smartSet . '/start',
                'finish_path' => '/languages/' . $languageId,
            ],
            'card' => $currentCard,
            'summary' => $summary,
        ]);
    }

    public function studySetSmartSet(int $setId, string $smartSet): void
    {
        $this->auth->requireUser();

        $set = $this->sets->find($setId);
        $cards = $this->progress->cardsForSetSmartSet($this->auth->userId() ?? 0, $setId, $smartSet);
        $sessionKey = $this->setSmartSessionKey($setId, $smartSet);
        $this->studySession->syncCards($sessionKey, $cards);
        $this->studySession->lockMode($sessionKey, StudySession::MODE_BILINGUAL);
        $currentCard = $this->studySession->current($sessionKey);
        $summary = $this->studySession->summary($sessionKey);

        if (($summary['complete'] ?? false) === true) {
            redirect('/study/set/' . $setId . '/smart/' . $smartSet . '/complete');
        }

        if ($set === null || (int) $set['published'] !== 1 || !$this->progress->isValidSmartSet($smartSet) || $currentCard === null) {
            redirect('/sets/' . $setId);
        }

        $path = '/study/set/' . $setId . '/smart/' . $smartSet;
        $this->rememberStudy($path);

        View::render('public/study', [
            'title' => 'Study ' . $set['name'],
            'context' => [
                'name' => $this->smartSetDefinitions()[$smartSet]['name'],
                'subtitle' => $set['language_name'],
                'scope_label' => 'Smart set',
                'pills' => [$set['language_name'], $set['name']],
                'scope_detail' => $this->smartSetDefinitions()[$smartSet]['description'],
                'back_path' => '/sets/' . $setId,
                'answer_path' => $path . '/answer',
                'session_key' => $sessionKey,
                'mode' => $this->studySession->mode($sessionKey),
                'mode_path' => $path . '/mode',
                'show_translation_modes' => false,
                'study_mode' => $this->studySession->studyMode($sessionKey),
                'study_mode_path' => $path . '/study-mode',
                'wrong_mode' => $this->studySession->wrongMode($sessionKey),
                'wrong_mode_path' => $path . '/wrong-mode',
                'reset_path' => $path . '/reset',
                'restart_path' => '/sets/' . $setId . '/smart/' . $smartSet . '/start',
                'finish_path' => '/sets/' . $setId,
            ],
            'card' => $currentCard,
            'summary' => $summary,
        ]);
    }

    public function answerSet(int $setId): void
    {
        $this->studySession->syncCards($this->setSessionKey($setId), $this->cards->forSet($setId));
        $this->handleAnswerResponse($this->setSessionKey($setId), (string) ($_POST['answer'] ?? ''), '/study/set/' . $setId, $setId);
    }

    public function answerPracticeSet(int $setId): void
    {
        $sessionKey = $this->practiceSetSessionKey($setId);
        $this->studySession->syncCards($sessionKey, $this->cards->forSet($setId));
        $this->handleAnswerResponse($sessionKey, (string) ($_POST['answer'] ?? ''), '/practice/set/' . $setId);
    }

    public function answerLanguageAll(int $languageId): void
    {
        $this->studySession->syncCards($this->allSessionKey($languageId), $this->sets->cardsForPublishedLanguage($languageId));
        $this->handleAnswerResponse($this->allSessionKey($languageId), (string) ($_POST['answer'] ?? ''), '/study/language/' . $languageId . '/all');
    }

    public function answerSmartSet(int $languageId, string $smartSet): void
    {
        $this->auth->requireUser();
        $sessionKey = $this->smartSessionKey($languageId, $smartSet);
        $this->studySession->syncCards($sessionKey, $this->progress->cardsForSmartSet($this->auth->userId() ?? 0, $languageId, $smartSet));
        $this->handleAnswerResponse($sessionKey, (string) ($_POST['answer'] ?? ''), '/study/language/' . $languageId . '/smart/' . $smartSet);
    }

    public function answerSetSmartSet(int $setId, string $smartSet): void
    {
        $this->auth->requireUser();
        $sessionKey = $this->setSmartSessionKey($setId, $smartSet);
        $this->studySession->syncCards($sessionKey, $this->progress->cardsForSetSmartSet($this->auth->userId() ?? 0, $setId, $smartSet));
        $this->handleAnswerResponse($sessionKey, (string) ($_POST['answer'] ?? ''), '/study/set/' . $setId . '/smart/' . $smartSet);
    }

    public function skipSet(int $setId): void
    {
        $this->studySession->syncCards($this->setSessionKey($setId), $this->cards->forSet($setId));
        $this->handleSkipResponse($this->setSessionKey($setId), '/study/set/' . $setId, $setId);
    }

    public function skipPracticeSet(int $setId): void
    {
        $sessionKey = $this->practiceSetSessionKey($setId);
        $this->studySession->syncCards($sessionKey, $this->cards->forSet($setId));
        $this->handleSkipResponse($sessionKey, '/practice/set/' . $setId);
    }

    public function skipLanguageAll(int $languageId): void
    {
        $this->studySession->syncCards($this->allSessionKey($languageId), $this->sets->cardsForPublishedLanguage($languageId));
        $this->handleSkipResponse($this->allSessionKey($languageId), '/study/language/' . $languageId . '/all');
    }

    public function skipSmartSet(int $languageId, string $smartSet): void
    {
        $this->auth->requireUser();
        $sessionKey = $this->smartSessionKey($languageId, $smartSet);
        $this->studySession->syncCards($sessionKey, $this->progress->cardsForSmartSet($this->auth->userId() ?? 0, $languageId, $smartSet));
        $this->handleSkipResponse($sessionKey, '/study/language/' . $languageId . '/smart/' . $smartSet);
    }

    public function skipSetSmartSet(int $setId, string $smartSet): void
    {
        $this->auth->requireUser();
        $sessionKey = $this->setSmartSessionKey($setId, $smartSet);
        $this->studySession->syncCards($sessionKey, $this->progress->cardsForSetSmartSet($this->auth->userId() ?? 0, $setId, $smartSet));
        $this->handleSkipResponse($sessionKey, '/study/set/' . $setId . '/smart/' . $smartSet);
    }

    public function resetSet(int $setId): void
    {
        $path = '/study/set/' . $setId;
        $sessionKey = $this->setSessionKey($setId);
        $this->studySession->clear($sessionKey);
        unset($_SESSION['last_result'][$sessionKey]);
        $this->forgetStudy($path);
        redirect('/sets/' . $setId);
    }

    public function resetPracticeSet(int $setId): void
    {
        $path = '/practice/set/' . $setId;
        $sessionKey = $this->practiceSetSessionKey($setId);
        $this->studySession->clear($sessionKey);
        unset($_SESSION['last_result'][$sessionKey]);
        $this->forgetStudy($path);
        redirect('/sets/' . $setId);
    }

    public function resetLanguageAll(int $languageId): void
    {
        $path = '/study/language/' . $languageId . '/all';
        $sessionKey = $this->allSessionKey($languageId);
        $this->studySession->clear($sessionKey);
        unset($_SESSION['last_result'][$sessionKey]);
        $this->forgetStudy($path);
        redirect('/');
    }

    public function completeSet(int $setId): void
    {
        $set = $this->sets->find($setId);
        $sessionKey = $this->setSessionKey($setId);
        $summary = $this->studySession->summary($sessionKey);

        if ($set === null || $summary === null) {
            redirect('/');
        }

        $summary = $this->attachFiniteSetTiming($sessionKey, $setId, $summary);

        View::render('public/complete', [
            'title' => 'Study complete',
            'context' => [
                'name' => $set['name'],
                'subtitle' => $set['language_name'],
                'reset_path' => '/study/set/' . $setId . '/reset',
                'restart_path' => '/sets/' . $setId . '/start',
            ],
            'summary' => $summary,
        ]);
    }

    public function completeLanguageAll(int $languageId): void
    {
        $language = $this->languages->find($languageId);
        $sessionKey = $this->allSessionKey($languageId);
        $summary = $this->studySession->summary($sessionKey);

        if ($language === null || $summary === null) {
            redirect('/');
        }

        View::render('public/complete', [
            'title' => 'Study complete',
            'context' => [
                'name' => 'All',
                'subtitle' => $language['name'],
                'reset_path' => '/study/language/' . $languageId . '/all/reset',
                'restart_path' => '/languages/' . $languageId . '/start-all',
            ],
            'summary' => $summary,
        ]);
    }

    public function completeSmartSet(int $languageId, string $smartSet): void
    {
        $this->auth->requireUser();
        $language = $this->languages->find($languageId);
        $sessionKey = $this->smartSessionKey($languageId, $smartSet);
        $summary = $this->studySession->summary($sessionKey);

        if ($language === null || !$this->progress->isValidSmartSet($smartSet) || $summary === null) {
            redirect('/languages/' . $languageId);
        }

        View::render('public/complete', [
            'title' => 'Study complete',
            'context' => [
                'name' => $this->smartSetDefinitions()[$smartSet]['name'],
                'subtitle' => $language['name'],
                'reset_path' => '/study/language/' . $languageId . '/smart/' . $smartSet . '/reset',
                'restart_path' => '/languages/' . $languageId . '/smart/' . $smartSet . '/start',
            ],
            'summary' => $summary,
        ]);
    }

    public function completeSetSmartSet(int $setId, string $smartSet): void
    {
        $this->auth->requireUser();
        $set = $this->sets->find($setId);
        $sessionKey = $this->setSmartSessionKey($setId, $smartSet);
        $summary = $this->studySession->summary($sessionKey);

        if ($set === null || !$this->progress->isValidSmartSet($smartSet) || $summary === null) {
            redirect('/sets/' . $setId);
        }

        View::render('public/complete', [
            'title' => 'Study complete',
            'context' => [
                'name' => $this->smartSetDefinitions()[$smartSet]['name'],
                'subtitle' => $set['language_name'],
                'reset_path' => '/study/set/' . $setId . '/smart/' . $smartSet . '/reset',
                'restart_path' => '/sets/' . $setId . '/smart/' . $smartSet . '/start',
            ],
            'summary' => $summary,
        ]);
    }

    public function resetSmartSet(int $languageId, string $smartSet): void
    {
        $path = '/study/language/' . $languageId . '/smart/' . $smartSet;
        $sessionKey = $this->smartSessionKey($languageId, $smartSet);
        $this->studySession->clear($sessionKey);
        unset($_SESSION['last_result'][$sessionKey]);
        $this->forgetStudy($path);
        redirect('/languages/' . $languageId);
    }

    public function resetSetSmartSet(int $setId, string $smartSet): void
    {
        $path = '/study/set/' . $setId . '/smart/' . $smartSet;
        $sessionKey = $this->setSmartSessionKey($setId, $smartSet);
        $this->studySession->clear($sessionKey);
        unset($_SESSION['last_result'][$sessionKey]);
        $this->forgetStudy($path);
        redirect('/sets/' . $setId);
    }

    public function setModeForSet(int $setId): void
    {
        $this->studySession->setMode($this->setSessionKey($setId), (string) ($_POST['mode'] ?? StudySession::MODE_BILINGUAL));
        redirect('/study/set/' . $setId);
    }

    public function setModeForPracticeSet(int $setId): void
    {
        $this->studySession->setMode($this->practiceSetSessionKey($setId), (string) ($_POST['mode'] ?? StudySession::MODE_BILINGUAL));
        redirect('/practice/set/' . $setId);
    }

    public function setStudyModeForSet(int $setId): void
    {
        $this->studySession->setStudyMode($this->setSessionKey($setId), $this->sanitizeStudyMode((string) ($_POST['study_mode'] ?? StudySession::STUDY_MODE_INFINITE)));
        redirect('/study/set/' . $setId);
    }

    public function setWrongModeForSet(int $setId): void
    {
        $this->studySession->setWrongMode($this->setSessionKey($setId), (string) ($_POST['wrong_mode'] ?? StudySession::WRONG_MODE_STAY));
        redirect('/study/set/' . $setId);
    }

    public function setWrongModeForPracticeSet(int $setId): void
    {
        $this->studySession->setWrongMode($this->practiceSetSessionKey($setId), (string) ($_POST['wrong_mode'] ?? StudySession::WRONG_MODE_STAY));
        redirect('/practice/set/' . $setId);
    }

    public function setModeForLanguageAll(int $languageId): void
    {
        $this->studySession->setMode($this->allSessionKey($languageId), (string) ($_POST['mode'] ?? StudySession::MODE_BILINGUAL));
        redirect('/study/language/' . $languageId . '/all');
    }

    public function setStudyModeForLanguageAll(int $languageId): void
    {
        $this->studySession->setStudyMode($this->allSessionKey($languageId), $this->sanitizeStudyMode((string) ($_POST['study_mode'] ?? StudySession::STUDY_MODE_INFINITE)));
        redirect('/study/language/' . $languageId . '/all');
    }

    public function setWrongModeForLanguageAll(int $languageId): void
    {
        $this->studySession->setWrongMode($this->allSessionKey($languageId), (string) ($_POST['wrong_mode'] ?? StudySession::WRONG_MODE_STAY));
        redirect('/study/language/' . $languageId . '/all');
    }

    public function setModeForSmartSet(int $languageId, string $smartSet): void
    {
        $this->auth->requireUser();
        $this->studySession->lockMode($this->smartSessionKey($languageId, $smartSet), StudySession::MODE_BILINGUAL);
        redirect('/study/language/' . $languageId . '/smart/' . $smartSet);
    }

    public function setStudyModeForSmartSet(int $languageId, string $smartSet): void
    {
        $this->auth->requireUser();
        $this->studySession->setStudyMode($this->smartSessionKey($languageId, $smartSet), $this->sanitizeStudyMode((string) ($_POST['study_mode'] ?? StudySession::STUDY_MODE_INFINITE)));
        redirect('/study/language/' . $languageId . '/smart/' . $smartSet);
    }

    public function setWrongModeForSmartSet(int $languageId, string $smartSet): void
    {
        $this->auth->requireUser();
        $this->studySession->setWrongMode($this->smartSessionKey($languageId, $smartSet), (string) ($_POST['wrong_mode'] ?? StudySession::WRONG_MODE_STAY));
        redirect('/study/language/' . $languageId . '/smart/' . $smartSet);
    }

    public function setModeForSetSmartSet(int $setId, string $smartSet): void
    {
        $this->auth->requireUser();
        $this->studySession->lockMode($this->setSmartSessionKey($setId, $smartSet), StudySession::MODE_BILINGUAL);
        redirect('/study/set/' . $setId . '/smart/' . $smartSet);
    }

    public function setStudyModeForSetSmartSet(int $setId, string $smartSet): void
    {
        $this->auth->requireUser();
        $this->studySession->setStudyMode($this->setSmartSessionKey($setId, $smartSet), $this->sanitizeStudyMode((string) ($_POST['study_mode'] ?? StudySession::STUDY_MODE_INFINITE)));
        redirect('/study/set/' . $setId . '/smart/' . $smartSet);
    }

    public function setPracticeSmartSetInfluence(int $setId): void
    {
        $recordsProgress = $this->auth->checkUser() && isset($_POST['records_progress']) && $_POST['records_progress'] === '1';
        $this->studySession->setRecordsProgress($this->practiceSetSessionKey($setId), $recordsProgress);
        redirect('/practice/set/' . $setId);
    }

    public function setWrongModeForSetSmartSet(int $setId, string $smartSet): void
    {
        $this->auth->requireUser();
        $this->studySession->setWrongMode($this->setSmartSessionKey($setId, $smartSet), (string) ($_POST['wrong_mode'] ?? StudySession::WRONG_MODE_STAY));
        redirect('/study/set/' . $setId . '/smart/' . $smartSet);
    }

    private function handleAnswerResponse(string $sessionKey, string $answer, string $redirectPath, ?int $setId = null): void
    {
        $result = $this->studySession->answer($sessionKey, $answer);

        if ($result === null) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false], 404);
            }
            redirect($redirectPath);
        }

        $this->recordProgress($sessionKey, $result);
        $currentCard = $this->studySession->current($sessionKey);
        $summary = $this->studySession->summary($sessionKey);
        $summary = $summary !== null && $setId !== null
            ? $this->attachFiniteSetTiming($sessionKey, $setId, $summary)
            : $summary;

        if ($this->wantsJson()) {
            $this->json([
                'ok' => true,
                'result' => $result,
                'card' => $currentCard,
                'summary' => $summary,
                'completion_message' => $summary !== null && ($summary['complete'] ?? false) ? $this->completionMessage($summary) : null,
            ]);
        }

        $_SESSION['last_result'][$sessionKey] = $result;

        if (($summary['complete'] ?? false) === true) {
            redirect($redirectPath . '/complete');
        }

        redirect($redirectPath);
    }

    private function handleSkipResponse(string $sessionKey, string $redirectPath, ?int $setId = null): void
    {
        $result = $this->studySession->skip($sessionKey);

        if ($result === null) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false], 404);
            }
            redirect($redirectPath);
        }

        $this->recordProgress($sessionKey, $result);
        $currentCard = $this->studySession->current($sessionKey);
        $summary = $this->studySession->summary($sessionKey);
        $summary = $summary !== null && $setId !== null
            ? $this->attachFiniteSetTiming($sessionKey, $setId, $summary)
            : $summary;

        if ($this->wantsJson()) {
            $this->json([
                'ok' => true,
                'result' => $result,
                'card' => $currentCard,
                'summary' => $summary,
                'completion_message' => $summary !== null && ($summary['complete'] ?? false) ? $this->completionMessage($summary) : null,
            ]);
        }

        $_SESSION['last_result'][$sessionKey] = $result;

        if (($summary['complete'] ?? false) === true) {
            redirect($redirectPath . '/complete');
        }

        redirect($redirectPath);
    }

    private function recordProgress(string $sessionKey, array $result): void
    {
        $userId = $this->auth->userId();

        if ($userId === null || !isset($result['card_id'])) {
            return;
        }

        if (!$this->studySession->recordsProgress($sessionKey)) {
            return;
        }

        $this->progress->recordAnswer(
            $userId,
            (int) $result['card_id'],
            (string) ($result['translation_direction'] ?? StudySession::MODE_BILINGUAL),
            (bool) ($result['correct'] ?? false),
            (bool) ($result['skipped'] ?? false)
        );
    }

    private function rememberStudy(string $path): void
    {
        if (!$this->auth->checkUser()) {
            return;
        }

        $_SESSION['last_study_path'] = $path;
    }

    private function forgetStudy(string $path): void
    {
        if (($_SESSION['last_study_path'] ?? null) === $path) {
            unset($_SESSION['last_study_path']);
        }
    }

    private function resetGuestStudyState(): void
    {
        unset($_SESSION['study_sessions'], $_SESSION['last_result'], $_SESSION['last_study_path']);
    }

    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

        return str_contains($accept, 'application/json') || strtolower($requestedWith) === 'xmlhttprequest';
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR);
        exit;
    }

    private function setSessionKey(int $setId): string
    {
        return 'set-' . $setId;
    }

    private function practiceSetSessionKey(int $setId): string
    {
        return 'practice-set-' . $setId;
    }

    private function allSessionKey(int $languageId): string
    {
        return 'language-all-' . $languageId;
    }

    private function smartSessionKey(int $languageId, string $smartSet): string
    {
        return 'language-smart-' . $languageId . '-' . $smartSet;
    }

    private function setSmartSessionKey(int $setId, string $smartSet): string
    {
        return 'set-smart-' . $setId . '-' . $smartSet;
    }

    private function completionMessage(array $summary): string
    {
        $cardTotal = max(1, (int) ($summary['card_total'] ?? 0));
        $score = (int) ($summary['score'] ?? 0);
        $ratio = $score / $cardTotal;

        return match (true) {
            $ratio >= 0.95 => 'Outstanding run. You barely missed anything.',
            $ratio >= 0.8 => 'Strong result. You are holding this set well.',
            $ratio >= 0.6 => 'Solid progress. A few words still need another pass.',
            $ratio >= 0.35 => 'Useful session. This set needs another round soon.',
            default => 'Tough round. Run it again while the misses are still fresh.',
        };
    }

    private function attachFiniteSetTiming(string $sessionKey, int $setId, array $summary): array
    {
        $userId = $this->auth->userId();
        $summary['best_time_seconds'] = null;
        $summary['is_new_best_time'] = false;

        if ($userId === null) {
            return $summary;
        }

        if (($summary['study_mode'] ?? StudySession::STUDY_MODE_INFINITE) !== StudySession::STUDY_MODE_FINITE) {
            return $summary;
        }

        $existingStat = $this->progress->finiteSetStat($userId, $setId);
        $previousBest = $existingStat !== null ? (int) $existingStat['best_time_seconds'] : null;

        if (($summary['complete'] ?? false) === true && !$this->studySession->completionRecorded($sessionKey)) {
            $elapsedSeconds = (int) ($summary['elapsed_seconds'] ?? 0);
            $existingStat = $this->progress->recordFiniteSetCompletion($userId, $setId, $elapsedSeconds);
            $summary['is_new_best_time'] = $previousBest === null || $elapsedSeconds < $previousBest;
            $this->studySession->markCompletionRecorded($sessionKey);
        }

        $summary['best_time_seconds'] = $existingStat !== null
            ? (int) ($existingStat['best_time_seconds'] ?? 0)
            : $previousBest;

        return $summary;
    }

    private function sanitizeStudyMode(string $studyMode): string
    {
        return $studyMode === StudySession::STUDY_MODE_FINITE
            ? StudySession::STUDY_MODE_FINITE
            : StudySession::STUDY_MODE_INFINITE;
    }

    private function smartSetDefinitions(): array
    {
        return [
            UserProgressRepository::SMART_SET_NEW => [
                'name' => 'New',
                'description' => 'Words without enough attempts to grade yet.',
            ],
            UserProgressRepository::SMART_SET_DIFFICULT => [
                'name' => 'Difficult',
                'description' => 'Words you get wrong most of the time.',
            ],
            UserProgressRepository::SMART_SET_MEDIUM => [
                'name' => 'Medium',
                'description' => 'Words you sometimes know and sometimes miss.',
            ],
            UserProgressRepository::SMART_SET_EASY => [
                'name' => 'Easy',
                'description' => 'Words you usually get right.',
            ],
            UserProgressRepository::SMART_SET_INCORRECT_RECENTLY => [
                'name' => 'Incorrect recently',
                'description' => 'Words you have missed or skipped in your recent attempts.',
            ],
            UserProgressRepository::SMART_SET_MASTERED => [
                'name' => 'Mastered',
                'description' => 'Words you are consistently getting right.',
            ],
            UserProgressRepository::SMART_SET_SKIPPED_RECENTLY => [
                'name' => 'Skipped recently',
                'description' => 'Words you have chosen to skip in recent attempts.',
            ],
            UserProgressRepository::SMART_SET_NEEDS_REVIEW => [
                'name' => 'Needs review',
                'description' => 'Words you have not seen for at least a week.',
            ],
            UserProgressRepository::SMART_SET_IMPROVING => [
                'name' => 'Improving',
                'description' => 'Words where your recent answers are stronger than your overall history.',
            ],
            UserProgressRepository::SMART_SET_UNSTABLE => [
                'name' => 'Unstable',
                'description' => 'Words that swing between correct and incorrect recent answers.',
            ],
        ];
    }
}
