(function () {
    'use strict';
    function refresh(root) {
        const rows = Array.from(root.querySelector('.alify-relation-items').children), hidden = !!root.closest('.alify-value[hidden]');
        rows.forEach((row, index) => {
            row.querySelector('[data-relation-action="up"]').disabled = hidden || index === 0;
            row.querySelector('[data-relation-action="down"]').disabled = hidden || index === rows.length - 1;
            row.querySelector('[data-relation-action="remove"]').disabled = hidden;
        });
        root.querySelector('[data-relation-action="clear"]').disabled = hidden || !rows.length;
        root.querySelector('.alify-relation-count').textContent = rows.length + ' / ' + (root.dataset.limit || 50) + ' references';
    }
    function changed(root) { refresh(root); root.dispatchEvent(new Event('change', {bubbles: true})); }
    function add(root, id, title) {
        if (root.closest('.alify-value[hidden]')) return;
        const list = root.querySelector('.alify-relation-items'), status = root.querySelector('.alify-post-status');
        if (Array.from(list.querySelectorAll('input')).some(input => input.value === String(id))) { status.textContent = 'Already selected.'; return; }
        if (list.children.length >= Number(root.dataset.limit || 50)) { status.textContent = 'Maximum ' + (root.dataset.limit || 50) + ' references. Remove a selection first.'; return; }
        const row = document.createElement('div'); row.className = 'alify-relation-item';
        const input = document.createElement('input'); input.type = 'hidden'; input.name = root.querySelector('input[name$="[__complete]"]').name.slice(0, -'[__complete]'.length) + '[ids][]'; input.value = String(id); row.appendChild(input);
        const label = document.createElement('span'); label.className = 'alify-relation-title'; label.textContent = title; row.appendChild(label);
        for (const [action, text] of [['up', 'Move earlier'], ['down', 'Move later'], ['remove', 'Remove reference']]) {
            const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.dataset.relationAction = action; button.textContent = text; row.appendChild(button);
        }
        list.appendChild(row); status.textContent = 'Reference added.'; changed(root);
    }
    window.AlifyRelationship = {add};
    document.addEventListener('alify:visibility', () => document.querySelectorAll('.alify-relationship').forEach(refresh));
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-relation-action]'); if (!button || button.disabled) return;
        const root = button.closest('.alify-relationship'), list = root.querySelector('.alify-relation-items'), row = button.closest('.alify-relation-item');
        const action = button.dataset.relationAction;
        if (action === 'up' && row && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
        if (action === 'down' && row && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
        if (action === 'remove' && row) row.remove();
        if (action === 'clear') list.replaceChildren();
        changed(root);
        if (action === 'remove' || action === 'clear') root.querySelector('[data-post-find]').focus();
    });
})();
