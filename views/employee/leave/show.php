<?php $lr = $leaveRequest; ?>
<div class="page-header">
    <div>
        <h1>Leave Request #<?= (int) $lr['id'] ?></h1>
        <p class="subtitle"><?= e($lr['leave_type_name'] ?? 'Leave') ?> · <?= e(format_date($lr['start_date'])) ?> → <?= e(format_date($lr['end_date'])) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <?= status_badge($lr['status']) ?>
        <a href="/employee/leave" class="btn btn-sm btn-soft">
            <i data-lucide="arrow-left" class="me-1" style="width:14px;height:14px"></i>Back
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card ems-card">
            <div class="card-header">Request Details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6"><div class="text-muted small">Type</div><div class="fw-semibold"><?= e($lr['leave_type_name']) ?></div></div>
                    <div class="col-md-6"><div class="text-muted small">Days</div><div class="fw-semibold"><?= e((string) $lr['chargeable_days']) ?></div></div>
                    <div class="col-md-6"><div class="text-muted small">From</div><div><?= e(format_date($lr['start_date'])) ?></div></div>
                    <div class="col-md-6"><div class="text-muted small">To</div><div><?= e(format_date($lr['end_date'])) ?></div></div>
                    <div class="col-12"><div class="text-muted small">Reason</div><div><?= nl2br(e($lr['reason'])) ?></div></div>
                </div>
            </div>
        </div>

        <?php if (!empty($approvals)): ?>
        <div class="card ems-card mt-3">
            <div class="card-header">Manager Comments</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($approvals as $a): ?>
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between gap-2 flex-wrap">
                            <span class="small fw-semibold"><?= e($a['approver_name'] ?? 'Approver') ?> — <?= status_badge($a['status']) ?></span>
                            <span class="text-muted small"><?= e(format_datetime($a['actioned_at'] ?? null)) ?></span>
                        </div>
                        <?php if (!empty($a['comments'])): ?><div class="mt-1 small"><?= e($a['comments']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <?php if (($lr['status'] ?? '') === 'pending'): ?>
        <div class="card ems-card mb-3">
            <div class="card-header">Cancel Request</div>
            <div class="card-body">
                <form method="POST" action="/employee/leave/<?= (int) $lr['id'] ?>/cancel" data-confirm="Cancel this leave request?">
                    <?= csrf_field() ?>
                    <label class="form-label small" for="reason">Cancellation reason</label>
                    <textarea name="reason" class="form-control form-control-sm mb-2" rows="2" id="reason"></textarea>
                    <button class="btn btn-danger btn-sm w-100">
                        <i data-lucide="ban" class="me-1" style="width:14px;height:14px"></i>Cancel Request
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
