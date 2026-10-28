<?php
if (!defined('ABSPATH')) { exit; }

/** Reusable schema snapshots. Expanded fields, not runtime indirection, are stored/exported. */
final class Alify_CF_Library {
    const OPTION = 'alify_cf_field_library';
    private static $registered = array();
    private static function error($message) { return new WP_Error('alify_library', $message); }
    private static function key($key) { return is_string($key) && preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $key); }
    private static function saved() { $saved = get_option(self::OPTION, array()); return is_array($saved) ? $saved : array(); }
    private static function definitions() { return self::$registered + self::saved(); }
    private static function bounded($value) { $json = wp_json_encode($value, 0, 24); return is_string($json) && strlen($json) <= 1048576; }
    public static function register($key, $title, $fields) {
        if (!self::key($key) || !is_string($title) || trim(sanitize_text_field($title)) === '' || strlen($title) > 200 || !is_array($fields) || !$fields || array_values($fields) !== $fields || count($fields) > 200 || !self::bounded($fields) || isset(self::definitions()[$key]) || count(self::$registered) >= 50) { return self::error('Use a unique library key/title, bounded field list and at most 50 trusted definitions.'); }
        self::$registered[$key] = array('title' => sanitize_text_field($title), 'fields' => $fields); return true;
    }
    public static function unregister($key) { if (!is_string($key)) { return false; } $exists = isset(self::$registered[$key]); unset(self::$registered[$key]); return $exists; }
    public static function catalog() { $out = array(); foreach (self::definitions() as $key => $definition) { if (self::key($key) && is_array($definition) && is_string($definition['title'] ?? null) && is_array($definition['fields'] ?? null)) { $out[$key] = array('title' => $definition['title'], 'managed' => !isset(self::$registered[$key])); } } return $out; }
    public static function contains($fields, $depth = 0) { if ($depth > 8) { return true; } foreach (is_array($fields) ? $fields : array() as $field) { if (!is_array($field)) { continue; } if (array_key_exists('clone', $field) || (isset($field['sub_fields']) && self::contains($field['sub_fields'], $depth + 1))) { return true; } foreach (is_array($field['layouts'] ?? null) ? $field['layouts'] : array() as $layout) { if (is_array($layout) && self::contains($layout['sub_fields'] ?? null, $depth + 1)) { return true; } } } return false; }
    public static function expand($fields) { if (!self::bounded($fields)) { return self::error('Reusable definitions exceed the 1 MiB/depth budget.'); } $budget = 2000; $result = self::walk($fields, array(), $budget, 0); return is_wp_error($result) || self::bounded($result) ? $result : self::error('Expanded schema exceeds 1 MiB.'); }
    private static function walk($fields, $stack, &$budget, $depth) {
        if (!is_array($fields) || array_values($fields) !== $fields || count($fields) > 200 || $depth > 8) { return self::error('Expansion requires bounded lists and at most eight composition levels.'); } $out = array();
        foreach ($fields as $field) {
            if (--$budget < 0 || !is_array($field)) { return self::error('Reusable expansion exceeds its 2,000-node budget or has malformed fields.'); }
            if (array_key_exists('clone', $field)) {
                if (array_diff(array_keys($field), array('clone', 'prefix')) || !self::key($field['clone']) || !is_string($field['prefix'] ?? null) || !preg_match('/^(?:[a-z][a-z0-9_]{0,39})?$/D', $field['prefix']) || isset($stack[$field['clone']])) { return self::error('Clone references need a valid key/prefix and must not recurse.'); }
                $definition = self::definitions()[$field['clone']] ?? null; if (!is_array($definition) || !is_array($definition['fields'] ?? null)) { return self::error('Reusable definition is unavailable.'); }
                $next = $stack; $next[$field['clone']] = true; $copy = self::walk($definition['fields'], $next, $budget, $depth + 1); if (is_wp_error($copy)) { return $copy; } $copy = self::instance($copy, $field['clone'] . '/' . $field['prefix'], $field['prefix']); if (is_wp_error($copy)) { return $copy; } $out = array_merge($out, $copy);
            } else {
                if (isset($field['sub_fields'])) { $children = self::walk($field['sub_fields'], $stack, $budget, $depth + 1); if (is_wp_error($children)) { return $children; } $field['sub_fields'] = $children; }
                if (isset($field['layouts'])) { if (!is_array($field['layouts']) || count($field['layouts']) > 10) { return self::error('Invalid Flexible layouts in reusable definition.'); } foreach ($field['layouts'] as &$layout) { if (!is_array($layout)) { return self::error('Invalid reusable layout.'); } $children = self::walk($layout['sub_fields'] ?? null, $stack, $budget, $depth + 1); if (is_wp_error($children)) { return $children; } $layout['sub_fields'] = $children; } unset($layout); }
                $out[] = $field;
            }
            if (count($out) > 200) { return self::error('Expanded field list exceeds 200; containers retain their existing smaller limits.'); }
        } return $out;
    }
    private static function condition($node, $map) {
        if (!$node) { return $node; } if (!is_array($node)) { return self::error('Malformed reusable condition.'); }
        if (isset($node['rules'])) { if (!is_array($node['rules'])) { return self::error('Malformed reusable condition rules.'); } foreach ($node['rules'] as &$child) { $child = self::condition($child, $map); if (is_wp_error($child)) { return $child; } } unset($child); return $node; }
        if (!is_string($node['field'] ?? null) || !isset($map[sanitize_key($node['field'])])) { return self::error('Reusable conditions must reference siblings inside the same definition.'); } $node['field'] = $map[sanitize_key($node['field'])]; return $node;
    }
    private static function instance($fields, $scope, $prefix) {
        $map = array(); $names = array(); foreach ($fields as $field) { if (!is_string($field['name'] ?? null) || !preg_match('/^[a-z][a-z0-9_]*$/D', $field['name']) || strlen($prefix . $field['name']) > 80 || isset($names[$field['name']]) || (isset($field['key']) && !is_string($field['key']))) { return self::error('Reusable fields need unique valid names (prefixed maximum 80 bytes) and string keys.'); } $names[$field['name']] = true; $old = sanitize_key($field['key'] ?? ('field_' . $field['name'])); if ($old === '' || isset($map[$old])) { return self::error('Duplicate reusable field key.'); } $map[$old] = 'field_' . substr(hash('sha256', $scope . '/' . $old), 0, 24); }
        foreach ($fields as &$field) { $old = sanitize_key($field['key'] ?? ('field_' . $field['name'])); $field['key'] = $map[$old]; $field['name'] = $prefix . $field['name']; if (isset($field['condition'])) { $field['condition'] = self::condition($field['condition'], $map); if (is_wp_error($field['condition'])) { return $field['condition']; } }
            if (isset($field['sub_fields'])) { $field['sub_fields'] = self::instance($field['sub_fields'], $scope . '/' . $old, ''); if (is_wp_error($field['sub_fields'])) { return $field['sub_fields']; } }
            foreach ($field['layouts'] ?? array() as $index => $layout) { if (!is_string($layout['name'] ?? null)) { return self::error('Reusable layout names must be strings.'); } $field['layouts'][$index]['key'] = 'layout_' . substr(hash('sha256', $scope . '/' . $old . '/' . $layout['name']), 0, 24); $field['layouts'][$index]['sub_fields'] = self::instance($layout['sub_fields'], $scope . '/' . $old . '/' . $layout['name'], ''); if (is_wp_error($field['layouts'][$index]['sub_fields'])) { return $field['layouts'][$index]['sub_fields']; } }
        } unset($field); return $fields;
    }
    public static function boot() { add_action('wp_ajax_alify_cf_library', array(__CLASS__, 'ajax')); }
    public static function controls() {
        echo '<fieldset class="alify-library"><legend><strong>Reusable field definitions (snapshots)</strong></legend><label>Definition <select id="alify-library-choice"><option value="">Choose definition</option>'; foreach (self::catalog() as $key => $definition) { echo '<option value="' . esc_attr($key) . '">' . esc_html($definition['title'] . ' (' . $key . ')') . '</option>'; } echo '</select></label> <label>Field-name prefix <input id="alify-library-prefix" placeholder="home_" maxlength="40"></label> <button type="button" class="button" id="alify-library-insert">Insert reusable fields</button> <button type="button" class="button" id="alify-library-delete">Delete saved definition</button><br><label>New definition key <input id="alify-library-key" maxlength="50"></label> <label>Definition title <input id="alify-library-title" maxlength="200"></label> <button type="button" class="button" id="alify-library-save">Save current fields as reusable</button><p id="alify-library-status" role="status">Copies are independent. Later library changes/deletion never alter saved groups. Existing limits, validation and exposure settings apply.</p></fieldset>';
    }
    public static function request($operation, $key, $prefix, $title, $raw, $location, $group_id) {
        if (!current_user_can('manage_options') || !is_int($group_id) || $group_id <= 0 || get_post_type($group_id) !== Alify_CF::GROUP || !current_user_can('edit_post', $group_id) || !self::key($key) || !in_array($operation, array('insert', 'save', 'delete'), true)) { return self::error('Authorized field-group editor and valid library operation required.'); }
        if ($operation === 'delete') { return self::store(function($saved) use ($key) { if (isset(self::$registered[$key]) || !isset($saved[$key])) { return self::error('Only saved administrator definitions can be deleted here.'); } unset($saved[$key]); return $saved; }); }
        if (!is_array($raw)) { return self::error('Invalid editor schema.'); } $errors = array(); $fields = Alify_CF::validate_schema($raw, $location, $group_id, $errors); if ($errors) { return self::error(implode(' ', $errors)); }
        if ($operation === 'insert') { $added = self::expand(array(array('clone' => $key, 'prefix' => $prefix))); if (is_wp_error($added)) { return $added; } $combined = Alify_CF::validate_schema(array_merge($fields, $added), $location, $group_id, $errors); return $errors ? self::error(implode(' ', $errors)) : array('fields' => $combined); }
        if (!$fields || !is_string($title) || trim(sanitize_text_field($title)) === '' || strlen($title) > 200) { return self::error('Save needs a nonempty field list and title.'); }
        return self::store(function($saved) use ($key, $title, $fields) { if (isset(self::definitions()[$key]) || count($saved) >= 50) { return self::error('Definition key already exists or the 50-definition managed limit is reached.'); } $saved[$key] = array('title' => sanitize_text_field($title), 'fields' => $fields); return $saved; });
    }
    private static function store($change) {
        $lock = wp_generate_uuid4(); if (!add_option('alify_cf_library_lock', $lock, '', false)) { return self::error('Library is busy; interrupted locks require verified administrator recovery.'); }
        try { wp_cache_delete(self::OPTION, 'options'); $next = $change(self::saved()); if (is_wp_error($next)) { return $next; } if (!self::bounded($next)) { return self::error('Managed definitions exceed 1 MiB.'); } update_option(self::OPTION, $next, false); return get_option(self::OPTION, array()) === $next ? array('catalog' => self::catalog()) : self::error('Library storage verification failed.'); } finally { if (get_option('alify_cf_library_lock') === $lock) { delete_option('alify_cf_library_lock'); } }
    }
    public static function ajax() {
        if (!current_user_can('manage_options')) { wp_send_json_error(array('message' => 'Administrator permission required.'), 403); } check_ajax_referer('alify_cf_library', 'nonce'); $raw = wp_unslash($_POST); if (!is_string($raw['schema'] ?? null) || strlen($raw['schema']) > 1048576 || !is_string($raw['group_id'] ?? null) || !preg_match('/^[1-9][0-9]*$/D', $raw['group_id'])) { wp_send_json_error(array('message' => 'Invalid bounded library request.'), 400); }
        $fields = json_decode($raw['schema'], true, 24); $result = self::request($raw['operation'] ?? null, $raw['key'] ?? null, $raw['prefix'] ?? '', $raw['title'] ?? '', $fields, $raw['location'] ?? null, (int) $raw['group_id']); if (is_wp_error($result)) { wp_send_json_error(array('message' => $result->get_error_message()), 400); } wp_send_json_success($result);
    }
}
