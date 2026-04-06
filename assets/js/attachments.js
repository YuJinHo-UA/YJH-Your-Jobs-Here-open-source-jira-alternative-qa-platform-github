(() => {
    const form = document.getElementById('bugAttachmentForm');
    const dropzone = document.getElementById('bugAttachmentDropzone');
    const input = document.getElementById('bugAttachmentInput');
    const list = document.getElementById('bugAttachmentList');
    const previewModalEl = document.getElementById('bugAttachmentPreviewModal');
    const previewImage = document.getElementById('bugAttachmentPreviewImage');
    const previewTitle = document.getElementById('bugAttachmentPreviewTitle');
    const previewMeta = document.getElementById('bugAttachmentPreviewMeta');
    const previewPrevBtn = document.getElementById('bugAttachmentPrevBtn');
    const previewNextBtn = document.getElementById('bugAttachmentNextBtn');
    const previewOpenOriginal = document.getElementById('bugAttachmentOpenOriginal');
    const previewModal = previewModalEl && window.bootstrap ? new window.bootstrap.Modal(previewModalEl) : null;
    let currentIndex = -1;

    if (!form || !dropzone || !input || !list) {
        return;
    }

    const t = (value) => (window.uiT ? window.uiT(value) : value);
    const getItems = () => Array.from(list.querySelectorAll('a.attachment-item'));

    const renderPreviewAt = (index) => {
        const items = getItems();
        if (!items.length || !previewModal || !previewImage) return;
        currentIndex = (index + items.length) % items.length;
        const link = items[currentIndex];
        const img = link.querySelector('img');
        const filename = link.querySelector('.attachment-name')?.textContent || 'Screenshot';
        const meta = link.querySelector('.attachment-author')?.textContent || '';
        const href = link.getAttribute('href') || '';
        previewImage.src = href;
        previewImage.alt = filename;
        if (previewTitle) previewTitle.textContent = filename;
        if (previewMeta) previewMeta.textContent = meta;
        if (previewOpenOriginal) previewOpenOriginal.href = href;
    };

    const bindPreviewHandlers = (item) => {
        item.addEventListener('click', (event) => {
            if (!previewModal) return;
            event.preventDefault();
            const items = getItems();
            const idx = items.indexOf(item);
            renderPreviewAt(idx);
            previewModal.show();
        });
    };

    const renderItem = (file) => {
        const link = document.createElement('a');
        link.className = 'attachment-item';
        link.href = file.filepath;
        link.target = '_blank';
        link.rel = 'noopener';

        const img = document.createElement('img');
        img.src = file.filepath;
        img.alt = file.filename || 'attachment';
        img.loading = 'lazy';

        const meta = document.createElement('div');
        meta.className = 'attachment-meta';

        const name = document.createElement('div');
        name.className = 'attachment-name';
        name.textContent = file.filename || 'attachment';

        const author = document.createElement('div');
        author.className = 'attachment-author text-muted small';
        author.textContent = `${file.username || ''} | ${file.uploaded_at || ''}`;

        meta.appendChild(name);
        meta.appendChild(author);
        link.appendChild(img);
        link.appendChild(meta);
        list.prepend(link);
        bindPreviewHandlers(link);
    };

    const uploadFiles = async (files) => {
        if (!files || files.length === 0) {
            return;
        }

        const payload = new FormData();
        payload.append('csrf_token', form.querySelector('[name="csrf_token"]').value);
        payload.append('target_type', form.querySelector('[name="target_type"]').value);
        payload.append('target_id', form.querySelector('[name="target_id"]').value);
        Array.from(files).forEach((file) => payload.append('attachments[]', file));

        dropzone.classList.add('is-loading');
        try {
            const response = await fetch('/api/upload.php', {
                method: 'POST',
                body: payload
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.error || 'Upload failed');
            }

            (data.uploaded || []).forEach(renderItem);
            if ((data.errors || []).length) {
                alert(data.errors.join('\n'));
            }
        } catch (error) {
            alert(error.message || 'Upload failed');
        } finally {
            dropzone.classList.remove('is-loading');
            input.value = '';
        }
    };

    dropzone.addEventListener('click', () => input.click());
    input.addEventListener('change', (event) => uploadFiles(event.target.files));

    ['dragenter', 'dragover'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach((eventName) => {
        dropzone.addEventListener(eventName, (event) => {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', (event) => {
        uploadFiles(event.dataTransfer.files);
    });

    dropzone.addEventListener('paste', (event) => {
        const items = event.clipboardData?.items || [];
        const files = [];
        for (const item of items) {
            if (item.kind === 'file') {
                const file = item.getAsFile();
                if (file && file.type.startsWith('image/')) {
                    files.push(file);
                }
            }
        }
        if (files.length) {
            uploadFiles(files);
        }
    });

    getItems().forEach(bindPreviewHandlers);

    if (previewPrevBtn) {
        previewPrevBtn.addEventListener('click', () => renderPreviewAt(currentIndex - 1));
    }
    if (previewNextBtn) {
        previewNextBtn.addEventListener('click', () => renderPreviewAt(currentIndex + 1));
    }

    document.addEventListener('keydown', (event) => {
        if (!previewModalEl || !previewModalEl.classList.contains('show')) return;
        if (event.key === 'ArrowLeft') renderPreviewAt(currentIndex - 1);
        if (event.key === 'ArrowRight') renderPreviewAt(currentIndex + 1);
    });

    dropzone.title = t('Click, drag, or paste screenshots');
    if (previewPrevBtn) previewPrevBtn.textContent = t('Prev');
    if (previewNextBtn) previewNextBtn.textContent = t('Next');
    if (previewOpenOriginal) previewOpenOriginal.textContent = t('Open original');
})();
