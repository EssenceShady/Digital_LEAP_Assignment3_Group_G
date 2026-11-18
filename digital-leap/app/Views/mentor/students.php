<?php
/** @var array $placements */
/** @var array $stats */
?>
<div class="page-head">
    <div>
        <h1>My students</h1>
        <p>Every student you supervise academically, with their logbook and visit position.</p>
    </div>
    <div class="page-actions"><a class="btn" href="<?= e(url('mentor/visits/form')) ?>">Schedule a visit</a></div>
</div>

<div class="card">
    <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="filter-bar">
        <input type="hidden" name="r" value="mentor/students">
        <div class="field"><label for="q">Student</label><input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Name or registration number"></div>
        <div class="field"><label for="status">Placement status</label><select id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (['proposed', 'approved', 'active', 'completed', 'terminated'] as $option): ?>
                <option value="<?= e($option) ?>"<?= selected($status, $option) ?>><?= e(ucfirst($option)) ?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="field"><label for="org_id">Organisation</label><select id="org_id" name="org_id">
            <option value="0">All assigned organisations</option>
            <?php foreach ($orgs as $org): ?><option value="<?= (int) $org['org_id'] ?>"<?= selected($orgId, $org['org_id']) ?>><?= e($org['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field field-actions"><button class="btn btn-outline" type="submit">Apply filters</button><a class="btn btn-quiet" href="<?= e(url('mentor/students')) ?>">Reset</a></div>
    </form>
    <p class="hint"><?= count($placements) ?> assigned placements match.</p>
</div>
<?php if ($placements === []): ?>
    <div class="card"><div class="empty"><strong>No students match</strong>Reset the filters to see all assigned students.</div></div>
<?php else: ?>
    <div class="grid grid-2">
        <?php foreach ($placements as $placement): $stat = $stats[(int) $placement['placement_id']]; ?>
            <section class="card">
                <div class="card-head">
                    <div class="cell-person">
                        <?= avatar_html(['user_id' => (int) $placement['student_user'], 'first_name' => (string) $placement['first_name'], 'last_name' => (string) $placement['last_name']], 'avatar avatar-sm') ?>
                        <div><h2><?= e($placement['first_name'] . ' ' . $placement['last_name']) ?></h2><p><?= e($placement['reg_no']) ?> · <?= e($placement['org_name']) ?></p></div>
                    </div>
                    <?= status_badge($placement['status']) ?>
                </div>
                <div class="stat-list">
                    <div class="row"><span>Period</span><strong><?= e(fmt_date($placement['start_date'])) ?> – <?= e(fmt_date($placement['end_date'])) ?></strong></div>
                    <div class="row"><span>Approved weeks</span><strong><?= (int) $stat['approved'] ?></strong></div>
                    <div class="row"><span>Awaiting supervisor</span><strong><?= (int) $stat['submitted'] ?></strong></div>
                    <div class="row"><span>Scheduled visits</span><strong><?= (int) $stat['visits'] ?></strong></div>
                </div>
                <div class="form-actions">
                    <a class="btn btn-sm btn-outline" href="<?= e(url('mentor/logbook')) ?>">Review logbook</a>
                    <a class="btn btn-sm btn-quiet" href="<?= e(url('mentor/evaluation', ['placement_id' => (int) $placement['placement_id']])) ?>">Evaluate</a>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
