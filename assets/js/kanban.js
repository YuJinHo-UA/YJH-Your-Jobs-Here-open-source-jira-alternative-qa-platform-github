(() => {
    const t = (value) => (window.uiT ? window.uiT(value) : value);
    const columns = document.querySelectorAll('.kanban-column');
    const form = document.getElementById('kanbanCardForm');
    const cardIdField = document.getElementById('kanbanCardId');
    const submitBtn = document.getElementById('kanbanCardSubmit');
    const cancelBtn = document.getElementById('kanbanCardCancel');
    const addColumnBtn = document.getElementById('kanbanAddColumnBtn');
    const columnModalEl = document.getElementById('kanbanColumnModal');
    const columnNameInput = document.getElementById('kanbanColumnNameInput');
    const columnCreateSubmit = document.getElementById('kanbanColumnCreateSubmit');
    const columnModal = columnModalEl && window.bootstrap ? new window.bootstrap.Modal(columnModalEl) : null;
<<<<<<< HEAD
    const cardTypeSelect = document.getElementById('kanbanCardType');
    const bugPickerWrap = document.getElementById('kanbanBugPickerWrap');
    const testCasePickerWrap = document.getElementById('kanbanTestCasePickerWrap');
=======
>>>>>>> 7e7a5ae49ac6caacc4b2a0ad95dd06bd60dfa616

    if (columnModalEl) {
        const modalTitle = columnModalEl.querySelector('.modal-title');
        const label = columnModalEl.querySelector('label[for="kanbanColumnNameInput"]');
        const cancel = columnModalEl.querySelector('[data-bs-dismiss="modal"]');
        if (modalTitle) modalTitle.textContent = t('Create column');
        if (label) label.textContent = t('Column name');
        if (cancel) cancel.textContent = t('Cancel');
        if (columnCreateSubmit) columnCreateSubmit.textContent = t('Create');
    }
    let dragged = null;
    let dragInProgress = false;

    const apiCall = async (payload) => {
        const response = await fetch('/api/kanban.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok) {
            throw new Error(data.error || 'Request failed');
        }
        return data;
    };

    const fillFormFromCard = (card) => {
        if (!form) return;
        if (cardTypeSelect) {
            cardTypeSelect.value = card.dataset.bugId
                ? 'bug'
                : (card.dataset.testCaseId ? 'test_case' : ((card.dataset.labels || '').includes('feature') ? 'feature' : 'task'));
        }
        form.querySelector('[name="title"]').value = card.dataset.title || '';
        form.querySelector('[name="description"]').value = card.dataset.description || '';
        form.querySelector('[name="labels"]').value = card.dataset.labels || '';
        form.querySelector('[name="due_date"]').value = card.dataset.dueDate || '';
        form.querySelector('[name="assignee_id"]').value = card.dataset.assigneeId || '';
        form.querySelector('[name="column_id"]').value = card.dataset.columnId || '';
        const bugField = form.querySelector('[name="source_bug_id"]');
        if (bugField) {
            bugField.value = card.dataset.bugId || '0';
        }
        const testCaseField = form.querySelector('[name="source_test_case_id"]');
        if (testCaseField) {
            testCaseField.value = card.dataset.testCaseId || '0';
        }
        if (cardIdField) {
            cardIdField.value = card.dataset.cardId || '';
        }

        if (submitBtn) {
            submitBtn.textContent = t('Save Card');
        }
        if (cancelBtn) {
            cancelBtn.classList.remove('d-none');
            cancelBtn.textContent = t('Cancel edit');
        }
        if (cardTypeSelect) {
            if (bugPickerWrap) {
                bugPickerWrap.classList.toggle('d-none', cardTypeSelect.value !== 'bug');
            }
            if (testCasePickerWrap) {
                testCasePickerWrap.classList.toggle('d-none', cardTypeSelect.value !== 'test_case');
            }
        }
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    columns.forEach((column) => {
        column.addEventListener('dragover', (event) => {
            event.preventDefault();
        });

        column.addEventListener('drop', async (event) => {
            event.preventDefault();
            if (!dragged) return;
            column.querySelector('.kanban-cards').appendChild(dragged);
            const cardId = dragged.dataset.cardId;
            const columnId = column.dataset.columnId;
            dragged.dataset.columnId = columnId;
            await apiCall({ action: 'move_card', card_id: cardId, column_id: columnId });
        });
    });

    document.querySelectorAll('.kanban-card').forEach((card) => {
        card.addEventListener('dragstart', () => {
            dragInProgress = true;
            dragged = card;
            card.classList.add('dragging');
        });

        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            dragged = null;
            setTimeout(() => {
                dragInProgress = false;
            }, 50);
        });

        card.addEventListener('click', (event) => {
            if (dragInProgress) return;
            if (event.target.closest('.dropdown')) return;
            fillFormFromCard(card);
        });
    });

    document.querySelectorAll('.kanban-card-edit').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const cardId = btn.dataset.cardId;
            const card = document.querySelector(`.kanban-card[data-card-id="${cardId}"]`);
            if (!card) return;

            if (card.querySelector('.kanban-card-inline-editor')) {
                return;
            }

            const titleEl = card.querySelector('.fw-semibold');
            const descEl = card.querySelector('.text-muted.small');
            const currentTitle = card.dataset.title || '';
            const currentDescription = card.dataset.description || '';

            const body = document.createElement('div');
            body.className = 'kanban-card-inline-editor mt-2';
            body.innerHTML = `
                <input type="text" class="form-control form-control-sm mb-2" value="${currentTitle.replace(/"/g, '&quot;')}">
                <textarea class="form-control form-control-sm mb-2" rows="3">${currentDescription}</textarea>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary">${t('Save')}</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary">${t('Cancel')}</button>
                    <button type="button" class="btn btn-sm btn-outline-danger">${t('Delete card')}</button>
                </div>
            `;

            const closeInlineEditor = () => {
                body.remove();
                if (titleEl) titleEl.classList.remove('d-none');
                if (descEl) descEl.classList.remove('d-none');
            };

            if (titleEl) titleEl.classList.add('d-none');
            if (descEl) descEl.classList.add('d-none');
            card.appendChild(body);

            const input = body.querySelector('input');
            const textarea = body.querySelector('textarea');
            const saveBtn = body.querySelector('.btn-primary');
            const cancelBtnLocal = body.querySelector('.btn-outline-secondary');
            const deleteBtnLocal = body.querySelector('.btn-outline-danger');

            saveBtn.addEventListener('click', async () => {
                const nextTitle = input.value.trim();
                const nextDescription = textarea.value.trim();
                if (!nextTitle) return;
                try {
                    await apiCall({
                        action: 'update_card',
                        card_id: cardId,
                        title: nextTitle,
                        description: nextDescription
                    });

                    card.dataset.title = nextTitle;
                    card.dataset.description = nextDescription;
                    if (titleEl) titleEl.textContent = nextTitle;
                    if (descEl) descEl.textContent = nextDescription;
                    closeInlineEditor();
                } catch (error) {
                    alert(error.message || t('Request failed'));
                }
            });

            cancelBtnLocal.addEventListener('click', closeInlineEditor);
            deleteBtnLocal.addEventListener('click', async () => {
                if (!confirm(t('Delete card?'))) return;
                try {
                    await apiCall({ action: 'delete_card', card_id: cardId });
                    card.remove();
                } catch (error) {
                    alert(error.message || t('Unable to delete card'));
                }
            });

            input.focus();
            input.select();
        });
    });

    document.querySelectorAll('.kanban-card-delete').forEach((btn) => {
        btn.addEventListener('click', async (event) => {
            event.preventDefault();
            const cardId = btn.dataset.cardId;
            if (!confirm(t('Delete card?'))) return;

            try {
                await apiCall({ action: 'delete_card', card_id: cardId });
                const card = document.querySelector(`.kanban-card[data-card-id="${cardId}"]`);
                if (card) {
                    card.remove();
                }
            } catch (error) {
                alert(error.message || t('Unable to delete card'));
            }
        });
    });

    document.querySelectorAll('.kanban-column-edit').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const columnId = btn.dataset.columnId;
            const column = document.querySelector(`.kanban-column[data-column-id="${columnId}"]`);
            if (!column) return;

            const titleEl = column.querySelector('.kanban-column-title');
            if (!titleEl) return;
            if (column.querySelector('.kanban-column-editor')) return;

            const currentName = titleEl.textContent.trim();
            titleEl.classList.add('d-none');

            const editor = document.createElement('div');
            editor.className = 'kanban-column-editor d-flex align-items-center gap-1';
            editor.innerHTML = `
                <input type="text" class="form-control form-control-sm" value="${currentName.replace(/"/g, '&quot;')}">
                <button type="button" class="btn btn-sm btn-primary">${t('Save')}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary">${t('Cancel')}</button>
            `;

            const parent = titleEl.parentElement;
            parent.insertBefore(editor, titleEl.nextSibling);

            const input = editor.querySelector('input');
            const saveBtn = editor.querySelector('.btn-primary');
            const cancelBtnLocal = editor.querySelector('.btn-outline-secondary');

            const closeEditor = () => {
                editor.remove();
                titleEl.classList.remove('d-none');
            };

            saveBtn.addEventListener('click', async () => {
                const nextName = input.value.trim();
                if (!nextName) return;
                try {
                    await apiCall({ action: 'update_column', column_id: columnId, name: nextName });
                    titleEl.textContent = nextName;
                    closeEditor();
                } catch (error) {
                    alert(error.message || t('Unable to update column'));
                }
            });

            cancelBtnLocal.addEventListener('click', closeEditor);
            input.focus();
            input.select();
        });
    });

    document.querySelectorAll('.kanban-column-delete').forEach((btn) => {
        btn.addEventListener('click', async (event) => {
            event.preventDefault();
            const columnId = btn.dataset.columnId;
            if (!confirm(t('Delete column?'))) return;
            try {
                await apiCall({ action: 'delete_column', column_id: columnId });
                const column = document.querySelector(`.kanban-column[data-column-id="${columnId}"]`);
                if (column) {
                    column.remove();
                }
            } catch (error) {
                alert(error.message || t('Column is not empty'));
            }
        });
    });

    if (cancelBtn && form && submitBtn && cardIdField) {
        cancelBtn.addEventListener('click', () => {
            form.reset();
            cardIdField.value = '';
            submitBtn.textContent = t('Add Card');
            cancelBtn.classList.add('d-none');
            if (cardTypeSelect) {
                if (bugPickerWrap) {
                    bugPickerWrap.classList.toggle('d-none', cardTypeSelect.value !== 'bug');
                }
                if (testCasePickerWrap) {
                    testCasePickerWrap.classList.toggle('d-none', cardTypeSelect.value !== 'test_case');
                }
            }
        });
    }

    if (cardTypeSelect) {
        const toggleBugPicker = () => {
            if (bugPickerWrap) {
                bugPickerWrap.classList.toggle('d-none', cardTypeSelect.value !== 'bug');
            }
            if (testCasePickerWrap) {
                testCasePickerWrap.classList.toggle('d-none', cardTypeSelect.value !== 'test_case');
            }
        };
        cardTypeSelect.addEventListener('change', toggleBugPicker);
        toggleBugPicker();
    }

    if (addColumnBtn) {
        addColumnBtn.addEventListener('click', () => {
            if (!columnModal || !columnNameInput) return;
            columnNameInput.value = '';
            columnModal.show();
            setTimeout(() => columnNameInput.focus(), 120);
        });
    }

    if (columnCreateSubmit) {
        columnCreateSubmit.addEventListener('click', async () => {
            const boardId = addColumnBtn ? addColumnBtn.dataset.boardId : '';
            const name = columnNameInput ? columnNameInput.value.trim() : '';
            if (!name) return;

            try {
                await apiCall({
                    action: 'create_column',
                    board_id: boardId,
                    name
                });
                window.location.reload();
            } catch (error) {
                alert(error.message || t('Unable to create column'));
            }
        });
    }

    if (columnNameInput && columnCreateSubmit) {
        columnNameInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                columnCreateSubmit.click();
            }
        });
    }

    if (addColumnBtn) {
        addColumnBtn.addEventListener('click', () => {
            if (!columnModal || !columnNameInput) return;
            columnNameInput.value = '';
            columnModal.show();
            setTimeout(() => columnNameInput.focus(), 120);
        });
    }

    if (columnCreateSubmit) {
        columnCreateSubmit.addEventListener('click', async () => {
            const boardId = addColumnBtn ? addColumnBtn.dataset.boardId : '';
            const name = columnNameInput ? columnNameInput.value.trim() : '';
            if (!name) return;

            try {
                await apiCall({
                    action: 'create_column',
                    board_id: boardId,
                    name
                });
                window.location.reload();
            } catch (error) {
                alert(error.message || t('Unable to create column'));
            }
        });
    }

    if (columnNameInput && columnCreateSubmit) {
        columnNameInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                columnCreateSubmit.click();
            }
        });
    }
})();
