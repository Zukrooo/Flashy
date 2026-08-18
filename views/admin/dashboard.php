<?php use App\Security\Csrf; ?>
<section class="space-y-6">
    <?php
    $activePage = $active_page ?? 'sets';
    $pages = [
        'languages' => [
            'label' => 'Languages',
            'href' => '/admin?page=languages',
            'count' => count($languages ?? []),
        ],
        'sets' => [
            'label' => 'Sets',
            'href' => '/admin?page=sets',
            'count' => count($sets ?? []),
        ],
        'tools' => [
            'label' => 'Tools',
            'href' => '/admin?page=tools',
            'count' => null,
        ],
    ];
    ?>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Admin dashboard</p>
            <h1 class="mt-3 text-3xl font-semibold text-slate-950">Manage study content</h1>
        </div>
        <div class="grid gap-3 sm:flex">
            <a class="btn-secondary w-full sm:w-auto" href="/admin/languages/new">Create language</a>
            <a class="btn-primary w-full sm:w-auto" href="/admin/sets/new">Create set</a>
        </div>
    </div>

    <div class="panel px-4 py-4 sm:px-6">
        <div class="flex flex-wrap gap-2">
            <?php foreach ($pages as $pageKey => $page): ?>
                <?php $isActive = $activePage === $pageKey; ?>
                <a
                    class="<?= $isActive ? 'btn-primary' : 'btn-secondary' ?> w-full justify-center gap-3 sm:w-auto"
                    href="<?= e($page['href']) ?>">
                    <?= e($page['label']) ?>
                    <?php if ($page['count'] !== null): ?>
                        <span class="<?= $isActive ? 'bg-slate-950/20 text-white' : 'bg-slate-200 text-slate-700' ?> rounded-full px-2 py-0.5 text-xs font-semibold">
                            <?= e((string) $page['count']) ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($activePage === 'languages'): ?>
        <div class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 class="text-lg font-semibold text-slate-950">Languages</h2>
                <p class="mt-1 text-sm text-slate-600">Manage the languages learners can choose from.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[42rem] divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                    <tr class="text-left text-sm text-slate-600">
                        <th class="px-6 py-4 font-medium">Language</th>
                        <th class="px-6 py-4 font-medium">Sets</th>
                        <th class="px-6 py-4 font-medium">Cards</th>
                        <th class="px-6 py-4 font-medium">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if ($languages === []): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-sm text-slate-600">No languages yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($languages as $language): ?>
                        <tr class="align-top">
                            <td class="px-6 py-5">
                                <div class="font-semibold text-slate-950"><?= e($language['name']) ?></div>
                                <div class="mt-1 max-w-md text-sm text-slate-600"><?= e($language['description']) ?></div>
                            </td>
                            <td class="px-6 py-5 text-sm text-slate-700"><?= e((string) $language['set_count']) ?></td>
                            <td class="px-6 py-5 text-sm text-slate-700"><?= e((string) $language['card_count']) ?></td>
                            <td class="px-6 py-5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a class="btn-secondary" href="/admin/languages/<?= e((string) $language['id']) ?>/edit">Edit language</a>
                                    <form method="post" action="/admin/languages/<?= e((string) $language['id']) ?>/delete">
                                        <?= Csrf::input() ?>
                                        <button class="btn-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($activePage === 'tools'): ?>
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="panel px-5 py-5 sm:px-6">
                <h2 class="text-lg font-semibold text-slate-950">Current database</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Flashy is currently connected to this database configuration.
                </p>
                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="rounded-full bg-slate-900 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-white">
                            <?= e((string) (($database_config['label'] ?? $database_driver ?? 'Database'))) ?>
                        </span>
                    </div>
                    <dl class="mt-4 grid gap-3 text-sm text-slate-700">
                        <?php foreach (($database_config['details'] ?? []) as $label => $value): ?>
                            <div class="grid gap-1 sm:grid-cols-[6rem_minmax(0,1fr)] sm:items-start sm:gap-3">
                                <dt class="font-semibold text-slate-900"><?= e((string) $label) ?></dt>
                                <dd class="break-all text-slate-600"><?= e((string) $value) ?></dd>
                            </div>
                        <?php endforeach; ?>
                    </dl>
                </div>
            </div>

            <div class="panel px-5 py-5 sm:px-6">
                <h2 class="text-lg font-semibold text-slate-950">Database tools</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Run the latest schema migrations against the currently configured <span class="font-semibold text-slate-900"><?= e((string) ($database_driver ?? 'database')) ?></span> database,
                    or import a local SQL export directly into it.
                </p>
                <form method="post" action="/admin/tools/migrate" class="mt-5">
                    <?= Csrf::input() ?>
                    <button class="btn-primary w-full justify-center sm:w-auto" type="submit">Run migrations</button>
                </form>
                <form method="post" action="/admin/tools/import-sql" enctype="multipart/form-data" class="mt-6 space-y-4">
                    <?= Csrf::input() ?>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700" for="sql_file">Import local SQL</label>
                        <input
                            id="sql_file"
                            name="sql_file"
                            type="file"
                            accept=".sql,text/sql,application/sql"
                            class="block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700 file:mr-4 file:cursor-pointer file:rounded-xl file:border-0 file:bg-slate-800 file:px-4 file:py-2 file:font-medium file:text-white hover:file:bg-slate-900 focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-300">
                    </div>
                    <p class="text-xs leading-5 text-slate-500">
                        Use this for a SQL dump that matches the current database driver. Import runs against the live configured database.
                    </p>
                    <button class="btn-secondary w-full justify-center sm:w-auto" type="submit">Import SQL</button>
                </form>
            </div>

            <div class="panel px-5 py-5 sm:px-6">
                <h2 class="text-lg font-semibold text-slate-950">Create admin user</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Create a new admin account that can log in through the normal learner sign-in and access the admin panel.
                </p>
                <form method="post" action="/admin/tools/create-admin" class="mt-5 space-y-4">
                    <?= Csrf::input() ?>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="admin_first_name">First name</label>
                            <input
                                id="admin_first_name"
                                name="first_name"
                                type="text"
                                class="input"
                                required>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="admin_last_name">Last name</label>
                            <input
                                id="admin_last_name"
                                name="last_name"
                                type="text"
                                class="input"
                                required>
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700" for="admin_email">Email</label>
                        <input
                            id="admin_email"
                            name="email"
                            type="email"
                            class="input"
                            required>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="admin_password">Password</label>
                            <input
                                id="admin_password"
                                name="password"
                                type="password"
                                class="input"
                                minlength="8"
                                required>
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-700" for="admin_password_confirm">Confirm password</label>
                            <input
                                id="admin_password_confirm"
                                name="password_confirm"
                                type="password"
                                class="input"
                                minlength="8"
                                required>
                        </div>
                    </div>
                    <button class="btn-primary w-full justify-center sm:w-auto" type="submit">Create admin</button>
                </form>
            </div>

            <div class="panel px-5 py-5 sm:px-6 lg:col-span-2">
                <h2 class="text-lg font-semibold text-slate-950">CSV sample</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600">
                    Download a starter CSV template for importing cards into a set.
                </p>
                <a class="btn-secondary mt-5 w-full justify-center sm:w-auto" href="/public/assets/set-import-sample.csv" download>Download sample CSV</a>
            </div>
        </div>
    <?php else: ?>
        <div class="panel overflow-hidden">
            <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
                <h2 class="text-lg font-semibold text-slate-950">Sets</h2>
                <p class="mt-1 text-sm text-slate-600">Manage published study sets, their visibility, and card collections.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                    <tr class="text-left text-sm text-slate-600">
                        <th class="px-6 py-4 font-medium">Language</th>
                        <th class="px-6 py-4 font-medium">Set</th>
                        <th class="px-6 py-4 font-medium">Cards</th>
                        <th class="px-6 py-4 font-medium">Status</th>
                        <th class="px-6 py-4 font-medium">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                    <?php if ($sets === []): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-sm text-slate-600">No sets yet.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($sets as $set): ?>
                        <tr class="align-top">
                            <td class="px-6 py-5 text-sm font-medium text-slate-700"><?= e($set['language_name']) ?></td>
                            <td class="px-6 py-5">
                                <div class="font-semibold text-slate-950"><?= e($set['name']) ?></div>
                                <div class="mt-1 max-w-md text-sm text-slate-600"><?= e($set['description']) ?></div>
                            </td>
                            <td class="px-6 py-5 text-sm text-slate-700"><?= e((string) $set['card_count']) ?></td>
                            <td class="px-6 py-5 text-sm">
                                <?php if ((int) $set['published'] === 1): ?>
                                    <span class="rounded-full bg-slate-200 px-3 py-1 font-medium text-slate-800">Published</span>
                                <?php else: ?>
                                    <span class="rounded-full bg-slate-100 px-3 py-1 font-medium text-slate-700">Hidden</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-5">
                                <div class="grid gap-3 sm:min-w-[12rem]">
                                    <div>
                                        <div class="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Manage</div>
                                        <div class="flex flex-wrap gap-2">
                                            <a class="btn-secondary" href="/admin/sets/<?= e((string) $set['id']) ?>/cards">Cards</a>
                                            <a class="btn-secondary" href="/admin/sets/<?= e((string) $set['id']) ?>/edit">Edit</a>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Visibility</div>
                                        <div class="flex flex-wrap gap-2">
                                            <form method="post" action="/admin/sets/<?= e((string) $set['id']) ?>/<?= (int) $set['published'] === 1 ? 'hide' : 'publish' ?>">
                                                <?= Csrf::input() ?>
                                                <button class="btn-secondary" type="submit">
                                                    <?= (int) $set['published'] === 1 ? 'Hide' : 'Publish' ?>
                                                </button>
                                            </form>
                                            <form method="post" action="/admin/sets/<?= e((string) $set['id']) ?>/delete">
                                                <?= Csrf::input() ?>
                                                <button class="btn-danger" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>
