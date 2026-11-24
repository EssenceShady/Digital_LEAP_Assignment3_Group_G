<?php
/** @var int $userId */
/** @var array $prefs */
use Leap\Core\Auth;
use Leap\Core\Preference;

$emailOn = static fn (string $family): bool => ($prefs['notify.email.' . $family] ?? 'on') === 'on';
$textSize = Preference::textSize($userId);
$reduceMotion = Preference::reduceMotion($userId);
?>
<div class="page-head">
    <div>
        <h1>My settings</h1>
        <p>What this account is emailed, how the pages read, and what this device keeps for offline work. Nothing here changes another person's records.</p>
    </div>
</div>

<form method="post" action="<?= e(url('settings')) ?>" class="grid grid-2" data-settings-form>
    <?= csrf_field() ?>

    <section class="card">
        <div class="card-head"><div><h2>Notifications</h2><p>The bell and your record history always keep every item; these choices only control the email copy.</p></div></div>
        <div class="settings-group">
            <?php foreach (Preference::FAMILIES as $family => $label): ?>
                <label class="check-row settings-check">
                    <input type="checkbox" name="email[<?= e($family) ?>]" value="on" <?= $emailOn($family) ? 'checked' : '' ?>>
                    <span><?= e($label) ?></span>
                </label>
            <?php endforeach; ?>
            <p class="hint">Mail is written to <code>storage/logs/mail.log</code> in this build, so nothing leaves the machine. Clearing a box stops that family from being queued at all.</p>
        </div>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Reading comfort</h2><p>Applies to this account on any device you sign in from.</p></div></div>
        <div class="settings-group">
            <fieldset class="field">
                <legend>Text size</legend>
                <label class="check-row">
                    <input type="radio" name="text_size" value="normal" <?= $textSize === 'normal' ? 'checked' : '' ?> data-preview-size>
                    <span>Normal — the text size your browser is set to</span>
                </label>
                <label class="check-row">
                    <input type="radio" name="text_size" value="large" <?= $textSize === 'large' ? 'checked' : '' ?> data-preview-size>
                    <span>Large — about one eighth bigger, headings and tables grow with it</span>
                </label>
            </fieldset>
            <label class="check-row settings-check">
                <input type="checkbox" name="reduce_motion" value="on" <?= $reduceMotion ? 'checked' : '' ?> data-preview-motion>
                <span>Reduce motion — stop slide-ins, spinners and smooth scrolling</span>
            </label>
            <p class="hint">Changes show as soon as you pick them and are kept when you save.</p>
        </div>
    </section>

    <div class="form-actions form-actions--span">
        <button class="btn" type="submit" data-tour="settings-save">Save settings</button>
        <a class="btn btn-quiet" href="<?= e(url('profile')) ?>">Edit profile and photo</a>
        <a class="btn btn-quiet" href="<?= e(url('notifications')) ?>">Open my notifications</a>
    </div>
</form>

<section class="card settings-device" data-device-panel data-user="<?= (int) $userId ?>">
    <div class="card-head"><div><h2>This device</h2><p>What the browser here holds so the app works without a connection.</p></div></div>
    <div class="settings-group">
        <div class="stat-list">
            <div class="row">
                <span>Installed app</span>
                <strong data-device-install>Checking this browser…</strong>
            </div>
            <div class="row">
                <span>DIVA answers saved on this device</span>
                <strong data-device-diva>—</strong>
            </div>
            <div class="row">
                <span>Log book changes waiting to be sent</span>
                <strong data-device-pending>—</strong>
            </div>
            <div class="row">
                <span>Pages cached for offline use</span>
                <strong data-device-cache>—</strong>
            </div>
        </div>
        <div class="form-actions">
            <button class="btn btn-outline btn-sm" type="button" data-install-trigger hidden>Install this app</button>
            <button class="btn btn-quiet btn-sm" type="button" data-device-clear-diva data-confirm="Clear the DIVA answers saved on this device? Your account history is not changed.">Clear saved DIVA answers</button>
            <button class="btn btn-quiet btn-sm" type="button" data-device-clear-shell data-confirm="Clear the pages this browser saved while you browsed? The installed app shell stays, and pages save again as you open them.">Clear browsed pages</button>
            <button class="btn btn-quiet btn-sm" type="button" data-tour-start>Show the guided tour again</button>
        </div>
        <p class="hint" data-install-help hidden></p>
        <p class="hint">Signed in as <?= e((string) (Auth::user()['email'] ?? '')) ?> · account <?= (int) $userId ?>. Offline copies are removed when you sign out.</p>
    </div>
</section>
