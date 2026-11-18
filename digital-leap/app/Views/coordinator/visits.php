<?php
/** @var array $visits */
/** @var array $overdue */
/** @var array $upcoming */
?>
<div class="page-head">
    <div>
        <h1>Site visits</h1>
        <p>Every scheduled and completed academic supervision visit.</p>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="filter-bar">
        <input type="hidden" name="r" value="coordinator/visits">
        <div class="field"><label for="q">Search</label><input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Student, registration or organisation"></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (['scheduled', 'completed', 'cancelled', 'missed'] as $option): ?><option value="<?= e($option) ?>"<?= selected($status, $option) ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label for="from">Scheduled from</label><input id="from" name="from" type="date" value="<?= e($from) ?>"></div>
        <div class="field"><label for="to">Scheduled to</label><input id="to" name="to" type="date" value="<?= e($to) ?>"></div>
        <div class="field field-actions"><button class="btn btn-outline" type="submit">Apply filters</button><a class="btn btn-quiet" href="<?= e(url('coordinator/visits')) ?>">Reset</a></div>
    </form>
</div>
<div class="grid grid-3">
    <div class="card kpi"><span class="kpi-label">Matching visits</span><span class="kpi-value"><?= count($visits) ?></span><span class="kpi-note">Current filters</span></div>
    <div class="card kpi"><span class="kpi-label">Overdue</span><span class="kpi-value"><?= count($overdue) ?></span><span class="kpi-note">Scheduled before today</span></div>
    <div class="card kpi"><span class="kpi-label">Upcoming</span><span class="kpi-value"><?= count($upcoming) ?></span><span class="kpi-note">Scheduled today or later</span></div>
</div>

<?php if ($overdue !== []): ?>
    <div class="alert alert-error" role="alert"><?= count($overdue) ?> visits are past their scheduled date and still marked scheduled.</div>
<?php endif; ?>

<div class="card">
    <div class="card-head"><div><h2>Visit register</h2></div></div>
    <?php if ($visits === []): ?>
        <div class="empty"><strong>No visits match</strong>Reset the filters to see all recorded visits.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Student</th><th>Organisation</th><th>Mentor</th><th>Scheduled</th><th>Mode</th><th>Status</th><th>Outcome</th></tr></thead>
                <tbody>
                <?php foreach ($visits as $visit): ?>
                    <tr>
                        <td data-label="Student"><strong><?= e($visit['first_name'] . ' ' . $visit['last_name']) ?></strong><div class="small muted"><?= e($visit['reg_no']) ?></div></td>
                        <td data-label="Organisation"><?= e($visit['org_name']) ?></td>
                        <td data-label="Mentor"><?= e($visit['mentor_first'] . ' ' . $visit['mentor_last']) ?></td>
                        <td data-label="Scheduled"><?= e(fmt_date($visit['scheduled_date'])) ?><?= $visit['status'] === 'scheduled' && $visit['scheduled_date'] < date('Y-m-d') ? ' <span class="badge badge-bad">Overdue</span>' : '' ?></td>
                        <td data-label="Mode"><?= e(ucfirst((string) $visit['mode'])) ?></td>
                        <td data-label="Status"><?= status_badge($visit['status']) ?></td>
                        <td data-label="Outcome"><?= $visit['findings'] ? e($visit['findings']) : '<span class="muted small">Pending</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
