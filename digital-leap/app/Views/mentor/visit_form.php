<?php
/** @var array $placements */
/** @var array $placement */
?>
<div class="page-head">
    <div>
        <h1>Schedule a site visit</h1>
        <p>Choose a student, a date and how the visit will be conducted.</p>
    </div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('mentor/visits')) ?>">Back to visits</a></div>
</div>

<div class="card">
    <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="form-grid">
        <input type="hidden" name="r" value="mentor/visits/form">
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
            <button class="btn btn-outline" type="submit">Choose student</button>
        </div>
    </form>

    <form method="post" action="<?= e(url('mentor/visits/save')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="placement_id" value="<?= (int) $placement['placement_id'] ?>">
        <div class="form-grid">
            <div class="field">
                <label for="scheduled_date">Visit date</label>
                <input id="scheduled_date" name="scheduled_date" type="date" min="<?= e(date('Y-m-d')) ?>" value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>" required>
                <span class="hint">Today or later.</span>
            </div>
            <div class="field">
                <label for="mode">Mode</label>
                <select id="mode" name="mode">
                    <option value="physical">Physical visit</option>
                    <option value="virtual">Virtual meeting</option>
                    <option value="phone">Telephone</option>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn" type="submit">Schedule visit</button>
            <a class="btn btn-quiet" href="<?= e(url('mentor/visits')) ?>">Cancel</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head"><div><h2><?= e($placement['first_name'] . ' ' . $placement['last_name']) ?></h2><p><?= e($placement['org_name']) ?> · <?= e(fmt_date($placement['start_date'])) ?> – <?= e(fmt_date($placement['end_date'])) ?></p></div></div>
</div>
