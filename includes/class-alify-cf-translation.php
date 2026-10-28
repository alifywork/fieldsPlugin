<?php
if (!defined('ABSPATH')) { exit; }

/** Polylang adapter: presentation translation, independent values, explicit safe scalar copy only. */
final class Alify_CF_Translation {
    private static $policy = array();
    public static function boot() {
        add_filter('pll_copy_post_metas', function($metas) { if (!Alify_CF_Integrations::enabled('POLYLANG')) { return $metas; } return array_values(array_filter($metas, function($key) { return strpos($key, '_alify_cf_') !== 0; })); });
        add_action('admin_init', function() {
            if (!Alify_CF_Integrations::enabled('POLYLANG') || !function_exists('pll_register_string') || !current_user_can('manage_options')) { return; }
            $schemas = array(); foreach (Alify_CF_API::groups() as $group) { $schemas[] = $group['fields']; }
            foreach (get_posts(array('post_type' => Alify_CF::GROUP, 'post_status' => array('publish', 'draft'), 'numberposts' => 100)) as $group) { $schemas[] = Alify_CF::schema($group->ID); }
            foreach ($schemas as $schema) { self::strings($schema); }
        });
    }
    private static function strings($fields) { foreach ($fields as $field) { foreach (array('label', 'instructions') as $prop) { if (!empty($field[$prop])) { pll_register_string('alify_' . $field['key'] . '_' . $prop, $field[$prop], 'Alify Custom Field', $prop === 'instructions'); } } if (isset($field['sub_fields'])) { self::strings($field['sub_fields']); } foreach ($field['layouts'] ?? array() as $layout) { pll_register_string('alify_' . $layout['key'], $layout['label'], 'Alify Custom Field'); self::strings($layout['sub_fields']); } } }
    public static function display($fields) {
        if (!Alify_CF_Integrations::enabled('POLYLANG') || !function_exists('pll__')) { return $fields; }
        foreach ($fields as &$field) { foreach (array('label', 'instructions') as $prop) { if (!empty($field[$prop])) { $field[$prop] = pll__($field[$prop]); } } if (isset($field['sub_fields'])) { $field['sub_fields'] = self::display($field['sub_fields']); } if (isset($field['layouts'])) { foreach ($field['layouts'] as &$layout) { $layout['label'] = pll__($layout['label']); $layout['sub_fields'] = self::display($layout['sub_fields']); } unset($layout); } } unset($field); return $fields;
    }
    public static function policy($name, $mode) { if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/D', $name) || !in_array($mode, array('translate', 'copy', 'ignore'), true) || count(self::$policy) >= 200) { return new WP_Error('alify_translation', 'Use a valid field name and translate/copy/ignore mode (200 policies maximum).'); } self::$policy[$name] = $mode; return true; }
    public static function get($name, $source, $language) {
        if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/D', $name) || (!is_int($source) && !is_string($source)) || !ctype_digit((string) $source) || !Alify_CF_Integrations::enabled('POLYLANG') || !function_exists('pll_get_post') || !is_string($language) || !preg_match('/^[a-zA-Z0-9_-]{1,32}$/D', $language)) { return null; }
        if ((self::$policy[$name] ?? 'translate') === 'ignore') { return null; } $target = pll_get_post($source, $language); return $target ? alify_get_field($name, $target) : null;
    }
    public static function copy($source, $target, $names) {
        foreach (array($source, $target) as $id) { if ((!is_int($id) && !is_string($id)) || !ctype_digit((string) $id) || (int) $id < 1) { return new WP_Error('alify_translation', 'Use positive source and target post IDs.'); } }
        if (!Alify_CF_Integrations::enabled('POLYLANG') || !function_exists('pll_get_post_language') || !function_exists('pll_get_post') || !current_user_can('edit_post', $source) || !current_user_can('edit_post', $target)) { return new WP_Error('alify_forbidden', 'Translation adapter and edit permission for both posts are required.'); }
        $from = get_post($source); $to = get_post($target); $language = pll_get_post_language($target);
        if (!$from || !$to || (int) $source === (int) $target || $from->post_type !== $to->post_type || !$language || (int) pll_get_post($source, $language) !== (int) $target || !is_array($names) || !$names || count($names) > 200) { return new WP_Error('alify_translation', 'Use linked translations of the same type and 1–200 explicit field names.'); }
        $values = array(); foreach ($names as $name) { $field = is_string($name) ? Alify_CF_API::field($from->post_type, $name, $source) : null; $destination = is_string($name) ? Alify_CF_API::field($to->post_type, $name, $target) : null; if (!$field || !$destination || (self::$policy[$name] ?? 'ignore') !== 'copy' || !in_array($field['type'], array('text', 'textarea', 'number', 'email', 'url', 'date', 'time', 'date_time', 'color', 'range', 'true_false', 'select', 'radio', 'button_group'), true) || Alify_CF_Choice::multiple($field) || $field !== $destination) { return new WP_Error('alify_translation', 'Only policy-opted-in matching scalar definitions may be copied.'); } $value = alify_get_field($name, $source); if (!is_scalar($value)) { return new WP_Error('alify_translation', 'Source scalar value is unavailable.'); } $values[$name] = $value; }
        return Alify_CF_API::write($values, $target);
    }
}
