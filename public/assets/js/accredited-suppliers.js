(() => {
    const form = document.getElementById('directory-filters');
    const category = document.getElementById('directory-category');
    const search = document.getElementById('directory-search');
    const results = document.getElementById('directory-results');
    const status = document.getElementById('directory-refresh-status');
    let controller;
    let debounce;
    let currentPage = Number(results.dataset.page) || 1;

    async function refresh(page = 1) {
        clearTimeout(debounce);
        if (controller) controller.abort();
        const request = new AbortController();
        controller = request;
        const url = new URL(form.action, window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        url.searchParams.set('page', page);
        results.setAttribute('aria-busy', 'true');
        status.textContent = 'Updating suppliers...';
        try {
            const response = await fetch(url, { signal: request.signal, cache: 'no-store' });
            if (!response.ok || response.redirected) throw new Error('Unable to refresh');
            const document = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = document.getElementById('directory-results');
            const options = document.getElementById('directory-category');
            if (!updated || !options || updated.dataset.loadError === '1') throw new Error('Unable to refresh');
            if (request.signal.aborted) return;
            results.innerHTML = updated.innerHTML;
            category.innerHTML = options.innerHTML;
            currentPage = Number(updated.dataset.page) || 1;
            url.searchParams.set('page', currentPage);
            window.history.replaceState(null, '', url);
            status.textContent = 'Updated ' + new Date().toLocaleTimeString() + '. Refreshes every 60 seconds.';
        } catch (error) {
            if (error.name !== 'AbortError') {
                status.textContent = 'Could not refresh. Displayed results may be outdated. Click Search to retry, or reload the page if your session has expired.';
            }
        } finally {
            if (controller === request) {
                controller = null;
                results.setAttribute('aria-busy', 'false');
            }
        }
    }

    form.addEventListener('submit', event => {
        event.preventDefault();
        refresh(1);
    });
    category.addEventListener('change', () => refresh(1));
    search.addEventListener('input', () => {
        clearTimeout(debounce);
        if (controller) controller.abort();
        currentPage = 1;
        debounce = setTimeout(() => refresh(1), 400);
    });
    results.addEventListener('click', event => {
        const link = event.target.closest('nav a');
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        refresh(Number(new URL(link.href).searchParams.get('page')) || 1);
    });
    setInterval(() => {
        if (!document.hidden && !controller) refresh(currentPage);
    }, 60000);
})();
