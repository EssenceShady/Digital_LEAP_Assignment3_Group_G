<?php
/** @var array|null $user */
/** @var array|null $student */
/** @var array $roles */
/** @var array $orgs */
$isSelf = $user !== null && (int) $user['user_id'] === (int) \Leap\Core\Auth::id();
$currentRole = $user === null ? 0 : (int) $user['role_id'];
?>
<div class="page-head">
    <div>
        <h1><?= $user ? 'Edit account' : 'Create account' ?></h1>
        <p><?= $user ? 'Update the directory record. Access changes are audited.' : 'Host supervisors are invited by email; every other role receives an initial password.' ?></p>
    </div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('admin/users')) ?>">Back to accounts</a></div>
</div>

<?php if ($user !== null): ?>
    <div class="card">
        <div class="detail-grid">
            <div><span class="detail-label">Status</span><div><?= status_badge($user['status']) ?></div></div>
            <div><span class="detail-label">Created</span><div><?= e(fmt_dt($user['created_at'])) ?></div></div>
            <div><span class="detail-label">Last sign-in</span><div><?= e(fmt_dt($user['last_login_at'])) ?></div></div>
            <div><span class="detail-label">Failed attempts</span><div><?= (int) $user['failed_attempts'] ?></div></div>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <form method="post" action="<?= e(url('admin/users/save')) ?>">
        <?= csrf_field() ?>
        <?php if ($user !== null): ?><input type="hidden" name="user_id" value="<?= (int) $user['user_id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="field">
                <label for="first_name">First name</label>
                <input id="first_name" name="first_name" type="text" maxlength="60" value="<?= e($user['first_name'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label for="last_name">Surname</label>
                <input id="last_name" name="last_name" type="text" maxlength="60" value="<?= e($user['last_name'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" maxlength="120" value="<?= e($user['email'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label for="phone">Phone</label>
                <input id="phone" name="phone" type="tel" maxlength="25" value="<?= e($user['phone'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="role_id">Role</label>
                <?php if ($isSelf): ?>
                    <input type="hidden" name="role_id" value="<?= $currentRole ?>">
                    <input type="text" value="<?= e((string) \Leap\Core\Database::val('SELECT role_name FROM roles WHERE role_id = ?', [$currentRole])) ?>" disabled>
                    <span class="hint">Your own role cannot be changed.</span>
                <?php else: ?>
                    <select id="role_id" name="role_id" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= (int) $role['role_id'] ?>"<?= selected($currentRole, (int) $role['role_id']) ?>><?= e($role['role_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="field">
                <label for="organisation_id">Host organisation</label>
                <select id="organisation_id" name="organisation_id">
                    <option value="0">None</option>
                    <?php foreach ($orgs as $org): ?>
                        <option value="<?= (int) $org['org_id'] ?>"<?= selected($user['organisation_id'] ?? 0, (int) $org['org_id']) ?>><?= e($org['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="hint">Required for host supervisors; optional for mentors.</span>
            </div>
            <?php if ($user === null): ?>
                <div class="field">
                    <label for="password">Initial password</label>
                    <input id="password" name="password" type="password" minlength="8" autocomplete="new-password">
                    <span class="hint">At least 8 characters with an uppercase letter, a lowercase letter and a number. Leave blank for a host supervisor to receive a single-use activation link instead.</span>
                </div>
            <?php endif; ?>
        </div>

        <h2 class="section-title">Student record</h2>
        <p class="section-note">Complete these fields only when the account role is Student. Existing student records are kept in step here.</p>
        <div class="form-grid">
            <div class="field">
                <label for="reg_no">Registration number</label>
                <input id="reg_no" name="reg_no" type="text" maxlength="20" value="<?= e($student['reg_no'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="programme">Programme</label>
                <?php
                $faculties = programme_faculties();
                $allProgrammes = array_merge(...array_values($faculties));
                $currentProgramme = (string) ($student['programme'] ?? '');
                ?>
                <select id="programme" name="programme">
                    <option value="">Not a student</option>
                    <?php foreach ($faculties as $faculty => $programmes): ?>
                        <optgroup label="<?= e($faculty) ?>">
                            <?php foreach ($programmes as $programme): ?>
                                <option value="<?= e($programme) ?>"<?= selected($currentProgramme, $programme) ?>><?= e($programme) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                    <?php if ($currentProgramme !== '' && !in_array($currentProgramme, $allProgrammes, true)): ?>
                        <option value="<?= e($currentProgramme) ?>" selected><?= e($currentProgramme) ?> (current record)</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="field">
                <label for="level">Year of study</label>
                <?php $currentYear = (string) ($student['level'] ?? 'Year 3'); ?>
                <select id="level" name="level">
                    <?php foreach (['Year 1', 'Year 2', 'Year 3', 'Year 4'] as $year): ?>
                        <option value="<?= e($year) ?>"<?= selected($currentYear, $year) ?>><?= e($year) ?></option>
                    <?php endforeach; ?>
                    <?php if ($currentYear !== '' && !in_array($currentYear, ['Year 1', 'Year 2', 'Year 3', 'Year 4'], true)): ?>
                        <option value="<?= e($currentYear) ?>" selected><?= e($currentYear) ?></option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="field">
                <label for="semester">Semester</label>
                <?php
                $currentSemester = (string) ($student['semester'] ?? 'Semester 5');
                $semesters = ['Semester 1', 'Semester 2', 'Semester 3', 'Semester 4', 'Semester 5', 'Semester 6', 'Semester 7', 'Semester 8'];
                ?>
                <select id="semester" name="semester">
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= e($sem) ?>"<?= selected($currentSemester, $sem) ?>><?= e($sem) ?></option>
                    <?php endforeach; ?>
                    <?php if ($currentSemester !== '' && !in_array($currentSemester, $semesters, true)): ?>
                        <option value="<?= e($currentSemester) ?>" selected><?= e($currentSemester) ?></option>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-actions">
            <button class="btn" type="submit"><?= $user ? 'Save changes' : 'Create account' ?></button>
            <a class="btn btn-quiet" href="<?= e(url('admin/users')) ?>">Cancel</a>
        </div>
    </form>
</div>
