<div class="page-header">
    <div>
        <h1>Apply for Loan</h1>
        <p class="subtitle">Submit a loan request for admin review and approval.</p>
    </div>
    <a href="/employee/loans" class="btn btn-sm btn-soft">
        <i data-lucide="arrow-left" class="me-1" style="width:14px;height:14px"></i>Back to Loans
    </a>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card ems-card">
    <div class="card-header fw-semibold py-2">Loan Application</div>
    <div class="card-body">
        <form method="POST" action="/employee/loans">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="loan_type">Loan Type <span class="text-danger">*</span></label>
                <select name="loan_type" id="loan_type" class="form-select" required>
                    <option value="">Select type…</option>
                    <?php foreach (['personal'=>'Personal','salary'=>'Salary','emergency'=>'Emergency','housing'=>'Housing','vehicle'=>'Vehicle','other'=>'Other'] as $v=>$l): ?>
                    <option value="<?= $v ?>" <?= ($_POST['loan_type'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label" for="principal_amount">Amount <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="1" name="principal_amount" id="principal_amount"
                           class="form-control" value="<?= e($_POST['principal_amount'] ?? '') ?>" required
                           oninput="calcEmi()">
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="total_installments">Installments (months) <span class="text-danger">*</span></label>
                    <input type="number" min="1" max="120" name="total_installments" id="total_installments"
                           class="form-control" value="<?= e($_POST['total_installments'] ?? '') ?>" required
                           oninput="calcEmi()">
                </div>
            </div>

            <div class="alert alert-info py-2 small mb-3" id="emiInfo" style="display:none">
                <i data-lucide="info" class="me-1" style="width:13px;height:13px"></i>
                Monthly installment: <strong id="emiAmt">—</strong>
            </div>

            <div class="mb-3">
                <label class="form-label" for="start_date">Preferred Start Date <span class="text-danger">*</span></label>
                <input type="date" name="start_date" id="start_date" class="form-control"
                       value="<?= e($_POST['start_date'] ?? date('Y-m-d')) ?>" required>
            </div>

            <div class="mb-4">
                <label class="form-label" for="reason">Reason / Purpose</label>
                <textarea name="reason" id="reason" class="form-control" rows="3"
                          placeholder="Briefly describe the purpose of this loan…"><?= e($_POST['reason'] ?? '') ?></textarea>
            </div>

            <div class="alert alert-warning py-2 small mb-4">
                <i data-lucide="alert-triangle" class="me-1" style="width:13px;height:13px"></i>
                Your request will be submitted as <strong>Pending</strong> and reviewed by HR/admin.
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="send" class="me-1" style="width:14px;height:14px"></i>Submit Application
                </button>
                <a href="/employee/loans" class="btn btn-soft">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>

<script>
function calcEmi() {
    var amt  = parseFloat(document.getElementById('principal_amount').value) || 0;
    var inst = parseInt(document.getElementById('total_installments').value) || 0;
    var info = document.getElementById('emiInfo');
    if (amt > 0 && inst > 0) {
        document.getElementById('emiAmt').textContent = (amt / inst).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
        info.style.display = '';
    } else {
        info.style.display = 'none';
    }
}
</script>
