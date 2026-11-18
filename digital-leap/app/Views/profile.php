<?php
/** @var array $user */
/** @var array|null $student */
/** @var array|null $cv */
$avatarUrl = avatar_url($user);
?>
<section class="profile-hero card">
    <div class="profile-photo">
        <label class="profile-avatar-large" for="avatar-input" title="Click to choose a new profile photo">
            <?php if ($avatarUrl !== null): ?>
                <img src="<?= e($avatarUrl) ?>" alt="Your profile photo" data-avatar-current>
            <?php else: ?>
                <span aria-hidden="true" data-avatar-initials><?= e(initials((string) $user['first_name'], (string) $user['last_name'])) ?></span>
            <?php endif; ?>
            <span class="profile-avatar-hint"><?= $avatarUrl === null ? 'Add photo' : 'Change photo' ?></span>
        </label>
        <span class="profile-photo-state"><?= $avatarUrl !== null ? 'Profile photo on file' : 'Currently showing your initials' ?></span>
    </div>
    <div class="profile-hero-copy">
        <p class="eyebrow">My profile</p>
        <h1><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h1>
        <p class="muted"><?= e($user['email']) ?></p>
        <div class="profile-chips">
            <span class="badge badge-muted"><?= e($user['role_name']) ?></span>
            <?= status_badge($user['status']) ?>
            <?php if ($student !== null): ?>
                <span class="badge badge-info"><?= e($student['programme']) ?></span>
                <span class="badge badge-muted"><?= e(year_label($student['level']) . ' · ' . $student['semester']) ?></span>
            <?php endif; ?>
        </div>
        <form method="post" action="<?= e(url('profile/avatar')) ?>" enctype="multipart/form-data" class="avatar-form" data-avatar-form>
            <?= csrf_field() ?>
            <img class="avatar-preview" data-avatar-preview alt="Preview of the photo you selected">
            <label class="btn btn-outline btn-sm file-btn">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                <?= $avatarUrl === null ? 'Choose photo' : 'Choose a new photo' ?>
                <input type="file" id="avatar-input" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required data-avatar-input>
            </label>
            <button class="btn btn-sm" type="submit" data-avatar-save>Save photo</button>
            <?php if ($avatarUrl !== null): ?>
                <button class="btn btn-sm btn-quiet" type="submit" formaction="<?= e(url('profile/avatar/remove')) ?>" formnovalidate data-confirm="Remove your profile photo and show your initials again?">Remove photo</button>
            <?php endif; ?>
            <span class="hint" data-avatar-hint>JPG, PNG or WebP, up to 8 MB. The photo you pick is shown here, in the top bar and beside your name everywhere.</span>
        </form>
    </div>
</section>

