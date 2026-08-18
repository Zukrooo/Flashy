<?php use App\Security\Csrf; ?>
<section class="mx-auto max-w-3xl space-y-6">
    <div class="panel px-5 py-6 sm:px-8 sm:py-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Profile</p>
                <h1 class="mt-3 text-3xl font-semibold text-slate-950">Edit profile</h1>
                <p class="mt-2 text-sm text-slate-600">Update your account details and password.</p>
            </div>
            <a class="mb-4 inline-flex w-fit items-center gap-2 rounded-2xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-100 shadow-sm" href="/">
                <span aria-hidden="true">←</span>
                <span>Back</span>
            </a>
        </div>

        <form method="post" action="/profile" class="mt-8 space-y-5">
            <?= Csrf::input() ?>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="field">
                    <label class="label" for="first_name">First name</label>
                    <input id="first_name" name="first_name" type="text" value="<?= e(old('first_name', (string) $user['first_name'])) ?>" required>
                </div>
                <div class="field">
                    <label class="label" for="last_name">Last name</label>
                    <input id="last_name" name="last_name" type="text" value="<?= e(old('last_name', (string) $user['last_name'])) ?>" required>
                </div>
            </div>
            <div class="field">
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e(old('email', (string) $user['email'])) ?>" required>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div class="field">
                    <label class="label" for="password">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password">
                </div>
                <div class="field">
                    <label class="label" for="password_confirm">Confirm new password</label>
                    <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password">
                </div>
            </div>
            <p class="text-xs text-slate-500">Leave the password fields blank if you do not want to change it.</p>
            <button class="btn-primary w-full justify-center sm:w-auto" type="submit">Save profile</button>
        </form>
    </div>

    <div class="panel px-5 py-6 sm:px-8 sm:py-8">
        <h2 class="text-xl font-semibold text-slate-950">Clear study data</h2>
        <p class="mt-2 text-sm text-slate-600">
            Remove your saved guesses, progress, and personal study set data. Your account will remain active.
        </p>
        <form method="post" action="/profile/clear-data" class="mt-6">
            <?= Csrf::input() ?>
            <button class="btn-danger w-full justify-center sm:w-auto" type="submit">Clear my data</button>
        </form>
    </div>
</section>
