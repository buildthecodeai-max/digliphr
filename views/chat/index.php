<?php
?>
<div id="chatApp"
     class="chat-shell"
     data-user-id="<?= (int) ($userId ?? 0) ?>"
     data-company-id="<?= (int) ($companyId ?? 0) ?>"
     data-poll="<?= (int) ($pollingInterval ?? 5) ?>"
     data-channel="<?= (int) ($initialChannelId ?? 0) ?>"
     data-dm="<?= (int) ($initialDmId ?? 0) ?>"
     data-msg="<?= (int) ($initialMsgId ?? 0) ?>"
     data-can-create="<?= !empty($canCreateChannel) ? '1' : '0' ?>"
     data-can-upload="<?= !empty($canUpload) ? '1' : '0' ?>"
     data-can-mass="<?= !empty($canMassMention) ? '1' : '0' ?>">

    <aside class="chat-rail" id="chatRail">
        <div class="chat-rail-head">
            <div class="chat-search">
                <i data-lucide="search"></i>
                <input type="search" id="chatSearchInput" placeholder="Search messages & files" autocomplete="off">
            </div>
            <div class="chat-rail-actions">
                <?php if (!empty($canCreateChannel)): ?>
                <button type="button" class="btn btn-sm btn-primary" id="btnCreateChannel" title="New channel">
                    <i data-lucide="hash"></i>
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-soft" id="btnNewDm" title="New DM">
                    <i data-lucide="message-square-plus"></i>
                </button>
            </div>
        </div>

        <div class="chat-rail-section">
            <div class="chat-section-label">Channels</div>
            <div id="chatChannelList" class="chat-list"></div>
            <div id="chatDiscoverList" class="chat-list chat-discover"></div>
        </div>

        <div class="chat-rail-section">
            <div class="chat-section-label">Direct messages</div>
            <div id="chatDmList" class="chat-list"></div>
        </div>

        <div class="chat-rail-foot">
            <a href="/chat/files">Files</a>
            <a href="/chat/saved">Saved</a>
            <a href="/chat/mentions">Mentions</a>
            <a href="/chat/documents">Docs</a>
        </div>
    </aside>

    <section class="chat-main" id="chatMain">
        <header class="chat-main-head">
            <button type="button" class="btn btn-sm btn-soft chat-back d-md-none" id="chatBackBtn" aria-label="Back">
                <i data-lucide="arrow-left"></i>
            </button>
            <div class="chat-main-title">
                <strong id="chatTitle">Select a conversation</strong>
                <span id="chatSubtitle" class="text-muted small"></span>
            </div>
            <div class="chat-main-actions">
                <button type="button" class="btn btn-sm btn-soft" id="btnToggleThread" title="Close thread" hidden>Thread</button>
                <button type="button" class="btn btn-sm btn-soft" id="btnMembers" title="Members"><i data-lucide="users"></i></button>
            </div>
        </header>

        <div class="chat-pins" id="chatPins" hidden></div>
        <div class="chat-messages" id="chatMessages" aria-live="polite"></div>
        <div class="chat-typing" id="chatTyping" hidden></div>

        <form class="chat-composer" id="chatComposer" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e($csrf ?? csrf_token()) ?>">
            <input type="file" id="chatFile" name="file" class="chat-composer-file sr-only" tabindex="-1" aria-hidden="true" <?= empty($canUpload) ? 'disabled' : '' ?>>
            <div class="chat-composer-box">
                <textarea id="chatBody" name="body" rows="1" placeholder="Message…" required></textarea>
                <div class="chat-composer-toolbar">
                    <div class="chat-composer-tools">
                        <button type="button" class="chat-composer-tool" id="chatAttachBtn" title="Attach file" <?= empty($canUpload) ? 'disabled' : '' ?>>
                            <i data-lucide="plus"></i>
                        </button>
                        <button type="button" class="chat-composer-tool" id="chatFormatBtn" title="Format" aria-label="Format">
                            <span class="chat-composer-aa">Aa</span>
                        </button>
                        <button type="button" class="chat-composer-tool" id="chatEmojiBtn" title="Emoji">
                            <i data-lucide="smile"></i>
                        </button>
                        <button type="button" class="chat-composer-tool" id="chatMentionBtn" title="Mention">
                            <i data-lucide="at-sign"></i>
                        </button>
                    </div>
                    <div class="chat-composer-send-wrap">
                        <button type="submit" class="chat-composer-send" id="chatSendBtn" title="Send" disabled>
                            <i data-lucide="send"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </section>

    <aside class="chat-thread" id="chatThread" hidden>
        <header class="chat-thread-head">
            <strong>Thread</strong>
            <button type="button" class="btn btn-sm btn-soft" id="chatThreadClose"><i data-lucide="x"></i></button>
        </header>
        <div class="chat-messages" id="chatThreadMessages"></div>
        <form class="chat-composer" id="chatThreadComposer">
            <input type="hidden" name="_csrf" value="<?= e($csrf ?? csrf_token()) ?>">
            <div class="chat-composer-box">
                <textarea id="chatThreadBody" rows="1" placeholder="Reply…" required></textarea>
                <div class="chat-composer-toolbar">
                    <div class="chat-composer-tools">
                        <button type="button" class="chat-composer-tool" id="chatThreadEmojiBtn" title="Emoji">
                            <i data-lucide="smile"></i>
                        </button>
                        <button type="button" class="chat-composer-tool" id="chatThreadMentionBtn" title="Mention">
                            <i data-lucide="at-sign"></i>
                        </button>
                    </div>
                    <div class="chat-composer-send-wrap">
                        <button type="submit" class="chat-composer-send" id="chatThreadSendBtn" title="Send" disabled>
                            <i data-lucide="send"></i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </aside>
</div>

<div class="modal fade" id="chatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="chatModalTitle">Dialog</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="chatModalBody"></div>
            <div class="modal-footer" id="chatModalFooter"></div>
        </div>
    </div>
</div>

<div class="chat-search-overlay" id="chatSearchOverlay" hidden>
    <div class="chat-search-panel">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Search results</strong>
            <button type="button" class="btn btn-sm btn-soft" id="chatSearchClose">Close</button>
        </div>
        <div id="chatSearchResults"></div>
    </div>
</div>
