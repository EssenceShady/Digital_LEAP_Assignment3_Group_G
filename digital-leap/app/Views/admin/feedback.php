<?php
/** @var array $rows */
/** @var array $summary */
/** @var array $distribution */
$total = (int) ($summary['total'] ?? 0);
$average = $summary['average'] === null ? null : (float) $summary['average'];
$positive = (int) ($summary['positive'] ?? 0);
$negative = (int) ($summary['negative'] ?? 0);
$positivePct = $total > 0 ? (int) round($positive / $total * 100) : 0;
$maxRating = 1;
foreach ($distribution as $row) {
    $maxRating = max($maxRating, (int) $row['total']);
}
$words = [5 => 'Excellent', 4 => 'Good', 3 => 'Fair', 2 => 'Poor', 1 => 'Very poor'];
$byRating = [];
foreach ($distribution as $row) {
    $byRating[(int) $row['rating']] = (int) $row['total'];
}
?>
<div class="page-head">
    <div>
        <h1>User feedback</h1>
        <p>Ratings, thoughts and suggestions submitted from inside the workspace.</p>
    </div>
</div>

<div class="grid grid-4">
    <div class="card kpi"><span class="kpi-label">Responses</span><span class="kpi-value"><?= $total ?></span><span class="kpi-note">All feedback received</span></div>
    <div class="card kpi"><span class="kpi-label">Average rating</span><span class="kpi-value"><?= $average === null ? '—' : e(number_format($average, 1)) ?></span><span class="kpi-note">Out of 5 · <?= rating_stars((int) round($average ?? 0)) ?></span></div>
    <div class="card kpi"><span class="kpi-label">Positive</span><span class="kpi-value"><?= $positivePct ?>%</span><span class="kpi-note"><?= $positive ?> rated the system 4 or 5</span></div>
    <div class="card kpi"><span class="kpi-label">Needs attention</span><span class="kpi-value"><?= $negative ?></span><span class="kpi-note">Rated 1 or 2 — read the detail</span></div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Rating distribution</h2><p>How users scored the system out of five. Each row shows the number of responses and their share.</p></div></div>
        <?php if ($total === 0): ?>
            <div class="chart-empty">No ratings yet. The feedback button appears on every signed-in page.</div>
        <?php else: ?>
            <div class="chart-list">
                <?php foreach ([5, 4, 3, 2, 1] as $rating): ?>
                    <?php $count = $byRating[$rating] ?? 0; $share = (int) round($count / $total * 100); ?>
                    <div class="chart-row">
                        <span class="chart-label"><strong><?= $rating ?> / 5</strong> &middot; <?= e($words[$rating]) ?></span>
                        <div class="chart-track <?= $rating >= 4 ? 'tone-green' : ($rating <= 2 ? 'tone-ink' : '') ?>" aria-hidden="true"><span data-width="<?= (int) round($count / $maxRating * 100) ?>"></span></div>
                        <span class="chart-value"><strong><?= $count ?></strong><small><?= $share ?>%</small></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-legend">
                <span><i class="legend-swatch" aria-hidden="true"></i> Responses per rating</span>
                <span>Share of <?= $total ?> responses</span>
            </div>
            <div class="chart-foot"><span><?= $total ?> responses</span><span>Average <?= $average === null ? '—' : e(number_format($average, 1)) ?> / 5</span></div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>How to use this page</h2><p>Turn comments into the next iteration.</p></div></div>
        <ul class="timeline">
            <li><div><strong>Read the lowest ratings first.</strong></div><div class="when">They point at friction that stops people finishing a task.</div></li>
            <li><div><strong>Group suggestions by screen.</strong></div><div class="when">The page column shows where the feedback was written.</div></li>
            <li><div><strong>Close the loop.</strong></div><div class="when">Reply to the person or fix the flow, then watch the average move.</div></li>
        </ul>
        <p class="small muted">Feedback is visible to administrators only. Each entry is tied to the signed-in account so you can follow up, and the submission is written to the audit log.</p>
    </section>
</div>

<section class="card">
    <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="filter-bar">
        <input type="hidden" name="r" value="admin/feedback">
        <div class="field"><label for="rating-filter">Rating</label><select id="rating-filter" name="rating">
            <option value="0">All ratings</option>
            <?php foreach ([5, 4, 3, 2, 1] as $option): ?><option value="<?= $option ?>"<?= selected($ratingFilter, $option) ?>><?= $option ?> / 5</option><?php endforeach; ?>
        </select></div>
        <div class="field"><label for="screen">Page</label><select id="screen" name="screen">
            <option value="">All pages</option>
            <?php foreach ($screens as $screen): ?><option value="<?= e($screen['page']) ?>"<?= selected($pageFilter, $screen['page']) ?>><?= e($screen['page']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field field-actions"><button class="btn btn-outline" type="submit">Apply filters</button><a class="btn btn-quiet" href="<?= e(url('admin/feedback')) ?>">Reset</a></div>
    </form>
    <div class="card-head"><div><h2>Matching responses</h2><p><?= count($rows) ?> shown, newest first, up to 200 entries. Summary charts above include all feedback.</p></div></div>
    <?php if ($rows === []): ?>
        <div class="empty"><strong>No feedback matches</strong>Reset the filters to see all available feedback.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Received</th><th>User</th><th>Rating</th><th>Thoughts</th><th>Suggestions</th><th>Page</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td data-label="Received" class="nowrap"><?= e(fmt_dt($row['created_at'])) ?></td>
                        <td data-label="User">
                            <?= e(trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? '')) ?: 'Deleted user') ?>
                            <div class="small muted"><?= e((string) ($row['role_name'] ?? 'Unknown role')) ?></div>
                        </td>
                        <td data-label="Rating" class="nowrap"><?= rating_stars((int) $row['rating']) ?> <span class="small muted"><?= (int) $row['rating'] ?>/5</span></td>
                        <td data-label="Thoughts"><?= $row['thoughts'] !== null && $row['thoughts'] !== '' ? nl2br(e($row['thoughts'])) : '<span class="muted">—</span>' ?></td>
                        <td data-label="Suggestions"><?= $row['suggestions'] !== null && $row['suggestions'] !== '' ? nl2br(e($row['suggestions'])) : '<span class="muted">—</span>' ?></td>
                        <td data-label="Page" class="small muted"><?= e((string) ($row['page'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
