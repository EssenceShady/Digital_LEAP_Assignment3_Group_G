<?php
/** @var array $placement */
/** @var string $reference */
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
    <a class="btn btn-quiet" href="<?= e($back) ?>">Back</a>
    <button class="btn" type="button" data-print>Print or save as PDF</button>
</div>

<article class="print-sheet print-letter">
    <header class="print-head">
        <div>
            <p class="print-university">Limkokwing University of Creative Technology</p>
            <p class="print-campus">Sierra Leone Campus · Department of Business and Management</p>
        </div>
        <div class="print-ref">
            <p><strong>Ref:</strong> <?= e($reference) ?></p>
            <p><strong>Date:</strong> <?= e(fmt_date(date('Y-m-d'), 'd F Y')) ?></p>
        </div>
    </header>

    <p class="letter-to">
        <?= e($placement['first_name'] . ' ' . $placement['last_name']) ?><br>
        <?= e($placement['reg_no']) ?> · <?= e($placement['programme']) ?><br>
        <?= e(year_label($placement['level'])) ?>, <?= e($placement['semester']) ?><br>
        <?= e($placement['email']) ?>
    </p>

    <h1 class="letter-title">Confirmation of internship placement</h1>

    <p>Dear <?= e($placement['first_name']) ?>,</p>

    <p>
        This letter confirms that you have been placed with
        <strong><?= e($placement['org_name']) ?></strong>,
        <?= e($placement['org_address']) ?>, <?= e($placement['org_district']) ?>,
        for the internship component of <?= e($placement['programme']) ?>
        in the <?= e($placement['academic_year']) ?> academic year.
    </p>

    <table class="print-table letter-details">
        <tbody>
            <tr><th scope="row">Placement period</th><td><?= e(fmt_date((string) $placement['start_date'], 'd F Y')) ?> to <?= e(fmt_date((string) $placement['end_date'], 'd F Y')) ?> (<?= (int) $placement['total_weeks'] ?> weeks)</td></tr>
            <tr><th scope="row">Internship type</th><td><?= e(internship_type_label($placement['internship_type'] ?? 'external')) ?></td></tr>
            <tr><th scope="row">Host organisation</th><td><?= e($placement['org_name']) ?></td></tr>
            <tr><th scope="row">Host contact</th><td><?= e($placement['contact_person']) ?> · <?= e($placement['contact_email']) ?></td></tr>
            <tr><th scope="row">Host supervisor</th><td><?= $placement['sup_first'] !== null ? e($placement['sup_first'] . ' ' . $placement['sup_last']) : 'To be confirmed by the organisation' ?></td></tr>
            <tr><th scope="row">Academic mentor</th><td><?= e($placement['mentor_first'] . ' ' . $placement['mentor_last']) ?></td></tr>
            <tr><th scope="row">Placement status</th><td><?= e(ucfirst((string) $placement['status'])) ?></td></tr>
        </tbody>
    </table>

    <p>
        During the placement you are required to keep a weekly logbook in the Digital LEAP system,
        have each entry reviewed by your host supervisor, participate in the scheduled site visits
        with your academic mentor, submit the student self evaluation, take part in the Career Fair
        and submit the internship report before the closing date.
        The final mark is out of <?= e(number_format(100 + (float) \Leap\Core\Settings::get('weight_report', 75) + (float) \Leap\Core\Settings::get('weight_career_fair', 25), 0)) ?>:
        Practical Internship 100 marks (logbook <?= e(number_format((float) \Leap\Core\Settings::get('weight_logbook', 50), 0)) ?>,
        Form D host supervisor assessment <?= e(number_format((float) \Leap\Core\Settings::get('weight_supervisor', 40), 0)) ?>,
        student self evaluation <?= e(number_format((float) \Leap\Core\Settings::get('weight_self', 10), 0)) ?>),
        internship report <?= e(number_format((float) \Leap\Core\Settings::get('weight_report', 75), 0)) ?> marks and
        Career Fair <?= e(number_format((float) \Leap\Core\Settings::get('weight_career_fair', 25), 0)) ?> marks.
        The academic mentor's evaluation is advisory and carries no marks.
    </p>

    <p>
        Please present this letter to the organisation on your first day. If any detail above is incorrect,
        contact the internship coordinator before the placement begins.
    </p>

    <p>Yours sincerely,</p>

    <div class="print-sign">
        <p class="print-sign-line"><?= e($placement['coord_first'] . ' ' . $placement['coord_last']) ?></p>
        <p class="print-sign-role">LEAP Internship Coordinator</p>
        <p class="print-sign-role">Limkokwing University, Sierra Leone</p>
    </div>

    <footer class="print-foot">
        <span>Digital LEAP Internship Management System</span>
        <span>Ref <?= e($reference) ?></span>
    </footer>
</article>

<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
