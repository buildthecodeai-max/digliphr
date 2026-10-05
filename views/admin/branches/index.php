<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span>All Branches</span>
        <?php if (can('branches.create')): ?><a href="/admin/branches/create" class="btn btn-primary btn-sm">Add Branch</a><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Company</th><th>City</th><th>Lat/Lng</th><th>Radius (m)</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($branches as $row): ?>
                <tr>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e($row['company_name'] ?? '—') ?></td>
                    <td><?= e($row['city'] ?? '—') ?></td>
                    <td><?= e($row['latitude'] ?? '—') ?>, <?= e($row['longitude'] ?? '—') ?></td>
                    <td><?= e($row['attendance_radius'] ?? '—') ?></td>
                    <td><?= status_badge((int)$row['is_active'] ? 'active' : 'inactive') ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $bid = (int) $row['id'];
                        $actions = [];
                        if (can('branches.update')) {
                            $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/branches/' . $bid . '/edit'];
                        }
                        if (can('branches.delete')) {
                            $actions[] = [
                                'type' => 'form',
                                'icon' => 'trash-2',
                                'label' => 'Delete',
                                'variant' => 'danger',
                                'action' => '/admin/branches/' . $bid . '/delete',
                                'confirm' => 'Delete this branch?',
                            ];
                        }
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
