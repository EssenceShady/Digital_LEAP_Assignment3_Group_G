<?php
/** @var array $placement */
/** @var array|null $entry */
/** @var int $week */
/** @var string $dateFrom */
/** @var string $dateTo */
$isEdit = $entry !== null;
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Edit week ' . (int) $entry['week_no'] : 'New logbook entry' ?></h1>
        <p>Record what you did, where and for how long. Your host supervisor approves each week.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('student/logbook')) ?>">Back to logbook</a>
    </div>
</div>

<div class="card">
    <form method="post" action="<?= e(url('student/logbook/save')) ?>">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="entry_id" value="<?= (int) $entry['entry_id'] ?>">
        <?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label for="week_no">Week number</label>
                <input id="week_no" name="week_no" type="number" min="1" max="<?= (int) $placement['total_weeks'] ?>" value="<?= e($isEdit ? (int) $entry['week_no'] : $week) ?>" required>
                <span class="hint">Between 1 and <?= (int) $placement['total_weeks'] ?>.</span>
            </div>
            <div class="field">
                <label for="hours">Hours worked</label>
                <input id="hours" name="hours" type="number" step="0.5" min="0.5" max="60" value="<?= e($isEdit ? (float) $entry['hours'] : '40') ?>" required>
                <span class="hint">Total hours for the week.</span>
            </div>
            <div class="field">
                <label for="date_from">Week starting</label>
                <input id="date_from" name="date_from" type="date" value="<?= e($isEdit ? $entry['date_from'] : $dateFrom) ?>" min="<?= e($placement['start_date']) ?>" max="<?= e($placement['end_date']) ?>" required>
            </div>
            <div class="field">
                <label for="date_to">Week ending</label>
                <input id="date_to" name="date_to" type="date" value="<?= e($isEdit ? $entry['date_to'] : $dateTo) ?>" min="<?= e($placement['start_date']) ?>" max="<?= e($placement['end_date']) ?>" required>
            </div>
            <div class="field full">
                <label for="activities">Activities</label>
                <textarea id="activities" name="activities" rows="9" maxlength="3000" required><?= e($isEdit ? $entry['activities'] : '') ?></textarea>
                <span class="hint">Describe the tasks you carried out, the systems you used and what you learned. At least twenty characters.</span>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-outline" type="submit" name="save" value="1">Save draft</button>
            <button class="btn" type="submit" name="submit" value="1" data-confirm="Submit this week to your host supervisor? You cannot edit it afterwards unless it is returned.">Submit for approval</button>
        </div>
    </form>
</div>
