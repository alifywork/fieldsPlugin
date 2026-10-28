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
        if (field.type !== 'repeater') return '';
        const subs = field.sub_fields || [];
        return `<div class="alify-sub-builder"><h3>Repeater subfields</h3><p>Single level · up to 20 subfields · up to 50 content rows. Subfield names are local to this repeater.</p>${subs.map((sub, index) => `<div class="alify-sub-card" data-sub-index="${index}"><div class="alify-grid">${['label', 'name', ...(sub.type === 'gallery' ? [] : ['default']), 'instructions'].map(prop => `<label class="alify-control"><span>${prop === 'default' ? 'Default value' : prop[0].toUpperCase() + prop.slice(1)}</span><input data-sub-prop="${prop}" value="${escape(sub[prop])}"></label>`).join('')}<label class="alify-control"><span>Type</span><select data-sub-prop="type">${Object.entries(AlifyCF.types).filter(([type]) => type !== 'repeater').map(([type, label]) => `<option value="${type}" ${sub.type === type ? 'selected' : ''}>${escape(label)}</option>`).join('')}</select></label><label class="alify-control"><span>Choices (value : Label, one per line)</span><textarea data-sub-prop="choices">${escape(sub.choices)}</textarea></label></div><label><input type="checkbox" data-sub-prop="required" ${sub.required ? 'checked' : ''}> Required subfield</label><div class="alify-actions"><button type="button" class="button" data-sub-action="up" ${index === 0 ? 'disabled' : ''}>Move up</button><button type="button" class="button" data-sub-action="down" ${index === subs.length - 1 ? 'disabled' : ''}>Move down</button><button type="button" class="button-link-delete" data-sub-action="remove">Remove subfield</button></div></div>`).join('')}<button type="button" class="button" data-sub-action="add" ${subs.length >= 20 ? 'disabled' : ''}>+ Add Subfield</button></div>`;
    }
    function conditionUI(field) {
        const rule = field.condition;
        const sources = fields.filter(item => item.key !== field.key && item.type !== 'repeater' && !item.condition);
        return `<div class="alify-condition"><label><input type="checkbox" data-condition-prop="enabled" ${rule ? 'checked' : ''}> Enable conditional logic</label>${rule ? `<p>Show this field when an unconditional field in this group matches:</p><div class="alify-grid"><label class="alify-control"><span>Source field</span><select data-condition-prop="field"><option value="">Choose field</option>${sources.map(item => `<option value="${escape(item.key)}" ${item.key === rule.field ? 'selected' : ''}>${escape(item.label || item.name || 'Untitled field')}</option>`).join('')}</select></label><label class="alify-control"><span>Comparison</span><select data-condition-prop="operator">${Object.entries({equals: 'Is equal to', not_equals: 'Is not equal to', empty: 'Is empty', not_empty: 'Is not empty'}).map(([key, label]) => `<option value="${key}" ${key === rule.operator ? 'selected' : ''}>${label}</option>`).join('')}</select></label><label class="alify-control"><span>Comparison value</span><input data-condition-prop="value" value="${escape(rule.value)}" ${['empty', 'not_empty'].includes(rule.operator) ? 'disabled' : ''}><small>Use a choice key, attachment ID, or 1 / 0 for True / False. Checkbox/Gallery comparisons match a selected key or image ID.</small></label></div>` : ''}</div>`;
    }
    function render(openKey) {
        list.innerHTML = fields.map((field, i) => `<details class="alify-card" data-key="${escape(field.key)}" ${field.key === openKey ? 'open' : ''}>
            <summary><span class="alify-drag" title="Drag to reorder" aria-hidden="true">⠿</span><span class="alify-index">${String(i + 1).padStart(2, '0')}</span><strong class="alify-card-title">${escape(field.label || 'Untitled field')}</strong><code class="alify-card-name">${escape(field.name)}</code><span class="alify-type">${escape(AlifyCF.types[field.type])}</span></summary>
            <div class="alify-card-body"><div class="alify-grid">${control(field, 'label', 'Field Label', 'The name your editors will see.')}${control(field, 'name', 'Field Name', 'Unique within matching groups. Use lowercase letters, numbers and underscores.')}<label class="alify-control"><span>Field Type</span><select data-prop="type">${Object.entries(AlifyCF.types).map(([key, label]) => `<option value="${key}" ${key === field.type ? 'selected' : ''}>${escape(label)}</option>`).join('')}</select></label>${['repeater', 'gallery'].includes(field.type) ? '' : control(field, 'default', 'Default Value', 'True / False: 1 or 0. Image / File: attachment ID.')}</div>${control(field, 'instructions', 'Instructions', 'Optional guidance for content editors.', true)}<div class="alify-choices" ${['checkbox', 'select'].includes(field.type) ? '' : 'hidden'}>${control(field, 'choices', 'Choices', 'One per line. Format: value : Label. Checkbox defaults are not supported in this release.', true)}</div><label class="alify-required-toggle"><input type="checkbox" data-prop="required" ${field.required ? 'checked' : ''}> Required field</label>${subfieldsUI(field)}${conditionUI(field)}<div class="alify-actions"><button type="button" class="button" data-action="up" ${i === 0 ? 'disabled' : ''}>Move up</button><button type="button" class="button" data-action="down" ${i === fields.length - 1 ? 'disabled' : ''}>Move down</button><button type="button" class="button" data-action="duplicate">Duplicate</button><button type="button" class="button-link-delete" data-action="remove">Delete field</button></div></div></details>`).join('');
        sync();
    }
    function key() { return 'field_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10); }
    function uniqueName(base) { let name = base, suffix = 2; while (fields.some(field => field.name === name)) name = base + '_' + suffix++; return name; }
    document.getElementById('alify-add').addEventListener('click', () => {
        const field = {key: key(), label: '', name: '', type: 'text', instructions: '', default: '', choices: '', required: false};
        fields.push(field); render(field.key); list.lastElementChild.querySelector('input').focus();
    });
    list.addEventListener('input', event => {
        const subProp = event.target.dataset.subProp;
        if (subProp) {
            const parent = fields.find(item => item.key === event.target.closest('.alify-card').dataset.key);
            const sub = parent.sub_fields[Number(event.target.closest('.alify-sub-card').dataset.subIndex)];
            const previous = sub.label;
            sub[subProp] = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
            if (subProp === 'label' && (!sub.name || sub.name === slug(previous))) {
                sub.name = slug(sub.label); event.target.closest('.alify-sub-card').querySelector('[data-sub-prop="name"]').value = sub.name;
            }
            if (subProp === 'type') { render(parent.key); return; }
            sync(); return;
        }
        const conditionProp = event.target.dataset.conditionProp;
        if (conditionProp) {
            const card = event.target.closest('.alify-card');
            const field = fields.find(item => item.key === card.dataset.key);
            if (conditionProp === 'enabled') field.condition = event.target.checked ? {field: '', operator: 'equals', value: ''} : null;
            else field.condition[conditionProp] = event.target.value;
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
        const subAction = event.target.dataset.subAction;
        if (subAction) {
            const parent = fields.find(item => item.key === event.target.closest('.alify-card').dataset.key);
            parent.sub_fields = parent.sub_fields || [];
            const subs = parent.sub_fields;
            const card = event.target.closest('.alify-sub-card');
            const index = card ? Number(card.dataset.subIndex) : -1;
            if (subAction === 'add' && subs.length < 20) subs.push({key: key(), name: '', label: '', type: 'text', default: '', instructions: '', choices: '', required: false});
            if (subAction === 'remove' && window.confirm('Remove subfield? Its values will be discarded from rows the next time each post is saved.')) subs.splice(index, 1);
            if (subAction === 'up' && index > 0) [subs[index - 1], subs[index]] = [subs[index], subs[index - 1]];
            if (subAction === 'down' && index < subs.length - 1) [subs[index + 1], subs[index]] = [subs[index], subs[index + 1]];
            render(parent.key); return;
        }
        const action = event.target.dataset.action;
        if (!action) return;
        const index = fields.findIndex(field => field.key === event.target.closest('.alify-card').dataset.key);
        const field = fields[index];
        if (action === 'remove') { if (!window.confirm(`Delete “${field.label || 'Untitled field'}”? Existing saved values will not be deleted.`)) return; fields.splice(index, 1); }
        if (action === 'duplicate') fields.splice(index + 1, 0, {...field, sub_fields: (field.sub_fields || []).map(sub => ({...sub})), condition: field.condition ? {...field.condition} : null, key: key(), label: field.label + ' (copy)', name: uniqueName((field.name || 'field') + '_copy')});
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
                const source = fields.find(item => item.key === field.condition.field);
                if (!source || source.type === 'repeater' || source.key === field.key || source.condition) {
                    event.preventDefault(); render(field.key); window.alert('Conditional logic must reference an unconditional field in the same group. Fix or disable rules referencing removed fields.'); return;
                }
            }
            if (field.type === 'repeater') {
                const subs = field.sub_fields || [];
                const localNames = new Set();
                if (!subs.length || subs.length > 20 || subs.some(sub => {
                    const invalid = !sub.label.trim() || !/^[a-z][a-z0-9_]*$/.test(sub.name) || localNames.has(sub.name) || sub.type === 'repeater';
                    localNames.add(sub.name); return invalid;
                })) {
                    event.preventDefault(); render(field.key); window.alert('Repeaters need 1–20 basic subfields with labels and unique local names.'); return;
                }
            }
            names.add(field.name);
        }
        sync();
    });
    render();
})(jQuery);
