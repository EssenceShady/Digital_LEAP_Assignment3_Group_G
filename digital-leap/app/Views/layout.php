<?php
/** @var string $content */
/** @var string $title */
use Leap\Core\Auth;
use Leap\Core\Database;
use Leap\Core\Messenger;
use Leap\Core\Preference;

$user = Auth::user();
$route = (string) ($_GET['r'] ?? '');
$unread = 0;
$recentNotifications = [];
$msgUnread = 0;
if ($user !== null) {
    $unread = (int) Database::val('SELECT COUNT(*) FROM notification WHERE user_id = ? AND is_read = 0', [(int) $user['user_id']]);
    $recentNotifications = Database::all(
        'SELECT notification_id, title, body, is_read, created_at
         FROM notification WHERE user_id = ? ORDER BY created_at DESC LIMIT 5',
        [(int) $user['user_id']]
    );
    if (Auth::can('message.use')) {
        $msgUnread = Messenger::unreadTotal((int) $user['user_id']);
    }
}
$prefUid = $user === null ? 0 : (int) $user['user_id'];
$textSize = Preference::textSize($prefUid);
$calmMotion = Preference::reduceMotion($prefUid);
$nav = $user === null ? [] : nav_items();
$avatarUrl = $user === null ? null : avatar_url($user);
$authRoutes = ['login', 'register', 'forgot', 'reset', 'token/supervisor'];
$isAuthPage = $user === null && in_array($route, $authRoutes, true);
$isLanding = $user === null && $route === '';
$navIcons = [
    'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    'diva' => '<path d="M10.5 21V3.5C8.5 1.5 5.5 3 5.5 5.5 3 5.5 2 8 3 10 1 12 2 15 4 15.5 3 18 5 21 7.5 20.5M13.5 21V3.5C15.5 1.5 18.5 3 18.5 5.5 21 5.5 22 8 21 10 23 12 22 15 20 15.5 21 18 19 21 16.5 20.5"/><path d="M5.5 5.5V9L8 11v4M3 10l3 2M4 15.5l3 1.5M18.5 5.5V9L16 11v4M21 10l-3 2M20 15.5l-3 1.5"/>',
    'messages' => '<path d="M20 14.5a2.5 2.5 0 0 1-2.5 2.5H9l-4 3.2V7.5A2.5 2.5 0 0 1 7.5 5h10A2.5 2.5 0 0 1 20 7.5Z"/><path d="M8.5 9.5h8"/><path d="M8.5 12.5h5"/>',
    'guidebook' => '<path d="M12 6.5C10.6 5.4 8.6 5 6.5 5H3v14h3.5c2.1 0 4.1.4 5.5 1.5 1.4-1.1 3.4-1.5 5.5-1.5H21V5h-3.5c-2.1 0-4.1.4-5.5 1.5Z"/><path d="M12 6.5v14"/>',
    'student/placement' => '<path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/><path d="M3 20h18"/>',
    'student/logbook' => '<path d="M5 4.5A2.5 2.5 0 0 1 7.5 2H19v18H7.5A2.5 2.5 0 0 0 5 22V4.5Z"/><path d="M5 18.5A2.5 2.5 0 0 1 7.5 16H19"/>',
    'student/preferences' => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
    'student/apply' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="m8.5 14.5 2 2 4-4"/>',
    'student/report' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/>',
    'student/self-evaluation' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8"/><path d="M8 12h5"/><path d="m8 16 2 2 4-4"/>',
    'student/career-fair' => '<path d="M4 21V9l8-4 8 4v12"/><path d="M4 21h16"/><path d="M8 21v-6h8v6"/><path d="M12 5V2"/>',
    'student/results' => '<circle cx="12" cy="8" r="5"/><path d="m8.5 12-1 9 4.5-2.5L16.5 21l-1-9"/>',
    'supervisor/queue' => '<path d="M4 4h16v7H4z"/><path d="M4 13h16v7H4z"/><path d="M8 8h8"/><path d="M8 17h5"/>',
    'supervisor/evaluation' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8"/><path d="M8 12h5"/><path d="m8 16 2 2 4-4"/>',
    'mentor/students' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.5A4.5 4.5 0 0 1 21 19"/>',
    'mentor/logbook' => '<path d="M5 4.5A2.5 2.5 0 0 1 7.5 2H19v18H7.5A2.5 2.5 0 0 0 5 22V4.5Z"/><path d="M9 7h6"/><path d="M9 11h6"/>',
    'mentor/visits' => '<path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
    'mentor/evaluation' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8"/><path d="M8 12h5"/><path d="m8 16 2 2 4-4"/>',
    'mentor/reports' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/><path d="m8 15 2 2 4-4"/>',
    'coordinator/applications' => '<path d="M3 6a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M8 12h8"/><path d="M8 16h5"/>',
    'coordinator/placements' => '<path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/><path d="M3 20h18"/>',
    'coordinator/organisations' => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/><path d="M16 9h3a1 1 0 0 1 1 1v11"/><path d="M8 7h4"/><path d="M8 11h4"/><path d="M8 15h4"/><path d="M2 21h20"/>',
    'coordinator/visits' => '<path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
    'coordinator/grades' => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M22 20H2"/>',
    'management/kpis' => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
    'admin/users' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9" r="2.5"/><path d="M15.5 14.5A4.5 4.5 0 0 1 21 19"/>',
    'admin/roles' => '<path d="M12 3 5 6v5c0 4.4 3 8.4 7 10 4-1.6 7-5.6 7-10V6Z"/><path d="m9 12 2 2 4-4"/>',
    'admin/settings' => '<path d="M4 7h10"/><path d="M18 7h2"/><circle cx="16" cy="7" r="2"/><path d="M4 17h4"/><path d="M12 17h8"/><circle cx="10" cy="17" r="2"/>',
    'admin/audit' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'admin/feedback' => '<path d="M21 12a8 8 0 0 1-8 8H5l-2 2V12a8 8 0 0 1 8-8h2a8 8 0 0 1 8 8Z"/><path d="M8 11h8"/><path d="M8 15h5"/>',
    'admin/mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
];
?>
<!doctype html>
<html lang="en" data-text-size="<?= e($textSize) ?>"<?= $calmMotion ? ' data-reduce-motion="on"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<title><?= e($title) ?> · Digital LEAP</title>
<meta name="theme-color" content="#111315">
<meta name="application-name" content="Digital LEAP">
<meta name="leap-base" content="<?= e(app_root()) ?>">
<?php if ($user !== null): ?>
<meta name="csrf-token" content="<?= e(\Leap\Core\Csrf::token()) ?>">
<?php endif; ?>
<meta name="leap-offline-url" content="<?= e(public_file('offline.html')) ?>">
<link rel="manifest" href="<?= e(public_file('manifest.webmanifest')) ?>">
<link rel="icon" type="image/png" sizes="192x192" href="<?= e(asset('img/icon-192.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('img/icon-192.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/boot.js')) ?>"></script>
</head>
<body class="<?= $user !== null ? 'app-shell' : ($isAuthPage ? 'auth-shell' : 'landing-shell') ?>" data-role="<?= e($user !== null ? (string) Auth::role() : 'guest') ?>" data-page="<?= e($route) ?>">
<a class="skip-link" href="#main">Skip to main content</a>
<div class="loadbar" data-loadbar aria-hidden="true"><span></span></div>

