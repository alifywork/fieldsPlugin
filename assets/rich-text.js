/* global wp, tinymce, jQuery */
(function () {
    'use strict';
    const selector = 'textarea[data-rich-editor]';
    function editorFor(textarea) { return window.tinymce && tinymce.get(textarea.id); }
    function syncOne(textarea) {
        const editor = editorFor(textarea);
        // Text-tab edits live in the textarea, not the inactive Visual editor.
        if (editor && editor.initialized && !editor.isHidden()) editor.save();
    }
    function sync() { document.querySelectorAll(selector).forEach(syncOne); }
    function enable(textarea) {
        if (!textarea || textarea.disabled || !window.wp || !wp.editor || !wp.editor.initialize) return;
        if (editorFor(textarea)) return;
        textarea.dataset.richEnabled = '1';
        wp.editor.initialize(textarea.id, {
            mediaButtons: textarea.dataset.richMedia === '1',
            quicktags: textarea.dataset.richMode !== 'visual',
            tinymce: textarea.dataset.richMode === 'text' ? false : {
                // Store explicit HTML paragraphs; the native post editor otherwise strips them on save.
                wpautop: false,
                toolbar1: textarea.dataset.richToolbar === 'basic' ? 'bold,italic,bullist,numlist,link,unlink,undo,redo' : 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo',
                toolbar2: '',
                setup: function (editor) {
                    editor.on('change input undo redo', function () {
                        if (editor.isHidden()) return;
                        editor.save(); textarea.dispatchEvent(new Event('input', {bubbles: true}));
                    });
                }
            }
        });
        const button = textarea.closest('.alify-rich').querySelector('[data-rich-enable]');
        if (button) button.hidden = true;
    }
    function focus(textarea) {
        const editor = editorFor(textarea);
        if (editor && editor.initialized && !editor.isHidden()) {
            editor.focus();
            const iframe = document.getElementById(textarea.id + '_ifr');
            if (iframe && textarea.hasAttribute('aria-invalid')) {
                iframe.setAttribute('aria-invalid', 'true');
                iframe.setAttribute('aria-describedby', textarea.getAttribute('aria-describedby') || '');
            }
        } else textarea.focus();
    }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-rich-enable]');
        if (button && !button.disabled) enable(button.closest('.alify-rich').querySelector(selector));
    });
    // Capture runs before ordinary form serialization and WordPress submit handlers.
    document.addEventListener('submit', sync, true);
    // TinyMCE iframes cannot safely be moved in the DOM. Save/remove before metabox sorting.
    if (window.jQuery) {
        jQuery(document).on('sortstart', '.meta-box-sortables', function (event, ui) {
            if (!ui || !ui.item) return;
            ui.item[0].querySelectorAll(selector).forEach(textarea => {
                syncOne(textarea);
                if (textarea.dataset.richEnabled === '1') wp.editor.remove(textarea.id);
            });
        }).on('sortstop', '.meta-box-sortables', function (event, ui) {
            if (!ui || !ui.item) return;
            ui.item[0].querySelectorAll(selector).forEach(textarea => {
                if (textarea.dataset.richEnabled === '1') enable(textarea);
            });
        });
    }
    function detach(root) { root.querySelectorAll(selector).forEach(textarea => { syncOne(textarea); if (textarea.dataset.richEnabled === '1' && window.wp && wp.editor) wp.editor.remove(textarea.id); }); }
    function restore(root) { root.querySelectorAll(selector).forEach(textarea => { if (textarea.dataset.richEnabled === '1') enable(textarea); }); }
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-row-action="up"], [data-row-action="down"], [data-row-action="remove"], [data-flex-action="up"], [data-flex-action="down"], [data-flex-action="remove"]');
        if (!button || button.disabled) return; const row = button.closest('.alify-row, .alify-flex-row'); if (!row) return;
        const action = button.dataset.rowAction || button.dataset.flexAction, affected = [row];
        const sibling = action === 'down' ? row.nextElementSibling : action === 'up' ? row.previousElementSibling : null;
        if (sibling) affected.push(sibling);
        affected.forEach(detach);
        // A native click can run a microtask checkpoint between capture and bubble listeners.
        // Restore only in the next task, after every DOM move handler has completed.
        setTimeout(() => affected.forEach(item => { if (item.isConnected) restore(item); }), 0);
    }, true);
    window.AlifyRichText = {sync, focus, detach, restore, enable};
})();
