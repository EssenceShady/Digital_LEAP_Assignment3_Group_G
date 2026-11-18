<?php
/** @var array $reports */
/** @var array $careerFairs */
/** @var string $careerFairDeadline */
?>
<div class="page-head">
    <div>
        <h1>Report assessment</h1>
        <p>The internship report contributes 75 of the 200 available marks (37.5%).</p>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="filter-bar">
        <input type="hidden" name="r" value="mentor/reports">
        <div class="field"><label for="q">Document search</label><input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Student, registration or filename"></div>
        <div class="field"><label for="review">Document review</label><select id="review" name="review">
            <option value="">Any review status</option>
            <option value="reviewed"<?= selected($reviewFilter, 'reviewed') ?>>Opened for review</option>
            <option value="unreviewed"<?= selected($reviewFilter, 'unreviewed') ?>>Not yet opened</option>
        </select></div>
        <div class="field field-actions"><button class="btn btn-outline" type="submit">Apply filters</button><a class="btn btn-quiet" href="<?= e(url('mentor/reports')) ?>">Reset</a></div>
    </form>
    <p class="hint">Filters apply to both document lists. Opening a file is not the same as assessing it.</p>
</div>
<div class="card">
    <div class="card-head"><div><h2>Submitted reports</h2><p><?= count($reports) ?> matching report(s) from your students.</p></div></div>
    <?php if ($reports === []): ?>
        <div class="empty"><strong>No internship reports match</strong>Reset the filters to see all available internship reports.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Student</th><th>File</th><th>Submitted</th><th>Review</th><th>Status</th><th>Score</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td data-label="Student"><strong><?= e($report['first_name'] . ' ' . $report['last_name']) ?></strong><div class="small muted"><?= e($report['reg_no']) ?> · <?= e($report['org_name']) ?></div></td>
                        <td data-label="File"><a href="<?= e(url('download', ['id' => (int) $report['document_id']])) ?>"><?= e($report['original_name']) ?></a></td>
                        <td data-label="Submitted"><?= e(fmt_date($report['submitted_at'])) ?></td>
                        <td data-label="Review" class="small"><?= review_label($report['reviewed_at'] ?? null, $report['reviewer_first'] ?? null, $report['reviewer_last'] ?? null) ?></td>
                        <td data-label="Status"><?= status_badge($report['status']) ?></td>
                        <td data-label="Score"><?= $report['score'] !== null ? e(number_format((float) $report['score'], 1)) . '%' : '—' ?></td>
                        <td class="right">
                            <?php if ($report['status'] !== 'assessed'): ?>
                                <details>
                                    <summary class="small"><strong>Assess</strong></summary>
                                    <form method="post" action="<?= e(url('mentor/reports/assess')) ?>" data-confirm="Save this assessment? The student is notified.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="report_id" value="<?= (int) $report['report_id'] ?>">
                                        <div class="field">
                                            <label for="score-<?= (int) $report['report_id'] ?>">Score (0–100)</label>
                                            <input id="score-<?= (int) $report['report_id'] ?>" name="score" type="number" min="0" max="100" step="0.5" required>
                                        </div>
                                        <div class="field">
                                            <label for="comments-<?= (int) $report['report_id'] ?>">Feedback</label>
                                            <textarea id="comments-<?= (int) $report['report_id'] ?>" name="comments" rows="3" required></textarea>
                                        </div>
                                        <div class="form-actions"><button class="btn btn-sm" type="submit">Save assessment</button></div>
                                    </form>
                                </details>
                            <?php else: ?>
                                <span class="small muted">Assessed</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head"><div><h2>Career Fair 2026 submissions</h2><p>Student participation reports from your students. Due <?= e(date('l j F Y, g:i a', strtotime($careerFairDeadline))) ?>.</p></div></div>
    <?php if ($careerFairs === []): ?>
        <div class="empty"><strong>No Career Fair reports match</strong>Reset the filters to see all available Career Fair reports.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Student</th><th>File</th><th>Submitted</th><th>Timing</th><th>Review</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($careerFairs as $entry): ?>
                    <tr>
                        <td data-label="Student"><strong><?= e($entry['first_name'] . ' ' . $entry['last_name']) ?></strong><div class="small muted"><?= e($entry['reg_no']) ?></div></td>
                        <td data-label="File"><?= e($entry['original_name']) ?></td>
                        <td data-label="Submitted"><?= e(fmt_date($entry['uploaded_at'])) ?></td>
                        <td data-label="Timing"><?= strtotime((string) $entry['uploaded_at']) > strtotime($careerFairDeadline) ? 'Late' : 'On time' ?></td>
                        <td data-label="Review" class="small"><?= review_label($entry['reviewed_at'] ?? null, $entry['reviewer_first'] ?? null, $entry['reviewer_last'] ?? null) ?></td>
                        <td class="right" data-label="Action"><a class="btn btn-sm btn-quiet" href="<?= e(url('download', ['id' => (int) $entry['doc_id']])) ?>">Download</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
