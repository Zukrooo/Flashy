<?php

use App\Security\Csrf;

$history = $summary['results'] ?? [];
$currentMode = $context['mode'] ?? 'bilingual';
$modeOptions = [
	'bilingual' => 'Bilingual',
	'to_english' => 'To English',
	'to_language' => 'To ' . $context['subtitle'],
];
$showTranslationModes = $context['show_translation_modes'] ?? true;
$headerPills = $context['pills'] ?? [$context['subtitle'], $context['name']];
$settingsTitle = (string) ($context['settings_title'] ?? 'Study Settings');
$showStudyModeSettings = (bool) ($context['show_study_mode'] ?? true);
$isPractice = (bool) ($context['is_practice'] ?? false);
$currentStudyMode = $context['study_mode'] ?? 'infinite';
$studyModeOptions = [
    'infinite' => 'Infinite',
    'finite' => 'Finite',
];
$currentWrongMode = $context['wrong_mode'] ?? 'stay';
$wrongModeOptions = [
    'stay' => 'Stay Until Correct',
    'advance' => 'Move On',
];
$formatSeconds = static function (?int $seconds): string {
    if ($seconds === null || $seconds < 0) {
        return '00:00:00';
    }

    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    $remainingSeconds = $seconds % 60;

    return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
};
$isFiniteStudy = $currentStudyMode === 'finite';
$elapsedSeconds = (int) ($summary['elapsed_seconds'] ?? 0);
$bestFiniteTimeSeconds = (int) ($context['best_finite_time_seconds'] ?? 0);
$summaryScore = (int) ($summary['score'] ?? 0);
$summaryTotal = (int) ($summary['total'] ?? 0);
$summaryCardTotal = (int) ($summary['card_total'] ?? 0);
$summaryLabel = $currentStudyMode === 'finite'
    ? sprintf('%d Correct / %d Words (%d Guesses)', $summaryScore, $summaryCardTotal, $summaryTotal)
    : sprintf('%d Correct in %d Guesses', $summaryScore, $summaryTotal);
