<?php /** @var array $rows */ /** @var int $month */ /** @var int $year */ ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0"><?= e($title ?? 'Payslips') ?></h1>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<!-- Month / Year filter -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-0">Month</label>
                <select name="month" class="form-select form-select-sm">
                    <?php
                    $months = ['January','February','March','April','May','June',
                               'July','August','September','October','November','December'];
                    foreach ($months as $i => $mname):
                        $mnum = $i + 1;
                    ?>
                    <option value="<?= $mnum ?>" <?= $mnum === (int)$month ? 'selected' : '' ?>><?= $mname ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Year</label>
                <input type="number" name="year" class="form-control form-control-sm" value="<?= (int)$year ?>">
            </div>
            <div class="col-md-2">
                <button class="btn btn-secondary btn-sm w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Number</th>
                    <th>Employee</th>
                    <th>Period</th>
                    <th>Issue Date</th>
                    <th>Gross</th>
                    <th>Deductions</th>
                    <th>Net</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">
                    No payslips found for <?= date('F', mktime(0,0,0,(int)$month,1)) ?> <?= (int)$year ?>.
                </td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="fw-semibold"><?= e($r['payslip_number']) ?></td>
                    <td>
                        <div><?= e($r['employee_name']) ?></div>
                        <div class="small text-muted"><?= e($r['employee_code'] ?? '') ?></div>
                    </td>
                    <td><?= e($r['period_name'] ?? '—') ?></td>
                    <td><?= e(format_date($r['issue_date'] ?? null)) ?></td>
                    <td><?= e(format_money($r['gross_earnings'])) ?></td>
                    <td><?= e(format_money($r['total_deductions'])) ?></td>
                    <td class="fw-semibold"><?= e(format_money($r['net_salary'])) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $pid = (int) $r['id'];
                        $actions = [
                            ['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/admin/payslips/' . $pid],
                        ];
                        if (!empty($r['file_path'])) {
                            $actions[] = ['type' => 'link', 'icon' => 'file-down', 'label' => 'PDF', 'href' => '/files/payslip/' . $pid];
                        }
                        if (can('payslips.edit')) {
                            $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/payslips/' . $pid . '/edit'];
                        }
                        if (can('payslips.delete')) {
                            $actions[] = [
                                'type' => 'form', 'icon' => 'trash-2', 'label' => 'Delete', 'variant' => 'danger',
                                'action' => '/admin/payslips/' . $pid . '/delete',
                                'confirm' => 'Delete payslip ' . e($r['payslip_number']) . '? This cannot be undone.',
                            ];
                        }
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($rows)): ?>
    <div class="card-footer py-2 text-end text-muted small">
        <?= count($rows) ?> payslip<?= count($rows) !== 1 ? 's' : '' ?> for
        <?= date('F', mktime(0,0,0,(int)$month,1)) ?> <?= (int)$year ?>
    </div>
    <?php endif; ?>
</div>
