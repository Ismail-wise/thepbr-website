(function () {
    'use strict';
    document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    }));
    const rows = document.getElementById('portal-partners');
    if (rows) {
        let nextIndex = Math.max(-1, ...Array.from(rows.querySelectorAll('input[name$="[name]"]'), input => Number(input.name.match(/\[(\d+)\]/)[1]))) + 1;
        document.getElementById('portal-add-partner').addEventListener('click', () => {
            if (rows.children.length >= 10) return;
            const row = document.createElement('div');
            row.className = 'partner-input';
            row.dataset.partnerRow = '';
            const name = document.createElement('input');
            Object.assign(name, {name: `partners[${nextIndex}][name]`, required: true, maxLength: 100, placeholder: 'Partner နာမည်'});
            name.setAttribute('aria-label', 'Partner နာမည်');
            const value = document.createElement('input');
            Object.assign(value, {name: `partners[${nextIndex}][value]`, type: 'number', min: '0', max: '1000000000', step: '0.01', value: '0', required: true});
            value.setAttribute('aria-label', 'ထည့်ဝင်တန်ဖိုး');
            const remove = document.createElement('button');
            remove.className = 'icon-btn';remove.type = 'button';remove.dataset.removePartner = '';remove.textContent = '×';remove.setAttribute('aria-label', 'Partner row ဖယ်ရန်');
            row.append(name, value, remove);rows.append(row);nextIndex++;
        });
        rows.addEventListener('click', event => {
            const remove = event.target.closest('[data-remove-partner]');
            if (remove && rows.children.length > 2) remove.closest('[data-partner-row]').remove();
        });
    }

    const root = document.getElementById('portal-ai');
    if (!root) return;
    const form = document.getElementById('portal-chat-form');
    const input = document.getElementById('portal-message');
    const messages = document.getElementById('portal-messages');
    const error = document.getElementById('portal-chat-error');
    const status = document.getElementById('portal-chat-status');
    const send = document.getElementById('portal-send');
    let busy = false;
    function scroll() {
        const area = document.getElementById('chat-scroll');area.scrollTop = area.scrollHeight;
    }
    function bubble(role, text) {
        const article = document.createElement('article');article.className = 'message ' + role;
        const body = document.createElement('div');body.className = 'message-body';
        const reply = document.createElement('div');reply.className = 'portal-reply';reply.textContent = text;
        body.append(reply);article.append(body);messages.append(article);return {article, reply};
    }
    document.querySelectorAll('[data-ai-prompt]').forEach(button => button.addEventListener('click', () => {
        input.value = button.dataset.aiPrompt;input.focus();
    }));
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();form.requestSubmit();
        }
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || busy) return;
        busy = true;send.disabled = true;input.disabled = true;error.textContent = '';status.textContent = 'PBR AI အဖြေ ပြင်ဆင်နေပါတယ်…';
        document.getElementById('portal-welcome')?.remove();
        const userBubble = bubble('user', message), assistantBubble = bubble('assistant', '');
        let text = '', completed = false, upstreamError = '';
        const abort = new AbortController(), timer = window.setTimeout(() => abort.abort(), 210000);
        scroll();
        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin', signal: abort.signal,
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json, text/event-stream', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({message, conversation_id: root.dataset.conversation ? Number(root.dataset.conversation) : null})
            });
            if (!response.ok) {
                const problem = await response.json().catch(() => ({}));
                throw new Error(problem.message || 'Account ဝင်ခွင့်နှင့် ချိတ်ဆက်မှုကို ပြန်စစ်ပါ။');
            }
            if (!response.headers.get('content-type')?.includes('text/event-stream') || !response.body) throw new Error('Login ပြန်ဝင်ပြီး ထပ်စမ်းပါ။');
            const reader = response.body.getReader(), decoder = new TextDecoder();let buffer = '';
            function consume() {
                buffer = buffer.replace(/\r\n/g, '\n');
                let end;
                while ((end = buffer.indexOf('\n\n')) >= 0) {
                    const frame = buffer.slice(0, end);buffer = buffer.slice(end + 2);
                    for (const line of frame.split('\n')) {
                        if (!line.startsWith('data:')) continue;
                        let data;try {data = JSON.parse(line.slice(5).trim());} catch {continue;}
                        if (data.type === 'meta' && Number.isInteger(data.conversationId)) {
                            root.dataset.conversation = String(data.conversationId);
                            const url = new URL(window.location.href);url.search = '';url.searchParams.set('conversation', String(data.conversationId));window.history.replaceState(null, '', url);
                            const history = document.querySelector('.history-list');
                            if (history && !history.querySelector(`[data-conversation="${data.conversationId}"]`)) {
                                history.querySelector('[data-empty-history]')?.remove();
                                const link = document.createElement('a');link.href = url.href;link.dataset.conversation = String(data.conversationId);link.textContent = message.slice(0, 72);history.prepend(link);
                            }
                        } else if (data.type === 'delta' && typeof data.text === 'string') {
                            text += data.text;assistantBubble.reply.textContent = text;scroll();
                        } else if (data.type === 'error') {
                            upstreamError = typeof data.text === 'string' ? data.text : 'AI အဖြေ မပြီးဆုံးသေးပါ။';
                        } else if (data.type === 'done') completed = true;
                    }
                }
            }
            while (true) {
                const chunk = await reader.read();
                if (chunk.done) break;
                buffer += decoder.decode(chunk.value, {stream: true});consume();
            }
            buffer += decoder.decode();consume();
            if (upstreamError || !completed || !text.trim()) throw new Error(upstreamError || 'AI အဖြေ မပြီးဆုံးသေးပါ။ ထပ်စမ်းပါ။');
            input.value = '';status.textContent = 'သင့် Account ထဲမှာ စကားဝိုင်းကို သိမ်းပြီးပါပြီ။';
        } catch (problem) {
            error.textContent = problem.name === 'AbortError' ? 'AI အဖြေစောင့်ရတာ ကြာနေပါတယ်။ ခဏနေရင် ထပ်စမ်းပါ။' : problem.message;
            status.textContent = 'ပြန်စမ်းနိုင်ပါတယ်။';
            if (!text) {userBubble.article.remove();assistantBubble.article.remove();input.value = message;}
            else input.value = '';
        } finally {
            window.clearTimeout(timer);busy = false;send.disabled = false;input.disabled = false;input.focus();
        }
    });
    scroll();
})();
