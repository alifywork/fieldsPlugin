<?php
if (!defined('ABSPATH')) { exit; }

/** Fixed single-level object; shares basic subfield validation with repeaters. */
final class Alify_CF_Group {
    public static function clean($field, $payload, $old = null, $permissions = false) {
        if (!is_array($payload) || ($payload['__complete'] ?? '') !== '1' || !is_array($payload['fields'] ?? null)) { return ''; }
        $row = $payload['fields']; $row['__row_complete'] = '1';
        $rows = Alify_CF_Repeater::clean($field, array('__complete' => '1', 'rows' => array($row)), is_array($old) ? array($old) : array(), $permissions);
        return is_array($rows) ? $rows[0] : '';
    }
    public static function empty_value($field, $value) {
        if (!is_array($value)) { return true; }
        foreach ($field['sub_fields'] as $sub) {
            $item = $value[$sub['name']] ?? '';
            if ($item !== '' && $item !== array() && !($sub['type'] === 'true_false' && $item !== '1')) { return false; }
        }
        return true;
    }
    public static function render($field, $value, $id, $input) {
        echo '<fieldset class="alify-group" id="' . esc_attr($id) . '"><legend class="screen-reader-text">' . esc_html($field['label']) . '</legend>';
        foreach ($field['sub_fields'] as $sub) {
            $sub_id = $id . '-' . $sub['key'];
            echo '<div class="alify-sub-value"><label class="alify-label" for="' . esc_attr($sub_id) . '">' . esc_html($sub['label']) . ($sub['required'] ? ' <span class="alify-required">*</span>' : '') . '</label><p class="description">' . esc_html($sub['instructions']) . '</p>';
            Alify_CF::control($sub, is_array($value) && array_key_exists($sub['name'], $value) ? $value[$sub['name']] : $sub['default'], $sub_id, $input . '[fields][' . $sub['name'] . ']');
            echo '</div>';
        }
        echo '<input type="hidden" name="' . esc_attr($input . '[fields][__fields_complete]') . '" value="1"><input type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1"></fieldset>';
    }
}
