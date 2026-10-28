/* global wp */
(function () {
    'use strict';
    function refresh(gallery) {
        const items = Array.from(gallery.querySelector('.alify-gallery-items').children);
        const hidden = !!gallery.closest('.alify-value[hidden]');
        items.forEach((item, index) => {
            item.querySelector('[data-gallery="up"]').disabled = hidden || index === 0;
            item.querySelector('[data-gallery="down"]').disabled = hidden || index === items.length - 1;
        });
        const add = gallery.querySelector('[data-gallery="add"]');
        if (add) add.disabled = hidden || items.length >= Number(gallery.dataset.limit);
        gallery.querySelector('[data-gallery="clear"]').disabled = hidden || !items.length;
        gallery.querySelector('.alify-gallery-count').textContent = items.length + ' / ' + gallery.dataset.limit + ' images';
    }
    function safeURL(url) {
        try { const parsed = new URL(url, window.location.href); return url && ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : ''; } catch (_) { return ''; }
    }
    function item(gallery, attachment) {
        const card = document.createElement('div'); card.className = 'alify-gallery-item'; card.dataset.imageId = attachment.id;
        const input = document.createElement('input'); input.type = 'hidden'; input.value = attachment.id;
        const marker = gallery.querySelector('.alify-gallery-complete');
        input.name = marker.name.replace(/\[__complete\]$/, '[ids][]'); card.appendChild(input);
        const url = safeURL(attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url);
        if (url) { const img = document.createElement('img'); img.alt = ''; img.src = url; card.appendChild(img); }
        const title = document.createElement('span'); title.className = 'alify-gallery-title'; title.textContent = attachment.filename || attachment.title || 'Image ' + attachment.id; card.appendChild(title);
        const actions = document.createElement('div'); actions.className = 'alify-gallery-actions';
        [['up', '←', 'Move image earlier'], ['down', '→', 'Move image later'], ['remove', 'Remove', 'Remove image']].forEach(([action, text, label]) => {
            const button = document.createElement('button'); button.type = 'button'; button.className = action === 'remove' ? 'button-link-delete' : 'button'; button.dataset.gallery = action; button.textContent = text; button.setAttribute('aria-label', label); actions.appendChild(button);
        });
        card.appendChild(actions); return card;
    }
    const refreshAll = () => document.querySelectorAll('.alify-gallery').forEach(refresh);
    document.addEventListener('alify:visibility', refreshAll);
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-gallery]');
        if (!button || button.disabled) return;
        const gallery = button.closest('.alify-gallery');
        if (!gallery) return;
        const grid = gallery.querySelector('.alify-gallery-items');
        const card = button.closest('.alify-gallery-item');
        const status = gallery.querySelector('.alify-gallery-status');
        const action = button.dataset.gallery;
        status.textContent = '';
        function changed() { refresh(gallery); gallery.dispatchEvent(new Event('change', {bubbles: true})); }
        if (action === 'remove') { card.remove(); changed(); const focus = gallery.querySelector('[data-gallery="add"]') || gallery.querySelector('[data-gallery="remove"]'); if (focus) focus.focus(); }
        if (action === 'clear' && window.confirm('Remove all images from this gallery? Media files will not be deleted.')) { grid.replaceChildren(); changed(); const add = gallery.querySelector('[data-gallery="add"]'); if (add) add.focus(); }
        if (action === 'up' && card.previousElementSibling) { grid.insertBefore(card, card.previousElementSibling); changed(); button.focus(); }
        if (action === 'down' && card.nextElementSibling) { grid.insertBefore(card.nextElementSibling, card); changed(); button.focus(); }
        if (action !== 'add' || !window.wp || !wp.media) return;
        const frame = wp.media({title: 'Add gallery images', button: {text: 'Add to gallery'}, multiple: true, library: {type: 'image'}});
        frame.on('select', () => {
            // The owning repeater row may have been removed while the modal was open.
            if (!gallery.isConnected || gallery.closest('.alify-value[hidden]')) return;
            const selected = frame.state().get('selection').toJSON();
            if (!Array.isArray(selected) || selected.some(image => image.type !== 'image' || !Number.isSafeInteger(Number(image.id)) || Number(image.id) <= 0)) {
                status.textContent = 'Only valid images can be added.'; return;
            }
            const existing = new Set(Array.from(grid.children).map(card => card.dataset.imageId));
            const additions = selected.filter(image => { const id = String(Number(image.id)); if (existing.has(id)) return false; existing.add(id); return true; });
            if (grid.children.length + additions.length > Number(gallery.dataset.limit)) {
                status.textContent = 'Maximum ' + gallery.dataset.limit + ' images. Select fewer images; the gallery was not changed.'; return;
            }
            additions.forEach(image => grid.appendChild(item(gallery, {...image, id: Number(image.id)})));
            changed(); status.textContent = additions.length + ' images added.'; button.focus();
        });
        frame.open();
    });
    refreshAll();
})();
