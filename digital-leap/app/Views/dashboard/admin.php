<?php
/** @var int $activeUsers */
/** @var int $locked */
/** @var int $queued */
/** @var int $failed */
/** @var int $today */
/** @var array $recent */
?>
<div class="page-head">
    <div>
        <h1>Administration</h1>
        <p>Accounts, access control, system settings and the audit trail.</p>
    </div>
    <div class="page-actions">
        <a class="btn" href="<?= e(url('admin/users/form')) ?>">Create account</a>
        <a class="btn btn-outline" href="<?= e(url('admin/audit')) ?>">Audit log</a>
    </div>
</div>

<div class="grid grid-4">
    <a class="card kpi kpi-link" href="<?= e(url('admin/users')) ?>" data-tour="kpis">
        <span class="kpi-label">Active accounts</span>
        <span class="kpi-value"><?= $activeUsers ?></span>
        <span class="kpi-note">All six roles</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('admin/users', ['status' => 'suspended'])) ?>">
        <span class="kpi-label">Locked accounts</span>
        <span class="kpi-value"><?= $locked ?></span>
        <span class="kpi-note">Cleared automatically after the lockout window</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('admin/mail')) ?>">
        <span class="kpi-label">Mail queued</span>
        <span class="kpi-value"><?= $queued ?></span>
        <span class="kpi-note"><?= $failed ?> failed after three attempts</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
    <a class="card kpi kpi-link" href="<?= e(url('admin/audit')) ?>">
        <span class="kpi-label">Audit events today</span>
        <span class="kpi-value"><?= $today ?></span>
        <span class="kpi-note">Every privileged action is recorded</span>
        <span class="kpi-arrow" aria-hidden="true">&rarr;</span>
    </a>
</div>

<section class="card">
    <div class="card-head">
        <div><h2>Recent activity</h2><p>The last eight recorded actions, newest first.</p></div>
        <a class="btn btn-quiet btn-sm" href="<?= e(url('admin/audit')) ?>">Open full log</a>
    </div>
    <?php if ($recent === []): ?>
        <div class="empty"><strong>No activity recorded yet</strong>Actions appear here as users work in the system.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td data-label="When"><?= e(fmt_dt($row['created_at'])) ?></td>
                        <td data-label="User"><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?: 'System' ?></td>
                        <td data-label="Action"><span class="mono small"><?= e($row['action']) ?></span></td>
                        <td data-label="Entity"><?= e($row['entity_type'] ?? '—') ?><?= $row['entity_id'] ? ' #' . (int) $row['entity_id'] : '' ?></td>
                        <td data-label="IP"><?= e($row['ip_address']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
