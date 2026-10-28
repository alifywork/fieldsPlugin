(function () {
    'use strict';
    function refresh(root) {
        const size = Number(root.dataset.pageSize || 0), list = root.querySelector('.alify-rows'); if (!size || !list) return;
        const rows = Array.from(list.children), pages = Math.max(1, Math.ceil(rows.length / size)); let page = Math.min(Math.max(Number(root.dataset.page || 1), 1), pages); root.dataset.page = String(page);
        rows.forEach((row, index) => { row.hidden = Math.floor(index / size) + 1 !== page; });
        let navigation = Array.from(root.querySelectorAll('[data-pagination]')).find(item => item.closest('.alify-repeater') === root);
        if (!navigation) { navigation = document.createElement('div'); navigation.dataset.pagination = '1'; navigation.setAttribute('role', 'group'); navigation.setAttribute('aria-label', 'Repeater pages'); root.appendChild(navigation); for (const [action, label] of [['previous', 'Previous page'], ['next', 'Next page']]) { const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.dataset.pageAction = action; button.textContent = label; navigation.appendChild(button); } const status = document.createElement('span'); status.setAttribute('aria-live', 'polite'); navigation.appendChild(status); }
        navigation.querySelector('[data-page-action="previous"]').disabled = page <= 1; navigation.querySelector('[data-page-action="next"]').disabled = page >= pages; navigation.querySelector('span').textContent = ` Page ${page} of ${pages}; all ${rows.length} rows remain in this form.`;
    }
    document.addEventListener('click', event => { const button = event.target.closest('[data-page-action]'); if (!button || button.disabled) return; const root = button.closest('.alify-repeater'); root.dataset.page = String(Number(root.dataset.page || 1) + (button.dataset.pageAction === 'next' ? 1 : -1)); refresh(root); });
    document.addEventListener('invalid', event => { const root = event.target.closest('.alify-repeater[data-page-size]'); if (!root) return; root.querySelectorAll('.alify-rows > .alify-row').forEach(row => { row.hidden = false; }); const status = root.querySelector('[data-pagination] span'); if (status) status.textContent = 'All rows shown for validation; no values were discarded.'; }, true);
    document.addEventListener('alify:visibility', () => document.querySelectorAll('.alify-repeater[data-page-size]').forEach(refresh));
    const observer = new MutationObserver(records => {
        const roots = new Set(); records.forEach(record => { if (!Array.from(record.addedNodes).some(node => node.nodeType === 1 && node.matches?.('.alify-row')) && !Array.from(record.removedNodes).some(node => node.nodeType === 1 && node.matches?.('.alify-row'))) return; const root = record.target.closest?.('.alify-repeater[data-page-size]'); if (root && Number(root.dataset.pageSize) > 0) { const added = Array.from(record.addedNodes).find(node => node.nodeType === 1 && node.matches?.('.alify-row')); if (added) { const index = Array.from(record.target.children).indexOf(added); root.dataset.page = String(Math.floor(index / Number(root.dataset.pageSize)) + 1); } roots.add(root); } }); roots.forEach(refresh);
    }); observer.observe(document.body, {subtree: true, childList: true}); document.querySelectorAll('.alify-repeater[data-page-size]').forEach(refresh);
})();
