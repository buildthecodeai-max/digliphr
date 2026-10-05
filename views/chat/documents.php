<div class="chat-page-wrap p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1">Shared Documents</h1>
            <p class="text-muted small mb-0">Versioned HR documents with optional acknowledgement.</p>
        </div>
        <a href="/chat" class="btn btn-sm btn-soft">Back to chat</a>
    </div>

    <?php if (!empty($canManage)): ?>
    <form method="post" action="/chat/documents" enctype="multipart/form-data" class="card border-0 shadow-sm p-3 mb-4">
        <?= csrf_field() ?>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label small">Title</label>
                <input type="text" name="title" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">—</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small">File</label>
                <input type="file" name="file" class="form-control form-control-sm">
            </div>
            <div class="col-12">
                <label class="form-label small">Description</label>
                <textarea name="description" class="form-control form-control-sm" rows="2"></textarea>
            </div>
            <div class="col-12 form-check ms-2">
                <input class="form-check-input" type="checkbox" name="requires_acknowledgement" value="1" id="ackReq">
                <label class="form-check-label" for="ackReq">Require acknowledgement</label>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm">Publish document</button>
            </div>
        </div>
    </form>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-sm align-middle">
            <thead><tr><th>Title</th><th>Category</th><th>Version</th><th>Acks</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($documents)): ?>
                <tr><td colspan="5" class="text-muted">No shared documents yet.</td></tr>
            <?php else: foreach ($documents as $d): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($d['title']) ?></div>
                        <div class="small text-muted"><?= e($d['description'] ?? '') ?></div>
                    </td>
                    <td><?= e($d['category_name'] ?? '—') ?></td>
                    <td>v<?= (int) $d['current_version'] ?></td>
                    <td><?= (int) ($d['ack_count'] ?? 0) ?></td>
                    <td>
                        <?php if (!empty($d['requires_acknowledgement'])): ?>
                        <form method="post" action="/chat/documents/<?= (int) $d['id'] ?>/acknowledge" class="d-inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-sm btn-primary" type="submit">Acknowledge</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
