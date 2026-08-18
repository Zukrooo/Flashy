<section class="space-y-8">
	<?php $isPracticeMode = (bool) ($is_practice_mode ?? false); ?>
	<div>
		<h1 class="mt-4 mb-2 max-w-3xl text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl lg:text-5xl">
			<?= e($isPracticeMode ? 'Choose a language to practice.' : 'Choose a language to study.') ?></h1>
		<p class="max-w-2xl text-base leading-7 text-slate-600">
			<?= e($isPracticeMode
                ? 'Pick a language first, then choose a set to practice without the timer.'
                : 'Pick a language first, then choose a set or study all published sets together.') ?>
		</p>
		<?php if (!($is_logged_in ?? false) && !$isPracticeMode): ?>
			<div class="mt-4 max-w-2xl rounded-2xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm leading-6 text-slate-700">
				Study as a guest with a fresh session each time you visit. Create an account or log in to unlock smart
				sets like Incorrect, Difficult, New, and Mastered, plus saved progress.
			</div>
		<?php endif; ?>
	</div>

	<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
		<?php if ($languages === []): ?>
			<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-600">
				No published languages or sets are available yet.
			</div>
		<?php endif; ?>
		
		<?php foreach ($languages as $language): ?>
			<div class="panel px-5 py-6 transition hover:-translate-y-0.5 hover:shadow-2xl sm:px-6 sm:py-6">
				<div class="flex h-full flex-col gap-4">
					<div>
						<h2 class="text-2xl font-semibold text-slate-950"><?= e($language['name']) ?></h2>
						<p class="mt-3 text-sm leading-6 text-slate-600">
							<?= e($language['description'] !== '' ? $language['description'] : 'No description yet.') ?>
						</p>
						<p class="mt-5 text-xs font-medium uppercase tracking-[0.2em] text-slate-500">
							<?= e((string)$language['set_count']) ?> sets · <?= e((string)$language['card_count']) ?>
							words
						</p>
					</div>
					<a
							class="btn-secondary self-start justify-center"
							href="<?= e($isPracticeMode ? '/practice/languages/' . (string) $language['id'] : '/languages/' . (string) $language['id']) ?>"><?= e($isPracticeMode ? 'Practice' : 'Study') ?></a>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
