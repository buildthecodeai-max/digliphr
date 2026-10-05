<?php $isEdit = !empty($branch); ?>
<div class="card"><div class="card-body">
<form method="POST" action="<?= $isEdit ? '/admin/branches/' . (int)$branch['id'] : '/admin/branches' ?>">
    <?= csrf_field() ?>
    <div class="row g-2">
        <div class="col-md-4"><label class="form-label" for="company_id">Company *</label><select name="company_id" class="form-select form-select-sm" required id="company_id">
            <option value="">Select</option><?php foreach ($companies as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)old('company_id', $branch['company_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-4"><label class="form-label" for="name">Name *</label><input type="text" name="name" class="form-control form-control-sm" value="<?= e(old('name', $branch['name'] ?? '')) ?>" required id="name"></div>
        <div class="col-md-4"><label class="form-label" for="code">Code</label><input type="text" name="code" class="form-control form-control-sm" value="<?= e(old('code', $branch['code'] ?? '')) ?>" id="code"></div>
        <div class="col-md-6"><label class="form-label" for="address">Address</label><input type="text" name="address" class="form-control form-control-sm" value="<?= e(old('address', $branch['address'] ?? '')) ?>" id="address"></div>
        <div class="col-md-3"><label class="form-label" for="city">City</label><input type="text" name="city" class="form-control form-control-sm" value="<?= e(old('city', $branch['city'] ?? '')) ?>" id="city"></div>
        <div class="col-md-3"><label class="form-label" for="timezone">Timezone</label><input type="text" name="timezone" class="form-control form-control-sm" value="<?= e(old('timezone', $branch['timezone'] ?? 'UTC')) ?>" id="timezone"></div>
        <div class="col-md-3"><label class="form-label" for="latitude">Latitude</label><input type="number" step="any" name="latitude" class="form-control form-control-sm" value="<?= e(old('latitude', $branch['latitude'] ?? '')) ?>" id="latitude"></div>
        <div class="col-md-3"><label class="form-label" for="longitude">Longitude</label><input type="number" step="any" name="longitude" class="form-control form-control-sm" value="<?= e(old('longitude', $branch['longitude'] ?? '')) ?>" id="longitude"></div>
        <div class="col-md-3"><label class="form-label" for="attendance_radius">Attendance Radius (m)</label><input type="number" step="any" name="attendance_radius" class="form-control form-control-sm" value="<?= e(old('attendance_radius', $branch['attendance_radius'] ?? 100)) ?>" id="attendance_radius"></div>
        <div class="col-md-3"><label class="form-label" for="contact_person">Contact Person</label><input type="text" name="contact_person" class="form-control form-control-sm" value="<?= e(old('contact_person', $branch['contact_person'] ?? '')) ?>" id="contact_person"></div>
        <div class="col-md-3"><label class="form-label" for="contact_phone">Contact Phone</label><input type="text" name="contact_phone" class="form-control form-control-sm" value="<?= e(old('contact_phone', $branch['contact_phone'] ?? '')) ?>" id="contact_phone"></div>
        <div class="col-md-3"><label class="form-label" for="contact_email">Contact Email</label><input type="email" name="contact_email" class="form-control form-control-sm" value="<?= e(old('contact_email', $branch['contact_email'] ?? '')) ?>" id="contact_email"></div>
        <div class="col-12"><div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_head_office" value="1" <?= old('is_head_office', $branch['is_head_office'] ?? 0) ? 'checked' : '' ?> id="is_head_office"><label class="form-check-label" for="is_head_office">Head Office</label></div>
        <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= old('is_active', $branch['is_active'] ?? 1) ? 'checked' : '' ?> id="is_active"><label class="form-check-label" for="is_active">Active</label></div></div>
    </div>
    <div class="mt-3"><button type="submit" class="btn btn-primary btn-sm">Save</button> <a href="/admin/branches" class="btn btn-secondary btn-sm">Cancel</a></div>
</form></div></div>
