<?php
/** @var array $placement */
/** @var array $criteria */
/** @var array|null $evaluation */
/** @var array $scores */
/** @var float $weightTotal */
$locked = $evaluation !== null && $evaluation['status'] === 'submitted';
?>
<div class="page-head">
    <div>
        <h1>Student self evaluation (Form E &mdash; 10%)</h1>
        <p>Reflect on your own internship against the department criteria. Form E weights this evaluation at 10% of Practical Internship.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('student/placement')) ?>">My placement</a>
    </div>
</div>

<div class="alert alert-info" role="note">
    <strong><?= e($placement['org_name']) ?></strong> · <?= e(internship_type_label($placement['internship_type'] ?? 'external')) ?> · <?= e(fmt_date($placement['start_date'])) ?> to <?= e(fmt_date($placement['end_date'])) ?>.
    Be honest and specific: the coordinator reads this alongside Form D and your mentor's assessment, but it never replaces them.
</div>

<?php if (abs($weightTotal - 100) > 0.01): ?>
    <div class="alert alert-error" role="alert">Criterion weights currently total <?= e(number_format($weightTotal, 1)) ?>%. The form cannot be submitted until they total 100%.</div>
<?php endif; ?>

<?php if ($locked): ?>
    <section class="card">
        <div class="card-head"><div><h2>Submitted self evaluation</h2><p>Submitted <?= e(fmt_dt($evaluation['submitted_at'])) ?> · total <?= e(number_format((float) $evaluation['total_score'], 2)) ?>%</p></div></div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Criterion</th><th class="right">Max</th><th class="right">Weight</th><th class="right">Your score</th><th>Your evidence</th></tr></thead>
                <tbody>
                <?php foreach ($criteria as $criterion): $row = $scores[(int) $criterion['criterion_id']] ?? null; ?>
                    <tr>
                        <td data-label="Criterion"><?= e($criterion['name']) ?></td>
                        <td data-label="Max" class="right"><?= (int) $criterion['max_score'] ?></td>
                        <td data-label="Weight" class="right"><?= (int) $criterion['weight'] ?>%</td>
                        <td data-label="Score" class="right"><?= $row ? e(number_format((float) $row['score'], 1)) : '—' ?></td>
                        <td data-label="Evidence"><?= e($row['comment'] ?? '') ?: '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($evaluation['comments'])): ?>
            <div class="comment"><div class="who">Overall reflection</div><p><?= nl2br(e($evaluation['comments'])) ?></p></div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <form method="post" action="<?= e(url('student/self-evaluation/save')) ?>" id="evaluation-form">
        <?= csrf_field() ?>
        <section class="card">
            <div class="card-head"><div><h2>Your assessment</h2><p>Score each criterion out of its maximum. The total updates as you type.</p></div><div class="kpi-value" id="eval-total">0.00%</div></div>
            <div class="table-wrap">
                <table class="table table--stack">
                    <thead><tr><th>Criterion</th><th class="right">Max</th><th class="right">Weight</th><th>Your score</th><th>Evidence</th></tr></thead>
                    <tbody>
                    <?php foreach ($criteria as $criterion): $row = $scores[(int) $criterion['criterion_id']] ?? null; ?>
                        <tr>
                            <td data-label="Criterion"><strong><?= e($criterion['name']) ?></strong></td>
                            <td data-label="Max" class="right"><?= (int) $criterion['max_score'] ?></td>
                            <td data-label="Weight" class="right"><?= (int) $criterion['weight'] ?>%</td>
                            <td data-label="Score">
                                <label class="sr-only" for="score-<?= (int) $criterion['criterion_id'] ?>">Score for <?= e($criterion['name']) ?></label>
                                <input id="score-<?= (int) $criterion['criterion_id'] ?>" name="scores[<?= (int) $criterion['criterion_id'] ?>]" type="number" min="0" max="<?= (int) $criterion['max_score'] ?>" step="0.5" value="<?= $row ? e(rtrim(rtrim(number_format((float) $row['score'], 1), '0'), '.')) : '' ?>" data-score data-weight="<?= (int) $criterion['weight'] ?>" class="input-score">
                            </td>
                            <td data-label="Evidence">
                                <label class="sr-only" for="comment-<?= (int) $criterion['criterion_id'] ?>">Evidence for <?= e($criterion['name']) ?></label>
                                <input id="comment-<?= (int) $criterion['criterion_id'] ?>" name="comments[<?= (int) $criterion['criterion_id'] ?>]" type="text" maxlength="500" value="<?= $row ? e($row['comment'] ?? '') : '' ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="field">
                <label for="comments_general">Overall reflection</label>
                <textarea id="comments_general" name="comments_general" rows="4" maxlength="2000"><?= e($evaluation['comments'] ?? '') ?></textarea>
                <span class="hint">What did you learn, what was difficult, and what would you do differently? The coordinator and management can read this.</span>
            </div>
            <div class="form-actions">
                <button class="btn btn-outline" type="submit" name="save" value="1">Save draft</button>
                <button class="btn" type="submit" name="submit" value="1" id="eval-submit" data-confirm="Submit your self evaluation? It is sent to the coordinator and cannot be edited afterwards.">Submit self evaluation</button>
            </div>
        </section>
    </form>
<?php endif; ?>
