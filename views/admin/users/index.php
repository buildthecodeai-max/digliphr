<div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
        <h1>Users</h1>
        <p class="subtitle mb-0">Manage login accounts and role assignments</p>
    </div>
    <?php if (can('roles.manage')): ?>
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" action="/admin/users/reset-all-passwords"
                  data-confirm="Reset passwords for ALL non-super-admin users? Each user will get a new temporary password visible in their credentials.">
                <?= csrf_field() ?>
                <button class="btn btn-warning btn-sm">
                    <i data-lucide="key-round" style="width:14px;height:14px;vertical-align:-2px;"></i> Reset All Passwords
                </button>
            </form>
            <a href="/admin/users/create" class="btn btn-primary btn-sm">Add User</a>
        </div>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label form-label-sm" for="q">Search</label>
                <input type="search" name="q" class="form-control form-control-sm" placeholder="Name, email, username"
                       value="<?= e($filters['q'] ?? '') ?>" id="q">
            </div>
            <div class="col-md-3">
                <label class="form-label form-label-sm" for="status">Status</label>
                <select name="status" class="form-select form-select-sm" id="status">
                    <option value="">All</option>
                    <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-secondary btn-sm">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Roles</th>
                <th>Status</th>
                <th>Last login</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <?= e($row['name']) ?>
                        <?php if (!empty($row['is_super_admin'])): ?>
                            <span class="badge text-bg-dark ms-1">Super</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($row['email']) ?></td>
                    <td class="small"><?= e($row['role_names'] ?: '—') ?></td>
                    <td><?= status_badge((int) $row['is_active'] ? 'active' : 'inactive') ?></td>
                    <td class="small"><?= e(!empty($row['last_login_at']) ? format_datetime($row['last_login_at']) : '—') ?></td>
                    <td class="text-end text-nowrap">
                        <?php
                        $uid = (int) $row['id'];
                        $actions = [
                            [
                                'type' => 'modal',
                                'icon' => 'eye',
                                'label' => 'View Credentials',
                                'variant' => 'default',
                                'target' => '#userCredModal',
                                'attrs' => [
                                    'data-uid'           => $uid,
                                    'data-name'          => $row['name'],
                                    'data-email'         => $row['email'],
                                    'data-username'      => $row['username'] ?? '',
                                    'data-phone'         => $row['phone'] ?? '',
                                    'data-roles'         => $row['role_names'] ?: '—',
                                    'data-last-login'    => $row['last_login_at'] ?? '',
                                    'data-status'        => (int) $row['is_active'] ? 'Active' : 'Inactive',
                                    'data-temp-password' => $row['temp_password'] ?? '',
                                ],
                            ],
                            ['type' => 'link', 'icon' => 'pencil', 'label' => 'Edit', 'variant' => 'warning', 'href' => '/admin/users/' . $uid . '/edit'],
                            [
                                'type' => 'form',
                                'icon' => (int) $row['is_active'] ? 'user-x' : 'user-check',
                                'label' => (int) $row['is_active'] ? 'Deactivate' : 'Activate',
                                'variant' => (int) $row['is_active'] ? 'danger' : 'success',
                                'action' => '/admin/users/' . $uid . '/toggle-active',
                                'confirm' => (int) $row['is_active']
                                    ? 'Deactivate this user?'
                                    : 'Activate this user?',
                            ],
                            [
                                'type' => 'form',
                                'icon' => 'trash-2',
                                'label' => 'Delete user',
                                'variant' => 'danger',
                                'action' => '/admin/users/' . $uid . '/delete',
                                'confirm' => 'Permanently delete this user? This cannot be undone.',
                            ],
                        ];
                        include config('app.paths.views') . '/partials/table-actions.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- User Credentials Modal -->
