<?php /** @var array $companies */ /** @var int|null $companyId */ /** @var int $year */ ?>
<div class="page-header">
    <div>
        <h1>Leave Allocation</h1>
        <p class="subtitle">Apply default leave balances to employees or run monthly accrual.</p>
    </div>
    <a href="/admin/leave" class="btn btn-sm btn-soft">
        <i data-lucide="arrow-left" class="me-1" style="width:14px;height:14px"></i>Back to Leave
    </a>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<!-- Company / Year filter -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <?php if (count($companies) > 1): ?>
            <div class="col-md-4">
                <label class="form-label small mb-0">Company</label>
                <select name="company_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">Select company</option>
                    <?php foreach ($companies as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= (int)$companyId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="company_id" value="<?= (int)($companies[0]['id'] ?? 0) ?>">
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label small mb-0">Year</label>
                <input type="number" name="year" class="form-control form-control-sm" value="<?= (int)$year ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-secondary btn-sm w-100">Apply</button>
            </div>
        </form>
    </div>
</div>

<?php $cid = (int) ($companyId ?: ($companies[0]['id'] ?? 0)); ?>

<div class="row g-4">
    <!-- Yearly allocation -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold py-2">
                <i data-lucide="calendar" class="me-1" style="width:16px;height:16px"></i>
                Apply Yearly Defaults (<?= (int)$year ?>)
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Credits the <strong>default_days</strong> opening balance for every <em>yearly</em> leave type
                    (e.g. Annual Leave = 15 days) to all active employees who don't yet have a balance for <?= (int)$year ?>.
                    Employees who already have a balance are skipped.
                </p>
                <form method="POST" action="/admin/leave/allocate/run">
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_id" value="<?= $cid ?>">
                    <input type="hidden" name="year"       value="<?= (int)$year ?>">
                    <input type="hidden" name="action"     value="yearly">
                    <button class="btn btn-primary btn-sm" onclick="return confirm('Apply yearly default balances to all active employees for <?= (int)$year ?>?')">
                        <i data-lucide="check-circle" class="me-1" style="width:14px;height:14px"></i>
                        Apply Yearly Defaults
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Monthly accrual -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header fw-semibold py-2">
                <i data-lucide="refresh-cw" class="me-1" style="width:16px;height:16px"></i>
                Run Monthly Accrual
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Adds the monthly <strong>default_days</strong> to <em>accrued</em> for every active employee
                    for <em>monthly</em> leave types (e.g. Sick Leave & Casual Leave = 1 day/month).
                    Each employee is accrued at most once per calendar month.
                </p>
                <form method="POST" action="/admin/leave/allocate/run" class="row g-2 align-items-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="company_id" value="<?= $cid ?>">
                    <input type="hidden" name="year"       value="<?= (int)$year ?>">
                    <input type="hidden" name="action"     value="monthly">
                    <div class="col-auto">
                        <label class="form-label small mb-0">Month</label>
                        <select name="month" class="form-select form-select-sm">
                            <?php
                            $months = ['January','February','March','April','May','June',
                                       'July','August','September','October','November','December'];
                            $currentMonth = (int) date('n');
                            foreach ($months as $i => $mname):
                                $mnum = $i + 1;
                            ?>
                            <option value="<?= $mnum ?>" <?= $mnum === $currentMonth ? 'selected' : '' ?>><?= $mname ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-success btn-sm" onclick="return confirm('Run monthly accrual for selected month?')">
                            <i data-lucide="play" class="me-1" style="width:14px;height:14px"></i>
                            Run Accrual
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <div class="alert alert-info small py-2">
        <i data-lucide="info" class="me-1" style="width:14px;height:14px"></i>
        <strong>Default leave types</strong> are configured under
        <a href="/admin/leave/types">Leave Types</a> — edit any type to change its <em>Default Days</em>
        and <em>Accrual Period</em>. New employees automatically receive their balances when they are created.
    </div>
</div>
