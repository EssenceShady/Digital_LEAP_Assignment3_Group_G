<?php
/** @var int $pendingApplications */
/** @var int $awaitingAllocation */
/** @var int $withoutSupervisor */
/** @var int $awaitingPublication */
/** @var int $overdue */
/** @var array $applications */
/** @var array $unassigned */
?>
<div class="page-head">
    <div>
        <h1>Coordinator dashboard</h1>
        <p>Everything that needs a decision: applications, allocations, missing supervisors, overdue logbooks and grades.</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="<?= e(url('coordinator/applications')) ?>">Applications</a>
        <a class="btn btn-outline" href="<?= e(url('coordinator/grades')) ?>">Grade sheet</a>
    </div>
</div>

<div class="grid grid-3">
    <a class="card kpi kpi-link" href="<?= e(url('coordinator/applications')) ?>" data-tour="kpis">
        <span class="kpi-label">Applications to review</span>
        <span class="kpi-value"><?= $pendingApplications ?></span>
        <span class="kpi-note">Submitted and unprocessed</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('coordinator/placements')) ?>">
        <span class="kpi-label">Placements to approve</span>
        <span class="kpi-value"><?= $awaitingAllocation ?></span>
        <span class="kpi-note">Still in proposed state</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('coordinator/placements')) ?>">
        <span class="kpi-label">Without a supervisor</span>
        <span class="kpi-value"><?= $withoutSupervisor ?></span>
        <span class="kpi-note">Invite the host supervisor</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('coordinator/placements')) ?>">
        <span class="kpi-label">Overdue logbooks</span>
        <span class="kpi-value"><?= $overdue ?></span>
        <span class="kpi-note">Active placements behind schedule</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('coordinator/grades')) ?>">
        <span class="kpi-label">Grades awaiting release</span>
        <span class="kpi-value"><?= $awaitingPublication ?></span>
        <span class="kpi-note">Computed but not published</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <div class="card kpi">
        <span class="kpi-label">This academic year</span>
        <span class="kpi-value"><?= e(date('Y')) ?></span>
        <span class="kpi-note">Reporting period in use</span>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head">
            <div><h2>New applications</h2><p>Each student ranks up to five host organisations. You make the final allocation.</p></div>
        </div>
        <?php if ($applications === []): ?>
            <div class="empty"><strong>No new applications</strong>Everything submitted has been processed.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--stack">
                    <thead><tr><th>Student</th><th>Host</th><th>Rank</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($applications as $a): ?>
                        <tr class="click-row" data-href="<?= e(url('coordinator/applications/view', ['student_id' => (int) $a['student_id']])) ?>">
                            <td data-label="Student"><?= e($a['first_name'] . ' ' . $a['last_name']) ?><br><small class="muted"><?= e($a['reg_no']) ?></small></td>
                            <td data-label="Host"><?= e($a['org_name']) ?></td>
                            <td data-label="Rank"><?= (int) $a['preference_rank'] ?></td>
                            <td class="right" data-label="Action"><a class="btn btn-sm btn-outline" href="<?= e(url('coordinator/applications/view', ['student_id' => (int) $a['student_id']])) ?>">Open</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head">
            <div><h2>Placements without a supervisor</h2><p>Send an invitation link to the host organisation.</p></div>
        </div>
        <?php if ($unassigned === []): ?>
            <div class="empty"><strong>All placements covered</strong>Every active placement has a host supervisor.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--stack">
                    <thead><tr><th>Student</th><th>Host</th><th>Start</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($unassigned as $p): ?>
                        <tr class="click-row" data-href="<?= e(url('coordinator/placements/view', ['id' => $p['placement_id']])) ?>">
                            <td data-label="Student"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></td>
                            <td data-label="Host"><?= e($p['org_name']) ?></td>
                            <td data-label="Start"><?= e(fmt_date($p['start_date'])) ?></td>
                            <td class="right" data-label="Action"><a class="btn btn-sm btn-outline" href="<?= e(url('coordinator/placements/view', ['id' => $p['placement_id']])) ?>">Assign</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
