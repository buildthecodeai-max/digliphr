<?php
/** @var array $overtime */
$rows = $overtime ?? [];
?>
<div class="card ems-card">
    <div class="card-header d-flex justify-content-between">
        <span class="report-card-title"><span class="rct-icon"><i data-lucide="timer"></i></span>Overtime Report</span>
        <span class="small text-muted">Calculated (from shift rules) vs. Approved (payroll-payable) shown separately</span>
    </div>
    <div class="report-table-shell">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Employee</th><th>Department</th><th>Date</th><th>Regular Hrs</th><th>Worked Hrs</th><th>Calculated OT</th><th>Requested OT</th><th>Approved OT</th><th>Approval Status</th><th>Reviewed By</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="10"><div class="empty-state mb-0 py-4">No overtime recorded in the selected period.</div></td></tr>
            <?php else: foreach ($rows as $r): $unclaimed = !$r['approval_status'] && (int) $r['calculated_overtime_minutes'] > 0; ?>
                <tr class="<?= $unclaimed ? 'table-warning' : '' ?>">
                    <td><div class="fw-semibold"><?= e($r['employee_name']) ?></div><div class="small text-muted"></div></td>
                    <td><?= e($r['department_name'] ?? '—') ?></td>
                    <td><?= e(format_date($r['attendance_date'])) ?></td>
                    <td><?= e(format_minutes((int) $r['regular_minutes'])) ?></td>
                    <td><?= e(format_minutes((int) $r['worked_minutes'])) ?></td>
                    <td class="fw-semibold"><?= e(format_minutes((int) $r['calculated_overtime_minutes'])) ?></td>
                    <td><?= $r['requested_minutes'] !== null ? e(format_minutes((int) $r['requested_minutes'])) : '—' ?></td>
                    <td><?= $r['approved_minutes'] !== null ? e(format_minutes((int) $r['approved_minutes'])) : '—' ?></td>
                    <td><?= $r['approval_status'] ? status_badge($r['approval_status']) : '<span class="badge bg-secondary">No Request</span>' ?></td>
                    <td><?= e($r['approved_by_name'] ?? '—') ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
