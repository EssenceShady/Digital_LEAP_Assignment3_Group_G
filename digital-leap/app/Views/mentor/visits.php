<?php
/** @var array $visits */
/** @var array $overdue */
/** @var array $placements */
/** @var array $proofs */
?>
<div class="page-head">
    <div>
        <h1>Site visits</h1>
        <p>Schedule, complete and evidence academic supervision visits.</p>
    </div>
    <div class="page-actions"><a class="btn" href="<?= e(url('mentor/visits/form')) ?>">Schedule a visit</a></div>
</div>

<div class="card">
    <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="filter-bar">
        <input type="hidden" name="r" value="mentor/visits">
        <div class="field"><label for="q">Search</label><input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Student, registration or organisation"></div>
        <div class="field"><label for="status">Status</label><select id="status" name="status">
            <option value="">All statuses</option>
            <?php foreach (['scheduled', 'completed', 'cancelled', 'missed'] as $option): ?><option value="<?= e($option) ?>"<?= selected($status, $option) ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label for="from">Scheduled from</label><input id="from" name="from" type="date" value="<?= e($from) ?>"></div>
        <div class="field"><label for="to">Scheduled to</label><input id="to" name="to" type="date" value="<?= e($to) ?>"></div>
        <div class="field field-actions"><button class="btn btn-outline" type="submit">Apply filters</button><a class="btn btn-quiet" href="<?= e(url('mentor/visits')) ?>">Reset</a></div>
    </form>
</div>
<?php if ($overdue !== []): ?>
    <div class="alert alert-error" role="alert"><?= count($overdue) ?> matching scheduled <?= count($overdue) === 1 ? 'visit is' : 'visits are' ?> overdue. Complete them or record the outcome.</div>
<?php endif; ?>

<div class="card">
    <div class="card-head"><div><h2>Matching visits</h2><p><?= count($visits) ?> recorded.</p></div></div>
    <?php if ($visits === []): ?>
        <div class="empty">
            <strong>No visits match</strong>
            Reset the filters to see all assigned visits.
            <div class="form-actions"><a class="btn" href="<?= e(url('mentor/visits/form')) ?>">Schedule a visit</a></div>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Student</th><th>Scheduled</th><th>Mode</th><th>Status</th><th>Proof</th><th>Outcome</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($visits as $visit): ?>
                    <?php
                    $visitId = (int) $visit['visit_id'];
                    $proof = $proofs[$visitId] ?? null;
                    $physical = $visit['mode'] === 'physical';
                    $needsProof = $physical && $visit['status'] === 'scheduled' && $proof === null;
                    ?>
                    <tr id="visit-<?= $visitId ?>">
                        <td data-label="Student"><strong><?= e($visit['first_name'] . ' ' . $visit['last_name']) ?></strong><div class="small muted"><?= e($visit['org_name']) ?></div></td>
                        <td data-label="Scheduled"><?= e(fmt_date($visit['scheduled_date'])) ?><?= $visit['actual_date'] ? '<div class="small muted">actual ' . e(fmt_date($visit['actual_date'])) . '</div>' : '' ?></td>
                        <td data-label="Mode"><?= e(ucfirst((string) $visit['mode'])) ?></td>
                        <td data-label="Status"><?= status_badge($visit['status']) ?><?= $visit['status'] === 'scheduled' && $visit['scheduled_date'] < date('Y-m-d') ? ' <span class="badge badge-bad">Overdue</span>' : '' ?></td>
                        <td data-label="Proof">
                            <?php if ($proof !== null): ?>
                                <span class="badge badge-ok">Site verified</span>
                                <div class="small muted"><?= e(fmt_dt($proof['at'])) ?> · <?= $proof['distance_m'] !== null ? e(fmt_distance($proof['distance_m'])) . ' from pin' : 'no site pin' ?></div>
                            <?php elseif ($needsProof): ?>
                                <span class="badge badge-warn">Not checked in</span>
                            <?php elseif ($physical): ?>
                                <span class="badge badge-muted">No location proof</span>
                            <?php else: ?>
                                <span class="badge badge-muted">Not required</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Outcome">
                            <?php if ($visit['status'] === 'completed'): ?>
                                <div class="small"><?= e($visit['findings']) ?></div>
                            <?php else: ?>
                                <span class="muted small">Not yet completed</span>
                            <?php endif; ?>
                        </td>
                        <td class="right nowrap">
                            <?php if ($visit['report_doc']): ?>
                                <a class="btn btn-sm btn-quiet" href="<?= e(url('download', ['id' => (int) $visit['report_doc']])) ?>">Report</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($visit['status'] === 'scheduled'): ?>
                        <tr>
                            <td colspan="7">
                                <?php if ($needsProof): ?>
                                    <form method="post" action="<?= e(url('mentor/visits/checkin')) ?>" data-sitecheckin-form>
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="visit_id" value="<?= $visitId ?>">
                                        <input type="hidden" name="lat" value="">
                                        <input type="hidden" name="lng" value="">
                                        <input type="hidden" name="accuracy" value="">
                                        <p class="small muted" data-checkin-status role="status" aria-live="polite">Press this while you are at the site. Your browser will ask for location permission.</p>
                                        <div class="form-actions"><button class="btn btn-outline" type="submit">Check in at site</button></div>
                                    </form>
                                <?php elseif ($proof !== null && $physical): ?>
                                    <p class="small muted">Checked in at the site on <?= e(fmt_dt($proof['at'])) ?><?= $proof['distance_m'] !== null ? ' (' . e(fmt_distance($proof['distance_m'])) . ' from the pin)' : '' ?>.</p>
                                <?php endif; ?>
                                <details>
                                    <summary class="small"><strong>Record the outcome</strong></summary>
                                    <form method="post" action="<?= e(url('mentor/visits/complete')) ?>" enctype="multipart/form-data" data-confirm="Mark this visit as completed?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="visit_id" value="<?= $visitId ?>">
                                        <div class="form-grid">
                                            <div class="field">
                                                <label for="actual-<?= $visitId ?>">Actual date</label>
                                                <input id="actual-<?= $visitId ?>" name="actual_date" type="date" value="<?= e(date('Y-m-d')) ?>" required>
                                            </div>
                                            <div class="field">
                                                <label for="report-<?= $visitId ?>">Visit report (optional)</label>
                                                <input id="report-<?= $visitId ?>" name="report" type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                            </div>
                                            <div class="field full">
                                                <label for="findings-<?= $visitId ?>">Findings</label>
                                                <textarea id="findings-<?= $visitId ?>" name="findings" rows="3" required></textarea>
                                            </div>
                                            <div class="field full">
                                                <label for="recommendations-<?= $visitId ?>">Recommendations</label>
                                                <textarea id="recommendations-<?= $visitId ?>" name="recommendations" rows="3" required></textarea>
                                            </div>
                                        </div>
                                        <div class="form-actions">
                                            <button class="btn" type="submit"<?= $needsProof ? ' disabled' : '' ?>>Complete visit</button>
                                            <?php if ($needsProof): ?>
                                                <span class="hint">Check in at the site before completing a physical visit.</span>
                                            <?php elseif (!$physical): ?>
                                                <span class="hint">Virtual and phone visits do not need a site check-in.</span>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </details>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