<div class="modal fade" id="userCredModal" tabindex="-1" aria-labelledby="userCredModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userCredModalLabel">
                    <i data-lucide="shield-user" style="width:18px;height:18px;vertical-align:-3px;margin-right:6px;"></i>
                    User Login Credentials
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm mb-0" style="--bs-table-bg:transparent;">
                    <tbody>
                        <tr>
                            <th class="ps-3" style="width:38%;font-size:.75rem;color:var(--ems-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Full Name</th>
                            <td class="pe-3" id="ucName">—</td>
                        </tr>
                        <tr>
                            <th class="ps-3" style="font-size:.75rem;color:var(--ems-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Email (Login)</th>
                            <td class="pe-3">
                                <span id="ucEmail" class="font-monospace"></span>
                                <button type="button" class="btn btn-sm p-0 ms-2 text-muted" title="Copy email" onclick="navigator.clipboard.writeText(document.getElementById('ucEmail').textContent).then(()=>{this.title='Copied!'})">
                                    <i data-lucide="copy" style="width:13px;height:13px;"></i>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <th class="ps-3" style="font-size:.75rem;color:var(--ems-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Username</th>
                            <td class="pe-3">
                                <span id="ucUsername" class="font-monospace"></span>
                                <span id="ucUsernameEmpty" class="text-muted small d-none">Not set</span>
                            </td>
                        </tr>
                        <tr>
                            <th class="ps-3" style="font-size:.75rem;color:var(--ems-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Phone</th>
                            <td class="pe-3" id="ucPhone">—</td>
                        </tr>
                        <tr>
                            <th class="ps-3" style="font-size:.75rem;color:var(--ems-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Roles</th>
                            <td class="pe-3 small" id="ucRoles">—</td>
                        </tr>
                        <tr>
                            <th class="ps-3" style="font-size:.75rem;color:var(--ems-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Status</th>
                            <td class="pe-3" id="ucStatus">—</td>
                        </tr>
                        <tr>
                            <th class="ps-3" style="font-size:.75rem;color:var(--ems-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Last Login</th>
                            <td class="pe-3 small text-muted" id="ucLastLogin">—</td>
                        </tr>
                        <tr id="ucTempRow" class="d-none">
                            <th class="ps-3" style="font-size:.75rem;color:var(--ems-text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.04em;">Temp Password</th>
                            <td class="pe-3">
                                <span id="ucTempPass" class="font-monospace fw-bold" style="color:#e11d48;letter-spacing:.08em;"></span>
                                <button type="button" class="btn btn-sm p-0 ms-2 text-muted" title="Copy password" onclick="navigator.clipboard.writeText(document.getElementById('ucTempPass').textContent).then(()=>{this.title='Copied!'})">
                                    <i data-lucide="copy" style="width:13px;height:13px;"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="px-3 pb-3 pt-2">
                    <div class="rounded-3 p-2 small" style="background:var(--ems-soft-orange);color:var(--ems-text-secondary);">
                        <i data-lucide="lock" style="width:13px;height:13px;vertical-align:-2px;"></i>
                        <strong>Password:</strong> Stored as a secure hash. Temp password shown above only until the user logs in and changes it.
                    </div>
                </div>
            </div>
            <div class="modal-footer gap-2">
                <button type="button" class="btn btn-soft btn-sm" data-bs-dismiss="modal">Close</button>
                <a id="ucEditLink" href="#" class="btn btn-warning btn-sm">
                    <i data-lucide="pencil" style="width:14px;height:14px;vertical-align:-2px;"></i> Edit User
                </a>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById('userCredModal');
    if (!modal) return;
    modal.addEventListener('show.bs.modal', function (e) {
        var btn = e.relatedTarget;
        if (!btn) return;
        var d = btn.dataset;
        document.getElementById('ucName').textContent       = d.name       || '—';
        document.getElementById('ucEmail').textContent      = d.email      || '—';
        document.getElementById('ucPhone').textContent      = d.phone      || '—';
        document.getElementById('ucRoles').textContent      = d.roles      || '—';
        document.getElementById('ucLastLogin').textContent  = d.lastLogin  || '—';
        document.getElementById('ucStatus').textContent     = d.status     || '—';
        var un = document.getElementById('ucUsername');
        var unEmpty = document.getElementById('ucUsernameEmpty');
        if (d.username) {
            un.textContent = d.username;
            un.classList.remove('d-none');
            unEmpty.classList.add('d-none');
        } else {
            un.textContent = '';
            un.classList.add('d-none');
            unEmpty.classList.remove('d-none');
        }
        document.getElementById('ucEditLink').href = '/admin/users/' + d.uid + '/edit';
        var tmpRow  = document.getElementById('ucTempRow');
        var tmpPass = document.getElementById('ucTempPass');
        if (d.tempPassword) {
            tmpPass.textContent = d.tempPassword;
            tmpRow.classList.remove('d-none');
        } else {
            tmpPass.textContent = '';
            tmpRow.classList.add('d-none');
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
    });
})();
</script>
