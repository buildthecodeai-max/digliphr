<div class="chat-page-wrap p-3 p-md-4" style="max-width:560px">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Chat Notifications</h1>
        <a href="/chat" class="btn btn-sm btn-soft">Back to chat</a>
    </div>
    <form method="post" action="/chat/notifications" class="card border-0 shadow-sm p-3">
        <?= csrf_field() ?>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="notify_dms" value="1" id="n1" <?= !empty($prefs['notify_dms']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="n1">Direct messages</label>
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="notify_mentions" value="1" id="n2" <?= !empty($prefs['notify_mentions']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="n2">Mentions</label>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="notify_channel_messages" value="1" id="n3" <?= !empty($prefs['notify_channel_messages']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="n3">Channel messages</label>
        </div>
        <div class="row g-2 mb-3">
            <div class="col">
                <label class="form-label small">Quiet hours start</label>
                <input type="time" name="quiet_hours_start" class="form-control form-control-sm" value="<?= e($prefs['quiet_hours_start'] ?? '') ?>">
            </div>
            <div class="col">
                <label class="form-label small">Quiet hours end</label>
                <input type="time" name="quiet_hours_end" class="form-control form-control-sm" value="<?= e($prefs['quiet_hours_end'] ?? '') ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save preferences</button>
    </form>
</div>
