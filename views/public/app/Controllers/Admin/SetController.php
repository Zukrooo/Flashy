<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Core\Database;
use App\Core\Migrator;
use App\Repositories\CardRepository;
use App\Repositories\LanguageRepository;
use App\Repositories\SetRepository;
use App\Repositories\UserRepository;
use App\Support\Flash;
use RuntimeException;

final class SetController
{
    private const DASHBOARD_PAGE_SETS = '/admin?page=sets';
    private const DASHBOARD_PAGE_LANGUAGES = '/admin?page=languages';
    private const DASHBOARD_PAGE_TOOLS = '/admin?page=tools';

    public function __construct(
        private readonly LanguageRepository $languages,
        private readonly SetRepository $sets,
        private readonly CardRepository $cards,
        private readonly Database $database,
        private readonly UserRepository $users
    ) {
    }

    public function dashboard(): void
    {
        $languages = $this->languages->allWithCounts();
        $sets = $this->sets->allWithCounts();

        View::render('admin/dashboard', [
            'title' => 'Admin dashboard',
            'active_page' => $this->dashboardPage(),
            'languages' => $languages,
            'sets' => $sets,
            'database_driver' => $this->database->config()->databaseDriver(),
            'database_config' => $this->databaseConfigSummary(),
        ]);
    }

    public function createForm(): void
    {
        $languages = $this->languages->allWithCounts();

        if ($languages === []) {
            Flash::put('error', 'Create a language before creating a set.');
            redirect('/admin/languages/new');
        }

        View::render('admin/sets/form', [
            'title' => 'Create set',
            'set' => null,
            'action' => '/admin/sets',
            'languages' => $languages,
        ]);
    }

    public function create(): void
    {
        $languageId = (int) ($_POST['language_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $published = isset($_POST['published']);

        stash_old_input([
            'language_id' => (string) $languageId,
            'name' => $name,
            'description' => $description,
            'published' => $published ? '1' : '',
        ]);

        if ($this->languages->find($languageId) === null) {
            Flash::put('error', 'Choose a language for the set.');
            redirect('/admin/sets/new');
        }

        if ($name === '') {
            Flash::put('error', 'Set name is required.');
            redirect('/admin/sets/new');
        }

        $setId = $this->sets->create($languageId, $name, $description, $published);
        clear_old_input();
        Flash::put('success', 'Set created.');
        redirect('/admin/sets/' . $setId . '/cards');
    }

    public function editForm(int $setId): void
    {
        $set = $this->sets->find($setId);

        if ($set === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        View::render('admin/sets/form', [
            'title' => 'Edit set',
            'set' => $set,
            'action' => '/admin/sets/' . $setId . '/edit',
            'languages' => $this->languages->allWithCounts(),
        ]);
    }

    public function update(int $setId): void
    {
        $set = $this->sets->find($setId);

        if ($set === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        $languageId = (int) ($_POST['language_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $published = isset($_POST['published']);

        if ($this->languages->find($languageId) === null) {
            Flash::put('error', 'Choose a language for the set.');
            redirect('/admin/sets/' . $setId . '/edit');
        }

        if ($name === '') {
            Flash::put('error', 'Set name is required.');
            redirect('/admin/sets/' . $setId . '/edit');
        }

        $this->sets->update($setId, $languageId, $name, $description, $published);
        Flash::put('success', 'Set updated.');
        redirect(self::DASHBOARD_PAGE_SETS);
    }

    public function delete(int $setId): void
    {
        $this->sets->delete($setId);
        Flash::put('success', 'Set deleted.');
        redirect(self::DASHBOARD_PAGE_SETS);
    }

    public function setPublished(int $setId, bool $published): void
    {
        $set = $this->sets->find($setId);

        if ($set === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        $this->sets->setPublished($setId, $published);
        Flash::put('success', $published ? 'Set published.' : 'Set hidden.');
        redirect(self::DASHBOARD_PAGE_SETS);
    }

    public function cards(int $setId): void
    {
        $set = $this->sets->find($setId);

        if ($set === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        View::render('admin/cards/index', [
            'title' => 'Manage cards',
            'set' => $set,
            'cards' => $this->cards->forSet($setId),
        ]);
    }

    public function migrateDatabase(): void
    {
        $result = Migrator::migrate($this->database);
        Flash::put('success', sprintf(
            'Database migration completed for %s on %s:%d.',
            $result['database'],
            $result['host'],
            $result['port']
        ));
        redirect(self::DASHBOARD_PAGE_TOOLS);
    }

    public function importSql(): void
    {
        $upload = $_FILES['sql_file'] ?? null;

        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Flash::put('error', 'Choose a SQL file to import.');
            redirect(self::DASHBOARD_PAGE_TOOLS);
        }

        $tmpPath = (string) ($upload['tmp_name'] ?? '');
        $originalName = strtolower(trim((string) ($upload['name'] ?? '')));

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            Flash::put('error', 'The uploaded SQL file could not be read.');
            redirect(self::DASHBOARD_PAGE_TOOLS);
        }

        if ($originalName !== '' && !str_ends_with($originalName, '.sql')) {
            Flash::put('error', 'Upload a .sql file.');
            redirect(self::DASHBOARD_PAGE_TOOLS);
        }

        $sql = file_get_contents($tmpPath);

        if ($sql === false || trim($sql) === '') {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }

            Flash::put('error', 'The uploaded SQL file was empty.');
            redirect(self::DASHBOARD_PAGE_TOOLS);
        }

        try {
            $pdo = $this->database->connection();
            $pdo->exec((string) $sql);
        } catch (\Throwable $throwable) {
            throw new RuntimeException('SQL import failed: ' . $throwable->getMessage(), 0, $throwable);
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }

        Flash::put('success', 'SQL import completed.');
        redirect(self::DASHBOARD_PAGE_TOOLS);
    }

    public function createAdminUser(): void
    {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '' || $password === '' || $passwordConfirm === '') {
            Flash::put('error', 'First name, last name, email, and password fields are required.');
            redirect(self::DASHBOARD_PAGE_TOOLS);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Flash::put('error', 'Enter a valid email address.');
            redirect(self::DASHBOARD_PAGE_TOOLS);
        }

        if (mb_strlen($password, 'UTF-8') < 8) {
            Flash::put('error', 'Password must be at least 8 characters.');
            redirect(self::DASHBOARD_PAGE_TOOLS);
        }

        if ($password !== $passwordConfirm) {
            Flash::put('error', 'Passwords do not match.');
            redirect(self::DASHBOARD_PAGE_TOOLS);
        }

        if ($this->users->findByEmail($email) !== null) {
            Flash::put('error', 'That email is already in use.');
            redirect(self::DASHBOARD_PAGE_TOOLS);
        }

        $this->users->create(
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            true,
            $firstName,
            $lastName
        );

        Flash::put('success', 'Admin user created.');
        redirect(self::DASHBOARD_PAGE_TOOLS);
    }

    private function dashboardPage(): string
    {
        $page = strtolower(trim((string) ($_GET['page'] ?? 'sets')));

        return match ($page) {
            'languages' => 'languages',
            'tools' => 'tools',
            default => 'sets',
        };
    }

    private function databaseConfigSummary(): array
    {
        $config = $this->database->config();

        return [
            'driver' => 'mysql',
            'label' => 'MySQL',
            'details' => [
                'Host' => $config->databaseHost(),
                'Port' => (string) $config->databasePort(),
                'Database' => $config->databaseName(),
                'User' => $config->databaseUser(),
                'Charset' => $config->databaseCharset(),
            ],
        ];
    }
}
