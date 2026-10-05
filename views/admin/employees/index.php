<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2"><div><h1>Employees</h1><p class="subtitle">Search, manage, import, and coordinate employee lifecycle workflows.</p></div><div class="d-flex gap-2"><?php if(can('employees.create')): ?><a class="btn btn-soft btn-sm" href="/admin/employees/import<?= !empty($filters['company_id'])?'?company_id='.(int)$filters['company_id']:'' ?>"><i data-lucide="file-up" class="me-1"></i>Import</a><a href="/admin/employees/create" class="btn btn-primary btn-sm"><i data-lucide="user-plus" class="me-1"></i>Add employee</a><?php endif; ?></div></div>
<?php if(!empty($filters['company_id'])): $savedModule='employees';$currentFilters=$filters;include config('app.paths.views').'/partials/saved-filters.php';endif; ?>
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <?php if(count($companies??[])>1): ?><div class="col-md-2"><select name="company_id" class="form-select form-select-sm"><option value="">All companies</option><?php foreach($companies as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($filters['company_id']??0)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <div class="col-md-3"><input type="text" name="q" class="form-control form-control-sm" placeholder="Search..." value="<?= e($filters['q'] ?? '') ?>"></div>
            <div class="col-md-2"><select name="department_id" class="form-select form-select-sm"><option value="">All Departments</option><?php foreach ($departments as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (int)($filters['department_id'] ?? 0) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><select name="branch_id" class="form-select form-select-sm"><option value="">All Branches</option><?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)($filters['branch_id'] ?? 0) === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><select name="employment_status" class="form-select form-select-sm"><option value="">All Status</option><?php foreach (['active','probation','inactive','terminated'] as $s): ?><option value="<?= $s ?>" <?= ($filters['employment_status'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><button class="btn btn-secondary btn-sm">Filter</button></div>
        </form>
    </div>
</div>
<form method="GET" action="/admin/employees/export" id="employeeExportForm" class="card">
    <div class="card-header d-flex justify-content-between align-items-center py-2">
        <div class="small fw-semibold">Employee directory</div>
        <?php if (can('reports.export')): ?><button type="submit" class="btn btn-soft btn-sm" id="employeeExportBtn" disabled><i data-lucide="download" style="width:14px;height:14px"></i> Export selected <span id="employeeSelectedCount">0</span></button><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th style="width:36px"><input type="checkbox" class="form-check-input" id="employeeSelectAll" aria-label="Select all visible employees"></th><th>Code</th><th>Name</th><th>Department</th><th>Branch</th><th>Designation</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if(empty($employees)): ?><tr><td colspan="8"><div class="empty-state py-5"><i data-lucide="users"></i><strong>No employees match this view</strong><div class="small text-secondary mt-1">Clear filters, add an employee, or import a validated CSV.</div><?php if(can('employees.create')): ?><div class="mt-3"><a class="btn btn-primary btn-sm" href="/admin/employees/create">Add employee</a> <a class="btn btn-soft btn-sm" href="/admin/employees/import">Import CSV</a></div><?php endif; ?></div></td></tr><?php endif; ?>
            <?php foreach ($employees as $row): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input employee-select" name="ids[]" value="<?= (int) $row['id'] ?>" aria-label="Select employee <?= e($row['employee_code']) ?>"></td>
                    <td><?= e($row['employee_code']) ?></td>
                    <td><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></td>
                    <td><?= e($row['department_name'] ?? '—') ?></td>
                    <td><?= e($row['branch_name'] ?? '—') ?></td>
                    <td><?= e($row['designation_name'] ?? '—') ?></td>
                    <td><?= status_badge($row['employment_status']) ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $actions = [
                            ['type' => 'link', 'icon' => 'eye', 'label' => 'View', 'href' => '/admin/employees/' . (int) $row['id']],
                        ];
                        if (can('employees.update')) {
                            $actions[] = ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/employees/' . (int) $row['id'] . '/edit'];
                        }
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($paginator)): ?><div class="card-footer py-2"><?= paginate_links($paginator, '/admin/employees?' . http_build_query(array_filter($filters ?? []))) ?></div><?php endif; ?>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const boxes = Array.from(document.querySelectorAll('.employee-select'));
    const all = document.getElementById('employeeSelectAll');
    const btn = document.getElementById('employeeExportBtn');
    const count = document.getElementById('employeeSelectedCount');
    function sync() { const selected = boxes.filter(function (b) { return b.checked; }).length; if (count) count.textContent = selected; if (btn) btn.disabled = selected === 0; if (all) all.checked = boxes.length > 0 && selected === boxes.length; }
    boxes.forEach(function (box) { box.addEventListener('change', sync); });
    if (all) all.addEventListener('change', function () { boxes.forEach(function (b) { b.checked = all.checked; }); sync(); });
    sync();
});
</script>
