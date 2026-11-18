<?php
/** @var array $errors */
/** @var array $old */
$faculties = programme_faculties();
$chosenProgramme = (string) ($old['programme'] ?? '');
?>
<div class="auth-card">
    <div class="card auth-card-inner">
        <div class="auth-card-head">
            <span class="auth-badge">New student</span>
            <h1>Create your account</h1>
            <p class="muted">Register once, then submit host-organisation preferences for the internship office to review.</p>
        </div>

        <form method="post" action="<?= e(url('register')) ?>" novalidate>
            <?= csrf_field() ?>
            <p class="form-section-title">Your details</p>
            <div class="form-grid">
                <div class="field">
                    <label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" value="<?= e($old['first'] ?? '') ?>" required>
                    <?php if (isset($errors['first_name'])): ?><span class="error"><?= e($errors['first_name']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="last_name">Surname</label>
                    <input type="text" id="last_name" name="last_name" value="<?= e($old['last'] ?? '') ?>" required>
                    <?php if (isset($errors['last_name'])): ?><span class="error"><?= e($errors['last_name']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" autocomplete="email" required>
                    <?php if (isset($errors['email'])): ?><span class="error"><?= e($errors['email']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="phone">Phone number</label>
                    <input type="tel" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" placeholder="+232 76 000 000">
                </div>
                <div class="field">
                    <label for="reg_no">Registration number</label>
                    <input type="text" id="reg_no" name="reg_no" value="<?= e($old['regNo'] ?? '') ?>" required>
                    <?php if (isset($errors['reg_no'])): ?><span class="error"><?= e($errors['reg_no']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="programme">Programme</label>
                    <select id="programme" name="programme" required>
                        <option value="">Choose a programme</option>
                        <?php foreach ($faculties as $faculty => $programmes): ?>
                            <optgroup label="<?= e($faculty) ?>">
                                <?php foreach ($programmes as $p): ?>
                                    <option value="<?= e($p) ?>"<?= selected($chosenProgramme, $p) ?>><?= e($p) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Programmes are grouped by faculty. Choose the one on your student record.</span>
                    <?php if (isset($errors['programme'])): ?><span class="error"><?= e($errors['programme']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="level">Year of study</label>
                    <select id="level" name="level">
                        <?php foreach (['Year 1', 'Year 2', 'Year 3', 'Year 4'] as $l): ?>
                            <option value="<?= e($l) ?>"<?= selected($old['level'] ?? 'Year 3', $l) ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester">
                        <?php foreach (['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5', 'Semester 6', 'Semester 7', 'Semester 8'] as $s): ?>
                            <option value="<?= e($s) ?>"<?= selected($old['semester'] ?? 'Semester 5', $s) ?>><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <p class="form-section-title">Account security</p>
            <div class="form-grid">
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password" required>
                    <span class="hint">At least 8 characters with one uppercase letter, one lowercase letter and one number. Symbols are optional but make the password stronger.</span>
                    <?php if (isset($errors['password'])): ?><span class="error"><?= e($errors['password']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
                    <?php if (isset($errors['password_confirm'])): ?><span class="error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn" type="submit">Create account</button>
                <a class="btn btn-quiet" href="<?= e(url('login')) ?>">I already have an account</a>
            </div>
        </form>
    </div>
</div>
