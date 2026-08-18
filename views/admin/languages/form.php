<?php use App\Security\Csrf; ?>
<section class="mx-auto max-w-3xl">
    <div class="panel px-5 py-6 sm:px-8 sm:py-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Languages</p>
                <h1 class="mt-3 text-3xl font-semibold text-slate-950"><?= $language === null ? 'Create language' : 'Edit language' ?></h1>
            </div>
            <a class="mb-4 inline-flex w-fit items-center gap-2 rounded-2xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-100 shadow-sm" href="/admin">
                <span aria-hidden="true">←</span>
                <span>Back</span>
            </a>
        </div>

        <form method="post" action="<?= e($action) ?>" class="mt-8 space-y-5">
            <?= Csrf::input() ?>
            <div class="field">
                <label class="label" for="name">Language name</label>
                <input id="name" name="name" type="text" value="<?= e($language['name'] ?? old('name')) ?>" required>
            </div>

            <div class="field">
                <label class="label" for="description">Description</label>
                <textarea id="description" name="description"><?= e($language['description'] ?? old('description')) ?></textarea>
            </div>

            <button class="btn-primary w-full justify-center sm:w-auto" type="submit"><?= $language === null ? 'Create language' : 'Save changes' ?></button>
        </form>
    </div>
</section>
