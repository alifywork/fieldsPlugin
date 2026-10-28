/* global AlifyPostObject */
(function () {
    'use strict';
    const requests = new WeakMap();
    function pagination(root) {
        const page = Number(root.dataset.page || 0), blocked = root.dataset.busy === '1' || !!root.closest('.alify-value[hidden]');
        root.querySelector('[data-post-prev]').disabled = blocked || page <= 1;
        root.querySelector('[data-post-next]').disabled = blocked || !page || page >= 100;
    }
    document.addEventListener('alify:visibility', () => document.querySelectorAll('.alify-post-object').forEach(pagination));
    function reset(event) {
        if (!event.target.matches('[data-post-search], [data-post-type]')) return;
        const root = event.target.closest('.alify-post-object'), controller = requests.get(root);
        if (controller) controller.abort(); requests.delete(root);
        delete root.dataset.page; delete root.dataset.busy;
        root.querySelector('.alify-post-results').replaceChildren(); root.querySelector('.alify-post-status').textContent = '';
        pagination(root);
    }
    document.addEventListener('input', reset); document.addEventListener('change', reset);
    document.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.target.matches('[data-post-search]')) {
            event.preventDefault(); event.target.closest('.alify-post-object').querySelector('[data-post-find]').click();
        }
    });
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-post-clear], [data-post-find], [data-post-prev], [data-post-next], [data-post-id]');
        if (!button || button.disabled) return;
        const root = button.closest('.alify-post-object'), input = root.querySelector('input[type="hidden"]');
        if (button.hasAttribute('data-post-id') && root.classList.contains('alify-relationship')) {
            if (window.AlifyRelationship) window.AlifyRelationship.add(root, button.dataset.postId, button.textContent);
            return;
        }
        if (button.hasAttribute('data-post-clear') || button.hasAttribute('data-post-id')) {
            input.value = button.dataset.postId || '';
            root.querySelector('.alify-post-selected').textContent = button.dataset.postId ? button.textContent : 'No post selected.';
            input.dispatchEvent(new Event('change', {bubbles: true}));
            root.querySelector('[data-post-find]').focus(); return;
        }
        const previous = requests.get(root);
        if (previous) previous.abort();
        const controller = new AbortController(); requests.set(root, controller);
        const page = button.hasAttribute('data-post-next') ? Number(root.dataset.page || 1) + 1 : button.hasAttribute('data-post-prev') ? Number(root.dataset.page || 1) - 1 : 1;
        const results = root.querySelector('.alify-post-results'), status = root.querySelector('.alify-post-status');
        root.dataset.busy = '1';
        results.replaceChildren(); status.textContent = 'Searching…';
        root.querySelector('[data-post-prev]').disabled = true; root.querySelector('[data-post-next]').disabled = true;
        try {
            const body = new URLSearchParams({action: 'alify_cf_search_posts', nonce: AlifyPostObject.nonce, search: root.querySelector('[data-post-search]').value, type: root.querySelector('[data-post-type]').value, page: String(page)});
            const response = await fetch(AlifyPostObject.url, {method: 'POST', body, credentials: 'same-origin', signal: controller.signal});
            const json = await response.json();
            if (requests.get(root) !== controller) return;
            if (!root.isConnected || root.closest('.alify-value[hidden]')) { delete root.dataset.busy; pagination(root); return; }
            if (!response.ok || !json.success) throw new Error(json.data && json.data.message || 'Search failed. Reload and try again.');
            for (const item of json.data.items) {
                const choose = document.createElement('button'); choose.type = 'button'; choose.className = 'button'; choose.dataset.postId = String(item.id);
                choose.textContent = item.title + ' (' + item.type + ')'; results.appendChild(choose);
            }
            root.dataset.page = String(page); status.textContent = 'Page ' + page + (json.data.items.length ? '' : ': No readable matches on this page.');
            delete root.dataset.busy; pagination(root);
        } catch (error) {
            if (error.name !== 'AbortError' && requests.get(root) === controller) {
                status.textContent = 'Search unavailable. Reload the editor or try again.'; delete root.dataset.busy; pagination(root);
            }
        }
    });
})();
