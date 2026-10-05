<?php
$isAdvances = ($type ?? '') === 'advances';
?>
<div class="page-header">
    <div>
        <h1><?= e($title ?? ($isAdvances ? 'My Salary Advances' : 'My Loans')) ?></h1>
        <p class="subtitle"><?= $isAdvances ? 'Advance requests and recovery status' : 'Active loans and installment summary' ?></p>
    </div>
    <a href="/employee/<?= $isAdvances ? 'advances' : 'loans' ?>/create" class="btn btn-primary btn-sm">
        <i data-lucide="plus" class="me-1" style="width:14px;height:14px"></i>
        <?= $isAdvances ? 'Apply for Advance' : 'Apply for Loan' ?>
    </a>
</div>

<div class="card ems-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <?php if ($isAdvances): ?>
                        <th>Number</th>
                        <th>Amount</th>
                        <th>Remaining</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Reason</th>
                    <?php else: ?>
                        <th>Number</th>
                        <th>Type</th>
                        <th>Principal</th>
                        <th>EMI</th>
                        <th>Remaining</th>
                        <th>Status</th>
                        <th>Start</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="7">
                        <div class="empty-state py-4 mb-0">
                            No <?= $isAdvances ? 'salary advances' : 'loans' ?> found.
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <?php if ($isAdvances): ?>
                        <td class="fw-semibold"><?= e($r['advance_number']) ?></td>
                        <td><?= e(format_money($r['amount'])) ?></td>
                        <td><?= e(format_money($r['remaining_amount'])) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td><?= e(format_date($r['request_date'])) ?></td>
                        <td class="small text-muted"><?= e($r['reason'] ?? '—') ?></td>
                    <?php else: ?>
                        <td class="fw-semibold"><?= e($r['loan_number']) ?></td>
                        <td><?= e(ucfirst((string) $r['loan_type'])) ?></td>
                        <td><?= e(format_money($r['principal_amount'])) ?></td>
                        <td><?= e(format_money($r['installment_amount'])) ?></td>
                        <td><?= e(format_money($r['remaining_amount'])) ?></td>
                        <td><?= status_badge($r['status']) ?></td>
                        <td><?= e(format_date($r['start_date'] ?? null)) ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
