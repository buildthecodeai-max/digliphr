<div class="page-header">
    <div>
        <h1>My Payslips</h1>
        <p class="subtitle">View and download your salary slips</p>
    </div>
</div>

<div class="card ems-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
            <tr>
                <th>Period</th>
                <th>Gross</th>
                <th>Deductions</th>
                <th>Net Pay</th>
                <th>Status</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($payslips['data'])): ?>
                <tr>
                    <td colspan="6">
                        <div class="empty-state mb-0 py-4">
                            <i data-lucide="receipt"></i>
                            No payslips are available yet. HR will publish them after payroll is processed.
                        </div>
                    </td>
                </tr>
            <?php else: foreach ($payslips['data'] as $p): ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e($p['period_name']) ?></div>
                        <div class="small text-secondary"><?= e(format_date($p['start_date'] ?? null)) ?> — <?= e(format_date($p['end_date'] ?? null)) ?></div>
                    </td>
                    <td><?= format_money($p['gross_earnings'], $p['currency']) ?></td>
                    <td><?= format_money($p['total_deductions'], $p['currency']) ?></td>
                    <td class="fw-bold text-primary"><?= format_money($p['net_salary'], $p['currency']) ?></td>
                    <td><?= status_badge($p['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $actions = [
                            ['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/employee/payslips/' . (int) $p['id']],
                            ['type' => 'link', 'icon' => 'file-down', 'label' => 'PDF', 'href' => '/employee/payslips/' . (int) $p['id'] . '/download'],
                        ];
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php $paginator = $payslips; $baseUrl = '/employee/payslips'; include config('app.paths.views') . '/partials/pagination.php'; ?>
<script>if (window.lucide) lucide.createIcons();</script>
