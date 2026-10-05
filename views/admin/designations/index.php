<div class="card">
    <div class="card-header d-flex justify-content-between py-2">
        <span>All Designations</span>
        <?php if (can('designations.create')): ?><a href="/admin/designations/create" class="btn btn-primary btn-sm">Add Designation</a><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Code</th><th>Company</th><th>Department</th><th>Level</th><th>Rank</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($designations as $row): ?>
                <tr>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e($row['code'] ?? '—') ?></td>
                    <td><?= e($row['company_name'] ?? '—') ?></td>
                    <td><?= e($row['department_name'] ?? '—') ?></td>
                    <td><?= e($row['level'] ?? '—') ?></td>
                    <td><?= !empty($row['is_manager_or_above']) ? '<span class="badge bg-primary">Manager+</span>' : '<span class="text-muted">Staff</span>' ?></td>
                    <td><?= status_badge((int)$row['is_active'] ? 'active' : 'inactive') ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $did = (int) $row['id'];
                        $actions = [];
                        if (can('designations.update')) {
                            $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/designations/' . $did . '/edit'];
                        }
                        if (can('designations.delete')) {
                            $actions[] = [
                                'type' => 'form',
                                'icon' => 'trash-2',
                                'label' => 'Delete',
                                'variant' => 'danger',
                                'action' => '/admin/designations/' . $did . '/delete',
                                'confirm' => 'Delete this designation?',
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
