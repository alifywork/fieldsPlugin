/* global wp, AlifyEditor */
(function () {
    'use strict';
    function payload() {
        const root = Object.create(null);
        document.querySelectorAll('.alify-values [name]').forEach(input => {
            if (input.disabled || (['checkbox', 'radio'].includes(input.type) && !input.checked) || ['button', 'submit'].includes(input.type)) return;
            const parts = input.name.match(/[^\[\]]+/g);
            if (!parts || parts.shift() !== 'alify_values') return;
            let target = root;
            const last = parts.pop();
            for (const part of parts) {
                if (!Object.prototype.hasOwnProperty.call(target, part)) target[part] = Object.create(null);
                target = target[part];
            }
            if (input.name.endsWith('[]')) {
                if (!Object.prototype.hasOwnProperty.call(target, last)) target[last] = [];
                target[last].push(input.value);
            } else target[last] = input.value;
        });
        return {complete: '1', groups: root};
    }
    wp.apiFetch.use((options, next) => {
        const method = (options.method || 'GET').toUpperCase();
        const path = (options.path || '').split('?')[0];
        if (!['POST', 'PUT', 'PATCH'].includes(method) || path !== AlifyEditor.path + '/' + AlifyEditor.postId) return next(options);
        const submitted = payload();
        return next({...options, data: {...(options.data || {}), alify_fields: submitted}}).catch(error => {
            let firstError = null;
            const current = payload();
            const groups = error.data && error.data.alify_errors;
            if (groups) document.querySelectorAll('.alify-values').forEach(group => {
                const marker = group.querySelector('[name$="[__group_complete]"]');
                const match = marker && marker.name.match(/^alify_values\[(\d+)\]/);
                const errors = match && groups[match[1]];
                if (!errors) return;
                group.querySelectorAll('.alify-value').forEach(field => {
                    const input = field.querySelector('[name]');
                    const name = input && input.name.match(/^alify_values\[\d+\]\[([^\]]+)\]/);
                    if (!name || !errors[name[1]]) return;
                    if (JSON.stringify((submitted.groups[match[1]] || {})[name[1]]) !== JSON.stringify((current.groups[match[1]] || {})[name[1]])) return;
                    let message = field.querySelector('.alify-field-error');
                    if (!message) { message = document.createElement('p'); message.className = 'alify-field-error'; message.setAttribute('role', 'alert'); field.appendChild(message); }
                    if (!message.id) message.id = 'alify-editor-error-' + match[1] + '-' + name[1];
                    message.hidden = false; message.textContent = errors[name[1]];
                    if (window.AlifyValidation) {
                        const paths = error.data.alify_error_locations;
                        const target = window.AlifyValidation.show(field, message, paths && paths[match[1]] && paths[match[1]][name[1]]);
                        if (!firstError && !field.hidden) firstError = target;
                        return;
                    }
                    field.querySelectorAll('input, select, textarea').forEach(control => {
                        control.setAttribute('aria-invalid', 'true');
                        const descriptions = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
                        if (!descriptions.includes(message.id)) descriptions.push(message.id);
                        control.setAttribute('aria-describedby', descriptions.join(' '));
                    });
                });
            });
            if (firstError && window.AlifyValidation) window.AlifyValidation.focus(firstError);
            throw error;
        });
    });
})();
