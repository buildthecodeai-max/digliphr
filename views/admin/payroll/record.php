<?php /** @var array $record */ ?>
<div class="page-header mb-3">
    <h1 class="page-title h4 mb-0">Payroll Record — <?= e($record['first_name'] . ' ' . $record['last_name']) ?></h1>
    <p class="text-muted small"><?= e($record['period_name']) ?> · <?= e($record['employee_code']) ?></p>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-2"><div class="stat-card"><div class="stat-label">Basic</div><div class="stat-value stat-value-sm"><?= format_money($record['basic_salary'], $record['currency']) ?></div></div></div>
    <div class="col-md-2"><div class="stat-card"><div class="stat-label">Gross</div><div class="stat-value stat-value-sm"><?= format_money($record['gross_earnings'], $record['currency']) ?></div></div></div>
    <div class="col-md-2"><div class="stat-card"><div class="stat-label">Deductions</div><div class="stat-value stat-value-sm"><?= format_money($record['total_deductions'], $record['currency']) ?></div></div></div>
    <div class="col-md-2"><div class="stat-card"><div class="stat-label">Net</div><div class="stat-value stat-value-sm"><?= format_money($record['net_salary'], $record['currency']) ?></div></div></div>
    <div class="col-md-2"><div class="stat-card"><div class="stat-label">OT</div><div class="stat-value stat-value-sm"><?= format_money($record['overtime_amount'], $record['currency']) ?></div></div></div>
    <div class="col-md-2"><div class="stat-card"><div class="stat-label">Status</div><div class="mt-1"><?= status_badge($record['status']) ?></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card card-compact">
            <div class="card-header py-2"><strong>Earnings</strong></div>
            <table class="table table-sm mb-0">
                <tbody>
                <?php foreach ($earnings as $e): ?>
                    <tr><td><?= e($e['component_name']) ?></td><td class="text-end"><?= format_money($e['amount'], $record['currency']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-compact">
            <div class="card-header py-2"><strong>Deductions</strong></div>
            <table class="table table-sm mb-0">
                <tbody>
                <?php foreach ($deductions as $d): ?>
                    <tr><td><?= e($d['component_name']) ?></td><td class="text-end"><?= format_money($d['amount'], $record['currency']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($deductions)): ?><tr><td colspan="2" class="text-muted">No deductions</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
