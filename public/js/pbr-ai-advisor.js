(() => {
    const root = document.getElementById('pbr-ai-advisor');
    if (!root) return;

    const chatUrl = root.dataset.chatUrl;
    const csrf = root.dataset.csrf;
    let conversationId = root.dataset.conversationId || '';

    const form = document.getElementById('pbrai-form');
    const input = document.getElementById('pbrai-input');
    const sendButton = document.getElementById('pbrai-send');
    const messages = document.getElementById('pbrai-messages');
    const emptyState = document.getElementById('pbrai-empty');
    const errorBox = document.getElementById('pbrai-error');
    const statusBox = document.getElementById('pbrai-status');

    const scrollBottom = () => {
        messages.scrollTop = messages.scrollHeight;
    };

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const renderSafeMarkdown = (value) => {
        let text = escapeHtml(value);
        text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
        text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
        return text;
    };

    const setBusy = (busy) => {
        sendButton.disabled = busy;
        input.disabled = busy;
        root.dataset.busy = busy ? '1' : '0';
        if (!busy) input.focus();
    };

    const showError = (text) => {
        errorBox.textContent = text;
        errorBox.classList.add('show');
    };

    const clearError = () => {
        errorBox.textContent = '';
        errorBox.classList.remove('show');
    };

    const showStatus = (text) => {
        statusBox.textContent = text;
        statusBox.classList.toggle('show', Boolean(text));
    };

    const addBubble = (role, text = '', thinking = false) => {
        if (emptyState) emptyState.style.display = 'none';

        const row = document.createElement('div');
        row.className = `pbrai-message ${role}`;

        const bubble = document.createElement('div');
        bubble.className = 'pbrai-bubble';

        const label = document.createElement('span');
        label.className = 'pbrai-message-label';
        label.textContent = role === 'user' ? 'သင်' : 'PBR AI Advisor';
        bubble.appendChild(label);

        const body = document.createElement('span');
        body.className = 'pbrai-message-body';
        if (thinking) {
            body.innerHTML = '<span class="pbrai-thinking"><i></i><i></i><i></i></span>';
        } else {
            body.innerHTML = renderSafeMarkdown(text);
        }
        bubble.appendChild(body);
        row.appendChild(bubble);
        messages.appendChild(row);
        scrollBottom();

        return { row, bubble, body };
    };

    const updateConversationUrl = (id) => {
        if (!id) return;
        conversationId = String(id);
        root.dataset.conversationId = conversationId;
        const url = new URL(window.location.href);
        url.searchParams.delete('new');
        url.searchParams.set('conversation', conversationId);
        window.history.replaceState({}, '', url);
    };

    const consumeEvent = (rawEvent, assistantState) => {
        const lines = rawEvent.split(/\r?\n/);
        for (const line of lines) {
            if (!line.startsWith('data: ')) continue;
            let data;
            try {
                data = JSON.parse(line.slice(6));
            } catch (_) {
                continue;
            }

            if (data.type === 'meta') {
                if (data.conversationId) updateConversationUrl(data.conversationId);
                if (data.mode === 'rag') {
                    showStatus('PBR Knowledge + Business Data ကိုအသုံးပြုပြီး အဖြေပြင်ဆင်နေပါတယ်…');
                } else if (data.mode === 'general') {
                    showStatus('Business Context + AI Knowledge + လိုအပ်ရင် Live Search ကိုအသုံးပြုနေပါတယ်…');
                }
            }

            if (data.type === 'delta' && typeof data.text === 'string') {
                if (!assistantState.started) {
                    assistantState.started = true;
                    assistantState.text = '';
                }
                assistantState.text += data.text;
                assistantState.body.innerHTML = renderSafeMarkdown(assistantState.text);
                scrollBottom();
            }

            if (data.type === 'error') {
                showError(data.text || 'AI Advisor ကို ခဏဆက်သွယ်လို့မရသေးပါ။');
            }

            if (data.type === 'done') {
                showStatus('');
            }
        }
    };

    const sendMessage = async (message) => {
        const clean = String(message || '').trim();
        if (!clean || root.dataset.busy === '1') return;

        clearError();
        addBubble('user', clean);
        input.value = '';
        input.style.height = 'auto';
        setBusy(true);
        showStatus('PBR AI Advisor က သင့် Business Data နဲ့ Knowledge Base ကို စစ်ဆေးနေပါတယ်…');

        const assistant = addBubble('assistant', '', true);
        const assistantState = {
            ...assistant,
            started: false,
            text: '',
        };

        try {
            const response = await fetch(chatUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'text/event-stream',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    message: clean,
                    conversation_id: conversationId || null,
                }),
            });

            if (!response.ok) {
                let messageText = 'AI Advisor ကို ခဏဆက်သွယ်လို့မရသေးပါ။';
                try {
                    const body = await response.json();
                    if (body.message) messageText = body.message;
                } catch (_) {}
                throw new Error(messageText);
            }

            if (!response.body) throw new Error('Streaming response မရရှိပါ။');

            const reader = response.body.getReader();
            const decoder = new TextDecoder('utf-8');
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });

                let boundary;
                while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                    const event = buffer.slice(0, boundary);
                    buffer = buffer.slice(boundary + 2);
                    consumeEvent(event, assistantState);
                }
            }

            if (buffer.trim()) consumeEvent(buffer, assistantState);

            if (!assistantState.started) {
                assistantState.body.textContent = 'အဖြေမရသေးပါ။ ထပ်စမ်းပေးပါ။';
            }
        } catch (error) {
            assistantState.body.textContent = 'AI Advisor ကို ခဏဆက်သွယ်လို့မရသေးပါ။';
            showError(error?.message || 'AI Advisor Service Error');
        } finally {
            showStatus('');
            setBusy(false);
            scrollBottom();
        }
    };

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(input.value);
    });

    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            form.requestSubmit();
        }
    });

    input?.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(input.scrollHeight, 150)}px`;
    });

    document.querySelectorAll('[data-pbrai-prompt]').forEach((button) => {
        button.addEventListener('click', () => {
            input.value = button.dataset.pbraiPrompt || button.textContent.trim();
            input.dispatchEvent(new Event('input'));
            input.focus();
        });
    });

    document.querySelectorAll('[data-pbrai-delete-form]').forEach((formEl) => {
        formEl.addEventListener('submit', (event) => {
            if (!window.confirm('ဒီ AI Conversation ကို အပြီးဖျက်မှာ သေချာပါသလား?')) {
                event.preventDefault();
            }
        });
    });

    scrollBottom();
    input?.focus();
})();
