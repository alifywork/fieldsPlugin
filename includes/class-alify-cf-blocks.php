<?php
if (!defined('ABSPATH')) { exit; }

/** Trusted PHP dynamic blocks. Attributes are PUBLIC post content, never private field storage. */
final class Alify_CF_Blocks {
    private static $definitions = array();
    public static function boot() { add_action('enqueue_block_editor_assets', array(__CLASS__, 'assets')); }
    public static function register($name, $settings) {
        if (!is_string($name) || !preg_match('~^alify/[a-z][a-z0-9-]{0,49}$~D', $name) || isset(self::$definitions[$name]) || count(self::$definitions) >= 30 || !is_array($settings) || array_diff(array_keys($settings), array('title', 'description', 'fields', 'render_callback')) || !is_callable($settings['render_callback'] ?? null) || !is_string($settings['title'] ?? null) || strlen($settings['title']) > 80 || trim(sanitize_text_field($settings['title'])) === '' || !is_array($settings['fields'] ?? null) || !$settings['fields'] || array_values($settings['fields']) !== $settings['fields'] || count($settings['fields']) > 20 || (isset($settings['description']) && (!is_string($settings['description']) || strlen($settings['description']) > 300))) { return new WP_Error('alify_block', 'Blocks need a unique alify/name, title, 1–20 scalar controls and trusted render callback; maximum 30 blocks.'); }
        if (WP_Block_Type_Registry::get_instance()->is_registered($name)) { return new WP_Error('alify_block_collision', 'Native block name is already registered.'); }
        $fields = array(); $attributes = array(); $errors = array();
        foreach ($settings['fields'] as $field) {
            if (!is_array($field) || array_diff(array_keys($field), array('name', 'label', 'type', 'default', 'choices')) || !is_string($field['name'] ?? null) || !preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $field['name']) || in_array($field['name'], array('constructor', 'prototype', 'lock', 'metadata', 'style', 'align', 'anchor'), true) || isset($attributes[$field['name']]) || !is_string($field['label'] ?? null) || strlen($field['label']) > 80 || trim(sanitize_text_field($field['label'])) === '' || !in_array($field['type'] ?? '', array('text', 'textarea', 'number', 'true_false', 'select'), true)) { return new WP_Error('alify_block_field', 'Invalid/duplicate/reserved block control; supported types: text, textarea, integer number, true_false, select.'); }
            $field['label'] = sanitize_text_field($field['label']);
            if ($field['type'] === 'select') {
                if (!is_array($field['choices'] ?? null) || !$field['choices'] || count($field['choices']) > 100) { return new WP_Error('alify_block_choices', 'Select needs 1–100 configured choices.'); }
                foreach ($field['choices'] as $key => &$label) { if (in_array((string) $key, array('__proto__', 'constructor', 'prototype'), true) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', (string) $key) || !is_string($label) || strlen($label) > 200 || trim(sanitize_text_field($label)) === '') { return new WP_Error('alify_block_choices', 'Invalid choice key/label.'); } $label = sanitize_text_field($label); } unset($label);
            } elseif (isset($field['choices'])) { return new WP_Error('alify_block_choices', 'Choices only apply to Select.'); }
            $default = array_key_exists('default', $field) ? $field['default'] : ($field['type'] === 'true_false' ? false : ($field['type'] === 'number' ? 0 : ''));
            $field['default'] = self::value($field, $default, $errors); $fields[] = $field;
            $attributes[$field['name']] = array('type' => $field['type'] === 'true_false' ? 'boolean' : ($field['type'] === 'number' ? 'integer' : 'string'), 'default' => $field['default']);
            if ($field['type'] === 'select') { $attributes[$field['name']]['enum'] = array_merge(array(''), array_map('strval', array_keys($field['choices']))); }
        }
        if ($errors) { return new WP_Error('alify_block_default', 'Invalid block default: ' . implode(' ', $errors)); }
        $definition = array('title' => sanitize_text_field($settings['title']), 'description' => sanitize_text_field($settings['description'] ?? ''), 'fields' => $fields, 'attributes' => $attributes);
        $render = $settings['render_callback'];
        $registered = register_block_type($name, array('api_version' => 2, 'title' => $definition['title'], 'description' => $definition['description'], 'category' => 'design', 'icon' => 'layout', 'attributes' => $attributes, 'uses_context' => array('postId', 'postType'), 'supports' => array('html' => false, 'customClassName' => false), 'render_callback' => function($attributes, $content, $block = null) use ($definition, $render) {
            $errors = array(); $values = self::values($definition, $attributes, $errors); if ($errors) { return ''; } $html = call_user_func($render, $values, $block); return is_string($html) && strlen($html) <= 131072 ? wp_kses_post($html) : '';
        }));
        if (!$registered) { return new WP_Error('alify_block_collision', 'Native block registration failed; name may already exist.'); }
        self::$definitions[$name] = $definition; return true;
    }
    private static function value($field, $value, &$errors) {
        if ($field['type'] === 'true_false') { if (is_bool($value)) { return $value; } }
        elseif ($field['type'] === 'number') { if (is_int($value) && $value >= -1000000 && $value <= 1000000) { return $value; } }
        elseif (is_string($value) && strlen($value) <= 2000 && wp_check_invalid_utf8($value) === $value) {
            if ($field['type'] === 'select') { if ($value === '' || array_key_exists($value, $field['choices'])) { return $value; } }
            else { return $field['type'] === 'textarea' ? sanitize_textarea_field($value) : sanitize_text_field($value); }
        }
        $errors[] = $field['name'] . ': invalid public attribute.'; return null;
    }
    public static function values($definition, $attributes, &$errors) {
        if (!is_array($attributes)) { $errors[] = 'Invalid attributes.'; return array(); } $values = array();
        foreach ($definition['fields'] as $field) { $values[$field['name']] = self::value($field, array_key_exists($field['name'], $attributes) ? $attributes[$field['name']] : $field['default'], $errors); } return $values;
    }
    public static function definitions() { return self::$definitions; }
    public static function assets() {
        if (!self::$definitions) { return; }
        wp_enqueue_script('alify-cf-blocks', plugins_url('assets/blocks.js', ALIFY_CF_FILE), array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render'), ALIFY_CF_VERSION, true);
        wp_localize_script('alify-cf-blocks', 'AlifyBlocks', self::$definitions);
    }
}
