<?php
/** @var array $rows */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
/** @var string $action */
/** @var string $entity */
/** @var int $userId */
/** @var string $from */
/** @var string $to */
/** @var array $entities */
/** @var array $actions */
/** @var array $staff */
$query = array_filter([
    'action' => $action,
    'entity_type' => $entity,
    'user_id' => $userId > 0 ? $userId : null,
    'from' => $from,
    'to' => $to,
], static fn ($value): bool => $value !== null && $value !== '');
?>
<div class="page-head">
    <div>
        <h1>Audit log</h1>
        <p><?= (int) $total ?> recorded action(s). Append-only: entries are never edited or deleted.</p>
    </div>
</div>

<div class="card">
    <form method="get" action="<?= e(($GLOBALS['base'] ?? '') . '/index.php') ?>" class="filter-bar">
        <input type="hidden" name="r" value="admin/audit">
        <div class="field">
            <label for="action">Action</label>
            <input id="action" name="action" type="search" list="action-list" value="<?= e($action) ?>" placeholder="e.g. login.failed">
            <datalist id="action-list">
                <?php foreach ($actions as $row): ?><option value="<?= e($row['action']) ?>"><?php endforeach; ?>
            </datalist>
        </div>
        <div class="field">
            <label for="entity_type">Entity</label>
            <select id="entity_type" name="entity_type">
                <option value="">All entities</option>
                <?php foreach ($entities as $row): ?>
                    <option value="<?= e($row['entity_type']) ?>"<?= selected($entity, $row['entity_type']) ?>><?= e($row['entity_type']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="user_id">Actor</label>
            <select id="user_id" name="user_id">
                <option value="0">Anyone</option>
                <?php foreach ($staff as $person): ?>
                    <option value="<?= (int) $person['user_id'] ?>"<?= selected($userId, (int) $person['user_id']) ?>><?= e($person['first_name'] . ' ' . $person['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="from">From</label>
            <input id="from" name="from" type="date" value="<?= e($from) ?>">
        </div>
        <div class="field">
            <label for="to">To</label>
            <input id="to" name="to" type="date" value="<?= e($to) ?>">
        </div>
        <div class="field field-actions">
            <button class="btn btn-outline" type="submit">Apply filters</button>
            <a class="btn btn-quiet" href="<?= e(url('admin/audit')) ?>">Reset</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="table table--stack">
            <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Entity</th><th>Source</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td data-label="When"><?= e(fmt_dt($row['created_at'])) ?></td>
                    <td data-label="Actor"><?= $row['first_name'] !== null ? e($row['first_name'] . ' ' . $row['last_name']) : '<span class="muted">System</span>' ?></td>
                    <td data-label="Action"><strong><?= e($row['action']) ?></strong></td>
                    <td data-label="Entity"><?= $row['entity_type'] !== null ? e($row['entity_type']) . ($row['entity_id'] !== null ? ' #' . (int) $row['entity_id'] : '') : '—' ?></td>
                    <td data-label="Source"><span class="small muted"><?= e($row['ip_address']) ?></span></td>
                    <td class="right"><a class="btn btn-sm btn-quiet" href="<?= e(url('admin/audit/view', ['id' => (int) $row['audit_id']])) ?>">Inspect</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?>
                <tr><td colspan="6" class="empty">No audit entries match those filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Audit pages">
            <?php for ($p = 1; $p <= $pages; $p++): ?>
                <a class="chip<?= $p === $page ? ' chip-active' : '' ?>" href="<?= e(url('admin/audit', $query + ['page' => $p])) ?>"><?= $p ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</div>
