<?php use App\Security\Csrf; ?>
<section class="mx-auto max-w-md">
    <div class="panel px-5 py-6 sm:px-8 sm:py-8">
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Study access</p>
        <h1 class="mt-3 text-3xl font-semibold text-slate-950">Create account</h1>
        <p class="mt-2 text-sm text-slate-600">Create a learner account to save progress and study smart sets.</p>
        <form method="post" action="/register" class="mt-8 space-y-5">
            <?= Csrf::input() ?>
            <div class="field">
                <label class="label" for="first_name">First name</label>
                <input id="first_name" name="first_name" type="text" value="<?= e(old('first_name')) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="last_name">Last name</label>
                <input id="last_name" name="last_name" type="text" value="<?= e(old('last_name')) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <div class="field">
                <label class="label" for="password_confirm">Confirm password</label>
                <input id="password_confirm" name="password_confirm" type="password" required>
            </div>
            <button class="btn-primary w-full justify-center" type="submit">Create account</button>
        </form>
        <p class="mt-6 text-sm text-slate-600">Already have an account? <a class="font-semibold text-slate-900 underline" href="/login">Log in</a>.</p>
    </div>
</section>
