<?php
if (!defined('ABSPATH')) { exit; }

/** Shared reference configuration; filters never replace read/edit capabilities. */
final class Alify_CF_Reference_Settings {
    public static function supported($field) { return in_array($field['type'], array('post_object', 'relationship'), true); }
    public static function multiple($field) { return $field['type'] === 'post_object' && !empty($field['multiple']); }
    public static function types() {
        $types = get_post_types(array('show_ui' => true), 'objects');
        foreach (array('attachment', 'revision', Alify_CF::GROUP) as $name) { unset($types[$name]); }
        return $types;
    }
    public static function settings($field, &$errors) {
        if (!self::supported($field)) { return array(); }
        $out = array();
        foreach (array('post_types', 'post_statuses') as $key) {
            if (!array_key_exists($key, $field)) { continue; }
            $raw = $field[$key]; if (is_string($raw)) { $raw = trim($raw) === '' ? array() : preg_split('/\r?\n/', trim($raw)); }
            $allowed = $key === 'post_types' ? array_keys(self::types()) : array('publish', 'private', 'draft', 'pending', 'future');
            if (!is_array($raw) || !$raw || array_values($raw) !== $raw || count($raw) > 20) { $errors[] = 'Reference filters need 1–20 registered types or supported statuses.'; continue; }
            $out[$key] = array(); foreach ($raw as $item) { if (!is_string($item) || !in_array(trim($item), $allowed, true)) { $errors[] = 'Reference filter contains an unavailable type/status.'; } else { $out[$key][] = trim($item); } } $out[$key] = array_values(array_unique($out[$key]));
        }
        if ($field['type'] === 'post_object' && array_key_exists('multiple', $field)) {
            if (!in_array($field['multiple'], array(true, false, 0, 1, '0', '1'), true)) { $errors[] = 'Post Object multiple must be boolean.'; } else { $out['multiple'] = (bool) $field['multiple']; }
        }
        foreach (array('min_items', 'max_items') as $key) {
            if (!array_key_exists($key, $field)) { continue; }
            if ($field['type'] === 'post_object' && !self::multiple($field)) { $errors[] = 'Reference count bounds require multiple Post Object.'; continue; }
            $raw = $field[$key];
            if ((!is_int($raw) && !is_string($raw)) || !preg_match('/^(0|[1-9][0-9]?)$/D', (string) $raw) || (int) $raw > 50 || ($key === 'max_items' && (int) $raw === 0)) { $errors[] = 'Reference bounds must be whole numbers from 0 to 50 (maximum at least 1).'; } else { $out[$key] = (int) $raw; }
        }
        if (($out['min_items'] ?? 0) > ($out['max_items'] ?? 50)) { $errors[] = 'Reference minimum cannot exceed maximum.'; }
        if (array_key_exists('return_format', $field)) { if (!in_array($field['return_format'], array('id', 'object'), true)) { $errors[] = 'Reference return format must be id or object.'; } else { $out['return_format'] = $field['return_format']; } }
        return $out;
    }
    public static function allowed($post, $field = array()) {
        if (!$post) { return false; }
        $types = $field['post_types'] ?? array('post', 'page');
        return in_array($post->post_type, $types, true) && in_array($post->post_status, $field['post_statuses'] ?? array('publish', 'private', 'draft', 'pending', 'future'), true);
    }
    public static function attributes($field) {
        $settings = array_intersect_key($field, array_flip(array('post_types', 'post_statuses')));
        return ' data-reference-kind="' . esc_attr($field['type']) . '" data-reference-settings="' . esc_attr(wp_json_encode($settings)) . '" data-limit="' . esc_attr($field['max_items'] ?? 50) . '"';
    }
    public static function format($field, $value) {
        if (($field['return_format'] ?? 'id') === 'id' || $value === null || $value === '' || $value === array()) { return $value; }
        if ($field['type'] === 'post_object' && !self::multiple($field)) { return Alify_CF_Post_Object::resolve($value, $field); }
        $out = array(); foreach (is_array($value) ? $value : array() as $id) { $post = Alify_CF_Post_Object::resolve($id, $field); if ($post) { $out[] = $post; } } return $out;
    }
}
