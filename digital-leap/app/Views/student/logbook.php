<?php
/** @var array|null $placement */
/** @var array $entries */
/** @var int|null $nextWeek */
/** @var string|null $dueDate */
/** @var string|null $dueDay */
$byWeek = [];
foreach ($entries as $entry) {
    $byWeek[(int) $entry['week_no']] = $entry;
}
?>
<div class="page-head">
    <div>
        <h1>Weekly log book (Form E &mdash; 50%)</h1>
        <p>One entry per week, recorded day by day as in the paper log book. The log book is 50% of Practical Internship in Form E. Submit by <?= e($dueDay ?: 'Friday') ?> so your supervisor has time to review it.</p>
    </div>
    <div class="page-actions">
        <?php if ($placement !== null && $placement['status'] === 'active'): ?>
            <a class="btn" href="<?= e(url('student/logbook/new')) ?>">New entry</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= e(url('student/placement')) ?>">Placement</a>
    </div>
</div>

<?php if ($placement === null): ?>
    <div class="card">
        <div class="empty"><strong>You need a placement first</strong>A logbook is attached to an active placement.</div>
    </div>
<?php else: ?>
    <div class="grid grid-3">
        <div class="card kpi">
            <span class="kpi-label">Weeks recorded</span>
            <span class="kpi-value"><?= count($entries) ?>/<?= (int) $placement['total_weeks'] ?></span>
            <span class="kpi-note">Placement started <?= e(fmt_date($placement['start_date'])) ?></span>
        </div>
        <div class="card kpi">
            <span class="kpi-label">Next entry</span>
            <span class="kpi-value"><?= $nextWeek ? 'Week ' . (int) $nextWeek : 'Complete' ?></span>
            <span class="kpi-note"><?= $dueDate ? 'Due ' . e(fmt_date($dueDate)) : 'All weeks recorded' ?></span>
        </div>
        <div class="card kpi">
            <span class="kpi-label">Returned</span>
            <span class="kpi-value"><?= count(array_filter($entries, static fn (array $e): bool => $e['status'] === 'returned')) ?></span>
            <span class="kpi-note">Fix and resubmit these weeks</span>
        </div>
    </div>

    <?php if ($entries === []): ?>
        <div class="card">
            <div class="empty">
                <strong>No logbook entries yet</strong>
                Start with week 1. Record each day's tasks and hours, then close the week with a summary.
                <div class="form-actions"><a class="btn" href="<?= e(url('student/logbook/new')) ?>">Write week 1</a></div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-head"><div><h2>All weeks</h2><p>Draft and returned entries can be edited.</p></div></div>
            <div class="table-wrap">
                <table class="table table--stack">
                    <thead><tr><th>Week</th><th>Dates</th><th>Hours</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($entries as $entry): ?>
                        <tr>
                            <td data-label="Week"><strong>Week <?= (int) $entry['week_no'] ?></strong></td>
                            <td data-label="Dates"><?= e(fmt_date($entry['date_from'])) ?> – <?= e(fmt_date($entry['date_to'])) ?></td>
                            <td data-label="Hours"><?= e(fmt_hours($entry['hours'])) ?></td>
                            <td data-label="Status"><?= status_badge($entry['status']) ?></td>
                            <td data-label="Submitted"><?= e(fmt_date($entry['submitted_at'])) ?></td>
                            <td data-label="Actions" class="right nowrap">
                                <a class="btn btn-sm btn-quiet" href="<?= e(url('student/logbook/view', ['id' => (int) $entry['entry_id']])) ?>">Open</a>
                                <?php if (in_array($entry['status'], ['draft', 'returned'], true)): ?>
                                    <a class="btn btn-sm btn-outline" href="<?= e(url('student/logbook/new', ['week' => (int) $entry['week_no']])) ?>">Edit</a>
                                    <form method="post" action="<?= e(url('student/logbook/submit')) ?>" class="inline-form" data-confirm="Submit week <?= (int) $entry['week_no'] ?> to your host supervisor?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="entry_id" value="<?= (int) $entry['entry_id'] ?>">
                                        <button class="btn btn-sm" type="submit">Submit</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<section class="card">
    <div class="card-head">
        <div>
            <h2>Official log book template</h2>
            <p>The Limkokwing internship log book from the internship guidebook: a cover page, the guidelines, one page for each of the twelve practical weeks, the weekly summary and the programme structure.</p>
        </div>
    </div>
    <p class="small muted">Your industry supervisor signs and stamps each weekly page. Submit the bound log book with the internship report and Forms D1 and D2 by the deadline on the cover.</p>
    <div class="form-actions">
        <a class="btn btn-outline" href="<?= e(url('logbook/template')) ?>" target="_blank" rel="noopener">View the template</a>
        <a class="btn" href="<?= e(url('logbook/template', ['download' => 1])) ?>">Download the PDF</a>
    </div>
</section>
