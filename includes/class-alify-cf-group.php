<?php
if (!defined('ABSPATH')) { exit; }

/** Fixed single-level object; shares basic subfield validation with repeaters. */
final class Alify_CF_Group {
    public static function clean($field, $payload, $old = null, $permissions = false, &$issue = null, &$location = null) {
        $issue = ''; $location = null;
        if (!is_array($payload) || ($payload['__complete'] ?? '') !== '1' || !is_array($payload['fields'] ?? null)) { $issue = 'Incomplete Group submission. Reload the editor.'; return ''; }
        $row = $payload['fields']; $row['__row_complete'] = '1';
        $rows = Alify_CF_Repeater::clean($field, array('__complete' => '1', 'rows' => array($row)), is_array($old) ? array($old) : array(), $permissions, $issue, $location);
        if ($location) { unset($location['row']); }
        if (strpos($issue, 'Row 1 → ') === 0) { $issue = substr($issue, strlen('Row 1 → ')); }
        return is_array($rows) ? $rows[0] : '';
    }
    public static function empty_value($field, $value) {
        if (!is_array($value)) { return true; }
        $values = array(); foreach ($field['sub_fields'] as $sub) { $values[$sub['key']] = $value[$sub['name']] ?? ''; }
        foreach ($field['sub_fields'] as $sub) {
            if (!Alify_CF_Conditions::visible($sub, $field['sub_fields'], $values)) { continue; }
            $item = $value[$sub['name']] ?? '';
            if ($sub['type'] === 'group' && self::empty_value($sub, $item)) { continue; }
            if ($item !== '' && $item !== array() && !($sub['type'] === 'true_false' && $item !== '1')) { return false; }
        }
        return true;
    }
    public static function render($field, $value, $id, $input, $class = 'alify-group') {
        echo '<fieldset class="' . esc_attr($class) . '" data-condition-scope="1" id="' . esc_attr($id) . '"><legend class="screen-reader-text">' . esc_html($field['label']) . '</legend>';
        foreach ($field['sub_fields'] as $sub) {
            $sub_id = $id . '-' . $sub['key'];
            echo '<div class="alify-sub-value"' . Alify_CF_Scoped_Conditions::attributes($sub, $field['sub_fields'], $value) . ' data-subfield="' . esc_attr($sub['name']) . '" data-sub-type="' . esc_attr($sub['type']) . '"><label class="alify-label" for="' . esc_attr($sub_id) . '">' . esc_html($sub['label']) . ($sub['required'] ? ' <span class="alify-required">*</span>' : '') . '</label><p id="' . esc_attr($sub_id . '-help') . '" class="description">' . esc_html($sub['instructions']) . '</p>';
            Alify_CF::control($sub, is_array($value) && array_key_exists($sub['name'], $value) ? $value[$sub['name']] : $sub['default'], $sub_id, $input . '[fields][' . $sub['name'] . ']');
            echo '</div>';
        }
        if (Alify_CF_Composition::needs_origin($field['sub_fields'])) { echo '<input type="hidden" name="' . esc_attr($input . '[fields][__row_origin]') . '" value="' . esc_attr(Alify_CF_Scoped_Conditions::origin($value)) . '">'; }
        echo '<input type="hidden" name="' . esc_attr($input . '[fields][__fields_complete]') . '" value="1"><input type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1"></fieldset>';
    }
}
