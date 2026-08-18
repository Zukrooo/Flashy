<?php

use App\Security\Csrf;
use App\Support\Flash;

$flash = Flash::pull();
$userEmail = $_SESSION['user_email'] ?? null;
$userFirstName = trim((string)($_SESSION['user_first_name'] ?? ''));
$userLastName = trim((string)($_SESSION['user_last_name'] ?? ''));
$userIsAdmin = (bool)($_SESSION['user_is_admin'] ?? false);
$pageTitle = trim((string)($title ?? 'Flashy'));
$languageSwitcher = is_array($language_switcher ?? null) ? $language_switcher : [];
$switcherLanguages = is_array($languageSwitcher['languages'] ?? null) ? $languageSwitcher['languages'] : [];
$selectedLanguageId = (int) ($languageSwitcher['selected_language_id'] ?? ($_SESSION['selected_language_id'] ?? 0));
$switcherMode = (string) ($languageSwitcher['mode'] ?? 'study');
$studyHref = $selectedLanguageId > 0 ? '/languages/' . $selectedLanguageId : '/';
$practiceHref = $selectedLanguageId > 0 ? '/practice/languages/' . $selectedLanguageId : '/';

if ($pageTitle === '') {
	$pageTitle = 'Flashy';
}

if (!str_ends_with($pageTitle, '- Flashy')) {
	$pageTitle = $pageTitle === 'Flashy' ? 'Flashy' : $pageTitle . ' - Flashy';
}

