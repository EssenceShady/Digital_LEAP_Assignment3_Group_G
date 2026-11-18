<?php
/** @var string $year */
/** @var array $summary */
/** @var array $byOrg */
/** @var array $submission */
/** @var array $grades */
/** @var array $visits */
$weekEntries = [];
foreach ($submission as $row) {
    $weekEntries[(int) $row['week_no']] = (int) $row['entries'];
}
$maxWeek = max(1, $weekEntries === [] ? 0 : max($weekEntries));
$totalWeekEntries = array_sum($weekEntries);
$weeksWithEntries = count(array_filter($weekEntries));

$maxOrg = 1;
$totalOrgPlacements = 0;
foreach ($byOrg as $row) {
    $maxOrg = max($maxOrg, (int) $row['total']);
    $totalOrgPlacements += (int) $row['total'];
}

$maxGrade = 1;
$totalGrades = 0;
foreach ($grades as $row) {
    $maxGrade = max($maxGrade, (int) $row['total']);
    $totalGrades += (int) $row['total'];
}

$maxVisit = 1;
$totalVisits = 0;
foreach ($visits as $row) {
    $maxVisit = max($maxVisit, (int) $row['total']);
    $totalVisits += (int) $row['total'];
}
$visitLabels = ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'missed' => 'Missed'];
?>
<div class="page-head">
    <div>
        <h1>Quality indicators</h1>
        <p><?= e($year) ?>. Placement coverage, completion, assessment and timeliness.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('management/export', ['year' => $year])) ?>">Export CSV</a>
        <a class="btn" href="<?= e(url('management/print', ['year' => $year])) ?>">Printable report</a>
    </div>
</div>

