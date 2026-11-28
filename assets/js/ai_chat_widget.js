/**
 * CashManager - Floating AI Assistant Widget
 * Modern floating chatbot widget matching reference design.
 * Immune to InfinityFree ModSecurity 403 by using auto POST -> GET fallback.
 */

(function () {
    // Avoid double initialization
    if (window.__cashManagerAiWidgetLoaded) return;
    window.__cashManagerAiWidgetLoaded = true;

    const DEFAULT_PROMPT_CHIPS = [
        '💡 Tiết kiệm 2 triệu?',
        '📱 Có nên mua điện thoại?',
        '🍲 Giảm 30% tiền ăn?'
    ];

    function injectAiWidget() {
        if (document.getElementById('aiChatWidget')) return;

        // 1. Floating Pill Button (Bottom Right)
        const fab = document.createElement('button');
        fab.type = 'button';
        fab.id = 'aiFabTrigger';
        fab.className = 'ai-fab';
        fab.setAttribute('aria-label', 'Mở trợ lý tài chính AI');
        fab.innerHTML = `
            <span class="ai-fab-icon"><i class="fa-solid fa-robot"></i></span>
            <span class="ai-fab-text">Hỏi AI</span>
        `;
        document.body.appendChild(fab);

        // 2. Chat Popup Widget Window
        const widget = document.createElement('div');
        widget.id = 'aiChatWidget';
        widget.className = 'ai-chat-widget collapsed';
        widget.setAttribute('role', 'dialog');
        widget.setAttribute('aria-labelledby', 'aiWidgetTitle');
        widget.innerHTML = `
            <div class="ai-widget-header">
                <div class="ai-widget-title" id="aiWidgetTitle">
                    <span class="ai-widget-badge"><i class="fa-solid fa-robot"></i></span>
                    <div>
                        <div class="ai-title-text">Trợ lý tài chính AI</div>
                        <small class="ai-status-online">
                            <span class="ai-online-dot"></span> Trực tuyến
                        </small>
                    </div>
                </div>
                <div class="ai-widget-actions">
                    <button type="button" id="aiWidgetMinimize" class="ai-widget-btn" title="Thu nhỏ">
                        <i class="fa-solid fa-minus"></i>
                    </button>
                    <button type="button" id="aiWidgetClose" class="ai-widget-btn" title="Đóng">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div class="ai-widget-body">
                <div class="aic-list" id="aicList" aria-live="polite">
                    <div class="aic-msg aic-bot">
                        <div class="aic-avatar"><i class="fa-solid fa-robot"></i></div>
                        <div class="aic-bubble">
                            Xin chào! Mình là trợ lý tài chính AI. Bạn cứ hỏi về chi tiêu, tiết kiệm hay kế hoạch mua sắm, mình sẽ trả lời dựa trên số liệu thu chi của bạn nhé.
                        </div>
                    </div>
                </div>

                <!-- Quick suggestion chips -->
                <div class="aic-chips" id="aicChips">
                    ${DEFAULT_PROMPT_CHIPS.map(chip => `
                        <button type="button" class="aic-chip" data-q="${chip.replace(/^[^\s]+\s*/, '')}">${chip}</button>
                    `).join('')}
                </div>
            </div>

            <div class="ai-widget-footer">
                <form class="aic-form" id="aicForm">
                    <input type="text" id="aicInput" class="form-control" placeholder="Hỏi về chi tiêu, tiết kiệm..." autocomplete="off">
                    <button type="submit" id="aicSend" class="aic-send" aria-label="Gửi câu hỏi">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        `;
        document.body.appendChild(widget);

        // 3. Bind Events
        bindAiEvents(fab, widget);
    }

    function bindAiEvents(fab, widget) {
        const btnMin = document.getElementById('aiWidgetMinimize');
        const btnClose = document.getElementById('aiWidgetClose');
        const form = document.getElementById('aicForm');
        const input = document.getElementById('aicInput');
        const list = document.getElementById('aicList');
        const chips = document.getElementById('aicChips');

        function openWidget() {
            widget.classList.remove('collapsed');
            fab.style.display = 'none';
            if (input) setTimeout(() => input.focus(), 150);
            scrollBottom();
        }

        function closeWidget() {
            widget.classList.add('collapsed');
            fab.style.display = 'inline-flex';
        }

        fab.addEventListener('click', openWidget);
        fab.addEventListener('touchend', (e) => {
            e.preventDefault();
            openWidget();
        }, { passive: false });

        if (btnMin) btnMin.addEventListener('click', closeWidget);
        if (btnClose) btnClose.addEventListener('click', closeWidget);

        // Also bind any page buttons like "Trợ lý AI" in header or cards
        document.querySelectorAll('#btnOpenAiModal, .btn-ai-header, #btnQuickAiChat, [data-open-ai]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                openWidget();
            });
        });

        // Chip click handler
        if (chips) {
            chips.addEventListener('click', (e) => {
                const target = e.target.closest('.aic-chip');
                if (!target) return;
                const q = target.getAttribute('data-q') || target.textContent.trim();
                sendMessage(q);
            });
        }

        // Form submit handler
        if (form && input) {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                const q = input.value.trim();
                if (!q) return;
                input.value = '';
                sendMessage(q);
            });
        }

        function scrollBottom() {
            if (list) list.scrollTop = list.scrollHeight;
        }

        async function sendMessage(questionText) {
            if (!questionText || !list) return;

            // 1. Append User Message Bubble
            const userMsg = document.createElement('div');
            userMsg.className = 'aic-msg aic-user';
            userMsg.innerHTML = `
                <div class="aic-bubble">${escapeHtml(questionText)}</div>
            `;
            list.appendChild(userMsg);
            scrollBottom();

            // 2. Append Loading Placeholder
            const botLoading = document.createElement('div');
            botLoading.className = 'aic-msg aic-bot';
            botLoading.innerHTML = `
                <div class="aic-avatar"><i class="fa-solid fa-robot"></i></div>
                <div class="aic-bubble aic-thinking">
                    <i class="fa-solid fa-spinner fa-spin"></i> Trợ lý AI đang suy nghĩ...
                </div>
            `;
            list.appendChild(botLoading);
            scrollBottom();

            // 3. Request with Auto POST -> GET Fallback (InfinityFree Safe)
            try {
                let res = null;
                const ctl = new AbortController();
                const timeoutId = setTimeout(() => ctl.abort(), 35000);

                // Attempt 1: POST FormData
                try {
                    const fd = new FormData();
                    fd.append('question', questionText);
                    res = await fetch('../api/ai/chat.php', {
                        method: 'POST',
                        credentials: 'include',
                        signal: ctl.signal,
                        body: fd
                    });
                } catch (_) {}

                // Attempt 2: Fallback to GET if 403, network blocked or error
                if (!res || res.status === 403 || !res.ok) {
                    res = await fetch('../api/ai/chat.php?question=' + encodeURIComponent(questionText), {
                        method: 'GET',
                        credentials: 'include',
                        signal: ctl.signal
                    });
                }

                clearTimeout(timeoutId);

                if (res.status === 401) {
                    botLoading.querySelector('.aic-bubble').innerHTML = 'Phiên đăng nhập đã hết hạn. Vui lòng <a href="login.html" style="color:#2563eb; font-weight:700;">đăng nhập lại</a>.';
                    return;
                }

                const json = await res.json();
                let answer = '';
                if (json.success && json.data && json.data.answer) {
                    answer = json.data.answer;
                } else if (json.reply) {
                    answer = json.reply;
                } else if (json.error) {
                    answer = json.error;
                } else {
                    answer = 'Xin lỗi, hiện tại mình chưa thể xử lý câu hỏi này. Bạn hãy thử lại nhé!';
                }

                // Render answer with formatting
                botLoading.querySelector('.aic-bubble').innerHTML = formatAiAnswer(answer);

            } catch (err) {
                botLoading.querySelector('.aic-bubble').innerHTML = `<span style="color:#ef4444;">Không thể kết nối máy chủ: ${escapeHtml(err.message || 'Lỗi mạng')}</span>`;
            }

            scrollBottom();
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatAiAnswer(text) {
        if (!text) return '';
        let s = escapeHtml(text);
        // Replace bold **text**
        s = s.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Replace list bullets
        s = s.replace(/(?:^|\n)[-*•]\s+(.+)/g, '<br>• $1');
        // Replace newlines
        s = s.replace(/\n\n/g, '<br><br>').replace(/\n/g, '<br>');
        return s;
    }

    // Expose Global Helper
    window.CashManagerAI = {
        open: function () {
            const fab = document.getElementById('aiFabTrigger');
            const widget = document.getElementById('aiChatWidget');
            if (widget) {
                widget.classList.remove('collapsed');
                if (fab) fab.style.display = 'none';
            }
        },
        close: function () {
            const fab = document.getElementById('aiFabTrigger');
            const widget = document.getElementById('aiChatWidget');
            if (widget) {
                widget.classList.add('collapsed');
                if (fab) fab.style.display = 'inline-flex';
            }
        }
    };

    // Auto-inject when DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectAiWidget);
    } else {
        injectAiWidget();
    }
})();
