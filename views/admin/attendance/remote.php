<?php
/** @var string $mode 'summary' | 'employee' */
/** @var array  $byEmployee  (summary mode) */
/** @var array  $records     (employee mode) */
/** @var array  $employee    (employee mode) */
/** @var int    $employeeId  (employee mode) */
/** @var array  $filters */
/** @var array  $departments */
/** @var array  $branches */

$mode       = $mode ?? 'summary';
$employeeId = $employeeId ?? 0;
$archived   = $archived ?? 0;
$filterBase = array_filter($filters);
$activeBase = array_diff_key($filterBase, ['archived' => 1]);
$archBase   = $activeBase + ['archived' => 1];
?>

<!-- Page header -->
<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <?php if ($mode === 'employee' && !empty($employee)): ?>
            <h1><?= e(trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))) ?> — Remote Work</h1>
            <p class="subtitle"><?= e($employee['employee_code'] ?? '') ?> · Individual remote attendance records</p>
        <?php else: ?>
            <h1>Remote Work</h1>
            <p class="subtitle">Employees working outside the office radius, grouped by person.</p>
        <?php endif; ?>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($mode === 'employee'): ?>
            <a href="/admin/attendance/remote?<?= e(http_build_query($filterBase)) ?>" class="btn btn-soft btn-sm">
                <i data-lucide="arrow-left" style="width:14px;height:14px" class="me-1"></i>All Employees
            </a>
        <?php else: ?>
            <a href="/admin/attendance" class="btn btn-soft btn-sm">
                <i data-lucide="arrow-left" style="width:14px;height:14px" class="me-1"></i>All Attendance
            </a>
        <?php endif; ?>
        <!-- Active / Archived toggle -->
        <div class="btn-group btn-group-sm">
            <a href="?<?= e(http_build_query(array_merge($activeBase, $employeeId ? ['employee_id' => $employeeId] : []))) ?>"
               class="btn <?= !$archived ? 'btn-primary' : 'btn-soft' ?>">Active</a>
            <?php if (can('attendance.view_archived')): ?>
            <a href="?<?= e(http_build_query(array_merge($archBase, $employeeId ? ['employee_id' => $employeeId] : []))) ?>"
               class="btn <?= $archived ? 'btn-warning' : 'btn-soft' ?>">Archived</a>
            <?php endif; ?>
        </div>
        <?php if (can('attendance.export')): ?>
        <a href="/admin/attendance/export?status=remote&<?= e(http_build_query($filterBase + ($employeeId ? ['employee_id' => $employeeId] : []))) ?>" class="btn btn-soft btn-sm">
            <i data-lucide="download" style="width:14px;height:14px" class="me-1"></i>Export CSV
        </a>
        <?php endif; ?>
    </div>
</div>

<?php include config('app.paths.views') . '/partials/alerts.php'; ?>

<!-- Filter bar -->
<div class="card shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <?php if ($employeeId): ?>
                <input type="hidden" name="employee_id" value="<?= $employeeId ?>">
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label small mb-0">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" value="<?= e($filters['q'] ?? '') ?>" placeholder="Name / Code…">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? date('Y-m-01')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Department</label>
                <select name="department_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= (int)$dept['id'] ?>" <?= ((int)($filters['department_id'] ?? 0)) === (int)$dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0">Branch</label>
                <select name="branch_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($branches as $br): ?>
                        <option value="<?= (int)$br['id'] ?>" <?= ((int)($filters['branch_id'] ?? 0)) === (int)$br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button class="btn btn-primary btn-sm w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<?php if ($mode === 'summary'): ?>
<!-- ===================== SUMMARY VIEW ===================== -->
<?php if ($archived): ?>
<div class="alert alert-warning py-2 small mb-3">
    <i data-lucide="archive" style="width:14px;height:14px" class="me-1"></i>
    Showing <strong>archived</strong> remote work records.
    <a href="?<?= e(http_build_query($activeBase)) ?>" class="ms-2">View active</a>
