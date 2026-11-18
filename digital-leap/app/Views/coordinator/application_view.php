<?php
/** @var array $student */
/** @var array $prefs */
/** @var array|null $cv */
/** @var array|null $placement */
/** @var string $year */
?>
<div class="page-head">
    <div>
        <h1><?= e($student['first_name'] . ' ' . $student['last_name']) ?></h1>
        <p><?= e($student['reg_no']) ?> · <?= e($student['programme']) ?> · <?= e(year_label($student['level'])) ?>, <?= e($student['semester']) ?></p>
    </div>
    <div class="page-actions">
        <?php if ($placement === null): ?>
            <a class="btn" href="<?= e(url('coordinator/placements/allocate', ['student_id' => (int) $student['student_id']])) ?>">Allocate placement</a>
        <?php else: ?>
            <a class="btn" href="<?= e(url('coordinator/placements/view', ['id' => (int) $placement['placement_id']])) ?>">View placement</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= e(url('coordinator/applications')) ?>">Back to queue</a>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Student record</h2></div></div>
        <div class="stat-list">
            <div class="row"><span>Email</span><strong><?= e($student['email']) ?></strong></div>
            <div class="row"><span>Phone</span><strong><?= e($student['phone'] ?? '—') ?></strong></div>
            <div class="row"><span>Academic year</span><strong><?= e($student['academic_year']) ?></strong></div>
            <div class="row"><span>CGPA</span><strong><?= e(number_format((float) $student['cgpa'], 2)) ?></strong></div>
            <div class="row"><span>Status</span><strong><?= status_badge($student['status']) ?></strong></div>
        </div>
    </section>
    <section class="card">
        <div class="card-head"><div><h2>CV</h2><p>The document the student uploaded.</p></div></div>
        <?php if ($cv === null): ?>
            <div class="empty"><strong>No CV uploaded</strong>Ask the student to upload a CV from their profile page before allocation.</div>
        <?php else: ?>
            <div class="stat-list">
                <div class="row"><span>File</span><strong><?= e($cv['original_name']) ?></strong></div>
                <div class="row"><span>Size</span><strong><?= number_format((int) $cv['file_size'] / 1024, 0) ?> KB</strong></div>
                <div class="row"><span>Uploaded</span><strong><?= e(fmt_date($cv['uploaded_at'])) ?></strong></div>
                <div class="row"><span>Review</span><strong class="small"><?= review_label($cv['reviewed_at'] ?? null, $cv['reviewer_first'] ?? null, $cv['reviewer_last'] ?? null) ?></strong></div>
            </div>
            <div class="form-actions"><a class="btn" href="<?= e(url('download', ['id' => (int) $cv['doc_id']])) ?>">Download CV</a></div>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-head"><div><h2>Ranked preferences for <?= e($year) ?></h2><p><?= count($prefs) ?> submitted. The office makes the final allocation; the ranking is advice.</p></div></div>
    <?php if ($prefs === []): ?>
        <div class="empty"><strong>No preferences</strong>The student has not submitted a ranked list for this year.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Rank</th><th>Organisation</th><th>District</th><th>MoU</th><th>Capacity</th><th>Status</th><th>Motivation</th></tr></thead>
                <tbody>
                <?php foreach ($prefs as $pref): ?>
                    <tr>
                        <td data-label="Rank"><strong><?= (int) $pref['preference_rank'] ?></strong></td>
                        <td data-label="Organisation"><?= e($pref['org_name']) ?></td>
                        <td data-label="District"><?= e($pref['district']) ?></td>
                        <td data-label="MoU"><?= status_badge($pref['mou_status']) ?></td>
                        <td data-label="Capacity"><?= (int) $pref['capacity'] ?></td>
                        <td data-label="Status"><?= status_badge($pref['status']) ?></td>
                        <td data-label="Motivation"><span class="small"><?= e($pref['motivation']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($placement !== null): ?>
    <section class="card">
        <div class="card-head"><div><h2>Current placement</h2><p><?= status_badge($placement['status']) ?></p></div></div>
        <div class="form-actions"><a class="btn btn-outline" href="<?= e(url('coordinator/placements/view', ['id' => (int) $placement['placement_id']])) ?>">Open placement record</a></div>
    </section>
<?php endif; ?>
