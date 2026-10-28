/* global AlifySelector */
(function () {
    'use strict';
    const requests = new WeakMap();
    function refresh(root) {
        const blocked = !!root.closest('.alify-value[hidden]'), busy = root.dataset.busy === '1';
        const rows = Array.from(root.querySelector('.alify-selector-selected').children);
        rows.forEach((row, i) => {
            const up = row.querySelector('[data-selector-up]'), down = row.querySelector('[data-selector-down]');
            if (up) up.disabled = blocked || i === 0;
            if (down) down.disabled = blocked || i === rows.length - 1;
        });
        root.querySelector('[data-selector-prev]').disabled = blocked || busy || Number(root.dataset.page || 1) <= 1;
        root.querySelector('[data-selector-next]').disabled = blocked || busy || !root.dataset.page || Number(root.dataset.page) >= 100;
    }
    function reset(root) {
        const controller = requests.get(root); if (controller) controller.abort(); requests.delete(root);
        delete root.dataset.busy; delete root.dataset.page; root.querySelector('.alify-selector-results').replaceChildren(); root.querySelector('[data-selector-status]').textContent = ''; refresh(root);
    }
    document.addEventListener('alify:visibility', () => {
        document.querySelectorAll('.alify-selector').forEach(root => { if (root.closest('.alify-value[hidden]')) reset(root); else refresh(root); });
    });
    document.addEventListener('input', event => { if (event.target.matches('[data-selector-search]')) reset(event.target.closest('.alify-selector')); });
    document.addEventListener('keydown', event => { if (event.key === 'Enter' && event.target.matches('[data-selector-search]')) { event.preventDefault(); event.target.closest('.alify-selector').querySelector('[data-selector-find]').click(); } });
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-selector-find], [data-selector-prev], [data-selector-next], [data-selector-pick], [data-selector-remove], [data-selector-up], [data-selector-down], [data-selector-clear]');
        if (!button || button.disabled) return;
        const root = button.closest('.alify-selector'); if (!root || root.closest('.alify-value[hidden]')) return;
        const list = root.querySelector('.alify-selector-selected'), status = root.querySelector('[data-selector-status]'), row = button.closest('.alify-selector-item');
        if (button.hasAttribute('data-selector-clear')) list.replaceChildren();
        if (button.hasAttribute('data-selector-remove')) row.remove();
        if (button.hasAttribute('data-selector-up') && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
        if (button.hasAttribute('data-selector-down') && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
        if (button.hasAttribute('data-selector-pick')) {
            const multi = root.dataset.multiple === '1', id = button.dataset.selectorPick;
            if (Array.from(list.querySelectorAll('input')).some(input => input.value === id)) { status.textContent = 'Already selected.'; return; }
            if (multi && list.children.length >= 50) { status.textContent = 'Maximum 50 selections.'; return; }
            if (!multi) list.replaceChildren();
            const item = document.createElement('div'); item.className = 'alify-selector-item';
            const input = document.createElement('input'); input.type = 'hidden'; input.value = id;
            input.name = multi ? root.querySelector('input[name$="[__complete]"]').name.replace(/\[__complete\]$/, '[ids][]') : root.querySelector('[data-selector-empty]').name;
            const label = document.createElement('span'); label.textContent = button.textContent; item.append(input, label);
            [['remove', 'Remove'], ...(multi ? [['up', 'Move earlier'], ['down', 'Move later']] : [])].forEach(([action, text]) => { const control = document.createElement('button'); control.type = 'button'; control.className = 'button'; control.setAttribute('data-selector-' + action, ''); control.textContent = text; item.appendChild(control); });
            list.appendChild(item); status.textContent = 'Selection added.';
        }
        if (!button.matches('[data-selector-find], [data-selector-prev], [data-selector-next]')) {
            refresh(root); root.dispatchEvent(new Event('change', {bubbles: true}));
            if (!button.isConnected || button.hasAttribute('data-selector-clear')) root.querySelector('[data-selector-find]').focus(); return;
        }
        const page = button.hasAttribute('data-selector-next') ? Number(root.dataset.page || 1) + 1 : button.hasAttribute('data-selector-prev') ? Number(root.dataset.page || 1) - 1 : 1;
        const previous = requests.get(root); if (previous) previous.abort();
        const controller = new AbortController(); requests.set(root, controller); root.dataset.busy = '1'; refresh(root);
        const results = root.querySelector('.alify-selector-results'); results.replaceChildren(); status.textContent = 'Searching…';
        try {
            const body = new URLSearchParams({action: 'alify_cf_search_objects', nonce: AlifySelector.nonce, settings: root.dataset.settings, search: root.querySelector('[data-selector-search]').value, page: String(page)});
            const response = await fetch(AlifySelector.url, {method: 'POST', body, credentials: 'same-origin', signal: controller.signal}); const json = await response.json();
            if (requests.get(root) !== controller || !root.isConnected || root.closest('.alify-value[hidden]')) return;
            if (!response.ok || !json.success || !json.data || !Array.isArray(json.data.items)) throw new Error(json.data && json.data.message || 'Search unavailable.');
            json.data.items.forEach(item => { const control = document.createElement('button'); control.type = 'button'; control.className = 'button'; control.dataset.selectorPick = String(item.id); control.textContent = item.title; results.appendChild(control); });
            root.dataset.page = String(page); status.textContent = json.data.items.length ? 'Choose a result. Page ' + page : 'No available results. Page ' + page;
        } catch (error) { if (error.name !== 'AbortError' && requests.get(root) === controller) status.textContent = error.message || 'Search unavailable.'; }
        finally { if (requests.get(root) === controller) { delete root.dataset.busy; requests.delete(root); refresh(root); } }
    });
    document.querySelectorAll('.alify-selector').forEach(refresh);
})();
