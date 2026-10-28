<?php
if (!defined('ABSPATH')) { exit; }

/** Single-level, bounded repeaters. Stored rows use local subfield names. */
final class Alify_CF_Repeater {
    const MAX_ROWS = 50;
    const MAX_FIELDS = 20;

    public static function schema($raw, &$errors) {
        if (!is_array($raw) || !$raw || count($raw) > self::MAX_FIELDS) {
            $errors[] = 'Groups and repeaters require between 1 and 20 subfields.'; return array();
        }
        $result = array(); $names = array(); $keys = array();
        foreach ($raw as $field) {
            if (!is_array($field)) { $errors[] = 'Invalid repeater subfield.'; continue; }
            foreach (array('name', 'key', 'label', 'type', 'instructions', 'choices') as $prop) {
                if (isset($field[$prop]) && !is_string($field[$prop])) { $errors[] = 'Invalid repeater subfield setting.'; continue 2; }
            }
            $name = $field['name'] ?? ''; $type = $field['type'] ?? 'text';
            $key = sanitize_key($field['key'] ?? '') ?: 'field_' . wp_generate_uuid4();
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $name) || isset($names[$name]) || isset($keys[$key]) || trim($field['label'] ?? '') === '' || !isset(Alify_CF::types()[$type]) || in_array($type, array('repeater', 'group'), true) || !empty($field['condition'])) {
                $errors[] = 'Container subfields need unique names/keys, labels and a basic type; nesting and subfield conditions are not supported.'; continue;
            }
            $names[$name] = true; $keys[$key] = true;
            $clean = array('name' => $name, 'key' => $key, 'type' => $type, 'label' => sanitize_text_field($field['label']), 'instructions' => sanitize_textarea_field($field['instructions'] ?? ''), 'choices' => sanitize_textarea_field($field['choices'] ?? ''), 'required' => !empty($field['required']));
            $clean['default'] = $type === 'gallery' ? array() : Alify_CF::clean_value($clean, $field['default'] ?? '');
            $result[] = $clean;
        }
        return $result;
    }

    /** Invalid/truncated payloads return '', causing the caller to retain all old rows. */
    public static function clean($field, $payload, $old = null, $permissions = false) {
        if (!is_array($payload) || ($payload['__complete'] ?? '') !== '1') { return ''; }
        $rows = $payload['rows'] ?? array();
        if (!is_array($rows) || count($rows) > self::MAX_ROWS || empty($field['sub_fields'])) { return ''; }
        $allowed_media = array();
        foreach ((array) $old as $row) {
            if (!is_array($row)) { continue; }
            foreach ($field['sub_fields'] as $sub) {
                if ($sub['type'] === 'gallery') {
                    foreach ((array) ($row[$sub['name']] ?? array()) as $image_id) { if (is_scalar($image_id)) { $allowed_media[] = (string) $image_id; } }
                }
                if (in_array($sub['type'], array('image', 'file'), true) && !empty($row[$sub['name']])) { $allowed_media[] = (string) $row[$sub['name']]; }
            }
        }
        $clean = array();
        foreach ($rows as $row) {
            if (!is_array($row) || ($row['__row_complete'] ?? '') !== '1') { return ''; }
            $out = array();
            foreach ($field['sub_fields'] as $sub) {
                $raw = $row[$sub['name']] ?? '';
                $value = $sub['type'] === 'gallery' ? Alify_CF_Gallery::clean($raw, $allowed_media, $permissions) : Alify_CF::clean_value($sub, $raw);
                $empty = $value === '' || $value === array() || ($sub['type'] === 'true_false' && $value !== '1');
                if (($sub['type'] === 'gallery' && $value === '') || (!empty($sub['required']) && $empty) || ($raw !== '' && $raw !== array() && $value === '')) { return ''; }
                if ($permissions && in_array($sub['type'], array('image', 'file'), true) && $value !== '' && !in_array((string) $value, $allowed_media, true) && !current_user_can('edit_post', $value)) { return ''; }
                $out[$sub['name']] = $value;
            }
            $clean[] = $out;
        }
        return $clean;
    }

    public static function render($field, $value, $id, $input) {
        $rows = is_array($value) ? $value : array();
        echo '<div class="alify-repeater" data-base-id="' . esc_attr($id) . '" data-limit="50"><div class="alify-rows">';
        foreach ($rows as $index => $row) { self::row($field, is_array($row) ? $row : array(), $id, $input, $index); }
        echo '</div><template class="alify-row-template">';
        self::row($field, null, $id, $input, '__ROW__');
        echo '</template><input type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1"><div class="alify-repeater-footer"><button type="button" class="button" data-row-action="add">+ Add Row</button> <span class="alify-row-count" aria-live="polite">' . esc_html(count($rows)) . ' / 50 rows</span></div><p class="description">Maximum 50 rows. Invalid rows retain the entire previously saved repeater.</p></div>';
    }

    private static function row($field, $row, $id, $input, $index) {
        echo '<div class="alify-row"><div class="alify-row-bar"><strong class="alify-row-label">Row</strong><div><button type="button" class="button" data-row-action="up" aria-label="Move row up">↑</button> <button type="button" class="button" data-row-action="down" aria-label="Move row down">↓</button> <button type="button" class="button-link-delete" data-row-action="remove">Remove row</button></div></div>';
        foreach ($field['sub_fields'] as $sub) {
            $sub_id = $id . '-' . $index . '-' . $sub['key'];
            $name = $input . '[rows][' . $index . '][' . $sub['name'] . ']';
            $value = $row === null ? $sub['default'] : ($row[$sub['name']] ?? '');
            echo '<div class="alify-sub-value"><label class="alify-label" for="' . esc_attr($sub_id) . '">' . esc_html($sub['label']) . (!empty($sub['required']) ? ' <span class="alify-required">*</span>' : '') . '</label><p class="description" id="' . esc_attr($sub_id . '-help') . '">' . esc_html($sub['instructions']) . '</p>';
            Alify_CF::control($sub, $value, $sub_id, $name);
            echo '</div>';
        }
        echo '<input type="hidden" name="' . esc_attr($input . '[rows][' . $index . '][__row_complete]') . '" value="1"></div>';
    }
}
