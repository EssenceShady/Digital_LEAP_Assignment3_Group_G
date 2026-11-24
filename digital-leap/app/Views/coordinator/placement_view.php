<?php
/** @var array $placement */
/** @var array $timeline */
/** @var array $docs */
/** @var array $entryCounts */
/** @var array $attendance */
/** @var array{lat: float, lng: float}|null $attendancePin */
$allowed = [
    'approved' => ['active', 'terminated'],
    'active' => ['completed', 'terminated'],
    'proposed' => ['terminated'],
];
?>
<div class="page-head">
    <div>
        <h1><?= e($placement['first_name'] . ' ' . $placement['last_name']) ?></h1>
        <p><?= e($placement['reg_no']) ?> · <?= e($placement['org_name']) ?> · <?= status_badge($placement['status']) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn" href="<?= e(url('coordinator/letter', ['id' => (int) $placement['placement_id']])) ?>">Placement letter</a>
        <a class="btn btn-outline" href="<?= e(url('coordinator/grades/view', ['placement_id' => (int) $placement['placement_id']])) ?>">Grade sheet</a>
        <a class="btn btn-quiet" href="<?= e(url('coordinator/placements')) ?>">Back</a>
    </div>
</div>

<?php $tracker = internship_progress((int) $placement['student_id']); $trackerTitle = 'Student progress'; $trackerIntro = 'Milestones recorded for this placement, from approval to published result.'; require APP_PATH . '/Views/shared/tracker.php'; ?>

<div class="grid grid-3">
    <section class="card">
        <div class="card-head"><div><h2>Placement</h2></div></div>
        <div class="stat-list">
            <div class="row"><span>Organisation</span><strong><?= e($placement['org_name']) ?></strong></div>
            <div class="row"><span>District</span><strong><?= e($placement['district']) ?></strong></div>
            <div class="row"><span>Period</span><strong><?= e(fmt_date($placement['start_date'])) ?> – <?= e(fmt_date($placement['end_date'])) ?></strong></div>
            <div class="row"><span>Weeks</span><strong><?= (int) $placement['total_weeks'] ?></strong></div>
            <div class="row"><span>Type</span><strong><?= e(internship_type_label($placement['internship_type'] ?? 'external')) ?></strong></div>
            <div class="row"><span>Academic year</span><strong><?= e($placement['academic_year']) ?></strong></div>
        </div>
    </section>
    <section class="card">
        <div class="card-head"><div><h2>Supervision</h2></div></div>
        <div class="stat-list">
            <div class="row"><span>Mentor</span><strong><?= e($placement['mentor_first'] . ' ' . $placement['mentor_last']) ?></strong></div>
            <div class="row"><span>Host supervisor</span><strong><?= $placement['sup_first'] ? e($placement['sup_first'] . ' ' . $placement['sup_last']) : 'Unassigned' ?></strong></div>
            <div class="row"><span>Approved by</span><strong><?= e((string) $placement['approved_by']) ?></strong></div>
            <div class="row"><span>Approved at</span><strong><?= e(fmt_dt($placement['approved_at'])) ?></strong></div>
        </div>
    </section>
    <section class="card">
        <div class="card-head"><div><h2>Logbook</h2></div></div>
        <div class="stat-list">
            <?php foreach ($entryCounts as $count): ?>
                <div class="row"><span><?= e(ucwords(str_replace('_', ' ', (string) $count['status']))) ?></span><strong><?= (int) $count['total'] ?></strong></div>
            <?php endforeach; ?>
            <?php if ($entryCounts === []): ?><div class="row"><span>No entries</span><strong>0</strong></div><?php endif; ?>
        </div>
    </section>
</div>

