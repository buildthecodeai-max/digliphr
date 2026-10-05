<div class="chat-page-wrap p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Chat Files</h1>
            <p class="text-muted small mb-0">Files shared in channels and DMs you can access.</p>
        </div>
        <a href="/chat" class="btn btn-sm btn-soft">Back to chat</a>
    </div>
    <form method="get" class="mb-3" style="max-width:360px">
        <div class="input-group input-group-sm">
            <input type="search" name="q" value="<?= e($q ?? '') ?>" class="form-control" placeholder="Search files…">
            <button class="btn btn-primary" type="submit">Search</button>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>File</th><th>Type</th><th>Size</th><th>By</th><th>When</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($files)): ?>
                <tr><td colspan="6" class="text-muted">No files found.</td></tr>
            <?php else: foreach ($files as $f): ?>
                <tr>
                    <td><?= e($f['original_filename']) ?></td>
                    <td><span class="badge bg-light text-dark"><?= e($f['preview_type'] ?? 'file') ?></span></td>
                    <td><?= number_format(((int) $f['file_size']) / 1024, 1) ?> KB</td>
                    <td><?= e($f['uploader_name'] ?? '—') ?></td>
                    <td><?= e(format_datetime($f['created_at'] ?? null)) ?></td>
                    <td><a class="btn btn-sm btn-soft" href="/files/chat-attachment/<?= (int) $f['id'] ?>">Open</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
