<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1"><?= e($title ?? 'Employee Documents') ?></h1>
            <p class="text-muted small mb-0">Upload and manage employee HR documents</p>
        </div>
    </div>

    <?php include config('app.paths.views') . '/partials/alerts.php'; ?>

    <div class="row g-3">
        <?php if (can('documents.manage')): ?>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-2"><strong>Upload Document</strong></div>
                <div class="card-body">
                    <form method="post" action="/admin/documents" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-2">
                            <label class="form-label small" for="employee_id">Employee</label>
                            <select name="employee_id" class="form-select form-select-sm" required id="employee_id">
                                <option value="">Select…</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= (int) $emp['id'] ?>">
                                        <?= e(trim($emp['first_name'] . ' ' . $emp['last_name']) . ' (' . $emp['employee_code'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="title">Title</label>
                            <input type="text" name="title" class="form-control form-control-sm" required id="title">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="document_type">Type</label>
                            <select name="document_type" class="form-select form-select-sm" id="document_type">
                                <option value="employment_contract">Employment Contract</option>
                                <option value="national_id">National ID</option>
                                <option value="resume">Resume</option>
                                <option value="certificate">Certificate</option>
                                <option value="experience_letter">Experience Letter</option>
                                <option value="warning_letter">Warning Letter</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="issue_date">Issue Date</label>
                            <input type="date" name="issue_date" class="form-control form-control-sm" id="issue_date">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="expiry_date">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control form-control-sm" id="expiry_date">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small" for="document">File</label>
                            <input type="file" name="document" class="form-control form-control-sm" required id="document">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small" for="description">Description</label>
                            <textarea name="description" class="form-control form-control-sm" rows="2" id="description"></textarea>
                        </div>
                        <button class="btn btn-primary btn-sm">Upload</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="<?= can('documents.manage') ? 'col-lg-8' : 'col-12' ?>">
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th>Title</th>
                                <th>Type</th>
                                <th>Issued</th>
                                <th>Expires</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No documents found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= e($r['employee_name']) ?></div>
                                    <div class="small text-muted"><?= e($r['employee_code'] ?? '') ?></div>
                                </td>
                                <td><?= e($r['title']) ?></td>
                                <td><?= e(ucwords(str_replace('_', ' ', (string) $r['document_type']))) ?></td>
                                <td><?= e(format_date($r['issue_date'] ?? null)) ?></td>
                                <td><?= e(format_date($r['expiry_date'] ?? null)) ?></td>
                                <td class="text-end text-nowrap">
                                    <?php
                                    $actions = [
                                        [
                                            'type' => 'link',
                                            'icon' => 'eye',
                                            'label' => 'View document',
                                            'href' => '/files/document/' . (int) $r['id'],
                                            'attrs' => ['target' => '_blank', 'rel' => 'noopener'],
                                        ],
                                        ['type' => 'link', 'icon' => 'download', 'label' => 'Download document', 'href' => '/files/document/' . (int) $r['id'] . '?download=1'],
                                    ];
                                    if (can('documents.manage')) {
                                        $actions[] = [
                                            'type' => 'form',
                                            'icon' => 'trash-2',
                                            'label' => 'Delete',
                                            'variant' => 'danger',
                                            'method' => 'POST',
                                            'action' => '/admin/documents/' . (int) $r['id'] . '/delete',
                                            'confirm' => ($r['document_type'] ?? '') === 'employment_agreement'
                                                ? 'Delete this signed employment agreement? The employee will be required to review and sign it again.'
                                                : 'Delete this document?',
                                        ];
                                    }
                                    include config('app.paths.views') . '/partials/table-actions.php';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
