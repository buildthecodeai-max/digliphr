<div class="container-fluid py-3" style="max-width:720px">
    <h1 class="h4 mb-3">Chat Settings</h1>
    <form method="post" action="/admin/chat/settings" class="card border-0 shadow-sm p-3 mb-3">
        <?= csrf_field() ?>
        <div class="mb-3">
            <label class="form-label">Polling interval (seconds, 3–10)</label>
            <input type="number" min="3" max="10" name="polling_interval_seconds" class="form-control" value="<?= e($settings['polling_interval_seconds'] ?? '5') ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Max upload size (bytes)</label>
            <input type="number" name="max_upload_bytes" class="form-control" value="<?= e($settings['max_upload_bytes'] ?? '10485760') ?>">
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="allow_mass_mentions" value="1" id="amm" <?= ($settings['allow_mass_mentions'] ?? '1') === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="amm">Allow @channel / @here for permitted roles</label>
        </div>
        <button type="submit" class="btn btn-primary">Save settings</button>
    </form>
    <form method="post" action="/admin/chat/settings/rebuild" onsubmit="return confirm('Rebuild system channels and re-sync memberships?');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-soft">Rebuild auto-channels & sync members</button>
    </form>
</div>
