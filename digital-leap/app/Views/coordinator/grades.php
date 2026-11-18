<?php
/** @var array $rows */
/** @var string $year */
?>
<div class="page-head">
    <div>
        <h1>Grade sheet</h1>
        <p><?= e($year) ?>. Compute from source records, moderate with a reason, then publish.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(($GLOBALS['base'] ?? '') . '/index.php?r=management/export&type=grades&year=' . urlencode($year)) ?>">Export CSV</a>
        <a class="btn" href="<?= e(url('coordinator/grades/print', ['year' => $year])) ?>">Printable sheet</a>
    </div>
</div>

<div class="card">
    <div class="card-head"><div><h2>Placements</h2><p><?= count($rows) ?> records for the year.</p></div></div>
    <div class="table-wrap">
        <table class="table table--stack">
            <thead><tr><th>Student</th><th>Organisation</th><th class="right">Form D</th><th class="right">Self</th><th class="right">Report</th><th class="right">Career Fair</th><th class="right">Total</th><th>Mark</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td data-label="Student"><strong><?= e($row['first_name'] . ' ' . $row['last_name']) ?></strong><div class="small muted"><?= e($row['reg_no']) ?></div></td>
                    <td data-label="Organisation"><?= e($row['org_name']) ?></td>
                    <td data-label="Form D" class="right"><?= $row['supervisor_total'] !== null ? e(number_format((float) $row['supervisor_total'], 1)) . '%' : '<span class="badge badge-warn">Pending</span>' ?></td>
                    <td data-label="Self" class="right"><?= $row['self_total'] !== null ? e(number_format((float) $row['self_total'], 1)) . '%' : '<span class="badge badge-warn">Pending</span>' ?></td>
                    <td data-label="Report" class="right"><?= $row['report_status'] === 'assessed' ? e(number_format((float) $row['report_score'], 1)) . '%' : '<span class="badge badge-warn">Pending</span>' ?></td>
                    <td data-label="Career Fair" class="right"><?= $row['career_fair_score'] !== null ? e(number_format((float) $row['career_fair_score'], 1)) . '%' : '<span class="badge badge-warn">Pending</span>' ?></td>
                    <td data-label="Total" class="right"><?= $row['weighted_total'] !== null ? '<strong>' . e(number_format((float) $row['weighted_total'], 1)) . '%</strong>' : '—' ?></td>
                    <td data-label="Mark"><?= $row['mark_status'] ? status_badge($row['mark_status']) : '<span class="muted small">Not computed</span>' ?><?= $row['grade_letter'] ? ' <strong>' . e($row['grade_letter']) . '</strong>' : '' ?></td>
                    <td class="right"><a class="btn btn-sm <?= $row['mark_status'] ? 'btn-quiet' : 'btn' ?>" href="<?= e(url('coordinator/grades/view', ['placement_id' => (int) $row['placement_id']])) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="small muted">Final mark: Practical Internship (100 marks: logbook 50, Form D 40, self evaluation 10) + Internship Report (75 marks) + Career Fair (25 marks), divided by 200. The academic mentor's evaluation is advisory and is not part of the mark.</p>
</div>
