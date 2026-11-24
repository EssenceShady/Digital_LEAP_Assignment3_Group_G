<?php
/** @var array $student */
/** @var array|null $mark */
?>
<div class="page-head">
    <div>
        <h1>Results (Form E)</h1>
        <p>Your final mark appears here once the coordinator publishes it, using the Form E assessment. Draft and moderated marks stay hidden.</p>
    </div>
</div>

<?php if ($mark === null): ?>
    <div class="card">
        <div class="empty">
            <strong>Results not published yet</strong>
            The coordinator publishes marks after moderation. Check your notifications.
        </div>
    </div>
<?php else: ?>
    <div class="grid grid-3">
        <div class="card kpi">
            <span class="kpi-label">Grade</span>
            <span class="kpi-value"><?= e($mark['grade_letter'] ?? '—') ?></span>
            <span class="kpi-note"><?= e($mark['classification'] ?? '') ?></span>
        </div>
        <div class="card kpi">
            <span class="kpi-label">Final mark</span>
            <span class="kpi-value"><?= e(number_format((float) $mark['weighted_total'], 1)) ?>%</span>
            <span class="kpi-note"><?= e($mark['academic_year']) ?> · <?= e($mark['org_name']) ?></span>
        </div>
        <div class="card kpi">
            <span class="kpi-label">Published</span>
            <span class="kpi-value"><?= e(fmt_date($mark['published_at'])) ?></span>
            <span class="kpi-note">Points <?= e((string) ($mark['points'] ?? '—')) ?></span>
        </div>
    </div>

    <section class="card">
        <div class="card-head"><div><h2>Breakdown</h2><p>Practical Internship 100 marks (logbook 50, Form D 40, self evaluation 10) + Internship Report 75 marks + Career Fair 25 marks, out of 200.</p></div></div>
        <div class="table-wrap">
            <?php
            $num = static fn ($value): string => $value === null ? '—' : number_format((float) $value, 2);
            $rows = [
                ['Practical · Logbook', $mark['logbook_pct'], '50% of practical', $mark['logbook_pct'] === null ? null : (float) $mark['logbook_pct'] * 0.5],
                ['Practical · Form D (host supervisor)', $mark['supervisor_pct'], '40% of practical', $mark['supervisor_pct'] === null ? null : (float) $mark['supervisor_pct'] * 0.4],
                ['Practical · Self evaluation', $mark['self_pct'], '10% of practical', $mark['self_pct'] === null ? null : (float) $mark['self_pct'] * 0.1],
                ['Practical Internship subtotal', $mark['practical_pct'], '100 marks', $mark['practical_pct']],
                ['Internship report', $mark['report_pct'], '75 marks', $mark['report_pct'] === null ? null : (float) $mark['report_pct'] * 0.75],
                ['Career Fair', $mark['career_fair_pct'], '25 marks', $mark['career_fair_pct'] === null ? null : (float) $mark['career_fair_pct'] * 0.25],
            ];
            $marksEarned = (float) ($mark['practical_pct'] ?? 0)
                + (float) ($mark['report_pct'] ?? 0) * 0.75
                + (float) ($mark['career_fair_pct'] ?? 0) * 0.25;
            ?>
            <table class="table table--stack">
                <thead><tr><th>Module</th><th class="right">Score</th><th class="right">Weight</th><th class="right">Marks</th></tr></thead>
                <tbody>
                    <?php foreach ($rows as [$label, $score, $weight, $contribution]): ?>
                        <tr>
                            <td data-label="Module"><?= e($label) ?></td>
                            <td data-label="Score" class="right"><?= $score === null ? '—' : e(number_format((float) $score, 2)) . '%' ?></td>
                            <td data-label="Weight" class="right"><?= e($weight) ?></td>
                            <td data-label="Marks" class="right"><?= e($num($contribution)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="table-total"><span>Final mark <?= e(number_format($marksEarned, 2)) ?> / 200</span><strong><?= e(number_format((float) $mark['weighted_total'], 2)) ?>%</strong></div>
        </div>
    </section>
<?php endif; ?>
