<?php
/** @var array $entries */
?>
<div class="page-head">
    <div>
        <h1>Logbook review</h1>
        <p>Read and comment on any week belonging to your assigned students. Commenting on an approved week marks it mentor-reviewed.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('logbook/template')) ?>" target="_blank" rel="noopener">Log book template</a>
    </div>
</div>

<div class="card">
    <div class="card-head"><div><h2>Entries</h2><p><?= count($entries) ?> entries across your students.</p></div></div>
    <?php if ($entries === []): ?>
        <div class="empty"><strong>No entries yet</strong>Students appear here as soon as they record their first week.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Student</th><th>Week</th><th>Dates</th><th>Hours</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td data-label="Student"><strong><?= e($entry['first_name'] . ' ' . $entry['last_name']) ?></strong><div class="small muted"><?= e($entry['reg_no']) ?></div></td>
                        <td data-label="Week">Week <?= (int) $entry['week_no'] ?></td>
                        <td data-label="Dates"><?= e(fmt_date($entry['date_from'])) ?> – <?= e(fmt_date($entry['date_to'])) ?></td>
                        <td data-label="Hours"><?= e(rtrim(rtrim(number_format((float) $entry['hours'], 1), '0'), '.')) ?></td>
                        <td data-label="Status"><?= status_badge($entry['status']) ?></td>
                        <td class="right"><a class="btn btn-sm btn-quiet" href="<?= e(url('mentor/entry', ['id' => (int) $entry['entry_id']])) ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
