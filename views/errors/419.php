<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 — Session Expired</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/approved-theme.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-wrapper">
    <div class="auth-card text-center">
        <div class="display-4 text-danger fw-bold">419</div>
        <h1 class="h5">Session Expired</h1>
        <p class="text-muted small"><?= e($message ?? 'Your session has expired. Please refresh and try again.') ?></p>
        <a href="/login" class="btn btn-primary btn-sm">Sign In Again</a>
    </div>
</div>
</body>
</html>
