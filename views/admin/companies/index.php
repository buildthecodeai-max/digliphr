<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span>All Companies</span>
        <?php if (can('companies.create')): ?>
        <a href="/admin/companies/create" class="btn btn-primary btn-sm">Add Company</a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>Name</th><th>Code</th><th>Email</th><th>Phone</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($companies as $row): ?>
                <tr>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e($row['code'] ?? '—') ?></td>
                    <td><?= e($row['email'] ?? '—') ?></td>
                    <td><?= e($row['phone'] ?? '—') ?></td>
                    <td><?= status_badge((int)$row['is_active'] ? 'active' : 'inactive') ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $cid = (int) $row['id'];
                        $actions = [];
                        if (can('companies.update')) {
                            $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/companies/' . $cid . '/edit'];
                        }
                        if (can('companies.delete')) {
                            $actions[] = [
                                'type' => 'form',
                                'icon' => 'trash-2',
                                'label' => 'Delete',
                                'variant' => 'danger',
                                'action' => '/admin/companies/' . $cid . '/delete',
                                'confirm' => 'Delete this company? It will be archived if related records exist.',
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
