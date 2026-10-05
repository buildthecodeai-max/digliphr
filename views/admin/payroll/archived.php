<?php
/** @var array $periods */
?>
<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="page-title h4 mb-0">Archived Payroll</h1>
        <p class="text-muted small mb-0">Soft-deleted payroll periods</p>
    </div>
    <a href="/admin/payroll" class="btn btn-outline-secondary btn-sm">Active Payroll</a>
</div>
<?php include config('app.paths.views') . '/partials/alerts.php'; ?>
<div class="card card-compact">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Period</th><th>Previous status</th><th>Gross</th><th>Deductions</th><th>Net</th>
                    <th>Archived by</th><th>Archived date</th><th>Reason</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($periods['data'])): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No archived payroll records found.</td></tr>
            <?php else: foreach ($periods['data'] as $period): ?>
                <tr>
                    <td><a href="/admin/payroll/<?= (int) $period['id'] ?>"><?= e($period['name']) ?></a></td>
                    <td><?= status_badge($period['status']) ?></td>
                    <td><?= format_money($period['total_gross']) ?></td>
                    <td><?= format_money($period['total_deductions']) ?></td>
                    <td><?= format_money($period['total_net']) ?></td>
                    <td><?= e($period['archived_by_name'] ?? '—') ?></td>
                    <td><?= e(format_datetime($period['deleted_at'] ?? null)) ?></td>
                    <td class="small"><?= e($period['deletion_reason'] ?? '—') ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $pid = (int) $period['id'];
                        $actions = [
                            ['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/admin/payroll/' . $pid],
                        ];
                        if (can('payroll.restore')) {
                            $actions[] = [
                                'type' => 'form',
                                'icon' => 'rotate-ccw',
                                'label' => 'Restore',
                                'variant' => 'success',
                                'action' => '/admin/payroll/' . $pid . '/restore',
                                'confirm' => 'Restore this payroll period?',
                            ];
                        }
                        if (can('payroll.permanently_delete')) {
                            $actions[] = [
                                'type' => 'form',
                                'icon' => 'trash-2',
                                'label' => 'Permanently delete',
                                'variant' => 'danger',
                                'action' => '/admin/payroll/' . $pid . '/force-delete',
                                'confirm' => 'Permanently delete this payroll period? This cannot be undone.',
                                'fields' => ['reason' => 'Permanent delete of invalid archived draft'],
                            ];
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
<?php
$paginator = $periods;
$baseUrl = '/admin/payroll/archived';
include config('app.paths.views') . '/partials/pagination.php';
?>
