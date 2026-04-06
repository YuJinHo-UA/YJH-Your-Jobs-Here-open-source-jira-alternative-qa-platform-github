(() => {
    const postAI = async (action, payload) => {
        const response = await fetch('/api/ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...payload }),
        });

        const raw = await response.text();
        let result = null;
        try {
            result = JSON.parse(raw);
        } catch (e) {
            const snippet = raw.replace(/\s+/g, ' ').trim().slice(0, 180);
            throw new Error(`Server returned non-JSON response (${response.status}). ${snippet}`);
        }

        if (!response.ok || !result.success) {
            throw new Error(result.error || 'AI request failed');
        }
        return result;
    };

    const setBusy = (button, busy) => {
        if (!button) return;
        button.disabled = busy;
        const label = button.dataset.label || button.textContent;
        button.dataset.label = label;
        button.textContent = busy ? 'AI working...' : label;
    };

    const toLines = (value) => Array.isArray(value) ? value.join('\n') : '';

    const initBugActions = () => {
        const assistBtn = document.querySelector('[data-ai-action="assist_bug"]');
        const dupBtn = document.querySelector('[data-ai-action="check_duplicates"]');
        const resultBox = document.querySelector('[data-ai-target="bug_result"]');
        const dupBox = document.querySelector('[data-ai-target="duplicates_result"]');
        const form = document.querySelector('form[data-draft-key^="bug-"]');
        if (!form) return;

        if (assistBtn && resultBox) {
            assistBtn.addEventListener('click', async () => {
                setBusy(assistBtn, true);
                try {
                    const title = form.querySelector('[name="title"]')?.value || '';
                    const description = form.querySelector('[name="description"]')?.value || '';
                    const { data } = await postAI('assist_bug', { title, description });

                    const steps = toLines(data.steps_to_reproduce);
                    const expected = toLines(data.expected_result);
                    const actual = toLines(data.actual_result);

                    const stepsField = form.querySelector('[name="steps_to_reproduce"]');
                    const expectedField = form.querySelector('[name="expected_result"]');
                    const actualField = form.querySelector('[name="actual_result"]');

                    if (stepsField && steps) stepsField.value = steps;
                    if (expectedField && expected) expectedField.value = expected;
                    if (actualField && actual) actualField.value = actual;

                    resultBox.classList.remove('d-none');
                    resultBox.textContent = data.analysis || 'AI filled bug fields.';
                } catch (error) {
                    resultBox.classList.remove('d-none');
                    resultBox.textContent = 'Error: ' + error.message;
                } finally {
                    setBusy(assistBtn, false);
                }
            });
        }

        if (dupBtn && dupBox) {
            dupBtn.addEventListener('click', async () => {
                setBusy(dupBtn, true);
                try {
                    const title = form.querySelector('[name="title"]')?.value || '';
                    const description = form.querySelector('[name="description"]')?.value || '';
                    const { data } = await postAI('check_duplicates', { title, description });
                    const list = Array.isArray(data.duplicates) ? data.duplicates : [];

                    if (list.length === 0) {
                        dupBox.classList.remove('d-none');
                        dupBox.innerHTML = '<div class="text-muted">No similar bugs found.</div>';
                        return;
                    }

                    dupBox.classList.remove('d-none');
                    dupBox.innerHTML = list.map((item) => {
                        const id = Number(item.id || 0);
                        const similarity = Number(item.similarity || 0);
                        const reason = String(item.reason || '');
                        return `<div class="mb-2"><a href="/bug.php?id=${id}">#${id}</a> <strong>${similarity}%</strong> ${reason}</div>`;
                    }).join('');
                } catch (error) {
                    dupBox.classList.remove('d-none');
                    dupBox.textContent = 'Error: ' + error.message;
                } finally {
                    setBusy(dupBtn, false);
                }
            });
        }
    };

    const initTestCaseAction = () => {
        const btn = document.querySelector('[data-ai-action="generate_test_cases"]');
        const box = document.querySelector('[data-ai-target="test_cases_result"]');
        const form = document.querySelector('form[data-draft-key^="testcase-"]');
        if (!btn || !box || !form) return;

        btn.addEventListener('click', async () => {
            setBusy(btn, true);
            try {
                const description = form.querySelector('[name="description"]')?.value || '';
                const preconditions = form.querySelector('[name="preconditions"]')?.value || '';
                const feature = [description, preconditions].filter(Boolean).join('\n');
                const { data } = await postAI('generate_test_cases', { feature });
                const first = Array.isArray(data.cases) ? data.cases[0] : null;

                if (!first) {
                    box.classList.remove('d-none');
                    box.textContent = 'AI returned no test cases.';
                    return;
                }

                const titleField = form.querySelector('[name="title"]');
                const stepsField = form.querySelector('[name="steps"]');
                const expectedField = form.querySelector('[name="expected"]');
                const checklistField = form.querySelector('[name="checklist"]');

                if (titleField && !titleField.value) titleField.value = first.title || '';
                if (stepsField) stepsField.value = toLines(first.steps);
                if (expectedField) expectedField.value = toLines(first.expected);
                if (checklistField) checklistField.value = toLines(first.preconditions);

                const count = Array.isArray(data.cases) ? data.cases.length : 1;
                box.classList.remove('d-none');
                box.textContent = `Generated ${count} test case(s). First one applied to form.`;
            } catch (error) {
                box.classList.remove('d-none');
                box.textContent = 'Error: ' + error.message;
            } finally {
                setBusy(btn, false);
            }
        });
    };

    const initReportAction = () => {
        const btn = document.querySelector('[data-ai-action="generate_report"]');
        const box = document.querySelector('[data-ai-target="report_result"]');
        if (!btn || !box) return;

        btn.addEventListener('click', async () => {
            setBusy(btn, true);
            try {
                const { data, meta } = await postAI('generate_report', { days: 7 });
                box.classList.remove('d-none');
                if (data.summary) {
                    box.innerHTML = `<div class="mb-2"><strong>Summary:</strong> ${data.summary}</div>` +
                        `<div class="mb-2"><strong>Risks:</strong> ${(data.risks || []).join('; ')}</div>` +
                        `<div><strong>Recommendations:</strong> ${(data.recommendations || []).join('; ')}</div>` +
                        `<div class="text-muted small mt-2">Model: ${meta?.model || '-'}</div>`;
                } else {
                    box.textContent = JSON.stringify(data, null, 2);
                }
            } catch (error) {
                box.classList.remove('d-none');
                box.textContent = 'Error: ' + error.message;
            } finally {
                setBusy(btn, false);
            }
        });
    };

    const appendChatMessage = (wrap, role, text) => {
        const row = document.createElement('div');
        row.className = 'mb-2';
        const who = role === 'user' ? 'You' : 'AI';
        row.innerHTML = `<div class="small text-muted">${who}</div><div>${text}</div>`;
        wrap.appendChild(row);
        wrap.scrollTop = wrap.scrollHeight;
    };

    const initChatWidget = () => {
        const toggleBtn = document.getElementById('aiChatToggle');
        const modalEl = document.getElementById('aiChatModal');
        const messagesEl = document.getElementById('aiChatMessages');
        const inputEl = document.getElementById('aiChatInput');
        const sendBtn = document.getElementById('aiChatSend');
        if (!toggleBtn || !modalEl || !messagesEl || !inputEl || !sendBtn || !window.bootstrap) return;

        const modal = new window.bootstrap.Modal(modalEl);
        toggleBtn.addEventListener('click', () => modal.show());

        const send = async () => {
            const message = inputEl.value.trim();
            if (!message) return;
            appendChatMessage(messagesEl, 'user', message);
            inputEl.value = '';
            setBusy(sendBtn, true);
            try {
                const { data } = await postAI('chat', { message, page: window.location.pathname });
                const answer = data.answer || data.raw || JSON.stringify(data);
                appendChatMessage(messagesEl, 'assistant', answer);
            } catch (error) {
                appendChatMessage(messagesEl, 'assistant', 'Error: ' + error.message);
            } finally {
                setBusy(sendBtn, false);
            }
        };

        sendBtn.addEventListener('click', send);
        inputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                send();
            }
        });

        if (!messagesEl.dataset.initialized) {
            appendChatMessage(messagesEl, 'assistant', 'Chat is ready. Ask any QA-related question.');
            messagesEl.dataset.initialized = '1';
        }
    };

    document.addEventListener('DOMContentLoaded', () => {
        initBugActions();
        initTestCaseAction();
        initReportAction();
        initChatWidget();
    });
})();
