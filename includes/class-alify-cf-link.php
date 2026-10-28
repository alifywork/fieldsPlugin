<?php
if (!defined('ABSPATH')) { exit; }

/** One atomic link value; HTTP(S) only, never executable markup. */
final class Alify_CF_Link {
    public static function clean($raw) {
        if (!is_array($raw) || ($raw['__complete'] ?? '') !== '1') { return ''; }
        foreach (array('url', 'title', 'target') as $key) { if (!isset($raw[$key]) || !is_string($raw[$key])) { return ''; } }
        if (!in_array($raw['target'], array('', '_blank'), true)) { return ''; }
        if ($raw['url'] === '') { return $raw['title'] === '' && $raw['target'] === '' ? array() : ''; }
        $url = $raw['url']; $parts = parse_url($url);
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) || !filter_var($url, FILTER_VALIDATE_URL) || !$parts || !in_array(strtolower($parts['scheme'] ?? ''), array('http', 'https'), true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) { return ''; }
        $url = esc_url_raw($url, array('http', 'https'));
        return $url ? array('url' => $url, 'title' => sanitize_text_field($raw['title']), 'target' => $raw['target']) : '';
    }
    public static function render($field, $value, $id, $input, $error_id = '') {
        $value = is_array($value) ? $value : array();
        $description = $id . '-help' . ($error_id ? ' ' . $error_id : '');
        $invalid = $error_id ? ' aria-invalid="true"' : '';
        echo '<fieldset class="alify-link" id="' . esc_attr($id) . '"><legend class="screen-reader-text">' . esc_html($field['label']) . '</legend>';
        foreach (array('url' => 'URL', 'title' => 'Link title') as $part => $label) {
            echo '<label for="' . esc_attr($id . '-' . $part) . '">' . esc_html($label) . '</label><input type="' . ($part === 'url' ? 'url' : 'text') . '" id="' . esc_attr($id . '-' . $part) . '" name="' . esc_attr($input . '[' . $part . ']') . '" value="' . esc_attr(is_string($value[$part] ?? null) ? $value[$part] : '') . '" aria-describedby="' . esc_attr($description) . '"' . $invalid . ($part === 'url' && !empty($field['required']) ? ' required' : '') . '>';
        }
        echo '<label for="' . esc_attr($id . '-target') . '">Open link in</label><select id="' . esc_attr($id . '-target') . '" name="' . esc_attr($input . '[target]') . '" aria-describedby="' . esc_attr($description) . '"' . $invalid . '><option value="">Same tab</option><option value="_blank" ' . selected($value['target'] ?? '', '_blank', false) . '>New tab</option></select><button type="button" class="button" data-link-clear>Clear link</button><input type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1"></fieldset>';
    }
}
