document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.WORKSHOP_BOOTSTRAP || {};
    const chatContainer   = document.getElementById('chatContainer');
    const chatForm        = document.getElementById('chatForm');
    const messageInput    = document.getElementById('messageInput');
    const sendBtn         = document.getElementById('sendBtn');
    const statusText      = document.getElementById('statusText');
    const progressTrack   = document.getElementById('progressTrack');
    const gradeOverlay    = document.getElementById('gradeOverlay');
    const gradeScoreValue = document.getElementById('gradeScoreValue');
    const gradeStars      = document.getElementById('gradeStars');
    const gradeFeedback   = document.getElementById('gradeFeedbackText');
    const gradeStatus     = document.getElementById('gradeStatusText');

    let isSending = false;
    let typingRow = null;
    let currentStep = cfg.currentStep || 1;
    const totalSteps = cfg.totalSteps || 5;
    const stepLabels = cfg.stepLabels || {};

    if (window.marked) {
        const renderer = new marked.Renderer();
        renderer.link = function (href, title, text) {
            const safeTitle = title ? ` title="${title}"` : '';
            return `<a href="${href}"${safeTitle} target="_blank" rel="noopener noreferrer">${text}</a>`;
        };
        marked.setOptions({ renderer, breaks: true, gfm: true });
    }

    renderProgress(currentStep);
    initChat();

    messageInput.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight < 120 ? this.scrollHeight : 120) + 'px';
    });

    messageInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
            e.preventDefault();
            sendMessage();
        }
    });

    chatForm.addEventListener('submit', (e) => {
        e.preventDefault();
        sendMessage();
    });

    async function sendMessage() {
        if (isSending) return;
        const text = messageInput.value.trim();
        if (!text) return;

        isSending = true;
        appendMessage('user', text);
        messageInput.value = '';
        messageInput.style.height = 'auto';
        setLoading(true);

        try {
            const res = await callApi({ action: 'send', message: text });
            if (res.success) {
                appendMessage('assistant', res.reply);
                handleStepUpdate(res.step);
                handleGrade(res.grade);
            } else {
                appendMessage('assistant', '⚠️ ' + (res.error || 'پاسخی دریافت نشد.'));
            }
        } catch (err) {
            appendMessage('assistant', '⚠️ ارتباط با سرور برقرار نشد.');
        } finally {
            setLoading(false);
            isSending = false;
        }
    }

    async function initChat() {
        setLoading(true);
        try {
            const res = await callApi({ action: 'init' });
            if (res.success) {
                if (res.messages && res.messages.length) {
                    res.messages.forEach((m) => appendMessage(m.role, m.content));
                    if (res.total_steps) renderProgress(res.current_step || currentStep);
                    if (res.grade_submitted) showGradeCard(res.grade_submitted);
                } else if (res.reply) {
                    appendMessage('assistant', res.reply);
                    handleStepUpdate(res.step);
                    handleGrade(res.grade);
                }
            } else {
                appendMessage('assistant', '⚠️ ' + (res.error || 'خطا در بارگذاری کارگاه.'));
            }
        } catch (err) {
            appendMessage('assistant', '⚠️ ارتباط با سرور برقرار نشد.');
        } finally {
            setLoading(false);
        }
    }

    async function callApi(body) {
        const res = await fetch('chat-api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        return res.json();
    }

    function handleStepUpdate(step) {
        if (step) {
            currentStep = step;
            renderProgress(currentStep);
        }
    }

    function handleGrade(grade) {
        if (grade) showGradeCard(grade);
    }

    function appendMessage(role, text) {
        const isUser = role === 'user';
        const row = document.createElement('div');
        row.className = `message-row ${isUser ? 'from-user' : 'from-assistant'}`;

        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${isUser ? 'user-bubble' : 'bot-bubble'}`;

        const textSpan = document.createElement('span');
        textSpan.className = 'message-text';
        textSpan.innerHTML = renderContent(text, isUser);

        bubble.appendChild(textSpan);
        row.appendChild(bubble);
        chatContainer.appendChild(row);
        scrollToBottom();
    }

    function renderContent(text, isUser) {
        if (isUser || !window.marked || !window.DOMPurify) {
            return escapeHtml(text).replace(/\n/g, '<br>');
        }
        const rawHtml = marked.parse(text);
        return DOMPurify.sanitize(rawHtml, { ADD_ATTR: ['target', 'rel'] });
    }

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showTypingBubble() {
        if (typingRow) return;
        typingRow = document.createElement('div');
        typingRow.className = 'typing-row';
        typingRow.innerHTML = `<div class="typing-bubble"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>`;
        chatContainer.appendChild(typingRow);
        scrollToBottom();
    }

    function hideTypingBubble() {
        if (typingRow) {
            typingRow.remove();
            typingRow = null;
        }
    }

    function setLoading(isLoading) {
        sendBtn.disabled = isLoading;
        if (isLoading) {
            statusText.textContent = 'مربی در حال فکر کردنه...';
            showTypingBubble();
        } else {
            statusText.textContent = 'آماده‌ی گفتگو 👋';
            hideTypingBubble();
        }
        scrollToBottom();
    }

    function scrollToBottom() {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }

    function renderProgress(active) {
        progressTrack.innerHTML = '';
        for (let i = 1; i <= totalSteps; i++) {
            const wrap = document.createElement('div');
            wrap.className = 'progress-step';
            if (i < active) wrap.classList.add('is-done');
            if (i === active) wrap.classList.add('is-current');

            const dot = document.createElement('div');
            dot.className = 'progress-dot';
            dot.textContent = i < active ? '✓' : i;
            dot.title = stepLabels[i] || `مرحله ${i}`;
            wrap.appendChild(dot);
            progressTrack.appendChild(wrap);

            if (i < totalSteps) {
                const line = document.createElement('div');
                line.className = 'progress-line';
                progressTrack.appendChild(line);
            }
        }
    }

    function showGradeCard(grade) {
        const score = grade.score ?? 0;
        const max = grade.max || 20;
        gradeScoreValue.textContent = `${toPersianDigits(score)} / ${toPersianDigits(max)}`;

        const starCount = Math.max(1, Math.min(5, Math.round((score / max) * 5)));
        gradeStars.textContent = '★'.repeat(starCount) + '☆'.repeat(5 - starCount);

        gradeFeedback.textContent = grade.feedback || '';

        if (grade.message) {
            gradeStatus.textContent = grade.status === 'success' ? ('✅ ' + grade.message) : ('⚠️ ' + grade.message);
            gradeStatus.classList.toggle('is-error', grade.status !== 'success');
        } else {
            gradeStatus.textContent = '';
        }

        renderProgress(totalSteps + 1); // همه‌ی مراحل تیک بخورند
        gradeOverlay.classList.remove('hidden');
    }

    window.closeGradeOverlay = function () {
        gradeOverlay.classList.add('hidden');
    };

    window.resetChat = async function () {
        if (!confirm('آیا مایلی از اول شروع کنی؟ گفتگوی فعلی پاک می‌شود.')) return;
        chatContainer.innerHTML = '';
        gradeOverlay.classList.add('hidden');
        currentStep = 1;
        renderProgress(1);
        setLoading(true);
        await callApi({ action: 'reset' });
        initChat();
    };

    function toPersianDigits(num) {
        const map = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return String(num).replace(/\d/g, (d) => map[d]);
    }
});
