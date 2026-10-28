(function () {
    'use strict';
    let sequence = 0;
    function refresh(root) {
        const rows = Array.from(root.querySelector('.alify-flex-rows').children);
        const limits = JSON.parse(root.dataset.layoutLimits || '[]');
        const counts = {}; rows.forEach(row => { const layout = row.querySelector('input[name$="[layout]"]')?.value; counts[layout] = (counts[layout] || 0) + 1; });
        root.querySelectorAll('.alify-flex-choice option').forEach(option => { const setting = limits.find(item => item.name === option.value); option.disabled = !!setting && (setting.disabled || (counts[option.value] || 0) >= setting.max); });
        const choice = root.querySelector('.alify-flex-choice'); if (choice?.selectedOptions[0]?.disabled) { const available = Array.from(choice.options).find(option => !option.disabled); if (available) choice.value = available.value; }
        const hidden = !!root.closest('.alify-value[hidden]');
        rows.forEach((row, i) => {
            row.querySelector('.alify-flex-label').textContent = 'Row ' + (i + 1);
            row.querySelector('[data-flex-action="up"]').disabled = hidden || i === 0;
            row.querySelector('[data-flex-action="down"]').disabled = hidden || i === rows.length - 1;
            const layout = row.querySelector('input[name$="[layout]"]')?.value, setting = limits.find(item => item.name === layout); row.querySelector('[data-flex-action="remove"]').disabled = hidden || (!!setting && counts[layout] <= setting.min);
        });
        root.querySelector('[data-flex-action="add"]').disabled = hidden || rows.length >= Number(root.dataset.limit) || (!!choice && Array.from(choice.options).every(option => option.disabled));
        root.querySelector('.alify-flex-count').textContent = ` ${rows.length} / ${root.dataset.limit} rows`;
    }
    function refreshAll() { document.querySelectorAll('.alify-flexible').forEach(refresh); }
    document.addEventListener('alify:visibility', refreshAll);
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-flex-action]'); if (!button || button.disabled) return;
        const root = button.closest('.alify-flexible'), rows = root.querySelector('.alify-flex-rows'), row = button.closest('.alify-flex-row');
        const action = button.dataset.flexAction;
        if (action === 'add' && rows.children.length < Number(root.dataset.limit)) {
            const select = root.querySelector('.alify-flex-choice'); if (select.selectedOptions[0]?.disabled) return; const choice = select.value;
            const template = Array.from(root.querySelectorAll('template[data-flex-template]')).find(item => item.dataset.flexTemplate === choice);
            if (!template) return;
            const fragment = template.content.cloneNode(true), token = 'flex_' + Date.now() + '_' + (++sequence);
            fragment.querySelectorAll('[name], [id], [for], [aria-describedby]').forEach(element => {
                ['name', 'id', 'for', 'aria-describedby'].forEach(attr => { if (element.hasAttribute(attr)) element.setAttribute(attr, element.getAttribute(attr).replace(/__FLEX_ROW__/g, token)); });
            });
            rows.appendChild(fragment);
            const first = rows.lastElementChild.querySelector('input:not([type="hidden"]), textarea, select'); if (first) first.focus();
        }
        if (action === 'remove' && row && window.confirm('Remove this layout row? Save to apply the change.')) { row.remove(); root.querySelector('[data-flex-action="add"]').focus(); }
        if (action === 'up' && row && row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
        if (action === 'down' && row && row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
        refresh(root); root.dispatchEvent(new Event('change', {bubbles: true}));
    });
    refreshAll();
})();
