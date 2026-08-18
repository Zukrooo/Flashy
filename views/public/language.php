<?php use App\Security\Csrf; ?>
<section class="mx-auto max-w-6xl space-y-8">
	<?php
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
	<style>
		.smart-set-row {
			display: flex;
			align-items: stretch;
			gap: 1rem;
			overflow-x: auto;
			overscroll-behavior-x: contain;
			padding-bottom: 0.5rem;
			cursor: grab;
			user-select: none;
			scrollbar-width: none;
			-ms-overflow-style: none;
		}

		.smart-set-row::-webkit-scrollbar {
			display: none;
		}

		.smart-set-card {
			min-width: min(18rem, 82vw);
			display: flex;
		}
	</style>
	<div class="flex flex-col gap-2">
		<a
				class="mb-4 inline-flex w-fit items-center gap-2 rounded-2xl border border-slate-800 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-100 shadow-sm"
				href="/">
            <span aria-hidden="true">←</span>
            <span>Back</span>
        </a>
		<div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
			<div>
				<h1 class="text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl"><?= e($language['name']) ?></h1>
				<p class="mt-4 max-w-2xl text-base leading-7 text-slate-600">
					<?= e($language['description'] !== '' ? $language['description'] : 'Choose a set to start studying.') ?>
				</p>
			</div>
		</div>
	</div>

	<div class="space-y-4">
		<?php if ($show_smart_sets): ?>
			<div class="flex items-end justify-between gap-4">
				<div>
					<p class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Smart Sets</p>
					<h2 class="mt-2 text-2xl font-semibold text-slate-950">Study what needs you most.</h2>
				</div>
				<div class="hidden items-center gap-2 rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-600 md:inline-flex">
					<span>Slide</span>
					<span aria-hidden="true">→</span>
				</div>
			</div>
			<div
					id="smart-set-row"
					class="smart-set-row touch-pan-x">
				<?php foreach ($orderedSmartSets as $smartSet): ?>
					<?php $isEmpty = (int)$smartSet['count'] === 0; ?>
					<div class="smart-set-card rounded-3xl border px-5 py-6 shadow-sm <?= $isEmpty ? 'border-slate-300 bg-slate-200 text-slate-500' : 'border-slate-700 bg-slate-800 text-white' ?>">
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
											cards</p>
									<?php endif; ?>
								</div>
								<?php if (!$isEmpty): ?>
									<form
											method="post"
											action="/languages/<?= e((string)$language['id']) ?>/smart/<?= e($smartSet['key']) ?>/start">
										<?= Csrf::input() ?>
										<button
												class="btn-secondary justify-center sm:w-auto"
												type="submit">
											Study
										</button>
									</form>
								<?php endif; ?>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else: ?>
			<div class="rounded-2xl border border-slate-200 bg-slate-100 px-5 py-5 text-sm leading-6 text-slate-700">
				You can study these sets as a guest right away. Create an account or log in to unlock smart sets based
				on your progress and keep your history.
			</div>
		<?php endif; ?>

		<div>
			<p class="text-sm font-semibold uppercase tracking-[0.24em] text-slate-500">Sets</p>
		</div>

		<div class="rounded-3xl border border-slate-300 bg-slate-100 px-5 py-6 shadow-sm sm:px-8">
			<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
				<div>
					<h2 class="text-xl font-semibold text-slate-950">All</h2>
					<p class="mt-2 text-sm text-slate-600">Random cards from every published set in this language.</p>
                    <p class="mt-4 text-xs font-medium uppercase tracking-[0.2em] text-slate-500">
                        <?= e((string) ($language['card_count'] ?? 0)) ?> cards
                    </p>
				</div>
				<form
						method="post"
						action="/languages/<?= e((string)$language['id']) ?>/start-all">
					<?= Csrf::input() ?>
					<button
							class="btn-secondary justify-center sm:w-auto"
							type="submit">Study
					</button>
				</form>
			</div>
		</div>

		<div class="grid items-stretch gap-4 sm:grid-cols-2 md:grid-cols-4">
			<?php foreach ($sets as $set): ?>
				<div class="panel h-full px-5 py-6">
					<div class="flex h-full flex-col justify-between gap-4">
						<div>
							<h2 class="text-xl font-semibold text-slate-950"><?= e($set['name']) ?></h2>
							<p class="mt-2 text-sm text-slate-600"><?= e($set['description'] !== '' ? $set['description'] : 'No description yet.') ?></p>
						</div>
						<div class="mt-auto flex items-end justify-between gap-3">
							<p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500"><?= e((string)$set['card_count']) ?>
								cards</p>
							<a
									class="btn-secondary justify-center"
									href="/sets/<?= e((string)$set['id']) ?>">Study</a>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<script>
	(() => {
		const row = document.getElementById('smart-set-row');
		if (!row) return;

		let isDragging = false;
		let dragStarted = false;
		let startX = 0;
		let startScrollLeft = 0;
		let activePointerId = null;

		const beginDrag = (clientX, pointerId = null) => {
			isDragging = true;
			dragStarted = false;
			startX = clientX;
			startScrollLeft = row.scrollLeft;
			activePointerId = pointerId;
			row.classList.remove('cursor-grab');
			row.classList.add('cursor-grabbing');
		};

		const moveDrag = (clientX) => {
			if (!isDragging) return;
			const delta = clientX - startX;
			if (Math.abs(delta) > 4) {
				dragStarted = true;
			}
			row.scrollLeft = startScrollLeft - delta;
		};

		const endDrag = () => {
			if (!isDragging) return;
			isDragging = false;
			activePointerId = null;
			row.classList.remove('cursor-grabbing');
			row.classList.add('cursor-grab');
			requestAnimationFrame(() => {
				dragStarted = false;
			});
		};

		row.addEventListener('pointerdown', (event) => {
			if (event.pointerType === 'mouse' && event.button !== 0) return;
			beginDrag(event.clientX, event.pointerId);
		});

		window.addEventListener('pointermove', (event) => {
			if (!isDragging || (activePointerId !== null && event.pointerId !== activePointerId)) {
				return;
			}
			event.preventDefault();
			moveDrag(event.clientX);
		}, {passive: false});

		window.addEventListener('pointerup', (event) => {
			if (activePointerId !== null && event.pointerId !== activePointerId) {
				return;
			}
			endDrag();
		});

		window.addEventListener('pointercancel', endDrag);

		row.addEventListener('click', (event) => {
			if (dragStarted) {
				event.preventDefault();
				event.stopPropagation();
			}
		}, true);
	})();
</script>
