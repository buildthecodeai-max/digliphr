<div class="page-header">
    <div>
        <h1>My Assets</h1>
        <p class="subtitle">Company assets currently assigned to you</p>
    </div>
</div>

<div class="card ems-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Tag</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Serial</th>
                    <th>Assigned</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6"><div class="empty-state py-4 mb-0">No assets assigned.</div></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['asset_tag']) ?></td>
                    <td>
                        <?= e($r['asset_name']) ?>
                        <?php if (!empty($r['brand']) || !empty($r['model'])): ?>
                            <div class="small text-muted"><?= e(trim(($r['brand'] ?? '') . ' ' . ($r['model'] ?? ''))) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= e($r['asset_type']) ?></td>
                    <td><?= e($r['serial_number'] ?? '—') ?></td>
                    <td><?= e(format_date($r['assigned_date'])) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
