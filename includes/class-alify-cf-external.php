<?php
if (!defined('ABSPATH')) { exit; }

/** No arbitrary URL discovery, scripts, redirects or raw provider HTML. */
final class Alify_CF_External {
    private static $requests = 0;
    public static function video($value) {
        if (!is_string($value) || strlen($value) > 2048) { return ''; } $url = wp_parse_url($value);
        if (!$url || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass']) || isset($url['port']) || isset($url['fragment'])) { return ''; }
        $host = strtolower($url['host'] ?? ''); $path = $url['path'] ?? ''; $id = '';
        if (in_array($host, array('youtube.com', 'www.youtube.com', 'youtu.be'), true)) {
            if ($host === 'youtu.be') { $id = ltrim($path, '/'); } elseif ($path === '/watch') { $query = array(); parse_str($url['query'] ?? '', $query); $id = $query['v'] ?? ''; } elseif (preg_match('~^/shorts/([a-zA-Z0-9_-]{11})$~D', $path, $match)) { $id = $match[1]; }
            return is_string($id) && preg_match('/^[a-zA-Z0-9_-]{11}$/D', $id) ? 'https://www.youtube.com/watch?v=' . $id : '';
        }
        return in_array($host, array('vimeo.com', 'www.vimeo.com'), true) && preg_match('~^/([1-9][0-9]{0,11})$~D', $path, $match) ? 'https://vimeo.com/' . $match[1] : '';
    }
    public static function map($raw) {
        if (!is_array($raw) || ($raw['__complete'] ?? '') !== '1' || !is_string($raw['lat'] ?? null) || !is_string($raw['lng'] ?? null) || !is_string($raw['address'] ?? '') || strlen($raw['address'] ?? '') > 500 || (!is_int($raw['zoom'] ?? 15) && !is_string($raw['zoom'] ?? '15')) || !preg_match('/^(?:[1-9]|1[0-9]|20)$/D', (string) ($raw['zoom'] ?? 15))) { return ''; }
        if ($raw['lat'] === '' && $raw['lng'] === '' && ($raw['address'] ?? '') === '') { return array(); }
        $out = array(); foreach (array('lat' => 90, 'lng' => 180) as $key => $bound) { if (!preg_match('/^-?(?:0|[1-9][0-9]{0,2})(?:\.[0-9]{1,6})?$/D', $raw[$key]) || abs((float) $raw[$key]) > $bound) { return ''; } $out[$key] = rtrim(rtrim(number_format((float) $raw[$key], 6, '.', ''), '0'), '.'); if ($out[$key] === '-0') { $out[$key] = '0'; } }
        $out['zoom'] = (int) ($raw['zoom'] ?? 15); $out['address'] = sanitize_text_field($raw['address'] ?? ''); return $out;
    }
    public static function render($field, $value, $id, $input) {
        if ($field['type'] === 'oembed') { echo '<input type="url" id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" value="' . esc_attr(is_string($value) ? $value : '') . '" placeholder="HTTPS YouTube or Vimeo video URL"' . (!empty($field['required']) ? ' required' : '') . '><p class="description">Only public YouTube/Vimeo video URLs. Saving never contacts providers; explicit theme rendering does.</p>'; return; }
        $value = is_array($value) ? $value : array(); echo '<fieldset class="alify-map" id="' . esc_attr($id) . '"><legend class="screen-reader-text">' . esc_html($field['label']) . '</legend>';
        foreach (array('lat' => 'Latitude', 'lng' => 'Longitude', 'address' => 'Address', 'zoom' => 'Zoom (1–20)') as $key => $label) { echo '<label>' . esc_html($label) . ' <input id="' . esc_attr($id . '-' . $key) . '" name="' . esc_attr($input . '[' . $key . ']') . '" value="' . esc_attr($value[$key] ?? ($key === 'zoom' ? 15 : '')) . '"' . ($key === 'address' ? ' maxlength="500"' : ($key === 'zoom' ? ' type="number" min="1" max="20" step="1"' : ' type="number" step="0.000001" min="-' . ($key === 'lat' ? 90 : 180) . '" max="' . ($key === 'lat' ? 90 : 180) . '"' . (!empty($field['required']) ? ' required' : ''))) . '></label> '; }
        if (defined('ALIFY_CF_GOOGLE_MAPS_PICKER') && ALIFY_CF_GOOGLE_MAPS_PICKER === true && defined('ALIFY_CF_GOOGLE_MAPS_KEY') && is_string(ALIFY_CF_GOOGLE_MAPS_KEY) && preg_match('/^[a-zA-Z0-9_-]{10,200}$/D', ALIFY_CF_GOOGLE_MAPS_KEY)) {
            wp_enqueue_script('alify-cf-map', plugins_url('assets/map.js', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION, true);
            wp_localize_script('alify-cf-map', 'AlifyMap', array('key' => ALIFY_CF_GOOGLE_MAPS_KEY));
            echo '<div class="alify-map-picker"><button type="button" class="button" data-map-activate>Enable interactive map/search</button><p class="description">Optional: enabling sends your map coordinates and searches to Google. No device location is collected. Google Maps JavaScript/Places access and billing must be configured. Manual inputs work without it.</p><div data-map-search></div><div data-map-canvas style="height:300px" hidden></div><p data-map-status role="status"></p></div>';
        }
        echo '<input type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1"></fieldset><p class="description">Coordinates are stored locally. Optional Google preview needs ALIFY_CF_GOOGLE_MAPS_KEY (restrict the browser key to your domains). No automatic geocoding or location collection.</p>';
    }
    public static function embed($url) {
        $url = self::video($url); if ($url === '') { return new WP_Error('alify_embed', 'Unsupported video URL.'); }
        $key = 'alify_cf_embed_' . hash('sha256', $url); $cached = get_transient($key); if (is_string($cached)) { return $cached; }
        if (self::$requests++ >= 5) { return new WP_Error('alify_embed_budget', 'Embed request budget reached; retry later.'); }
        $youtube = strpos($url, 'https://www.youtube.com/') === 0; $endpoint = $youtube ? 'https://www.youtube.com/oembed' : 'https://vimeo.com/api/oembed.json';
        $response = wp_safe_remote_get(add_query_arg(array('url' => $url, 'format' => 'json'), $endpoint), array('timeout' => 3, 'redirection' => 0, 'limit_response_size' => 131072, 'sslverify' => true));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) { return new WP_Error('alify_embed_provider', 'Video provider unavailable.'); }
        $data = json_decode(wp_remote_retrieve_body($response), true, 8); if (!is_array($data) || ($data['type'] ?? '') !== 'video' || !is_string($data['html'] ?? null)) { return new WP_Error('alify_embed_provider', 'Unsupported provider response.'); }
        $processor = new WP_HTML_Tag_Processor($data['html']); if (!$processor->next_tag('iframe')) { return new WP_Error('alify_embed_provider', 'Missing video iframe.'); }
        $source = $processor->get_attribute('src'); $parts = is_string($source) ? wp_parse_url($source) : null;
        $expected = $youtube ? substr($url, strlen('https://www.youtube.com/watch?v=')) : substr($url, strlen('https://vimeo.com/'));
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || !in_array($parts['host'] ?? '', $youtube ? array('www.youtube.com', 'www.youtube-nocookie.com') : array('player.vimeo.com'), true) || ($parts['path'] ?? '') !== ($youtube ? '/embed/' : '/video/') . $expected) { return new WP_Error('alify_embed_provider', 'Provider iframe origin or video mismatch.'); }
        $source = $youtube ? 'https://www.youtube-nocookie.com/embed/' . $expected : 'https://player.vimeo.com/video/' . $expected . '?dnt=1';
        $html = '<iframe src="' . esc_url($source) . '" title="' . esc_attr(sanitize_text_field(is_string($data['title'] ?? null) ? substr($data['title'], 0, 200) : 'Video')) . '" width="640" height="360" loading="lazy" referrerpolicy="no-referrer" allowfullscreen></iframe>'; set_transient($key, $html, HOUR_IN_SECONDS); return $html;
    }
    public static function map_html($value) {
        if (!defined('ALIFY_CF_GOOGLE_MAPS_KEY') || !is_string(ALIFY_CF_GOOGLE_MAPS_KEY) || !preg_match('/^[a-zA-Z0-9_-]{10,200}$/D', ALIFY_CF_GOOGLE_MAPS_KEY) || !is_array($value)) { return ''; }
        $value['__complete'] = '1'; $value = self::map($value); if (!is_array($value) || !$value) { return ''; }
        return '<iframe title="Map location" src="' . esc_url(add_query_arg(array('key' => ALIFY_CF_GOOGLE_MAPS_KEY, 'q' => $value['lat'] . ',' . $value['lng'], 'zoom' => $value['zoom']), 'https://www.google.com/maps/embed/v1/place')) . '" width="600" height="400" loading="lazy" referrerpolicy="no-referrer" allowfullscreen></iframe>';
    }
}
