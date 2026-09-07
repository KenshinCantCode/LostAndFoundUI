<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$auth = new Auth();
$currentUser = $auth->getCurrentUser();
$unreadCount = $currentUser ? getUnreadCount($currentUser['id']) : 0;

// Get flash message
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? sanitize($pageTitle) . ' - ' : '' ?><?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { ink: '#173b2f', coral: '#4f8f6c', mint: '#2f7658', paper: '#f5f8f5' },
                    fontFamily: { sans: ['DM Sans', 'ui-sans-serif', 'system-ui'], display: ['Space Grotesk', 'ui-sans-serif', 'system-ui'] }
                }
            }
        };
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/style.css?v=20260907" rel="stylesheet">
    <link href="<?= SITE_URL ?>/assets/css/footer.css?v=20260907" rel="stylesheet">
</head>
<body class="bg-paper text-ink antialiased">
    <nav class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-5 py-4 lg:px-8">
            <a class="font-display flex items-center gap-3 text-lg font-bold tracking-tight text-ink" href="<?= SITE_URL ?>">
                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-ink text-coral shadow-sm"><i class="fas fa-search-location"></i></span>
                <span><?= SITE_NAME ?></span>
            </a>
            <button class="rounded-lg p-2 text-ink lg:hidden" type="button" aria-label="Toggle navigation" data-nav-toggle>
                <i class="fas fa-bars"></i>
            </button>
            <div class="hidden items-center gap-8 lg:flex" data-nav-menu>
                <div class="flex items-center gap-6 text-sm font-semibold text-slate-600">
                    <a class="transition hover:text-coral" href="<?= SITE_URL ?>"><i class="fas fa-home mr-1 text-xs"></i>Home</a>
                    <a class="transition hover:text-coral" href="<?= SITE_URL ?>/search.php"><i class="fas fa-search mr-1 text-xs"></i>Search</a>
                    <?php if ($auth->isLoggedIn()): ?><a class="transition hover:text-coral" href="<?= SITE_URL ?>/report-lost.php"><i class="fas fa-plus-circle mr-1 text-xs"></i>Report Lost</a><?php endif; ?>
                </div>
                <div class="flex items-center gap-3">
                    <?php if ($auth->isLoggedIn()): ?>
                        <a class="relative rounded-lg p-2 text-slate-600 transition hover:bg-slate-100 hover:text-coral" href="<?= SITE_URL ?>/notifications.php" aria-label="Notifications"><i class="fas fa-bell"></i><?php if ($unreadCount > 0): ?><span class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-coral px-1 text-[10px] font-bold text-white"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span><?php endif; ?></a>
                        <div class="relative">
                            <button class="flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1.5 pl-1.5 pr-3 text-sm font-semibold text-slate-700 shadow-sm" type="button" data-menu-toggle="user-menu" aria-controls="user-menu" aria-expanded="false"><span class="flex h-8 w-8 items-center justify-center rounded-full bg-mint text-sm text-white"><i class="fas fa-user"></i></span><span><?= sanitize($_SESSION['full_name']) ?></span><i class="fas fa-chevron-down text-[10px] text-slate-400"></i></button>
                            <div id="user-menu" class="absolute right-0 mt-2 hidden w-56 rounded-xl border border-slate-200 bg-white p-2 text-sm shadow-xl"><p class="border-b border-slate-100 px-3 pb-2 text-xs text-slate-400">Signed in as <strong class="text-slate-600"><?= sanitize($_SESSION['username']) ?></strong></p><a class="block rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-50" href="<?= SITE_URL ?>/dashboard.php">Dashboard</a><a class="block rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-50" href="<?= SITE_URL ?>/my-reports.php">My Reports</a><a class="block rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-50" href="<?= SITE_URL ?>/my-claims.php">My Claims</a><a class="block rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-50" href="<?= SITE_URL ?>/profile.php">Profile</a><?php if ($auth->isAdmin()): ?><a class="block rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-50" href="<?= SITE_URL ?>/admin/">Admin Panel</a><?php endif; ?><a class="mt-1 block rounded-lg px-3 py-2 text-coral hover:bg-red-50" href="<?= SITE_URL ?>/logout.php">Logout</a></div>
                        </div>
                    <?php else: ?>
                        <a class="text-sm font-semibold text-slate-600 hover:text-coral" href="<?= SITE_URL ?>/login.php">Login</a><a class="rounded-lg bg-ink px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-slate-700" href="<?= SITE_URL ?>/register.php">Register</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="absolute left-0 right-0 top-full hidden border-b border-slate-200 bg-white p-5 shadow-lg lg:hidden" data-nav-mobile><a class="block py-2 font-semibold" href="<?= SITE_URL ?>">Home</a><a class="block py-2 font-semibold" href="<?= SITE_URL ?>/search.php">Search</a><?php if ($auth->isLoggedIn()): ?><a class="block py-2 font-semibold" href="<?= SITE_URL ?>/report-lost.php">Report Lost</a><?php endif; ?><?php if (!$auth->isLoggedIn()): ?><a class="mt-2 block rounded-lg bg-ink px-4 py-2 text-center font-bold text-white" href="<?= SITE_URL ?>/register.php">Register</a><?php endif; ?></div>
        </div>
    </nav>

    <?php if ($flash): ?>
    <div class="mx-auto mt-4 max-w-7xl px-5 lg:px-8">
        <div class="alert alert-<?= $flash['type'] ?> flex items-center justify-between" role="alert">
            <?= $flash['message'] ?>
            <button type="button" class="ml-4 rounded-lg px-2 py-1 text-current opacity-60 hover:opacity-100" data-dismiss="alert" aria-label="Dismiss">&times;</button>
        </div>
    </div>
    <?php endif; ?>

    <main class="mx-auto min-h-[calc(100vh-180px)] max-w-7xl px-5 py-8 lg:px-8">
