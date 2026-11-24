<?php
/** @var array $placements */
/** @var array $visits */
/** @var array $reports */
$pendingTotal = 0;
foreach ($placements as $p) {
    $pendingTotal += (int) $p['pending'];
}
?>
<div class="page-head">
    <div>
        <h1>Mentor dashboard</h1>
        <p>Your students, their Form E log books, the visits you have scheduled and the Form C reports awaiting assessment.</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="<?= e(url('mentor/visits/form')) ?>">Schedule a visit</a>
    </div>
</div>

<div class="grid grid-3">
    <a class="card kpi kpi-link" href="<?= e(url('mentor/students')) ?>" data-tour="kpis">
        <span class="kpi-label">Students supervised</span>
        <span class="kpi-value"><?= count($placements) ?></span>
        <span class="kpi-note">Across all academic years</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('mentor/logbook')) ?>">
        <span class="kpi-label">Logbooks to review</span>
        <span class="kpi-value"><?= $pendingTotal ?></span>
        <span class="kpi-note">Submitted and waiting</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('mentor/visits')) ?>">
        <span class="kpi-label">Visits scheduled</span>
        <span class="kpi-value"><?= count($visits) ?></span>
        <span class="kpi-note"><?= count($reports) ?> report(s) to assess</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
</div>

<section class="card">
    <div class="card-head">
        <div><h2>Upcoming site visits</h2><p>Complete the visit record on the day and upload the report.</p></div>
        <a class="btn btn-quiet btn-sm" href="<?= e(url('mentor/visits')) ?>">All visits</a>
    </div>
    <?php if ($visits === []): ?>
        <div class="empty"><strong>No visits scheduled</strong>Schedule one for each student at least once per placement.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Date</th><th>Student</th><th>Organisation</th><th>Mode</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($visits as $v): ?>
                    <tr class="click-row" data-href="<?= e(url('mentor/visits', ['id' => $v['visit_id']])) ?>">
                        <td data-label="Date"><?= e(fmt_date($v['scheduled_date'])) ?></td>
                        <td data-label="Student"><?= e($v['first_name'] . ' ' . $v['last_name']) ?></td>
                        <td data-label="Organisation"><?= e($v['org_name']) ?></td>
                        <td data-label="Mode"><?= e(ucfirst((string) $v['mode'])) ?></td>
                        <td data-label="Status"><?= status_badge($v['status']) ?></td>
                        <td class="right"><a class="btn btn-sm btn-outline" href="<?= e(url('mentor/visits', ['id' => $v['visit_id']])) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><div><h2>My students</h2></div></div>
    <?php if ($placements === []): ?>
        <div class="empty"><strong>No students assigned</strong>The coordinator assigns students when a placement is created.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Student</th><th>Organisation</th><th>Period</th><th>Logbooks pending</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($placements as $p): ?>
                    <tr>
                        <td data-label="Student"><?= e($p['first_name'] . ' ' . $p['last_name']) ?><br><small class="muted"><?= e($p['reg_no']) ?></small></td>
                        <td data-label="Organisation"><?= e($p['org_name']) ?></td>
                        <td data-label="Period"><?= e(fmt_date($p['start_date'])) ?> – <?= e(fmt_date($p['end_date'])) ?></td>
                        <td data-label="Logbooks pending"><?= (int) $p['pending'] ?></td>
                        <td data-label="Status"><?= status_badge($p['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
