<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e((string) ($code ?? 500)) ?> — Error</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/approved-theme.css') ?>" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="text-center mb-3">
            <div class="display-4 fw-bold text-danger"><?= e((string) ($code ?? 500)) ?></div>
            <h1 class="h5">Something went wrong</h1>
        </div>
        <p class="text-muted small text-center"><?= e($message ?? 'An unexpected error occurred.') ?></p>
        <?php if (!empty($exception) && config('app.debug')): ?>
            <pre class="small bg-light p-2 rounded mt-3" style="max-height:240px;overflow:auto;"><?= e($exception->getMessage()) ?>\n<?= e($exception->getFile()) ?>:<?= e((string) $exception->getLine()) ?></pre>
        <?php endif; ?>
        <div class="text-center mt-3">
            <a href="/" class="btn btn-primary btn-sm">Go Home</a>
        </div>
    </div>
</div>
</body>
</html>
