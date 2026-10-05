<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="page-title h4 mb-0">Payroll Periods</h1>
        <p class="text-muted small mb-0">Manage monthly payroll cycles</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('payroll.view_archived')): ?>
            <a href="/admin/payroll/archived" class="btn btn-outline-secondary btn-sm">Archived</a>
        <?php endif; ?>
        <?php if (can('payroll.process')): ?>
            <a href="/admin/payroll/create" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> New Period</a>
        <?php endif; ?>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>
<?php $savedModule='payroll';$currentFilters=array_merge($filters,['company_id'=>$companyId]);include config('app.paths.views').'/partials/saved-filters.php'; ?>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-0" for="status">Status</label>
                <select name="status" class="form-select form-select-sm" id="status">
                    <option value="">All</option>
                    <?php foreach (['draft','processing','calculated','approved','locked','paid','reopened','cancelled'] as $s): ?>
                    <option value="<?= e($s) ?>" <?= ($filters['status'] ?? '') === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-secondary btn-sm w-100">Filter</button></div>
        </form>
    </div>
</div>

<div class="card card-compact">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr><th>Period</th><th>Start</th><th>End</th><th>Employees</th><th>Gross</th><th>Net</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (empty($periods['data'])): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No payroll periods found.</td></tr>
            <?php else: foreach ($periods['data'] ?? [] as $period): ?>
                <tr>
                    <td><a href="/admin/payroll/<?= (int) $period['id'] ?>"><?= e($period['name']) ?></a></td>
                    <td><?= format_date($period['start_date']) ?></td>
                    <td><?= format_date($period['end_date']) ?></td>
                    <td><?= (int) $period['total_employees'] ?></td>
                    <td><?= format_money($period['total_gross']) ?></td>
                    <td><?= format_money($period['total_net']) ?></td>
                    <td><?= status_badge($period['status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $pid = (int) $period['id'];
                        $actions = [
                            ['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/admin/payroll/' . $pid],
                        ];
                        if (can('payroll.archive') && in_array($period['status'], ['draft', 'cancelled', 'reopened', 'calculated'], true)) {
                            $actions[] = [
                                'type' => 'link',
                                'icon' => 'archive',
                                'label' => 'Archive',
                                'variant' => 'danger',
                                'href' => '/admin/payroll/' . $pid . '#archive',
                            ];
                        }
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$paginator = $periods;
$baseUrl = '/admin/payroll?' . http_build_query(array_filter($filters ?? []));
include config('app.paths.views') . '/partials/pagination.php';
?>
