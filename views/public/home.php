<section class="space-y-8">
	<?php $selectedLanguage = is_array($selected_language ?? null) ? $selected_language : null; ?>
	<div>
		<h1 class="mt-4 mb-2 max-w-3xl text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl lg:text-5xl">
			Choose mode
		</h1>
		<p class="max-w-2xl text-base leading-7 text-slate-600">
			Choose your language in the header, then start either a tracked study session or untimed practice.
		</p>
		<?php if (!($is_logged_in ?? false)): ?>
			<div class="mt-4 max-w-2xl rounded-2xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm leading-6 text-slate-700">
				Study as a guest with a fresh session each time you visit. Create an account or log in to unlock smart
				sets like Incorrect, Difficult, New, and Mastered, plus saved progress.
			</div>
		<?php endif; ?>
	</div>

	<?php if ($selectedLanguage === null): ?>
		<div class="rounded-2xl border border-dashed border-slate-300 px-4 py-6 text-sm text-slate-600">
			No published languages or sets are available yet.
		</div>
	<?php else: ?>
		<div class="grid gap-4 lg:grid-cols-2">
			<div class="panel h-full px-5 py-6 transition hover:-translate-y-0.5 hover:shadow-2xl">
				<div class="flex h-full flex-col justify-between gap-4">
					<div>
						<h2 class="text-xl font-semibold text-slate-950">Study</h2>
						<p class="mt-2 text-sm text-slate-600">
							Go to the set list for <?= e($selectedLanguage['name']) ?> and study with timers, guesses, and smart sets.
						</p>
					</div>
					<div class="mt-auto flex items-end justify-between gap-3">
						<p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Sets</p>
						<a
								class="btn-secondary justify-center"
								href="<?= e((string) ($study_path ?? '/')) ?>">Open sets</a>
					</div>
				</div>
			</div>
			<div class="panel h-full px-5 py-6 transition hover:-translate-y-0.5 hover:shadow-2xl">
				<div class="flex h-full flex-col justify-between gap-4">
					<div>
						<h2 class="text-xl font-semibold text-slate-950">Practice</h2>
						<p class="mt-2 text-sm text-slate-600">
							Go to the set list for <?= e($selectedLanguage['name']) ?> and practice without recording stats.
						</p>
					</div>
					<div class="mt-auto flex items-end justify-between gap-3">
						<p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Sets</p>
						<a
								class="btn-secondary justify-center"
								href="<?= e((string) ($practice_path ?? '/')) ?>">Open sets</a>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
</section>
