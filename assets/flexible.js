(function () {
    'use strict';
    window.AlifyCloneTemplate = window.AlifyCloneTemplate || ((template, placeholder, token) => {
        const fragment = template.content.cloneNode(true);
        const walk = root => { root.querySelectorAll('[name], [id], [for], [aria-describedby], [data-row-prefix], [data-row-id-prefix]').forEach(element => { ['name', 'id', 'for', 'aria-describedby', 'data-row-prefix', 'data-row-id-prefix'].forEach(attr => { if (element.hasAttribute(attr)) element.setAttribute(attr, element.getAttribute(attr).split(placeholder).join(token)); }); }); root.querySelectorAll('template').forEach(child => walk(child.content)); }; walk(fragment); return fragment;
    });
    let sequence = 0;
    const own = (root, selector) => Array.from(root.querySelectorAll(selector)).filter(item => item.closest('.alify-flexible') === root);
    function refresh(root) {
        const rows = Array.from(root.querySelector('.alify-flex-rows').children);
        const limits = JSON.parse(root.dataset.layoutLimits || '[]');
        const counts = {}; rows.forEach(row => { const layout = row.querySelector('input[name$="[layout]"]')?.value; counts[layout] = (counts[layout] || 0) + 1; });
        own(root, '.alify-flex-choice option').forEach(option => { const setting = limits.find(item => item.name === option.value); option.disabled = !!setting && (setting.disabled || (counts[option.value] || 0) >= setting.max); });
        const choice = own(root, '.alify-flex-choice')[0]; if (choice?.selectedOptions[0]?.disabled) { const available = Array.from(choice.options).find(option => !option.disabled); if (available) choice.value = available.value; }
        const hidden = !!root.closest('.alify-value[hidden], .alify-sub-value[hidden]');
        rows.forEach((row, i) => {
            row.querySelector('.alify-flex-label').textContent = 'Row ' + (i + 1);
            row.querySelector('[data-flex-action="up"]').disabled = hidden || i === 0;
            row.querySelector('[data-flex-action="down"]').disabled = hidden || i === rows.length - 1;
            const layout = row.querySelector('input[name$="[layout]"]')?.value, setting = limits.find(item => item.name === layout); row.querySelector('[data-flex-action="remove"]').disabled = hidden || (!!setting && counts[layout] <= setting.min);
        });
        own(root, '[data-flex-action="add"]')[0].disabled = hidden || rows.length >= Number(root.dataset.limit) || (!!choice && Array.from(choice.options).every(option => option.disabled));
        own(root, '.alify-flex-count')[0].textContent = ` ${rows.length} / ${root.dataset.limit} rows`;
    }
    function refreshAll() { document.querySelectorAll('.alify-flexible').forEach(refresh); }
    document.addEventListener('alify:visibility', refreshAll);
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-flex-action]'); if (!button || button.disabled) return;
        const root = button.closest('.alify-flexible'), rows = root.querySelector('.alify-flex-rows'), row = button.closest('.alify-flex-row');
        const action = button.dataset.flexAction;
        if (action === 'add' && rows.children.length < Number(root.dataset.limit)) {
            const select = own(root, '.alify-flex-choice')[0]; if (select.selectedOptions[0]?.disabled) return; const choice = select.value;
            const template = own(root, 'template[data-flex-template]').find(item => item.dataset.flexTemplate === choice);
            if (!template) return;
            const token = 'flex_' + Date.now() + '_' + (++sequence); const fragment = window.AlifyCloneTemplate(template, template.dataset.rowToken || '__FLEX_ROW__', token);
            rows.appendChild(fragment);
            const first = rows.lastElementChild.querySelector('input:not([type="hidden"]), textarea, select'); if (first) first.focus();
        }
        if (action === 'remove' && row && window.confirm('Remove this layout row? Save to apply the change.')) { row.remove(); own(root, '[data-flex-action="add"]')[0].focus(); }
        if (action === 'up' && row && row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
        if (action === 'down' && row && row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
        refresh(root); root.dispatchEvent(new Event('change', {bubbles: true}));
    });
    refreshAll();
})();
