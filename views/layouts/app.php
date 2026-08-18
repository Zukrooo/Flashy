<?php

use App\Security\Csrf;
use App\Support\Flash;

$flash = Flash::pull();
$studyHref = isset($_SESSION['user_id']) ? ($_SESSION['last_study_path'] ?? '/') : '/';
$userEmail = $_SESSION['user_email'] ?? null;
$userFirstName = trim((string)($_SESSION['user_first_name'] ?? ''));
$userLastName = trim((string)($_SESSION['user_last_name'] ?? ''));
$userIsAdmin = (bool)($_SESSION['user_is_admin'] ?? false);
$pageTitle = trim((string)($title ?? 'Flashy'));

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
				<a
						class="btn-secondary w-full cursor-pointer sm:w-auto"
						href="/">Language</a>
				<a
						class="btn-secondary w-full cursor-pointer sm:w-auto"
						href="<?= e($studyHref) ?>">Study</a>
				<?php if ($userEmail !== null): ?>
					<div class="profile-menu">
						<button
								class="btn-secondary w-full cursor-pointer sm:w-auto"
								type="button">
							<?= e($userFirstName !== '' ? 'Hi, ' . $userFirstName : (string)$userEmail) ?>
						</button>
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
									<span aria-hidden="true">›</span>
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
						<a
								class="btn-secondary w-full cursor-pointer justify-center"
								href="/">Language</a>
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
