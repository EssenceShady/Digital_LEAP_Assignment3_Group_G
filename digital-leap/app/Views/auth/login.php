<?php /** @var string $email */ ?>
<div class="auth-card">
    <div class="card auth-card-inner">
        <div class="auth-card-head">
            <span class="auth-badge">Student and staff sign-in</span>
            <h1>Welcome back</h1>
            <p class="muted">Use the university email address issued by the internship office.</p>
        </div>

        <form method="post" action="<?= e(url('login')) ?>" novalidate data-login-form>
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" placeholder="name@leap.edu.sl" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" autocomplete="current-password" placeholder="Enter your password" required>
            </div>
            <div class="form-between form-between-end">
                <a class="link-quiet" href="<?= e(url('forgot')) ?>">Forgot password?</a>
            </div>
            <button class="btn btn-block" type="submit">Sign in to Digital LEAP</button>
        </form>

        <div class="auth-divider"><span>Explore a demo role</span></div>
        <p class="demo-hint">Choose a role to load its demo credentials, then press <strong>Sign in</strong>. This is seeded sample data, not a live account.</p>
        <div class="demo-grid">
            <?php
            $demos = [
                ['Student', 'mohamedalie247@gmail.com', 'Student@1234', 'Logbook, placement and results'],
                ['Supervisor', 'supervisor@brightfuture.sl', 'Super@1234', 'Review and evaluate'],
                ['Mentor', 'deborahjulietmoiforay@gmail.com', 'Mentor@1234', 'Students, visits and reports'],
                ['Coordinator', 'bayohmusa001@gmail.com', 'Musa@2026', 'Allocations, grades and visits'],
                ['Administrator', 'sesayshadrachsaidu@gmail.com', 'Shadrach@2026', 'Accounts, roles and audit'],
                ['Management', 'fatmatabfc@gmail.com', 'Fatmata@2026', 'Indicators and exports'],
            ];
            foreach ($demos as [$role, $mail, $pass, $note]): ?>
                <button type="button" class="demo-role" data-demo-email="<?= e($mail) ?>" data-demo-password="<?= e($pass) ?>" data-demo-label="<?= e($role) ?>">
                    <span class="demo-role-name"><?= e($role) ?></span>
                    <span class="demo-role-note"><?= e($note) ?></span>
                    <span class="demo-role-mail"><?= e($mail) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <p class="auth-switch">New student? <a href="<?= e(url('register')) ?>">Create an account</a></p>
    </div>
</div>
