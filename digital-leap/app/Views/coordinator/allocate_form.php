<?php
/** @var array $student */
/** @var string $year */
/** @var array $orgs */
/** @var array $mentors */
/** @var array $supervisors */
/** @var array $prefs */
$rankedIds = array_map(static fn (array $p): int => (int) $p['org_id'], $prefs);
$defaultOrgId = 0;
foreach ($prefs as $pref) {
    $eligible = $pref['org_status'] === 'active'
        && in_array((string) $pref['mou_status'], ['signed', 'pending'], true)
        && (int) $pref['used'] < (int) $pref['capacity'];
    if ($eligible) {
        $defaultOrgId = (int) $pref['org_id'];
        break;
    }
}
$otherOrgs = array_values(array_filter($orgs, static fn (array $o): bool => !in_array((int) $o['org_id'], $rankedIds, true)));
?>
<div class="page-head">
    <div>
        <h1>Allocate a placement</h1>
        <p><?= e($student['first_name'] . ' ' . $student['last_name']) ?> · <?= e($student['reg_no']) ?> · <?= e($year) ?></p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('coordinator/applications/view', ['student_id' => (int) $student['student_id']])) ?>">Back to application</a>
        <a class="btn btn-quiet" href="<?= e(url('coordinator/applications')) ?>">Applications queue</a>
    </div>
</div>

<section class="card allocation-note">
    <div class="card-head">
        <div><h2>Who chooses the host?</h2><p>The internship office allocates, not the host organisation.</p></div>
    </div>
    <p class="small">The student ranked the hosts they want. That ranking is advice: you make the final allocation and may place the student with any active host whose MoU is signed or pending. Saving this placement closes their ranked list automatically.</p>
</section>

