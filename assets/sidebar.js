/* global wp, AlifySidebar */
(function () {
    'use strict';
    const h = wp.element.createElement, store = 'alify/fields', emptyDraft = {};
    if (!wp.data.select(store)) wp.data.registerStore(store, {
        reducer(state = {}, action) { return action.type === 'SET' ? {...state, [action.post]: {...state[action.post], [action.name]: action.value}} : state; },
        actions: {set(post, name, value) { return {type: 'SET', post, name, value}; }},
        selectors: {getDraft(state, post) { return state[post] || emptyDraft; }}
    });
    const supported = ['text', 'textarea', 'email', 'url', 'number', 'date', 'time', 'color', 'true_false', 'select'];
    const rootFor = field => document.querySelector('.alify-value[data-field-key="' + field.key + '"]');
    const inputFor = field => rootFor(field)?.querySelector('input[name]:not([type="hidden"]), textarea[name], select[name]');
    function Sidebar() {
        const post = wp.data.useSelect(select => select('core/editor').getCurrentPostId(), []);
        const draft = wp.data.useSelect(select => select(store).getDraft(post), [post]);
        const [data, setData] = wp.element.useState(null), [error, setError] = wp.element.useState(''), [version, changed] = wp.element.useState(0);
        wp.element.useEffect(() => { let active = true; setData(null); wp.apiFetch({path: '/alify/v1/posts/' + post}).then(value => { if (active) setData(value); }).catch(() => { if (active) setError('Fields are unavailable for this object.'); }); return () => { active = false; }; }, [post]);
        wp.element.useEffect(() => {
            const sync = event => { if (event.target?.closest?.('.alify-values')) changed(value => value + 1); };
            document.addEventListener('input', sync); document.addEventListener('change', sync); document.addEventListener('alify:visibility', sync);
            const observer = new MutationObserver(() => changed(value => value + 1)); observer.observe(document.body, {childList: true, subtree: true});
            return () => { observer.disconnect(); document.removeEventListener('input', sync); document.removeEventListener('change', sync); document.removeEventListener('alify:visibility', sync); };
        }, []);
        const controls = data ? Object.entries(data.schema).filter(([name, field]) => Object.prototype.hasOwnProperty.call(data.values, name) && supported.includes(field.type) && !field.multiple).map(([name, field]) => {
            const input = inputFor(field), root = rootFor(field); if (root?.hidden) return null;
            const value = input ? (field.type === 'true_false' ? input.checked : input.value) : (draft[name] ?? data.values[name] ?? '');
            const change = next => {
                const current = inputFor(field); if (!field.write || !current || current.disabled || rootFor(field)?.hidden) return;
                if (field.type === 'true_false') current.checked = next; else current.value = next;
                current.dispatchEvent(new Event('input', {bubbles: true})); current.dispatchEvent(new Event('change', {bubbles: true})); wp.data.dispatch(store).set(post, name, next);
            };
            const common = {key: name, label: field.label, disabled: !field.write || !input || input.disabled, onChange: change};
            if (field.type === 'true_false') return h(wp.components.ToggleControl, {...common, checked: !!value});
            if (field.type === 'textarea') return h(wp.components.TextareaControl, {...common, value});
            if (field.type === 'select') return h(wp.components.SelectControl, {...common, value, options: [{label: 'None', value: ''}, ...Object.entries(field.choices || {}).map(([value, label]) => ({value, label}))]});
            return h(wp.components.TextControl, {...common, value, type: ['email', 'url', 'number', 'date', 'time', 'color'].includes(field.type) ? field.type : 'text'});
        }) : [];
        return h(wp.editPost.PluginSidebar, {name: 'alify-fields', title: 'Alify Custom Field', icon: 'edit'}, h(wp.components.PanelBody, {title: 'Post fields'}, h('p', null, 'Save with the normal WordPress Update/Publish button. Only exposed scalar fields appear; structured fields stay in their metaboxes.'), error ? h('p', {role: 'alert'}, error) : !data ? h(wp.components.Spinner) : controls.length ? controls : h('p', null, 'No readable supported scalar fields are exposed.')));
    }
    wp.plugins.registerPlugin('alify-fields', {render: Sidebar});
})();
