<?php
/** @var array $students */
/** @var string $year */
?>
<div class="page-head">
    <div>
        <h1>Applications</h1>
        <p><?= e($year) ?> intake. Open a student to read the CV and ranked preferences, then allocate. The office decides the host; the ranking is advice.</p>
    </div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('coordinator/placements')) ?>">Placements</a></div>
</div>

<div class="card">
    <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="filter-bar">
        <input type="hidden" name="r" value="coordinator/applications">
        <div class="field"><label for="q">Student</label><input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Name, registration or email"></div>
        <div class="field"><label for="cv">CV document</label><select id="cv" name="cv">
            <option value="">All students</option>
            <option value="uploaded"<?= selected($cvFilter, 'uploaded') ?>>CV uploaded</option>
            <option value="missing"<?= selected($cvFilter, 'missing') ?>>CV missing</option>
        </select></div>
        <div class="field field-actions"><button class="btn btn-outline" type="submit">Apply filters</button><a class="btn btn-quiet" href="<?= e(url('coordinator/applications')) ?>">Reset</a></div>
    </form>
</div>
<div class="card">
    <div class="card-head"><div><h2>Students</h2><p><?= count($students) ?> matching active students.</p></div></div>
    <?php if ($students === []): ?><div class="empty"><strong>No students match</strong>Reset the filters to see all active students.</div><?php endif; ?>
    <div class="table-wrap">
        <table class="table table--stack">
            <thead><tr><th>Student</th><th>Reg. no.</th><th>Preferences</th><th>Application</th><th>Placement</th><th>CV</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td data-label="Student"><strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong><div class="small muted"><?= e($student['email']) ?></div></td>
                    <td data-label="Reg. no."><?= e($student['reg_no']) ?></td>
                    <td data-label="Preferences"><?= (int) $student['preference_count'] ?></td>
                    <td data-label="Application"><?= $student['preference_count'] > 0 ? status_badge($student['first_status']) : '<span class="muted small">None</span>' ?></td>
                    <td data-label="Placement"><?= $student['placement_status'] ? status_badge($student['placement_status']) : '<span class="muted small">Not allocated</span>' ?></td>
                    <td data-label="CV"><?= $student['cv_doc'] ? '<span class="badge badge-ok">Uploaded</span>' : '<span class="badge badge-warn">Missing</span>' ?></td>
                    <td class="right nowrap">
                        <a class="btn btn-sm btn-quiet" href="<?= e(url('coordinator/applications/view', ['student_id' => (int) $student['student_id']])) ?>">Open</a>
                        <?php if ($student['placement_status'] === null && $student['preference_count'] > 0): ?>
                            <a class="btn btn-sm" href="<?= e(url('coordinator/placements/allocate', ['student_id' => (int) $student['student_id']])) ?>">Allocate</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
