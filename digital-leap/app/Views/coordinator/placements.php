<?php
/** @var array $placements */
/** @var string $year */
/** @var string $status */
/** @var int $orgId */
/** @var array $orgs */
?>
<div class="page-head">
    <div>
        <h1>Placements</h1>
        <p><?= e($year) ?>. Filters apply to the academic year shown.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-quiet" href="<?= e(url('logbook/template')) ?>" target="_blank" rel="noopener">Log book template</a>
        <a class="btn btn-outline" href="<?= e(url('coordinator/applications')) ?>">Applications queue</a>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="filter-bar">
        <input type="hidden" name="r" value="coordinator/placements">
        <div class="field">
            <label for="year">Academic year</label>
            <select id="year" name="year">
                <option value="2026/2027"<?= selected($year, '2026/2027') ?>>2026/2027</option>
                <option value="2025/2026"<?= selected($year, '2025/2026') ?>>2025/2026</option>
            </select>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach (['proposed', 'approved', 'active', 'completed', 'terminated'] as $option): ?>
                    <option value="<?= e($option) ?>"<?= selected($status, $option) ?>><?= e(ucfirst($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="org_id">Organisation</label>
            <select id="org_id" name="org_id">
                <option value="0">All organisations</option>
                <?php foreach ($orgs as $org): ?>
                    <option value="<?= (int) $org['org_id'] ?>"<?= selected($orgId, $org['org_id']) ?>><?= e($org['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field-actions">
            <button class="btn btn-outline" type="submit">Apply filters</button>
            <a class="btn btn-quiet" href="<?= e(url('coordinator/placements')) ?>">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head"><div><h2>Placement register</h2><p><?= count($placements) ?> records.</p></div></div>
    <?php if ($placements === []): ?>
        <div class="empty"><strong>No placements match</strong>Change the filters or allocate a placement from the applications queue.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Student</th><th>Organisation</th><th>Period</th><th>Mentor</th><th>Supervisor</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($placements as $placement): ?>
                    <tr>
                        <td data-label="Student">
                            <div class="cell-person">
                                <?= avatar_html(['user_id' => (int) $placement['student_user'], 'first_name' => (string) $placement['first_name'], 'last_name' => (string) $placement['last_name']], 'avatar avatar-sm') ?>
                                <div><strong><?= e($placement['first_name'] . ' ' . $placement['last_name']) ?></strong><div class="small muted"><?= e($placement['reg_no']) ?></div></div>
                            </div>
                        </td>
                        <td data-label="Organisation"><?= e($placement['org_name']) ?><div class="small muted"><?= e(internship_type_label($placement['internship_type'] ?? 'external')) ?></div></td>
                        <td data-label="Period"><?= e(fmt_date($placement['start_date'])) ?> – <?= e(fmt_date($placement['end_date'])) ?><div class="small muted"><?= (int) $placement['total_weeks'] ?> weeks</div></td>
                        <td data-label="Mentor"><div class="cell-person"><?= avatar_html(['user_id' => (int) $placement['mentor_user'], 'first_name' => (string) $placement['mentor_first'], 'last_name' => (string) $placement['mentor_last']], 'avatar avatar-sm') ?><span><?= e($placement['mentor_first'] . ' ' . $placement['mentor_last']) ?></span></div></td>
                        <td data-label="Supervisor"><?= $placement['sup_first'] ? '<div class="cell-person">' . avatar_html(['user_id' => (int) $placement['sup_user'], 'first_name' => (string) $placement['sup_first'], 'last_name' => (string) $placement['sup_last']], 'avatar avatar-sm') . '<span>' . e($placement['sup_first'] . ' ' . $placement['sup_last']) . '</span></div>' : '<span class="badge badge-bad">Unassigned</span>' ?></td>
                        <td data-label="Status"><?= status_badge($placement['status']) ?></td>
                        <td data-label="Actions" class="right nowrap">
                            <a class="btn btn-sm btn-quiet" href="<?= e(url('coordinator/placements/view', ['id' => (int) $placement['placement_id']])) ?>">Open</a>
                            <a class="btn btn-sm btn-outline" href="<?= e(url('coordinator/letter', ['id' => (int) $placement['placement_id']])) ?>">Letter</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
