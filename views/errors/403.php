<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Forbidden</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/approved-theme.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-wrapper">
    <div class="auth-card text-center">
        <div class="display-4 text-warning fw-bold">403</div>
        <h1 class="h5">Access Denied</h1>
        <p class="text-muted small"><?= e($message ?? 'You do not have permission to access this resource.') ?></p>
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm me-1">Go Back</a>
        <a href="/" class="btn btn-primary btn-sm">Home</a>
        <?php if (auth()->check()): ?>
            <form method="POST" action="/logout" class="d-inline-block ms-1">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm">Sign Out</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
