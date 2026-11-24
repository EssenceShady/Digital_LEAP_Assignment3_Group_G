<?php
/** @var array $student */
/** @var array|null $placement */
/** @var array $timeline */
/** @var array $docs */
/** @var array|null $report */
/** @var array|null $attendance */
?>
<div class="page-head">
    <div>
        <h1>My placement</h1>
        <p>The placement record, its audit trail and every document issued to you. Form A2 (industrial training agreement), Form A3 (placement agreement) and Form B (industrial training notification) are signed on paper; this page records the placement they establish.</p>
    </div>
    <div class="page-actions">
        <?php if ($placement !== null): ?>
            <a class="btn" href="<?= e(url('student/letter', ['id' => (int) $placement['placement_id']])) ?>">Download placement letter</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= e(url('student/logbook')) ?>">Open logbook</a>
    </div>
</div>

<?php $tracker = internship_progress((int) $student['student_id']); require APP_PATH . '/Views/shared/tracker.php'; ?>

<?php if ($placement === null): ?>
    <div class="card">
        <div class="empty">
            <strong>No placement allocated yet</strong>
            Once your ranked preferences are reviewed, the coordinator allocates an organisation, mentor and supervisor here.
            <div class="form-actions"><a class="btn" href="<?= e(url('student/apply')) ?>">Open my application</a></div>
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-3">
        <section class="card">
            <div class="card-head"><div><h2>Host organisation</h2><p><?= status_badge($placement['status']) ?></p></div></div>
            <div class="stat-list">
                <div class="row"><span>Name</span><strong><?= e($placement['org_name']) ?></strong></div>
                <div class="row"><span>District</span><strong><?= e($placement['district']) ?></strong></div>
                <div class="row"><span>Address</span><strong><?= e($placement['address']) ?></strong></div>
                <div class="row"><span>Contact</span><strong><?= e($placement['contact_person']) ?></strong></div>
            </div>
        </section>
        <section class="card">
            <div class="card-head"><div><h2>Period</h2><p><?= e($placement['academic_year']) ?></p></div></div>
            <div class="stat-list">
                <div class="row"><span>Start</span><strong><?= e(fmt_date($placement['start_date'])) ?></strong></div>
                <div class="row"><span>End</span><strong><?= e(fmt_date($placement['end_date'])) ?></strong></div>
                <div class="row"><span>Duration</span><strong><?= (int) $placement['total_weeks'] ?> weeks</strong></div>
                <div class="row"><span>Type</span><strong><?= e(internship_type_label($placement['internship_type'] ?? 'external')) ?></strong></div>
                <div class="row"><span>Approved</span><strong><?= e(fmt_dt($placement['approved_at'])) ?></strong></div>
            </div>
        </section>
        <section class="card">
            <div class="card-head"><div><h2>Supervision</h2><p>Who to contact.</p></div></div>
            <div class="stat-list">
                <div class="row"><span>Academic mentor</span><strong><?= e(trim($placement['mentor_first'] . ' ' . $placement['mentor_last'])) ?></strong></div>
                <div class="row"><span>Mentor email</span><strong><?= e($placement['mentor_email']) ?></strong></div>
                <div class="row"><span>Host supervisor</span><strong><?= e(trim(($placement['sup_first'] ?? '') . ' ' . ($placement['sup_last'] ?? ''))) ?: 'Not assigned' ?></strong></div>
                <div class="row"><span>Supervisor email</span><strong><?= e($placement['sup_email'] ?? '—') ?></strong></div>
            </div>
        </section>
    </div>

    <section class="card" id="attendance">
        <div class="card-head">
            <div>
                <h2>Attendance</h2>
                <p>Check in and out at the site. Location is read only when you press a button, and only students are tracked at their own placement.</p>
            </div>
        </div>
        <?php
        $today = $attendance['today'] ?? null;
        $canCheck = (bool) ($attendance['active'] ?? false);
        $openIn = $today !== null && $today['type'] === 'check_in';
        ?>
        <div class="stat-list">
            <div class="row">
                <span>Today</span>
                <strong>
                    <?php if (!$canCheck): ?>
                        Attendance opens when the placement is active.
                    <?php elseif ($today === null): ?>
                        No check-in recorded yet.
                    <?php else: ?>
                        <?= $openIn ? 'Checked in' : 'Checked out' ?> at <?= e($today['time']) ?>
                        <?php if ($today['distance_m'] !== null): ?>· <?= e(fmt_distance($today['distance_m'])) ?> from site pin<?php endif; ?>
                        <?php if ($today['source'] === 'attested'): ?>· location not shared<?php endif; ?>
                    <?php endif; ?>
                </strong>
            </div>
            <div class="row">
                <span>Site pin</span>
                <strong><?= ($attendance['org'] ?? null) !== null ? 'Set' : 'Not set by the coordinator' ?></strong>
            </div>
        </div>
        <?php if ($canCheck): ?>
            <p class="small muted" data-attendance-status role="status" aria-live="polite">Your browser will ask for location permission the first time.</p>
            <div class="form-actions">
                <form method="post" action="<?= e(url('student/attendance/check')) ?>" data-attendance-form data-type="check_in">
                    <?= csrf_field() ?>
                    <input type="hidden" name="type" value="check_in">
                    <input type="hidden" name="lat" value="">
                    <input type="hidden" name="lng" value="">
                    <input type="hidden" name="accuracy" value="">
                    <button class="btn" type="submit"<?= $openIn ? ' disabled' : '' ?>>Check in</button>
                </form>
                <form method="post" action="<?= e(url('student/attendance/check')) ?>" data-attendance-form data-type="check_out">
                    <?= csrf_field() ?>
                    <input type="hidden" name="type" value="check_out">
                    <input type="hidden" name="lat" value="">
                    <input type="hidden" name="lng" value="">
                    <input type="hidden" name="accuracy" value="">
                    <button class="btn btn-outline" type="submit"<?= $openIn ? '' : ' disabled' ?>>Check out</button>
                </form>
            </div>
        <?php endif; ?>
        <?php if (($attendance['recent'] ?? []) !== []): ?>
            <ul class="timeline">
                <?php foreach ($attendance['recent'] as $event): ?>
                    <li>
                        <div>
                            <strong><?= $event['type'] === 'check_in' ? 'Check in' : 'Check out' ?></strong>
                            <span class="small muted"><?= e(fmt_dt($event['at'])) ?></span>
                        </div>
                        <div class="when">
                            <?php if ($event['distance_m'] !== null): ?><?= e(fmt_distance($event['distance_m'])) ?> from pin<?php else: ?>No distance<?php endif; ?>
                            · <?= $event['source'] === 'device' ? 'device location' : 'attested' ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <div class="grid grid-2">
        <section class="card">
            <div class="card-head"><div><h2>Status timeline</h2><p>Rebuilt from the append-only audit log.</p></div></div>
            <?php if ($timeline === []): ?>
                <div class="empty"><strong>No events recorded</strong>Placement events appear here as the coordinator acts.</div>
            <?php else: ?>
                <ul class="timeline">
                    <?php foreach ($timeline as $event): ?>
                        <li>
                            <div><strong><?= e(humanise_action((string) $event['action'])) ?></strong></div>
                            <div class="when"><?= e(fmt_dt($event['created_at'])) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-head"><div><h2>Documents</h2><p>Placement letter and uploaded files.</p></div></div>
            <?php if ($docs === []): ?>
                <div class="empty"><strong>No documents yet</strong>The placement letter is generated from this record and is always available above.</div>
            <?php else: ?>
                <ul class="doc-list">
                    <?php foreach ($docs as $doc): ?>
                        <li class="doc-row">
                            <div class="doc-main">
                                <a class="doc-name" href="<?= e(url('download', ['id' => (int) $doc['doc_id']])) ?>"><?= e($doc['original_name']) ?></a>
                                <p class="doc-meta">
                                    <span><?= e(ucwords(str_replace('_', ' ', (string) $doc['doc_type']))) ?></span>
                                    <span>Uploaded <?= e(fmt_date($doc['uploaded_at'])) ?></span>
                                    <span><?= review_label($doc['reviewed_at'] ?? null, $doc['reviewer_first'] ?? null, $doc['reviewer_last'] ?? null) ?></span>
                                </p>
                            </div>
                            <a class="btn btn-sm btn-quiet doc-action" href="<?= e(url('download', ['id' => (int) $doc['doc_id']])) ?>">Download</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
<?php endif; ?>
