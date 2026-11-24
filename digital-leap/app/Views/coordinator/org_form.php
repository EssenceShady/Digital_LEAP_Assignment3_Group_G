<?php
/** @var array|null $org */
/** @var array{lat: float, lng: float}|null $geo */
$districts = ['Western Area Urban', 'Western Area Rural', 'Bo', 'Kenema', 'Kono', 'Bombali', 'Port Loko', 'Tonkolili'];
?>
<div class="page-head">
    <div>
        <h1><?= $org ? 'Edit organisation' : 'Add organisation' ?></h1>
        <p>Record the MoU position and the number of students the host can absorb.</p>
    </div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('coordinator/organisations')) ?>">Back to register</a></div>
</div>

<div class="card">
    <form method="post" action="<?= e(url('coordinator/organisations/save')) ?>">
        <?= csrf_field() ?>
        <?php if ($org): ?><input type="hidden" name="org_id" value="<?= (int) $org['org_id'] ?>"><?php endif; ?>
        <div class="form-grid">
            <div class="field full">
                <label for="name">Organisation name</label>
                <input id="name" name="name" type="text" maxlength="120" value="<?= e($org['name'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label for="sector">Sector</label>
                <input id="sector" name="sector" type="text" maxlength="80" value="<?= e($org['sector'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label for="district">District</label>
                <select id="district" name="district">
                    <?php foreach ($districts as $district): ?>
                        <option value="<?= e($district) ?>"<?= selected($org['district'] ?? 'Western Area Urban', $district) ?>><?= e($district) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field full">
                <label for="address">Address</label>
                <input id="address" name="address" type="text" maxlength="180" value="<?= e($org['address'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="contact_person">Contact person</label>
                <input id="contact_person" name="contact_person" type="text" maxlength="120" value="<?= e($org['contact_person'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label for="contact_email">Contact email</label>
                <input id="contact_email" name="contact_email" type="email" maxlength="120" value="<?= e($org['contact_email'] ?? '') ?>" required>
            </div>
            <div class="field">
                <label for="contact_phone">Contact phone</label>
                <input id="contact_phone" name="contact_phone" type="tel" maxlength="30" value="<?= e($org['contact_phone'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="capacity">Student capacity</label>
                <input id="capacity" name="capacity" type="number" min="1" max="200" value="<?= e($org['capacity'] ?? 3) ?>" required>
                <span class="hint">Maximum concurrent placements at this organisation.</span>
            </div>
            <div class="field">
                <label for="mou_status">MoU status</label>
                <select id="mou_status" name="mou_status">
                    <option value="pending"<?= selected($org['mou_status'] ?? 'pending', 'pending') ?>>Pending</option>
                    <option value="signed"<?= selected($org['mou_status'] ?? '', 'signed') ?>>Signed</option>
                    <option value="expired"<?= selected($org['mou_status'] ?? '', 'expired') ?>>Expired</option>
                </select>
            </div>
            <div class="field">
                <label for="mou_expiry">MoU expiry</label>
                <input id="mou_expiry" name="mou_expiry" type="date" value="<?= e($org['mou_expiry'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="status">Register status</label>
                <select id="status" name="status">
                    <option value="active"<?= selected($org['status'] ?? 'active', 'active') ?>>Active</option>
                    <option value="inactive"<?= selected($org['status'] ?? '', 'inactive') ?>>Inactive</option>
                </select>
            </div>
            <div class="field">
                <label for="latitude">Site pin latitude</label>
                <input id="latitude" name="latitude" type="text" inputmode="decimal" value="<?= e(isset($geo['lat']) ? (string) $geo['lat'] : '') ?>" placeholder="8.484000">
                <span class="hint">Optional. With the longitude, shows how far a student's check-in is from the site.</span>
            </div>
            <div class="field">
                <label for="longitude">Site pin longitude</label>
                <input id="longitude" name="longitude" type="text" inputmode="decimal" value="<?= e(isset($geo['lng']) ? (string) $geo['lng'] : '') ?>" placeholder="-13.234000">
                <span class="hint">Leave both blank to keep the current pin. Clear it below to remove it.</span>
            </div>
            <?php if (isset($geo['lat'], $geo['lng'])): ?>
                <div class="field">
                    <label for="clear_geo">Remove site pin</label>
                    <label class="check-row"><input type="checkbox" id="clear_geo" name="clear_geo" value="1"><span>Delete the recorded latitude and longitude</span></label>
                </div>
            <?php endif; ?>
        </div>
        <div class="form-actions"><button class="btn" type="submit">Save organisation</button></div>
    </form>
</div>
