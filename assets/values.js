/* global wp */
(function () {
    'use strict';
    // Structured schema names + visual row ordinals; never parse translated labels or submitted tokens.
    function clearError(field) {
        const messages = Array.from(field.querySelectorAll('.alify-field-error, .alify-subfield-error'));
        const ids = messages.map(message => message.id).filter(Boolean);
        messages.forEach(message => { message.hidden = true; });
        field.querySelectorAll('.alify-error-jump').forEach(button => button.remove());
        field.querySelectorAll('[aria-describedby]').forEach(control => {
            const keep = control.getAttribute('aria-describedby').split(/\s+/).filter(id => id && !ids.includes(id));
            if (keep.length) control.setAttribute('aria-describedby', keep.join(' ')); else control.removeAttribute('aria-describedby');
        });
        field.querySelectorAll('[aria-invalid]').forEach(control => control.removeAttribute('aria-invalid'));
    }
    function focusError(target) {
        if (!target || target.closest('.alify-value[hidden]')) return;
        for (let parent = target.parentElement; parent; parent = parent.parentElement) {
            if (parent.tagName === 'DETAILS') parent.open = true;
            if (parent.classList.contains('postbox')) {
                parent.classList.remove('closed');
                const inside = parent.querySelector('.inside');
                if (inside) { inside.hidden = false; inside.style.display = ''; }
            }
        }
        if (target.matches('[data-rich-editor]') && window.AlifyRichText) window.AlifyRichText.focus(target);
        else target.focus();
        const scrollTarget = target.matches('[data-rich-editor]') ? target.closest('.alify-rich') : target;
        if (scrollTarget && scrollTarget.scrollIntoView) scrollTarget.scrollIntoView({block: 'center', behavior: 'auto'});
    }
    function showError(field, message, location) {
        const text = message.textContent;
        clearError(field);
        field.querySelectorAll('.alify-subfield-error').forEach(error => error.remove());
        message.hidden = false;
        let scope = field;
        if (location && typeof location.subfield === 'string') {
            let container = field;
            if (Object.prototype.hasOwnProperty.call(location, 'row')) {
                const list = field.querySelector('.alify-rows, .alify-flex-rows');
                container = list && Number.isInteger(location.row) && location.row >= 0 ? list.children[location.row] : null;
            }
            const sub = container && Array.from(container.querySelectorAll('.alify-sub-value')).find(item => item.dataset.subfield === location.subfield);
            if (sub) scope = sub;
        }
        let description = message;
        if (scope !== field) {
            description = scope.querySelector('.alify-subfield-error');
            if (!description) { description = document.createElement('p'); description.className = 'alify-subfield-error'; scope.appendChild(description); }
            description.id = message.id + '-inline'; description.textContent = text; description.hidden = false;
        }
        const controls = Array.from(scope.querySelectorAll('input:not([type="hidden"]), select, textarea, button')).filter(control => !control.disabled && !control.closest('[hidden]'));
        controls.forEach(control => {
            control.setAttribute('aria-invalid', 'true');
            const ids = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
            if (!ids.includes(description.id)) ids.push(description.id);
            control.setAttribute('aria-describedby', ids.join(' '));
        });
        const target = controls.find(control => control.matches('[data-rich-editor], [data-post-search]')) || controls[0] || message;
        if (target === message) message.tabIndex = -1;
        if (scope !== field) {
            const jump = document.createElement('button'); jump.type = 'button'; jump.className = 'button-link alify-error-jump'; jump.textContent = 'Go to invalid field';
            jump.addEventListener('click', () => focusError(target)); message.insertAdjacentElement('afterend', jump);
        }
        return target;
    }
    window.AlifyValidation = {show: showError, focus: focusError, clear: clearError};
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-link-clear]');
        if (!button || button.disabled) return;
        const root = button.closest('.alify-link');
        root.querySelectorAll('input:not([type="hidden"]), select').forEach(control => { control.value = ''; });
        const url = root.querySelector('input[type="url"]');
        url.dispatchEvent(new Event('input', {bubbles: true})); url.focus();
    });
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-radio-clear]');
        if (!button || button.disabled) return;
        const root = button.closest('.alify-radio');
        root.querySelectorAll('input[type="radio"]').forEach(input => { input.checked = false; });
        root.querySelector('input[type="hidden"]').dispatchEvent(new Event('change', {bubbles: true}));
        const first = root.querySelector('input[type="radio"]');
        if (first) first.focus();
    });
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-password-toggle], [data-password-clear]');
        if (!button || button.disabled) return;
        const root = button.closest('.alify-password'), input = root.querySelector('[data-password-input]');
        const toggle = root.querySelector('[data-password-toggle]');
        if (button.hasAttribute('data-password-clear')) {
            input.value = ''; input.type = 'password';
            toggle.setAttribute('aria-pressed', 'false'); toggle.textContent = 'Show value';
            input.dispatchEvent(new Event('input', {bubbles: true})); input.focus();
        } else {
            const show = input.type === 'password'; input.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(show)); toggle.textContent = show ? 'Hide value' : 'Show value';
        }
    });
    function filterChoices(root) {
        const search = root.querySelector('[data-select-search]'), select = root.querySelector('select');
        if (!select) return;
        const term = search ? search.value.toLocaleLowerCase() : '';
        let shown = 0;
        Array.from(select.options).forEach(option => {
            option.hidden = option.value !== '' && !option.selected && !!term && !(option.textContent + ' ' + option.value).toLocaleLowerCase().includes(term);
            if (!option.hidden && option.value !== '') shown++;
        });
        const status = root.querySelector('.alify-select-status');
        if (status) status.textContent = term ? shown + ' choices shown (selected values stay visible).' : '';
    }
    function selectEdited(event) {
        const root = event.target.closest('.alify-select'); if (root) filterChoices(root);
    }
    document.addEventListener('input', selectEdited); document.addEventListener('change', selectEdited);
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-select-clear]');
        if (!button || button.disabled) return;
        const root = button.closest('.alify-select'), select = root.querySelector('select'), search = root.querySelector('[data-select-search]');
        Array.from(select.options).forEach(option => { option.selected = false; });
        if (!select.multiple) select.value = '';
        if (search) search.value = '';
        filterChoices(root); select.dispatchEvent(new Event('change', {bubbles: true})); select.focus();
    });
    function dateTimeValue(input) {
        const match = input && input.value.match(/^([1-9][0-9]{3})-(0[1-9]|1[0-2])-([0-2][0-9]|3[01])[T ]([01][0-9]|2[0-3]):([0-5][0-9])(?::([0-5][0-9])(?:\.0{1,3})?)?$/);
        if (!match || match[0] !== input.value) return '';
        const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
        if (date.getUTCFullYear() !== Number(match[1]) || date.getUTCMonth() !== Number(match[2]) - 1 || date.getUTCDate() !== Number(match[3])) return '';
        return match[1] + '-' + match[2] + '-' + match[3] + ' ' + match[4] + ':' + match[5] + ':' + (match[6] || '00');
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-date-time-clear]');
        if (!button || button.disabled) return;
        const input = button.closest('.alify-date-time').querySelector('input');
        input.value = ''; input.dispatchEvent(new Event('input', {bubbles: true})); input.focus();
    });
    function temporalPreview(root) {
        const input = root.querySelector('[data-temporal-input]'), output = root.querySelector('[data-temporal-preview]');
        if (!input || !output) return;
        const type = root.dataset.temporalType;
        let raw = input.value;
        if (type === 'date_time') raw = dateTimeValue(input);
        if (!raw || (type === 'date' && !/^\d{4}-\d{2}-\d{2}$/.test(raw)) || (type === 'time' && !/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/.test(raw))) { output.textContent = ''; return; }
        const year = type === 'time' ? 2000 : Number(raw.slice(0, 4)), month = type === 'time' ? 1 : Number(raw.slice(5, 7)), day = type === 'time' ? 1 : Number(raw.slice(8, 10));
        const days = [31, year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if (month < 1 || month > 12 || day < 1 || day > days[month - 1]) { output.textContent = ''; return; }
        const time = type === 'date_time' ? raw.slice(11) : type === 'time' ? raw : '00:00:00';
        const hour = Number(time.slice(0, 2)), pad = value => String(value).padStart(2, '0');
        const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const tokens = {Y: String(year).padStart(4, '0'), m: pad(month), d: pad(day), j: String(day), F: months[month - 1], M: months[month - 1].slice(0, 3), H: pad(hour), i: time.slice(3, 5), s: time.slice(6, 8) || '00', g: String(hour % 12 || 12), a: hour < 12 ? 'am' : 'pm', A: hour < 12 ? 'AM' : 'PM'};
        output.textContent = (root.dataset.displayFormat || '').replace(/[YmdjFMHisgaA]/g, token => tokens[token]);
    }
    function temporalEdited(event) {
        const root = event.target.closest('.alify-temporal'); if (root) temporalPreview(root);
    }
    document.addEventListener('input', temporalEdited);
    document.addEventListener('change', temporalEdited);
    document.querySelectorAll('.alify-temporal').forEach(temporalPreview);
    function rangeUnits(text) {
        if (!/^-?(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,3})?$/.test(text)) return null;
        const parts = text.replace('-', '').split('.');
        const units = Number(parts[0]) * 1000 + Number((parts[1] || '').padEnd(3, '0'));
        return units <= 1000000000 ? (text.startsWith('-') ? -units : units) : null;
    }
    function rangeValue(number) {
        if (!number) return null;
        const value = rangeUnits(number.value), min = rangeUnits(number.min), max = rangeUnits(number.max), step = rangeUnits(number.step);
        return value !== null && min !== null && max !== null && step > 0 && value >= min && value <= max && (value - min) % step === 0 ? String(value / 1000) : null;
    }
    function syncRange(event) {
        const root = event.target.closest('.alify-range');
        if (!root) return;
        const number = root.querySelector('[data-range-number]'), slider = root.querySelector('[data-range-slider]');
        if (event.target === slider) {
            number.value = slider.value;
            number.dispatchEvent(new Event('input', {bubbles: true}));
            return;
        }
        const value = rangeValue(number);
        number.setCustomValidity(number.value !== '' && value === null ? 'Use a plain decimal within the bounds, aligned to the step (at most three decimal places).' : '');
        if (value !== null) { slider.value = value; slider.removeAttribute('aria-valuetext'); }
        else slider.setAttribute('aria-valuetext', number.value === '' ? 'No value selected' : 'Numeric input invalid');
    }
    document.addEventListener('input', syncRange);
    document.addEventListener('change', syncRange);
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-range-clear]');
        if (!button || button.disabled) return;
        const root = button.closest('.alify-range'), number = root.querySelector('[data-range-number]');
        number.value = ''; root.querySelector('[data-range-slider]').value = number.min;
        number.dispatchEvent(new Event('input', {bubbles: true})); number.focus();
    });
    document.querySelectorAll('[data-range-number]').forEach(number => syncRange({target: number}));
    function syncColor(event) {
        const root = event.target.closest('.alify-color');
        if (!root) return;
        const text = root.querySelector('input[type="text"]');
        const picker = root.querySelector('[data-color-picker]');
        if (event.target === picker) {
            text.value = picker.value;
            text.dispatchEvent(new Event('input', {bubbles: true}));
        } else if (event.target === text && /^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value.toLowerCase();
    }
    document.addEventListener('input', syncColor);
    document.addEventListener('change', syncColor);
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-color-clear], [data-color-apply]');
        if (!button || button.disabled) return;
        const root = button.closest('.alify-color'), text = root.querySelector('input[type="text"]');
        const picker = root.querySelector('[data-color-picker]');
        text.value = button.hasAttribute('data-color-apply') ? picker.value : '';
        if (!text.value) picker.value = '#000000';
        text.dispatchEvent(new Event('input', {bubbles: true})); text.focus();
    });
    function refreshRows(repeater) {
        const rows = Array.from(repeater.querySelector('.alify-rows').children);
        const hidden = !!repeater.closest('.alify-value[hidden]');
        rows.forEach((row, index) => {
            row.querySelector('.alify-row-label').textContent = 'Row ' + (index + 1);
            row.querySelector('[data-row-action="up"]').disabled = hidden || index === 0;
            row.querySelector('[data-row-action="down"]').disabled = hidden || index === rows.length - 1;
            row.querySelector('[data-row-action="remove"]').disabled = hidden || rows.length <= Number(repeater.dataset.min || 0);
        });
        repeater.querySelector('[data-row-action="add"]').disabled = hidden || rows.length >= Number(repeater.dataset.limit);
        repeater.querySelector('.alify-row-count').textContent = rows.length + ' / ' + repeater.dataset.limit + ' rows';
    }
    let rowSequence = 0;
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-row-action]');
        if (!button || button.disabled) return;
        const repeater = button.closest('.alify-repeater');
        const rows = repeater.querySelector('.alify-rows');
        const row = button.closest('.alify-row');
        const action = button.dataset.rowAction;
        if (action === 'add' && rows.children.length < Number(repeater.dataset.limit)) {
            const token = 'new_' + Date.now() + '_' + (++rowSequence);
            const template = repeater.querySelector('.alify-row-template');
            const fragment = template.content.cloneNode(true);
            fragment.querySelectorAll('[name], [id], [for], [aria-describedby]').forEach(element => {
                ['name', 'id', 'for', 'aria-describedby'].forEach(attr => {
                    if (element.hasAttribute(attr)) element.setAttribute(attr, element.getAttribute(attr).replace(/__ROW__/g, token));
                });
            });
            rows.appendChild(fragment);
            const input = rows.lastElementChild.querySelector('input:not([type="hidden"]), select, textarea');
            if (input) input.focus();
        }
        if (action === 'remove' && rows.children.length > Number(repeater.dataset.min || 0) && window.confirm('Remove this row? Save the post to apply the change.')) { row.remove(); repeater.querySelector('[data-row-action="add"]').focus(); }
        if (action === 'up' && row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
        if (action === 'down' && row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
        refreshRows(repeater);
        repeater.dispatchEvent(new Event('change', {bubbles: true}));
    });
    function refreshConditions() {
        document.querySelectorAll('.alify-values').forEach(group => {
            const fields = Array.from(group.querySelectorAll('.alify-value'));
            fields.forEach(field => {
                let rule;
                try { rule = JSON.parse(field.dataset.condition || 'null'); } catch (_) { rule = null; }
                let visible = true;
                if (rule) {
                    const rawRules = rule.rules || [rule];
                    const rules = Array.isArray(rawRules) && rawRules.length <= 10 ? rawRules : [];
                    const matches = rules.map(rule => {
                        if (!rule || typeof rule.field !== 'string' || typeof rule.value !== 'string') return false;
                        const source = fields.find(item => item.dataset.fieldKey === rule.field);
                        let matched = false;
                        if (source && !['repeater', 'group', 'flexible', 'link', 'wysiwyg', 'relationship', 'password'].includes(source.dataset.fieldType) && !JSON.parse(source.dataset.condition || 'null')) {
                            const inputs = Array.from(source.querySelectorAll('input, select, textarea'));
                            let values;
                            if (source.dataset.fieldType === 'select') {
                                const select = source.querySelector('select'); values = select ? Array.from(select.selectedOptions).map(option => option.value).filter(value => value !== '') : [''];
                            } else if (source.dataset.fieldType === 'gallery') {
                                values = Array.from(source.querySelectorAll('.alify-gallery-items input[type="hidden"]')).map(input => input.value);
                            } else if (['checkbox', 'true_false', 'radio', 'button_group'].includes(source.dataset.fieldType)) {
                                values = inputs.filter(input => input.checked).map(input => input.value);
                                if (source.dataset.fieldType === 'true_false' && !values.length) values = ['0'];
                            } else if (source.dataset.fieldType === 'date_time') {
                                values = [dateTimeValue(inputs[0])];
                            } else if (source.dataset.fieldType === 'range') {
                                values = [rangeValue(source.querySelector('[data-range-number]')) ?? ''];
                            } else values = inputs.length ? [inputs[0].value] : [''];
                            const empty = !values.length || (values.length === 1 && (values[0] === '' || (source.dataset.fieldType === 'true_false' && values[0] === '0')));
                            const matches = values.includes(rule.value);
                            matched = ({equals: matches, not_equals: !matches, empty, not_empty: !empty})[rule.operator] === true;
                        }
                        return matched;
                    });
                    // Invalid references fail closed, including an ANY set containing one.
                    const validSources = rules.every(rule => rule && typeof rule.field === 'string' && typeof rule.value === 'string' && ['equals', 'not_equals', 'empty', 'not_empty'].includes(rule.operator) && fields.some(item => item.dataset.fieldKey === rule.field && item !== field && !['repeater', 'group', 'flexible', 'link', 'wysiwyg', 'relationship', 'password'].includes(item.dataset.fieldType) && !JSON.parse(item.dataset.condition || 'null')));
                    visible = !!rules.length && (!rule.rules || ['all', 'any'].includes(rule.relation)) && validSources && (rule.relation === 'any' ? matches.some(Boolean) : matches.every(Boolean));
                }
                field.hidden = !visible;
                field.querySelectorAll('input, select, textarea, button').forEach(input => {
                    if (field.dataset.fieldType === 'wysiwyg' && input.closest('.wp-editor-wrap')) {
                        if (!visible) {
                            if (!input.hasAttribute('data-alify-rich-disabled')) input.dataset.alifyRichDisabled = input.disabled ? '1' : '0';
                            input.disabled = true;
                        } else if (input.hasAttribute('data-alify-rich-disabled')) {
                            input.disabled = input.dataset.alifyRichDisabled === '1'; delete input.dataset.alifyRichDisabled;
                        }
                    } else input.disabled = !visible;
                });
                field.querySelectorAll('.alify-repeater').forEach(refreshRows);
            });
        });
        document.dispatchEvent(new Event('alify:visibility'));
    }
    function edited(event) {
        const field = event.target.closest('.alify-value');
        if (field) {
            clearError(field);
        }
        refreshConditions();
    }
    document.addEventListener('input', edited);
    document.addEventListener('change', edited);
    refreshConditions();
    let firstError = null;
    document.querySelectorAll('.alify-value > .alify-field-error').forEach(message => {
        let location = null;
        try { location = JSON.parse(message.dataset.errorLocation || 'null'); } catch (_) { /* Legacy feedback has no target. */ }
        const target = showError(message.closest('.alify-value'), message, location);
        if (!firstError && !message.closest('.alify-value[hidden]')) firstError = target;
    });
    if (firstError) focusError(firstError);
    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-media]');
        if (!button) return;
        const container = button.closest('.alify-media');
        if (!container) return;
        const input = container.querySelector('input[type="hidden"]');
        const image = container.querySelector('img');
        const caption = container.querySelector('.alify-media-preview span');
        const remove = container.querySelector('[data-media="remove"]');
        if (button.dataset.media === 'remove') {
            input.value = ''; image.hidden = true; image.removeAttribute('src');
            caption.textContent = 'No ' + container.dataset.type + ' selected'; remove.hidden = true;
            input.dispatchEvent(new Event('change', {bubbles: true}));
            const choose = container.querySelector('[data-media="choose"]');
            if (choose) choose.focus();
            return;
        }
        if (!window.wp || !wp.media) return;
        const isImage = container.dataset.type === 'image';
        const frame = wp.media({title: isImage ? 'Choose image' : 'Choose file', button: {text: 'Use this ' + container.dataset.type}, multiple: false, library: isImage ? {type: 'image'} : {}});
        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            if (isImage && attachment.type !== 'image') return;
            input.value = attachment.id;
            const url = isImage && attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : (isImage ? attachment.url : '');
            // URLs originate in WordPress media, but reject non-HTTP schemes defensively.
            let safe = '';
            try { const parsed = new URL(url, window.location.href); if (url && ['http:', 'https:'].includes(parsed.protocol)) safe = parsed.href; } catch (_) { /* No preview. */ }
            image.hidden = !safe;
            if (safe) image.src = safe; else image.removeAttribute('src');
            caption.textContent = attachment.filename || attachment.title || 'Selected attachment';
            remove.hidden = false;
            input.dispatchEvent(new Event('change', {bubbles: true}));
            button.focus();
        });
        frame.open();
    });
})();
