<?php
/** @var array $checkIns */
/** @var array{lat: float, lng: float}|null $checkInsPin */
?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Attendance check-ins</h2>
            <p>The student's deliberate site check-ins. Location is captured only when they press a button; staff are not tracked.</p>
        </div>
    </div>
    <p class="small muted">Site pin: <?= $checkInsPin !== null ? e(number_format($checkInsPin['lat'], 5) . ', ' . number_format($checkInsPin['lng'], 5)) : 'not set' ?>. Distances are measured when the student checks in.</p>
    <?php if ($checkIns === []): ?>
        <div class="empty"><strong>No check-ins yet</strong>The student can check in from My placement.</div>
    <?php else: ?>
        <ul class="timeline">
            <?php foreach ($checkIns as $event): ?>
                <li>
                    <div>
                        <strong><?= $event['type'] === 'check_in' ? 'Check in' : 'Check out' ?></strong>
                        <span class="small muted">· <?= e(fmt_dt($event['at'])) ?></span>
                    </div>
                    <div class="when">
                        <?= $event['distance_m'] !== null ? e(fmt_distance($event['distance_m'])) . ' from pin' : 'No distance' ?>
                        · <?= $event['source'] === 'device' ? 'device location' : 'attested' ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
