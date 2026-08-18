<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\View;
use App\Repositories\CardRepository;
use App\Repositories\SetRepository;
use App\Support\Flash;
use RuntimeException;

final class CardController
{
    public function __construct(
        private readonly CardRepository $cards,
        private readonly SetRepository $sets
    ) {
    }

    public function create(int $setId): void
    {
        $set = $this->sets->find($setId);

        if ($set === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        $gaidhlig = trim((string) ($_POST['gaidhlig'] ?? ''));
        $english = (string) ($_POST['english'] ?? '');
        $languageAliases = trim((string) ($_POST['language_aliases'] ?? ''));
        $englishAliases = trim((string) ($_POST['english_aliases'] ?? ''));

        if ($gaidhlig === '' || $english === '') {
            Flash::put('error', 'Both the language word and the English word are required.');
            redirect('/admin/sets/' . $setId . '/cards');
        }

        $this->cards->create($setId, $gaidhlig, $english, $languageAliases, $englishAliases);
        Flash::put('success', 'Card added.');
        redirect('/admin/sets/' . $setId . '/cards');
    }

    public function editForm(int $cardId): void
    {
        $card = $this->cards->find($cardId);

        if ($card === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Card not found']);
            return;
        }

        $set = $this->sets->find((int) $card['set_id']);

        if ($set === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        View::render('admin/cards/form', [
            'title' => 'Edit card',
            'set' => $set,
            'card' => $card,
        ]);
    }

    public function update(int $cardId): void
    {
        $card = $this->cards->find($cardId);

        if ($card === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Card not found']);
            return;
        }

        $gaidhlig = trim((string) ($_POST['gaidhlig'] ?? ''));
        $english = (string) ($_POST['english'] ?? '');
        $languageAliases = trim((string) ($_POST['language_aliases'] ?? ''));
        $englishAliases = trim((string) ($_POST['english_aliases'] ?? ''));

        if ($gaidhlig === '' || $english === '') {
            Flash::put('error', 'Both the language word and the English word are required.');
            redirect('/admin/cards/' . $cardId . '/edit');
        }

        $this->cards->update($cardId, $gaidhlig, $english, $languageAliases, $englishAliases);
        Flash::put('success', 'Card updated.');
        redirect('/admin/sets/' . $card['set_id'] . '/cards');
    }

    public function delete(int $cardId): void
    {
        $card = $this->cards->find($cardId);

        if ($card === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Card not found']);
            return;
        }

        $setId = (int) $card['set_id'];
        $this->cards->delete($cardId);
        Flash::put('success', 'Card deleted.');
        redirect('/admin/sets/' . $setId . '/cards');
    }

    public function import(int $setId): void
    {
        $set = $this->sets->find($setId);

        if ($set === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        $upload = $_FILES['csv_file'] ?? null;

        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Flash::put('error', 'Choose a CSV file to import.');
            redirect('/admin/sets/' . $setId . '/cards');
        }

        $tmpPath = (string) ($upload['tmp_name'] ?? '');

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            Flash::put('error', 'The uploaded CSV could not be read.');
            redirect('/admin/sets/' . $setId . '/cards');
        }

        $imported = 0;

        try {
            $handle = fopen($tmpPath, 'rb');

            if ($handle === false) {
                throw new RuntimeException('The uploaded CSV could not be opened.');
            }

            $rowNumber = 0;

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;
                $row = $this->normalizeCsvRow($row, $rowNumber === 1);

                if ($rowNumber === 1 && $this->looksLikeHeader($row)) {
                    continue;
                }

                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $languageWord = trim((string) ($row[0] ?? ''));
                $englishWord = (string) ($row[1] ?? '');
                $languageAliases = trim((string) ($row[2] ?? ''));
                $englishAliases = trim((string) ($row[3] ?? ''));

                if ($languageWord === '' || $englishWord === '') {
                    continue;
                }

                $this->cards->create($setId, $languageWord, $englishWord, $languageAliases, $englishAliases);
                $imported++;
            }

            fclose($handle);
        } finally {
            if ($tmpPath !== '' && is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }

        Flash::put('success', "Imported {$imported} cards from CSV.");
        redirect('/admin/sets/' . $setId . '/cards');
    }

    public function export(int $setId): void
    {
        $set = $this->sets->find($setId);

        if ($set === null) {
            http_response_code(404);
            View::render('errors/not-found', ['title' => 'Set not found']);
            return;
        }

        $cards = $this->cards->forSet($setId);
        $filename = $this->csvFilename((string) $set['name']);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $handle = fopen('php://output', 'wb');

        if ($handle === false) {
            throw new RuntimeException('The export file could not be created.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['language_word', 'english', 'language_aliases', 'english_aliases']);

        foreach ($cards as $card) {
            fputcsv($handle, [
                (string) ($card['gaidhlig'] ?? ''),
                (string) ($card['english'] ?? ''),
                $this->normalizeAliasesForCsv((string) ($card['language_aliases'] ?? '')),
                $this->normalizeAliasesForCsv((string) ($card['english_aliases'] ?? '')),
            ]);
        }

        fclose($handle);
    }

    private function looksLikeHeader(array $row): bool
    {
        $normalized = array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            array_slice($row, 0, 4)
        );

        return ($normalized[0] ?? '') === 'language_word'
            && ($normalized[1] ?? '') === 'english';
    }

    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeAliasesForCsv(string $aliases): string
    {
        $values = preg_split('/\r\n|\r|\n|\|/', $aliases) ?: [];
        $values = array_filter(array_map(static fn (string $value): string => trim($value), $values));

        return implode('|', $values);
    }

    private function normalizeCsvRow(array $row, bool $stripBom = false): array
    {
        return array_map(
            fn ($value, $index): string => $this->normalizeCsvValue((string) $value, $stripBom && (int) $index === 0),
            $row,
            array_keys($row)
        );
    }

    private function normalizeCsvValue(string $value, bool $stripBom = false): string
    {
        if ($stripBom && str_starts_with($value, "\xEF\xBB\xBF")) {
            $value = substr($value, 3);
        }

        if ($value === '') {
            return '';
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $encoding = mb_detect_encoding($value, ['Windows-1252', 'ISO-8859-1', 'UTF-8'], true);

        if ($encoding !== false) {
            $converted = mb_convert_encoding($value, 'UTF-8', $encoding);

            if (is_string($converted)) {
                return $converted;
            }
        }

        $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);

        return is_string($converted) ? $converted : $value;
    }

    private function csvFilename(string $setName): string
    {
        $slug = strtolower(trim($setName));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? 'set';
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'set';
        }

        return $slug . '-export.csv';
    }
}
