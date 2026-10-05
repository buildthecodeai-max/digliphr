<div class="chat-page-wrap p-3 p-md-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Saved Messages</h1>
        <a href="/chat" class="btn btn-sm btn-soft">Back to chat</a>
    </div>
    <div class="chat-simple-list">
        <?php if (empty($items)): ?>
            <p class="text-muted">No saved messages yet.</p>
        <?php else: foreach ($items as $m): ?>
            <article class="chat-simple-item">
                <div class="fw-semibold"><?= e($m['user_name'] ?? 'User') ?></div>
                <div class="small text-muted mb-1"><?= e(format_datetime($m['created_at'] ?? null)) ?></div>
                <div><?= $m['body_html'] ?? e($m['body'] ?? '') ?></div>
                <a class="small" href="/chat?<?= !empty($m['channel_id']) ? 'channel=' . (int) $m['channel_id'] : 'dm=' . (int) $m['conversation_id'] ?>&msg=<?= (int) $m['id'] ?>">Open</a>
            </article>
        <?php endforeach; endif; ?>
    </div>
</div>
