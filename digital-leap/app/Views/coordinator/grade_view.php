<?php
/** @var array $placement */
/** @var array $breakdown */
/** @var array|null $mark */
/** @var array|null $suggested */
/** @var array $weights */
$reportMarks = round($breakdown['report'] * $weights['report'] / 100, 2);
$careerMarks = $breakdown['career_fair'] === null ? null : round($breakdown['career_fair'] * $weights['career_fair'] / 100, 2);
$marksEarned = round($breakdown['practical'] + $reportMarks + ($careerMarks ?? 0), 2);
$marksPossible = 100 + $weights['report'] + $weights['career_fair'];
$published = $mark !== null && $mark['status'] === 'published';
?>
<div class="page-head">
    <div>
        <h1>Grade sheet</h1>
        <p><?= e($placement['first_name'] . ' ' . $placement['last_name']) ?> · <?= e($placement['reg_no']) ?> · <?= e($placement['org_name']) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('coordinator/grades')) ?>">All grades</a>
        <a class="btn btn-outline" href="<?= e(url('coordinator/grades/print', ['year' => $placement['academic_year']])) ?>">Printable sheet</a>
        <a class="btn btn-quiet" href="<?= e(url('coordinator/placements/view', ['id' => (int) $placement['placement_id']])) ?>">Placement</a>
    </div>
</div>

<?php if (!$breakdown['complete']): ?>
    <div class="alert alert-warning" role="alert">Missing components: <?= e(implode(', ', $breakdown['missing'])) ?>. The mark cannot be computed yet.</div>