?>
<section class="mx-auto max-w-6xl pb-40">
	<div class="mb-8 flex flex-col gap-1">
        <div class="flex flex-col gap-4 sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500"><?= e($context['scope_label'] ?? 'Study set') ?></p>
                <h1 class="mt-2 text-3xl font-semibold text-slate-950"><?= e($context['name']) ?></h1>
                <div class="mt-3 flex flex-wrap gap-2">
                    <?php foreach ($headerPills as $pill): ?>
                        <span class="inline-flex items-center rounded-full border border-slate-300 bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-700">
                            <?= e($pill) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
                <?php if (!empty($context['scope_detail'])): ?>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600"><?= e($context['scope_detail']) ?></p>
                <?php endif; ?>
            </div>
        </div>
	</div>

	<style>
        .study-settings-modal {
            position: fixed;
            inset: 0;
            z-index: 45;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(2, 6, 23, 0.58);
            padding: 1rem;
        }

        .study-settings-modal[data-open="true"] {
            display: flex;
        }

        .study-action-button {
            position: relative;
        }

        .study-action-button.is-loading {
            color: transparent;
        }

        .study-action-button.is-loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 1rem;
            height: 1rem;
            margin-top: -0.5rem;
            margin-left: -0.5rem;
            border: 2px solid #fff;
            border-right-color: transparent;
            border-radius: 9999px;
            animation: study-button-spin 0.7s linear infinite;
        }

        .study-action-button.btn-secondary.is-loading::after {
            border-color: #0f172a;
            border-right-color: transparent;
        }

        @keyframes study-button-spin {
            to {
                transform: rotate(360deg);
            }
        }

		.study-card-stack {
			display: grid;
			overflow: visible;
		}

		.study-card {
			grid-area: 1 / 1;
			transition: transform 420ms cubic-bezier(0.22, 1, 0.36, 1), background-color 160ms ease, box-shadow 160ms ease, opacity 220ms ease;
			will-change: transform, background-color, box-shadow, opacity;
            position: relative;
            min-height: 15.5rem;
            perspective: 1600px;
            overflow: hidden;
            border-radius: 2rem;
		}

        .study-card-inner {
            position: relative;
            min-height: inherit;
            transform-style: preserve-3d;
            transition: transform 560ms cubic-bezier(0.22, 1, 0.36, 1);
        }

        .study-card-face {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 2rem;
            padding: 2.5rem 1.5rem;
            text-align: center;
            backface-visibility: hidden;
            -webkit-backface-visibility: hidden;
        }

        .study-card--theme-primary .study-card-face--prompt {
            background: #020617;
            color: #fff;
            box-shadow: 0 24px 48px rgba(2, 6, 23, 0.15);
        }

        .study-card--theme-secondary .study-card-face--prompt {
            background: #1e293b;
            color: #fff;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.16);
        }

        .study-card-face--answer {
            background: #9f1239;
            color: #fff;
            box-shadow: 0 24px 48px rgba(159, 18, 57, 0.28);
            transform: rotateX(180deg);
        }

        .study-card.is-answer-revealed .study-card-inner {
            transform: rotateX(180deg);
        }

		.study-card--back {
			opacity: 0.92;
			transform: scale(0.96) translateY(10px);
			z-index: 1;
		}

		.study-card--front {
			z-index: 2;
		}

		.study-card.is-correct {
			animation: study-correct-fly 520ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
		}

		.study-card.is-skipped {
			animation: study-skip-fly 520ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
		}

        .study-card.is-wrong-advance {
            animation: study-skip-fly 520ms cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }

		.study-card.is-wrong {
			animation: study-incorrect-shake 760ms ease;
		}

        .study-card.is-wrong .study-card-face--prompt {
            animation: study-incorrect-face 760ms ease;
		}

		.study-card.is-hidden {
			opacity: 0;
		}

		.study-card--back.is-revealed {
			opacity: 1;
			transform: scale(1) translateY(0);
			transition: transform 240ms ease, opacity 240ms ease;
		}

		@keyframes study-correct-fly {
			0% {
				transform: translateX(0) rotate(0deg);
				opacity: 1;
				background: #020617;
				box-shadow: 0 24px 48px rgba(2, 6, 23, 0.15);
			}
			20% {
				background: #166534;
				box-shadow: 0 30px 60px rgba(22, 101, 52, 0.35);
			}
			100% {
				transform: translateX(calc(100vw + 18rem)) rotate(8deg);
				opacity: 1;
				background: #166534;
				box-shadow: 0 30px 60px rgba(22, 101, 52, 0.35);
			}
		}

		@keyframes study-incorrect-shake {
			0% {
				transform: translateX(0);
			}
			12% {
				transform: translateX(-14px);
			}
			24% {
				transform: translateX(12px);
			}
			36% {
				transform: translateX(-9px);
			}
			48% {
				transform: translateX(7px);
			}
			60% {
				transform: translateX(0);
			}
			100% {
				transform: translateX(0);
			}
		}

        @keyframes study-incorrect-face {
            0% {
				background: #020617;
				box-shadow: 0 24px 48px rgba(2, 6, 23, 0.15);
            }
            12%,
            24%,
            36%,
            48%,
            60% {
                background: #9f1239;
                box-shadow: 0 24px 48px rgba(159, 18, 57, 0.32);
            }
            100% {
                background: #020617;
                box-shadow: 0 24px 48px rgba(2, 6, 23, 0.15);
            }
        }

		@keyframes study-skip-fly {
			0% {
				transform: translateX(0) rotate(0deg);
				opacity: 1;
				background: #020617;
				box-shadow: 0 24px 48px rgba(2, 6, 23, 0.15);
			}
			20% {
				background: #9f1239;
				box-shadow: 0 30px 60px rgba(159, 18, 57, 0.32);
			}
			100% {
				transform: translateX(calc(-100vw - 18rem)) rotate(-8deg);
				opacity: 1;
				background: #9f1239;
				box-shadow: 0 30px 60px rgba(159, 18, 57, 0.32);
			}
		}
	</style>

	<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem] xl:grid-cols-[minmax(0,1fr)_20rem]">
		<div class="order-2 space-y-2 lg:order-1 lg:sticky lg:top-8 lg:self-start lg:space-y-4">
            <div class="panel px-5 py-6 sm:px-8 sm:py-8">
            <?php if ($isFiniteStudy || $isPractice): ?>
                <div class="mb-6 text-center">
                    <?php if ($isFiniteStudy): ?>
                        <p id="study-elapsed-timer" class="text-3xl font-semibold tabular-nums text-slate-950 sm:text-4xl"><?= e($formatSeconds($elapsedSeconds)) ?></p>
                        <?php if ($bestFiniteTimeSeconds > 0): ?>
                            <p class="mt-2 text-sm text-slate-600">Best <?= e($formatSeconds($bestFiniteTimeSeconds)) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if (!$isPractice && !empty($context['restart_path'])): ?>
                        <form method="post" action="<?= e($context['restart_path']) ?>" class="mt-2">
                            <?= Csrf::input() ?>
                            <input type="hidden" name="mode" value="<?= e($currentMode) ?>">
                            <input type="hidden" name="study_mode" value="<?= e($currentStudyMode) ?>">
                            <input type="hidden" name="wrong_mode" value="<?= e($currentWrongMode) ?>">
                            <button
                                    type="submit"
                                    class="cursor-pointer text-xs font-medium text-slate-500 underline decoration-slate-300 underline-offset-4 transition hover:text-slate-700 hover:decoration-slate-500">
                                Restart session
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
			<div class="study-card-stack">
				<div
						id="study-card-back"
						class="study-card study-card--back study-card--theme-secondary">
                    <div class="study-card-inner">
                        <div class="study-card-face study-card-face--prompt">
                            <p
                                    class="study-card-word-text text-4xl font-bold sm:text-5xl lg:text-6xl"><?= e($card['prompt']) ?></p>
                            <p
                                    class="study-card-language-text mt-4 text-sm uppercase tracking-[0.24em] text-slate-300">
                                <?= e($card['prompt_language'] === 'English' ? 'English' : $context['subtitle']) ?>
                            </p>
                        </div>
                        <div class="study-card-face study-card-face--answer">
                            <p
                                    class="study-card-answer-word-text text-4xl font-bold sm:text-5xl lg:text-6xl"><?= e($card['expected']) ?></p>
                            <p
                                    class="study-card-answer-language-text mt-4 text-sm uppercase tracking-[0.24em] text-rose-100">
                                <?= e($card['answer_language'] === 'English' ? 'English' : $context['subtitle']) ?>
                            </p>
                        </div>
                    </div>
				</div>

				<div
						id="study-card"
						class="study-card study-card--front study-card--theme-primary">
                    <div class="study-card-inner">
                        <div class="study-card-face study-card-face--prompt">
                            <p
                                    class="study-card-word-text text-4xl font-bold sm:text-5xl lg:text-6xl"><?= e($card['prompt']) ?></p>
                            <p
                                    class="study-card-language-text mt-4 text-sm uppercase tracking-[0.24em] text-slate-300">
                                <?= e($card['prompt_language'] === 'English' ? 'English' : $context['subtitle']) ?>
                            </p>
                        </div>
                        <div class="study-card-face study-card-face--answer">
                            <p
                                    class="study-card-answer-word-text text-4xl font-bold sm:text-5xl lg:text-6xl"><?= e($card['expected']) ?></p>
                            <p
                                    class="study-card-answer-language-text mt-4 text-sm uppercase tracking-[0.24em] text-rose-100">
                                <?= e($card['answer_language'] === 'English' ? 'English' : $context['subtitle']) ?>
                            </p>
                        </div>
                    </div>
				</div>
			</div>

			<form
					id="study-form"
					method="post"
					action="<?= e($context['answer_path']) ?>"
					class="mt-6 space-y-5 sm:mt-8"
					data-language-name="<?= e($context['subtitle']) ?>"
					data-skip-path="<?= e(str_replace('/answer', '/skip', $context['answer_path'])) ?>"
                    data-reset-path="<?= e($context['reset_path'] ?? '') ?>"
                    data-restart-path="<?= e($context['restart_path'] ?? '') ?>"
                    data-finish-path="<?= e($context['finish_path'] ?? ($context['back_path'] ?? '/')) ?>"
                    data-study-mode="<?= e($currentStudyMode) ?>"
                    data-translation-mode="<?= e($currentMode) ?>"
                    data-wrong-mode="<?= e($currentWrongMode) ?>">
				<?= Csrf::input() ?>
				<div class="field">
					<input
							id="answer"
							name="answer"
							type="text"
							required
							autocomplete="off"
							autofocus
							placeholder="Your answer...">
				</div>
				<p class="text-xs text-slate-500">
					Answers are matched in lowercase.
				</p>
				<div class="grid grid-cols-3 gap-3 sm:flex">
					<button
							data-study-action-button="answer"
                            class="study-action-button btn-primary w-full justify-center"
							type="submit">Check
					</button>
					<button
							id="skip-answer"
							data-study-action-button="skip"
							class="study-action-button btn-secondary w-full justify-center"
							type="button">Skip
					</button>
                    <button
                            id="reveal-answer"
                            data-study-action-button="reveal"
                            class="study-action-button w-full justify-center rounded-2xl bg-rose-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-900 cursor-pointer"
                            type="button">Reveal
					</button>
				</div>
			</form>
            </div>
            <div class="lg:hidden">
                <button
                        type="button"
                        id="study-settings-open"
                        class="flex w-full items-center justify-between border border-slate-800 bg-slate-800 px-4 py-3 text-left text-sm font-semibold uppercase tracking-[0.18em] text-slate-100 shadow-sm">
                    <span><?= e($settingsTitle) ?></span>
                    <span aria-hidden="true">⚙</span>
                </button>
            </div>
		</div>

		<aside class="order-1 hidden panel px-5 py-6 sm:px-8 sm:py-8 lg:order-2 lg:sticky lg:top-8 lg:block lg:self-start">
            <?php if ($showTranslationModes): ?>
                <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">Translation</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    Switch between mixed prompts or one-way translation while you study.
                </p>

                <div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-1">
                    <?php foreach ($modeOptions as $modeValue => $modeLabel): ?>
                        <?php $isActive = $currentMode === $modeValue; ?>
                        <form
                                method="post"
                                action="<?= e($context['mode_path']) ?>">
                            <?= Csrf::input() ?>
                            <input
                                    type="hidden"
                                    name="mode"
                                    value="<?= e($modeValue) ?>">
                            <button
                                    class="<?= $isActive ? 'btn-primary' : 'btn-secondary' ?> w-full justify-center"
                                    type="submit"
                            >
                                <?= e($modeLabel) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($showStudyModeSettings): ?>
            <div class="<?= $showTranslationModes ? 'mt-8 border-t border-slate-200 pt-8' : '' ?>">
                <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">Study Mode</h2>
				<p class="mt-3 text-sm leading-6 text-slate-600">
					Infinite keeps cycling through cards. Finite stops when you have made an answer for all cards in a
					set.
				</p>

				<div class="mt-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-1">
					<?php foreach ($studyModeOptions as $studyModeValue => $studyModeLabel): ?>
						<?php $isActive = $currentStudyMode === $studyModeValue; ?>
						<form
								method="post"
								action="<?= e($context['study_mode_path']) ?>">
							<?= Csrf::input() ?>
							<input
									type="hidden"
									name="study_mode"
									value="<?= e($studyModeValue) ?>">
							<button
									class="<?= $isActive ? 'btn-primary' : 'btn-secondary' ?> w-full justify-center"
									type="submit"
							>
								<?= e($studyModeLabel) ?>
							</button>
						</form>
					<?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="<?= ($showTranslationModes || $showStudyModeSettings) ? 'mt-8 border-t border-slate-200 pt-8' : '' ?>">
                <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">Incorrect Answers</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    Choose whether a wrong answer must be corrected first or can move on to the next card.
                </p>

                <div class="mt-6 grid grid-cols-1 gap-3 lg:grid-cols-1">
                    <?php foreach ($wrongModeOptions as $wrongModeValue => $wrongModeLabel): ?>
                        <?php $isActive = $currentWrongMode === $wrongModeValue; ?>
                        <form
                                method="post"
                                action="<?= e($context['wrong_mode_path']) ?>">
                            <?= Csrf::input() ?>
                            <input
                                    type="hidden"
                                    name="wrong_mode"
                                    value="<?= e($wrongModeValue) ?>">
                            <button
                                    class="<?= $isActive ? 'btn-primary' : 'btn-secondary' ?> w-full justify-center"
                                    type="submit"
                            >
                                <?= e($wrongModeLabel) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>

		</aside>
	</div>
