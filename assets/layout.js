(function () {
    'use strict';
    function icon(input) { const preview = input.parentElement.querySelector('[data-icon-preview]'); if (preview) preview.className = /^dashicons-[a-z0-9-]+$/.test(input.value) ? 'dashicons ' + input.value : ''; }
    document.querySelectorAll('[data-icon-picker]').forEach(icon);
    document.addEventListener('change', event => { if (event.target.matches('[data-icon-picker]')) icon(event.target); });
    function prepare() { document.querySelectorAll('.alify-values').forEach(group => {
        if (group.dataset.alifyLayoutReady) return; group.dataset.alifyLayoutReady = '1';
        let tab = null, accordion = null; const tabs = [];
        const fields = Array.from(group.children).filter(element => element.matches('.alify-value'));
        if (!fields.some(field => ['tab', 'accordion'].includes(field.dataset.fieldType))) return;
        const nav = document.createElement('div'); nav.className = 'alify-tabs'; nav.setAttribute('role', 'tablist'); group.prepend(nav);
        fields.forEach(field => {
            if (field.dataset.fieldType === 'tab') {
                accordion = null; tab = document.createElement('div'); tab.dataset.editorSection = 'tab'; tab.id = field.querySelector('button').id + '-section'; tab.setAttribute('role', 'tabpanel'); group.appendChild(tab);
                nav.appendChild(field); const button = field.querySelector('[data-editor-tab]'); button.setAttribute('role', 'tab'); button.setAttribute('aria-controls', tab.id); button.setAttribute('aria-selected', String(!tabs.length)); tab.hidden = !!tabs.length; tabs.push({button, section: tab});
                button.addEventListener('click', () => tabs.forEach(item => { item.section.hidden = item.button !== button; item.button.setAttribute('aria-selected', String(item.button === button)); }));
                button.addEventListener('keydown', event => { if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return; event.preventDefault(); const index = tabs.findIndex(item => item.button === button); const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length; tabs[next].button.click(); tabs[next].button.focus(); });
            } else if (field.dataset.fieldType === 'accordion') {
                (tab || group).appendChild(field); accordion = document.createElement('div'); accordion.dataset.editorSection = 'accordion'; accordion.id = field.querySelector('button').id + '-section'; (tab || group).appendChild(accordion);
                const button = field.querySelector('[data-editor-accordion]'); button.setAttribute('aria-controls', accordion.id); button.setAttribute('aria-expanded', 'true'); const section = accordion;
                button.addEventListener('click', () => { section.hidden = !section.hidden; button.setAttribute('aria-expanded', String(!section.hidden)); });
            } else (accordion || tab || group).appendChild(field);
        });
        group.addEventListener('invalid', event => {
            let node = event.target.parentElement; while (node && node !== group) { if (node.dataset.editorSection) { node.hidden = false; const control = group.querySelector('[aria-controls="' + node.id + '"]'); if (control) { control.setAttribute(node.dataset.editorSection === 'tab' ? 'aria-selected' : 'aria-expanded', 'true'); } } node = node.parentElement; }
        }, true);
    }); }
    const observer = new MutationObserver(records => { if (records.some(record => Array.from(record.addedNodes).some(node => node.nodeType === 1 && (node.matches?.('.alify-values') || node.querySelector?.('.alify-values'))))) { prepare(); document.querySelectorAll('[data-icon-picker]').forEach(icon); } });
    observer.observe(document.body, {childList: true, subtree: true}); prepare();
})();
