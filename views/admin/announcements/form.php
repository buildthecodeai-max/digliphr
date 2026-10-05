<?php
$a = $announcement ?? [];
$edit = !empty($a);
?>
<div class="page-header">
    <div>
        <h1><?= $edit ? 'Edit Announcement' : 'New Announcement' ?></h1>
        <p class="subtitle">Share updates with employees</p>
    </div>
</div>

<div class="row justify-content-center">
<div class="col-lg-8">
<div class="card ems-card"><div class="card-body">
<form method="POST" action="<?= $edit ? '/admin/announcements/' . (int) $a['id'] : '/admin/announcements' ?>">
<?= csrf_field() ?>
<?php if (!empty($companies)): ?>
<div class="mb-3">
    <label class="form-label" for="company_id">Company</label>
    <select name="company_id" class="form-select" id="company_id">
        <?php foreach ($companies as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= (int) ($a['company_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>
<div class="mb-3"><label class="form-label" for="title">Title</label><input name="title" class="form-control" value="<?= e($a['title'] ?? '') ?>" required id="title"></div>
<div class="mb-3"><label class="form-label" for="body">Message</label><textarea name="body" class="form-control" rows="5" required id="body"><?= e($a['body'] ?? '') ?></textarea></div>
<div class="row g-3 mb-3">
<div class="col-md-4">
    <label class="form-label" for="audience">Audience</label>
    <select name="audience" class="form-select" id="audience">
        <?php foreach (['all' => 'All', 'branch' => 'Branch', 'department' => 'Department'] as $val => $label): ?>
        <option value="<?= $val ?>" <?= ($a['audience'] ?? 'all') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-4">
    <label class="form-label" for="publish_at">Publish at</label>
    <input type="datetime-local" name="publish_at" class="form-control" value="<?= e(!empty($a['publish_at']) ? date('Y-m-d\TH:i', strtotime($a['publish_at'])) : '') ?>" id="publish_at">
</div>
<div class="col-md-4">
    <label class="form-label" for="expires_at">Expires at</label>
    <input type="datetime-local" name="expires_at" class="form-control" value="<?= e(!empty($a['expires_at']) ? date('Y-m-d\TH:i', strtotime($a['expires_at'])) : '') ?>" id="expires_at">
</div>
</div>
<div class="form-check mb-3">
    <input class="form-check-input" type="checkbox" name="is_published" value="1" id="pub" <?= !isset($a['is_published']) || !empty($a['is_published']) ? 'checked' : '' ?>>
    <label class="form-check-label" for="pub">Publish</label>
</div>
<button class="btn btn-primary"><?= $edit ? 'Update' : 'Create' ?></button>
<a href="/admin/announcements" class="btn btn-soft">Cancel</a>
</form>
</div></div>
</div>
</div>
