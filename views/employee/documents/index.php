<div class="page-header">
    <div>
        <h1>My Documents</h1>
        <p class="subtitle">HR documents shared with your account</p>
    </div>
</div>

<div class="card ems-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Issued</th>
                    <th>Expires</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="5"><div class="empty-state py-4 mb-0">No documents available.</div></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['title'] ?? $r['document_type'] ?? 'Document') ?></td>
                    <td><?= e(ucwords(str_replace('_', ' ', (string) ($r['document_type'] ?? '—')))) ?></td>
                    <td><?= e(format_date($r['issue_date'] ?? null)) ?></td>
                    <td><?= e(format_date($r['expiry_date'] ?? null)) ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $actions = [
                            [
                                'type' => 'link',
                                'icon' => 'eye',
                                'label' => 'View document',
                                'href' => '/files/document/' . (int) $r['id'],
                                'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
                            ],
                            ['type' => 'link', 'icon' => 'download', 'label' => 'Download document', 'href' => '/files/document/' . (int) $r['id'] . '?download=1'],
                        ];
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
