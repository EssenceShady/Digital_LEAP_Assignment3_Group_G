<?php
/** @var string $year */
/** @var array $rows */
/** @var array $weights */
$published = array_values(array_filter($rows, static fn (array $r): bool => $r['mark_status'] === 'published'));
$average = null;
if ($published !== []) {
    $average = round(array_sum(array_map(static fn (array $r): float => (float) $r['weighted_total'], $published)) / count($published), 2);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Digital LEAP</title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="print-body">
<div class="print-actions no-print">
    <a class="btn btn-quiet" href="<?= e(url('coordinator/grades', ['year' => $year])) ?>">Back</a>
    <button class="btn" type="button" data-print>Print or save as PDF</button>
</div>

<article class="print-sheet print-landscape">
    <header class="print-head">
        <div>
            <p class="print-university">Limkokwing University of Creative Technology</p>
            <p class="print-campus">Sierra Leone Campus · Department of Business and Management</p>
        </div>
        <div class="print-ref">
            <p><strong>Internship grade sheet</strong></p>
            <p>Academic year <?= e($year) ?></p>
        </div>
    </header>

    <h1 class="print-title">Final internship marks, <?= e($year) ?></h1>

    <p class="print-note">
        Practical Internship 100 marks: logbook <?= e(number_format($weights['logbook'], 0)) ?> ·
        Form D (host supervisor) <?= e(number_format($weights['supervisor'], 0)) ?> ·
        self evaluation <?= e(number_format($weights['self'], 0)) ?>.
        Internship report <?= e(number_format($weights['report'], 0)) ?> marks ·
        Career Fair <?= e(number_format($weights['career_fair'], 0)) ?> marks.
        Final mark is out of <?= e(number_format(100 + $weights['report'] + $weights['career_fair'], 0)) ?> and shown as a percentage.
        Only published marks are final.
    </p>

    <table class="print-table print-wide">
        <thead>
            <tr>
                <th scope="col">#</th>
                <th scope="col">Registration no</th>
                <th scope="col">Student</th>
                <th scope="col">Organisation</th>
                <th scope="col">Logbook</th>
                <th scope="col">Form D</th>
                <th scope="col">Self</th>
                <th scope="col">Practical</th>
                <th scope="col">Report</th>
                <th scope="col">Career Fair</th>
                <th scope="col">Final %</th>
                <th scope="col">Grade</th>
                <th scope="col">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $index => $row): ?>
                <tr>
                    <td><?= $index + 1 ?></td>
                    <td><?= e($row['reg_no']) ?></td>
                    <td><?= e($row['last_name'] . ', ' . $row['first_name']) ?></td>
                    <td><?= e($row['org_name']) ?></td>
                    <td><?= $row['logbook_pct'] === null ? '—' : e(number_format((float) $row['logbook_pct'], 2)) ?></td>
                    <td><?= $row['supervisor_pct'] === null ? '—' : e(number_format((float) $row['supervisor_pct'], 2)) ?></td>
                    <td><?= $row['self_pct'] === null ? '—' : e(number_format((float) $row['self_pct'], 2)) ?></td>
                    <td><?= $row['practical_pct'] === null ? '—' : e(number_format((float) $row['practical_pct'], 2)) ?></td>
                    <td><?= $row['report_pct'] === null ? '—' : e(number_format((float) $row['report_pct'], 2)) ?></td>
                    <td><?= $row['career_fair_pct'] === null ? '—' : e(number_format((float) $row['career_fair_pct'], 2)) ?></td>
                    <td><?= $row['weighted_total'] === null ? '—' : e(number_format((float) $row['weighted_total'], 2)) ?></td>
                    <td><?= e($row['grade_letter'] ?? '—') ?></td>
                    <td><?= e(ucfirst((string) ($row['mark_status'] ?? 'not computed'))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="13">No placements were recorded for this academic year.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="print-note">
        Placements: <?= count($rows) ?> · Published marks: <?= count($published) ?> ·
        Mean published total: <?= $average === null ? '—' : e(number_format($average, 2)) ?>%
    </p>

    <div class="print-sign">
        <p class="print-sign-line">&nbsp;</p>
        <p class="print-sign-role">LEAP Internship Coordinator</p>
        <p class="print-sign-role">Date: ____________________</p>
    </div>

    <footer class="print-foot">
        <span>Digital LEAP Internship Management System</span>
        <span>Grade sheet · <?= e($year) ?></span>
    </footer>
</article>

<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