</div>
<?php endif; ?>
<?php
    $totalDays   = array_sum(array_column($byEmployee ?? [], 'remote_days'));
    $totalMins   = array_sum(array_column($byEmployee ?? [], 'total_work_minutes'));
    $totalOtMins = array_sum(array_column($byEmployee ?? [], 'total_overtime_minutes'));
    $empCount    = count($byEmployee ?? []);
?>
<!-- Stats -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm"><div class="card-body py-2 text-center">
            <div class="small text-muted">Employees</div>
            <div class="fw-bold text-info fs-5"><?= $empCount ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm"><div class="card-body py-2 text-center">
            <div class="small text-muted">Total Remote Days</div>
            <div class="fw-bold text-primary fs-5"><?= $totalDays ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm"><div class="card-body py-2 text-center">
            <div class="small text-muted">Total Work Hours</div>
            <div class="fw-bold text-success fs-5"><?php
                printf('%dh %dm', intdiv((int)$totalMins, 60), (int)$totalMins % 60);
            ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm"><div class="card-body py-2 text-center">
            <div class="small text-muted">Total Overtime</div>
            <div class="fw-bold text-warning fs-5"><?php
                printf('%dh %dm', intdiv((int)$totalOtMins, 60), (int)$totalOtMins % 60);
            ?></div>
        </div></div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm mb-0 table-hover">
            <thead class="table-light">
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Branch</th>
                    <th class="text-center">Remote Days</th>
                    <th class="text-center">Work Hours</th>
                    <th class="text-center">Overtime</th>
                    <th>Last Remote</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($byEmployee)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No remote work records for this period.</td></tr>
            <?php else: ?>
                <?php foreach ($byEmployee as $row):
                    $wm = (int)$row['total_work_minutes'];
                    $ot = (int)$row['total_overtime_minutes'];
                ?>
                <tr>
                    <td>
                        <div class="fw-semibold"><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></div>
                        <div class="text-muted small"><?= e($row['employee_code'] ?? '') ?></div>
                    </td>
                    <td class="small align-middle"><?= e($row['department_name'] ?? '—') ?></td>
                    <td class="small align-middle"><?= e($row['branch_name'] ?? '—') ?></td>
                    <td class="text-center align-middle">
                        <span class="badge text-bg-info"><?= (int)$row['remote_days'] ?></span>
                    </td>
                    <td class="text-center align-middle text-success fw-semibold">
                        <?= $wm > 0 ? sprintf('%dh %dm', intdiv($wm, 60), $wm % 60) : '—' ?>
                    </td>
                    <td class="text-center align-middle <?= $ot > 0 ? 'text-warning' : 'text-muted' ?>">
                        <?= $ot > 0 ? sprintf('%dh %dm', intdiv($ot, 60), $ot % 60) : '—' ?>
                    </td>
                    <td class="small align-middle"><?= e(format_date($row['last_remote_date'] ?? null)) ?></td>
                    <td class="align-middle">
                        <a href="/admin/attendance/remote?employee_id=<?= (int)$row['employee_id'] ?>&<?= e(http_build_query($filterBase)) ?>"
                           class="btn btn-xs btn-soft">
                            <i data-lucide="eye" style="width:12px;height:12px" class="me-1"></i>Details
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- ===================== EMPLOYEE DRILLDOWN VIEW ===================== -->
<?php if ($archived): ?>
<div class="alert alert-warning py-2 small mb-3">
    <i data-lucide="archive" style="width:14px;height:14px" class="me-1"></i>
    Showing <strong>archived</strong> remote work records for this employee.
    <?php if (can('attendance.view_archived')): ?>
        <a href="?employee_id=<?= $employeeId ?>&<?= e(http_build_query($activeBase)) ?>" class="ms-2">View active</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php
    $data      = $records['data'] ?? [];
    $totalMins = array_sum(array_column($data, 'work_minutes'));
    $otMins    = array_sum(array_column($data, 'overtime_minutes'));
