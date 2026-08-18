<?php use App\Security\Csrf; ?>
<section class="mx-auto max-w-6xl space-y-6">
	<?php
	$isPracticeMode = (bool) ($is_practice_mode ?? false);
	$finiteSetStat = is_array($finite_set_stat ?? null) ? $finite_set_stat : null;
	$formatSeconds = static function (?int $seconds): string {
		if ($seconds === null || $seconds < 0) {
			return '0:00';
		}

		$hours = intdiv($seconds, 3600);
		$minutes = intdiv($seconds % 3600, 60);
		$remainingSeconds = $seconds % 60;

		if ($hours > 0) {
			return sprintf('%d:%02d:%02d', $hours, $minutes, $remainingSeconds);
		}

		return sprintf('%d:%02d', $minutes, $remainingSeconds);
	};
	$availableSmartSets = array_values(array_filter(
		$smart_sets ?? [],
		static fn(array $smartSet): bool => (int)($smartSet['count'] ?? 0) > 0
	));
	$emptySmartSets = array_values(array_filter(
		$smart_sets ?? [],
		static fn(array $smartSet): bool => (int)($smartSet['count'] ?? 0) === 0
	));
	$orderedSmartSets = [...$availableSmartSets, ...$emptySmartSets];
	?>
	<div class="flex flex-col gap-2">
		<a
				class="mb-4 inline-flex w-fit items-center gap-2 rounded-2xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-100 shadow-sm"
				href="<?= e($isPracticeMode ? '/practice/languages/' . (string) $set['language_id'] : '/languages/' . (string) $set['language_id']) ?>">
            <span aria-hidden="true">←</span>
            <span>Back</span>
        </a>
		<div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
			<div>
				<div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                    <?= e($set['language_name']) ?>
                </span>
					<span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                    <?= e((string)$card_count) ?> words
                </span>
				</div>
				<h1 class="mt-4 text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl"><?= e($set['name']) ?></h1>
				<p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">
					<?= e($set['description'] !== '' ? $set['description'] : 'Choose whether to study the whole set or focus on one smart subset.') ?>
				</p>
			</div>
		</div>
	</div>

	<div class="space-y-4">
		<div class="rounded-3xl border border-slate-300 bg-slate-100 px-5 py-6 shadow-sm sm:px-8">
			<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
				<div>
					<p class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Whole set</p>
					<h2 class="mt-2 text-xl font-semibold text-slate-950"><?= e($set['name']) ?></h2>
					<p class="mt-2 text-sm text-slate-600"><?= e($isPracticeMode ? 'Practice every card in this set without the timer.' : 'Study every card in this set with shuffled order and mixed directions.') ?></p>
					<?php if (!$isPracticeMode && $finiteSetStat !== null): ?>
						<p class="mt-3 text-sm font-medium text-slate-700">
							Fastest finite run:
							<strong><?= e($formatSeconds((int) ($finiteSetStat['best_time_seconds'] ?? 0))) ?></strong>
						</p>
					<?php endif; ?>
				</div>
				<form
						method="post"
						action="<?= e($isPracticeMode ? '/practice/sets/' . (string) $set['id'] . '/start' : '/sets/' . (string) $set['id'] . '/start') ?>">
					<?= Csrf::input() ?>
                    <button
                            class="btn-secondary w-full justify-center sm:w-auto"
                            type="submit"><?= e($isPracticeMode ? 'Practice' : 'Study') ?>
                    </button>
				</form>
			</div>
		</div>
		
		<?php if ($show_smart_sets && !$isPracticeMode): ?>
			<div class="pt-4 sm:pt-6">
				<p class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Smart Sets</p>
				<h2 class="mt-2 text-2xl font-semibold text-slate-950">Focus this set where it matters most.</h2>
			</div>
			<div class="grid gap-4 sm:grid-cols-2 md:grid-cols-4">
				<?php foreach ($orderedSmartSets as $smartSet): ?>
					<?php $isEmpty = (int)$smartSet['count'] === 0; ?>
					<div class="h-full rounded-3xl border px-5 py-6 shadow-sm <?= $isEmpty ? 'border-slate-300 bg-slate-200 text-slate-500' : 'border-slate-700 bg-slate-800 text-white' ?>">
						<div class="flex h-full w-full flex-col gap-4">
							<div>
								<h2 class="text-xl font-semibold <?= $isEmpty ? 'text-slate-700' : 'text-white' ?>"><?= e($smartSet['name']) ?></h2>
								<p class="mt-2 text-sm <?= $isEmpty ? 'text-slate-500' : 'text-slate-300' ?>">
									<?= e($smartSet['description']) ?>
								</p>
							</div>
							<div class="mt-auto flex items-end justify-between gap-3">
								<div>
									<?php if ($isEmpty): ?>
										<p class="text-xs font-medium leading-5 text-rose-700">We need more data,
											study more cards to use this smart set.</p>
									<?php else: ?>
										<p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-400"><?= e((string)$smartSet['count']) ?>
											words</p>
									<?php endif; ?>
								</div>
								<?php if (!$isEmpty): ?>
									<form
											method="post"
											action="<?= e($isPracticeMode
												? '/practice/sets/' . (string) $set['id'] . '/smart/' . $smartSet['key'] . '/start'
												: '/sets/' . (string) $set['id'] . '/smart/' . $smartSet['key'] . '/start') ?>">
										<?= Csrf::input() ?>
										<button
												class="btn-secondary justify-center sm:w-auto"
												type="submit">
											<?= e($isPracticeMode ? 'Practice' : 'Study') ?>
										</button>
									</form>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php elseif (!$isPracticeMode): ?>
			<div class="rounded-2xl border border-slate-200 bg-slate-100 px-5 py-5 text-sm leading-6 text-slate-700">
				You can study this set as a guest right away. Create an account or log in to unlock smart sets based on
				your progress and keep your history.
			</div>
		<?php endif; ?>
	</div>
</section>
