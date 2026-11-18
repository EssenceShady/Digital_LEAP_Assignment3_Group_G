<?php
/** @var array $student */
/** @var array $prefs */
/** @var array $orgs */
/** @var bool $submitted */
/** @var array|null $placement */
/** @var string $year */
?>
<div class="page-head">
    <div>
        <h1>Host organisation preferences</h1>
        <p>Rank up to five organisations for <?= e($year) ?>. The coordinator allocates one placement per student per academic year.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('student/placement')) ?>">Placement status</a>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head">
            <div><h2>My ranked list</h2><p><?= count($prefs) ?> of 5 preferences used.</p></div>
            <?php if (!$submitted && $prefs !== [] && $placement === null): ?>
                <form method="post" action="<?= e(url('student/preferences/submit')) ?>" data-confirm="Submit this ranked list for review? You will not be able to edit it afterwards.">
                    <?= csrf_field() ?>
                    <button class="btn" type="submit">Submit for review</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($placement !== null): ?>
            <div class="alert alert-info" role="status">
                <span>A placement has been allocated for <?= e($placement['academic_year']) ?> at <?= e($placement['org_name']) ?>.</span>
                <a href="<?= e(url('student/placement')) ?>">View the placement</a>
            </div>
        <?php endif; ?>

        <?php if ($prefs === []): ?>
            <div class="empty">
                <strong>No preferences yet</strong>
                Add a host organisation on the right. Rank them in the order you would like to be placed.
            </div>
        <?php else: ?>
            <?php $locked = $placement !== null; ?>
            <ol class="rank-list">
                <?php foreach ($prefs as $pref): ?>
                    <?php $rank = (int) $pref['preference_rank']; ?>
                    <li class="rank-item<?= $pref['status'] === 'allocated' ? ' is-current' : '' ?>">
                        <span class="rank-number" aria-hidden="true"><?= $rank ?></span>
                        <div class="rank-body">
                            <div class="rank-top">
                                <div class="rank-title">
                                    <strong><?= e($pref['org_name']) ?></strong>
                                    <span class="rank-meta">
                                        Rank <?= $rank ?> of 5
                                        <span aria-hidden="true">·</span>
                                        <?= e($pref['sector']) ?>
                                        <span aria-hidden="true">·</span>
                                        <?= e($pref['district']) ?> district
                                        <span aria-hidden="true">·</span>
                                        capacity <?= (int) $pref['capacity'] ?>
                                    </span>
                                </div>
                                <div class="rank-status"><?= status_badge($pref['status']) ?></div>
                            </div>
                            <p class="rank-motivation">
                                <span class="rank-motivation-label">Why this organisation</span>
                                <?= e($pref['motivation']) ?>
                            </p>
                            <?php if (!$locked && $pref['status'] === 'draft'): ?>
                                <div class="rank-actions">
                                    <form method="post" action="<?= e(url('student/preferences/move')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="application_id" value="<?= (int) $pref['application_id'] ?>">
                                        <input type="hidden" name="direction" value="up">
                                        <button class="btn btn-sm btn-quiet" type="submit"<?= $rank === 1 ? ' disabled' : '' ?> aria-label="Move <?= e($pref['org_name']) ?> up">
                                            <span aria-hidden="true">↑</span> Move up
                                        </button>
                                    </form>
                                    <form method="post" action="<?= e(url('student/preferences/move')) ?>" class="inline-form">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="application_id" value="<?= (int) $pref['application_id'] ?>">
                                        <input type="hidden" name="direction" value="down">
                                        <button class="btn btn-sm btn-quiet" type="submit"<?= $rank === count($prefs) ? ' disabled' : '' ?> aria-label="Move <?= e($pref['org_name']) ?> down">
                                            <span aria-hidden="true">↓</span> Move down
                                        </button>
                                    </form>
                                    <form method="post" action="<?= e(url('student/preferences/remove')) ?>" class="inline-form" data-confirm="Remove <?= e($pref['org_name']) ?> from your preference list?">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="application_id" value="<?= (int) $pref['application_id'] ?>">
                                        <button class="btn btn-sm btn-danger" type="submit">Remove</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if ($locked): ?>
                <p class="rank-lock-note">Preferences are locked because a placement exists. Reordering and removal are unavailable.</p>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Add an organisation</h2><p>Only active organisations with a signed or pending MoU are listed.</p></div></div>
        <?php if ($placement !== null): ?>
            <div class="empty"><strong>Allocation complete</strong>Preferences are read-only once a placement exists.</div>
        <?php elseif (count($prefs) >= 5): ?>
            <div class="empty"><strong>List is full</strong>Remove a preference before adding another.</div>
        <?php elseif ($orgs === []): ?>
            <div class="empty"><strong>No organisations available</strong>The coordinator is onboarding host organisations.</div>
        <?php else: ?>
            <form method="post" action="<?= e(url('student/preferences/add')) ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="org_id">Organisation</label>
                    <select id="org_id" name="org_id" required>
                        <option value="">Choose an organisation</option>
                        <?php foreach ($orgs as $org): ?>
                            <option value="<?= (int) $org['org_id'] ?>"><?= e($org['name']) ?> — <?= e($org['district']) ?> (<?= e($org['sector']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Choose the organisation you would most like to join. You can reorder afterwards.</span>
                </div>
                <div class="field">
                    <label for="motivation">Why this organisation?</label>
                    <textarea id="motivation" name="motivation" rows="5" maxlength="600" required></textarea>
                    <span class="hint">At least ten characters. The coordinator reads this when reviewing applications.</span>
                </div>
                <div class="form-actions">
                    <button class="btn" type="submit">Add preference</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>
