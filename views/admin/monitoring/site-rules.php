<?php /** @var array $rules */ /** @var array $exceptions */ /** @var array $schedules */ /** @var array $companies */ /** @var array $employees */ /** @var int|null $companyId */ ?>
<div class="page-header">
    <div><h1>Website rules & schedules</h1><p class="subtitle">Decide what counts as productive, neutral, distracting, or blocked during scheduled hours.</p></div>
</div>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header">Add website rule</div>
            <div class="card-body">
                <form method="post" action="/admin/monitoring/site-rules" class="row g-3">
                    <?= csrf_field() ?>
                    <?php if (count($companies) > 1): ?>
                        <div class="col-12"><label class="form-label">Company</label><select name="company_id" class="form-select"><?php foreach ($companies as $company): ?><option value="<?= (int) $company['id'] ?>" <?= (int) $company['id'] === (int) $companyId ? 'selected' : '' ?>><?= e($company['name']) ?></option><?php endforeach; ?></select></div>
                    <?php else: ?><input type="hidden" name="company_id" value="<?= (int) $companyId ?>"><?php endif; ?>
                    <div class="col-12"><label class="form-label">Domain</label><input class="form-control" name="domain_pattern" placeholder="youtube.com" required><div class="form-text">Store domains only; pages and query strings are never retained.</div></div>
                    <div class="col-md-6"><label class="form-label">Category</label><select class="form-select" name="category"><option value="productive">Productive</option><option value="neutral" selected>Neutral</option><option value="distracting">Distracting</option><option value="blocked">Blocked</option></select></div>
                    <div class="col-md-6"><label class="form-label">Context</label><select class="form-select" name="context"><option value="any">Any use</option><option value="training">Training</option><option value="research">Research</option></select></div>
                    <div class="col-md-6"><label class="form-label">Agent action</label><select class="form-select" name="action"><option value="allow">Allow</option><option value="notify">Notify</option><option value="warn" selected>Warn</option><option value="block">Block</option></select></div>
                    <div class="col-md-6"><label class="form-label">Threshold (seconds)</label><input class="form-control" type="number" name="threshold_seconds" min="0" max="86400" value="300"></div>
                    <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="siteRuleActive"><label class="form-check-label" for="siteRuleActive">Active immediately</label></div></div>
                    <div class="col-12"><button class="btn btn-primary">Save rule</button></div>
                </form>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Add work schedule</div>
            <div class="card-body">
                <form method="post" action="/admin/monitoring/site-rules/schedules" class="row g-3">
                    <?= csrf_field() ?><input type="hidden" name="company_id" value="<?= (int) $companyId ?>">
                    <div class="col-12"><label class="form-label">Schedule name</label><input class="form-control" name="name" value="Standard work hours" required></div>
                    <div class="col-6"><label class="form-label">Start</label><input class="form-control" type="time" name="start_time" value="09:00" required></div>
                    <div class="col-6"><label class="form-label">End</label><input class="form-control" type="time" name="end_time" value="18:00" required></div>
                    <div class="col-12"><label class="form-label">Break window (optional)</label><div class="row g-2"><div class="col-6"><input class="form-control" type="time" name="break_start" value="13:00" aria-label="Break starts"></div><div class="col-6"><input class="form-control" type="time" name="break_end" value="14:00" aria-label="Break ends"></div></div></div>
                    <div class="col-12"><label class="form-label">Work days</label><div class="d-flex flex-wrap gap-3"><?php foreach (['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat','sun'=>'Sun'] as $value => $label): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="work_days[]" value="<?= $value ?>" <?= in_array($value, ['mon','tue','wed','thu','fri'], true) ? 'checked' : '' ?>> <?= $label ?></label><?php endforeach; ?></div></div>
                    <div class="col-12"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="Asia/Karachi"></div>
                    <div class="col-12"><button class="btn btn-soft">Create schedule</button></div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Assign schedule</div>
            <div class="card-body">
                <form method="post" action="/admin/monitoring/site-rules/schedules/assign" class="row g-2">
                    <?= csrf_field() ?><input type="hidden" name="company_id" value="<?= (int) $companyId ?>">
                    <div class="col-6"><select name="schedule_id" class="form-select" required><option value="">Select schedule</option><?php foreach ($schedules as $schedule): ?><option value="<?= (int) $schedule['id'] ?>"><?= e($schedule['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-6"><select name="employee_id" class="form-select" required><option value="">Select employee</option><?php foreach ($employees as $employee): ?><option value="<?= (int) $employee['id'] ?>"><?= e(trim($employee['first_name'] . ' ' . $employee['last_name'])) ?></option><?php endforeach; ?></select></div>
                    <div class="col-12"><button class="btn btn-soft">Assign to employee</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card mb-3"><div class="card-header">Active rules</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Domain</th><th>Category</th><th>Action</th><th>Context</th><th>Threshold</th></tr></thead><tbody><?php foreach ($rules as $rule): ?><tr><td><strong><?= e($rule['domain_pattern']) ?></strong></td><td><?= status_badge($rule['category']) ?></td><td><?= e(ucfirst($rule['action'])) ?></td><td><?= e(ucfirst($rule['context'])) ?></td><td><?= e((string) $rule['threshold_seconds']) ?> sec</td></tr><?php endforeach; ?><?php if (empty($rules)): ?><tr><td colspan="5" class="text-muted text-center py-4">No rules yet. Add YouTube or other work-policy domains.</td></tr><?php endif; ?></tbody></table></div></div>
        <div class="card mb-3"><div class="card-header">Pending exceptions</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Employee</th><th>Domain</th><th>Reason</th><th></th></tr></thead><tbody><?php foreach ($exceptions as $exception): ?><tr><td><?= e(trim($exception['first_name'] . ' ' . $exception['last_name'])) ?></td><td><?= e($exception['domain_pattern']) ?></td><td><?= e($exception['reason']) ?></td><td class="text-end text-nowrap"><form method="post" action="/admin/monitoring/site-rules/exceptions/<?= (int) $exception['id'] ?>/review" class="d-inline"><?= csrf_field() ?><input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-success">Approve</button></form> <form method="post" action="/admin/monitoring/site-rules/exceptions/<?= (int) $exception['id'] ?>/review" class="d-inline"><?= csrf_field() ?><input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-soft">Reject</button></form></td></tr><?php endforeach; ?><?php if (empty($exceptions)): ?><tr><td colspan="4" class="text-muted text-center py-4">No pending exceptions.</td></tr><?php endif; ?></tbody></table></div></div>
        <div class="card"><div class="card-header">Schedules</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Name</th><th>Hours</th><th>Break</th><th>Assigned</th></tr></thead><tbody><?php foreach ($schedules as $schedule): ?><?php $breaks = json_decode((string) ($schedule['break_windows'] ?? '[]'), true) ?: []; ?><tr><td><strong><?= e($schedule['name']) ?></strong><div class="small text-muted"><?= e((string) $schedule['timezone']) ?></div></td><td><?= e(substr($schedule['start_time'], 0, 5) . ' – ' . substr($schedule['end_time'], 0, 5)) ?></td><td><?= !empty($breaks[0]) ? e(substr((string) ($breaks[0]['start'] ?? ''), 0, 5) . ' – ' . substr((string) ($breaks[0]['end'] ?? ''), 0, 5)) : '—' ?></td><td><?= e((string) $schedule['assigned_employees']) ?></td></tr><?php endforeach; ?><?php if (empty($schedules)): ?><tr><td colspan="4" class="text-muted text-center py-4">No work schedules configured.</td></tr><?php endif; ?></tbody></table></div></div>
    </div>
</div>