<?php endif; ?>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Computed breakdown</h2><p>Practical Internship + Internship Report + Career Fair, out of 200.</p></div></div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Module</th><th class="right">Score</th><th class="right">Weight</th><th class="right">Marks</th><th>Source</th></tr></thead>
                <tbody>
                    <tr>
                        <td data-label="Module">Practical · Logbook</td>
                        <td data-label="Score" class="right"><?= e(number_format($breakdown['logbook'], 2)) ?>%</td>
                        <td data-label="Weight" class="right"><?= e(number_format($weights['logbook'], 0)) ?>%</td>
                        <td data-label="Marks" class="right"><?= e(number_format($breakdown['logbook'] * $weights['logbook'] / 100, 2)) ?></td>
                        <td data-label="Source"><span class="small"><?= (int) $breakdown['approved_weeks'] ?>/<?= (int) $breakdown['total_weeks'] ?> weeks approved</span></td>
                    </tr>
                    <tr>
                        <td data-label="Module">Practical · Form D</td>
                        <td data-label="Score" class="right"><?= e(number_format($breakdown['supervisor'], 2)) ?>%</td>
                        <td data-label="Weight" class="right"><?= e(number_format($weights['supervisor'], 0)) ?>%</td>
                        <td data-label="Marks" class="right"><?= e(number_format($breakdown['supervisor'] * $weights['supervisor'] / 100, 2)) ?></td>
                        <td data-label="Source"><?= status_badge($breakdown['supervisor_status']) ?></td>
                    </tr>
                    <tr>
                        <td data-label="Module">Practical · Self evaluation</td>
                        <td data-label="Score" class="right"><?= e(number_format($breakdown['self'], 2)) ?>%</td>
                        <td data-label="Weight" class="right"><?= e(number_format($weights['self'], 0)) ?>%</td>
                        <td data-label="Marks" class="right"><?= e(number_format($breakdown['self'] * $weights['self'] / 100, 2)) ?></td>
                        <td data-label="Source"><?= status_badge($breakdown['self_status']) ?></td>
                    </tr>
                    <tr>
                        <td data-label="Module"><strong>Practical Internship subtotal</strong></td>
                        <td data-label="Score" class="right"><strong><?= e(number_format($breakdown['practical'], 2)) ?>%</strong></td>
                        <td data-label="Weight" class="right">100 marks</td>
                        <td data-label="Marks" class="right"><strong><?= e(number_format($breakdown['practical'], 2)) ?></strong></td>
                        <td data-label="Source"><span class="small muted">Logbook, Form D and self evaluation</span></td>
                    </tr>
                    <tr>
                        <td data-label="Module">Internship Report</td>
                        <td data-label="Score" class="right"><?= e(number_format($breakdown['report'], 2)) ?>%</td>
                        <td data-label="Weight" class="right"><?= e(number_format($weights['report'], 0)) ?> marks</td>
                        <td data-label="Marks" class="right"><?= e(number_format($reportMarks, 2)) ?></td>
                        <td data-label="Source"><?= status_badge($breakdown['report_status']) ?></td>
                    </tr>
                    <tr>
                        <td data-label="Module">Career Fair</td>
                        <td data-label="Score" class="right"><?= $breakdown['career_fair'] === null ? '—' : e(number_format($breakdown['career_fair'], 2)) . '%' ?></td>
                        <td data-label="Weight" class="right"><?= e(number_format($weights['career_fair'], 0)) ?> marks</td>
                        <td data-label="Marks" class="right"><?= $careerMarks === null ? '—' : e(number_format($careerMarks, 2)) ?></td>
                        <td data-label="Source"><?= $breakdown['career_fair'] === null ? '<span class="badge badge-warn">Score pending</span>' : '<span class="small muted">Recorded by the coordinator</span>' ?></td>
                    </tr>
                </tbody>
            </table>
            <div class="table-total"><span>Final mark <?= e(number_format($marksEarned, 2)) ?> / <?= e(number_format($marksPossible, 0)) ?></span><strong><?= e(number_format($breakdown['weighted'], 2)) ?>%</strong></div>
        </div>
        <?php if ($suggested !== null): ?>
            <p class="small muted">Suggested grade from the scale: <strong><?= e($suggested['grade_letter']) ?></strong> — <?= e($suggested['classification']) ?> (<?= e(number_format((float) $suggested['points'], 2)) ?> points).</p>
        <?php endif; ?>
        <p class="small muted">The academic mentor's evaluation<?= $breakdown['mentor'] === null ? '' : ' (' . e(number_format($breakdown['mentor'], 2)) . '%)' ?> is advisory only and does not carry marks.</p>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Mark record</h2><p><?= $mark ? status_badge($mark['status']) : 'Not computed' ?></p></div></div>
        <?php if ($mark === null): ?>
            <div class="empty"><strong>No mark yet</strong>Compute the mark once every component is complete.</div>
        <?php else: ?>
            <div class="stat-list">
                <div class="row"><span>Practical</span><strong><?= $mark['practical_pct'] === null ? '—' : e(number_format((float) $mark['practical_pct'], 2)) . '%' ?></strong></div>
                <div class="row"><span>Report</span><strong><?= $mark['report_pct'] === null ? '—' : e(number_format((float) $mark['report_pct'], 2)) . '%' ?></strong></div>
                <div class="row"><span>Career Fair</span><strong><?= $mark['career_fair_pct'] === null ? '—' : e(number_format((float) $mark['career_fair_pct'], 2)) . '%' ?></strong></div>
                <div class="row"><span>Total</span><strong><?= $mark['weighted_total'] === null ? '—' : e(number_format((float) $mark['weighted_total'], 2)) . '%' ?></strong></div>
                <div class="row"><span>Grade</span><strong><?= e($mark['grade_letter'] ?? '—') ?> <?= e($mark['classification'] ?? '') ?></strong></div>
                <div class="row"><span>Computed</span><strong><?= e(fmt_dt($mark['computed_at'])) ?></strong></div>
                <div class="row"><span>Moderated</span><strong><?= e(fmt_dt($mark['moderated_at'])) ?></strong></div>
                <div class="row"><span>Published</span><strong><?= e(fmt_dt($mark['published_at'])) ?></strong></div>
            </div>
            <?php if (!empty($mark['moderation_reason'])): ?>
                <div class="comment"><div class="who">Moderation reason</div><p><?= e($mark['moderation_reason']) ?></p></div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>1. Compute</h2><p>Recompute from the logbook, evaluations, report and career fair score.</p></div></div>
        <form method="post" action="<?= e(url('coordinator/grades/compute')) ?>" data-confirm="Recompute this mark from the source records? Any unpublished computed values are replaced.">
            <?= csrf_field() ?>
            <input type="hidden" name="placement_id" value="<?= (int) $placement['placement_id'] ?>">
            <div class="form-actions">
                <button class="btn" type="submit"<?= $breakdown['complete'] && !$published ? '' : ' disabled' ?>>Compute mark</button>
            </div>
            <?php if ($published): ?><p class="small muted">Published marks are locked.</p><?php endif; ?>
        </form>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>2. Moderate</h2><p>A reason is mandatory and permanently recorded.</p></div></div>
        <?php if ($mark === null): ?>
            <div class="empty"><strong>Compute first</strong>A draft mark is required before moderation.</div>
        <?php elseif ($mark['status'] !== 'draft'): ?>
            <div class="empty"><strong>Moderation complete</strong>This mark is <?= e($mark['status']) ?>.</div>
        <?php else: ?>
            <form method="post" action="<?= e(url('coordinator/grades/moderate')) ?>" data-confirm="Moderate this mark with the reason you have written?">
                <?= csrf_field() ?>
                <input type="hidden" name="placement_id" value="<?= (int) $placement['placement_id'] ?>">
                <div class="field">
                    <label for="moderation_reason">Moderation reason</label>
                    <textarea id="moderation_reason" name="moderation_reason" rows="3" maxlength="255" required></textarea>
                    <span class="hint">At least ten characters. Visible to management and in the audit log.</span>
                </div>
                <div class="form-actions"><button class="btn btn-outline" type="submit">Record moderation</button></div>
            </form>
        <?php endif; ?>
    </section>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Career Fair score</h2><p>25 of the 200 final marks. Recorded from the Career Fair 2026 assessment.</p></div></div>
        <?php if ($published): ?>
            <div class="empty"><strong>Mark published</strong>The career fair score is frozen with the published mark.</div>
        <?php else: ?>
            <form method="post" action="<?= e(url('coordinator/grades/career-fair')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="placement_id" value="<?= (int) $placement['placement_id'] ?>">
                <div class="field">
                    <label for="career_fair_score">Career fair score (0–100)</label>
                    <input id="career_fair_score" name="career_fair_score" type="number" min="0" max="100" step="0.5" value="<?= $breakdown['career_fair'] === null ? '' : e(rtrim(rtrim(number_format($breakdown['career_fair'], 2), '0'), '.')) ?>" required>
                    <span class="hint">Changing the score after computing means recomputing the mark.</span>
                </div>
                <div class="form-actions"><button class="btn btn-outline" type="submit">Save score</button></div>
            </form>
        <?php endif; ?>
    </section>

    <?php if ($mark !== null && $mark['status'] === 'moderated'): ?>
        <section class="card">
            <div class="card-head"><div><h2>3. Publish</h2><p>The student sees the breakdown and grade, but never the moderation notes.</p></div></div>
            <form method="post" action="<?= e(url('coordinator/grades/publish')) ?>" data-confirm="Publish this mark to the student? This cannot be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="placement_id" value="<?= (int) $placement['placement_id'] ?>">
                <div class="form-actions"><button class="btn" type="submit">Publish mark</button></div>
            </form>
        </section>
    <?php endif; ?>
</div>
