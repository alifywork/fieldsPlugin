/* global wp, AlifyBlocks */
(function () {
    'use strict';
    const h = wp.element.createElement, Fragment = wp.element.Fragment;
    const Preview = wp.serverSideRender.default || wp.serverSideRender;
    Object.entries(AlifyBlocks).forEach(([name, definition]) => {
        if (wp.blocks.getBlockType(name)) return;
        function Edit({attributes, setAttributes}) {
            // Keep an immutable, debounced snapshot: native preview must never compare a mutated attribute reference.
            const signature = JSON.stringify(Object.fromEntries(definition.fields.map(field => [field.name, attributes[field.name] ?? field.default])));
            const [preview, setPreview] = wp.element.useState(() => JSON.parse(signature));
            wp.element.useEffect(() => { const timer = setTimeout(() => setPreview(JSON.parse(signature)), 350); return () => clearTimeout(timer); }, [signature]);
            const controls = definition.fields.map(field => {
                const value = attributes[field.name] ?? field.default;
                const change = next => setAttributes({[field.name]: next});
                const textChange = next => { if (new Blob([next]).size <= 2000) change(next); };
                const common = {key: field.name, label: field.label};
                if (field.type === 'true_false') return h(wp.components.ToggleControl, {...common, checked: value, onChange: change});
                if (field.type === 'select') return h(wp.components.SelectControl, {...common, value, options: [{label: 'None', value: ''}, ...Object.entries(field.choices).map(([value, label]) => ({label, value}))], onChange: change});
                if (field.type === 'textarea') return h(wp.components.TextareaControl, {...common, value, maxLength: 2000, onChange: textChange});
                if (field.type === 'number') return h(wp.components.TextControl, {...common, type: 'number', min: -1000000, max: 1000000, step: 1, value, onChange: raw => { if (raw === '') change(0); else if (/^-?\d+$/.test(raw) && Number.isSafeInteger(Number(raw)) && Math.abs(Number(raw)) <= 1000000) change(Number(raw)); }});
                return h(wp.components.TextControl, {...common, value, maxLength: 2000, onChange: textChange});
            });
            const props = wp.blockEditor.useBlockProps({'data-alify-block': name});
            return h(Fragment, null,
                h(wp.blockEditor.InspectorControls, null, h(wp.components.PanelBody, {title: definition.title}, ...controls)),
                h('div', props, h('p', null, definition.title), h(Preview, {key: JSON.stringify(preview), block: name, attributes: preview, httpMethod: 'POST'})));
        }
        wp.blocks.registerBlockType(name, {apiVersion: 2, title: definition.title, description: definition.description, icon: 'layout', category: 'design', attributes: definition.attributes, usesContext: ['postId', 'postType'], supports: {html: false, customClassName: false}, edit: Edit, save: () => null});
    });
})();
