<?php
/** @var array $orgs */
?>
<div class="page-head">
    <div>
        <h1>Host organisations</h1>
        <p>MoU status, capacity and current allocation load.</p>
    </div>
    <div class="page-actions"><a class="btn" href="<?= e(url('coordinator/organisations/form')) ?>">Add organisation</a></div>
</div>

<div class="card">
    <div class="card-head"><div><h2>Register</h2><p><?= count($orgs) ?> organisations.</p></div></div>
    <?php if ($orgs === []): ?>
        <div class="empty"><strong>No organisations</strong>Add the first host organisation to begin allocating placements.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table table--stack">
                <thead><tr><th>Organisation</th><th>District</th><th>MoU</th><th>Capacity</th><th>Active</th><th>Contact</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($orgs as $org): ?>
                    <tr>
                        <td data-label="Organisation"><strong><?= e($org['name']) ?></strong><div class="small muted"><?= e($org['sector']) ?></div></td>
                        <td data-label="District"><?= e($org['district']) ?></td>
                        <td data-label="MoU"><?= status_badge($org['mou_status']) ?><div class="small muted"><?= e(fmt_date($org['mou_expiry'])) ?></div></td>
                        <td data-label="Capacity"><?= (int) $org['capacity'] ?></td>
                        <td data-label="Active"><?= (int) $org['active_placements'] ?> / <?= (int) $org['capacity'] ?></td>
                        <td data-label="Contact"><?= e($org['contact_person']) ?><div class="small muted"><?= e($org['contact_email']) ?></div></td>
                        <td class="right"><a class="btn btn-sm btn-quiet" href="<?= e(url('coordinator/organisations/form', ['id' => (int) $org['org_id']])) ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
