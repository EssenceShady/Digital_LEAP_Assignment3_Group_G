<?php
/** @var array|null $placement */
/** @var array|null $report */
/** @var array $docs */
?>
<div class="page-head">
    <div>
        <h1>Internship report (Form C)</h1>
        <p>Form C is the student's report cover. Upload the final report once your placement is active; your academic mentor assesses it under Module Two of Form E.</p>
    </div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('student/placement')) ?>">Placement</a></div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Status</h2><p>The mentor scores structure, analysis, evidence and presentation.</p></div></div>
        <?php if ($placement === null): ?>
            <div class="empty"><strong>No placement</strong>A report is linked to an active placement.</div>
        <?php elseif ($report === null): ?>
            <div class="empty"><strong>No report uploaded</strong>Upload your report before the assessment deadline.</div>
        <?php else: ?>
            <div class="stat-list">
                <div class="row"><span>Status</span><strong><?= status_badge($report['status']) ?></strong></div>
                <div class="row"><span>Submitted</span><strong><?= e(fmt_dt($report['submitted_at'])) ?></strong></div>
                <div class="row"><span>Assessed</span><strong><?= e(fmt_dt($report['assessed_at'])) ?></strong></div>
                <?php if ($report['score'] !== null): ?>
                    <div class="row"><span>Score</span><strong><?= e(number_format((float) $report['score'], 1)) ?>%</strong></div>
                <?php endif; ?>
            </div>
            <?php if (!empty($report['comments'])): ?>
                <div class="comment"><div class="who">Mentor feedback</div><p><?= nl2br(e($report['comments'])) ?></p></div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Upload</h2><p>PDF or Word, up to 5 MB. Re-uploading replaces the previous file.</p></div></div>
        <?php if ($placement !== null && $placement['status'] === 'active'): ?>
            <form method="post" action="<?= e(url('student/report/upload')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="report">Report file</label>
                    <input id="report" name="report" type="file" accept=".pdf,.doc,.docx" required>
                    <span class="hint">Only PDF, DOC and DOCX files are accepted.</span>
                </div>
                <div class="form-actions"><button class="btn" type="submit">Upload report</button></div>
            </form>
        <?php else: ?>
            <div class="empty"><strong>Upload disabled</strong>The report can only be uploaded while the placement is active.</div>
        <?php endif; ?>
    </section>
</div>

<?php if ($docs !== []): ?>
    <section class="card">
        <div class="card-head"><div><h2>Submitted files</h2></div></div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>File</th><th>Size</th><th>Uploaded</th><th>Review</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($docs as $doc): ?>
                    <tr>
                        <td data-label="File"><?= e($doc['original_name']) ?></td>
                        <td data-label="Size"><?= number_format((int) $doc['file_size'] / 1024, 0) ?> KB</td>
                        <td data-label="Uploaded"><?= e(fmt_date($doc['uploaded_at'])) ?></td>
                        <td data-label="Review" class="small"><?= review_label($doc['reviewed_at'] ?? null, $doc['reviewer_first'] ?? null, $doc['reviewer_last'] ?? null) ?></td>
                        <td class="right" data-label="Action"><a class="btn btn-sm btn-quiet" href="<?= e(url('download', ['id' => (int) $doc['doc_id']])) ?>">Download</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
