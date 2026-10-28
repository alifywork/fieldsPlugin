/* global wp */
(function () {
    'use strict';
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
