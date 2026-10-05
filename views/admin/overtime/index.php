<div class="container-fluid py-3">
    <div class="mb-3">
        <h1 class="h4 mb-1"><?= e($title ?? 'Overtime Requests') ?></h1>
        <p class="text-muted small mb-0">Review and approve employee overtime</p>
    </div>

    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Requested</th>
                        <th>Approved</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($rows)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">No overtime requests.</td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e(format_date($r['overtime_date'] ?? null)) ?></td>
                        <td>
                            <div class="fw-semibold"><?= e($r['employee_name']) ?></div>
                            <div class="small text-muted"><?= e($r['employee_code'] ?? '') ?></div>
                        </td>
                        <td><?= (int) ($r['requested_minutes'] ?? 0) ?> min</td>
                        <td><?= $r['approved_minutes'] !== null ? ((int) $r['approved_minutes'] . ' min') : '—' ?></td>
                        <td><?= isset($r['amount']) ? e(format_money($r['amount'])) : '—' ?></td>
                        <td class="small"><?= e($r['reason'] ?? '—') ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td class="text-end text-nowrap">
                            <?php
                            $oid = (int) $r['id'];
                            $actions = [];
                            if (($r['status'] ?? '') === 'pending' && can('overtime.approve')) {
                                $actions[] = ['type' => 'form', 'icon' => 'check', 'label' => 'Approve',
                                    'variant' => 'success', 'action' => '/admin/overtime/' . $oid . '/approve'];
                                $actions[] = ['type' => 'form', 'icon' => 'x', 'label' => 'Reject',
                                    'variant' => 'danger', 'action' => '/admin/overtime/' . $oid . '/reject'];
                            }
                            if (can('overtime.approve')) {
                                $actions[] = ['type' => 'form', 'icon' => 'trash-2', 'label' => 'Delete',
                                    'variant' => 'danger', 'action' => '/admin/overtime/' . $oid . '/delete',
                                    'confirm' => 'Delete this overtime request?'];
                            }
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
</div>
