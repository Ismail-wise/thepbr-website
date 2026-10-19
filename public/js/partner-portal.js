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

    document.querySelectorAll('[data-knowledge-submit]').forEach(knowledgeForm => {
        knowledgeForm.addEventListener('submit', event => {
            // Keep the clicked submitter enabled until the browser captures its
            // name/value (approve vs draft). Disable on the next event-loop turn.
            const progress = knowledgeForm.querySelector('[data-knowledge-progress]');
            if (event.submitter?.value === 'approve' && !knowledgeForm.querySelector('[name="reviewed"]')?.checked) return;
            if (progress) progress.textContent = 'သိမ်းနေပါတယ်…';
            window.setTimeout(() => knowledgeForm.querySelectorAll('button').forEach(button => button.disabled = true), 0);
        });
    });

    const themeButton = document.querySelector('[data-theme-toggle]');
    function applyTheme(theme) {
        document.documentElement.dataset.theme = theme;
        themeButton?.setAttribute('aria-pressed', String(theme === 'dark'));
        themeButton?.setAttribute('aria-label', theme === 'dark' ? 'Light theme' : 'Dark theme');
    }
    applyTheme(document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light');
    themeButton?.addEventListener('click', () => {
        const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        applyTheme(theme);
        try { localStorage.setItem('pbr-theme', theme); } catch { /* storage is optional */ }
    });

    const root = document.getElementById('portal-ai');
    if (!root) return;
    const form = document.getElementById('portal-chat-form');
    const input = document.getElementById('portal-message');
    const messages = document.getElementById('portal-messages');
    const error = document.getElementById('portal-chat-error');
    const status = document.getElementById('portal-chat-status');
    const send = document.getElementById('portal-send');
    const language = document.getElementById('portal-reply-language');
    const area = document.getElementById('chat-scroll');
    const originals = new WeakMap();
    let busy = false, following = true;
    const stale = root.dataset.stale === 'true';
    const labels = (my, en) => language?.value === 'en' ? en : my;
    function fitViewport() {
        const viewport = window.visualViewport;
        if (viewport && viewport.scale === 1 && viewport.height > 0) {
            document.documentElement.style.setProperty('--portal-viewport', viewport.height + 'px');
        }
    }
    window.visualViewport?.addEventListener('resize', fitViewport);
    fitViewport();
    function resize() {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 160) + 'px';
    }
    function scroll(force = false) {
        if (following || force) area.scrollTop = area.scrollHeight;
    }
    area.addEventListener('scroll', () => {
        following = area.scrollHeight - area.scrollTop - area.clientHeight < 100;
    }, {passive: true});

    // Small DOM-only formatter: never accepts model HTML, URLs or attributes.
    function inline(parent, text) {
        const pattern = /\*\*([^*\n]+)\*\*|`([^`\n]+)`/g;
        let last = 0, match;
        while ((match = pattern.exec(text))) {
            parent.append(document.createTextNode(text.slice(last, match.index)));
            const element = document.createElement(match[1] ? 'strong' : 'code');
            element.textContent = match[1] || match[2];parent.append(element);
            last = pattern.lastIndex;
        }
        parent.append(document.createTextNode(text.slice(last)));
    }
    function renderReply(reply, text) {
        originals.set(reply, text);reply.dataset.formatted = 'true';reply.replaceChildren();
        let paragraph = null, list = null, code = null;
        for (const line of text.split('\n')) {
            if (/^\s*```/.test(line)) {
                if (code) code = null;
                else {const pre = document.createElement('pre');code = document.createElement('code');pre.append(code);reply.append(pre);}
                paragraph = null;list = null;continue;
            }
            if (code) {code.append(document.createTextNode(line + '\n'));continue;}
            if (!line.trim()) {paragraph = null;list = null;continue;}
            const bullet = line.match(/^\s*(?:([-*])\s+|(\d+)[.)]\s+)(.*)$/);
            if (bullet) {
                const tag = bullet[2] ? 'OL' : 'UL';
                if (!list || list.tagName !== tag) {list = document.createElement(tag.toLowerCase());reply.append(list);}
                const item = document.createElement('li');inline(item, bullet[3]);list.append(item);paragraph = null;continue;
            }
            list = null;
            const heading = line.match(/^#{1,6}\s+(.+)$/);
            if (heading) {
                paragraph = document.createElement('p');const strong = document.createElement('strong');inline(strong, heading[1]);paragraph.append(strong);reply.append(paragraph);paragraph = null;continue;
            }
            if (!paragraph) {paragraph = document.createElement('p');reply.append(paragraph);}
            else paragraph.append(document.createElement('br'));
            inline(paragraph, line);
        }
    }
    root.querySelectorAll('[data-format-reply]').forEach(reply => renderReply(reply, reply.textContent));
    root.addEventListener('click', async event => {
        const button = event.target.closest('[data-copy-reply]');
        if (!button || button.disabled) return;
        const reply = button.closest('.message')?.querySelector('.portal-reply');
        if (!reply) return;
        try {
            await navigator.clipboard.writeText(originals.get(reply) ?? reply.textContent);
            button.classList.add('is-copied');button.setAttribute('aria-label', 'Copied');
            status.textContent = labels('ကူးယူပြီးပါပြီ။', 'Copied.');
            window.setTimeout(() => {button.classList.remove('is-copied');button.setAttribute('aria-label', 'Copy answer');}, 1800);
        } catch {status.textContent = labels('စာသားကို ရွေးပြီး ကူးယူနိုင်ပါတယ်။', 'Select the text to copy.');}
    });
    root.querySelectorAll('details').forEach(details => details.addEventListener('toggle', () => {
        if (details.open) root.querySelectorAll('details').forEach(other => {if (other !== details) other.open = false;});
    }));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') root.querySelectorAll('details[open]').forEach(details => {details.open = false;details.querySelector('summary')?.focus();});
    });
    document.addEventListener('click', event => {
        root.querySelectorAll('.portal-history[open], .chat-context-menu[open]').forEach(details => {if (!details.contains(event.target)) details.open = false;});
    });
    function updateLanguage() {
        const english = language?.value === 'en';
        const welcome = document.querySelector('[data-chat-welcome]');
        if (welcome) welcome.textContent = english ? 'What’s on your mind?' : 'ဘာဆွေးနွေးကြမလဲ?';
        input.placeholder = english ? 'Ask in English or မြန်မာ…' : 'မြန်မာလို သို့မဟုတ် English လို မေးပါ…';
        root.querySelectorAll('[data-label-my]').forEach(label => {label.textContent = english ? label.dataset.labelEn : label.dataset.labelMy;});
    }
    if (language) {
        try {const saved = localStorage.getItem('pbr-reply-language');if (['auto', 'my', 'en'].includes(saved)) language.value = saved;} catch { /* storage is optional */ }
        language.addEventListener('change', () => {
            try {localStorage.setItem('pbr-reply-language', language.value);} catch { /* storage is optional */ }
            updateLanguage();
        });
    }
    updateLanguage();
    function showSources(body, sources) {
        if (!Array.isArray(sources) || !sources.length) return;
        const details = document.createElement('details');details.className = 'pbr-ai-sources';
        const summary = document.createElement('summary');summary.textContent = `Sources · ${sources.length}`;
        const list = document.createElement('ul');
        for (const source of sources) {
            const row = document.createElement('li'), label = `[${String(source.reference)}] ${String(source.title)}`;
            let target = null;
            try {target = new URL(source.url, window.location.origin);} catch { /* deleted source */ }
            if (source.url && target && target.origin === window.location.origin && target.pathname.startsWith('/portal/workspaces/')) {
                const link = document.createElement('a');link.href = target.href;link.textContent = label;row.append(link);
            } else row.textContent = label;
            const note = document.createElement('small');note.textContent = String(source.business_status || '');row.append(note);list.append(row);
        }
        details.append(summary, list);body.append(details);
    }
    function bubble(role, text) {
        const article = document.createElement('article');article.className = 'message ' + role;
        const avatar = document.createElement('span');avatar.className = 'message-avatar';avatar.setAttribute('aria-hidden', 'true');
        const template = document.getElementById('portal-assistant-avatar');
        if (role === 'assistant' && template) avatar.append(template.content.cloneNode(true));
        else if (role === 'user') avatar.textContent = document.querySelector('.pbr-user-avatar')?.textContent || '';
        const content = document.createElement('div');content.className = 'message-content';
        const body = document.createElement('div');body.className = 'message-body';
        const reply = document.createElement('div');reply.className = 'portal-reply';reply.textContent = text;body.append(reply);
        const meta = document.createElement('div');meta.className = 'message-meta';
        const time = document.createElement('time');time.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});meta.append(time);
        if (role === 'assistant') {
            const copy = document.createElement('button');copy.type = 'button';copy.className = 'reply-copy';copy.dataset.copyReply = '';copy.setAttribute('aria-label', 'Copy answer');copy.title = 'Copy';copy.disabled = true;
            const icon = document.getElementById('portal-copy-icon');
            if (icon) copy.append(icon.content.cloneNode(true));else copy.textContent = 'Copy';meta.append(copy);
        }
        content.append(body, meta);article.append(avatar, content);messages.append(article);
        return {article, reply};
    }
    document.querySelectorAll('[data-ai-prompt]').forEach(button => button.addEventListener('click', () => {
        if (busy || stale) return;
        input.value = (language?.value === 'en' ? button.dataset.aiPromptEn : button.dataset.aiPromptMy) || button.dataset.aiPrompt;
        resize();input.focus();
    }));
    input.addEventListener('input', resize);
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();if (!stale && !busy) form.requestSubmit();
        }
    });
    form.addEventListener('submit', async event => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || busy || stale) return;
        busy = true;following = true;send.disabled = true;input.disabled = true;if (language) language.disabled = true;
        error.textContent = '';status.textContent = labels('အဖြေပေးနေပါတယ်…', 'Replying…');
        document.getElementById('portal-welcome')?.remove();
        const userBubble = bubble('user', message), assistantBubble = bubble('assistant', '');
        assistantBubble.article.classList.add('is-pending');
        let text = '', completed = false, upstreamError = '';
        const abort = new AbortController(), timer = window.setTimeout(() => abort.abort(), 210000);
        scroll(true);
        try {
            const response = await fetch(root.dataset.endpoint, {
                method: 'POST', credentials: 'same-origin', signal: abort.signal,
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json, text/event-stream', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                body: JSON.stringify({message, reply_language: language?.value || 'auto', conversation_id: root.dataset.conversation ? Number(root.dataset.conversation) : null})
            });
            if (!response.ok) {
                const problem = await response.json().catch(() => ({}));
                throw new Error(problem.message || labels('ချိတ်ဆက်မှုကို ပြန်စစ်ပါ။', 'Check your connection and access.'));
            }
            if (!response.headers.get('content-type')?.includes('text/event-stream') || !response.body) throw new Error(labels('Login ပြန်ဝင်ပြီး ထပ်စမ်းပါ။', 'Sign in again and retry.'));
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
                            const deletion = document.getElementById('portal-delete-chat');
                            if (deletion) {deletion.action = `${deletion.dataset.deleteBase}/${data.conversationId}`;deletion.hidden = false;}
                            const url = new URL(window.location.href);url.search = '';url.searchParams.set('conversation', String(data.conversationId));window.history.replaceState(null, '', url);
                            if (root.dataset.workspace) showSources(assistantBubble.reply.parentElement, data.sources);
                            const history = document.querySelector('.history-list');
                            if (history && !history.querySelector(`[data-conversation="${data.conversationId}"]`)) {
                                history.querySelector('[data-empty-history]')?.remove();
                                const link = document.createElement('a');link.href = url.href;link.dataset.conversation = String(data.conversationId);link.textContent = message.slice(0, 72);history.prepend(link);
                            }
                        } else if (data.type === 'delta' && typeof data.text === 'string') {
                            text += data.text;assistantBubble.reply.textContent = text;assistantBubble.article.classList.remove('is-pending');scroll();
                        } else if (data.type === 'error') {
                            upstreamError = typeof data.text === 'string' ? data.text : labels('အဖြေ မပြီးဆုံးသေးပါ။', 'Incomplete response.');
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
            if (upstreamError || !completed || !text.trim()) throw new Error(upstreamError || labels('အဖြေ မပြီးဆုံးသေးပါ။ ထပ်စမ်းပါ။', 'Incomplete response. Please retry.'));
            input.value = '';status.textContent = labels('သိမ်းပြီးပါပြီ။', 'Saved.');
        } catch (problem) {
            error.textContent = problem.name === 'AbortError' ? labels('အချိန်ကြာနေပါတယ်။ ထပ်စမ်းပါ။', 'The response timed out. Please retry.') : problem.message;
            status.textContent = labels('ပြန်စမ်းနိုင်ပါတယ်။', 'You can retry.');
            if (!text) {userBubble.article.remove();assistantBubble.article.remove();input.value = message;}
            else input.value = '';
        } finally {
            if (text) {renderReply(assistantBubble.reply, text);assistantBubble.article.querySelector('[data-copy-reply]').disabled = false;}
            assistantBubble.article.classList.remove('is-pending');
            window.clearTimeout(timer);busy = false;send.disabled = stale;input.disabled = stale;if (language) language.disabled = false;
            resize();scroll();input.focus();
        }
    });
    resize();scroll(true);
})();
