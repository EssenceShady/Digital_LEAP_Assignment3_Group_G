<?php
declare(strict_types=1);

use Leap\Core\Csrf;

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $route = '', array $params = []): string
{
    $base = $GLOBALS['base'] ?? '';
    if ($route === '' && $params === []) {
        return $base === '' ? '/' : $base . '/';
    }
    $query = ['r' => $route] + $params;
    return $base . '/index.php?' . http_build_query($query);
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/public/assets/' . $path;
    $version = is_file($file) ? '?v=' . filemtime($file) : '';
    return ($GLOBALS['base'] ?? '') . '/public/assets/' . $path . $version;
}

/** Application root without a trailing slash, e.g. "/digital-leap" or "". */
function app_root(): string
{
    return rtrim((string) ($GLOBALS['base'] ?? ''), '/');
}

/** A file under public/ that is not versioned, e.g. the manifest or offline page. */
function public_file(string $path): string
{
    return app_root() . '/public/' . ltrim($path, '/');
}


function redirect(string $route = '', array $params = [], string $fragment = ''): void
{
    header('Location: ' . url($route, $params) . ($fragment !== '' ? '#' . $fragment : ''));
    exit;
}

function post(string $key, $default = null)
{
    return $_POST[$key] ?? $default;
}

function qget(string $key, $default = null)
{
    return $_GET[$key] ?? $default;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function fmt_date(?string $value, string $format = 'd M Y'): string
{
    if ($value === null || $value === '' || $value === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '—';
}

function fmt_dt(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('d M Y, H:i', $ts) : '—';
}

function fmt_hours($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return rtrim(rtrim(number_format((float) $value, 1), '0'), '.');
}

function fmt_distance($metres): string
{
    if ($metres === null || $metres === '') {
        return '—';
    }
    $value = (float) $metres;
    if ($value < 1000) {
        return number_format($value, 0) . ' m';
    }
    return number_format($value / 1000, 1) . ' km';
}

function password_policy_error(string $password): ?string
{
    if (strlen($password) < 8
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/\d/', $password)) {
        return 'Use at least 8 characters with an uppercase letter, a lowercase letter and a number.';
    }
    return null;
}

function year_label(?string $value): string
{
    $number = trim((string) preg_replace('/^\s*(level|year)\s*/i', '', (string) $value));
    return $number === '' ? '—' : 'Year ' . $number;
}

function review_label(?string $reviewedAt, ?string $firstName, ?string $lastName): string
{
    if ($reviewedAt === null || $reviewedAt === '') {
        return '<span class="muted">Not yet opened</span>';
    }
    $who = trim((string) $firstName . ' ' . (string) $lastName);
    return 'Reviewed by ' . e($who !== '' ? $who : 'a reviewer') . ' · ' . e(fmt_dt($reviewedAt));
}

/** A readable sentence for an audit action such as placement.approve. */
function humanise_action(string $action): string
{
    static $map = [
        'placement.approve' => 'Placement approved',
        'placement.approved' => 'Placement approved',
        'placement.created' => 'Placement created',
        'placement.career_fair_scored' => 'Career fair score recorded',
        'placement.type_changed' => 'Internship type changed',
        'letter.rendered' => 'Placement letter generated',
        'document.upload' => 'Document uploaded',
        'document.reviewed' => 'Document reviewed',
        'logbook.approve' => 'Log book week approved',
        'logbook.approved' => 'Log book week approved',
        'logbook.return' => 'Log book week returned',
        'logbook.returned' => 'Log book week returned',
        'logbook.comment_added' => 'Comment added to the log book',
        'logbook.days_saved' => 'Daily records saved',
        'logbook.day_notice' => 'Daily record emailed to supervisor and mentor',
        'logbook.updated' => 'Log book updated',
        'attendance.check_in' => 'Attendance check-in',
        'attendance.check_out' => 'Attendance check-out',
        'organisation.geo_updated' => 'Site location set',
        'mark.computed' => 'Draft mark computed',
        'mark.publish' => 'Mark published',
    ];
    if (isset($map[$action])) {
        return $map[$action];
    }
    $words = str_replace(['.', '_'], ' ', (string) preg_replace('/^(placement|document|logbook|career_fair|mark)\./', '', $action));
    return ucfirst(trim($words));
}

function status_badge(?string $status): string
{
    $key = strtolower((string) $status);
    $map = [
        'draft' => 'muted', 'proposed' => 'warn', 'pending' => 'warn', 'invited' => 'warn',
        'submitted' => 'info', 'shortlisted' => 'info', 'scheduled' => 'info',
        'approved' => 'ok', 'active' => 'ok', 'completed' => 'ok', 'signed' => 'ok',
        'published' => 'ok', 'assessed' => 'ok', 'mentor_reviewed' => 'ok', 'allocated' => 'ok',
        'returned' => 'bad', 'rejected' => 'bad', 'failed' => 'bad', 'missed' => 'bad',
        'terminated' => 'bad', 'suspended' => 'bad', 'withdrawn' => 'muted',
        'moderated' => 'info', 'queued' => 'warn', 'sent' => 'ok', 'cancelled' => 'muted',
        'expired' => 'bad', 'none' => 'muted', 'inactive' => 'muted',
    ];
    $tone = $map[$key] ?? 'muted';
    return '<span class="badge badge-' . $tone . '">' . e(ucwords(str_replace('_', ' ', $key))) . '</span>';
}

function initials(string $first, string $last): string
{
    return strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
}

function internship_type_label(?string $type): string
{
    return strtolower((string) $type) === 'in-house' ? 'In-house, LEAP platform' : 'External placement';
}

/**
 * Official Limkokwing Sierra Leone programmes, grouped by faculty.
 *
 * @return array<string, list<string>>
 */
function programme_faculties(): array
{
    return [
        'Faculty of Communication, Media & Broadcasting' => [
            'BA (Hons) Professional Communication',
            'BA (Hons) Digital Media',
            'BA (Hons) Broadcasting and Journalism',
            'Diploma in Multimedia',
            'Diploma in Advertisement',
            'Diploma in Broadcasting',
        ],
        'Faculty of Architecture & the Built Environment' => [
            'BA (Hons) Interior Architecture',
            'Diploma in Architectural Technology',
            'Diploma in Interior Design',
        ],
        'Faculty of Business Management & Globalisation' => [
            'BA (Hons) Tourism Management',
            'BA (Hons) Business Administration',
            'BA (Hons) International Business',
            'BA (Hons) Entrepreneurship',
        ],
        'Faculty of Information & Communication Technology' => [
            'BSc (Hons) Software Engineering with Multimedia',
            'BSc (Hons) Business Information Technology',
            'Diploma in Mobile Computing',
        ],
        'Faculty of Design Innovation & Multimedia Creativity' => [
            'BA (Hons) Fashion & Apparel Design',
            'BA (Hons) Creative Multimedia',
            'BA (Hons) Web Design',
            'BA (Hons) Animation',
        ],
    ];
}

function avatar_file(int $userId): ?string
{
    $dir = STORAGE_PATH . '/uploads/avatars';
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $path = $dir . '/' . $userId . '.' . $ext;
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

function avatar_url(?array $user = null): ?string
{
    $id = (int) ($user['user_id'] ?? \Leap\Core\Auth::id());
    if ($id < 1) {
        return null;
    }
    $file = avatar_file($id);
    if ($file === null) {
        return null;
    }
    return url('avatar', ['u' => $id, 'v' => filemtime($file) . '-' . filesize($file)]);
}

function avatar_html(array $user, string $class = 'chat-avatar'): string
{
    $id = (int) ($user['user_id'] ?? 0);
    $first = (string) ($user['first_name'] ?? '');
    $last = (string) ($user['last_name'] ?? '');
    $file = $id > 0 && \Leap\Controllers\ProfileController::canViewAvatar($id) ? avatar_file($id) : null;
    if ($file !== null) {
        $src = url('avatar', ['u' => $id, 'v' => filemtime($file) . '-' . filesize($file)]);
        return '<img class="' . e($class) . ' avatar-img" src="' . e($src) . '" alt="'
            . e(trim($first . ' ' . $last)) . ' photo" loading="lazy">';
    }
    return '<span class="' . e($class) . '" aria-hidden="true">' . e(initials($first, $last)) . '</span>';
}

function csrf_field(): string
{
    return Csrf::field();
}

function selected($value, $expected): string
{
    return (string) $value === (string) $expected ? ' selected' : '';
}

function checked($value): string
{
    return $value ? ' checked' : '';
}

function nav_items(): array
{
    $role = \Leap\Core\Auth::role();
    $map = [
        'student' => [
            ['dashboard', 'Dashboard'],
            ['student/apply', 'Apply for Internship'],
            ['student/placement', 'My Placement'],
            ['student/logbook', 'Logbook (Form E)'],
            ['student/report', 'Internship Report (Form C)'],
            ['student/self-evaluation', 'Self Evaluation (Form E)'],
            ['student/career-fair', 'Career Fair 2026'],
            ['student/results', 'Results (Form E)'],
            ['diva', 'DIVA'],
            ['messages', 'Messages'],
        ],
        'supervisor' => [
            ['dashboard', 'Dashboard'],
            ['diva', 'DIVA'],
            ['supervisor/queue', 'Logbook Queue (Form E)'],
            ['supervisor/evaluation', 'Form D evaluation'],
        ],
        'mentor' => [
            ['dashboard', 'Dashboard'],
            ['diva', 'DIVA'],
            ['messages', 'Messages'],
            ['mentor/students', 'My Students'],
            ['mentor/logbook', 'Logbook Review (Form E)'],
            ['mentor/visits', 'Site Visits'],
            ['mentor/evaluation', 'Mentor evaluation'],
            ['mentor/reports', 'Report Assessment (Form C)'],
        ],
        'coordinator' => [
            ['dashboard', 'Dashboard'],
            ['diva', 'DIVA'],
            ['messages', 'Messages'],
            ['coordinator/applications', 'Applications'],
            ['coordinator/placements', 'Placements'],
            ['coordinator/organisations', 'Organisations'],
            ['coordinator/visits', 'Site Visits'],
            ['coordinator/grades', 'Grades (Form E)'],
        ],
        'management' => [
            ['dashboard', 'Dashboard'],
            ['diva', 'DIVA'],
            ['management/kpis', 'Quality Indicators'],
        ],
        'admin' => [
            ['dashboard', 'Dashboard'],
            ['diva', 'DIVA'],
            ['admin/users', 'Users'],
            ['admin/roles', 'Role Matrix'],
            ['admin/settings', 'Settings'],
            ['admin/audit', 'Audit Log'],
            ['admin/feedback', 'Feedback'],
            ['admin/mail', 'Mail Queue'],
        ],
    ];

    $items = $map[$role] ?? [['dashboard', 'Dashboard']];
    // The guidebook is published to every role, so it closes the list instead
    // of being repeated in each role's own array.
    $items[] = ['guidebook', 'Guidebook'];

    return $items;
}
function career_fair_deadline(): string
{
    return '2026-11-11 12:00:00';
}

function weight_total_for(string $type): float
{
    $rows = \Leap\Core\Database::all(
        'SELECT COALESCE(SUM(weight),0) AS total FROM criterion WHERE evaluation_type = ? AND active = 1',
        [$type]
    );
    return (float) ($rows[0]['total'] ?? 0);
}

function rating_stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    $html = '<span class="stars" role="img" aria-label="' . $rating . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="' . ($i <= $rating ? 'on' : '') . '" aria-hidden="true">&#9733;</span>';
    }
    return $html . '</span>';
}

/**
 * Milestones that carry a placement from allocation to published result.
 * Percentages are weighted so the tracker reflects the whole internship, not just the logbook.
 */
function internship_progress(int $studentId): array
{
    $placement = \Leap\Core\Database::one(
        'SELECT p.*, o.name AS org_name FROM placement p
         JOIN organisation o ON o.org_id = p.org_id
         WHERE p.student_id = ? ORDER BY p.start_date DESC LIMIT 1',
        [$studentId]
    );

    $steps = [
        ['key' => 'placement', 'label' => 'Placement confirmed', 'detail' => 'The internship office has not allocated a host organisation yet.', 'pct' => 0, 'weight' => 10],
        ['key' => 'logbook', 'label' => 'Weekly logbook', 'detail' => 'Logbook weeks are submitted and reviewed as the placement runs.', 'pct' => 0, 'weight' => 35],
        ['key' => 'supervisor', 'label' => 'Host supervisor evaluation', 'detail' => 'The workplace supervisor evaluates your performance.', 'pct' => 0, 'weight' => 15],
        ['key' => 'mentor', 'label' => 'Academic mentor evaluation', 'detail' => 'Your mentor records the academic assessment.', 'pct' => 0, 'weight' => 15],
        ['key' => 'visit', 'label' => 'Site visit', 'detail' => 'A mentor visit confirms the placement is running as approved.', 'pct' => 0, 'weight' => 5],
        ['key' => 'report', 'label' => 'Internship report', 'detail' => 'Upload the report and have it assessed.', 'pct' => 0, 'weight' => 10],
        ['key' => 'result', 'label' => 'Result published', 'detail' => 'The grade sheet is computed, moderated and released.', 'pct' => 0, 'weight' => 10],
    ];

    if ($placement === null) {
        $steps[0]['state'] = 'active';
        foreach (array_slice($steps, 1) as $i => $step) {
            $steps[$i + 1]['state'] = 'todo';
        }
        return ['percent' => 0, 'placement' => null, 'steps' => $steps];
    }

    $pid = (int) $placement['placement_id'];
    $weeks = max(1, (int) $placement['total_weeks']);

    $submitted = (int) \Leap\Core\Database::val(
        "SELECT COUNT(*) FROM logbook_entry WHERE placement_id = ? AND status <> 'draft'",
        [$pid]
    );
    $approved = (int) \Leap\Core\Database::val(
        "SELECT COUNT(*) FROM logbook_entry WHERE placement_id = ? AND status IN ('approved','mentor_reviewed')",
        [$pid]
    );
    $supervisor = \Leap\Core\Database::one(
        "SELECT status, submitted_at FROM evaluation WHERE placement_id = ? AND evaluation_type = 'supervisor' LIMIT 1",
        [$pid]
    );
    $mentor = \Leap\Core\Database::one(
        "SELECT status, submitted_at FROM evaluation WHERE placement_id = ? AND evaluation_type = 'mentor' LIMIT 1",
        [$pid]
    );
    $visit = \Leap\Core\Database::one(
        'SELECT status, completed_at, scheduled_date FROM site_visit
         WHERE placement_id = ? ORDER BY completed_at IS NULL, scheduled_date DESC LIMIT 1',
        [$pid]
    );
    $report = \Leap\Core\Database::one(
        'SELECT status, submitted_at, assessed_at, score FROM internship_report WHERE placement_id = ? LIMIT 1',
        [$pid]
    );
    $mark = \Leap\Core\Database::one(
        'SELECT status, weighted_total, published_at FROM mark WHERE placement_id = ? LIMIT 1',
        [$pid]
    );

    $confirmed = $placement['approved_at'] !== null
        || in_array($placement['status'], ['approved', 'active', 'completed'], true);
    $steps[0]['pct'] = $confirmed ? 100 : 50;
    $steps[0]['detail'] = $confirmed
        ? 'Confirmed for ' . (string) $placement['academic_year'] . ' at ' . (string) ($placement['org_name'] ?? 'the host organisation') . '.'
        : 'Allocated and waiting for coordinator approval.';

    $logbookPct = (int) round(min(100, ($submitted / $weeks) * 100));
    $steps[1]['pct'] = $logbookPct;
    $steps[1]['detail'] = $submitted . ' of ' . $weeks . ' weeks submitted · ' . $approved . ' approved.';

    if ($supervisor !== null && $supervisor['status'] === 'submitted') {
        $steps[2]['pct'] = 100;
        $steps[2]['detail'] = 'Submitted ' . fmt_dt($supervisor['submitted_at']) . '.';
    } else {
        $steps[2]['detail'] = $supervisor === null
            ? 'Waiting for the host supervisor to evaluate the placement.'
            : 'Draft started by the host supervisor; not submitted yet.';
    }

    if ($mentor !== null && $mentor['status'] === 'submitted') {
        $steps[3]['pct'] = 100;
        $steps[3]['detail'] = 'Submitted ' . fmt_dt($mentor['submitted_at']) . '.';
    } else {
        $steps[3]['detail'] = $mentor === null
            ? 'Waiting for the academic mentor to evaluate the placement.'
            : 'Draft started by the academic mentor; not submitted yet.';
    }

    if ($visit !== null && $visit['status'] === 'completed') {
        $steps[4]['pct'] = 100;
        $steps[4]['detail'] = 'Completed ' . fmt_date($visit['completed_at'] !== null ? substr((string) $visit['completed_at'], 0, 10) : (string) $visit['scheduled_date']) . '.';
    } elseif ($visit !== null) {
        $steps[4]['pct'] = 40;
        $steps[4]['detail'] = 'Scheduled for ' . fmt_date($visit['scheduled_date']) . '; not completed yet.';
    }

    if ($report !== null) {
        if ($report['status'] === 'assessed') {
            $steps[5]['pct'] = 100;
            $steps[5]['detail'] = 'Assessed ' . fmt_dt($report['assessed_at']) . ($report['score'] !== null ? ' · score ' . number_format((float) $report['score'], 1) . '%' : '') . '.';
        } else {
            $steps[5]['pct'] = 70;
            $steps[5]['detail'] = 'Report submitted ' . fmt_dt($report['submitted_at']) . '; awaiting assessment.';
        }
    } else {
        $steps[5]['detail'] = 'No internship report uploaded yet.';
    }

    if ($mark !== null) {
        if ($mark['status'] === 'published') {
            $steps[6]['pct'] = 100;
            $steps[6]['detail'] = 'Published ' . fmt_dt($mark['published_at']) . ($mark['weighted_total'] !== null ? ' · final mark ' . number_format((float) $mark['weighted_total'], 1) . '%' : '') . '.';
        } elseif ($mark['status'] === 'moderated') {
            $steps[6]['pct'] = 70;
            $steps[6]['detail'] = 'Moderated; waiting to be published.';
        } elseif ($mark['weighted_total'] !== null) {
            $steps[6]['pct'] = 40;
            $steps[6]['detail'] = 'Mark computed; waiting for moderation and publication.';
        } else {
            $steps[6]['detail'] = 'The grade sheet has not been computed yet.';
        }
    } else {
        $steps[6]['detail'] = 'The grade sheet has not been computed yet.';
    }

    $percent = 0.0;
    $activeAssigned = false;
    foreach ($steps as $i => $step) {
        $percent += $step['weight'] * ($step['pct'] / 100);
        if ($step['pct'] >= 100) {
            $steps[$i]['state'] = 'done';
        } elseif (!$activeAssigned) {
            $steps[$i]['state'] = 'active';
            $activeAssigned = true;
        } else {
            $steps[$i]['state'] = 'todo';
        }
    }
    if (!$activeAssigned) {
        $steps[count($steps) - 1]['state'] = 'done';
    }

    return [
        'percent' => (int) round($percent),
        'placement' => $placement,
        'steps' => $steps,
    ];
}
