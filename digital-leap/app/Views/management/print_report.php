<?php
/** @var string $year */
/** @var array $summary */
/** @var array $byOrg */
/** @var array $submission */
/** @var array $grades */
/** @var array $visits */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Digital LEAP management report <?= e($year) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="print-body">
<div class="print-sheet">
    <header class="print-head">
        <div>
            <h1>Digital LEAP Internship Management System</h1>
            <p>Management and quality assurance report · <?= e($year) ?></p>
            <p class="small">Limkokwing University, Sierra Leone · Generated <?= e(date('d M Y, H:i')) ?></p>
        </div>
        <div class="print-actions">
            <button class="btn" type="button" data-print>Print or save as PDF</button>
        </div>
    </header>

    <h2>Summary</h2>
    <table class="table">
        <tbody>
            <tr><th>Placements</th><td><?= (int) $summary['placements'] ?></td><th>Active</th><td><?= (int) $summary['active'] ?></td></tr>
            <tr><th>Completed</th><td><?= (int) $summary['completed'] ?></td><th>Completion rate</th><td><?= e(number_format($summary['completion'], 1)) ?>%</td></tr>
            <tr><th>Average published mark</th><td><?= $summary['average'] === null ? '—' : e(number_format($summary['average'], 1)) . '%' ?></td><th>Published marks</th><td><?= (int) $summary['published'] ?></td></tr>
            <tr><th>Overdue logbooks</th><td><?= (int) $summary['overdue'] ?></td><th>Outstanding visits</th><td><?= (int) $summary['outstandingVisits'] ?></td></tr>
            <tr><th>Logbook entries</th><td><?= (int) $summary['entries'] ?></td><th>Report generated</th><td><?= e(date('d M Y')) ?></td></tr>
        </tbody>
    </table>

    <h2>Placements by organisation</h2>
    <table class="table">
        <thead><tr><th>Organisation</th><th>District</th><th class="right">Total</th><th class="right">Active</th><th class="right">Completed</th></tr></thead>
        <tbody>
        <?php foreach ($byOrg as $row): ?>
            <tr><td><?= e($row['name']) ?></td><td><?= e($row['district']) ?></td><td class="right"><?= (int) $row['total'] ?></td><td class="right"><?= (int) $row['active'] ?></td><td class="right"><?= (int) $row['completed'] ?></td></tr>
        <?php endforeach; ?>
        <?php if ($byOrg === []): ?><tr><td colspan="5">No placements for this year.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <h2>Logbook submission by week</h2>
    <table class="table">
        <thead><tr><th>Week</th><th class="right">Students</th><th class="right">Entries</th></tr></thead>
        <tbody>
        <?php foreach ($submission as $row): ?>
            <tr><td>Week <?= (int) $row['week_no'] ?></td><td class="right"><?= (int) $row['students'] ?></td><td class="right"><?= (int) $row['entries'] ?></td></tr>
        <?php endforeach; ?>
        <?php if ($submission === []): ?><tr><td colspan="3">No submissions recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <h2>Grade distribution</h2>
    <table class="table">
        <thead><tr><th>Grade</th><th>Classification</th><th class="right">Students</th></tr></thead>
        <tbody>
        <?php foreach ($grades as $row): ?>
            <tr><td><?= e($row['grade_letter']) ?></td><td><?= e($row['classification']) ?></td><td class="right"><?= (int) $row['total'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p class="print-sign">Prepared by the LEAP/Internship Coordinator. Signed: ______________________________ Date: ______________</p>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
