/* global AlifyMap */
(function () {
    'use strict';
    let loading; const active = new Map(), pending = new WeakSet();
    function sdk() {
        if (window.google?.maps?.importLibrary) return Promise.resolve(window.google.maps);
        if (loading) return loading;
        loading = new Promise((resolve, reject) => {
            const script = document.createElement('script'); let settled = false;
            function finish(error) { if (settled) return; settled = true; clearTimeout(timer); if (error) { script.remove(); loading = undefined; reject(new Error('Google Maps unavailable. Check configuration/network; manual inputs are unchanged.')); } else resolve(window.google.maps); }
            const timer = setTimeout(() => finish(true), 15000);
            window.AlifyCFMapsReady = () => finish(!window.google?.maps?.importLibrary);
            script.onerror = () => finish(true); script.async = true; script.referrerPolicy = 'strict-origin-when-cross-origin';
            script.src = 'https://maps.googleapis.com/maps/api/js?' + new URLSearchParams({key: AlifyMap.key, v: 'weekly', loading: 'async', callback: 'AlifyCFMapsReady'});
            document.head.appendChild(script);
        }); return loading;
    }
    function controls(root) { const result = {}; ['lat', 'lng', 'zoom', 'address'].forEach(key => { result[key] = Array.from(root.querySelectorAll('input[name]')).find(input => input.name.endsWith('[' + key + ']')); }); return result; }
    function coordinate(lat, lng) { lat = Number(lat); lng = Number(lng); return Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? {lat, lng} : null; }
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-map-activate]'); if (!button || button.disabled) return;
        const root = button.closest('.alify-map'); if (!root || active.has(root) || pending.has(root)) return;
        const input = controls(root), status = root.querySelector('[data-map-status]'); pending.add(root); status.textContent = 'Loading optional Google map…';
        try {
            const api = await sdk(); const [{Map: GoogleMap, Circle}, {PlaceAutocompleteElement}] = await Promise.all([api.importLibrary('maps'), api.importLibrary('places')]);
            if (!root.isConnected) return;
            const canvas = root.querySelector('[data-map-canvas]'); canvas.hidden = false;
            const center = input.lat.value !== '' && input.lng.value !== '' ? coordinate(input.lat.value, input.lng.value) : null;
            const map = new GoogleMap(canvas, {center: center || {lat: 0, lng: 0}, zoom: center ? Math.max(1, Math.min(20, Number(input.zoom.value) || 15)) : 2, streetViewControl: false, mapTypeControl: false});
            const point = new Circle({map, center: center || {lat: 0, lng: 0}, radius: 30, draggable: true, visible: !!center, fillColor: '#2271b1', fillOpacity: 0.9, strokeColor: '#ffffff', strokeWeight: 2});
            const search = new PlaceAutocompleteElement(); root.querySelector('[data-map-search]').appendChild(search); let revision = 0;
            function writable() { return root.isConnected && !input.lat.disabled && !input.lng.disabled && !root.closest('[hidden]'); }
            function set(location, address) {
                const value = location && coordinate(typeof location.lat === 'function' ? location.lat() : location.lat, typeof location.lng === 'function' ? location.lng() : location.lng);
                if (!value || !writable()) return;
                input.lat.value = value.lat.toFixed(6); input.lng.value = value.lng.toFixed(6); if (typeof address === 'string') { const chars = Array.from(address).slice(0, 500); while (new TextEncoder().encode(chars.join('')).length > 500) chars.pop(); input.address.value = chars.join(''); }
                point.setCenter(value); point.setVisible(true); map.panTo(value); input.zoom.value = String(Math.max(1, Math.min(20, Math.round(map.getZoom() || 15))));
                input.lat.dispatchEvent(new Event('input', {bubbles: true})); input.lng.dispatchEvent(new Event('change', {bubbles: true})); status.textContent = 'Location selected. Save the form to persist it.';
            }
            const click = map.addListener('click', event => { revision++; set(event.latLng, ''); });
            const drag = point.addListener('dragend', () => { revision++; set(point.getCenter(), ''); });
            const zoom = map.addListener('zoom_changed', () => { if (writable()) { input.zoom.value = String(Math.max(1, Math.min(20, Math.round(map.getZoom() || 15)))); input.zoom.dispatchEvent(new Event('change', {bubbles: true})); } });
            const chosen = async event => {
                const version = ++revision, before = JSON.stringify(Object.values(input).map(control => control.value));
                try { const place = event.placePrediction.toPlace(); await place.fetchFields({fields: ['location', 'formattedAddress']}); if (version !== revision || JSON.stringify(Object.values(input).map(control => control.value)) !== before || !writable()) return; set(place.location, place.formattedAddress); }
                catch (_) { if (root.isConnected && version === revision) status.textContent = 'Place lookup failed; manual coordinates are unchanged.'; }
            };
            search.addEventListener('gmp-select', chosen);
            const manual = event => { if (event.isTrusted) { revision++; const center = input.lat.value !== '' && input.lng.value !== '' ? coordinate(input.lat.value, input.lng.value) : null; point.setVisible(!!center); if (center) { point.setCenter(center); map.panTo(center); } } };
            root.addEventListener('input', manual);
            active.set(root, () => { revision++; [click, drag, zoom].forEach(listener => listener?.remove()); search.removeEventListener('gmp-select', chosen); root.removeEventListener('input', manual); point.setMap(null); root.querySelector('[data-map-search]').replaceChildren(); canvas.replaceChildren(); });
            button.hidden = true; status.textContent = 'Search for a place, click the map or drag the blue point. Nothing is stored until you save.';
        } catch (_) { if (root.isConnected) status.textContent = 'Interactive map unavailable. Manual inputs are unchanged; check Google configuration/network.'; }
        finally { pending.delete(root); }
    });
    new MutationObserver(() => { for (const [root, dispose] of active) { if (!root.isConnected) { dispose(); active.delete(root); } } }).observe(document.body, {subtree: true, childList: true});
})();
