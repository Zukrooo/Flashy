<?php use App\Security\Csrf; ?>
<section class="mx-auto max-w-3xl">
    <div class="panel px-6 py-8 sm:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Edit card</p>
                <h1 class="mt-3 text-3xl font-semibold text-slate-950"><?= e($set['name']) ?></h1>
            </div>
            <a class="mb-4 inline-flex w-fit items-center gap-2 rounded-2xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-100 shadow-sm" href="/admin/sets/<?= e((string) $set['id']) ?>/cards">
                <span aria-hidden="true">←</span>
                <span>Back</span>
            </a>
        </div>

        <form method="post" action="/admin/cards/<?= e((string) $card['id']) ?>/edit" class="mt-8 space-y-5">
            <?= Csrf::input() ?>
            <div class="field">
                <label class="label" for="gaidhlig">Language word</label>
                <input id="gaidhlig" name="gaidhlig" type="text" value="<?= e($card['gaidhlig']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="english">English</label>
                <input id="english" name="english" type="text" value="<?= e($card['english']) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="language_aliases">Language aliases</label>
                <textarea id="language_aliases" name="language_aliases" placeholder="One exact alias per line"><?= e(str_replace('|', "\n", $card['language_aliases'] ?? '')) ?></textarea>
            </div>
            <div class="field">
                <label class="label" for="english_aliases">English aliases</label>
                <textarea id="english_aliases" name="english_aliases" placeholder="One exact alias per line"><?= e(str_replace('|', "\n", $card['english_aliases'] ?? '')) ?></textarea>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row">
                <button class="btn-primary w-full justify-center sm:w-auto" type="submit">Save card</button>
            </div>
        </form>

        <div class="mt-8 border-t border-slate-200 pt-6">
            <h2 class="text-base font-semibold text-slate-950">Delete card</h2>
            <p class="mt-2 text-sm text-slate-600">
                Remove this word and its aliases from the current set.
            </p>
            <form method="post" action="/admin/cards/<?= e((string) $card['id']) ?>/delete" class="mt-4">
                <?= Csrf::input() ?>
                <button class="btn-danger w-full justify-center sm:w-auto" type="submit">Delete card</button>
            </form>
        </div>
    </div>
</section>