<div class="leap-boot" data-leap-boot aria-hidden="true">
    <div class="leap-boot-inner">
        <div class="leap-boot-orb">
            <span class="leap-boot-ring leap-boot-ring-a"></span>
            <span class="leap-boot-ring leap-boot-ring-b"></span>
            <div class="leap-boot-globe">
                <picture class="leap-boot-face">
                    <source type="image/webp" srcset="<?= e(asset('img/globe-flag-sphere.webp')) ?>">
                    <img src="<?= e(asset('img/globe-flag-sphere.png')) ?>" width="340" height="340" alt="" decoding="async">
                </picture>
                <picture class="leap-boot-face leap-boot-face-back">
                    <source type="image/webp" srcset="<?= e(asset('img/globe-flag-sphere.webp')) ?>">
                    <img src="<?= e(asset('img/globe-flag-sphere.png')) ?>" width="340" height="340" alt="" decoding="async">
                </picture>
                <span class="leap-boot-glint"></span>
            </div>
        </div>
        <img class="leap-boot-wordmark" src="<?= e(asset('img/leap-wordmark-ivory.png')) ?>" width="1013" height="566" alt="Digital LEAP">
        <p class="leap-boot-line"><span>Internship management</span><span>Limkokwing University, Sierra Leone</span></p>
        <div class="leap-boot-meter"><span></span></div>
        <p class="leap-boot-status">Preparing your workspace</p>
    </div>
