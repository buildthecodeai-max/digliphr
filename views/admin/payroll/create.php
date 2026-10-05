<div class="page-header mb-3">
    <h1 class="page-title h4 mb-0">Create Payroll Period</h1>
</div>

<div class="card card-compact col-lg-6">
    <div class="card-body">
        <form method="POST" action="/admin/payroll">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="period_year">Year</label>
                    <input type="number" name="period_year" class="form-control" value="<?= date('Y') ?>" required id="period_year">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="period_month">Month</label>
                    <select name="period_month" class="form-select" required id="period_month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>"<?= (int) date('n') === $m ? ' selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                        <?php endfor; ?>
                    </select>
                    <div class="form-text">The period uses calendar dates. Process it only after attendance, leave, overtime, loans, and advances are reviewed.</div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary btn-sm">Create Period</button>
                <a href="/admin/payroll" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </form>
    </div>
</div>
