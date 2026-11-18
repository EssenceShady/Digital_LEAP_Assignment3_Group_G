<?php
/** @var array $student */
/** @var array|null $placement */
/** @var array $timeline */
/** @var array $docs */
/** @var array|null $report */
?>
<div class="page-head">
    <div>
        <h1>My placement</h1>
        <p>The placement record, its audit trail and every document issued to you.</p>
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

    <div class="grid grid-2">
        <section class="card">
            <div class="card-head"><div><h2>Status timeline</h2><p>Rebuilt from the append-only audit log.</p></div></div>
            <?php if ($timeline === []): ?>
                <div class="empty"><strong>No events recorded</strong>Placement events appear here as the coordinator acts.</div>
            <?php else: ?>
                <ul class="timeline">
                    <?php foreach ($timeline as $event): ?>
                        <li>
                            <div><strong><?= e(str_replace(['placement.', '_'], ['', ' '], (string) $event['action'])) ?></strong></div>
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
                <div class="table-wrap">
                    <table class="table table--stack">
                        <thead><tr><th>File</th><th>Type</th><th>Uploaded</th><th>Review</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($docs as $doc): ?>
                            <tr>
                                <td data-label="File"><?= e($doc['original_name']) ?></td>
                                <td data-label="Type"><?= e(ucwords(str_replace('_', ' ', (string) $doc['doc_type']))) ?></td>
                                <td data-label="Uploaded"><?= e(fmt_date($doc['uploaded_at'])) ?></td>
                                <td data-label="Review" class="small"><?= review_label($doc['reviewed_at'] ?? null, $doc['reviewer_first'] ?? null, $doc['reviewer_last'] ?? null) ?></td>
                                <td class="right"><a class="btn btn-sm btn-quiet" href="<?= e(url('download', ['id' => (int) $doc['doc_id']])) ?>">Download</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
<?php endif; ?>
