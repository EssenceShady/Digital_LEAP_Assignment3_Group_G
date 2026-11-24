<?php
/** @var array $placements */
/** @var array $placement */
/** @var array $criteria */
/** @var array|null $evaluation */
/** @var array $scores */
/** @var float $weightTotal */
$locked = $evaluation !== null && $evaluation['status'] === 'submitted';
?>
<div class="page-head">
    <div>
        <h1>Form D &mdash; host supervisor evaluation</h1>
        <p>Independently assess <?= e($placement['first_name'] . ' ' . $placement['last_name']) ?> against the department criteria. The guidebook prints this form twice, as D1 at week 6 and D2 at week 12; it carries 40 of the 100 practical marks in Form E.</p>
    </div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('supervisor/queue')) ?>">Back to queue</a></div>
</div>

<div class="card">
    <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="form-grid">
        <input type="hidden" name="r" value="supervisor/evaluation">
        <div class="field">
            <label for="placement_id">Student</label>
            <select id="placement_id" name="placement_id">
                <?php foreach ($placements as $item): ?>
                    <option value="<?= (int) $item['placement_id'] ?>"<?= selected($item['placement_id'], $placement['placement_id']) ?>><?= e($item['first_name'] . ' ' . $item['last_name'] . ' — ' . $item['org_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="btn btn-outline" type="submit">Load student</button>
        </div>
    </form>
</div>

<?php if (abs($weightTotal - 100) > 0.01): ?>
    <div class="alert alert-error" role="alert">Criterion weights currently total <?= e(number_format($weightTotal, 1)) ?>%. An evaluation cannot be submitted until they total 100%.</div>
<?php endif; ?>

<?php if ($locked): ?>
    <div class="card">
        <div class="card-head"><div><h2>Submitted evaluation</h2><p>Submitted <?= e(fmt_dt($evaluation['submitted_at'])) ?> · total <?= e(number_format((float) $evaluation['total_score'], 2)) ?>%</p></div></div>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Criterion</th><th class="right">Max</th><th class="right">Weight</th><th class="right">Score</th><th>Comment</th></tr></thead>
                <tbody>
                <?php foreach ($criteria as $criterion): $row = $scores[(int) $criterion['criterion_id']] ?? null; ?>
                    <tr>
                        <td data-label="Criterion"><?= e($criterion['name']) ?></td>
                        <td data-label="Max" class="right"><?= (int) $criterion['max_score'] ?></td>
                        <td data-label="Weight" class="right"><?= (int) $criterion['weight'] ?>%</td>
                        <td data-label="Score" class="right"><?= $row ? e(number_format((float) $row['score'], 1)) : '—' ?></td>
                        <td data-label="Comment"><?= e($row['comment'] ?? '') ?: '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!empty($evaluation['comments'])): ?>
            <div class="comment"><div class="who">Overall comment</div><p><?= nl2br(e($evaluation['comments'])) ?></p></div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <form method="post" action="<?= e(url('supervisor/evaluation/save')) ?>" id="evaluation-form">
        <?= csrf_field() ?>
        <input type="hidden" name="placement_id" value="<?= (int) $placement['placement_id'] ?>">
        <section class="card">
            <div class="card-head"><div><h2>Weighted criteria</h2><p>Score each criterion out of its maximum. The total updates as you type.</p></div><div class="kpi-value" id="eval-total">0.00%</div></div>
            <div class="table-wrap">
                <table class="table table--stack">
                    <thead><tr><th>Criterion</th><th class="right">Max</th><th class="right">Weight</th><th>Score</th><th>Comment</th></tr></thead>
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
                            <td data-label="Comment">
                                <label class="sr-only" for="comment-<?= (int) $criterion['criterion_id'] ?>">Evidence for <?= e($criterion['name']) ?></label>
                                <input id="comment-<?= (int) $criterion['criterion_id'] ?>" name="comments[<?= (int) $criterion['criterion_id'] ?>]" type="text" maxlength="500" value="<?= $row ? e($row['comment'] ?? '') : '' ?>">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="field">
                <label for="comments_general">Overall comment</label>
                <textarea id="comments_general" name="comments_general" rows="4" maxlength="2000"><?= e($evaluation['comments'] ?? '') ?></textarea>
                <span class="hint">Summarise strengths and development areas. The coordinator sees this when grading.</span>
            </div>
            <div class="form-actions">
                <button class="btn btn-outline" type="submit" name="save" value="1">Save draft</button>
                <button class="btn" type="submit" name="submit" value="1" id="eval-submit" data-confirm="Submit this evaluation? It is sent to the coordinator and cannot be edited afterwards.">Submit evaluation</button>
            </div>
        </section>
    </form>
<?php endif; ?>