</section>

<div
        id="study-settings-modal"
        class="study-settings-modal lg:hidden"
        data-open="false"
        aria-hidden="true">
    <div class="panel w-full max-w-lg px-5 py-6 sm:px-8 sm:py-8">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700"><?= e($settingsTitle) ?></p>
                <p class="mt-2 text-sm text-slate-600">Adjust translation and study behavior for this session.</p>
            </div>
            <button
                    type="button"
                    id="study-settings-close"
                    class="btn-secondary justify-center">
                Close
            </button>
        </div>

        <?php if ($showTranslationModes): ?>
            <div class="mt-6 border-t border-slate-200 pt-6">
                <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">Translation</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">
                    Switch between mixed prompts or one-way translation while you study.
                </p>

                <div class="mt-6 grid grid-cols-1 gap-3">
                    <?php foreach ($modeOptions as $modeValue => $modeLabel): ?>
                        <?php $isActive = $currentMode === $modeValue; ?>
                        <form
                                method="post"
                                action="<?= e($context['mode_path']) ?>">
                            <?= Csrf::input() ?>
                            <input
                                    type="hidden"
                                    name="mode"
                                    value="<?= e($modeValue) ?>">
                            <button
                                    class="<?= $isActive ? 'btn-primary' : 'btn-secondary' ?> w-full justify-center"
                                    type="submit"
                            >
                                <?= e($modeLabel) ?>
                            </button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($showStudyModeSettings): ?>
        <div class="<?= $showTranslationModes ? 'mt-8 border-t border-slate-200 pt-8' : 'mt-6 border-t border-slate-200 pt-6' ?>">
            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">Study Mode</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                Infinite keeps cycling through cards. Finite stops when you have made an answer for all cards in a
                set.
            </p>

            <div class="mt-6 grid grid-cols-1 gap-3">
                <?php foreach ($studyModeOptions as $studyModeValue => $studyModeLabel): ?>
                    <?php $isActive = $currentStudyMode === $studyModeValue; ?>
                    <form
                            method="post"
                            action="<?= e($context['study_mode_path']) ?>">
                        <?= Csrf::input() ?>
                        <input
                                type="hidden"
                                name="study_mode"
                                value="<?= e($studyModeValue) ?>">
                        <button
                                class="<?= $isActive ? 'btn-primary' : 'btn-secondary' ?> w-full justify-center"
                                type="submit"
                        >
                            <?= e($studyModeLabel) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="<?= ($showTranslationModes || $showStudyModeSettings) ? 'mt-8 border-t border-slate-200 pt-8' : 'mt-6' ?>">
            <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">Incorrect Answers</h2>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                Choose whether a wrong answer must be corrected first or can move on to the next card.
            </p>

            <div class="mt-6 grid grid-cols-1 gap-3">
                <?php foreach ($wrongModeOptions as $wrongModeValue => $wrongModeLabel): ?>
                    <?php $isActive = $currentWrongMode === $wrongModeValue; ?>
                    <form
                            method="post"
                            action="<?= e($context['wrong_mode_path']) ?>">
                        <?= Csrf::input() ?>
                        <input
                                type="hidden"
                                name="wrong_mode"
                                value="<?= e($wrongModeValue) ?>">
                        <button
                                class="<?= $isActive ? 'btn-primary' : 'btn-secondary' ?> w-full justify-center"
                                type="submit"
                        >
                            <?= e($wrongModeLabel) ?>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<div
		id="study-complete-modal"
		class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/55 px-4">
	<div class="panel w-full max-w-lg px-5 py-6 sm:px-8 sm:py-8">
		<p class="text-sm font-semibold uppercase tracking-[0.24em] text-accent">Session complete</p>
		<h2 class="mt-3 text-3xl font-semibold text-slate-950"><?= e($context['name']) ?></h2>
		<p
				id="study-complete-message"
				class="mt-3 text-sm leading-6 text-slate-600"></p>
		<p
				id="study-complete-score"
				class="mt-4 text-base text-slate-600"></p>
        <p
                id="study-complete-time"
                class="mt-2 text-sm text-slate-600"></p>

		<div class="mt-8 grid gap-3 sm:flex sm:flex-wrap">
			<form
					id="study-complete-restart"
					method="post"
					action="<?= e($context['restart_path'] ?? '') ?>">
				<?= Csrf::input() ?>
				<input
						type="hidden"
						name="mode"
						value="<?= e($currentMode) ?>">
				<input
						type="hidden"
						name="study_mode"
						value="<?= e($currentStudyMode) ?>">
                <input
                        type="hidden"
                        name="wrong_mode"
                        value="<?= e($currentWrongMode) ?>">
				<button
						class="btn-primary w-full justify-center sm:w-auto"
						type="submit">Study again
				</button>
			</form>
			<a
					id="study-complete-finish"
					class="btn-secondary w-full justify-center sm:w-auto"
					href="<?= e($context['finish_path'] ?? ($context['back_path'] ?? '/')) ?>">Finish</a>
		</div>
	</div>
