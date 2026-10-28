<?php
if (!defined('ABSPATH')) { exit; }

/** Choice settings and complete multi-select payloads; raw APIs always return keys. */
final class Alify_CF_Choice {
    const MAX_SELECTED = 100;
    public static function supported($type) { return in_array($type, array('select', 'checkbox', 'radio', 'button_group'), true); }
    public static function multiple($field) { return $field['type'] === 'select' && !empty($field['multiple']); }
    public static function settings($field, &$errors) {
        if (!self::supported($field['type'])) { return array(); }
        $out = array();
        if (array_key_exists('return_format', $field)) {
            if (!is_string($field['return_format']) || !in_array($field['return_format'], array('value', 'label', 'array'), true)) { $errors[] = 'Choice return format must be value, label or array.'; }
            else { $out['return_format'] = $field['return_format']; }
        }
        if (in_array($field['type'], array('radio', 'checkbox'), true) && array_key_exists('layout', $field)) {
            if (!is_string($field['layout']) || !in_array($field['layout'], array('vertical', 'horizontal'), true)) { $errors[] = 'Choice layout must be vertical or horizontal.'; }
            else { $out['layout'] = $field['layout']; }
        }
        if ($field['type'] === 'select') {
            foreach (array('multiple', 'searchable') as $key) {
                if (!array_key_exists($key, $field)) { continue; }
                if (!in_array($field[$key], array(true, false, 0, 1, '0', '1'), true)) { $errors[] = 'Select settings must be boolean values.'; }
                else { $out[$key] = (bool) $field[$key]; }
            }
        }
        return $out;
    }
    private static function keys($field, $raw) {
        if (!is_array($raw) || array_values($raw) !== $raw || count($raw) > self::MAX_SELECTED) { return null; }
        $choices = Alify_CF::choices($field); $out = array();
        foreach ($raw as $key) {
            if ((!is_string($key) && !is_int($key)) || !array_key_exists((string) $key, $choices)) { return null; }
            $key = (string) $key; if (!in_array($key, $out, true)) { $out[] = $key; }
        }
        return $out;
    }
    public static function clean($field, $payload) {
        if (!is_array($payload) || ($payload['__complete'] ?? '') !== '1') { return ''; }
        $keys = self::keys($field, array_key_exists('values', $payload) ? $payload['values'] : array());
        // Store choice-definition order, not browser-specific selection history.
        return $keys === null ? '' : array_values(array_intersect(array_map('strval', array_keys(Alify_CF::choices($field))), $keys));
    }
    public static function defaults($field, $raw, &$errors) {
        if (is_string($raw)) { $raw = $raw === '' ? array() : array_map('trim', explode("\n", $raw)); }
        $keys = self::keys($field, $raw);
        if ($keys === null) { $errors[] = 'Choice defaults must be up to 100 configured keys, one per line or a JSON list.'; return array(); }
        return array_values(array_intersect(array_map('strval', array_keys(Alify_CF::choices($field))), $keys));
    }
    public static function format($field, $value) {
        if ($value === null || $value === '') { return $value; }
        $mode = $field['return_format'] ?? 'value';
        if ($mode === 'value') { return $value; }
        $choices = Alify_CF::choices($field);
        $one = function($key) use ($choices, $mode) {
            if ((!is_string($key) && !is_int($key)) || !array_key_exists((string) $key, $choices)) { return null; }
            return $mode === 'label' ? $choices[$key] : array('value' => (string) $key, 'label' => $choices[$key]);
        };
        if ($field['type'] === 'checkbox' || self::multiple($field)) {
            $out = array(); foreach (is_array($value) ? $value : array() as $key) { $item = $one($key); if ($item !== null) { $out[] = $item; } } return $out;
        }
        return $one($value);
    }
    public static function render_select($field, $value, $id, $input, $error_id) {
        $multiple = self::multiple($field);
        $described = $id . '-help' . ($error_id ? ' ' . $error_id : '');
        echo '<div class="alify-select">';
        if (!empty($field['searchable'])) { echo '<label class="alify-select-search">Filter choices <input type="search" data-select-search aria-label="' . esc_attr($field['label'] . ': filter choices') . '" autocomplete="off"></label>'; }
        if ($multiple) { echo '<input type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1">'; }
        echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($input . ($multiple ? '[values][]' : '')) . '" aria-describedby="' . esc_attr($described) . '"' . ($multiple ? ' multiple size="6"' : '') . (!empty($field['required']) ? ' required' : '') . ($error_id ? ' aria-invalid="true"' : '') . '>';
        if (!$multiple) { echo '<option value="">Select an option</option>'; }
        foreach (Alify_CF::choices($field) as $key => $label) {
            $key = (string) $key; $selected = $multiple ? in_array($key, is_array($value) ? $value : array(), true) : is_scalar($value) && (string) $value === $key;
            echo '<option value="' . esc_attr($key) . '"' . ($selected ? ' selected' : '') . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        if (empty($field['required'])) { echo '<button type="button" class="button-link" data-select-clear>Clear selection</button>'; }
        echo '<span class="alify-select-status" aria-live="polite"></span></div>';
    }
}
