<?php
/** @var array $placements */
/** @var array $entries */
/** @var array $evaluations */
?>
<div class="page-head">
    <div>
        <h1>Logbook queue (Form E &mdash; 50%)</h1>
        <p>The weekly log book is the diary half of Form E. Entries from your assigned students appear here newest submission first.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('logbook/template')) ?>" target="_blank" rel="noopener">Log book template</a>
    </div>
</div>

<?php if ($placements === []): ?>
    <div class="card"><div class="empty"><strong>No students assigned</strong>The internship coordinator assigns host supervisors during allocation.</div></div>
<?php else: ?>
    <div class="grid grid-3">
        <?php foreach ($placements as $placement): ?>
            <section class="card">
                <div class="card-head"><div><h2><?= e($placement['first_name'] . ' ' . $placement['last_name']) ?></h2><p><?= e($placement['reg_no']) ?> · <?= e($placement['org_name']) ?></p></div></div>
                <div class="stat-list">
                    <div class="row"><span>Placement</span><strong><?= status_badge($placement['status']) ?></strong></div>
                    <div class="row"><span>Period</span><strong><?= e(fmt_date($placement['start_date'])) ?> – <?= e(fmt_date($placement['end_date'])) ?></strong></div>
                    <div class="row"><span>Supervisor evaluation</span><strong><?= $evaluations[(int) $placement['placement_id']] ? status_badge($evaluations[(int) $placement['placement_id']]['status']) : 'Not started' ?></strong></div>
                </div>
                <div class="form-actions">
                    <a class="btn btn-sm btn-outline" href="<?= e(url('supervisor/evaluation', ['placement_id' => (int) $placement['placement_id']])) ?>">Open evaluation</a>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="filter-bar">
            <input type="hidden" name="r" value="supervisor/queue">
            <div class="field"><label for="status">Status</label><select id="status" name="status">
                <option value=""<?= selected($status, '') ?>>All submitted work</option>
                <?php foreach (['submitted', 'approved', 'returned', 'mentor_reviewed'] as $option): ?>
                    <option value="<?= e($option) ?>"<?= selected($status, $option) ?>><?= e(ucfirst(str_replace('_', ' ', $option))) ?></option>
                <?php endforeach; ?>
            </select></div>
            <div class="field"><label for="week">Week</label><input id="week" name="week" type="number" min="1" value="<?= $week > 0 ? $week : '' ?>" placeholder="Any week"></div>
            <div class="field field-actions"><button class="btn btn-outline" type="submit">Apply filters</button><a class="btn btn-quiet" href="<?= e(url('supervisor/queue')) ?>">Reset</a></div>
        </form>
    </div>
    <section class="card">
        <div class="card-head"><div><h2><?= $status === 'submitted' ? 'Awaiting your review' : 'Logbook entries' ?></h2><p><?= count($entries) ?> matching entries. Unsubmitted drafts are not included.</p></div></div>
        <?php if ($entries === []): ?>
            <div class="empty"><strong>No entries match</strong>Change the filters or wait for a student submission.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--stack">
                    <thead><tr><th>Student</th><th>Week</th><th>Dates</th><th>Hours</th><th>Submitted</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td data-label="Student"><strong><?= e($entry['first_name'] . ' ' . $entry['last_name']) ?></strong><div class="small muted"><?= e($entry['reg_no']) ?></div></td>
                            <td data-label="Week">Week <?= (int) $entry['week_no'] ?><div><?= status_badge($entry['status']) ?></div></td>
                            <td data-label="Dates"><?= e(fmt_date($entry['date_from'])) ?> – <?= e(fmt_date($entry['date_to'])) ?></td>
                            <td data-label="Hours"><?= e(fmt_hours($entry['hours'])) ?></td>
                            <td data-label="Submitted"><?= e(fmt_dt($entry['submitted_at'])) ?></td>
                            <td class="right"><a class="btn btn-sm" href="<?= e(url('supervisor/entry', ['id' => (int) $entry['entry_id']])) ?>">Review</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