<div class="grid grid-2">
    <section class="card">
        <div class="card-head"><div><h2>Account details</h2><p class="muted small">Keep your contact details current. Your email address is fixed because it identifies your account.</p></div></div>
        <form method="post" action="<?= e(url('profile')) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="field">
                    <label for="first_name">First name</label>
                    <input type="text" id="first_name" name="first_name" value="<?= e($user['first_name']) ?>" required>
                </div>
                <div class="field">
                    <label for="last_name">Surname</label>
                    <input type="text" id="last_name" name="last_name" value="<?= e($user['last_name']) ?>" required>
                </div>
                <div class="field">
                    <label for="email">Email address</label>
                    <input type="email" id="email" value="<?= e($user['email']) ?>" disabled>
                    <span class="hint">Contact the administrator to change this.</span>
                </div>
                <div class="field">
                    <label for="phone">Phone number</label>
                    <input type="tel" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>">
                </div>
            </div>

            <h3 class="small">Change password</h3>
            <div class="form-grid">
                <div class="field full">
                    <label for="current_password">Current password</label>
                    <input type="password" id="current_password" name="current_password" autocomplete="current-password">
                </div>
                <div class="field">
                    <label for="new_password">New password</label>
                    <input type="password" id="new_password" name="new_password" autocomplete="new-password">
                    <span class="hint">Use a password you do not use on another site.</span>
                </div>
                <div class="field">
                    <label for="new_password_confirm">Confirm new password</label>
                    <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password">
                </div>
            </div>

            <div class="form-actions">
                <button class="btn" type="submit">Save changes</button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-head"><div><h2>Account summary</h2></div></div>
        <div class="stat-list">
            <div class="row"><span>Role</span><strong><?= e($user['role_name']) ?></strong></div>
            <div class="row"><span>Status</span><strong><?= status_badge($user['status']) ?></strong></div>
            <div class="row"><span>Last sign-in</span><strong><?= e(fmt_dt($user['last_login_at'])) ?></strong></div>
            <?php if ($user['organisation_id']): ?>
                <div class="row"><span>Employer</span><strong><?= e($user['org_name']) ?></strong></div>
            <?php endif; ?>
            <?php if ($student !== null): ?>
                <div class="row"><span>Registration number</span><strong><?= e($student['reg_no']) ?></strong></div>
                <div class="row"><span>Programme</span><strong><?= e($student['programme']) ?></strong></div>
                <div class="row"><span>Year</span><strong><?= e(year_label($student['level']) . ' · ' . $student['semester']) ?></strong></div>
            <?php endif; ?>
        </div>

        <?php if ($student !== null): ?>
            <h3 class="small">Curriculum vitae</h3>
            <?php if ($cv !== null): ?>
                <?php
                $cvIsImage = in_array((string) $cv['mime_type'], ['image/jpeg', 'image/png'], true);
                $cvCanPreview = $cvIsImage || (string) $cv['mime_type'] === 'application/pdf';
                $cvSize = (int) $cv['file_size'] >= 1048576
                    ? number_format((int) $cv['file_size'] / 1048576, 1) . ' MB'
                    : max(1, (int) round((int) $cv['file_size'] / 1024)) . ' KB';
                ?>
                <div class="receipt">
                    <span class="receipt-icon" aria-hidden="true">CV</span>
                    <div class="receipt-body">
                        <strong><?= e($cv['original_name']) ?></strong>
                        <span class="receipt-meta"><?= e($cvSize) ?> · Uploaded <?= e(fmt_dt($cv['uploaded_at'])) ?></span>
                        <span class="receipt-state"><?= review_label($cv['reviewed_at'] ?? null, $cv['reviewer_first'] ?? null, $cv['reviewer_last'] ?? null) ?></span>
                    </div>
                    <div class="receipt-actions">
                        <?php if ($cvCanPreview): ?>
                            <a class="btn btn-sm" href="<?= e(url('download', ['id' => (int) $cv['doc_id'], 'inline' => 1])) ?>" target="_blank" rel="noopener">Open preview</a>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline" href="<?= e(url('download', ['id' => (int) $cv['doc_id']])) ?>">Download</a>
                    </div>
                </div>
                <?php if ($cvIsImage): ?>
                    <a class="cv-preview" href="<?= e(url('download', ['id' => (int) $cv['doc_id'], 'inline' => 1])) ?>" target="_blank" rel="noopener">
                        <img src="<?= e(url('download', ['id' => (int) $cv['doc_id'], 'inline' => 1])) ?>" alt="Preview of your uploaded CV, <?= e($cv['original_name']) ?>">
                        <span>Open full size</span>
                    </a>
                <?php endif; ?>
            <?php else: ?>
                <p class="small muted">No CV uploaded yet. The internship office needs one to review your application.</p>
            <?php endif; ?>
            <details class="disclosure"<?= $cv === null ? ' open' : '' ?>>
                <summary><?= $cv === null ? 'Upload a CV' : 'Replace this CV' ?></summary>
                <form method="post" action="<?= e(url('profile/cv')) ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="cv">CV file (PDF, Word, JPG or PNG, up to 5 MB)</label>
                        <input type="file" id="cv" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
                        <span class="hint">You will see the file name, size and upload time after it is saved. The newest file is the one reviewers open.</span>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-outline" type="submit"><?= $cv === null ? 'Upload CV' : 'Upload replacement' ?></button>
                    </div>
                </form>
            </details>
        <?php endif; ?>
    </section>
</div>
