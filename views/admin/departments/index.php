<div class="card">
    <div class="card-header d-flex justify-content-between py-2">
        <span>All Departments</span>
        <?php if (can('departments.create')): ?><a href="/admin/departments/create" class="btn btn-primary btn-sm">Add Department</a><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Code</th><th>Branch</th><th>Head</th><th>Schedule</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($departments as $row): ?>
                <tr>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e($row['code'] ?? '—') ?></td>
                    <td><?= e($row['branch_name'] ?? '—') ?></td>
                    <td><?= e($row['head_name'] ?? '—') ?></td>
                    <td><?= $row['week_pattern_name'] ? e($row['week_pattern_name']) : '<span class="text-muted">Company default</span>' ?></td>
                    <td><?= status_badge((int)$row['is_active'] ? 'active' : 'inactive') ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $did = (int) $row['id'];
                        $actions = [];
                        if (can('departments.update')) {
                            $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/departments/' . $did . '/edit'];
                        }
                        if (can('departments.delete')) {
                            $actions[] = [
                                'type' => 'form',
                                'icon' => 'trash-2',
                                'label' => 'Delete',
                                'variant' => 'danger',
                                'action' => '/admin/departments/' . $did . '/delete',
                                'confirm' => 'Delete this department?',
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
