<?php
/** @var array $records */
/** @var array $companies */
/** @var array $branches */
/** @var array $departments */
/** @var array $intervals */
?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3">Monitoring Policies</h1>
    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="table-responsive border rounded-3 ems-surface monitoring-panel">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Name</th><th>Mode</th><th>Interval</th><th>Version</th><th>Active</th></tr></thead>
                    <tbody>
                    <?php foreach ($records['data'] as $row): ?>
                        <tr>
                            <td><?= e($row['name']) ?><?= !empty($row['is_system_default']) ? ' <span class="badge text-bg-secondary">Default</span>' : '' ?></td>
                            <td><?= e($row['mode']) ?></td>
                            <td><?= e((string) $row['screenshot_interval_minutes']) ?> min</td>
                            <td>v<?= e((string) $row['version']) ?></td>
                            <td><?= !empty($row['is_active']) ? 'Yes' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted mt-2">Exceptional recording remains deferred and disabled by default.</p>
        </div>
        <div class="col-lg-5">
            <div class="border rounded-3 ems-surface monitoring-panel p-3 mb-3">
                <h2 class="h6">Create policy</h2>
                <form method="post" action="/admin/monitoring/policies"><?= csrf_field() ?>
                    <div class="mb-2"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" required></div>
                    <?php if (!empty($companies)): ?>
                    <div class="mb-2"><label class="form-label small">Company</label>
                        <select name="company_id" class="form-select form-select-sm">
                            <?php foreach ($companies as $c): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="mb-2"><label class="form-label small">Screenshot interval</label>
                        <select name="screenshot_interval_minutes" class="form-select form-select-sm">
                            <?php foreach ($intervals as $i): ?><option value="<?= (int) $i ?>" <?= (int)$i === 10 ? 'selected' : '' ?>><?= (int) $i ?> minutes</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2"><label class="form-label small">Notice text</label><textarea name="notice_text" class="form-control form-control-sm" rows="4"></textarea></div>
                    <input type="hidden" name="mode" value="activity_screenshots">
                    <button class="btn btn-sm btn-primary">Create</button>
                </form>
            </div>
            <div class="border rounded-3 ems-surface monitoring-panel p-3">
                <h2 class="h6">Assign policy</h2>
                <form method="post" action="/admin/monitoring/policies/assign"><?= csrf_field() ?>
                    <div class="mb-2"><label class="form-label small">Policy ID</label><input name="policy_id" type="number" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Company ID</label><input name="company_id" type="number" class="form-control form-control-sm" required></div>
                    <div class="mb-2"><label class="form-label small">Branch ID (optional)</label><input name="branch_id" type="number" class="form-control form-control-sm"></div>
                    <div class="mb-2"><label class="form-label small">Department ID (optional)</label><input name="department_id" type="number" class="form-control form-control-sm"></div>
                    <div class="mb-2"><label class="form-label small">Employee ID (optional)</label><input name="employee_id" type="number" class="form-control form-control-sm"></div>
                    <button class="btn btn-sm btn-outline-primary">Assign</button>
                </form>
            </div>
        </div>
    </div>
</div>
