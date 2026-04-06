(() => {
    const root = document.documentElement;
    const body = document.body;
    const themeToggle = document.getElementById('themeToggle');
    const savedTheme = localStorage.getItem('theme');

    const applyTheme = (theme) => {
        const isDark = theme === 'dark';
        body.classList.add('theme-switching');
        root.classList.toggle('theme-dark', isDark);
        root.classList.toggle('theme-light', !isDark);
        root.dataset.theme = theme;
        body.classList.toggle('theme-dark', isDark);
        body.classList.toggle('theme-light', !isDark);
        body.dataset.theme = theme;
        root.style.colorScheme = isDark ? 'dark' : 'light';
        window.dispatchEvent(new CustomEvent('yjh:theme-changed', { detail: { theme } }));
        window.setTimeout(() => body.classList.remove('theme-switching'), 260);
    };

    if (savedTheme === 'dark' || savedTheme === 'light') {
        applyTheme(savedTheme);
    }

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const isDark = root.classList.contains('theme-dark');
            const nextTheme = isDark ? 'light' : 'dark';
            applyTheme(nextTheme);
            localStorage.setItem('theme', nextTheme);
        });
    }

    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('appSidebar');
    if (mobileMenuBtn && sidebar) {
        mobileMenuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.shiftKey && event.key.toLowerCase() === 'p') {
            window.location.href = '/projects.php';
        }
        if (event.shiftKey && event.key.toLowerCase() === 'b') {
            window.location.href = '/bug.php';
        }
        if (event.shiftKey && event.key.toLowerCase() === 't') {
            window.location.href = '/testcase.php';
        }
        if (event.shiftKey && event.key.toLowerCase() === 'd') {
            window.location.href = '/index.php';
        }
        if (event.shiftKey && event.key.toLowerCase() === 'k') {
            window.location.href = '/kanban.php';
        }
        if (event.shiftKey && event.key.toLowerCase() === 'w') {
            window.location.href = '/wiki.php';
        }
    });
})();

