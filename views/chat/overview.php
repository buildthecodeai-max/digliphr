<?php
/** @var array $metrics */
/** @var array $channels */
/** @var array $conversations */
?>
<div class="container-fluid py-3">
    <div class="page-header">
        <div>
            <h1>Chat Overview</h1>
            <p class="subtitle">Channels, DMs, and unread activity</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-primary" href="/chat"><i data-lucide="messages-square" class="me-1" style="width:14px;height:14px"></i>Open chat</a>
            <a class="btn btn-sm btn-soft" href="/chat/mentions"><i data-lucide="at-sign" class="me-1" style="width:14px;height:14px"></i>Mentions</a>
        </div>
    </div>

    <?php
    $__overviewStrip = rtrim((string) config('app.paths.views'), '/') . '/partials/workspace-overview-strip.php';
    if (is_file($__overviewStrip)) {
        include $__overviewStrip;
    }
    unset($__overviewStrip);
    ?>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Your channels</div>
                <div class="list-group list-group-flush">
                    <?php if (empty($channels)): ?>
                        <div class="p-3 text-muted small">No channels yet.</div>
                    <?php else: ?>
                        <?php foreach ($channels as $ch): ?>
                            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                               href="/chat?channel=<?= (int) ($ch['id'] ?? 0) ?>">
                                <span>#<?= e($ch['name'] ?? $ch['slug'] ?? 'channel') ?></span>
                                <?php if (!empty($ch['unread_count'])): ?>
                                    <span class="badge bg-danger"><?= (int) $ch['unread_count'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header">Direct messages</div>
                <div class="list-group list-group-flush">
                    <?php if (empty($conversations)): ?>
                        <div class="p-3 text-muted small">No direct messages yet.</div>
                    <?php else: ?>
                        <?php foreach ($conversations as $dm): ?>
                            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                               href="/chat?dm=<?= (int) ($dm['id'] ?? 0) ?>">
                                <span><?= e($dm['title'] ?? $dm['other_user_name'] ?? 'Conversation') ?></span>
                                <?php if (!empty($dm['unread_count'])): ?>
                                    <span class="badge bg-danger"><?= (int) $dm['unread_count'] ?></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
