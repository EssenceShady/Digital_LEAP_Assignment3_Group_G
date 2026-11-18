<?php /** @var string $token */ ?>
<?php /** @var array $errors */ ?>
<div class="auth-card">
    <div class="card">
        <h1>Set a new password</h1>
        <form method="post" action="<?= e(url('reset')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="field">
                <label for="password">New password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required autofocus>
                <span class="hint">At least 8 characters with one uppercase letter, one lowercase letter and one number. Symbols are optional but make the password stronger.</span>
                <?php if (isset($errors['password'])): ?><span class="error"><?= e($errors['password']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="password_confirm">Confirm new password</label>
                <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                <?php if (isset($errors['password_confirm'])): ?><span class="error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Change password</button>
            </div>
        </form>
    </div>
</div>
