/* global jQuery */
(function () {
    'use strict';
    window.AlifyCloneTemplate = window.AlifyCloneTemplate || ((template, placeholder, token) => {
        const fragment = template.content.cloneNode(true);
        const walk = root => { root.querySelectorAll('[name], [id], [for], [aria-describedby], [data-row-prefix], [data-row-id-prefix]').forEach(element => { ['name', 'id', 'for', 'aria-describedby', 'data-row-prefix', 'data-row-id-prefix'].forEach(attr => { if (element.hasAttribute(attr)) element.setAttribute(attr, element.getAttribute(attr).split(placeholder).join(token)); }); }); root.querySelectorAll('template').forEach(child => walk(child.content)); }; walk(fragment); return fragment;
    });
    let sequence = 0;
    const rowSelector = '.alify-row, .alify-flex-row';
    function copy(source, template, placeholder) {
        if (window.AlifyRichText) window.AlifyRichText.sync();
        const token = 'copy_' + Date.now() + '_' + (++sequence);
        const fragment = window.AlifyCloneTemplate(template, template.dataset.rowToken || placeholder, token), destination = fragment.firstElementChild;
        const firstName = source.querySelector('[name*="[rows]"]')?.name || '';
        const oldPrefix = source.dataset.rowPrefix || firstName.match(/^(.*?\[rows\]\[[^\]]+\])/)?.[1] || '';
        const newPrefix = destination.dataset.rowPrefix || oldPrefix.replace(/(\[rows\])\[[^\]]+\]$/, '$1[' + token + ']');
        const oldToken = oldPrefix.match(/\[rows\]\[([^\]]+)\]$/)?.[1];
        const normalize = name => name.replace(oldPrefix, '[ROW]').replace(newPrefix, '[ROW]');
        const nested = row => Array.from(row.querySelectorAll('.alify-repeater, .alify-flexible')).filter(root => root.parentElement.closest('.alify-repeater, .alify-flexible') === row.closest('.alify-repeater, .alify-flexible'));
        const targets = nested(destination);
        nested(source).forEach((root, index) => {
            const target = targets[index]; if (!target) return;
            const flex = root.classList.contains('alify-flexible'), selector = flex ? '.alify-flex-rows' : '.alify-rows';
            const list = target.querySelector(selector); list.replaceChildren();
            Array.from(root.querySelector(selector).children).forEach(row => {
                const layout = flex ? row.querySelector('input[name$="[layout]"]')?.value : null;
                const childTemplate = Array.from(target.querySelectorAll(flex ? 'template[data-flex-template]' : '.alify-row-template')).find(item => item.closest('.alify-repeater, .alify-flexible') === target && (!flex || item.dataset.flexTemplate === layout));
                if (childTemplate) list.appendChild(copy(row, childTemplate, flex ? '__FLEX_ROW__' : '__ROW__'));
            });
        });
        for (const className of ['alify-gallery-items', 'alify-relation-items', 'alify-selector-selected', 'alify-media-preview', 'alify-post-selected']) {
            const originals = Array.from(source.querySelectorAll('.' + className)).filter(item => item.closest(rowSelector) === source), targets = Array.from(destination.querySelectorAll('.' + className)).filter(item => item.closest(rowSelector) === destination);
            originals.forEach((original, index) => { if (targets[index]) targets[index].replaceChildren(...Array.from(original.children).map(child => child.cloneNode(true))); });
        }
        // Selected-list clones still carry the old row token; rewrite every reference to it.
        if (oldToken) destination.querySelectorAll('[name], [id], [for], [aria-describedby]').forEach(element => {
            ['name', 'id', 'for', 'aria-describedby'].forEach(attr => { if (!element.hasAttribute(attr)) return; let value = element.getAttribute(attr); if (attr === 'name') value = value.replace(oldPrefix, newPrefix); else value = source.dataset.rowIdPrefix ? value.split(source.dataset.rowIdPrefix).join(destination.dataset.rowIdPrefix) : value.split('-' + oldToken + '-').join('-' + token + '-'); element.setAttribute(attr, value); });
        });
        const inputs = Array.from(source.querySelectorAll('input[name], textarea[name], select[name]'));
        destination.querySelectorAll('input[name], textarea[name], select[name]').forEach(target => {
            if (target.closest('.alify-gallery-items, .alify-relation-items, .alify-selector-selected')) return;
            const original = inputs.find(input => normalize(input.name) === normalize(target.name) && (['radio', 'checkbox'].includes(target.type) ? input.value === target.value : true));
            if (!original) return;
            if (['radio', 'checkbox'].includes(target.type)) target.checked = original.checked;
            else if (target.tagName === 'SELECT' && target.multiple) Array.from(target.options).forEach(option => { option.selected = Array.from(original.selectedOptions).some(item => item.value === option.value); });
            else if (target.type !== 'file') target.value = original.value;
        });
        source.querySelectorAll('[data-media="remove"]').forEach((button, i) => { const target = destination.querySelectorAll('[data-media="remove"]')[i]; if (target) target.hidden = button.hidden; });
        destination.querySelectorAll('[aria-invalid]').forEach(input => input.removeAttribute('aria-invalid'));
        destination.querySelectorAll('.alify-field-error').forEach(error => error.remove());
        return fragment;
    }
    function prepare(root = document) {
        root.querySelectorAll(rowSelector).forEach(row => {
            if (row.dataset.rowReady) return; row.dataset.rowReady = '1';
            const container = row.closest('.alify-repeater, .alify-flexible'), bar = row.querySelector('.alify-row-bar > div'); if (!bar || !container) return;
            for (const [key, text] of [['clone', 'Duplicate row'], ['collapse', 'Collapse row'], ['drag', 'Drag row']]) { const button = document.createElement('button'); button.type = 'button'; button.className = 'button'; button.dataset.rowTool = key; button.textContent = text; if (key === 'collapse') button.setAttribute('aria-expanded', 'true'); bar.appendChild(button); }
            if (container.dataset.collapsed === '1') { row.classList.add('alify-row-collapsed'); const button = row.querySelector('[data-row-tool="collapse"]'); button.textContent = 'Expand row'; button.setAttribute('aria-expanded', 'false'); }
        });
        document.querySelectorAll('.alify-repeater, .alify-flexible').forEach(container => {
            const list = container.querySelector('.alify-rows, .alify-flex-rows'); if (!list) return;
            Array.from(list.children).forEach(row => {
                const layoutName = row.querySelector('input[name$="[layout]"]')?.value, layoutSetting = JSON.parse(container.dataset.layoutLimits || '[]').find(item => item.name === layoutName);
                const clone = row.querySelector('[data-row-tool="clone"]'); if (clone) clone.disabled = (!!layoutSetting && (layoutSetting.disabled || Array.from(list.children).filter(item => item.querySelector('input[name$="[layout]"]')?.value === layoutName).length >= layoutSetting.max)) || !!container.closest('.alify-value[hidden], .alify-sub-value[hidden]') || list.children.length >= Number(container.dataset.limit);
                const name = container.dataset.rowLabel, sub = name ? Array.from(row.querySelectorAll('[data-subfield]')).find(item => item.dataset.subfield === name && item.closest('[data-condition-scope]') === (row.matches('[data-condition-scope]') ? row : row.querySelector('.alify-layout-fields'))) : null;
                let label = row.querySelector('.alify-extra-row-label');
                if (!label) { label = document.createElement('span'); label.className = 'alify-extra-row-label'; row.querySelector('.alify-row-bar')?.appendChild(label); }
                const input = sub && ['text', 'number', 'email', 'url', 'select', 'radio', 'checkbox', 'range', 'date', 'time', 'date_time', 'color', 'icon_picker'].includes(sub.dataset.subType) ? sub.querySelector('input:not([type="hidden"]):not([type="password"]), select') : null;
                if (label) label.textContent = input ? ' — ' + String(input.value).slice(0, 80) : '';
            });
            if (window.jQuery && jQuery.fn && jQuery.fn.sortable && !list.dataset.alifySortable) {
                list.dataset.alifySortable = '1';
                jQuery(list).sortable({items: '> .alify-row, > .alify-flex-row', handle: '[data-row-tool="drag"]', start(event, ui) { if (window.AlifyRichText) window.AlifyRichText.detach(ui.item[0]); }, stop(event, ui) { if (window.AlifyRichText) window.AlifyRichText.restore(ui.item[0]); list.dispatchEvent(new Event('change', {bubbles: true})); document.dispatchEvent(new Event('alify:visibility')); }});
            }
        });
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-row-tool]'); if (!button || button.disabled) return;
        const row = button.closest(rowSelector), container = row.closest('.alify-repeater, .alify-flexible');
        if (button.dataset.rowTool === 'collapse') { const collapsed = row.classList.toggle('alify-row-collapsed'); button.setAttribute('aria-expanded', String(!collapsed)); button.textContent = collapsed ? 'Expand row' : 'Collapse row'; }
        if (button.dataset.rowTool === 'clone') {
            const flexible = row.classList.contains('alify-flex-row'), list = row.parentElement; if (list.children.length >= Number(container.dataset.limit)) return;
            const layout = flexible ? row.querySelector('input[name$="[layout]"]')?.value : '';
            const template = flexible ? Array.from(container.querySelectorAll('template[data-flex-template]')).find(item => item.dataset.flexTemplate === layout && item.closest('.alify-flexible') === container) : Array.from(container.querySelectorAll('.alify-row-template')).find(item => item.closest('.alify-repeater') === container);
            if (!template) return; list.insertBefore(copy(row, template, flexible ? '__FLEX_ROW__' : '__ROW__'), row.nextSibling); prepare(); container.dispatchEvent(new Event('change', {bubbles: true})); document.dispatchEvent(new Event('alify:visibility'));
        }
    });
    document.addEventListener('invalid', event => { event.target.closest('.alify-row-collapsed')?.classList.remove('alify-row-collapsed'); }, true);
    document.addEventListener('input', () => prepare()); document.addEventListener('alify:visibility', () => prepare());
    const observer = new MutationObserver(records => { if (records.some(record => Array.from(record.addedNodes).some(node => node.nodeType === 1 && (node.matches?.(rowSelector) || node.querySelector?.(rowSelector))))) prepare(); });
    observer.observe(document.body, {childList: true, subtree: true}); prepare();
    window.AlifyRows = {prepare, copy};
})();
