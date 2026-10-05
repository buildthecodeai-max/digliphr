<?php /** @var array $row */ ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= e($title ?? 'Payslip') ?></h1>
        <p class="text-muted small mb-0"><?= e($row['employee_name'] ?? '') ?> · <?= e($row['period_name'] ?? '') ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('payslips.edit')): ?>
        <a href="/admin/payslips/<?= (int)$row['id'] ?>/edit" class="btn btn-warning btn-sm">
            <i data-lucide="pencil" class="me-1" style="width:14px;height:14px"></i>Edit
        </a>
        <?php endif; ?>
        <?php if (can('payslips.delete')): ?>
        <form method="POST" action="/admin/payslips/<?= (int)$row['id'] ?>/delete" onsubmit="return confirm('Delete this payslip? This cannot be undone.')">
            <?= csrf_field() ?>
            <button class="btn btn-danger btn-sm">
                <i data-lucide="trash-2" class="me-1" style="width:14px;height:14px"></i>Delete
            </button>
        </form>
        <?php endif; ?>
        <a href="/admin/payslips" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
            <img src="<?= asset('images/diglip-logo.png') ?>" alt="Diglip" height="48" style="object-fit:contain">
            <div>
                <div class="fw-bold fs-5"><?= e($company['name'] ?? 'Diglip') ?></div>
                <div class="small text-muted">Pay Slip</div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-3"><div class="small text-muted">Payslip #</div><div class="fw-semibold"><?= e($row['payslip_number']) ?></div></div>
            <div class="col-md-3"><div class="small text-muted">Issue Date</div><div><?= e(format_date($row['issue_date'] ?? null)) ?></div></div>
            <div class="col-md-3"><div class="small text-muted">Status</div><div><?= status_badge($row['status']) ?></div></div>
            <div class="col-md-3"><div class="small text-muted">Currency</div><div><?= e($row['currency'] ?? 'PKR') ?></div></div>
            <div class="col-md-4"><div class="small text-muted">Gross Earnings</div><div class="fs-5"><?= e(format_money($row['gross_earnings'])) ?></div></div>
            <div class="col-md-4"><div class="small text-muted">Total Deductions</div><div class="fs-5"><?= e(format_money($row['total_deductions'])) ?></div></div>
            <div class="col-md-4"><div class="small text-muted">Net Salary</div><div class="fs-5 fw-bold text-success"><?= e(format_money($row['net_salary'])) ?></div></div>
        </div>
        <?php if (!empty($row['file_path'])): ?>
            <div class="mt-3">
                <a class="btn btn-primary btn-sm" href="/files/payslip/<?= (int) $row['id'] ?>">
                    <i data-lucide="file-down" class="me-1" style="width:14px;height:14px"></i>Download PDF
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
