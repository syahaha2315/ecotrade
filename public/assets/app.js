(() => {
    const toggle = document.getElementById('theme-toggle');
    let theme = 'light';
    try { theme = localStorage.getItem('ecotrade-theme') || 'light'; } catch { }
    const applyTheme = () => {
        document.documentElement.dataset.theme = theme;
        toggle.textContent = theme === 'dark' ? 'Mod cerah' : 'Mod gelap';
        toggle.setAttribute('aria-pressed', String(theme === 'dark'));
    };
    toggle.hidden = false;
    applyTheme();
    toggle.addEventListener('click', () => {
        theme = theme === 'dark' ? 'light' : 'dark';
        applyTheme();
        try { localStorage.setItem('ecotrade-theme', theme); } catch { }
    });
    const loading = document.getElementById('loading-message');
    const busy = () => { loading.hidden = false; document.querySelector('main').setAttribute('aria-busy', 'true'); };
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', event => {
            if (!event.defaultPrevented) {
                busy();
                form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
            }
        });
    });
    document.querySelectorAll('a[href]').forEach(link => {
        link.addEventListener('click', event => {
            if (event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !link.hash) busy();
        });
    });
    window.addEventListener('pageshow', () => {
        loading.hidden = true;
        document.querySelector('main').removeAttribute('aria-busy');
        document.querySelectorAll('button[type="submit"]').forEach(button => {
            if (!button.hasAttribute('data-permanent-disabled')) button.disabled = false;
        });
    });
})();
