<?php /** @var array $row */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-1"><?= e($title ?? 'Edit Payslip') ?></h1>
        <p class="text-muted small mb-0"><?= e($row['employee_name'] ?? '') ?> · <?= e($row['period_name'] ?? '') ?></p>
    </div>
    <a href="/admin/payslips/<?= (int)$row['id'] ?>" class="btn btn-outline-secondary btn-sm">Cancel</a>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card shadow-sm">
    <div class="card-header fw-semibold py-2">Payslip Details</div>
    <div class="card-body">
        <form method="POST" action="/admin/payslips/<?= (int)$row['id'] ?>/update">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="issue_date">Issue Date</label>
                <input type="date" name="issue_date" id="issue_date" class="form-control"
                       value="<?= e($row['issue_date'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label" for="status">Status</label>
                <select name="status" id="status" class="form-select">
                    <?php foreach (['generated','sent','viewed','downloaded','void'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($row['status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-4">
                    <label class="form-label" for="gross_earnings">Gross Earnings</label>
                    <input type="number" step="0.01" min="0" name="gross_earnings" id="gross_earnings"
                           class="form-control" value="<?= e((string)$row['gross_earnings']) ?>" required
                           oninput="calcNet()">
                </div>
                <div class="col-sm-4">
                    <label class="form-label" for="total_deductions">Total Deductions</label>
                    <input type="number" step="0.01" min="0" name="total_deductions" id="total_deductions"
                           class="form-control" value="<?= e((string)$row['total_deductions']) ?>" required
                           oninput="calcNet()">
                </div>
                <div class="col-sm-4">
                    <label class="form-label" for="net_salary">Net Salary</label>
                    <input type="number" step="0.01" name="net_salary" id="net_salary"
                           class="form-control" value="<?= e((string)$row['net_salary']) ?>" required>
                </div>
            </div>

            <div class="alert alert-warning py-2 small mb-3">
                <i data-lucide="alert-triangle" class="me-1" style="width:14px;height:14px"></i>
                Editing a payslip only updates the stored figures. Any generated PDF will reflect the old values — regenerate it from the payroll period if needed.
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="/admin/payslips/<?= (int)$row['id'] ?>" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<script>
function calcNet() {
    var gross = parseFloat(document.getElementById('gross_earnings').value) || 0;
    var ded   = parseFloat(document.getElementById('total_deductions').value) || 0;
    document.getElementById('net_salary').value = (gross - ded).toFixed(2);
}
</script>
