<?php
/** @var array|null $placement */
/** @var array $docs */
/** @var string $deadline */
/** @var bool $late */
$latest = $docs[0] ?? null;
$submittedLate = $latest !== null && strtotime((string) $latest['uploaded_at']) > strtotime($deadline);
?>
<div class="page-head">
    <div>
        <h1>Career Fair 2026</h1>
        <p>Student participation report. It forms part of your internship assessment portfolio and is reviewed by your academic mentor.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('career-fair/template')) ?>" target="_blank" rel="noopener">Open template</a>
        <a class="btn btn-outline" href="<?= e(url('career-fair/template', ['download' => 1])) ?>">Download template</a>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Submission</h2><p>Complete the two-day report in the official template, then upload the finished file here.</p></div></div>
        <div class="stat-list">
            <div class="row"><span>Deadline</span><strong><?= e(date('l j F Y, g:i a', strtotime($deadline))) ?></strong></div>
            <div class="row"><span>Status</span><strong><?= $latest === null ? 'Not submitted' : status_badge('submitted') ?></strong></div>
            <?php if ($latest !== null): ?>
                <div class="row"><span>Submitted</span><strong><?= e(fmt_dt($latest['uploaded_at'])) ?></strong></div>
                <div class="row"><span>Timing</span><strong><?= $submittedLate ? 'Late submission' : 'On time' ?></strong></div>
            <?php elseif ($late): ?>
                <div class="row"><span>Timing</span><strong>Deadline has passed</strong></div>
            <?php endif; ?>
        </div>
        <p class="small muted">The report is read alongside Form D1, Form D2, the log book and the internship report. The Form E weightings in the guidebook are unchanged; your faculty applies the Career Fair report within the assessment portfolio.</p>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Upload</h2><p>PDF or Word, up to 5 MB. Re-uploading adds a newer file to the list.</p></div></div>
        <?php if ($placement === null): ?>
            <div class="empty"><strong>No placement</strong>A Career Fair report is attached to your placement record, so one must exist first.</div>
        <?php else: ?>
            <form method="post" action="<?= e(url('student/career-fair/upload')) ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="career_fair">Report file</label>
                    <input id="career_fair" name="career_fair" type="file" accept=".pdf,.doc,.docx" required>
                    <span class="hint">Only PDF, DOC and DOCX files are accepted.</span>
                </div>
                <?php if ($late): ?>
                    <p class="small muted">The deadline has passed. Your file is still accepted and is marked as a late submission.</p>
                <?php endif; ?>
                <div class="form-actions"><button class="btn" type="submit">Upload report</button></div>
            </form>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-head"><div><h2>What the report covers</h2><p>The template follows the two-day programme.</p></div></div>
    <div class="grid grid-2">
        <div>
            <p><strong>Introduction.</strong> Career Fair 2026 and its relevance to your career.</p>
            <p><strong>Day One.</strong> CV and cover letter preparation, interview skills, LinkedIn and professional networking.</p>
            <p><strong>Day Two.</strong> The official opening, the panel discussion on the theme and your interactions with employers at their booths.</p>
        </div>
        <div>
            <p><strong>Lessons and action plan.</strong> Your main lessons and the steps you will take, with target dates.</p>
            <p><strong>Challenges and recommendations.</strong> What was difficult and what future fairs should change.</p>
            <p><strong>Conclusion and declaration.</strong> How the fair shaped your career goals, signed and dated.</p>
        </div>
    </div>
</section>

<?php if ($docs !== []): ?>
    <section class="card">
        <div class="card-head"><div><h2>Submitted files</h2></div></div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>File</th><th>Size</th><th>Uploaded</th><th>Timing</th><th>Review</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($docs as $doc): ?>
                    <tr>
                        <td data-label="File"><?= e($doc['original_name']) ?></td>
                        <td data-label="Size"><?= number_format((int) $doc['file_size'] / 1024, 0) ?> KB</td>
                        <td data-label="Uploaded"><?= e(fmt_date($doc['uploaded_at'])) ?></td>
                        <td data-label="Timing"><?= strtotime((string) $doc['uploaded_at']) > strtotime($deadline) ? 'Late' : 'On time' ?></td>
                        <td data-label="Review" class="small"><?= review_label($doc['reviewed_at'] ?? null, $doc['reviewer_first'] ?? null, $doc['reviewer_last'] ?? null) ?></td>
                        <td class="right" data-label="Action"><a class="btn btn-sm btn-quiet" href="<?= e(url('download', ['id' => (int) $doc['doc_id']])) ?>">Download</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
