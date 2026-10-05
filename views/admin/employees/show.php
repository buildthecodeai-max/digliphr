<?php /** @var array $employee */ ?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h4 mb-1"><?= e(trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''))) ?></h1>
            <p class="text-muted small mb-0"><?= e($employee['employee_code'] ?? '') ?> · <?= status_badge($employee['employment_status'] ?? 'active') ?></p>
        </div>
        <div class="d-flex gap-2">
            <?php if (can('employees.update')): ?>
                <a href="/admin/employees/<?= (int) $employee['id'] ?>/edit" class="btn btn-outline-secondary btn-sm">Edit</a>
            <?php endif; ?>
            <?php if (can('employees.delete')): ?>
                <form method="POST" action="/admin/employees/<?= (int) $employee['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Deactivate this employee?')">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline-danger btn-sm">Deactivate</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>

    <nav class="workspace-tabs mb-3" aria-label="Employee sections">
        <div class="workspace-tabs-scroll">
            <a class="workspace-tab is-active" href="/admin/employees/<?= (int) $employee['id'] ?>">Overview</a>
            <a class="workspace-tab" href="/admin/attendance?q=<?= urlencode((string) ($employee['employee_code'] ?? '')) ?>">Attendance</a>
            <a class="workspace-tab" href="/admin/leave?employee_id=<?= (int) $employee['id'] ?>">Leave</a>
            <a class="workspace-tab" href="/admin/payroll?employee_id=<?= (int) $employee['id'] ?>">Payroll</a>
            <a class="workspace-tab" href="/admin/documents?employee_id=<?= (int) $employee['id'] ?>">Documents</a>
            <?php if (can('attendance.device.view')): ?><a class="workspace-tab" href="/admin/employees/<?= (int) $employee['id'] ?>/attendance-device">Attendance Device</a><?php endif; ?>
        </div>
    </nav>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-2"><strong>Profile</strong></div>
                <div class="card-body">
                    <div class="row g-2 small">
                        <div class="col-md-4"><strong>Company:</strong> <?= e($employee['company_name'] ?? '—') ?></div>
                        <div class="col-md-4"><strong>Branch:</strong> <?= e($employee['branch_name'] ?? '—') ?></div>
                        <div class="col-md-4"><strong>Department:</strong> <?= e($employee['department_name'] ?? '—') ?></div>
                        <div class="col-md-4"><strong>Designation:</strong> <?= e($employee['designation_name'] ?? '—') ?></div>
                        <div class="col-md-4"><strong>Manager:</strong> <?= e($employee['manager_name'] ?? '—') ?></div>
                        <div class="col-md-4"><strong>Shift:</strong> <?= e($employee['shift_name'] ?? '—') ?></div>
                        <div class="col-md-4"><strong>Joining:</strong> <?= e(format_date($employee['joining_date'] ?? null)) ?></div>
                        <div class="col-md-4"><strong>Date of Birth:</strong> <?= e(format_date($employee['date_of_birth'] ?? null)) ?></div>
                        <div class="col-md-4"><strong>CNIC / ID Card:</strong> <?= e($employee['national_id'] ?: '—') ?></div>
                        <div class="col-md-4"><strong>Type:</strong> <?= e(ucwords(str_replace('_', ' ', (string) ($employee['employment_type'] ?? '—')))) ?></div>
                        <div class="col-md-4"><strong>Gender:</strong> <?= e(ucfirst((string) ($employee['gender'] ?? '—'))) ?></div>
                        <div class="col-md-4"><strong>Company Email:</strong> <?= e($employee['company_email'] ?: '—') ?></div>
                        <div class="col-md-4"><strong>Personal Email:</strong> <?= e($employee['personal_email'] ?: '—') ?></div>
                        <div class="col-md-4"><strong>Phone:</strong> <?= e($employee['phone'] ?: '—') ?></div>
                        <div class="col-md-4"><strong>Salary:</strong> <?= e(format_money($employee['basic_salary'] ?? 0)) ?></div>
                        <div class="col-md-4"><strong>Login:</strong> <?= !empty($employee['user_id']) ? e($employee['user_email'] ?? 'Linked') : 'Not created' ?></div>
                        <div class="col-md-8"><strong>Address:</strong> <?= e($employee['current_address'] ?: '—') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <?php
            $onboardingChecks = [
                ['label' => 'Basic identity', 'done' => !empty($employee['first_name']) && !empty($employee['employee_code']), 'href' => '/admin/employees/' . (int) $employee['id'] . '/edit'],
                ['label' => 'Company contact', 'done' => !empty($employee['company_email']) && !empty($employee['phone']), 'href' => '/admin/employees/' . (int) $employee['id'] . '/edit'],
                ['label' => 'Organization assignment', 'done' => !empty($employee['department_id']) && !empty($employee['branch_id']) && !empty($employee['designation_id']), 'href' => '/admin/employees/' . (int) $employee['id'] . '/edit'],
                ['label' => 'Shift assigned', 'done' => !empty($employee['shift_id']), 'href' => '/admin/employees/' . (int) $employee['id'] . '/edit'],
                ['label' => 'Employee login', 'done' => !empty($employee['user_id']), 'href' => '#create-login'],
            ];
            $onboardingDone = count(array_filter($onboardingChecks, fn ($item) => $item['done']));
            $onboardingPct = (int) round(($onboardingDone / max(1, count($onboardingChecks))) * 100);
            ?>
            <div class="card ems-card mb-3 onboarding-card">
                <div class="card-header d-flex justify-content-between align-items-center"><strong>Onboarding readiness</strong><span class="filter-chip"><?= $onboardingPct ?>%</span></div>
                <div class="card-body">
                    <div class="progress-soft mb-3"><span style="width: <?= $onboardingPct ?>%"></span></div>
                    <div class="small text-secondary mb-2"><?= $onboardingDone ?> of <?= count($onboardingChecks) ?> setup items complete</div>
                    <div class="onboarding-checklist">
                    <?php foreach ($onboardingChecks as $check): ?>
                        <a class="onboarding-check <?= $check['done'] ? 'is-done' : '' ?>" href="<?= e($check['href']) ?>">
                            <i data-lucide="<?= $check['done'] ? 'check-circle-2' : 'circle-alert' ?>"></i><span><?= e($check['label']) ?></span><i data-lucide="arrow-up-right" class="ms-auto onboarding-arrow"></i>
                        </a>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php if(can('employees.update')): ?><a class="btn btn-soft btn-sm w-100 mb-3" href="/admin/employees/<?= (int)$employee['id'] ?>/workflows"><i data-lucide="list-checks" class="me-1"></i>Open onboarding / offboarding workflow</a><?php endif; ?>
            <?php if (can('employees.update')): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white py-2"><strong>Change Status</strong></div>
                <div class="card-body">
                    <form method="POST" action="/admin/employees/<?= (int) $employee['id'] ?>/status">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <select name="employment_status" class="form-select form-select-sm">
                                <?php foreach (['active', 'probation', 'notice_period', 'suspended', 'terminated', 'resigned', 'retired', 'inactive'] as $s): ?>
                                    <option value="<?= $s ?>" <?= ($employee['employment_status'] ?? '') === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Notes"></textarea>
                        </div>
                        <button class="btn btn-warning btn-sm w-100">Update Status</button>
                    </form>
                </div>
            </div>

            <?php if (empty($employee['user_id'])): ?>
            <div class="card shadow-sm" id="create-login">
                <div class="card-header bg-white py-2"><strong>Create Login</strong></div>
                <div class="card-body">
                    <form method="POST" action="/admin/employees/<?= (int) $employee['id'] ?>/create-login">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small" for="email">Login Email</label>
                            <input type="email" name="email" class="form-control form-control-sm"
                                   value="<?= e($employee['company_email'] ?: ($employee['personal_email'] ?: '')) ?>"
                                   placeholder="name@company.com" required id="email">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="password">Temp Password</label>
                            <input type="text" name="password" class="form-control form-control-sm" value="Employee@123" id="password">
                        </div>
                        <button class="btn btn-primary btn-sm w-100">Create Employee Login</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