</div>

<aside class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-300 bg-white/95 backdrop-blur">
	<div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 lg:px-8">
		<style>
			.scroll-rail {
				scrollbar-width: none;
				-ms-overflow-style: none;
			}

			.scroll-rail::-webkit-scrollbar {
				display: none;
			}
		</style>
		<div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
			<h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-700">Session history</h2>
			<p
					class="text-sm text-slate-600"
					data-study-summary>
				<?= e($summaryLabel) ?>
			</p>
		</div>
		
		<?php if ($history === []): ?>
			<div
					id="study-history-empty"
					class="rounded-2xl border border-dashed border-slate-300 px-4 py-4 text-sm text-slate-600">
				No answers yet in this study session.
			</div>
		<?php endif; ?>

		<div
				id="study-history"
				class="scroll-rail flex gap-3 overflow-x-auto overscroll-x-contain pb-2 cursor-grab select-none touch-pan-x">
			<?php foreach ($history as $result): ?>
				<?php
				$rowClasses = $result['correct']
					? 'border-green-200 bg-green-50'
					: 'border-rose-200 bg-rose-50';
				$promptLabel = $result['answer_language'] === 'English' ? $context['subtitle'] : 'English';
				$answerText = !empty($result['skipped']) ? 'Skipped' : $result['answer'];
				?>
				<div class="min-w-52 shrink-0 rounded-2xl border px-3 py-3 <?= $rowClasses ?>">
					<div class="mt-1 text-sm text-slate-700"><?= e($promptLabel) ?>
						: <?= e($result['prompt']) ?></div>
					<div class="mt-1 text-sm text-slate-700">Your answer: <?= e($answerText) ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</aside>