</div>

<?php if ($user !== null): ?>
<header class="topbar">
    <div class="topbar-left">
        <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="sidebar" aria-label="Open navigation">
            <span></span><span></span><span></span>
        </button>
        <a class="brand" href="<?= e(url('dashboard')) ?>" data-tour="brand">
            <img class="brand-logo" src="<?= e(asset('img/leap-wordmark-ink.png')) ?>" alt="LEAP — Limkokwing Entrepreneurship Acceleration Platform, Sierra Leone">
            <span class="brand-text">Digital LEAP<small>Internship Management</small></span>
        </a>
    </div>
    <div class="topbar-right">
        <p class="net-pill" data-net-status data-state="online" role="status" aria-live="polite">
            <span class="net-dot" aria-hidden="true"></span><span class="net-text">Online</span>
        </p>
        <button class="icon-btn tour-trigger" type="button" data-tour-start aria-label="Take a guided tour" title="Take a guided tour">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 4 2c-.9.7-1.5 1.2-1.5 2.5"/><path d="M12 17h.01"/></svg>
        </button>

        <div class="menu-wrap">
            <button class="icon-btn bell-btn" type="button" data-notif-toggle aria-expanded="false" aria-controls="notif-panel" aria-label="Notifications<?= $unread > 0 ? ', ' . $unread . ' unread' : '' ?>" data-tour="notifications">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                <?php if ($unread > 0): ?><span class="count" aria-hidden="true"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
            </button>
            <div class="dropdown notif-panel" id="notif-panel" hidden>
                <div class="dropdown-head">
                    <div><strong>Notifications</strong><span><?= $unread > 0 ? $unread . ' unread' : 'You are up to date' ?></span></div>
                    <a class="link-quiet" href="<?= e(url('notifications')) ?>">View all</a>
                </div>
                <?php if ($recentNotifications === []): ?>
                    <div class="dropdown-empty">Nothing yet. Updates arrive when a record moves.</div>
                <?php else: ?>
                    <div class="notif-list">
                        <?php foreach ($recentNotifications as $item): ?>
                            <form method="post" action="<?= e(url('notifications/read')) ?>" class="notif-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="notification_id" value="<?= (int) $item['notification_id'] ?>">
                                <button type="submit" class="notif-item<?= (int) $item['is_read'] === 0 ? ' is-unread' : '' ?>">
                                    <span class="notif-dot" aria-hidden="true"></span>
                                    <span class="notif-copy">
                                        <strong><?= e($item['title']) ?></strong>
                                        <span><?= e(mb_strimwidth((string) $item['body'], 0, 92, '…')) ?></span>
                                        <small><?= e(fmt_dt($item['created_at'])) ?></small>
                                    </span>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a class="dropdown-foot" href="<?= e(url('notifications')) ?>">Open the notification inbox</a>
            </div>
        </div>

        <div class="menu-wrap user-chip" data-tour="profile">
            <a class="avatar-link" href="<?= e(url('profile')) ?>" aria-label="Open my profile to edit details and profile photo">
                <?php if ($avatarUrl !== null): ?>
                    <img class="avatar avatar-img" src="<?= e($avatarUrl) ?>" alt="">
                <?php else: ?>
                    <span class="avatar" aria-hidden="true"><?= e(initials((string) $user['first_name'], (string) $user['last_name'])) ?></span>
                <?php endif; ?>
            </a>
            <button class="user-menu-toggle" type="button" data-user-toggle aria-expanded="false" aria-controls="user-menu">
                <span class="user-meta">
                    <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                    <small><?= e($user['role_name']) ?></small>
                </span>
                <svg class="menu-caret" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 10 5 5 5-5"/></svg>
            </button>
            <div class="dropdown user-menu" id="user-menu" hidden>
                <div class="user-menu-head">
                    <?php if ($avatarUrl !== null): ?>
                        <img class="avatar avatar-img" src="<?= e($avatarUrl) ?>" alt="">
                    <?php else: ?>
                        <span class="avatar" aria-hidden="true"><?= e(initials((string) $user['first_name'], (string) $user['last_name'])) ?></span>
                    <?php endif; ?>
                    <div>
                        <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?></strong>
                        <small><?= e($user['email']) ?></small>
                    </div>
                </div>
                <a href="<?= e(url('profile')) ?>">Account and security</a>
                <?php if (Auth::can('diva.use')): ?>
                    <a href="<?= e(url('diva')) ?>">Ask DIVA</a>
                <?php endif; ?>
                <?php if (Auth::can('message.use')): ?>
                    <a href="<?= e(url('messages')) ?>">Messages<?= $msgUnread > 0 ? ' (' . $msgUnread . ')' : '' ?></a>
                <?php endif; ?>
                <a href="<?= e(url('notifications')) ?>">Notifications<?= $unread > 0 ? ' (' . $unread . ')' : '' ?></a>
                <a href="<?= e(url('settings')) ?>" data-tour="settings">My settings</a>
                <button type="button" data-feedback-open>Send feedback</button>
                <button type="button" data-tour-start>Take the guided tour</button>
                <button type="button" data-install-trigger hidden>How to install this app</button>
                <p class="install-help" data-install-help hidden></p>
                <form method="post" action="<?= e(url('logout')) ?>" class="user-menu-signout">
                    <?= csrf_field() ?>
                    <button type="submit">Sign out</button>
                </form>
            </div>
        </div>
    </div>
