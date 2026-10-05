<?php $isEdit = !empty($company); ?>
<div class="card">
    <div class="card-body">
        <form method="POST" action="<?= $isEdit ? '/admin/companies/' . (int)$company['id'] : '/admin/companies' ?>">
            <?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-md-6"><label class="form-label" for="name">Name *</label><input type="text" name="name" class="form-control form-control-sm" value="<?= e(old('name', $company['name'] ?? '')) ?>" required id="name"></div>
                <div class="col-md-3"><label class="form-label" for="code">Code</label><input type="text" name="code" class="form-control form-control-sm" value="<?= e(old('code', $company['code'] ?? '')) ?>" id="code"></div>
                <div class="col-md-3"><label class="form-label" for="legal_name">Legal Name</label><input type="text" name="legal_name" class="form-control form-control-sm" value="<?= e(old('legal_name', $company['legal_name'] ?? '')) ?>" id="legal_name"></div>
                <div class="col-md-4"><label class="form-label" for="email">Email</label><input type="email" name="email" class="form-control form-control-sm" value="<?= e(old('email', $company['email'] ?? '')) ?>" id="email"></div>
                <div class="col-md-4"><label class="form-label" for="phone">Phone</label><input type="text" name="phone" class="form-control form-control-sm" value="<?= e(old('phone', $company['phone'] ?? '')) ?>" id="phone"></div>
                <div class="col-md-4"><label class="form-label" for="website">Website</label><input type="url" name="website" class="form-control form-control-sm" value="<?= e(old('website', $company['website'] ?? '')) ?>" id="website"></div>
                <div class="col-md-6"><label class="form-label" for="address_line1">Address</label><input type="text" name="address_line1" class="form-control form-control-sm" value="<?= e(old('address_line1', $company['address_line1'] ?? '')) ?>" id="address_line1"></div>
                <div class="col-md-3"><label class="form-label" for="city">City</label><input type="text" name="city" class="form-control form-control-sm" value="<?= e(old('city', $company['city'] ?? '')) ?>" id="city"></div>
                <div class="col-md-3"><label class="form-label" for="country">Country</label><input type="text" name="country" class="form-control form-control-sm" value="<?= e(old('country', $company['country'] ?? '')) ?>" id="country"></div>
                <div class="col-md-3"><label class="form-label" for="timezone">Timezone</label><input type="text" name="timezone" class="form-control form-control-sm" value="<?= e(old('timezone', $company['timezone'] ?? 'UTC')) ?>" id="timezone"></div>
                <div class="col-md-3"><label class="form-label" for="currency">Currency</label><input type="text" name="currency" class="form-control form-control-sm" value="<?= e(old('currency', $company['currency'] ?? 'PKR')) ?>" id="currency"></div>
                <div class="col-md-3"><label class="form-label" for="tax_number">Tax #</label><input type="text" name="tax_number" class="form-control form-control-sm" value="<?= e(old('tax_number', $company['tax_number'] ?? '')) ?>" id="tax_number"></div>
                <div class="col-md-3"><label class="form-label" for="registration_number">Registration #</label><input type="text" name="registration_number" class="form-control form-control-sm" value="<?= e(old('registration_number', $company['registration_number'] ?? '')) ?>" id="registration_number"></div>
                <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= old('is_active', $company['is_active'] ?? 1) ? 'checked' : '' ?> id="is_active"><label class="form-check-label" for="is_active">Active</label></div></div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <a href="/admin/companies" class="btn btn-secondary btn-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
