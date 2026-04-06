(() => {
    const input = document.getElementById('globalSearchInput');
    const modal = document.getElementById('searchModal');
    const modalInput = document.getElementById('searchModalInput');
    const modalResults = document.getElementById('searchModalResults');
    const modalClose = document.getElementById('searchModalClose');
    const t = (value) => (window.uiT ? window.uiT(value) : value);

    const resolveApiUrl = (endpoint) => {
        const script = document.currentScript || document.querySelector('script[src*="search.js"]');
        const src = script && script.src ? script.src : '';
        if (src) {
            const marker = '/assets/js/search.js';
            const idx = src.indexOf(marker);
            if (idx > -1) {
                const base = src.slice(0, idx);
                return `${base}${endpoint}`;
            }
        }
        return endpoint;
    };

    const openModal = () => {
        if (!modal || !modalInput || !modalResults) {
            return;
        }
        modal.classList.add('active');
        modalInput.value = '';
        modalResults.innerHTML = '';
        modalInput.focus();
    };

    const closeModal = () => {
        if (!modal) {
            return;
        }
        modal.classList.remove('active');
    };

    const renderResults = (data) => {
        modalResults.innerHTML = '';
        Object.keys(data).forEach((group) => {
            const section = document.createElement('div');
            section.className = 'search-result-group';
            const title = document.createElement('div');
            title.className = 'fw-semibold mb-1';
            title.textContent = t(group);
            section.appendChild(title);

            data[group].forEach((item) => {
                const row = document.createElement('div');
                row.className = 'search-result-item';
                row.textContent = item.title;
                row.addEventListener('click', () => {
                    window.location.href = item.url;
                });
                section.appendChild(row);
            });
            modalResults.appendChild(section);
        });
    };

    const runSearch = async (query) => {
        if (!query) {
            modalResults.innerHTML = '';
            return;
        }
        const res = await fetch(`${resolveApiUrl('/api/search.php')}?q=${encodeURIComponent(query)}`);
        const payload = await res.json();
        renderResults(payload.results || {});
    };

    if (input) {
        input.addEventListener('focus', openModal);
    }

    if (modalClose) {
        modalClose.addEventListener('click', closeModal);
    }

    document.addEventListener('keydown', (event) => {
        if ((event.metaKey || event.ctrlKey) && (event.code === 'KeyK' || event.key.toLowerCase() === 'k')) {
            event.preventDefault();
            openModal();
        }
        if (event.key === 'Escape' && modal && modal.classList.contains('active')) {
            closeModal();
        }
    });

    if (modalInput) {
        modalInput.addEventListener('input', (event) => {
            runSearch(event.target.value);
        });
    }
})();

