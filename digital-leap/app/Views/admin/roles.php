<?php
/** @var array $roles */
/** @var array $permissions */
/** @var array $granted */
?>
<div class="page-head">
    <div>
        <h1>Role and permission matrix</h1>
        <p>Read-only view of the server-side grants enforced by the router. One role per account, many permissions per role.</p>
    </div>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table matrix-table">
            <thead>
                <tr>
                    <th scope="col">Permission</th>
                    <?php foreach ($roles as $role): ?>
                        <th scope="col" class="center"><?= e($role['role_name']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permissions as $permission): ?>
                    <tr>
                        <td>
                            <strong><?= e($permission['perm_key']) ?></strong>
                            <div class="small muted"><?= e($permission['description']) ?></div>
                        </td>
                        <?php foreach ($roles as $role): ?>
                            <td class="center">
                                <?php if (isset($granted[(int) $role['role_id']][(int) $permission['permission_id']])): ?>
                                    <span class="grant grant-yes" aria-label="Granted">&#10003;</span>
                                <?php else: ?>
                                    <span class="grant grant-no" aria-label="Not granted">&#183;</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