header('X-Robots-Tag: noindex, nofollow, noarchive', true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta
			name="viewport"
			content="width=device-width, initial-scale=1.0">
	<title><?= e($pageTitle) ?></title>
	<meta
			name="robots"
			content="noindex, nofollow, noarchive">
	<meta
			name="googlebot"
			content="noindex, nofollow, noarchive">
	<link
			rel="icon"
			type="image/svg+xml"
			href="/public/favicon.svg">
	<link
			rel="preconnect"
			href="https://fonts.googleapis.com">
	<link
			rel="preconnect"
			href="https://fonts.gstatic.com"
			crossorigin>
	<link
			href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap"
			rel="stylesheet">
	<link
			rel="stylesheet"
			href="/public/assets/app.css">
</head>
<body class="min-h-screen">
<div class="mx-auto flex min-h-screen w-full max-w-6xl flex-col px-4 pb-6 pt-0 sm:px-6 sm:pb-8 sm:pt-0 lg:px-8">
	<style>
		.profile-menu {
			position: relative;
			display: inline-flex;
			padding-bottom: 0.5rem;
			margin-bottom: -0.5rem;
			margin-left: 0.7rem;
		}

		.profile-greeting {
			display: inline-flex;
			align-items: center;
			gap: 0.45rem;
			padding: 0.7rem 0;
			font-size: 0.95rem;
			font-weight: 600;
			color: #0f172a;
			cursor: pointer;
		}

		.profile-greeting-chevron {
			font-size: 0.8rem;
			color: #475569;
		}

		.language-switcher {
			display: inline-flex;
			align-items: center;
			gap: 0.75rem;
		}

		.language-switcher-label {
			font-size: 0.9rem;
			font-weight: 600;
			color: #475569;
		}

		.language-switcher-field {
			position: relative;
			display: inline-flex;
			align-items: center;
		}

		.language-switcher-select {
			appearance: none;
			-webkit-appearance: none;
			-moz-appearance: none;
			cursor: pointer;
			border-radius: 1rem;
			border: 0;
			background: #fff;
			padding: 0.625rem 2.2rem 0.625rem 1rem;
			min-height: 2.5rem;
			font-family: "Instrument Sans", "Segoe UI", sans-serif;
			font-size: 0.875rem;
			font-weight: 600;
			letter-spacing: 0;
			color: #334155;
			line-height: 1.25rem;
			box-shadow: 0 0 0 1px #e2e8f0;
			outline: none;
			transition: background-color 160ms ease, border-color 160ms ease, color 160ms ease, box-shadow 160ms ease;
		}

		.language-switcher-select:hover {
			background: #f8fafc;
		}

		.language-switcher-select:focus {
			background: #fff;
			box-shadow: 0 0 0 1px #64748b, 0 0 0 3px rgba(148, 163, 184, 0.18);
		}

		.language-switcher-chevron {
			position: absolute;
			right: 0.8rem;
			pointer-events: none;
			color: #475569;
		}

		.nav-divider {
			height: 2rem;
			width: 1px;
			background: rgba(148, 163, 184, 0.45);
			margin: 0 0.15rem 0 0.35rem;
		}

		.profile-dropdown {
			position: absolute;
			right: 0;
			top: calc(100% - 0.25rem);
			min-width: 12rem;
			z-index: 30;
			opacity: 0;
			visibility: hidden;
			pointer-events: none;
			transform: translateY(0.35rem);
			transition: opacity 160ms ease, transform 160ms ease, visibility 160ms ease;
		}

		.profile-menu:hover .profile-dropdown,
		.profile-menu:focus-within .profile-dropdown {
			opacity: 1;
			visibility: visible;
			pointer-events: auto;
			transform: translateY(0);
		}

		.profile-dropdown-card {
			border: 1px solid rgba(203, 213, 225, 0.9);
			background: rgba(255, 255, 255, 0.98);
			box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
			backdrop-filter: blur(12px);
		}

		.profile-dropdown-link {
			display: flex;
			width: 100%;
			align-items: center;
			justify-content: space-between;
			border-radius: 1rem;
			padding: 0.75rem 0.9rem;
			font-size: 0.95rem;
			font-weight: 600;
			color: #0f172a;
			transition: background-color 160ms ease, color 160ms ease;
			cursor: pointer;
		}

		.profile-dropdown-link:hover,
		.profile-dropdown-link:focus-visible {
			background: #e2e8f0;
			color: #020617;
			outline: none;
		}

		.profile-dropdown-link--danger {
			color: #be123c;
		}

		.profile-dropdown-link--danger:hover,
		.profile-dropdown-link--danger:focus-visible {
			background: #ffe4e6;
			color: #9f1239;
		}

		.mobile-nav-panel {
			position: fixed;
			inset: 0;
			z-index: 50;
			display: flex;
			min-height: 100vh;
			flex-direction: column;
			background: rgba(248, 250, 252, 0.98);
			backdrop-filter: blur(14px);
			opacity: 0;
			visibility: hidden;
			pointer-events: none;
			transition: opacity 180ms ease, visibility 180ms ease;
		}

		.mobile-nav-panel[data-open="true"] {
			opacity: 1;
			visibility: visible;
			pointer-events: auto;
		}

        .mobile-nav-inner {
            min-height: 100dvh;
            padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
        }

        .mobile-nav-actions {
            padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
        }
	</style>
	<?php if (!($hide_nav ?? false)): ?>
		<header class="sticky top-0 z-40 -mx-4 mb-4 flex flex-col gap-4 border-b border-slate-300 bg-slate-50/95 px-4 py-4 backdrop-blur sm:static sm:mx-0 sm:mb-10 sm:flex-row sm:items-center sm:justify-between sm:bg-transparent sm:px-0 sm:py-4 sm:backdrop-blur-0">
			<div class="flex items-center justify-between gap-4">
				<a
						href="/"
						class="inline-flex items-center gap-3 text-slate-900">
					<span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-accent text-lg font-bold text-white shadow-lg shadow-slate-950/15">F</span>
					<span>
                    <span class="block text-lg font-semibold">Flashy</span>
                </span>
				</a>
				<div class="flex items-center gap-3 sm:hidden">
					<?php if ($userEmail !== null): ?>
						<span class="text-sm font-semibold text-slate-700">
                        <?= e($userFirstName !== '' ? 'Hi, ' . $userFirstName : (string)$userEmail) ?>
                    </span>
					<?php endif; ?>
					<button
							type="button"
							id="mobile-nav-open"
							class="btn-secondary cursor-pointer">
						Menu
					</button>
				</div>
			</div>
			<nav class="hidden gap-3 sm:flex sm:items-center">
				<?php if ($switcherLanguages !== []): ?>
					<form
							method="post"
							action="/language-switch"
							class="language-switcher">
						<?= Csrf::input() ?>
						<input
								type="hidden"
								name="mode"
								value="<?= e($switcherMode) ?>">
						<label
								for="desktop-language-switcher"
								class="language-switcher-label">Choose language:</label>
						<div class="language-switcher-field">
							<select
									id="desktop-language-switcher"
									name="language_id"
									onchange="this.form.submit()"
									class="language-switcher-select">
								<?php foreach ($switcherLanguages as $languageOption): ?>
									<option
											value="<?= e((string) $languageOption['id']) ?>"
										<?= (int) $languageOption['id'] === $selectedLanguageId ? ' selected' : '' ?>>
										<?= e($languageOption['name']) ?>
									</option>
								<?php endforeach; ?>
							</select>
							<span
									class="language-switcher-chevron"
									aria-hidden="true">
								<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M3 5.25L7 9.25L11 5.25" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</span>
						</div>
					</form>
					<div
							class="nav-divider"
							aria-hidden="true"></div>
				<?php endif; ?>
				<a
						class="btn-secondary w-full cursor-pointer sm:w-auto"
						href="<?= e($practiceHref) ?>">Practice</a>
				<a
						class="btn-secondary w-full cursor-pointer sm:w-auto"
						href="<?= e($studyHref) ?>">Study</a>
				<?php if ($userEmail !== null): ?>
					<div class="profile-menu">
						<div class="profile-greeting">
							<?= e($userFirstName !== '' ? 'Hi, ' . $userFirstName : (string)$userEmail) ?>
							<span
									class="profile-greeting-chevron"
									aria-hidden="true">
								<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path d="M3 5.25L7 9.25L11 5.25" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
								</svg>
							</span>
						</div>
						<div class="profile-dropdown profile-dropdown-card overflow-hidden rounded-2xl">
							<div class="grid gap-1 p-2">
								<?php if (isset($_SESSION['admin_user_id']) || $userIsAdmin): ?>
									<a
											class="profile-dropdown-link"
											href="/admin">
										<span>Admin</span>
										<span aria-hidden="true">⚙</span>
									</a>
								<?php endif; ?>
								<a
										class="profile-dropdown-link"
										href="/profile">
									<span>Edit profile</span>
									<span aria-hidden="true">
										<svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
											<path d="M9 9C10.7949 9 12.25 7.54493 12.25 5.75C12.25 3.95507 10.7949 2.5 9 2.5C7.20507 2.5 5.75 3.95507 5.75 5.75C5.75 7.54493 7.20507 9 9 9Z" stroke="currentColor" stroke-width="1.5"/>
											<path d="M3.75 14.75C4.55 12.8306 6.51683 11.5 9 11.5C11.4832 11.5 13.45 12.8306 14.25 14.75" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
										</svg>
									</span>
								</a>
								<form
										method="post"
										action="/logout">
									<?= Csrf::input() ?>
									<button
											class="profile-dropdown-link profile-dropdown-link--danger"
											type="submit">
										<span>Log out</span>
										<span aria-hidden="true">⇥</span>
									</button>
								</form>
							</div>
						</div>
					</div>
				<?php else: ?>
					<a
							class="btn-secondary w-full cursor-pointer sm:w-auto"
							href="/login">Log in</a>
					<a
							class="btn-primary w-full cursor-pointer sm:w-auto"
							href="/register">Create account</a>
				<?php endif; ?>
			</nav>
		</header>
			<div
				id="mobile-nav-panel"
				class="mobile-nav-panel sm:hidden"
				data-open="false"
				aria-hidden="true">
			<div class="mobile-nav-inner mx-auto flex w-full max-w-6xl flex-col px-4 py-4">
				<div class="flex items-center justify-between gap-4 border-b border-slate-300 pb-4">
					<a
							href="/"
							class="inline-flex items-center gap-3 text-slate-900">
						<span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-accent text-lg font-bold text-white shadow-lg shadow-slate-950/15">F</span>
						<span class="block text-lg font-semibold">Flashy</span>
					</a>
					<button
							type="button"
							id="mobile-nav-close"
							class="btn-secondary cursor-pointer">
						Close
					</button>
				</div>
				<div class="flex flex-1 flex-col pt-8">
					<div class="grid gap-3">
						<?php if ($switcherLanguages !== []): ?>
							<form
									method="post"
									action="/language-switch"
									class="grid gap-2">
								<?= Csrf::input() ?>
								<input
										type="hidden"
										name="mode"
										value="<?= e($switcherMode) ?>">
								<label
										for="mobile-language-switcher"
										class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-600">Choose Language:</label>
								<div class="language-switcher-field">
									<select
											id="mobile-language-switcher"
											name="language_id"
											onchange="this.form.submit()"
											class="language-switcher-select w-full">
										<?php foreach ($switcherLanguages as $languageOption): ?>
											<option
													value="<?= e((string) $languageOption['id']) ?>"
												<?= (int) $languageOption['id'] === $selectedLanguageId ? ' selected' : '' ?>>
												<?= e($languageOption['name']) ?>
											</option>
										<?php endforeach; ?>
									</select>
									<span
											class="language-switcher-chevron"
											aria-hidden="true">
										<svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
											<path d="M3 5.25L7 9.25L11 5.25" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</span>
								</div>
							</form>
						<?php endif; ?>
						<a
								class="btn-secondary w-full cursor-pointer justify-center"
								href="<?= e($practiceHref) ?>">Practice</a>
						<a
								class="btn-secondary w-full cursor-pointer justify-center"
								href="<?= e($studyHref) ?>">Study</a>
					</div>
					<div class="mobile-nav-actions mt-auto border-t border-slate-300 pt-6">
						<div class="grid gap-3">
							<?php if ($userEmail !== null): ?>
								<?php if (isset($_SESSION['admin_user_id']) || $userIsAdmin): ?>
									<a
											class="btn-secondary w-full cursor-pointer justify-center"
											href="/admin">Admin</a>
								<?php endif; ?>
								<a
										class="btn-secondary w-full cursor-pointer justify-center"
										href="/profile">Edit profile</a>
								<form
										method="post"
										action="/logout">
									<?= Csrf::input() ?>
									<button
											class="btn-danger w-full cursor-pointer justify-center"
											type="submit">Log out
									</button>
								</form>
							<?php else: ?>
								<a
										class="btn-secondary w-full cursor-pointer justify-center"
										href="/login">Log in</a>
								<a
										class="btn-primary w-full cursor-pointer justify-center"
										href="/register">Create account</a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>
	
	<?php if ($flash !== null): ?>
		<?php
		$flashClasses = match ($flash['type']) {
			'error' => 'border-rose-200 bg-rose-50 text-rose-900',
			'success' => 'border-green-200 bg-green-50 text-green-900',
			default => 'border-slate-200 bg-slate-100 text-slate-900',
		};
		?>
		<div class="mb-6 rounded-2xl border px-4 py-3 text-sm <?= $flashClasses ?>">
			<?= e($flash['message']) ?>
		</div>
	<?php endif; ?>

	<main class="flex-1">
		<?php require $viewPath; ?>
	</main>
</div>
<?php if (!($hide_nav ?? false)): ?>
	<script>
		(() => {
			const panel = document.getElementById('mobile-nav-panel');
			const openButton = document.getElementById('mobile-nav-open');
			const closeButton = document.getElementById('mobile-nav-close');
			if (!panel || !openButton || !closeButton) return;

			const setOpen = (open) => {
				panel.setAttribute('data-open', open ? 'true' : 'false');
				panel.setAttribute('aria-hidden', open ? 'false' : 'true');
				document.body.style.overflow = open ? 'hidden' : '';
			};

			openButton.addEventListener('click', () => setOpen(true));
			closeButton.addEventListener('click', () => setOpen(false));
			panel.addEventListener('click', (event) => {
				if (event.target === panel) {
					setOpen(false);
				}
			});
			window.addEventListener('keydown', (event) => {
				if (event.key === 'Escape') {
					setOpen(false);
				}
			});
		})();
	</script>
<?php endif; ?>
</body>
</html>