?>
<!-- Employee stats -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card shadow-sm"><div class="card-body py-2 text-center">
            <div class="small text-muted">Remote Days</div>
            <div class="fw-bold text-info fs-5"><?= number_format((int)($records['total'] ?? 0)) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm"><div class="card-body py-2 text-center">
            <div class="small text-muted">Work Hours (this page)</div>
            <div class="fw-bold text-success fs-5"><?= sprintf('%dh %dm', intdiv((int)$totalMins, 60), (int)$totalMins % 60) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm"><div class="card-body py-2 text-center">
            <div class="small text-muted">Overtime (this page)</div>
            <div class="fw-bold text-warning fs-5"><?= sprintf('%dh %dm', intdiv((int)$otMins, 60), (int)$otMins % 60) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card shadow-sm"><div class="card-body py-2 text-center">
            <div class="small text-muted">Avg Work / Day</div>
            <div class="fw-bold text-primary fs-5"><?php
                $cnt = count($data);
                if ($cnt > 0) {
                    $avg = (int)$totalMins / $cnt;
                    printf('%dh %dm', intdiv((int)$avg, 60), (int)$avg % 60);
                } else { echo '—'; }
            ?></div>
        </div></div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Work</th>
                    <th>Late</th>
                    <th>Overtime</th>
                    <th>Shift</th>
                    <th>Verification</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($data)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">No remote records for this employee in the selected period.</td></tr>
            <?php else: ?>
                <?php foreach ($data as $row):
                    $wm   = (int)($row['work_minutes'] ?? 0);
                    $late = (int)($row['late_minutes'] ?? 0);
                    $ot   = (int)($row['overtime_minutes'] ?? 0);
                    $isRowArchived = !empty($row['deleted_at']);
                ?>
                <tr class="<?= $isRowArchived ? 'table-warning' : '' ?>">
                    <td class="fw-semibold text-nowrap"><?= e(format_date($row['attendance_date'])) ?></td>
                    <td class="text-nowrap small"><?= e(format_datetime($row['check_in_at'] ?? null)) ?></td>
                    <td class="text-nowrap small"><?= $row['check_out_at'] ? e(format_datetime($row['check_out_at'])) : '<span class="text-muted">—</span>' ?></td>
                    <td class="text-nowrap <?= $wm > 0 ? 'text-success fw-semibold' : 'text-muted' ?>">
                        <?= $wm > 0 ? sprintf('%dh %dm', intdiv($wm, 60), $wm % 60) : '—' ?>
                    </td>
                    <td class="text-nowrap <?= $late > 0 ? 'text-warning' : 'text-muted' ?>">
                        <?= $late > 0 ? $late . ' min' : '—' ?>
                    </td>
                    <td class="text-nowrap <?= $ot > 0 ? 'text-info' : 'text-muted' ?>">
                        <?= $ot > 0 ? sprintf('%dh %dm', intdiv($ot, 60), $ot % 60) : '—' ?>
                    </td>
                    <td class="small"><?= e($row['shift_name'] ?? '—') ?></td>
                    <td><?= status_badge($row['verification_status'] ?? 'pending') ?></td>
                    <td class="text-nowrap">
                        <a href="/admin/attendance/<?= (int)$row['id'] ?>" class="btn btn-xs btn-soft">View</a>
                        <?php if ($isRowArchived && can('attendance.view_archived')): ?>
                        <form method="POST" action="/admin/attendance/<?= (int)$row['id'] ?>/restore" class="d-inline ms-1">
                            <?= csrf_field() ?>
                            <button class="btn btn-xs btn-success" title="Restore">Restore</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if (($records['last_page'] ?? 1) > 1): ?>
    <div class="card-footer py-2">
        <nav><ul class="pagination pagination-sm mb-0">
            <?php for ($p = 1; $p <= $records['last_page']; $p++): ?>
            <li class="page-item <?= $p === ($records['current_page'] ?? 1) ? 'active' : '' ?>">
                <a class="page-link" href="?<?= e(http_build_query(array_merge($filterBase, ['employee_id' => $employeeId, 'page' => $p]))) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
