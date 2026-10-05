<?php
/** @var array $record */
/** @var array $period */
/** @var int $periodId */
?>
<div class="page-header d-flex justify-content-between mb-3">
    <div>
        <h1 class="h4 mb-0">Edit Payroll Record</h1>
        <p class="text-muted small mb-0"><?= e(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? '')) ?> · <?= e($period['name'] ?? '') ?></p>
    </div>
    <a href="/admin/payroll/<?= (int) $periodId ?>/records/<?= (int) $record['id'] ?>" class="btn btn-outline-secondary btn-sm">Back</a>
</div>
<?php include config('app.paths.views') . '/partials/alerts.php'; ?>
<div class="card">
    <div class="card-body">
        <form method="post" action="/admin/payroll/<?= (int) $periodId ?>/records/<?= (int) $record['id'] ?>/update" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label" for="gross_earnings">Gross earnings</label>
                <input type="number" step="0.01" min="0" name="gross_earnings" class="form-control" value="<?= e((string) $record['gross_earnings']) ?>" required id="gross_earnings">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="total_deductions">Total deductions</label>
                <input type="number" step="0.01" min="0" name="total_deductions" class="form-control" value="<?= e((string) $record['total_deductions']) ?>" required id="total_deductions">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="input-field">Current net</label>
                <input type="text" class="form-control" value="<?= e((string) $record['net_salary']) ?>" disabled id="input-field">
            </div>
            <div class="col-12">
                <label class="form-label" for="remarks">Remarks</label>
                <textarea name="remarks" class="form-control" rows="3" id="remarks"><?= e($record['remarks'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary">Save &amp; recalculate</button>
            </div>
        </form>
    </div>
</div>
