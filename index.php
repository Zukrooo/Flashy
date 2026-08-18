<?php

declare(strict_types=1);

use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CardController;
use App\Controllers\Admin\LanguageController;
use App\Controllers\Admin\SetController;
use App\Controllers\PublicAuthController;
use App\Controllers\PublicController;
use App\Controllers\SetupController;
use App\Core\Config;
use App\Core\Migrator;
use App\Core\View;
use App\Repositories\CardRepository;
use App\Repositories\LanguageRepository;
use App\Repositories\SetRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserProgressRepository;
use App\Security\Csrf;
use App\Support\Auth;
use App\Support\StudySession;

require_once __DIR__ . '/app/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow, noarchive', true);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$basePath = __DIR__;
$setupController = new SetupController($basePath);
$isConfigured = Config::isConfigured($basePath);

if (!$isConfigured) {
    if ($method === 'GET' && $path === '/install') {
        $setupController->show();
        return;
    }

    if ($method === 'POST' && $path === '/install') {
        Csrf::verify($_POST['_token'] ?? null);
        $setupController->install();
        return;
    }

    redirect('/install');
}

if ($path === '/install') {
    redirect('/');
}

try {
    if ($database instanceof \App\Core\Database && $database->config()->isSqlite()) {
        Migrator::migrate($database);
    }

    $pdo = $database?->connection();

    if (!$pdo instanceof \PDO) {
        throw new \RuntimeException('Database connection is not available.');
    }

    $languageRepository = new LanguageRepository($pdo);
    $setRepository = new SetRepository($pdo);
    $cardRepository = new CardRepository($pdo);
    $userRepository = new UserRepository($pdo);
    $progressRepository = new UserProgressRepository($pdo);
    $auth = new Auth($userRepository);
    $studySession = new StudySession();

    $publicController = new PublicController($languageRepository, $setRepository, $cardRepository, $progressRepository, $studySession, $auth);
    $publicAuthController = new PublicAuthController($auth, $userRepository, $progressRepository);
    $authController = new AuthController($auth);
    $languageController = new LanguageController($languageRepository);
    $setController = new SetController($languageRepository, $setRepository, $cardRepository, $database, $userRepository);
    $cardController = new CardController($cardRepository, $setRepository);

    $dispatch = static function (callable $handler, bool $requiresAdmin = false, bool $requiresCsrf = false) use ($auth): void {
        if ($requiresAdmin) {
            $auth->requireAdmin();
        }

        if ($requiresCsrf) {
            Csrf::verify($_POST['_token'] ?? null);
        }

        $handler();
    };

    if ($method === 'GET' && $path === '/') {
        $dispatch(static fn () => $publicController->home());
        return;
    }

    if ($method === 'GET' && $path === '/practice') {
        $dispatch(static fn () => $publicController->practiceHome());
        return;
    }

    if ($method === 'GET' && $path === '/login') {
        $dispatch(static fn () => $publicAuthController->showLogin());
        return;
    }

    if ($method === 'POST' && $path === '/login') {
        $dispatch(static fn () => $publicAuthController->login(), false, true);
        return;
    }

    if ($method === 'GET' && $path === '/register') {
        $dispatch(static fn () => $publicAuthController->showRegister());
        return;
    }

    if ($method === 'POST' && $path === '/register') {
        $dispatch(static fn () => $publicAuthController->register(), false, true);
        return;
    }

    if ($method === 'POST' && $path === '/logout') {
        $dispatch(static fn () => $publicAuthController->logout(), false, true);
        return;
    }

    if ($method === 'GET' && $path === '/profile') {
        $dispatch(static fn () => $publicAuthController->showProfile());
        return;
    }

    if ($method === 'POST' && $path === '/profile') {
        $dispatch(static fn () => $publicAuthController->updateProfile(), false, true);
        return;
    }

    if ($method === 'POST' && $path === '/profile/clear-data') {
        $dispatch(static fn () => $publicAuthController->clearData(), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/sets/(\d+)/start$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->startSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'GET' && preg_match('#^/sets/(\d+)$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->set((int) $matches[1]));
        return;
    }

    if ($method === 'GET' && preg_match('#^/practice/sets/(\d+)$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->practiceSetLanding((int) $matches[1]));
        return;
    }

    if ($method === 'POST' && preg_match('#^/sets/(\d+)/smart/([a-z-]+)/start$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->startSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/sets/(\d+)/smart/([a-z-]+)/start$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->startPracticeSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/sets/(\d+)/start$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->startPracticeSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'GET' && preg_match('#^/languages/(\d+)$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->language((int) $matches[1]));
        return;
    }

    if ($method === 'GET' && preg_match('#^/practice/languages/(\d+)$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->practiceLanguage((int) $matches[1]));
        return;
    }

    if ($method === 'POST' && preg_match('#^/languages/(\d+)/start-all$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->startLanguageAll((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/languages/(\d+)/smart/([a-z-]+)/start$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->startSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/languages/(\d+)/smart/([a-z-]+)/start$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->startPracticeSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'GET' && preg_match('#^/study/set/(\d+)$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->studySet((int) $matches[1]));
        return;
    }

    if ($method === 'GET' && preg_match('#^/practice/set/(\d+)$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->practiceSet((int) $matches[1]));
        return;
    }

    if ($method === 'GET' && preg_match('#^/study/set/(\d+)/smart/([a-z-]+)$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->studySetSmartSet((int) $matches[1], $matches[2]));
        return;
    }

    if ($method === 'GET' && preg_match('#^/practice/set/(\d+)/smart/([a-z-]+)$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->practiceSetSmartSet((int) $matches[1], $matches[2]));
        return;
    }

    if ($method === 'GET' && preg_match('#^/study/language/(\d+)/all$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->studyLanguageAll((int) $matches[1]));
        return;
    }

    if ($method === 'GET' && preg_match('#^/study/language/(\d+)/smart/([a-z-]+)$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->studySmartSet((int) $matches[1], $matches[2]));
        return;
    }

    if ($method === 'GET' && preg_match('#^/practice/language/(\d+)/smart/([a-z-]+)$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->practiceSmartSet((int) $matches[1], $matches[2]));
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/answer$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->answerSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/set/(\d+)/answer$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->answerPracticeSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/smart/([a-z-]+)/answer$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->answerSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/set/(\d+)/smart/([a-z-]+)/answer$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->answerPracticeSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/skip$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->skipSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/set/(\d+)/skip$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->skipPracticeSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/smart/([a-z-]+)/skip$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->skipSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/set/(\d+)/smart/([a-z-]+)/skip$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->skipPracticeSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setModeForSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/set/(\d+)/mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setModeForPracticeSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/study-mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setStudyModeForSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/wrong-mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setWrongModeForSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/set/(\d+)/wrong-mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setWrongModeForPracticeSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/smart/([a-z-]+)/mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setModeForSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/set/(\d+)/smart/([a-z-]+)/mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setModeForPracticeSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/smart/([a-z-]+)/study-mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setStudyModeForSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/smart/([a-z-]+)/wrong-mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setWrongModeForSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/all/answer$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->answerLanguageAll((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/smart/([a-z-]+)/answer$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->answerSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/language/(\d+)/smart/([a-z-]+)/answer$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->answerPracticeSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/all/skip$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->skipLanguageAll((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/smart/([a-z-]+)/skip$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->skipSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/language/(\d+)/smart/([a-z-]+)/skip$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->skipPracticeSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/all/mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setModeForLanguageAll((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/all/study-mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setStudyModeForLanguageAll((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/all/wrong-mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setWrongModeForLanguageAll((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/smart/([a-z-]+)/mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setModeForSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/language/(\d+)/smart/([a-z-]+)/mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setModeForPracticeSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/smart/([a-z-]+)/study-mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setStudyModeForSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/smart/([a-z-]+)/wrong-mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setWrongModeForSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/language/(\d+)/smart/([a-z-]+)/wrong-mode$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->setWrongModeForPracticeSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'GET' && preg_match('#^/study/set/(\d+)/complete$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->completeSet((int) $matches[1]));
        return;
    }

    if ($method === 'GET' && preg_match('#^/study/language/(\d+)/all/complete$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->completeLanguageAll((int) $matches[1]));
        return;
    }

    if ($method === 'GET' && preg_match('#^/study/language/(\d+)/smart/([a-z-]+)/complete$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->completeSmartSet((int) $matches[1], $matches[2]));
        return;
    }

    if ($method === 'GET' && preg_match('#^/study/set/(\d+)/smart/([a-z-]+)/complete$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->completeSetSmartSet((int) $matches[1], $matches[2]));
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/reset$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->resetSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/set/(\d+)/reset$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->resetPracticeSet((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/set/(\d+)/smart/([a-z-]+)/reset$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->resetSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/set/(\d+)/smart/([a-z-]+)/reset$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->resetPracticeSetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/all/reset$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->resetLanguageAll((int) $matches[1]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/study/language/(\d+)/smart/([a-z-]+)/reset$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->resetSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/practice/language/(\d+)/smart/([a-z-]+)/reset$#', $path, $matches) === 1) {
        $dispatch(static fn () => $publicController->resetPracticeSmartSet((int) $matches[1], $matches[2]), false, true);
        return;
    }

    if ($method === 'GET' && $path === '/admin/login') {
        $dispatch(static fn () => $authController->showLogin());
        return;
    }

    if ($method === 'POST' && $path === '/admin/login') {
        $dispatch(static fn () => $authController->login(), false, true);
        return;
    }

    if ($method === 'POST' && $path === '/admin/logout') {
        $dispatch(static fn () => $authController->logout(), true, true);
        return;
    }

    if ($method === 'GET' && $path === '/admin') {
        $dispatch(static fn () => $setController->dashboard(), true);
        return;
    }

    if ($method === 'POST' && $path === '/admin/tools/migrate') {
        $dispatch(static fn () => $setController->migrateDatabase(), true, true);
        return;
    }

    if ($method === 'POST' && $path === '/admin/tools/import-sql') {
        $dispatch(static fn () => $setController->importSql(), true, true);
        return;
    }

    if ($method === 'POST' && $path === '/admin/tools/create-admin') {
        $dispatch(static fn () => $setController->createAdminUser(), true, true);
        return;
    }

    if ($method === 'GET' && $path === '/admin/languages/new') {
        $dispatch(static fn () => $languageController->createForm(), true);
        return;
    }

    if ($method === 'POST' && $path === '/admin/languages') {
        $dispatch(static fn () => $languageController->create(), true, true);
        return;
    }

    if ($method === 'GET' && preg_match('#^/admin/languages/(\d+)/edit$#', $path, $matches) === 1) {
        $dispatch(static fn () => $languageController->editForm((int) $matches[1]), true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/admin/languages/(\d+)/edit$#', $path, $matches) === 1) {
        $dispatch(static fn () => $languageController->update((int) $matches[1]), true, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/admin/languages/(\d+)/delete$#', $path, $matches) === 1) {
        $dispatch(static fn () => $languageController->delete((int) $matches[1]), true, true);
        return;
    }

    if ($method === 'GET' && $path === '/admin/sets/new') {
        $dispatch(static fn () => $setController->createForm(), true);
        return;
    }

    if ($method === 'POST' && $path === '/admin/sets') {
        $dispatch(static fn () => $setController->create(), true, true);
        return;
    }

    if ($method === 'GET' && preg_match('#^/admin/sets/(\d+)/edit$#', $path, $matches) === 1) {
        $dispatch(static fn () => $setController->editForm((int) $matches[1]), true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/admin/sets/(\d+)/edit$#', $path, $matches) === 1) {
        $dispatch(static fn () => $setController->update((int) $matches[1]), true, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/admin/sets/(\d+)/delete$#', $path, $matches) === 1) {
        $dispatch(static fn () => $setController->delete((int) $matches[1]), true, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/admin/sets/(\d+)/(publish|hide)$#', $path, $matches) === 1) {
        $dispatch(
            static fn () => $setController->setPublished((int) $matches[1], $matches[2] === 'publish'),
            true,
            true
        );
        return;
    }

    if ($method === 'GET' && preg_match('#^/admin/sets/(\d+)/cards$#', $path, $matches) === 1) {
        $dispatch(static fn () => $setController->cards((int) $matches[1]), true);
        return;
    }

    if ($method === 'GET' && preg_match('#^/admin/sets/(\d+)/export$#', $path, $matches) === 1) {
        $dispatch(static fn () => $cardController->export((int) $matches[1]), true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/admin/sets/(\d+)/cards$#', $path, $matches) === 1) {
        $dispatch(static fn () => $cardController->create((int) $matches[1]), true, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/admin/sets/(\d+)/import$#', $path, $matches) === 1) {
        $dispatch(static fn () => $cardController->import((int) $matches[1]), true, true);
        return;
    }

    if ($method === 'GET' && preg_match('#^/admin/cards/(\d+)/edit$#', $path, $matches) === 1) {
        $dispatch(static fn () => $cardController->editForm((int) $matches[1]), true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/admin/cards/(\d+)/edit$#', $path, $matches) === 1) {
        $dispatch(static fn () => $cardController->update((int) $matches[1]), true, true);
        return;
    }

    if ($method === 'POST' && preg_match('#^/admin/cards/(\d+)/delete$#', $path, $matches) === 1) {
        $dispatch(static fn () => $cardController->delete((int) $matches[1]), true, true);
        return;
    }

    http_response_code(404);
    View::render('errors/not-found', ['title' => 'Page not found']);
} catch (Throwable $exception) {
    http_response_code(500);
    View::render('errors/error', [
        'title' => 'Application error',
        'message' => $exception->getMessage(),
    ]);
}
