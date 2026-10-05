<?php
$errors = $errors ?? \App\Core\Session::flash('errors') ?? [];
?>
<?php if (!empty($flash_success)): ?>
    <div class="alert alert-success alert-dismissible fade show py-2" role="alert" data-ds-flash="success">
        <?= e($flash_success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($flash_error)): ?>
    <div class="alert alert-danger alert-dismissible fade show py-2" role="alert" data-ds-flash="danger">
        <?= e($flash_error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($flash_warning)): ?>
    <div class="alert alert-warning alert-dismissible fade show py-2" role="alert" data-ds-flash="warning">
        <?= e($flash_warning) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($errors) && is_array($errors)): ?>
    <div class="alert alert-danger py-2">
        <ul class="mb-0 small">
            <?php foreach ($errors as $fieldErrors): ?>
                <?php if (is_array($fieldErrors)): ?>
                    <?php foreach ($fieldErrors as $msg): ?>
                        <li><?= e($msg) ?></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li><?= e((string) $fieldErrors) ?></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
