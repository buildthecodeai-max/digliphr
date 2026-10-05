<div class="d-flex justify-content-between mb-2">
    <div></div>
    <?php if (can('leave.types')): ?><a href="/admin/leave/types/create" class="btn btn-primary btn-sm">Add Leave Type</a><?php endif; ?>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Code</th><th>Paid</th><th>Half Day</th><th>Active</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($leaveTypes)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No leave types.</td></tr>
            <?php else: foreach ($leaveTypes as $lt): ?>
                <tr>
                    <td><?= e($lt['name']) ?></td>
                    <td><?= e($lt['code']) ?></td>
                    <td><?= !empty($lt['is_paid']) ? 'Yes' : 'No' ?></td>
                    <td><?= !empty($lt['allow_half_day']) ? 'Yes' : 'No' ?></td>
                    <td><?= status_badge(!empty($lt['is_active']) ? 'active' : 'inactive') ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $actions = [];
                        if (can('leave.types')) {
                            $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/leave/types/' . (int) $lt['id'] . '/edit'];
                        }
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
