<?php /** @var array $period */ /** @var array $records */ /** @var bool $isArchived */ ?>
<?php $isArchived = $isArchived ?? !empty($period['deleted_at']); ?>
<div class="page-header d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="page-title h4 mb-0"><?= e($period['name']) ?></h1>
        <p class="text-muted small mb-0">
            <?= format_date($period['start_date']) ?> — <?= format_date($period['end_date']) ?>
            <?= $isArchived ? ' · <span class="badge text-bg-secondary">Archived</span>' : '' ?>
        </p>
    </div>
    <div class="d-flex flex-wrap gap-1">
        <?php if (!$isArchived && can('payroll.process') && in_array($period['status'], ['draft','reopened','calculated'], true)): ?>
            <form method="POST" action="/admin/payroll/<?= (int) $period['id'] ?>/process" class="d-inline" data-confirm="Process payroll for all eligible employees?">
                <?= csrf_field() ?>
                <button class="btn btn-primary btn-sm">Process</button>
            </form>
        <?php endif; ?>
        <?php if (!$isArchived && can('payroll.approve') && $period['status'] === 'calculated'): ?>
            <a class="btn btn-success btn-sm" href="/admin/payroll/<?= (int) $period['id'] ?>/variance"><i data-lucide="scan-search" class="me-1" style="width:14px;height:14px"></i>Review &amp; Approve</a>
        <?php endif; ?>
        <?php if (!$isArchived && can('payroll.lock') && in_array($period['status'], ['approved','calculated'], true)): ?>
            <form method="POST" action="/admin/payroll/<?= (int) $period['id'] ?>/lock" class="d-inline" data-confirm="Lock this period? Further edits will be restricted.">
                <?= csrf_field() ?><button class="btn btn-dark btn-sm">Lock</button>
            </form>
        <?php endif; ?>
        <?php if (!$isArchived && can('payroll.reopen') && in_array($period['status'], ['approved','locked','calculated'], true)): ?>
            <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reopenModal">Reopen</button>
        <?php endif; ?>
        <?php if (!$isArchived && can('payroll.cancel') && !in_array($period['status'], ['locked','paid','cancelled'], true)): ?>
            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#cancelModal">Cancel</button>
        <?php endif; ?>
        <?php if (!$isArchived && can('payslips.generate')): ?>
            <form method="POST" action="/admin/payroll/<?= (int) $period['id'] ?>/payslips" class="d-inline">
                <?= csrf_field() ?><button class="btn btn-outline-primary btn-sm">Generate Payslips</button>
            </form>
        <?php endif; ?>
        <?php if (!$isArchived && can('payroll.archive') && in_array($period['status'], ['draft','cancelled','reopened','calculated'], true)): ?>
            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#archiveModal" id="archive">Archive</button>
        <?php endif; ?>
        <?php if ($isArchived && can('payroll.restore')): ?>
            <form method="POST" action="/admin/payroll/<?= (int) $period['id'] ?>/restore" class="d-inline" data-confirm="Restore this payroll period?">
                <?= csrf_field() ?><button class="btn btn-success btn-sm">Restore</button>
            </form>
        <?php endif; ?>
        <a href="/admin/payroll" class="btn btn-light btn-sm">Back</a>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Employees</div><div class="stat-value"><?= (int) $period['total_employees'] ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Gross</div><div class="stat-value stat-value-sm"><?= format_money($period['total_gross']) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Deductions</div><div class="stat-value stat-value-sm"><?= format_money($period['total_deductions']) ?></div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Net</div><div class="stat-value stat-value-sm"><?= format_money($period['total_net']) ?></div></div></div>
</div>

<div class="card card-compact">
    <div class="card-header py-2"><strong>Employee Records</strong></div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Code</th><th>Name</th><th>Department</th><th>Present</th><th>Gross</th><th>Net</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($records as $r): ?>
                <tr>
                    <td><?= e($r['employee_code']) ?></td>
                    <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                    <td><?= e($r['department_name'] ?? '—') ?></td>
                    <td><?= e((string) $r['present_days']) ?>/<?= e((string) $r['working_days']) ?></td>
                    <td><?= format_money($r['gross_earnings'], $r['currency'] ?? null) ?></td>
                    <td><?= format_money($r['net_salary'], $r['currency'] ?? null) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $actions = [
                            ['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/admin/payroll/' . (int) $period['id'] . '/records/' . (int) $r['id']],
                        ];
                        if (!$isArchived && can('payroll.edit') && in_array($period['status'], ['draft', 'reopened', 'calculated'], true)) {
                            $actions[] = [
                                'type' => 'link',
                                'icon' => 'pencil',
                                'label' => 'Edit',
                                'variant' => 'warning',
                                'href' => '/admin/payroll/' . (int) $period['id'] . '/records/' . (int) $r['id'] . '/edit',
                            ];
                        }
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!$isArchived): ?>
<div class="modal fade" id="reopenModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="/admin/payroll/<?= (int) $period['id'] ?>/reopen" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Reopen Payroll</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label" for="reason-3">Reason <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="3" required id="reason-3"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button><button class="btn btn-warning btn-sm">Reopen</button></div>
        </form>
    </div>
</div>
<div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="/admin/payroll/<?= (int) $period['id'] ?>/cancel" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Cancel Payroll</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label" for="reason-2">Reason <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="3" required id="reason-2"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button><button class="btn btn-danger btn-sm">Cancel Period</button></div>
        </form>
    </div>
</div>
<div class="modal fade" id="archiveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="/admin/payroll/<?= (int) $period['id'] ?>/archive" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header"><h5 class="modal-title">Archive Payroll</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="form-label" for="reason">Reason <span class="text-danger">*</span></label>
                <textarea name="reason" class="form-control" rows="3" required id="reason"></textarea>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button><button class="btn btn-secondary btn-sm">Archive</button></div>
        </form>
    </div>
</div>
<?php endif; ?>
