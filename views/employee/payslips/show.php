<?php /** @var array $record */ ?>
<div class="page-header">
    <div>
        <h1>Payslip — <?= e($record['period_name']) ?></h1>
        <p class="subtitle"><?= e(format_date($record['period_start'] ?? null)) ?> — <?= e(format_date($record['period_end'] ?? null)) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/employee/payslips" class="btn btn-sm btn-soft">Back</a>
        <a href="/employee/payslips/<?= (int) $record['id'] ?>/download" class="btn btn-sm btn-primary">
            <i data-lucide="download" class="me-1" style="width:14px;height:14px"></i>Download PDF
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="metric-card tone-mint">
            <div class="metric-icon"><i data-lucide="trending-up"></i></div>
            <div class="metric-label">Gross Earnings</div>
            <div class="metric-value" style="font-size:1.25rem"><?= format_money($record['gross_earnings'], $record['currency']) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card tone-orange">
            <div class="metric-icon"><i data-lucide="minus-circle"></i></div>
            <div class="metric-label">Total Deductions</div>
            <div class="metric-value" style="font-size:1.25rem"><?= format_money($record['total_deductions'], $record['currency']) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card tone-purple">
            <div class="metric-icon"><i data-lucide="wallet"></i></div>
            <div class="metric-label">Net Salary</div>
            <div class="metric-value" style="font-size:1.25rem"><?= format_money($record['net_salary'], $record['currency']) ?></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card ems-card">
            <div class="card-header">Earnings</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php if (empty($earnings)): ?>
                        <tr><td class="text-secondary text-center py-3">No earning lines.</td></tr>
                    <?php else: foreach ($earnings as $e): ?>
                        <tr>
                            <td><?= e($e['component_name'] ?? $e['name'] ?? 'Earning') ?></td>
                            <td class="text-end fw-semibold"><?= format_money($e['amount'], $record['currency']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card ems-card">
            <div class="card-header">Deductions</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <tbody>
                    <?php if (empty($deductions)): ?>
                        <tr><td class="text-secondary text-center py-3">No deduction lines.</td></tr>
                    <?php else: foreach ($deductions as $d): ?>
                        <tr>
                            <td><?= e($d['component_name'] ?? $d['name'] ?? 'Deduction') ?></td>
                            <td class="text-end fw-semibold"><?= format_money($d['amount'], $record['currency']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>if (window.lucide) lucide.createIcons();</script>
