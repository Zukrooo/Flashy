<?php use App\Security\Csrf; ?>
<section class="mx-auto max-w-4xl">
    <div class="panel px-5 py-6 sm:px-8 sm:py-8">
        <p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Session complete</p>
        <h1 class="mt-3 text-3xl font-semibold text-slate-950"><?= e($context['name']) ?></h1>
        <p class="mt-2 text-sm text-slate-600"><?= e($context['subtitle']) ?></p>
        <p class="mt-4 text-base text-slate-600">
            Score: <strong><?= e((string) $summary['score']) ?></strong> / <strong><?= e((string) $summary['total']) ?></strong>
        </p>

        <div class="mt-8 space-y-3">
            <?php foreach ($summary['results'] as $result): ?>
                <?php
                $rowClasses = $result['correct']
                    ? 'border-slate-200 bg-slate-100'
                    : 'border-rose-200 bg-rose-50';
                $expectedLabel = $result['answer_language'] === 'English' ? 'English' : $context['subtitle'];
                $answerText = !empty($result['skipped']) ? 'Skipped' : $result['answer'];
                ?>
                <div class="rounded-2xl border px-4 py-4 <?= $rowClasses ?>">
                    <div class="mt-1 text-sm font-semibold text-slate-950"><?= e($result['prompt']) ?></div>
                    <?php if (!empty($result['set_name'])): ?>
                        <div class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">Set: <?= e($result['set_name']) ?></div>
                    <?php endif; ?>
                    <div class="mt-1 text-sm text-slate-700"><?= e($expectedLabel) ?>: <?= e($result['expected']) ?></div>
                    <div class="mt-1 text-sm text-slate-700">Your answer: <?= e($answerText) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-8 grid gap-3 sm:flex sm:flex-wrap">
            <form method="post" action="<?= e($context['reset_path']) ?>">
                <?= Csrf::input() ?>
                <button class="btn-primary w-full justify-center sm:w-auto" type="submit">Back to languages</button>
            </form>
            <form method="post" action="<?= e($context['restart_path']) ?>">
                <?= Csrf::input() ?>
                <button class="btn-secondary w-full justify-center sm:w-auto" type="submit">Study again</button>
            </form>
        </div>
    </div>
</section>
