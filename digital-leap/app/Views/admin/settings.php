<?php
/** @var array $settings */
$practicalWeights = ['weight_logbook', 'weight_supervisor', 'weight_self'];
$sum = 0.0;
foreach ($settings as $row) {
    if (in_array($row['setting_key'], $practicalWeights, true)) {
        $sum += (float) $row['setting_value'];
    }
}
?>
<div class="page-head">
    <div>
        <h1>System settings</h1>
        <p>Mark weights, session policy and mail delivery. Every change is written to the audit log.</p>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div><h2>Weights</h2><p>Practical Internship split: <strong><?= e(number_format($sum, 2)) ?>%</strong>. Logbook, Form D and self evaluation must add up to 100. Report and Career Fair are marks out of 100, carried at 75 and 25 of the 200 final marks.</p></div>
    </div>
</div>

<div class="card">
    <form method="post" action="<?= e(url('admin/settings/save')) ?>">
        <?= csrf_field() ?>
        <div class="form-grid">
            <?php foreach ($settings as $row): ?>
                <?php
                $key = (string) $row['setting_key'];
                if ($key === 'weight_mentor') {
                    continue;
                }
                $type = (string) $row['data_type'];
                $value = (string) $row['setting_value'];
                ?>
                <div class="field">
                    <label for="set-<?= e($key) ?>"><?= e(str_replace('_', ' ', ucfirst($key))) ?></label>
                    <?php if ($key === 'mail_mode'): ?>
                        <select id="set-<?= e($key) ?>" name="setting[<?= e($key) ?>]">
                            <option value="log"<?= selected($value, 'log') ?>>log — write to storage/logs/mail.log</option>
                            <option value="smtp"<?= selected($value, 'smtp') ?>>smtp — send through a mail server</option>
                        </select>
                    <?php elseif ($key === 'logbook_due_day'): ?>
                        <select id="set-<?= e($key) ?>" name="setting[<?= e($key) ?>]">
                            <?php foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day): ?>
                                <option value="<?= e($day) ?>"<?= selected($value, $day) ?>><?= e($day) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($type === 'decimal'): ?>
                        <input id="set-<?= e($key) ?>" name="setting[<?= e($key) ?>]" type="number" step="0.01" min="0" max="100" value="<?= e($value) ?>">
                    <?php elseif ($type === 'int'): ?>
                        <input id="set-<?= e($key) ?>" name="setting[<?= e($key) ?>]" type="number" step="1" min="0" value="<?= e($value) ?>">
                    <?php else: ?>
                        <input id="set-<?= e($key) ?>" name="setting[<?= e($key) ?>]" type="text" maxlength="255" value="<?= e($value) ?>">
                    <?php endif; ?>
                    <span class="hint"><?= e($row['description']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Save settings</button></div>
    </form>
</div>
