<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Reset Password') ?> — <?= e($appName ?? config('app.name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/saas-theme.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/approved-theme.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-brand text-center mb-4">
            <h1 class="h4">Set New Password</h1>
        </div>
        <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
        <form method="POST" action="/reset-password">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email', $email ?? '')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">New Password</label>
                <input type="password" class="form-control" id="password" name="password" minlength="8" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password_confirmation">Confirm Password</label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Update Password</button>
        </form>
    </div>
</div>
</body>
</html>
