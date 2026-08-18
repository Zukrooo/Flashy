<?php use App\Security\Csrf; ?>
<section class="mx-auto max-w-md">
    <div class="panel px-5 py-6 sm:px-8 sm:py-8">
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Study access</p>
        <h1 class="mt-3 text-3xl font-semibold text-slate-950">Log in</h1>
        <p class="mt-2 text-sm text-slate-600">Log in to track progress and unlock personal study sets.</p>
        <form method="post" action="/login" class="mt-8 space-y-5">
            <?= Csrf::input() ?>
            <div class="field">
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="btn-primary w-full justify-center" type="submit">Log in</button>
        </form>
        <p class="mt-6 text-sm text-slate-600">No account yet? <a class="font-semibold text-slate-900 underline" href="/register">Create one</a>.</p>
    </div>
</section>
