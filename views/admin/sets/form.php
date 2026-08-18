<?php use App\Security\Csrf; ?>
<section class="mx-auto max-w-3xl">
    <div class="panel px-5 py-6 sm:px-8 sm:py-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Admin sets</p>
                <h1 class="mt-3 text-3xl font-semibold text-slate-950"><?= $set === null ? 'Create set' : 'Edit set' ?></h1>
            </div>
            <div class="grid gap-3 sm:flex sm:items-center">
                <?php if ($set !== null): ?>
                    <a class="btn-secondary w-full justify-center sm:w-auto" href="/admin/sets/<?= e((string) $set['id']) ?>/export">Export set</a>
                <?php endif; ?>
                <a class="inline-flex w-fit items-center gap-2 rounded-2xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-100 shadow-sm" href="/admin">
                    <span aria-hidden="true">←</span>
                    <span>Back</span>
                </a>
            </div>
        </div>

        <form method="post" action="<?= e($action) ?>" class="mt-8 space-y-5">
            <?= Csrf::input() ?>
            <div class="field">
                <label class="label" for="language_id">Language</label>
                <select id="language_id" name="language_id" required>
                    <option value="">Select a language</option>
                    <?php $selectedLanguageId = (string) ($set['language_id'] ?? old('language_id')); ?>
                    <?php foreach ($languages as $language): ?>
                        <option value="<?= e((string) $language['id']) ?>" <?= $selectedLanguageId === (string) $language['id'] ? 'selected' : '' ?>>
                            <?= e($language['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="label" for="name">Set name</label>
                <input id="name" name="name" type="text" value="<?= e($set['name'] ?? old('name')) ?>" required>
            </div>

            <div class="field">
                <label class="label" for="description">Description</label>
                <textarea id="description" name="description"><?= e($set['description'] ?? old('description')) ?></textarea>
            </div>

            <?php
            $published = $set !== null
                ? (int) $set['published'] === 1
                : old('published') === '1';
            ?>
            <label class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                <input class="h-4 w-4 rounded border-slate-300 text-accent focus:ring-accent" type="checkbox" name="published" <?= $published ? 'checked' : '' ?>>
                Visible on the public homepage
            </label>

            <button class="btn-primary w-full justify-center sm:w-auto" type="submit"><?= $set === null ? 'Create set' : 'Save changes' ?></button>
        </form>
    </div>
</section>
