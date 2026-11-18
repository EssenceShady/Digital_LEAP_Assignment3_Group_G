<?php /** @var string $token */ ?>
<?php /** @var array $user */ ?>
<?php /** @var array $errors */ ?>
<div class="auth-card">
    <div class="card">
        <p class="chip">Invitation</p>
        <h1>Activate your supervisor account</h1>
        <p class="muted">Hello <?= e($user['first_name'] . ' ' . $user['last_name']) ?>. Set a password for
            <strong><?= e($user['email']) ?></strong> to begin reviewing logbooks.</p>
        <form method="post" action="<?= e(url('token/supervisor')) ?>" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="new-password" required autofocus>
                <span class="hint">At least 8 characters with one uppercase letter, one lowercase letter and one number. Symbols are optional but make the password stronger.</span>
                <?php if (isset($errors['password'])): ?><span class="error"><?= e($errors['password']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                <?php if (isset($errors['password_confirm'])): ?><span class="error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Activate account</button>
            </div>
        </form>
    </div>
</div>
