<?php
/** @var array $tracker */
/** @var string|null $trackerTitle */
/** @var string|null $trackerIntro */
$trackerTitle = $trackerTitle ?? 'Internship progress';
$trackerIntro = $trackerIntro ?? 'Every milestone from allocation to published result, updated automatically.';
$stateLabels = ['done' => 'Done', 'active' => 'In progress', 'todo' => 'Upcoming'];
$doneSteps = count(array_filter($tracker['steps'], static fn (array $step): bool => $step['state'] === 'done'));
$totalSteps = count($tracker['steps']);
?>
<section class="card">
    <div class="card-head">
        <div><h2><?= e($trackerTitle) ?></h2><p><?= e($trackerIntro) ?></p></div>
        <span class="badge badge-<?= (int) $tracker['percent'] >= 100 ? 'ok' : 'info' ?>"><?= (int) $tracker['percent'] ?>% complete</span>
    </div>
    <div class="tracker-head">
        <span class="tracker-percent"><?= (int) $tracker['percent'] ?>%</span>
        <div>
            <div class="tracker-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) $tracker['percent'] ?>" aria-label="Internship progress: <?= (int) $tracker['percent'] ?> percent, <?= $doneSteps ?> of <?= $totalSteps ?> milestones complete">
                <span data-width="<?= (int) $tracker['percent'] ?>"></span>
            </div>
            <p class="tracker-caption">
                <?= $doneSteps ?> of <?= $totalSteps ?> milestones complete
                <?= $tracker['placement'] !== null ? ' | Placement at ' . e((string) ($tracker['placement']['org_name'] ?? 'the host organisation')) . ' | ' . (int) $tracker['placement']['total_weeks'] . ' weeks' : ' | No placement allocated yet.' ?>
            </p>
        </div>
    </div>
    <ol class="tracker-steps">
        <?php foreach ($tracker['steps'] as $index => $step): ?>
            <li class="tracker-step is-<?= e($step['state']) ?>">
                <span class="tracker-dot" aria-hidden="true"><?= $step['state'] === 'done' ? '&#10003;' : (int) $index + 1 ?></span>
                <div class="tracker-copy">
                    <strong><?= e($step['label']) ?></strong>
                    <span><?= e($step['detail']) ?></span>
                </div>
                <span class="tracker-state"><?= e($stateLabels[$step['state']] ?? 'Upcoming') ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
