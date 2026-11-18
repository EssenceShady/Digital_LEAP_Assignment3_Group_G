<?php
/** @var array|null $student */
/** @var array|null $placement */
/** @var array $entries */
/** @var array|null $feedback */
/** @var array $tasks */
/** @var array $prefs */
/** @var int $progress */
/** @var int|null $nextWeek */
/** @var string|null $dueDate */
/** @var array $returned */
/** @var bool $cvOnFile */
/** @var bool $prefsSubmitted */
$approved = count(array_filter($entries, static fn (array $e): bool => in_array($e['status'], ['approved', 'mentor_reviewed'], true)));
?>
<div class="page-head">
    <div>
        <h1>Welcome back, <?= e(explode(' ', (string) \Leap\Core\Auth::name())[0]) ?></h1>
        <p><?= $placement ? 'You are on placement at ' . e($placement['org_name']) . '.' : 'Your placement is not active yet.' ?></p>
    </div>
    <div class="page-actions">
        <?php if ($placement !== null && $nextWeek !== null): ?>
            <a class="btn" href="<?= e(url('student/logbook/new')) ?>">New logbook entry</a>
        <?php endif; ?>
        <?php if ($placement === null): ?>
            <a class="btn" href="<?= e(url('student/apply')) ?>">Apply for an internship</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= e(url('student/placement')) ?>">My placement</a>
    </div>
</div>

<?php if ($student !== null && $placement === null): ?>
    <section class="card apply-banner" data-tour="tasks">
        <div class="apply-banner-copy">
            <p class="eyebrow">Your next step</p>
            <h2>Apply for your internship in three steps</h2>
            <p>Everything happens on one page: upload your CV, rank up to five host organisations, then submit the list. The coordinator allocates one placement per student.</p>
            <a class="btn" href="<?= e(url('student/apply')) ?>">Open my application</a>
        </div>
        <ol class="apply-banner-list">
            <li class="<?= $cvOnFile ? 'is-done' : 'is-current' ?>">
                <span aria-hidden="true"><?= $cvOnFile ? '&#10003;' : '1' ?></span>
                <strong>CV</strong>
                <small><?= $cvOnFile ? 'On file' : 'Upload it first' ?></small>
            </li>
            <li class="<?= $prefs !== [] ? 'is-done' : ($cvOnFile ? 'is-current' : '') ?>">
                <span aria-hidden="true"><?= $prefs !== [] ? '&#10003;' : '2' ?></span>
                <strong>Rank hosts</strong>
                <small><?= count($prefs) ?> of 5 chosen</small>
            </li>
            <li class="<?= $prefsSubmitted ? 'is-done' : ($prefs !== [] ? 'is-current' : '') ?>">
                <span aria-hidden="true"><?= $prefsSubmitted ? '&#10003;' : '3' ?></span>
                <strong>Submit</strong>
                <small><?= $prefsSubmitted ? 'With the office' : 'For review' ?></small>
            </li>
        </ol>
    </section>
<?php endif; ?>

<div class="grid grid-4">
    <a class="card kpi kpi-link" href="<?= e(url('student/placement')) ?>" data-tour="kpis">
        <span class="kpi-label">Placement</span>
        <span class="kpi-value"><?= $placement ? e(ucfirst((string) $placement['status'])) : 'Pending' ?></span>
        <span class="kpi-note"><?= $placement ? e($placement['org_name']) : 'No placement allocated' ?></span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('student/logbook')) ?>">
        <span class="kpi-label">Logbook progress</span>
        <span class="kpi-value"><?= (int) $progress ?>%</span>
        <span class="kpi-note"><?= count($entries) ?> of <?= $placement ? (int) $placement['total_weeks'] : 12 ?> weeks started</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url($placement === null ? 'student/apply' : ($nextWeek !== null ? 'student/logbook/new' : 'student/logbook'))) ?>">
        <span class="kpi-label">Next entry due</span>
        <span class="kpi-value"><?= $dueDate ? e(date('d M', strtotime($dueDate))) : '—' ?></span>
        <span class="kpi-note"><?= $nextWeek ? 'Week ' . (int) $nextWeek : 'All weeks submitted' ?></span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('student/logbook')) ?>">
        <span class="kpi-label">Approved weeks</span>
        <span class="kpi-value"><?= $approved ?></span>
        <span class="kpi-note"><?= count($returned) ?> returned for rework</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
</div>

<?php if ($student !== null): ?>
    <?php $tracker = internship_progress((int) $student['student_id']); require APP_PATH . '/Views/shared/tracker.php'; ?>
<?php endif; ?>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>What you need to do now</h2><p>Only the things that need your attention.</p></div></div>
        <ul class="timeline">
            <?php foreach ($tasks as [$task, $route, $label]): ?>
                <li class="click-row" data-href="<?= e(url($route)) ?>">
                    <div><?= e($task) ?></div>
                    <a class="small" href="<?= e(url($route)) ?>"><?= e($label) ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Placement at a glance</h2><p>Who is responsible for your supervision.</p></div></div>
        <?php if ($placement === null): ?>
            <div class="empty"><strong>No placement yet</strong>Open <a href="<?= e(url('student/apply')) ?>">Apply for an internship</a> to upload your CV and rank host organisations. The internship office allocates the placement.</div>
        <?php else: ?>
            <div class="stat-list">
                <div class="row"><span>Organisation</span><strong><?= e($placement['org_name']) ?></strong></div>
                <div class="row"><span>District</span><strong><?= e($placement['district']) ?></strong></div>
                <div class="row"><span>Period</span><strong><?= e(fmt_date($placement['start_date'])) ?> – <?= e(fmt_date($placement['end_date'])) ?></strong></div>
                <div class="row"><span>Academic mentor</span><strong><?= e(trim(($placement['mentor_first'] ?? '') . ' ' . ($placement['mentor_last'] ?? ''))) ?></strong></div>
                <div class="row"><span>Host supervisor</span><strong><?= e(trim(($placement['sup_first'] ?? '') . ' ' . ($placement['sup_last'] ?? ''))) ?: 'Not yet assigned' ?></strong></div>
                <div class="row"><span>Status</span><strong><?= status_badge($placement['status']) ?></strong></div>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-head">
        <div><h2>Most recent feedback</h2><p>The latest comment from your supervisor or mentor.</p></div>
        <a class="btn btn-quiet btn-sm" href="<?= e(url('student/logbook')) ?>">Open logbook</a>
    </div>
    <?php if ($feedback === null): ?>
        <div class="empty"><strong>No feedback yet</strong>Comments appear here as soon as a reviewer writes one.</div>
    <?php else: ?>
        <div class="comment">
            <div class="who"><?= e($feedback['first_name'] . ' ' . $feedback['last_name']) ?> · <?= e($feedback['role_name']) ?></div>
            <div class="when"><?= e(fmt_dt($feedback['created_at'])) ?></div>
            <p><?= e($feedback['body']) ?></p>
        </div>
    <?php endif; ?>
</section>
