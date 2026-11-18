<div class="auth-card">
    <div class="card">
        <h1>Reset your password</h1>
        <p class="muted">Enter the email address on your account. If it is registered, a single-use link valid for one hour will be queued.</p>
        <form method="post" action="<?= e(url('forgot')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" autocomplete="username" required autofocus>
            </div>
            <div class="form-actions">
                <button class="btn" type="submit">Send reset link</button>
                <a class="btn btn-quiet" href="<?= e(url('login')) ?>">Back to sign in</a>
            </div>
        </form>
        <p class="small muted">In this offline demonstration the message is written to the mail queue, which the administrator can open under Mail Queue.</p>
    </div>
</div>
