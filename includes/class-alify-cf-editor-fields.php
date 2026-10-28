<?php
if (!defined('ABSPATH')) { exit; }

final class Alify_CF_Editor_Fields {
    public static function presentation($type) { return in_array($type, array('tab', 'accordion', 'message'), true); }
    public static function icons() { return array('admin-home', 'admin-users', 'admin-post', 'admin-page', 'admin-media', 'admin-site', 'admin-tools', 'admin-settings', 'calendar', 'clock', 'email', 'phone', 'location', 'location-alt', 'search', 'star-filled', 'star-empty', 'heart', 'yes', 'no', 'plus', 'minus', 'arrow-up-alt', 'arrow-down-alt', 'arrow-left-alt', 'arrow-right-alt', 'download', 'upload', 'external', 'portfolio', 'book', 'cart', 'format-image', 'format-video', 'format-audio', 'edit', 'trash', 'visibility', 'hidden', 'lock'); }
    public static function settings($field, &$errors) {
        $out = array(); $type = $field['type'];
        if (self::presentation($type)) {
            if (!empty($field['required']) || !empty($field['condition']) || !empty($field['api_read']) || !empty($field['api_write']) || !empty($field['public_output'])) { $errors[] = 'Layout/message fields cannot be required, conditional or exposed as stored values.'; }
            if ($type === 'message') { $content = $field['content'] ?? ''; if (!is_string($content) || strlen($content) > 16384) { $errors[] = 'Messages need at most 16384 bytes of safe HTML.'; } else { $out['content'] = wp_kses($content, array('p' => array(), 'br' => array(), 'strong' => array(), 'em' => array(), 'code' => array(), 'ul' => array(), 'ol' => array(), 'li' => array(), 'a' => array('href' => true, 'title' => true), 'h3' => array(), 'h4' => array()), array('http', 'https')); } }
        }
        if ($type === 'icon_picker' && array_key_exists('icons', $field)) {
            $raw = $field['icons']; if (is_string($raw)) { $raw = trim($raw) === '' ? array() : preg_split('/\r?\n/', trim($raw)); }
            if (!is_array($raw) || !$raw || array_values($raw) !== $raw || count($raw) > 40) { $errors[] = 'Choose 1–40 supported Dashicons.'; }
            else { $out['icons'] = array(); foreach ($raw as $icon) { if (!is_string($icon) || !in_array(trim($icon), self::icons(), true)) { $errors[] = 'Unsupported Dashicon identifier.'; } else { $out['icons'][] = trim($icon); } } $out['icons'] = array_values(array_unique($out['icons'])); }
        }
        if ($type === 'wysiwyg') {
            foreach (array('toolbar' => array('basic', 'full'), 'editor_mode' => array('both', 'visual', 'text')) as $key => $allowed) { if (array_key_exists($key, $field)) { if (!in_array($field[$key], $allowed, true)) { $errors[] = 'Choose a supported rich editor preset.'; } else { $out[$key] = $field[$key]; } } }
            if (array_key_exists('media_buttons', $field)) { if (!in_array($field['media_buttons'], array(true, false, 0, 1, '0', '1'), true)) { $errors[] = 'Media buttons must be boolean.'; } else { $out['media_buttons'] = (bool) $field['media_buttons']; } }
        }
        if (in_array($type, array('repeater', 'flexible'), true)) {
            if (array_key_exists('collapsed', $field)) { if (!in_array($field['collapsed'], array(true, false, 0, 1, '0', '1'), true)) { $errors[] = 'Collapsed must be boolean.'; } else { $out['collapsed'] = (bool) $field['collapsed']; } }
            if (array_key_exists('row_label', $field)) { if (!is_string($field['row_label']) || !preg_match('/^([a-z][a-z0-9_]*)?$/D', $field['row_label'])) { $errors[] = 'Use a subfield name for row labels.'; } else { $out['row_label'] = $field['row_label']; } }
        }
        return $out;
    }
    public static function render($field, $value, $id, $input) {
        if ($field['type'] === 'message') { echo '<div class="alify-message">' . $field['content'] . '</div>'; return; }
        if (self::presentation($field['type'])) { echo '<button type="button" class="button" id="' . esc_attr($id . '-layout') . '" data-editor-' . ($field['type'] === 'tab' ? 'tab' : 'accordion') . '>' . esc_html($field['label']) . '</button>'; return; }
        echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" data-icon-picker' . (!empty($field['required']) ? ' required' : '') . '><option value="">No icon</option>';
        foreach ($field['icons'] ?? self::icons() as $icon) { echo '<option value="' . esc_attr('dashicons-' . $icon) . '"' . selected($value, 'dashicons-' . $icon, false) . '>' . esc_html($icon) . '</option>'; }
        echo '</select><span data-icon-preview aria-hidden="true"></span>';
    }
    public static function clean_icon($field, $value) { return is_string($value) && strpos($value, 'dashicons-') === 0 && in_array(substr($value, 10), $field['icons'] ?? self::icons(), true) ? $value : ''; }
    public static function attributes($field) { return ' data-row-label="' . esc_attr($field['row_label'] ?? '') . '" data-collapsed="' . (!empty($field['collapsed']) ? '1' : '0') . '"'; }
}
