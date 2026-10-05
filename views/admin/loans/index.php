<div class="row g-3">
<div class="col-lg-8"><div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
<thead class="table-light"><tr><th>No.</th><th>Employee</th><th>Type</th><th>Amount</th><th>EMI</th><th>Status</th><th></th></tr></thead>
<tbody>
<?php if (empty($rows)): ?><tr><td colspan="7" class="text-center text-muted py-4">No loans.</td></tr>
<?php else: foreach ($rows as $r): ?>
<tr>
<td><?= e($r['loan_number']) ?></td>
<td><?= e($r['employee_name']) ?></td>
<td><?= e($r['loan_type']) ?></td>
<td><?= e(format_money($r['principal_amount'])) ?></td>
<td><?= e(format_money($r['installment_amount'])) ?></td>
<td><?= status_badge($r['status']) ?></td>
<td class="text-end text-nowrap"><?php
$actions = [];
if (($r['status'] ?? '') === 'pending' && can('loans.approve')) {
    $actions[] = [
        'type' => 'form',
        'icon' => 'check',
        'label' => 'Approve',
        'variant' => 'success',
        'method' => 'POST',
        'action' => '/admin/loans/' . (int) $r['id'] . '/approve',
    ];
}
include config('app.paths.views') . '/partials/table-actions.php';
?></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div></div></div>
<?php if (can('loans.manage')): ?>
<div class="col-lg-4"><div class="card"><div class="card-header py-2 fw-semibold">New Loan</div><div class="card-body">
<form method="POST" action="/admin/loans"><?= csrf_field() ?>
<div class="mb-2"><select name="employee_id" class="form-select form-select-sm" required><option value="">Employee…</option><?php foreach ($employees as $e): ?><option value="<?= (int)$e['id'] ?>"><?= e($e['name']) ?></option><?php endforeach; ?></select></div>
<div class="mb-2"><select name="loan_type" class="form-select form-select-sm"><option value="personal">Personal</option><option value="salary">Salary</option><option value="emergency">Emergency</option><option value="housing">Housing</option><option value="vehicle">Vehicle</option><option value="other">Other</option></select></div>
<div class="mb-2"><input type="number" step="0.01" name="principal_amount" class="form-control form-control-sm" placeholder="Amount" required></div>
<div class="mb-2"><input type="number" name="total_installments" class="form-control form-control-sm" placeholder="Installments" value="12" required></div>
<div class="mb-2"><input type="date" name="start_date" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>" required></div>
<div class="mb-2"><textarea name="reason" class="form-control form-control-sm" rows="2" placeholder="Reason"></textarea></div>
<button class="btn btn-primary btn-sm w-100">Create</button>
</form></div></div></div>
<?php endif; ?>
</div>
