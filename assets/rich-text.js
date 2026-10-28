/* global wp, tinymce, jQuery */
(function () {
    'use strict';
    const selector = 'textarea[data-rich-editor]';
    function editorFor(textarea) { return window.tinymce && tinymce.get(textarea.id); }
    function syncOne(textarea) {
        const editor = editorFor(textarea);
        // Text-tab edits live in the textarea, not the inactive Visual editor.
        if (editor && !editor.isHidden()) editor.save();
    }
    function sync() { document.querySelectorAll(selector).forEach(syncOne); }
    function enable(textarea) {
        if (!textarea || textarea.disabled || !window.wp || !wp.editor || !wp.editor.initialize) return;
        if (editorFor(textarea)) return;
        textarea.dataset.richEnabled = '1';
        wp.editor.initialize(textarea.id, {
            mediaButtons: false,
            quicktags: true,
            tinymce: {
                wpautop: true,
                toolbar1: 'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo',
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
        if (editor && !editor.isHidden()) {
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
    window.AlifyRichText = {sync, focus};
})();
