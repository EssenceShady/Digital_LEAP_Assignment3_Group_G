<?php
/** @var array $placements */
/** @var array $queue */
/** @var array $evaluations */
?>
<div class="page-head">
    <div>
        <h1>Supervisor dashboard</h1>
        <p>Review the Form E weekly log book of your assigned students and complete their Form D evaluation (D1 at week 6, D2 at week 12).</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('supervisor/evaluation')) ?>">Form D evaluation</a>
    </div>
</div>

<div class="grid grid-3">
    <a class="card kpi kpi-link" href="<?= e(url('supervisor/queue')) ?>" data-tour="kpis">
        <span class="kpi-label">Students assigned</span>
        <span class="kpi-value"><?= count($placements) ?></span>
        <span class="kpi-note">Current and previous placements</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('supervisor/queue')) ?>">
        <span class="kpi-label">Awaiting your review</span>
        <span class="kpi-value"><?= count($queue) ?></span>
        <span class="kpi-note">Submitted logbook entries</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('supervisor/evaluation')) ?>">
        <span class="kpi-label">Evaluations in draft</span>
        <span class="kpi-value"><?= count($evaluations) ?></span>
        <span class="kpi-note">Complete before the placement ends</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
</div>

<section class="card">
    <div class="card-head">
        <div><h2>Logbooks waiting for review</h2><p>Approve the week or return it with a clear reason.</p></div>
    </div>
    <?php if ($queue === []): ?>
        <div class="empty"><strong>Nothing waiting</strong>All submitted entries have been reviewed.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Student</th><th>Reg. no.</th><th>Week</th><th>Submitted</th><th>Hours</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($queue as $row): ?>
                    <tr class="click-row" data-href="<?= e(url('supervisor/entry', ['id' => $row['entry_id']])) ?>">
                        <td data-label="Student"><?= e($row['first_name'] . ' ' . $row['last_name']) ?></td>
                        <td data-label="Reg. no."><?= e($row['reg_no']) ?></td>
                        <td data-label="Week">Week <?= (int) $row['week_no'] ?></td>
                        <td data-label="Submitted"><?= e(fmt_dt($row['submitted_at'])) ?></td>
                        <td data-label="Hours"><?= e($row['hours'] ?? '—') ?></td>
                        <td class="right"><a class="btn btn-sm" href="<?= e(url('supervisor/entry', ['id' => $row['entry_id']])) ?>">Review</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>My students</h2></div></div>
        <?php if ($placements === []): ?>
            <div class="empty"><strong>No students assigned</strong>The internship office links you to a student when it allocates a placement.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--stack">
                    <thead><tr><th>Student</th><th>Organisation</th><th>Period</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($placements as $p): ?>
                        <tr>
                            <td data-label="Student"><?= e($p['first_name'] . ' ' . $p['last_name']) ?><br><small class="muted"><?= e($p['reg_no']) ?></small></td>
                            <td data-label="Organisation"><?= e($p['org_name']) ?></td>
                            <td data-label="Period"><?= e(fmt_date($p['start_date'])) ?><br><small class="muted">to <?= e(fmt_date($p['end_date'])) ?></small></td>
                            <td data-label="Status"><?= status_badge($p['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Evaluations to complete</h2><p>Weighted criteria, saved as draft until you submit.</p></div></div>
        <?php if ($evaluations === []): ?>
            <div class="empty"><strong>No draft evaluations</strong>You can start one from the Evaluation page.</div>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($evaluations as $e): ?>
                    <li>
                        <div><strong><?= e($e['first_name'] . ' ' . $e['last_name']) ?></strong> · <?= status_badge($e['evaluation_type']) ?></div>
                        <a class="small" href="<?= e(url('supervisor/evaluation', ['id' => $e['evaluation_id']])) ?>">Continue evaluation</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