<div class="card">
    <div class="card-head">
        <div>
            <h2>Attendance check-ins</h2>
            <p>The student's deliberate site check-ins. Location is captured only when they press a button; supervisors and mentors are not tracked.</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-sm btn-quiet" href="<?= e(url('coordinator/organisations/form', ['id' => (int) $placement['org_id']])) ?>">Site pin</a>
        </div>
    </div>
    <p class="small muted">Site pin: <?= $attendancePin !== null ? e(number_format($attendancePin['lat'], 5) . ', ' . number_format($attendancePin['lng'], 5)) : 'not set — distances cannot be checked until it is recorded.' ?></p>
    <?php if ($attendance === []): ?>
        <div class="empty"><strong>No check-ins yet</strong>The student can check in from My placement.</div>
    <?php else: ?>
        <ul class="timeline">
            <?php foreach ($attendance as $event): ?>
                <li>
                    <div>
                        <strong><?= $event['type'] === 'check_in' ? 'Check in' : 'Check out' ?></strong>
                        <span class="small muted">· <?= e(fmt_dt($event['at'])) ?> by <?= e($event['name'] !== '' ? $event['name'] : 'student') ?></span>
                    </div>
                    <div class="when">
                        <?= $event['distance_m'] !== null ? e(fmt_distance($event['distance_m'])) . ' from pin' : 'No distance' ?>
                        · <?= $event['source'] === 'device' ? 'device location' : 'attested' ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head"><div><h2>Actions</h2><p>Every action is audited with the previous and new state.</p></div></div>
    <div class="form-actions">
        <?php if ($placement['status'] === 'proposed'): ?>
            <form method="post" action="<?= e(url('coordinator/placements/approve')) ?>" data-confirm="Approve this placement? The student, mentor and supervisor are notified.">
                <?= csrf_field() ?>
                <input type="hidden" name="placement_id" value="<?= (int) $placement['placement_id'] ?>">
                <button class="btn" type="submit">Approve placement</button>
            </form>
        <?php endif; ?>
        <?php foreach ($allowed[$placement['status']] ?? [] as $next): ?>
            <form method="post" action="<?= e(url('coordinator/placements/status')) ?>" data-confirm="Mark this placement <?= e($next) ?>? This changes what the student can do.">
                <?= csrf_field() ?>
                <input type="hidden" name="placement_id" value="<?= (int) $placement['placement_id'] ?>">
                <input type="hidden" name="to" value="<?= e($next) ?>">
                <button class="btn <?= $next === 'terminated' ? 'btn-danger' : 'btn-outline' ?>" type="submit">Mark <?= e($next) ?></button>
            </form>
        <?php endforeach; ?>
        <?php if (($allowed[$placement['status']] ?? []) === [] && $placement['status'] !== 'proposed'): ?>
            <span class="small muted">No further status changes are available.</span>
        <?php endif; ?>
        <?php if (!in_array($placement['status'], ['completed', 'terminated'], true)): ?>
            <form method="post" action="<?= e(url('coordinator/placements/type')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="placement_id" value="<?= (int) $placement['placement_id'] ?>">
                <label class="small" for="internship_type">Internship type</label>
                <select id="internship_type" name="internship_type">
                    <option value="external"<?= ($placement['internship_type'] ?? 'external') === 'external' ? ' selected' : '' ?>>External placement</option>
                    <option value="in-house"<?= ($placement['internship_type'] ?? 'external') === 'in-house' ? ' selected' : '' ?>>In-house, LEAP platform</option>
                </select>
                <button class="btn btn-quiet" type="submit">Update type</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Status timeline</h2><p>From the audit log.</p></div></div>
        <?php if ($timeline === []): ?>
            <div class="empty"><strong>No events</strong>Nothing has been recorded against this placement yet.</div>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($timeline as $event): ?>
                    <li>
                        <div><strong><?= e(humanise_action((string) $event['action'])) ?></strong> <span class="small muted">by user <?= e((string) ($event['user_id'] ?? 'system')) ?></span></div>
                        <div class="when"><?= e(fmt_dt($event['created_at'])) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <section class="card">
        <div class="card-head"><div><h2>Documents</h2></div></div>
        <?php if ($docs === []): ?>
            <div class="empty"><strong>No documents</strong>The placement letter can be printed from the button above.</div>
        <?php else: ?>
            <ul class="timeline">
                <?php foreach ($docs as $doc): ?>
                    <li><div><a href="<?= e(url('download', ['id' => (int) $doc['doc_id']])) ?>"><?= e($doc['original_name']) ?></a></div><div class="when"><?= e(fmt_date($doc['uploaded_at'])) ?> · <?= review_label($doc['reviewed_at'] ?? null, $doc['reviewer_first'] ?? null, $doc['reviewer_last'] ?? null) ?></div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
