/* global AlifyCF, jQuery */
(function ($) {
    'use strict';
    const input = document.getElementById('alify-schema');
    if (!input) return;
    let fields;
    try { fields = JSON.parse(input.value); } catch (_) { fields = []; }
    const list = document.getElementById('alify-fields');
    const escape = value => String(value == null ? '' : value).replace(/[&<>"']/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char]));
    const slug = value => value.toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_+|_+$/g, '').replace(/^[^a-z]+/, '');
    const sync = () => { input.value = JSON.stringify(fields); document.getElementById('alify-empty').hidden = fields.length > 0; document.getElementById('alify-count').textContent = `${fields.length} ${fields.length === 1 ? 'field' : 'fields'}`; };
    const control = (field, key, title, hint, multiline) => `<label class="alify-control"><span>${title}</span>${multiline ? `<textarea data-prop="${key}" rows="3">${escape(field[key])}</textarea>` : `<input data-prop="${key}" value="${escape(field[key])}"  >`}${hint ? `<small>${hint}</small>` : ''}</label>`;
    function subfieldsUI(field) {
        if (!['repeater', 'group'].includes(field.type)) return '';
        const subs = field.sub_fields || [];
        return `<div class="alify-sub-builder"><h3>${field.type === 'group' ? 'Group' : 'Repeater'} subfields</h3><p>Single level · up to 20 basic subfields${field.type === 'repeater' ? ' · up to 50 content rows' : ' · one structured object'}. Subfield names are local to this container.</p>${subs.map((sub, index) => `<div class="alify-sub-card" data-sub-index="${index}"><div class="alify-grid">${['label', 'name', ...(['gallery', 'link'].includes(sub.type) ? [] : ['default']), 'instructions'].map(prop => `<label class="alify-control"><span>${prop === 'default' ? 'Default value' : prop[0].toUpperCase() + prop.slice(1)}</span><input data-sub-prop="${prop}" value="${escape(sub[prop])}"></label>`).join('')}<label class="alify-control"><span>Type</span><select data-sub-prop="type">${Object.entries(AlifyCF.types).filter(([type]) => !['repeater', 'group', 'flexible', 'wysiwyg'].includes(type)).map(([type, label]) => `<option value="${type}" ${sub.type === type ? 'selected' : ''}>${escape(label)}</option>`).join('')}</select></label><label class="alify-control"><span>Choices (value : Label, one per line)</span><textarea data-sub-prop="choices">${escape(sub.choices)}</textarea></label></div><label><input type="checkbox" data-sub-prop="required" ${sub.required ? 'checked' : ''}> Required subfield</label><div class="alify-actions"><button type="button" class="button" data-sub-action="up" ${index === 0 ? 'disabled' : ''}>Move up</button><button type="button" class="button" data-sub-action="down" ${index === subs.length - 1 ? 'disabled' : ''}>Move down</button><button type="button" class="button-link-delete" data-sub-action="remove">Remove subfield</button></div></div>`).join('')}<button type="button" class="button" data-sub-action="add" ${subs.length >= 20 ? 'disabled' : ''}>+ Add Subfield</button></div>`;
    }
    function layoutsUI(field) {
        if (field.type !== 'flexible') return '';
        const layouts = field.layouts || [];
        return `<div class="alify-layout-builder"><h3>Flexible layouts</h3><p>1–10 named layouts, each with 1–20 basic subfields. No nested containers.</p>${layouts.map((layout, index) => `<section class="alify-layout-card" data-layout-index="${index}"><div class="alify-grid"><label class="alify-control"><span>Layout label</span><input data-layout-prop="label" value="${escape(layout.label)}"></label><label class="alify-control"><span>Layout name</span><input data-layout-prop="name" value="${escape(layout.name)}"></label></div>${subfieldsUI({...layout, type: 'group'})}<div class="alify-actions"><button type="button" class="button" data-layout-action="up" ${index === 0 ? 'disabled' : ''}>Move layout up</button><button type="button" class="button" data-layout-action="down" ${index === layouts.length - 1 ? 'disabled' : ''}>Move layout down</button><button type="button" class="button-link-delete" data-layout-action="remove">Remove layout</button></div></section>`).join('')}<button type="button" class="button" data-layout-action="add" ${layouts.length >= 10 ? 'disabled' : ''}>+ Add Layout</button></div>`;
    }
    function containerFor(target) {
        const parent = fields.find(item => item.key === target.closest('.alify-card').dataset.key);
        const layout = target.closest('.alify-layout-card');
        return layout ? parent.layouts[Number(layout.dataset.layoutIndex)] : parent;
    }
    function conditionUI(field) {
        const condition = field.condition, rules = condition ? (condition.rules || [condition]) : [];
        const sources = fields.filter(item => item.key !== field.key && !['repeater', 'group', 'flexible', 'link', 'wysiwyg'].includes(item.type) && !item.condition);
        return `<div class="alify-condition"><label><input type="checkbox" data-condition-prop="enabled" ${condition ? 'checked' : ''}> Enable conditional logic</label>${condition ? `<p>Show this field when rules match unconditional siblings:</p><label>Match <select data-condition-relation><option value="all" ${condition.relation !== 'any' ? 'selected' : ''}>ALL rules (AND)</option><option value="any" ${condition.relation === 'any' ? 'selected' : ''}>ANY rule (OR)</option></select></label>${rules.map((rule, index) => `<div class="alify-condition-rule" data-rule-index="${index}"><div class="alify-grid"><label class="alify-control"><span>Source field</span><select data-condition-prop="field"><option value="">Choose field</option>${sources.map(item => `<option value="${escape(item.key)}" ${item.key === rule.field ? 'selected' : ''}>${escape(item.label || item.name || 'Untitled field')}</option>`).join('')}</select></label><label class="alify-control"><span>Comparison</span><select data-condition-prop="operator">${Object.entries({equals: 'Is equal to', not_equals: 'Is not equal to', empty: 'Is empty', not_empty: 'Is not empty'}).map(([key, label]) => `<option value="${key}" ${key === rule.operator ? 'selected' : ''}>${label}</option>`).join('')}</select></label><label class="alify-control"><span>Comparison value</span><input data-condition-prop="value" value="${escape(rule.value)}" ${['empty', 'not_empty'].includes(rule.operator) ? 'disabled' : ''}><small>Choice key, attachment ID or 1/0 for a toggle. Checkbox/Gallery rules match a selected key/ID.</small></label></div><button type="button" class="button-link-delete" data-condition-action="remove" ${rules.length <= 1 ? 'disabled' : ''}>Remove rule</button></div>`).join('')}<button type="button" class="button" data-condition-action="add" ${rules.length >= 10 ? 'disabled' : ''}>+ Add Rule</button>` : ''}</div>`;
    }
    function render(openKey) {
        list.innerHTML = fields.map((field, i) => `<details class="alify-card" data-key="${escape(field.key)}" ${field.key === openKey ? 'open' : ''}>
            <summary><span class="alify-drag" title="Drag to reorder" aria-hidden="true">⠿</span><span class="alify-index">${String(i + 1).padStart(2, '0')}</span><strong class="alify-card-title">${escape(field.label || 'Untitled field')}</strong><code class="alify-card-name">${escape(field.name)}</code><span class="alify-type">${escape(AlifyCF.types[field.type])}</span></summary>
            <div class="alify-card-body"><div class="alify-grid">${control(field, 'label', 'Field Label', 'The name your editors will see.')}${control(field, 'name', 'Field Name', 'Unique within matching groups. Use lowercase letters, numbers and underscores.')}<label class="alify-control"><span>Field Type</span><select data-prop="type">${Object.entries(AlifyCF.types).map(([key, label]) => `<option value="${key}" ${key === field.type ? 'selected' : ''}>${escape(label)}</option>`).join('')}</select></label>${['repeater', 'gallery', 'group', 'flexible', 'link'].includes(field.type) ? '' : control(field, 'default', 'Default Value', field.type === 'color' ? 'Six-digit hex, e.g. #2271b1. Empty means no color.' : field.type === 'time' ? '24-hour HH:MM, e.g. 09:30. No date or timezone conversion.' : field.type === 'wysiwyg' ? 'WordPress post HTML. Empty formatting is treated as empty.' : 'True / False: 1 or 0. Image / File: attachment ID.', field.type === 'wysiwyg')}</div>${control(field, 'instructions', 'Instructions', 'Optional guidance for content editors.', true)}<div class="alify-choices" ${['checkbox', 'select'].includes(field.type) ? '' : 'hidden'}>${control(field, 'choices', 'Choices', 'One per line. Format: value : Label. Checkbox defaults are not supported in this release.', true)}</div><label class="alify-required-toggle"><input type="checkbox" data-prop="required" ${field.required ? 'checked' : ''}> Required field</label>${field.type === 'repeater' ? `<div class="alify-grid"><label class="alify-control"><span>Minimum rows</span><input type="number" min="0" max="50" step="1" data-prop="min_rows" value="${escape(field.min_rows ?? 0)}"></label><label class="alify-control"><span>Maximum rows</span><input type="number" min="1" max="50" step="1" data-prop="max_rows" value="${escape(field.max_rows ?? 50)}"></label></div><p>Limits apply on save; rows are never silently added or removed. Required repeaters need at least one row.</p>` : ''}${subfieldsUI(field)}${layoutsUI(field)}${conditionUI(field)}<div class="alify-actions"><button type="button" class="button" data-action="up" ${i === 0 ? 'disabled' : ''}>Move up</button><button type="button" class="button" data-action="down" ${i === fields.length - 1 ? 'disabled' : ''}>Move down</button><button type="button" class="button" data-action="duplicate">Duplicate</button><button type="button" class="button-link-delete" data-action="remove">Delete field</button></div></div></details>`).join('');
        sync();
    }
    function key() { return 'field_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10); }
    function uniqueName(base) { let name = base, suffix = 2; while (fields.some(field => field.name === name)) name = base + '_' + suffix++; return name; }
    document.getElementById('alify-add').addEventListener('click', () => {
        const field = {key: key(), label: '', name: '', type: 'text', instructions: '', default: '', choices: '', required: false};
        fields.push(field); render(field.key); list.lastElementChild.querySelector('input').focus();
    });
    list.addEventListener('input', event => {
        const layoutProp = event.target.dataset.layoutProp;
        if (layoutProp) {
            const layout = containerFor(event.target), before = layout.label;
            layout[layoutProp] = event.target.value;
            if (layoutProp === 'label' && (!layout.name || layout.name === slug(before))) { layout.name = slug(layout.label); event.target.closest('.alify-layout-card').querySelector('[data-layout-prop="name"]').value = layout.name; }
            sync(); return;
        }
        const subProp = event.target.dataset.subProp;
        if (subProp) {
            const parent = containerFor(event.target);
            const sub = parent.sub_fields[Number(event.target.closest('.alify-sub-card').dataset.subIndex)];
            const previous = sub.label;
            sub[subProp] = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
            if (subProp === 'label' && (!sub.name || sub.name === slug(previous))) {
                sub.name = slug(sub.label); event.target.closest('.alify-sub-card').querySelector('[data-sub-prop="name"]').value = sub.name;
            }
            if (subProp === 'type') { render(event.target.closest('.alify-card').dataset.key); return; }
            sync(); return;
        }
        if (event.target.hasAttribute('data-condition-relation')) {
            const field = fields.find(item => item.key === event.target.closest('.alify-card').dataset.key);
            field.condition = {relation: event.target.value, rules: field.condition.rules || [field.condition]}; sync(); return;
        }
        const conditionProp = event.target.dataset.conditionProp;
        if (conditionProp) {
            const card = event.target.closest('.alify-card');
            const field = fields.find(item => item.key === card.dataset.key);
            if (conditionProp === 'enabled') field.condition = event.target.checked ? {field: '', operator: 'equals', value: ''} : null;
            else { const rules = field.condition.rules || [field.condition]; rules[Number(event.target.closest('.alify-condition-rule').dataset.ruleIndex)][conditionProp] = event.target.value; }
            sync();
            if (conditionProp !== 'value') render(field.key);
            return;
        }
        const prop = event.target.dataset.prop;
        if (!prop) return;
        const card = event.target.closest('.alify-card');
        const field = fields.find(item => item.key === card.dataset.key);
        const previous = field.label;
        field[prop] = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
        if (prop === 'label' && (!field.name || field.name === slug(previous))) {
            field.name = slug(field.label); card.querySelector('[data-prop="name"]').value = field.name;
        }
        if (prop === 'type') { render(field.key); return; }
        card.querySelector('.alify-card-title').textContent = field.label || 'Untitled field';
        card.querySelector('.alify-card-name').textContent = field.name;
        card.querySelector('.alify-type').textContent = AlifyCF.types[field.type];
        sync();
    });
    list.addEventListener('click', event => {
        const conditionAction = event.target.dataset.conditionAction;
        if (conditionAction && !event.target.disabled) {
            const field = fields.find(item => item.key === event.target.closest('.alify-card').dataset.key);
            const condition = field.condition, rules = condition.rules || [condition];
            if (conditionAction === 'add' && rules.length < 10) rules.push({field: '', operator: 'equals', value: ''});
            if (conditionAction === 'remove' && rules.length > 1) rules.splice(Number(event.target.closest('.alify-condition-rule').dataset.ruleIndex), 1);
            field.condition = {relation: condition.relation || 'all', rules}; render(field.key); return;
        }
        const layoutAction = event.target.dataset.layoutAction;
        if (layoutAction) {
            const parent = fields.find(item => item.key === event.target.closest('.alify-card').dataset.key);
            parent.layouts = parent.layouts || []; const layouts = parent.layouts;
            const card = event.target.closest('.alify-layout-card'), index = card ? Number(card.dataset.layoutIndex) : -1;
            if (layoutAction === 'add' && layouts.length < 10) layouts.push({key: key(), name: '', label: '', sub_fields: []});
            if (layoutAction === 'remove' && window.confirm('Remove layout? Existing content using it will need explicit review.')) layouts.splice(index, 1);
            if (layoutAction === 'up' && index > 0) [layouts[index - 1], layouts[index]] = [layouts[index], layouts[index - 1]];
            if (layoutAction === 'down' && index < layouts.length - 1) [layouts[index + 1], layouts[index]] = [layouts[index], layouts[index + 1]];
            render(parent.key); return;
        }
        const subAction = event.target.dataset.subAction;
        if (subAction) {
            const parent = containerFor(event.target);
            parent.sub_fields = parent.sub_fields || [];
            const subs = parent.sub_fields;
            const card = event.target.closest('.alify-sub-card');
            const index = card ? Number(card.dataset.subIndex) : -1;
            if (subAction === 'add' && subs.length < 20) subs.push({key: key(), name: '', label: '', type: 'text', default: '', instructions: '', choices: '', required: false});
            if (subAction === 'remove' && window.confirm('Remove subfield? Its values will be discarded from this container the next time each post is saved.')) subs.splice(index, 1);
            if (subAction === 'up' && index > 0) [subs[index - 1], subs[index]] = [subs[index], subs[index - 1]];
            if (subAction === 'down' && index < subs.length - 1) [subs[index + 1], subs[index]] = [subs[index], subs[index + 1]];
            render(event.target.closest('.alify-card').dataset.key); return;
        }
        const action = event.target.dataset.action;
        if (!action) return;
        const index = fields.findIndex(field => field.key === event.target.closest('.alify-card').dataset.key);
        const field = fields[index];
        if (action === 'remove') { if (!window.confirm(`Delete “${field.label || 'Untitled field'}”? Existing saved values will not be deleted.`)) return; fields.splice(index, 1); }
        if (action === 'duplicate') fields.splice(index + 1, 0, {...field, layouts: (field.layouts || []).map(layout => ({...layout, sub_fields: (layout.sub_fields || []).map(sub => ({...sub}))})), sub_fields: (field.sub_fields || []).map(sub => ({...sub})), condition: field.condition ? JSON.parse(JSON.stringify(field.condition)) : null, key: key(), label: field.label + ' (copy)', name: uniqueName((field.name || 'field') + '_copy')});
        if (action === 'up' && index > 0) [fields[index - 1], fields[index]] = [field, fields[index - 1]];
        if (action === 'down' && index < fields.length - 1) [fields[index + 1], fields[index]] = [field, fields[index + 1]];
        render(field.key);
    });
    $(list).sortable({handle: '.alify-drag', items: '> .alify-card', tolerance: 'pointer', update: function () {
        fields = Array.from(list.children).map(card => fields.find(field => field.key === card.dataset.key));
        Array.from(list.children).forEach((card, i) => { card.querySelector('.alify-index').textContent = String(i + 1).padStart(2, '0'); card.querySelector('[data-action="up"]').disabled = i === 0; card.querySelector('[data-action="down"]').disabled = i === fields.length - 1; }); sync();
    }});
    document.getElementById('post').addEventListener('submit', event => {
        const names = new Set();
        for (const field of fields) {
            if (!field.label.trim() || !/^[a-z][a-z0-9_]*$/.test(field.name) || names.has(field.name)) {
                event.preventDefault(); render(field.key); window.alert('Every field needs a label and a unique valid field name.'); list.querySelector(`[data-key="${field.key}"] input`).focus(); return;
            }
            if (field.condition) {
                const rules = field.condition.rules || [field.condition];
                if (!rules.length || rules.length > 10 || rules.some(rule => {
                    const source = fields.find(item => item.key === rule.field);
                    return !source || ['repeater', 'group', 'flexible', 'link', 'wysiwyg'].includes(source.type) || source.key === field.key || source.condition;
                })) {
                    event.preventDefault(); render(field.key); window.alert('Use 1–10 rules referencing unconditional siblings. Fix removed sources, self-references or chains.'); return;
                }
            }
            if (field.type === 'repeater') {
                const minimum = String(field.min_rows ?? 0), maximum = String(field.max_rows ?? 50);
                if (!/^(0|[1-9][0-9]?)$/.test(minimum) || !/^[1-9][0-9]?$/.test(maximum) || Number(minimum) > Number(maximum) || Number(maximum) > 50) { event.preventDefault(); render(field.key); window.alert('Choose whole-number row limits: 0 ≤ minimum ≤ maximum ≤ 50, maximum at least 1.'); return; }
            }
            if (field.type === 'flexible') {
                const layouts = field.layouts || [], names = new Set();
                if (!layouts.length || layouts.length > 10 || layouts.some(layout => { const bad = !layout.label.trim() || !/^[a-z][a-z0-9_]{0,49}$/.test(layout.name) || names.has(layout.name); names.add(layout.name); return bad; })) { event.preventDefault(); render(field.key); window.alert('Flexible Content needs 1–10 uniquely named layouts.'); return; }
            }
            for (const container of (field.type === 'flexible' ? (field.layouts || []) : (['repeater', 'group'].includes(field.type) ? [field] : []))) {
                const subs = container.sub_fields || [];
                const localNames = new Set();
                if (!subs.length || subs.length > 20 || subs.some(sub => {
                    const invalid = !sub.label.trim() || !/^[a-z][a-z0-9_]*$/.test(sub.name) || localNames.has(sub.name) || ['repeater', 'group', 'flexible', 'wysiwyg'].includes(sub.type);
                    localNames.add(sub.name); return invalid;
                })) {
                    event.preventDefault(); render(field.key); window.alert('Groups/repeaters need 1–20 basic subfields with labels and unique local names.'); return;
                }
            }
            names.add(field.name);
        }
        sync();
    });
    render();
})(jQuery);
