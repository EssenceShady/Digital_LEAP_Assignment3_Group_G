<?php
/** @var array $summary */
/** @var array $byOrg */
?>
<div class="page-head">
    <div>
        <h1>Management overview</h1>
        <p>Quality assurance indicators for the <?= e($summary['year']) ?> internship cycle.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('management/kpis')) ?>">Full indicators</a>
        <a class="btn btn-outline" href="<?= e(url('management/export')) ?>">Export CSV</a>
    </div>
</div>

<div class="grid grid-4">
    <a class="card kpi kpi-link" href="<?= e(url('management/kpis')) ?>" data-tour="kpis">
        <span class="kpi-label">Placements</span>
        <span class="kpi-value"><?= (int) $summary['placements'] ?></span>
        <span class="kpi-note"><?= (int) $summary['active'] ?> currently active</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('management/kpis')) ?>">
        <span class="kpi-label">Completion rate</span>
        <span class="kpi-value"><?= e($summary['completion']) ?>%</span>
        <span class="kpi-note"><?= (int) $summary['completed'] ?> completed</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('management/kpis')) ?>">
        <span class="kpi-label">Average published mark</span>
        <span class="kpi-value"><?= $summary['average'] === null ? '—' : e(number_format((float) $summary['average'], 1)) ?></span>
        <span class="kpi-note"><?= (int) $summary['published'] ?> results released</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('management/kpis')) ?>">
        <span class="kpi-label">Outstanding visits</span>
        <span class="kpi-value"><?= (int) $summary['outstandingVisits'] ?></span>
        <span class="kpi-note"><?= (int) $summary['overdue'] ?> placements behind on logbooks</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
</div>

<section class="card">
    <div class="card-head">
        <div><h2>Placements by host organisation</h2><p>Concentration risk is visible when one host dominates the intake.</p></div>
    </div>
    <?php if ($byOrg === []): ?>
        <div class="empty"><strong>No placements recorded</strong>Allocations will appear here once created.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Host organisation</th><th class="right">Placements</th><th>Share</th></tr></thead>
                <tbody>
                <?php
                $max = max(array_map(static fn (array $r): int => (int) $r['total'], $byOrg));
                $all = array_sum(array_map(static fn (array $r): int => (int) $r['total'], $byOrg));
                foreach ($byOrg as $row):
                    $width = $max > 0 ? (int) round((int) $row['total'] / $max * 100) : 0;
                    $share = $all > 0 ? (int) round((int) $row['total'] / $all * 100) : 0;
                ?>
                    <tr>
                        <td data-label="Host organisation"><?= e($row['name']) ?></td>
                        <td data-label="Placements" class="right"><?= (int) $row['total'] ?></td>
                        <td data-label="Share">
                            <div class="chart-share">
                                <div class="chart-track" aria-hidden="true"><span data-width="<?= $width ?>"></span></div>
                                <span class="chart-share-value"><?= $share ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
