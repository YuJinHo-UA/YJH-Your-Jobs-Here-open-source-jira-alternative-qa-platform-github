(() => {
    const lang = (document.documentElement.lang || 'en').toLowerCase();
    if (!['ru', 'en'].includes(lang)) {
        return;
    }

    const root = document.querySelector('.app-content');
    if (!root) {
        return;
    }

    const skipTags = new Set([
        'SCRIPT', 'STYLE', 'TEXTAREA', 'INPUT', 'SELECT', 'OPTION',
        'BUTTON', 'LABEL', 'TH', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6'
    ]);

    const textNodes = [];
    const uniqueTexts = [];
    const textToIndex = new Map();
    const MAX_TEXT_NODES = 500;
    const MAX_UNIQUE_TEXTS = 120;
    const MAX_TEXT_LENGTH = 500;
    const STORAGE_KEY = `yjh_translate_cache_${lang}`;

    const isLikelyUserContent = (text) => {
        const trimmed = text.trim();
        if (trimmed.length < 4 || trimmed.length > MAX_TEXT_LENGTH) {
            return false;
        }
        if (/^[#@\d\s.,:%()+\-/]+$/.test(trimmed)) {
            return false;
        }
        const hasLatin = /[A-Za-z]/.test(trimmed);
        const hasCyrillic = /[\u0400-\u04FF]/.test(trimmed);
        if (lang === 'ru' && hasLatin && !hasCyrillic) {
            return true;
        }
        if (lang === 'en' && hasCyrillic && !hasLatin) {
            return true;
        }
        return false;
    };

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
            const parent = node.parentElement;
            if (!parent) {
                return NodeFilter.FILTER_REJECT;
            }
            if (parent.closest('.app-header, .app-sidebar, .search-modal')) {
                return NodeFilter.FILTER_REJECT;
            }
            if (parent.closest('[data-no-auto-translate]')) {
                return NodeFilter.FILTER_REJECT;
            }
            if (skipTags.has(parent.tagName)) {
                return NodeFilter.FILTER_REJECT;
            }
            const text = node.nodeValue || '';
            return isLikelyUserContent(text) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
        }
    });

    let current = walker.nextNode();
    while (current) {
        const raw = current.nodeValue || '';
        const key = raw.trim();
        if (!textToIndex.has(key)) {
            if (uniqueTexts.length >= MAX_UNIQUE_TEXTS) {
                break;
            }
            textToIndex.set(key, uniqueTexts.length);
            uniqueTexts.push(key);
        }
        textNodes.push({
            node: current,
            key
        });
        if (textNodes.length >= MAX_TEXT_NODES) {
            break;
        }
        current = walker.nextNode();
    }

    if (!uniqueTexts.length) {
        return;
    }

    const resolveApiUrl = (endpoint) => {
        const script = document.currentScript || document.querySelector('script[src*="auto-translate.js"]');
        const src = script && script.src ? script.src : '';
        if (src) {
            const marker = '/assets/js/auto-translate.js';
            const idx = src.indexOf(marker);
            if (idx > -1) {
                const base = src.slice(0, idx);
                return `${base}${endpoint}`;
            }
        }
        return endpoint;
    };

    const requestTranslations = async (texts) => {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 3500);
        const response = await fetch(resolveApiUrl('/api/translate.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target: lang, texts }),
            signal: controller.signal
        });
        clearTimeout(timeout);
        if (!response.ok) {
            return null;
        }
        const payload = await response.json();
        return Array.isArray(payload.translations) ? payload.translations : null;
    };

    const readCache = () => {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    };

    const writeCache = (cache) => {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(cache));
        } catch (e) {
            // Ignore storage limits.
        }
    };

    const applyTranslatedNodes = (translated, cacheMap) => {
        textNodes.forEach(({ node, key }) => {
            const idx = textToIndex.get(key);
            if (idx === undefined) {
                return;
            }
            const value = translated[idx] || cacheMap[key] || key;
            if (!value || value === key) {
                return;
            }

            const raw = node.nodeValue || '';
            const leading = raw.match(/^\s*/)?.[0] ?? '';
            const trailing = raw.match(/\s*$/)?.[0] ?? '';
            node.nodeValue = `${leading}${value}${trailing}`;
        });
    };

    const run = async () => {
        const cache = readCache();
        const translated = new Array(uniqueTexts.length).fill(null);
        const missing = [];
        const missingIndexes = [];

        uniqueTexts.forEach((text, idx) => {
            const cached = cache[text];
            if (typeof cached === 'string' && cached.trim() !== '') {
                translated[idx] = cached;
            } else {
                missing.push(text);
                missingIndexes.push(idx);
            }
        });

        applyTranslatedNodes(translated, cache);

        if (!missing.length) {
            return;
        }

        const result = await requestTranslations(missing).catch(() => null);
        if (!result) {
            return;
        }

        for (let i = 0; i < missing.length; i++) {
            const original = missing[i];
            const value = (result[i] || original).trim();
            const safeValue = value !== '' ? value : original;
            cache[original] = safeValue;
            translated[missingIndexes[i]] = safeValue;
        }

        writeCache(cache);
        applyTranslatedNodes(translated, cache);
    };

    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(() => {
            run();
        }, { timeout: 1200 });
    } else {
        setTimeout(() => {
            run();
        }, 300);
    }
})();
