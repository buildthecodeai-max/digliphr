<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Forgot Password') ?> — <?= e($appName ?? config('app.name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/saas-theme.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/approved-theme.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-brand text-center mb-4">
            <div class="auth-logo"><i class="bi bi-key"></i></div>
            <h1 class="h4 mb-1">Reset Password</h1>
            <p class="text-muted small">Enter your email to receive a reset link</p>
        </div>
        <?php include config('app.paths.views') . '/partials/alerts.php'; ?>
        <form method="POST" action="/forgot-password">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="email">Email address</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= e(old('email')) ?>" required autofocus>
            </div>
            <button type="submit" class="btn btn-primary w-100 mb-2">Send Reset Link</button>
            <a href="/login" class="btn btn-outline-secondary w-100">Back to Sign In</a>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
