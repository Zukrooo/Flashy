<?php use App\Security\Csrf; ?>
<section class="mx-auto w-full max-w-3xl">
    <div class="panel px-5 py-6 sm:px-8 sm:py-8">
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Install Flashy</p>
        <h1 class="mt-3 text-3xl font-semibold text-slate-950">Connect your 20i MySQL database</h1>
        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
            Enter the MySQL details from My20i, then create the first admin account. On 20i shared hosting, use the
            database hostname they provide rather than <span class="font-semibold text-slate-900">localhost</span>.
        </p>

        <form method="post" action="/install" class="mt-8 space-y-8">
            <?= Csrf::input() ?>

            <div class="space-y-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">Database</h2>
                    <p class="mt-1 text-sm text-slate-600">These values will be written to `config/database.php`.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="db_host">Host</label>
                        <input id="db_host" name="db_host" type="text" value="<?= e(old('db_host')) ?>" placeholder="shareddb1b.hosting.stackcp.net" required>
                    </div>
                    <div class="field">
                        <label class="label" for="db_port">Port</label>
                        <input id="db_port" name="db_port" type="number" min="1" value="<?= e(old('db_port', '3306')) ?>" required>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="db_name">Database name</label>
                        <input id="db_name" name="db_name" type="text" value="<?= e(old('db_name')) ?>" required>
                    </div>
                    <div class="field">
                        <label class="label" for="db_user">Database user</label>
                        <input id="db_user" name="db_user" type="text" value="<?= e(old('db_user')) ?>" required>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="db_pass">Database password</label>
                        <input id="db_pass" name="db_pass" type="password" required>
                    </div>
                    <div class="field">
                        <label class="label" for="db_charset">Charset</label>
                        <input id="db_charset" name="db_charset" type="text" value="<?= e(old('db_charset', 'utf8mb4')) ?>" required>
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950">First admin</h2>
                    <p class="mt-1 text-sm text-slate-600">This account will be able to log in and manage the app.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="first_name">First name</label>
                        <input id="first_name" name="first_name" type="text" value="<?= e(old('first_name')) ?>" required>
                    </div>
                    <div class="field">
                        <label class="label" for="last_name">Last name</label>
                        <input id="last_name" name="last_name" type="text" value="<?= e(old('last_name')) ?>" required>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="field">
                        <label class="label" for="password">Password</label>
                        <input id="password" name="password" type="password" minlength="8" required>
                    </div>
                    <div class="field">
                        <label class="label" for="password_confirm">Confirm password</label>
                        <input id="password_confirm" name="password_confirm" type="password" minlength="8" required>
                    </div>
                </div>
            </div>

            <button class="btn-primary w-full justify-center sm:w-auto" type="submit">Install Flashy</button>
        </form>
    </div>
</section>
