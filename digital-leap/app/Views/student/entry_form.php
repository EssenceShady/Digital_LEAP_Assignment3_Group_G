<?php
/** @var array $placement */
/** @var array|null $entry */
/** @var int $week */
/** @var array $days */
$isEdit = $entry !== null;
$weekNo = $isEdit ? (int) $entry['week_no'] : (int) $week;
?>
<div class="page-head">
    <div>
        <h1><?= $isEdit ? 'Edit week ' . $weekNo . ' log book' : 'New log book entry' ?> (Form E)</h1>
        <p>Record each working day the way the paper log book asks for it, then close the week with your summary and reflection.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('student/logbook')) ?>">Back to logbook</a>
        <a class="btn btn-outline" href="<?= e(url('logbook/template')) ?>">Open paper log book</a>
    </div>
</div>

<form method="post" action="<?= e(url('student/logbook/save')) ?>" data-draft-form>
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="entry_id" value="<?= (int) $entry['entry_id'] ?>">
    <?php endif; ?>

    <div class="card">
        <div class="form-grid">
            <div class="field">
                <label for="week_no">Week number</label>
                <input id="week_no" name="week_no" type="number" min="1" max="<?= (int) $placement['total_weeks'] ?>" value="<?= e($weekNo) ?>" required>
                <span class="hint">Between 1 and <?= (int) $placement['total_weeks'] ?>. The dates below follow the placement start date.</span>
            </div>
            <div class="field">
                <label>Placement</label>
                <input type="text" value="<?= e($placement['start_date'] . ' to ' . $placement['end_date']) ?>" readonly>
                <span class="hint">Days outside this period cannot be recorded.</span>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Daily record</h2>
        <p class="hint">One row per day, as in section 2 of the log book. Only days inside your placement period are listed, so a first or last week can be shorter than seven rows. Leave a row empty for weekends, public holidays or days you were not at the placement. Each saved day is emailed to your host supervisor and academic mentor that evening, even while the week is a draft; the full week is emailed again when you submit it.</p>
        <div class="table-wrap">
            <table class="table logbook-day-table">
                <thead>
                    <tr>
                        <th class="day-col">Day</th>
                        <th class="date-col">Date</th>
                        <th>Tasks performed</th>
                        <th class="right hours-col">Hours</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($days as $day): ?>
                        <tr>
                            <td class="day-col"><?= e(date('D', strtotime($day['date']))) ?></td>
                            <td class="date-col"><input type="date" name="day_date[]" value="<?= e($day['date']) ?>" min="<?= e($placement['start_date']) ?>" max="<?= e($placement['end_date']) ?>"></td>
                            <td><input type="text" name="day_tasks[]" value="<?= e($day['tasks']) ?>" maxlength="1000" placeholder="What did you work on?"></td>
                            <td class="right hours-col"><input class="hours-input" type="number" name="day_hours[]" value="<?= e($day['hours'] === null ? '' : (float) $day['hours']) ?>" step="0.5" min="0" max="24"></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="hint">The week total is calculated from these rows and stored with the entry.</p>
    </div>

    <div class="card">
        <h2>Weekly summary</h2>
        <p class="hint">The paragraph a reader meets first: what the week was for, what you delivered and what moved forward. At least twenty characters.</p>
        <div class="field full">
            <label for="activities">Summary of the week</label>
            <textarea id="activities" name="activities" rows="7" maxlength="3000" required><?= e($isEdit ? (string) $entry['activities'] : '') ?></textarea>
        </div>
    </div>

    <div class="card">
        <h2>Weekly sections</h2>
        <p class="hint">The remaining sections of the paper log book. Only the summary above is compulsory; fill these when they apply.</p>
        <div class="form-grid">
            <div class="field full">
                <label for="technical_skills">Technical skills learned</label>
                <textarea id="technical_skills" name="technical_skills" rows="3" maxlength="2000" placeholder="Tools, languages, methods or procedures you picked up."><?= e($isEdit ? (string) ($entry['technical_skills'] ?? '') : '') ?></textarea>
            </div>
            <div class="field full">
                <label for="challenges">Challenges experienced</label>
                <textarea id="challenges" name="challenges" rows="3" maxlength="2000" placeholder="What blocked you, and how you worked around it."><?= e($isEdit ? (string) ($entry['challenges'] ?? '') : '') ?></textarea>
            </div>
            <div class="field full">
                <label for="meeting_notes">Meeting with mentor or industry supervisor</label>
                <textarea id="meeting_notes" name="meeting_notes" rows="3" maxlength="2000" placeholder="Who you met, when, and what was agreed."><?= e($isEdit ? (string) ($entry['meeting_notes'] ?? '') : '') ?></textarea>
            </div>
            <div class="field full">
                <label for="reflection">Reflection</label>
                <textarea id="reflection" name="reflection" rows="4" maxlength="2000" placeholder="What the week taught you about the work, the organisation or your own practice."><?= e($isEdit ? (string) ($entry['reflection'] ?? '') : '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn btn-outline" type="submit" name="save" value="1">Save draft</button>
        <button class="btn" type="submit" name="submit" value="1" data-confirm="Submit this week to your host supervisor? The full week is emailed to your supervisor and mentor, and you cannot edit it afterwards unless it is returned.">Submit for approval</button>
    </div>
</form>