<form method="post" action="<?= e(url('coordinator/placements/save')) ?>" data-confirm="Create this placement? The student, mentor and supervisor are notified and the student's other applications are closed.">
    <?= csrf_field() ?>
    <input type="hidden" name="student_id" value="<?= (int) $student['student_id'] ?>">
    <input type="hidden" name="academic_year" value="<?= e($year) ?>">

    <section class="card">
        <div class="card-head">
            <div><h2>Step 1 · Choose the host organisation</h2><p>The student's ranked preferences, in their order.</p></div>
            <span class="badge badge-muted"><?= count($prefs) ?> ranked</span>
        </div>

        <?php if ($prefs === []): ?>
            <div class="empty"><strong>No ranked preferences</strong>This student has not submitted a list for <?= e($year) ?>. You can still allocate from another active host below.</div>
        <?php else: ?>
            <div class="pick-list">
                <?php foreach ($prefs as $pref): ?>
                    <?php
                    $used = (int) $pref['used'];
                    $capacity = (int) $pref['capacity'];
                    $full = $used >= $capacity;
                    $mouOk = in_array((string) $pref['mou_status'], ['signed', 'pending'], true);
                    $blocked = !$mouOk || $full || $pref['org_status'] !== 'active';
                    $reason = '';
                    if ($pref['org_status'] !== 'active') {
                        $reason = 'This host is inactive.';
                    } elseif (!$mouOk) {
                        $reason = 'The MoU is ' . $pref['mou_status'] . '. Renew it before placing a student here.';
                    } elseif ($full) {
                        $reason = 'At capacity for ' . $year . ' (' . $used . ' of ' . $capacity . ').';
                    }
                    ?>
                    <label class="pick<?= $blocked ? ' is-blocked' : '' ?>">
                        <input type="radio" name="org_id" value="<?= (int) $pref['org_id'] ?>"<?= $defaultOrgId === (int) $pref['org_id'] ? ' checked' : '' ?><?= $blocked ? ' disabled' : '' ?>>
                        <span class="pick-body">
                            <span class="pick-top">
                                <span class="pick-rank">Preference <?= (int) $pref['preference_rank'] ?></span>
                                <span class="pick-badges">
                                    <?= status_badge($pref['mou_status']) ?>
                                    <?= status_badge($pref['status']) ?>
                                </span>
                            </span>
                            <span class="pick-title"><?= e($pref['name']) ?></span>
                            <span class="pick-meta"><?= e($pref['district']) ?><?= $pref['sector'] !== '' ? ' · ' . e($pref['sector']) : '' ?> · capacity <?= $used ?> of <?= $capacity ?> used</span>
                            <?php if (trim((string) $pref['motivation']) !== ''): ?>
                                <span class="rank-motivation"><span class="rank-motivation-label">Why this host</span><?= e($pref['motivation']) ?></span>
                            <?php endif; ?>
                            <?php if ($blocked): ?>
                                <span class="pick-reason">Not selectable: <?= e($reason) ?></span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="field allocation-other">
            <label for="org_id_other">Or choose another active host</label>
            <select id="org_id_other" name="org_id_other" data-org-other>
                <option value="">Keep the preference selected above</option>
                <?php if ($otherOrgs !== []): ?>
                    <optgroup label="Available for <?= e($year) ?>">
                        <?php foreach ($otherOrgs as $org): ?>
                            <?php
                            $orgFree = in_array((string) $org['mou_status'], ['signed', 'pending'], true) && (int) $org['used'] < (int) $org['capacity'];
                            ?>
                            <option value="<?= (int) $org['org_id'] ?>"<?= $orgFree ? '' : ' disabled' ?>>
                                <?= e($org['name']) ?> — <?= e($org['district']) ?> (<?= (int) $org['used'] ?> of <?= (int) $org['capacity'] ?> used, MoU <?= e($org['mou_status']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
            </select>
            <span class="hint">Choosing a host here overrides the ranked list. Hosts with an expired MoU or no spare capacity are disabled.</span>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Step 2 · Type, supervision and dates</h2><p>Where the student works, who supports them, and for how long.</p></div></div>
        <div class="form-grid">
            <div class="field">
                <label for="internship_type">Internship type</label>
                <select id="internship_type" name="internship_type">
                    <option value="external">External placement — at the host's site</option>
                    <option value="in-house">In-house — LEAP acceleration platform on campus</option>
                </select>
                <span class="hint">The guidebook runs both types with the same structure and assessment. In-house students work on the LEAP platform and still need a mentor and a supervisor.</span>
            </div>
            <div class="field">
                <label for="mentor_id">Academic mentor</label>
                <select id="mentor_id" name="mentor_id" required>
                    <option value="">Choose a mentor</option>
                    <?php foreach ($mentors as $mentor): ?>
                        <option value="<?= (int) $mentor['user_id'] ?>"><?= e($mentor['first_name'] . ' ' . $mentor['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">The mentor reviews logbook entries and marks the report.</span>
            </div>
            <div class="field">
                <label for="supervisor_id">Host supervisor</label>
                <select id="supervisor_id" name="supervisor_id">
                    <option value="0">Assign later</option>
                    <?php foreach ($supervisors as $supervisor): ?>
                        <option value="<?= (int) $supervisor['user_id'] ?>"><?= e($supervisor['first_name'] . ' ' . $supervisor['last_name']) ?> — <?= e($supervisor['org_name'] ?? 'No organisation') ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">The supervisor must belong to the chosen organisation.</span>
            </div>
            <div class="field">
                <label for="start_date">Start date</label>
                <input id="start_date" name="start_date" type="date" value="<?= e(date('Y-m-d', strtotime('+14 days'))) ?>" required>
            </div>
            <div class="field">
                <label for="end_date">End date</label>
                <input id="end_date" name="end_date" type="date" value="<?= e(date('Y-m-d', strtotime('+98 days'))) ?>" required>
                <span class="hint">The end date must be after the start date. Use the internship period from the guidebook.</span>
            </div>
        </div>
    </section>

    <div class="form-actions">
        <button class="btn" type="submit">Create placement</button>
        <a class="btn btn-outline" href="<?= e(url('coordinator/applications/view', ['student_id' => (int) $student['student_id']])) ?>">Cancel</a>
    </div>
</form>
