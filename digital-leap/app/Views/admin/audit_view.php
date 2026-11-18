<?php
/** @var array $row */
/** @var array $before */
/** @var array $after */
/** @var array $changes */
$format = static function ($value): string {
    if (is_array($value) || is_object($value)) {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($value === null || $value === '') {
        return '—';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    return (string) $value;
};
?>
<div class="page-head">
    <div>
        <h1>Audit entry <?= (int) $row['audit_id'] ?></h1>
        <p><?= e($row['action']) ?> · <?= e(fmt_dt($row['created_at'])) ?></p>
    </div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('admin/audit', ['entity_type' => $row['entity_type'] ?? ''])) ?>">Back to log</a></div>
</div>

<div class="card">
    <div class="detail-grid">
        <div><span class="detail-label">Actor</span><div><?= $row['first_name'] !== null ? e($row['first_name'] . ' ' . $row['last_name']) : 'System' ?></div></div>
        <div><span class="detail-label">Entity</span><div><?= $row['entity_type'] !== null ? e($row['entity_type']) . ($row['entity_id'] !== null ? ' #' . (int) $row['entity_id'] : '') : '—' ?></div></div>
        <div><span class="detail-label">IP address</span><div><?= e($row['ip_address']) ?></div></div>
        <div><span class="detail-label">Recorded</span><div><?= e(fmt_dt($row['created_at'])) ?></div></div>
        <div class="detail-full"><span class="detail-label">User agent</span><div class="small"><?= e($row['user_agent'] ?? '—') ?></div></div>
    </div>
</div>

<div class="card">
    <div class="card-head"><div><h2>Field changes</h2><p>Values that differ between the recorded before and after snapshots.</p></div></div>
    <?php if ($changes === []): ?>
        <p class="empty">This entry carries no field-level diff. Open the snapshots below for the full payload.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--fit">
                <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
                <tbody>
                <?php foreach ($changes as $change): ?>
                    <tr>
                        <td><strong><?= e($change['key']) ?></strong></td>
                        <td><span class="diff diff-old"><?= e($format($change['before'])) ?></span></td>
                        <td><span class="diff diff-new"><?= e($format($change['after'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><div><h2>Before</h2></div></div>
        <pre class="code-block"><?= e($before === [] ? 'No snapshot recorded.' : json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
    </div>
    <div class="card">
        <div class="card-head"><div><h2>After</h2></div></div>
        <pre class="code-block"><?= e($after === [] ? 'No snapshot recorded.' : json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
    </div>
</div>
