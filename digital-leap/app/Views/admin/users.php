<?php
/** @var array $users */
/** @var array $roles */
/** @var int $roleId */
/** @var string $status */
/** @var string $locked */
/** @var string $search */
/** @var int $selfId */
$now = date('Y-m-d H:i:s');
?>
<div class="page-head">
    <div>
        <h1>User accounts</h1>
        <p>Create staff and student accounts, suspend access, clear lockouts and issue single-use links.</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="<?= e(url('admin/users/form')) ?>">Create account</a>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="filter-bar">
        <input type="hidden" name="r" value="admin/users">
        <div class="field">
            <label for="q">Search</label>
            <input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Name or email">
        </div>
        <div class="field">
            <label for="role_id">Role</label>
            <select id="role_id" name="role_id">
                <option value="0">All roles</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int) $role['role_id'] ?>"<?= selected($roleId, (int) $role['role_id']) ?>><?= e($role['role_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">Any status</option>
                <option value="active"<?= selected($status, 'active') ?>>Active</option>
                <option value="invited"<?= selected($status, 'invited') ?>>Invited</option>
                <option value="suspended"<?= selected($status, 'suspended') ?>>Suspended</option>
            </select>
        </div>
        <div class="field">
            <label for="locked">Lockout</label>
            <select id="locked" name="locked">
                <option value="">All accounts</option>
                <option value="1"<?= selected($locked, '1') ?>>Locked only</option>
            </select>
        </div>
        <div class="field field-actions">
            <button class="btn btn-outline" type="submit">Apply filters</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head"><div><h2>Directory</h2><p><?= count($users) ?> account(s) match the filters.</p></div></div>
    <div class="table-wrap">
        <table class="table table--stack">
            <thead>
                <tr><th>Account</th><th>Role</th><th>Organisation</th><th>Status</th><th>Security</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <?php
                $id = (int) $user['user_id'];
                $isLocked = $user['locked_until'] !== null && $user['locked_until'] > $now;
                $isSelf = $id === $selfId;
                ?>
                <tr>
                    <td data-label="Account">
                        <div class="cell-person">
                            <?= avatar_html($user, 'avatar avatar-sm') ?>
                            <div>
                                <strong><?= e($user['first_name'] . ' ' . $user['last_name']) ?><?= $isSelf ? ' (you)' : '' ?></strong>
                                <div class="small muted"><?= e($user['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td data-label="Role"><?= e($user['role_name']) ?></td>
                    <td data-label="Organisation"><?= $user['org_name'] !== null ? e($user['org_name']) : '<span class="muted">—</span>' ?></td>
                    <td data-label="Status">
                        <?= status_badge($user['status']) ?>
                        <?php if ($user['status'] === 'invited'): ?><div class="small muted"><?= (int) $user['open_tokens'] ?> open link(s)</div><?php endif; ?>
                    </td>
                    <td data-label="Security">
                        <?php if ($isLocked): ?>
                            <span class="badge badge-bad">Locked</span>
                            <div class="small muted">Until <?= e(fmt_dt($user['locked_until'])) ?></div>
                        <?php elseif ((int) $user['failed_attempts'] > 0): ?>
                            <span class="badge badge-warn"><?= (int) $user['failed_attempts'] ?> failed</span>
                        <?php else: ?>
                            <span class="small muted">Clear</span>
                        <?php endif; ?>
                        <?php if ($user['last_login_at'] !== null): ?>
                            <div class="small muted">Last seen <?= e(fmt_dt($user['last_login_at'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="right actions-cell">
                        <a class="btn btn-sm btn-quiet" href="<?= e(url('admin/users/form', ['id' => $id])) ?>">Edit</a>
                        <form class="inline-form" method="post" action="<?= e(url('admin/users/reset')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $id ?>">
                            <button class="btn btn-sm btn-outline" type="submit"><?= $user['status'] === 'invited' ? 'Resend invite' : 'Reset link' ?></button>
                        </form>
                        <?php if ($isLocked): ?>
                            <form class="inline-form" method="post" action="<?= e(url('admin/users/unlock')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= $id ?>">
                                <button class="btn btn-sm btn-outline" type="submit">Unlock</button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$isSelf && $user['status'] !== 'invited'): ?>
                            <form class="inline-form" method="post" action="<?= e(url('admin/users/toggle')) ?>" data-confirm="Change access for <?= e($user['first_name'] . ' ' . $user['last_name']) ?>?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= $id ?>">
                                <button class="btn btn-sm <?= $user['status'] === 'active' ? 'btn-danger' : 'btn' ?>" type="submit"><?= $user['status'] === 'active' ? 'Suspend' : 'Reactivate' ?></button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($users === []): ?>
                <tr><td colspan="6" class="empty">No accounts match those filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
