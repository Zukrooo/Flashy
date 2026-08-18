<?php use App\Security\Csrf; ?>
<section class="mx-auto max-w-md">
    <div class="panel px-5 py-6 sm:px-8 sm:py-8">
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Admin access</p>
        <h1 class="mt-3 text-3xl font-semibold text-slate-950">Log in</h1>
        <form method="post" action="/admin/login" class="mt-8 space-y-5">
            <?= Csrf::input() ?>
            <div class="field">
                <label class="label" for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= e(old('email')) ?>" required>
            </div>
            <div class="field">
                <label class="label" for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="btn-primary w-full" type="submit">Log in</button>
        </form>
    </div>
</section>