<script>
	(() => {
		const form = document.getElementById('study-form');
		if (!form) return;

		let frontCard = document.getElementById('study-card');
		let backCard = document.getElementById('study-card-back');
		const answerInput = document.getElementById('answer');
        const checkButton = form.querySelector('button[type="submit"]');
		const skipButton = document.getElementById('skip-answer');
        const revealButton = document.getElementById('reveal-answer');
		const history = document.getElementById('study-history');
		const historyEmpty = document.getElementById('study-history-empty');
        const elapsedTimer = document.getElementById('study-elapsed-timer');
		const completeModal = document.getElementById('study-complete-modal');
        const settingsModal = document.getElementById('study-settings-modal');
        const settingsOpenButton = document.getElementById('study-settings-open');
        const settingsCloseButton = document.getElementById('study-settings-close');
		const completeMessage = document.getElementById('study-complete-message');
		const completeScore = document.getElementById('study-complete-score');
        const completeTime = document.getElementById('study-complete-time');
		const restartForm = document.getElementById('study-complete-restart');
		const languageName = form.dataset.languageName;
		const skipPath = form.dataset.skipPath;
		const currentStudyMode = form.dataset.studyMode ?? 'infinite';
		const currentTranslationMode = form.dataset.translationMode ?? 'bilingual';
        const currentWrongMode = form.dataset.wrongMode ?? 'stay';
        let elapsedSeconds = Number(<?= json_encode($elapsedSeconds, JSON_THROW_ON_ERROR) ?>);
        const actionButtons = {
            answer: checkButton,
            skip: skipButton,
            reveal: revealButton,
        };
        let revealTimeoutId = null;
        let elapsedIntervalId = null;

        const formatSummaryLabel = (summary) => {
            if (currentStudyMode === 'finite') {
                return `${summary.score} Correct / ${summary.card_total} Words (${summary.total} Guesses)`;
            }

            return `${summary.score} Correct in ${summary.total} Guesses`;
        };

        const formatElapsed = (seconds) => {
            const safeSeconds = Math.max(0, Number(seconds) || 0);
            const hours = Math.floor(safeSeconds / 3600);
            const minutes = Math.floor((safeSeconds % 3600) / 60);
            const remainingSeconds = safeSeconds % 60;

            return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(remainingSeconds).padStart(2, '0')}`;
        };

        const renderElapsedTimer = () => {
            if (!elapsedTimer || currentStudyMode !== 'finite') {
                return;
            }

            elapsedTimer.textContent = formatElapsed(elapsedSeconds);
        };

        const syncElapsedTimer = (summary) => {
            if (currentStudyMode !== 'finite') {
                return;
            }

            elapsedSeconds = Number(summary.elapsed_seconds ?? elapsedSeconds);
            renderElapsedTimer();
        };

        const startElapsedTimer = () => {
            if (currentStudyMode !== 'finite' || !elapsedTimer) {
                return;
            }

            renderElapsedTimer();
            elapsedIntervalId = window.setInterval(() => {
                elapsedSeconds += 1;
                renderElapsedTimer();
            }, 1000);
        };

        const setActionLoading = (mode, loading) => {
            Object.entries(actionButtons).forEach(([actionMode, button]) => {
                if (!button) {
                    return;
                }

                button.disabled = loading || frontCard.classList.contains('is-answer-revealed');
                button.classList.toggle('is-loading', loading && actionMode === mode);
                button.setAttribute('aria-busy', loading && actionMode === mode ? 'true' : 'false');
            });
        };

        const clearRevealTimer = () => {
            if (revealTimeoutId !== null) {
                window.clearTimeout(revealTimeoutId);
                revealTimeoutId = null;
            }
        };

        const clearActionState = () => {
            clearRevealTimer();
            answerInput.disabled = false;
            setActionLoading('answer', false);
        };

        const resetRevealState = () => {
            frontCard.classList.remove('is-answer-revealed');
            clearActionState();
        };

        const revealCurrentAnswer = () => {
            frontCard.classList.remove('is-correct', 'is-skipped', 'is-wrong', 'is-wrong-advance');
            frontCard.classList.add('is-answer-revealed');
            answerInput.disabled = true;
            setActionLoading('reveal', true);

            revealTimeoutId = window.setTimeout(async () => {
                revealTimeoutId = null;
                await submitStudyAction('skip');
            }, 2000);
        };

        const setSettingsOpen = (open) => {
            if (!settingsModal) return;
            settingsModal.setAttribute('data-open', open ? 'true' : 'false');
            settingsModal.setAttribute('aria-hidden', open ? 'false' : 'true');
            document.body.style.overflow = open ? 'hidden' : '';
        };

        settingsOpenButton?.addEventListener('click', () => setSettingsOpen(true));
        settingsCloseButton?.addEventListener('click', () => setSettingsOpen(false));
        settingsModal?.addEventListener('click', (event) => {
            if (event.target === settingsModal) {
                setSettingsOpen(false);
            }
        });
        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setSettingsOpen(false);
            }
        });

		const escapeHtml = (value) => {
			const div = document.createElement('div');
			div.textContent = value ?? '';
			return div.innerHTML;
		};

		const renderHistoryItem = (result) => {
			const tone = result.correct
				? 'border-green-200 bg-green-50'
				: 'border-rose-200 bg-rose-50';
			const answerText = result.skipped ? 'Skipped' : escapeHtml(result.answer);
			const promptLabel = result.answer_language === 'English' ? languageName : 'English';

			return `<div class="min-w-52 shrink-0 rounded-2xl border px-3 py-3 ${tone}">
            <div class="mt-1 text-sm text-slate-700">${escapeHtml(promptLabel)}: ${escapeHtml(result.prompt)}</div>
            <div class="mt-1 text-sm text-slate-700">Your answer: ${answerText}</div>
        </div>`;
		};

		const setCardContent = (cardEl, studyCard) => {
			const promptLanguageEl = cardEl.querySelector('.study-card-language-text');
			const promptWordEl = cardEl.querySelector('.study-card-word-text');
            const answerLanguageEl = cardEl.querySelector('.study-card-answer-language-text');
            const answerWordEl = cardEl.querySelector('.study-card-answer-word-text');

			if (promptLanguageEl) {
				promptLanguageEl.textContent = studyCard.prompt_language === 'English' ? 'English' : languageName;
			}

			if (promptWordEl) {
				promptWordEl.textContent = studyCard.prompt ?? '';
			}

            if (answerLanguageEl) {
                answerLanguageEl.textContent = studyCard.answer_language === 'English' ? 'English' : languageName;
            }

            if (answerWordEl) {
                answerWordEl.textContent = studyCard.expected ?? '';
            }
		};

		const updateSummary = (summary) => {
			const summaryLine = document.querySelector('[data-study-summary]');
			if (!summaryLine) return;
			summaryLine.textContent = formatSummaryLabel(summary);
		};

		const showCompletionModal = (summary, message) => {
			if (!completeModal || !completeMessage || !completeScore || !completeTime) return;

            if (elapsedIntervalId !== null) {
                window.clearInterval(elapsedIntervalId);
                elapsedIntervalId = null;
            }

			const modeInput = restartForm?.querySelector('input[name="mode"]');
			const studyModeInput = restartForm?.querySelector('input[name="study_mode"]');

			if (modeInput) modeInput.value = currentTranslationMode;
			if (studyModeInput) studyModeInput.value = currentStudyMode;
            const wrongModeInput = restartForm?.querySelector('input[name="wrong_mode"]');
			if (wrongModeInput) wrongModeInput.value = currentWrongMode;

			completeMessage.textContent = message ?? '';
			completeScore.textContent = formatSummaryLabel(summary);
            completeTime.textContent = currentStudyMode === 'finite'
                ? `Time: ${formatElapsed(summary.elapsed_seconds ?? 0)}${summary.best_time_seconds ? ` • Best: ${formatElapsed(summary.best_time_seconds)}` : ''}${summary.is_new_best_time ? ' • New personal best' : ''}`
                : '';
			completeModal.classList.remove('hidden');
			completeModal.classList.add('flex');
		};

		let isDragging = false;
		let dragStarted = false;
		let startX = 0;
		let startScrollLeft = 0;
		let activePointerId = null;

		const beginDrag = (clientX, pointerId = null) => {
			isDragging = true;
			dragStarted = false;
			startX = clientX;
			startScrollLeft = history.scrollLeft;
			activePointerId = pointerId;
			history.classList.remove('cursor-grab');
			history.classList.add('cursor-grabbing');
		};

		const moveDrag = (clientX) => {
			if (!isDragging) return;
			const delta = clientX - startX;
			if (Math.abs(delta) > 4) {
				dragStarted = true;
			}
			history.scrollLeft = startScrollLeft - delta;
		};

		const endDrag = () => {
			if (!isDragging) return;
			isDragging = false;
			activePointerId = null;
			history.classList.remove('cursor-grabbing');
			history.classList.add('cursor-grab');
			requestAnimationFrame(() => {
				dragStarted = false;
			});
		};

		history.addEventListener('pointerdown', (event) => {
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

		history.addEventListener('click', (event) => {
			if (dragStarted) {
				event.preventDefault();
				event.stopPropagation();
			}
		}, true);

		const submitStudyAction = async (mode) => {
            clearRevealTimer();
			const formData = new FormData(form);
            answerInput.disabled = true;
            setActionLoading(mode, true);
            let payloadIsComplete = false;
            const focusAnswerInput = () => {
                if (payloadIsComplete) {
                    return;
                }

                answerInput.focus();
            };

			try {
				const response = await fetch(mode === 'skip' ? skipPath : form.action, {
					method: 'POST',
					headers: {
						'Accept': 'application/json',
						'X-Requested-With': 'XMLHttpRequest',
					},
					body: formData,
				});

				if (!response.ok) {
					if (mode === 'skip') {
						const fallbackForm = document.createElement('form');
						fallbackForm.method = 'post';
						fallbackForm.action = skipPath;
						fallbackForm.innerHTML = form.querySelector('input[name="_token"]').outerHTML;
						document.body.appendChild(fallbackForm);
						fallbackForm.submit();
						return;
					}
					form.submit();
					return;
				}

				const payload = await response.json();
				if (!payload.ok || !payload.result || !payload.summary || (!payload.card && !payload.summary.complete)) {
					form.submit();
					return;
				}

                payloadIsComplete = Boolean(payload.summary.complete);
                frontCard.classList.remove('is-answer-revealed');
                clearActionState();
                focusAnswerInput();

				if (historyEmpty) historyEmpty.remove();
				history.insertAdjacentHTML('afterbegin', renderHistoryItem(payload.result));
                syncElapsedTimer(payload.summary);
				updateSummary(payload.summary);
				history.scrollTo({left: 0, behavior: 'smooth'});

				frontCard.classList.remove('is-correct', 'is-skipped', 'is-wrong', 'is-wrong-advance', 'is-hidden');
				backCard.classList.remove('is-revealed');
				void frontCard.offsetWidth;
				void backCard.offsetWidth;

				if (mode !== 'skip' && !payload.result.correct && currentWrongMode === 'stay') {
					frontCard.classList.add('is-wrong');
					answerInput.value = '';

					setTimeout(() => {
						frontCard.classList.remove('is-wrong');
						focusAnswerInput();
					}, 760);

					return;
				}

				if (!payload.summary.complete && payload.card) {
					setCardContent(backCard, payload.card);
					backCard.classList.add('is-revealed');
				}
				frontCard.classList.add(
                    mode === 'skip'
                        ? 'is-skipped'
                        : (payload.result.correct ? 'is-correct' : 'is-wrong-advance')
                );

				setTimeout(() => {
					answerInput.value = '';

					if (payload.summary.complete) {
						showCompletionModal(payload.summary, payload.completion_message);
						return;
					}

					const outgoingCard = frontCard;
					const incomingCard = backCard;

					outgoingCard.classList.add('is-hidden');
					const previousTransition = outgoingCard.style.transition;
					outgoingCard.style.transition = 'none';
					outgoingCard.classList.remove('is-correct', 'is-skipped', 'is-wrong', 'is-wrong-advance', 'study-card--front');
					outgoingCard.classList.add('study-card--back');
					backCard.classList.remove('is-revealed');
					incomingCard.classList.remove('study-card--back');
					incomingCard.classList.add('study-card--front');
					setCardContent(outgoingCard, payload.card);
					void outgoingCard.offsetWidth;
					outgoingCard.style.transition = previousTransition;
					outgoingCard.classList.remove('is-hidden');

					frontCard = incomingCard;
					backCard = outgoingCard;

                    frontCard.classList.remove('is-answer-revealed');
					focusAnswerInput();
				}, 520);
			} catch (error) {
				form.submit();
			}
		};

		form.addEventListener('submit', async (event) => {
			event.preventDefault();
			await submitStudyAction('answer');
		});

		skipButton?.addEventListener('click', async () => {
			await submitStudyAction('skip');
		});

        revealButton?.addEventListener('click', () => {
            revealCurrentAnswer();
        });

        window.requestAnimationFrame(() => {
            if (!answerInput.disabled) {
                answerInput.focus();
            }
        });

        startElapsedTimer();
	})();
</script>
