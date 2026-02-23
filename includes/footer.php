    </div>
</div>

<div class="search-modal" id="searchModal">
    <div class="search-modal-card">
        <div class="search-modal-header">
            <input type="text" id="searchModalInput" placeholder="Type to search...">
            <button class="btn btn-sm btn-outline-secondary" id="searchModalClose">Esc</button>
        </div>
        <div class="search-modal-results" id="searchModalResults"></div>
    </div>
</div>

<div class="toast-container position-fixed top-0 end-0 p-3">
    <?php foreach ($toasts as $toast) : ?>
        <div class="toast align-items-center text-bg-<?php echo h($toast['level']); ?> border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body"><?php echo h($toast['message']); ?></div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<button type="button" class="btn btn-primary rounded-circle ai-chat-fab" id="aiChatToggle" aria-label="Open AI chat">
    <i class="fa-solid fa-robot"></i>
</button>

<div class="modal fade" id="aiChatModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content ai-chat-modal">
            <div class="modal-header ai-chat-header">
                <h5 class="modal-title">AI Assistant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body ai-chat-body">
                <div id="aiChatMessages" class="ai-chat-messages border rounded p-2 mb-2"></div>
                <div class="input-group">
                    <input type="text" id="aiChatInput" class="form-control" placeholder="Type your question...">
                    <button class="btn btn-primary" type="button" id="aiChatSend">Send</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .ai-chat-fab {
        position: fixed;
        right: 20px;
        bottom: 20px;
        width: 54px;
        height: 54px;
        z-index: 1080;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/i18n.js"></script>
<script src="/assets/js/search.js"></script>
<script src="/assets/js/kanban.js"></script>
<script src="/assets/js/charts.js"></script>
<script src="/assets/js/preview.js"></script>
<script src="/assets/js/attachments.js"></script>
<script src="/assets/js/drafts.js"></script>
<script src="/assets/js/history.js"></script>
<script src="/assets/js/filters.js"></script>
<script src="/assets/js/ai-assistant.js"></script>
</body>
</html>
