<?php /** @var string $title */ ?>
<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="page-title h4 mb-0"><?= e($title ?? 'Change Password') ?></h1>
    </div>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card card-compact">
            <div class="card-body">
                <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
                <form method="POST" action="/change-password">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">New Password</label>
                        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirmation">Confirm New Password</label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