<div class="grid grid-4">
    <div class="card kpi"><span class="kpi-label">Placements</span><span class="kpi-value"><?= (int) $summary['placements'] ?></span><span class="kpi-note"><?= (int) $summary['active'] ?> active · <?= (int) $summary['completed'] ?> completed</span></div>
    <div class="card kpi"><span class="kpi-label">Completion rate</span><span class="kpi-value"><?= e(number_format((float) $summary['completion'], 1)) ?>%</span><span class="kpi-note">Completed ÷ total placements</span></div>
    <div class="card kpi"><span class="kpi-label">Average final mark</span><span class="kpi-value"><?= $summary['average'] === null ? '—' : e(number_format((float) $summary['average'], 1)) . '%' ?></span><span class="kpi-note"><?= (int) $summary['published'] ?> published marks</span></div>
    <div class="card kpi"><span class="kpi-label">Overdue logbooks</span><span class="kpi-value"><?= (int) $summary['overdue'] ?></span><span class="kpi-note">Active placements behind schedule</span></div>
    <div class="card kpi"><span class="kpi-label">Outstanding visits</span><span class="kpi-value"><?= (int) $summary['outstandingVisits'] ?></span><span class="kpi-note">Still marked scheduled</span></div>
    <div class="card kpi"><span class="kpi-label">Logbook entries</span><span class="kpi-value"><?= (int) $summary['entries'] ?></span><span class="kpi-note">Submitted or reviewed</span></div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Logbook submission by week</h2><p>Entries received per teaching week. The shape shows whether students are keeping pace.</p></div></div>
        <?php if ($submission === []): ?>
            <div class="empty"><strong>No submissions</strong>No logbook entries have been submitted for the year.</div>
        <?php else: ?>
            <div class="chart-cols" role="img" aria-label="Bar chart of logbook entries received in each of the twelve teaching weeks. The number above each bar is the count for that week.">
                <?php foreach (range(1, 12) as $week): ?>
                    <?php
                    $entries = $weekEntries[$week] ?? 0;
                    $height = $entries === 0 ? 2 : max(4, (int) round($entries / $maxWeek * 100));
                    ?>
                    <div class="chart-col">
                        <span class="chart-col-value"><?= $entries ?></span>
                        <div class="chart-col-track"><span class="chart-col-bar" data-height="<?= $height ?>"></span></div>
                        <span class="chart-col-label">W<?= $week ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-legend">
                <span><i class="legend-swatch" aria-hidden="true"></i> Entries received</span>
                <span>Numbers above the bars are counts</span>
            </div>
            <div class="chart-foot">
                <span><?= $weeksWithEntries ?> of 12 weeks have entries</span>
                <span>Peak <?= $maxWeek ?> in one week &middot; <?= $totalWeekEntries ?> in total</span>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Grade distribution</h2><p>Published marks only, grouped by classification. Each row shows students and the share of published results.</p></div></div>
        <?php if ($totalGrades === 0): ?>
            <div class="chart-empty">No marks have been published yet.</div>
        <?php else: ?>
            <div class="chart-list">
                <?php foreach ($grades as $row): ?>
                    <?php $count = (int) $row['total']; $share = (int) round($count / $totalGrades * 100); ?>
                    <div class="chart-row">
                        <span class="chart-label"><strong>Grade <?= e($row['grade_letter']) ?></strong> &middot; <?= e($row['classification']) ?></span>
                        <div class="chart-track <?= in_array($row['grade_letter'], ['A', 'B'], true) ? 'tone-green' : (in_array($row['grade_letter'], ['D', 'F'], true) ? 'tone-ink' : 'tone-blue') ?>" aria-hidden="true"><span data-width="<?= (int) round($count / $maxGrade * 100) ?>"></span></div>
                        <span class="chart-value"><strong><?= $count ?></strong><small><?= $share ?>%</small></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-legend">
                <span><i class="legend-swatch" aria-hidden="true"></i> Students per grade</span>
                <span>Share of <?= $totalGrades ?> published results</span>
            </div>
            <div class="chart-foot"><span>Published grades</span><span>Longest bar = <?= $maxGrade ?> students</span></div>
        <?php endif; ?>
    </section>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Placements by organisation</h2><p>Drill-down for the year.</p></div></div>
        <?php if ($byOrg === []): ?>
            <div class="empty"><strong>No placements</strong>Nothing has been allocated for <?= e($year) ?>.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table class="table table--stack">
                    <thead><tr><th>Organisation</th><th>District</th><th>Share</th><th class="right">Total</th><th class="right">Active</th><th class="right">Completed</th></tr></thead>
                    <tbody>
                    <?php foreach ($byOrg as $row): ?>
                        <?php $share = $totalOrgPlacements > 0 ? (int) round((int) $row['total'] / $totalOrgPlacements * 100) : 0; ?>
                        <tr>
                            <td data-label="Organisation"><?= e($row['name']) ?></td>
                            <td data-label="District"><?= e($row['district']) ?></td>
                            <td data-label="Share">
                                <div class="chart-share">
                                    <div class="chart-track" aria-hidden="true"><span data-width="<?= (int) round((int) $row['total'] / $maxOrg * 100) ?>"></span></div>
                                    <span class="chart-share-value"><?= $share ?>%</span>
                                </div>
                            </td>
                            <td data-label="Total" class="right"><?= (int) $row['total'] ?></td>
                            <td data-label="Active" class="right"><?= (int) $row['active'] ?></td>
                            <td data-label="Completed" class="right"><?= (int) $row['completed'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Site visits by status</h2><p>Whether scheduled visits are being completed. Each row shows visits and the share of all recorded visits.</p></div></div>
        <?php if ($visits === []): ?>
            <div class="chart-empty">No visits recorded for the year.</div>
        <?php else: ?>
            <div class="chart-list">
                <?php foreach ($visits as $row): ?>
                    <?php $count = (int) $row['total']; $share = $totalVisits > 0 ? (int) round($count / $totalVisits * 100) : 0; ?>
                    <div class="chart-row">
                        <span class="chart-label"><?= e($visitLabels[$row['status']] ?? ucfirst((string) $row['status'])) ?></span>
                        <div class="chart-track <?= $row['status'] === 'completed' ? 'tone-green' : ($row['status'] === 'scheduled' ? '' : 'tone-ink') ?>" aria-hidden="true"><span data-width="<?= (int) round($count / $maxVisit * 100) ?>"></span></div>
                        <span class="chart-value"><strong><?= $count ?></strong><small><?= $share ?>%</small></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-legend">
                <span><i class="legend-swatch" aria-hidden="true"></i> Visits</span>
                <span>Share of <?= $totalVisits ?> recorded visits</span>
            </div>
            <div class="chart-foot"><span><?= (int) $summary['outstandingVisits'] ?> still scheduled</span><span><?= count($visits) ?> status groups</span></div>
        <?php endif; ?>
    </section>
</div>
