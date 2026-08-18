<?php use App\Security\Csrf; ?>
<section class="space-y-6">
    <style>
        .admin-cards-layout {
            display: grid;
            gap: 1.5rem;
        }

        @media (min-width: 768px) {
            .admin-cards-layout {
                grid-template-columns: minmax(18rem, 0.78fr) minmax(0, 1.22fr);
                align-items: start;
            }
        }
    </style>

    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Manage cards</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950"><?= e($set['name']) ?></h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600">
                <?= e($set['language_name']) ?><?php if ($set['description'] !== ''): ?> · <?= e($set['description']) ?><?php endif; ?>
            </p>
        </div>
        <div class="grid gap-3 sm:flex sm:items-center">
            <a class="btn-secondary w-full justify-center sm:w-auto" href="/admin/sets/<?= e((string) $set['id']) ?>/export">Export set</a>
            <a class="btn-secondary w-full justify-center sm:w-auto" href="/admin/sets/<?= e((string) $set['id']) ?>/edit">Edit set</a>
            <a class="inline-flex w-fit items-center gap-2 rounded-2xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-100 shadow-sm" href="/admin">
                <span aria-hidden="true">←</span>
                <span>Back</span>
            </a>
        </div>
    </div>

    <div class="admin-cards-layout">
        <div class="space-y-6">
            <div class="panel px-5 py-6 sm:px-8 sm:py-8">
                <h2 class="text-xl font-semibold text-slate-950">Add card</h2>
                <form method="post" action="/admin/sets/<?= e((string) $set['id']) ?>/cards" class="mt-6 space-y-5">
                    <?= Csrf::input() ?>
                    <div class="field">
                        <label class="label" for="gaidhlig">Language word</label>
                        <input id="gaidhlig" name="gaidhlig" type="text" required>
                    </div>
                    <div class="field">
                        <label class="label" for="english">English</label>
                        <input id="english" name="english" type="text" required>
                    </div>
                    <div class="field">
                        <label class="label" for="language_aliases">Language aliases</label>
                        <textarea id="language_aliases" name="language_aliases" placeholder="One exact alias per line"></textarea>
                    </div>
                    <div class="field">
                        <label class="label" for="english_aliases">English aliases</label>
                        <textarea id="english_aliases" name="english_aliases" placeholder="One exact alias per line"></textarea>
                    </div>
                    <button class="btn-primary w-full justify-center sm:w-auto" type="submit">Add card</button>
                </form>

                <div class="mt-8 border-t border-slate-200 pt-6">
                    <h2 class="text-xl font-semibold text-slate-950">Import CSV</h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Use columns: <code>language_word</code>, <code>english</code>, optional <code>language_aliases</code>, optional <code>english_aliases</code>.
                        Aliases can be separated with <code>|</code> inside the CSV cell.
                    </p>
                    <div class="mt-4">
                        <a class="btn-secondary w-full justify-center sm:w-auto" href="/public/assets/set-import-sample.csv" download>Download sample CSV</a>
                    </div>
                    <form method="post" action="/admin/sets/<?= e((string) $set['id']) ?>/import" enctype="multipart/form-data" class="mt-6 space-y-5">
                        <?= Csrf::input() ?>
                        <div class="field">
                            <label class="label" for="csv_file">CSV file</label>
                            <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv" required>
                        </div>
                        <button class="btn-primary w-full justify-center sm:w-auto" type="submit">Import CSV</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="panel overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full table-fixed divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                    <tr class="text-left text-sm text-slate-600">
                        <th class="w-[22%] px-6 py-4 font-medium">Language word</th>
                        <th class="w-[22%] px-6 py-4 font-medium">English</th>
                        <th class="w-[34%] px-6 py-4 font-medium">Aliases</th>
                        <th class="w-[22%] px-6 py-4 font-medium">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if ($cards === []): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-sm text-slate-600">No cards yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($cards as $card): ?>
                        <tr>
                            <td class="break-words px-6 py-5 text-sm font-medium text-slate-950"><?= e($card['gaidhlig']) ?></td>
                            <td class="break-words px-6 py-5 text-sm text-slate-700"><?= e($card['english']) ?></td>
                            <td class="break-words px-6 py-5 text-sm text-slate-700">
                                <?php if (($card['language_aliases'] ?? '') !== ''): ?>
                                    <div><strong>Language:</strong> <?= nl2br(e(str_replace('|', "\n", $card['language_aliases']))) ?></div>
                                <?php endif; ?>
                                <?php if (($card['english_aliases'] ?? '') !== ''): ?>
                                    <div class="mt-2"><strong>English:</strong> <?= nl2br(e(str_replace('|', "\n", $card['english_aliases']))) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5">
                                <div class="flex flex-nowrap items-center gap-2 whitespace-nowrap">
                                    <a
                                        class="btn-secondary h-10 w-10 justify-center p-0"
                                        href="/admin/cards/<?= e((string) $card['id']) ?>/edit"
                                        aria-label="Edit card"
                                        title="Edit card"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path d="M14.69 2.86a1.5 1.5 0 0 1 2.12 2.12l-8.2 8.2-3.43.65.65-3.43 8.86-8.86Zm-7.8 8.5-.27 1.42 1.42-.27 7.7-7.7-1.15-1.15-7.7 7.7Z" />
                                        </svg>
                                        <span class="sr-only">Edit</span>
                                    </a>
                                    <form class="m-0 inline-flex shrink-0" method="post" action="/admin/cards/<?= e((string) $card['id']) ?>/delete">
                                        <?= Csrf::input() ?>
                                        <button
                                            class="btn-danger h-10 w-10 shrink-0 justify-center p-0"
                                            type="submit"
                                            aria-label="Delete card"
                                            title="Delete card"
                                        >
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path d="M7.5 2.5A1.5 1.5 0 0 0 6 4v1H3.75a.75.75 0 0 0 0 1.5h.64l.72 8.01A2 2 0 0 0 7.1 16.5h5.8a2 2 0 0 0 1.99-1.99l.72-8.01h.64a.75.75 0 0 0 0-1.5H14V4a1.5 1.5 0 0 0-1.5-1.5h-5Zm5 2.5h-5V4h5v1Zm-4.25 3a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 8.25 8Zm3.5 0a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5a.75.75 0 0 1 .75-.75Z" />
                                            </svg>
                                            <span class="sr-only">Delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
