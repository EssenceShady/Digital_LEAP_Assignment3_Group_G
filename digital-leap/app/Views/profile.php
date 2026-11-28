<?php
/** @var array $user */
/** @var array|null $student */
/** @var array|null $cv */
/** @var bool $mustChangePassword */
$avatarUrl = avatar_url($user);
$fullName = trim((string) $user['first_name'] . ' ' . (string) $user['last_name']);
$initialTab = !empty($mustChangePassword) ? 'security' : 'account';
?>
<div class="account-page-head">
    <div>
        <p class="eyebrow">My account</p>
        <h1>Account and security</h1>
        <p>Your identity, contact details and password in one place. Changes here apply only to your account.</p>
    </div>
    <span class="account-status-line"><?= status_badge($user['status']) ?><span>Member account</span></span>
</div>

<section class="account-identity card">
    <form method="post" action="<?= e(url('profile/avatar')) ?>" enctype="multipart/form-data" class="account-photo-form" data-avatar-form>
        <?= csrf_field() ?>
        <label class="account-avatar" for="avatar-input" aria-label="Choose a new profile photo">
            <?php if ($avatarUrl !== null): ?>
                <img src="<?= e($avatarUrl) ?>" alt="Your profile photo" data-avatar-current>
            <?php else: ?>
                <span aria-hidden="true" data-avatar-initials><?= e(initials((string) $user['first_name'], (string) $user['last_name'])) ?></span>
            <?php endif; ?>
            <span class="account-avatar-veil">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h3l2-3h6l2 3h3v12H4Z"/><circle cx="12" cy="13" r="3.2"/></svg>
                <span>Edit</span>
            </span>
        </label>
        <input class="sr-only" type="file" id="avatar-input" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-avatar-input>
        <div class="account-photo-copy">
            <strong data-avatar-title><?= $avatarUrl !== null ? 'Profile photo' : 'Add a profile photo' ?></strong>
            <span data-avatar-hint role="status" aria-live="polite"><?= $avatarUrl !== null ? 'This photo appears beside your name across Digital LEAP.' : 'A clear photo helps colleagues recognise you.' ?></span>
            <div class="account-photo-actions">
                <label class="btn btn-outline btn-sm" for="avatar-input">Choose photo</label>
                <button class="btn btn-sm" type="submit" data-avatar-save disabled>Save photo</button>
                <?php if ($avatarUrl !== null): ?>
                    <button class="btn btn-sm btn-quiet" type="submit" formaction="<?= e(url('profile/avatar/remove')) ?>" formnovalidate data-confirm="Remove your profile photo and show your initials again?">Remove</button>
                <?php endif; ?>
            </div>
            <span class="hint">JPG, PNG or WebP, up to 8 MB. You can zoom and reposition the photo before saving.</span>
        </div>
    </form>

    <div class="account-identity-copy">
        <p class="eyebrow">Account holder</p>
        <h2><?= e($fullName) ?></h2>
        <p class="account-email"><?= e($user['email']) ?></p>
        <div class="profile-chips">
            <span class="badge badge-muted"><?= e($user['role_name']) ?></span>
            <?= status_badge($user['status']) ?>
            <?php if ($student !== null): ?>
                <span class="badge badge-info"><?= e($student['programme']) ?></span>
                <span class="badge badge-muted"><?= e(year_label($student['level']) . ' · ' . $student['semester']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</section>

<div class="account-shell" data-account-tabs>
    <div class="account-tabbar" role="tablist" aria-label="Account sections">
        <button class="account-tab<?= $initialTab === 'account' ? ' is-active' : '' ?>" id="account-tab-account" type="button" role="tab" aria-selected="<?= $initialTab === 'account' ? 'true' : 'false' ?>" aria-controls="account-panel-account" tabindex="<?= $initialTab === 'account' ? '0' : '-1' ?>" data-account-tab="account">Account</button>
        <button class="account-tab<?= $initialTab === 'security' ? ' is-active' : '' ?>" id="account-tab-security" type="button" role="tab" aria-selected="<?= $initialTab === 'security' ? 'true' : 'false' ?>" aria-controls="account-panel-security" tabindex="<?= $initialTab === 'security' ? '0' : '-1' ?>" data-account-tab="security">Security</button>
    </div>

    <section class="account-panel" id="account-panel-account" role="tabpanel" aria-labelledby="account-tab-account" data-account-panel="account"<?= $initialTab === 'account' ? '' : ' hidden' ?>>
        <div class="account-grid">
            <section class="card account-details-card" id="account-details">
                <div class="card-head">
                    <div>
                        <h2>Your details</h2>
                        <p>Keep the name and phone number on your account current. Your email identifies the account and is managed by the internship office.</p>
                    </div>
                </div>
                <form method="post" action="<?= e(url('profile')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="profile_action" value="details">
                    <div class="form-grid">
                        <div class="field">
                            <label for="first_name">First name</label>
                            <input type="text" id="first_name" name="first_name" value="<?= e($user['first_name']) ?>" autocomplete="given-name" required>
                        </div>
                        <div class="field">
                            <label for="last_name">Surname</label>
                            <input type="text" id="last_name" name="last_name" value="<?= e($user['last_name']) ?>" autocomplete="family-name" required>
                        </div>
                        <div class="field">
                            <label for="email">Email address</label>
                            <input type="email" id="email" value="<?= e($user['email']) ?>" disabled>
                            <span class="hint">Contact the administrator if this needs to change.</span>
                        </div>
                        <div class="field">
                            <label for="phone">Phone number</label>
                            <input type="tel" id="phone" name="phone" value="<?= e($user['phone'] ?? '') ?>" autocomplete="tel">
                            <span class="hint">Optional. Used only for internship coordination.</span>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn" type="submit">Save details</button>
                        <a class="btn btn-quiet" href="<?= e(url('settings')) ?>">Notification and display settings</a>
                    </div>
                </form>
            </section>

            <aside class="account-side">
                <section class="card">
                    <div class="card-head"><div><h2>Account summary</h2><p>The record behind your sign-in.</p></div></div>
                    <div class="stat-list account-stat-list">
                        <div class="row"><span>Role</span><strong><?= e($user['role_name']) ?></strong></div>
                        <div class="row"><span>Status</span><strong><?= status_badge($user['status']) ?></strong></div>
                        <div class="row"><span>Last sign-in</span><strong><?= e(fmt_dt($user['last_login_at'])) ?></strong></div>
                        <?php if ($user['organisation_id']): ?>
                            <div class="row"><span>Organisation</span><strong><?= e($user['org_name']) ?></strong></div>
                        <?php endif; ?>
                        <?php if ($student !== null): ?>
                            <div class="row"><span>Registration</span><strong><?= e($student['reg_no']) ?></strong></div>
                            <div class="row"><span>Programme</span><strong><?= e($student['programme']) ?></strong></div>
                            <div class="row"><span>Study year</span><strong><?= e(year_label($student['level']) . ' · ' . $student['semester']) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($student !== null): ?>
                    <section class="card">
                        <div class="card-head"><div><h2>Curriculum vitae</h2><p>The newest file is the one reviewers open.</p></div></div>
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
                                        <a class="btn btn-sm" href="<?= e(url('download', ['id' => (int) $cv['doc_id'], 'inline' => 1])) ?>" target="_blank" rel="noopener">Open</a>
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
                                    <span class="hint">You will see the file name, size and upload time after it is saved.</span>
                                </div>
                                <div class="form-actions">
                                    <button class="btn btn-outline" type="submit"><?= $cv === null ? 'Upload CV' : 'Upload replacement' ?></button>
                                </div>
                            </form>
                        </details>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
    </section>

    <section class="account-panel" id="account-panel-security" role="tabpanel" aria-labelledby="account-tab-security" data-account-panel="security"<?= $initialTab === 'security' ? '' : ' hidden' ?>>
        <div class="account-grid">
            <section class="card">
                <div class="card-head">
                    <div>
                        <h2>Password</h2>
                        <p>Choose a password you do not use on another site. Your current password is required before a change is saved.</p>
                    </div>
                    <span class="security-mark" aria-hidden="true">
                        <svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    </span>
                </div>
                <form method="post" action="<?= e(url('profile')) ?>" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="profile_action" value="password">
                    <?php if ($mustChangePassword): ?>
                    <p class="security-force-note">Your account was issued a temporary password. Choose a new one now to continue.</p>
                <?php endif; ?>
                <div class="form-grid security-fields">
                        <div class="field full">
                            <label for="current_password">Current password</label>
                            <input type="password" id="current_password" name="current_password" autocomplete="current-password">
                        </div>
                        <div class="field">
                            <label for="new_password">New password</label>
                            <input type="password" id="new_password" name="new_password" autocomplete="new-password" data-no-meter>
                        </div>
                        <div class="field">
                            <label for="new_password_confirm">Confirm new password</label>
                            <input type="password" id="new_password_confirm" name="new_password_confirm" autocomplete="new-password">
                        </div>
                    </div>
                    <ul class="security-policy" aria-label="Password requirements">
                        <li>At least 8 characters</li>
                        <li>One uppercase letter</li>
                        <li>One lowercase letter</li>
                        <li>One number</li>
                    </ul>
                    <div class="form-actions">
                        <button class="btn" type="submit">Update password</button>
                    </div>
                </form>
            </section>

            <aside class="account-side">
                <section class="card">
                    <div class="card-head"><div><h2>Account protection</h2><p>How this sign-in is currently protected.</p></div></div>
                    <div class="stat-list account-stat-list">
                        <div class="row"><span>Account status</span><strong><?= status_badge($user['status']) ?></strong></div>
                        <div class="row"><span>Last sign-in</span><strong><?= e(fmt_dt($user['last_login_at'])) ?></strong></div>
                        <div class="row account-row-stack"><span>Email</span><strong><?= e($user['email']) ?></strong></div>
                        <div class="row"><span>Access level</span><strong><?= e($user['role_name']) ?></strong></div>
                    </div>
                </section>
                <section class="card security-notes">
                    <div class="card-head"><div><h2>Stay secure</h2></div></div>
                    <ul class="security-note-list">
                        <li><span aria-hidden="true">01</span><p>Signing out also clears pages cached on this device for offline use.</p></li>
                        <li><span aria-hidden="true">02</span><p>Every form in the application is checked before it can change a record.</p></li>
                        <li><span aria-hidden="true">03</span><p>Only you and the administrator can reset this account password.</p></li>
                    </ul>
                </section>
            </aside>
        </div>
    </section>
</div>

<dialog class="photo-dialog" data-avatar-dialog aria-labelledby="photo-dialog-title">
    <div class="photo-dialog-card">
        <div class="photo-dialog-head">
            <div>
                <p class="eyebrow">Profile photo</p>
                <h2 id="photo-dialog-title">Adjust your photo</h2>
                <p>Drag to reposition. Use the slider to zoom. The circle shows how the photo will appear.</p>
            </div>
            <button class="photo-dialog-close" type="button" aria-label="Close photo editor" data-photo-cancel>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12"/><path d="M18 6 6 18"/></svg>
            </button>
        </div>
        <div class="photo-dialog-stage">
            <canvas width="640" height="640" tabindex="0" aria-label="Photo crop area. Drag to reposition or use the arrow keys." data-photo-canvas></canvas>
        </div>
        <div class="photo-zoom-row">
            <button type="button" class="photo-zoom-button" aria-label="Zoom out" data-photo-zoom-out>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
            </button>
            <label class="sr-only" for="photo-zoom">Photo zoom</label>
            <input id="photo-zoom" type="range" min="1" max="3" step="0.01" value="1" data-photo-zoom>
            <button type="button" class="photo-zoom-button" aria-label="Zoom in" data-photo-zoom-in>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            </button>
        </div>
        <p class="photo-dialog-error" data-photo-error hidden></p>
        <div class="photo-dialog-actions">
            <button class="btn btn-quiet" type="button" data-photo-cancel>Cancel</button>
            <button class="btn" type="button" data-photo-apply>Use this photo</button>
        </div>
    </div>
</dialog>