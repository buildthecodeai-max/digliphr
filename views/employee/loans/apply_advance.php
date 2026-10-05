<div class="page-header">
    <div>
        <h1>Apply for Salary Advance</h1>
        <p class="subtitle">Request a salary advance against your upcoming payroll.</p>
    </div>
    <a href="/employee/advances" class="btn btn-sm btn-soft">
        <i data-lucide="arrow-left" class="me-1" style="width:14px;height:14px"></i>Back to Advances
    </a>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="row justify-content-center">
<div class="col-lg-6">
<div class="card ems-card">
    <div class="card-header fw-semibold py-2">Advance Request</div>
    <div class="card-body">
        <form method="POST" action="/employee/advances">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="amount">Amount Requested <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="1" name="amount" id="amount"
                       class="form-control" value="<?= e($_POST['amount'] ?? '') ?>" required
                       placeholder="Enter amount…">
            </div>

            <div class="mb-3">
                <label class="form-label" for="installments_count">Repay over (months)</label>
                <input type="number" min="1" max="24" name="installments_count" id="installments_count"
                       class="form-control" value="<?= e($_POST['installments_count'] ?? '1') ?>"
                       placeholder="1 = deduct from next salary">
                <div class="form-text">Leave as 1 to deduct the full amount from your next salary.</div>
            </div>

            <div class="mb-4">
                <label class="form-label" for="reason">Reason <span class="text-danger">*</span></label>
                <textarea name="reason" id="reason" class="form-control" rows="3" required
                          placeholder="Briefly explain why you need this advance…"><?= e($_POST['reason'] ?? '') ?></textarea>
            </div>

            <div class="alert alert-warning py-2 small mb-4">
                <i data-lucide="alert-triangle" class="me-1" style="width:13px;height:13px"></i>
                Your request will be submitted as <strong>Pending</strong> and reviewed by HR/admin.
                The approved amount will be deducted from your salary.
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i data-lucide="send" class="me-1" style="width:14px;height:14px"></i>Submit Request
                </button>
                <a href="/employee/advances" class="btn btn-soft">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>
</div>
