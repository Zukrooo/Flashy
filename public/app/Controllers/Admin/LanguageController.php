<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Repositories\LanguageRepository;
use App\Support\Flash;

final class LanguageController
{
    private const DASHBOARD_PAGE = '/admin?page=languages';

    public function __construct(private readonly LanguageRepository $languages)
    {
    }

    public function createForm(): void
    {
        View::render('admin/languages/form', [
            'title' => 'Create language',
            'language' => null,
            'action' => '/admin/languages',
        ]);
    }

    public function create(): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        stash_old_input([
            'name' => $name,
            'description' => $description,
        ]);

        if ($name === '') {
            Flash::put('error', 'Language name is required.');
            redirect('/admin/languages/new');
        }

        $this->languages->create($name, $description);
        clear_old_input();
        Flash::put('success', 'Language created.');
        redirect(self::DASHBOARD_PAGE);
    }

    public function editForm(int $languageId): void
    {
        $language = $this->languages->find($languageId);

        if ($language === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Language not found']);
            return;
        }

        View::render('admin/languages/form', [
            'title' => 'Edit language',
            'language' => $language,
            'action' => '/admin/languages/' . $languageId . '/edit',
        ]);
    }

    public function update(int $languageId): void
    {
        $language = $this->languages->find($languageId);

        if ($language === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Language not found']);
            return;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($name === '') {
            Flash::put('error', 'Language name is required.');
            redirect('/admin/languages/' . $languageId . '/edit');
        }

        $this->languages->update($languageId, $name, $description);
        Flash::put('success', 'Language updated.');
        redirect(self::DASHBOARD_PAGE);
    }

    public function delete(int $languageId): void
    {
        $this->languages->delete($languageId);
        Flash::put('success', 'Language deleted.');
        redirect(self::DASHBOARD_PAGE);
    }
}