</header>

<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-role">
            <span class="sidebar-role-label">Signed in as</span>
            <strong><?= e($user['role_name']) ?></strong>
            <span class="sidebar-role-sub">Demo workspace · <?= e(date('Y')) ?></span>
        </div>
        <nav aria-label="Main navigation" data-tour="nav">
            <?php foreach ($nav as [$href, $label]): ?>
                <?php $isActive = $route === $href || str_starts_with($route, $href . '/'); ?>
                <a href="<?= e(url($href)) ?>" class="<?= $isActive ? 'active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
                    <span class="nav-ico" aria-hidden="true"><svg viewBox="0 0 24 24"><?= $navIcons[$href] ?? '<circle cx="12" cy="12" r="3"/>' ?></svg></span>
                    <span><?= e($label) ?></span>
                    <?php if ($href === 'messages' && $msgUnread > 0): ?>
                        <span class="nav-count"><?= $msgUnread > 99 ? '99+' : $msgUnread ?><span class="sr-only"> unread</span></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <a class="sidebar-profile" href="<?= e(url('profile')) ?>">
                <?= avatar_html(['user_id' => (int) ($user['user_id'] ?? 0), 'first_name' => (string) $user['first_name'], 'last_name' => (string) $user['last_name']], 'sidebar-avatar') ?>
                <span><?= e($user['first_name'] . ' ' . $user['last_name']) ?><small>Account and security</small></span>
            </a>
            <a class="sidebar-settings" href="<?= e(url('settings')) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3.2"/><path d="M12 2.8v2.4M12 18.8v2.4M4.5 7.5l2 1.2M17.5 15.3l2 1.2M4.5 16.5l2-1.2M17.5 8.7l2-1.2"/></svg>
                <span>My settings<small>Notifications, text size, offline data</small></span>
            </a>
            <button type="button" class="sidebar-feedback" data-feedback-open data-tour="feedback">Share feedback</button>
            <form method="post" action="<?= e(url('logout')) ?>" class="sidebar-signout">
                <?= csrf_field() ?>
                <button type="submit">Sign out</button>
            </form>
            <span class="sidebar-campus">Limkokwing University<br>Sierra Leone</span>
        </div>
    </aside>
    <div class="nav-scrim" data-nav-close hidden></div>

    <main id="main" class="main">
        <?php if ($route === 'dashboard'): ?>
            <aside class="demo-strip" data-demo-strip>
                <span class="demo-strip-badge">Demo</span>
                <p><strong>This is a sample workspace.</strong> Seeded students, placements, logbooks and results are ready to explore.</p>
                <button type="button" class="btn btn-sm btn-outline" data-tour-start>Take the tour</button>
                <button type="button" class="demo-strip-close" data-demo-dismiss aria-label="Dismiss demo message">&times;</button>
            </aside>
        <?php endif; ?>
        <?php foreach (flashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>" role="status" data-dismiss>
                <span><?= e($f['message']) ?></span>
                <button type="button" class="alert-close" data-alert-close aria-label="Dismiss message">&times;</button>
            </div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
</div>
<?php else: ?>
<main id="main" class="public-main<?= $isAuthPage ? ' auth-layout' : '' ?>">
    <p class="net-pill net-pill--float" data-net-status data-state="online" role="status" aria-live="polite">
        <span class="net-dot" aria-hidden="true"></span><span class="net-text">Online</span>
    </p>
    <?php if ($isAuthPage): ?>
        <section class="auth-brand" aria-label="About Digital LEAP Internship Management">
            <div class="auth-brand-top">
                <span class="logo-tile"><img src="<?= e(asset('img/leap-wordmark-ink.png')) ?>" alt="LEAP — Limkokwing Entrepreneurship Acceleration Platform, Sierra Leone"></span>
                <span class="auth-kicker">Digital Leap Internship Management</span>
            </div>
            <div class="auth-copy">
                <p class="auth-eyebrow">Limkokwing University · Sierra Leone</p>
                <h1>Where internships become evidence.</h1>
                <p class="auth-lead">One workspace for placements, weekly logbooks, supervision, assessment and results — built to work offline on the university’s own machines.</p>
                <ul class="auth-points">
                    <li><span aria-hidden="true">&#10003;</span> Submit logbooks from a phone and follow every comment.</li>
                    <li><span aria-hidden="true">&#10003;</span> Review, evaluate and moderate with a full audit trail.</li>
                    <li><span aria-hidden="true">&#10003;</span> Publish results only when the evidence is complete.</li>
                </ul>
            </div>
            <p class="auth-brand-foot">Offline-first · Six roles · Limkokwing University, Sierra Leone</p>
        </section>
        <section class="auth-panel" aria-label="Sign in or register">
            <figure class="auth-strip">
                <img src="<?= e(asset('img/campus-graduation-960.jpg')) ?>" width="1904" height="680"
                     srcset="<?= e(asset('img/campus-graduation-960.jpg')) ?> 960w, <?= e(asset('img/campus-graduation.jpg')) ?> 1904w"
                     sizes="(max-width: 560px) calc(100vw - 28px), 480px"
                     alt="Limkokwing graduates in caps and gowns throwing their mortarboards during a graduation ceremony."
                     decoding="async">
                <figcaption>Limkokwing graduation</figcaption>
            </figure>
            <?php foreach (flashes() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?>" role="status" data-dismiss>
                    <span><?= e($f['message']) ?></span>
                    <button type="button" class="alert-close" data-alert-close aria-label="Dismiss message">&times;</button>
                </div>
            <?php endforeach; ?>
            <?= $content ?>
        </section>
    <?php else: ?>
        <?php foreach (flashes() as $f): ?>
            <div class="alert alert-<?= e($f['type']) ?>" role="status" data-dismiss>
                <span><?= e($f['message']) ?></span>
                <button type="button" class="alert-close" data-alert-close aria-label="Dismiss message">&times;</button>
            </div>
        <?php endforeach; ?>
        <?= $content ?>
    <?php endif; ?>
</main>
<?php endif; ?>

<?php if ($user !== null && !empty($_SESSION['_tour_prompt'])): ?>
    <?php $_SESSION['_tour_prompt'] = 0; ?>
    <div class="welcome-tour" data-tour-prompt>
        <div class="welcome-tour-card" role="dialog" aria-modal="true" aria-labelledby="welcome-tour-title" aria-describedby="welcome-tour-copy">
            <span class="auth-badge">Welcome</span>
            <h2 id="welcome-tour-title">Your workspace is ready</h2>
            <p id="welcome-tour-copy">Would you like a short tour of <?= $route === 'dashboard' ? 'your dashboard' : 'this page' ?>, written for your role? It takes about a minute and you can leave at any time.</p>
            <div class="welcome-tour-actions">
                <button type="button" class="btn" data-tour-prompt-start>Show me around</button>
                <button type="button" class="btn btn-quiet" data-tour-prompt-skip>I'll explore on my own</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($user !== null || $isLanding): ?>
<footer class="site-foot">
    <span class="foot-brand"><img src="<?= e(asset('img/limkokwing-logo.png')) ?>" alt="" aria-hidden="true"> Limkokwing University, Sierra Leone</span>
    <span>Digital LEAP · Internship Management System</span>
</footer>
<?php endif; ?>

<?php if ($user !== null): ?>
<?php
$divaPrompt = match ($route) {
    'student/placement' => 'What is my next step on this placement?',
    'student/logbook' => 'What is my next step on this logbook?',
    'mentor/visits' => 'How do I record a site visit?',
    'coordinator/placements/view' => 'What needs attention on this placement?',
    default => '',
};
?>
<?php if (Auth::can('diva.use') && $route !== 'diva'): ?>
<a class="diva-fab" href="<?= e($divaPrompt !== '' ? url('diva', ['q' => $divaPrompt, 'from' => $route]) : url('diva')) ?>"
   aria-label="Ask DIVA, the internship assistant" aria-keyshortcuts="Alt+Shift+D">
    <img src="<?= e(asset('img/diva-mark-sm.png')) ?>" alt="" aria-hidden="true" width="24" height="24">
    <span>DIVA</span>
</a>
<?php endif; ?>
<div class="feedback-layer" data-feedback-layer hidden>
    <div class="feedback-card" role="dialog" aria-modal="true" aria-labelledby="feedback-title" aria-describedby="feedback-copy">
        <div class="feedback-card-head">
            <div>
                <p class="eyebrow">Your voice</p>
                <h2 id="feedback-title">How is Digital LEAP working for you?</h2>
                <p id="feedback-copy">Rate the system and tell us what to keep or change. Your answer reaches the administrator.</p>
            </div>
            <button class="feedback-close" type="button" data-feedback-close aria-label="Close feedback">&times;</button>
        </div>
        <form method="post" action="<?= e(url('feedback')) ?>" data-feedback-form>
            <?= csrf_field() ?>
            <input type="hidden" name="page" value="<?= e($route) ?>">
            <div class="field">
                <span class="field-label">Your rating</span>
                <div class="rating-scale" role="radiogroup" aria-label="Rate Digital LEAP from 1 to 5">
                    <?php foreach ([1 => 'Very poor', 2 => 'Poor', 3 => 'Fair', 4 => 'Good', 5 => 'Excellent'] as $value => $word): ?>
                        <label class="rating-option">
                            <input type="radio" name="rating" value="<?= (int) $value ?>" required>
                            <span class="rating-num"><?= (int) $value ?></span>
                            <span class="rating-word"><?= e($word) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="field">
                <label for="feedback-thoughts">What are your thoughts?</label>
                <textarea id="feedback-thoughts" name="thoughts" rows="3" maxlength="1000" placeholder="What works well? What feels slow or confusing?"></textarea>
            </div>
            <div class="field">
                <label for="feedback-suggestions">What should we add or improve?</label>
                <textarea id="feedback-suggestions" name="suggestions" rows="3" maxlength="1000" placeholder="One change that would make this system better for you."></textarea>
            </div>
            <p class="feedback-error" data-feedback-error role="alert" hidden></p>
            <div class="form-actions">
                <button class="btn" type="submit">Send feedback</button>
                <button class="btn btn-quiet" type="button" data-feedback-close>Not now</button>
                <span class="hint">Visible to the administrator with your name; hidden from other users.</span>
            </div>
        </form>
        <div class="feedback-done" data-feedback-done hidden>
            <div class="done-mark" aria-hidden="true">&#10003;</div>
            <h3>Thank you for helping</h3>
            <p>Your feedback has been recorded. It goes straight to the administrator and shapes the next iteration of the system.</p>
            <div class="form-actions"><button class="btn" type="button" data-feedback-close>Close</button></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="sync-strip" data-sync-strip hidden role="status" aria-live="polite"></div>

<div class="install-prompt" data-install-prompt hidden>
    <div class="install-mark" aria-hidden="true">DL</div>
    <div class="install-copy">
        <strong>Install Digital LEAP</strong>
        <span>One tap from the home screen. Logbooks keep working without a connection.</span>
    </div>
    <div class="install-actions">
        <button type="button" class="btn btn-sm" data-install-accept>Install</button>
        <button type="button" class="btn btn-sm btn-quiet" data-install-dismiss>Not now</button>
    </div>
</div>

<div class="toast-stack" data-toasts aria-live="polite" aria-atomic="false"></div>
<script src="<?= e(asset('js/offline.js')) ?>"></script>
<script src="<?= e(asset('js/diva-offline.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<script src="<?= e(asset('js/attendance.js')) ?>"></script>
</body>
</html>
