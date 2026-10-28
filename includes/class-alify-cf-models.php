<?php
if (!defined('ABSPATH')) { exit; }

/** Small, portable, capability-checked content model registry. Never deletes content. */
final class Alify_CF_Models {
    public static function boot() {
        add_action('init', array(__CLASS__, 'register'), 5);
        add_action('admin_menu', function() { add_submenu_page('edit.php?post_type=' . Alify_CF::GROUP, 'Content Models', 'Content Models', 'manage_options', 'alify-models', array(__CLASS__, 'render')); });
        add_action('admin_post_alify_models', array(__CLASS__, 'handle'));
    }
    public static function data() { $data = get_option('alify_cf_models', array()); return is_array($data) ? array_replace(array('options' => array(), 'post_types' => array(), 'taxonomies' => array()), $data) : array('options' => array(), 'post_types' => array(), 'taxonomies' => array()); }
    public static function register() {
        $data = self::data();
        foreach ($data['options'] as $slug => $model) { Alify_CF_Options::register($slug, $model['label']); }
        foreach ($data['post_types'] as $slug => $model) { if (!post_type_exists($slug)) { register_post_type($slug, self::post_args($model)); } }
        foreach ($data['taxonomies'] as $slug => $model) { if (!taxonomy_exists($slug)) { register_taxonomy($slug, $model['post_types'], self::tax_args($model)); } }
    }
    private static function post_args($model) { return array('label' => $model['label'], 'public' => $model['public'], 'show_ui' => true, 'show_in_rest' => $model['rest'], 'hierarchical' => $model['hierarchical'], 'has_archive' => $model['archive'], 'map_meta_cap' => true, 'supports' => array('title', 'editor', 'thumbnail', 'revisions')); }
    private static function tax_args($model) { return array('label' => $model['label'], 'public' => $model['public'], 'show_ui' => true, 'show_in_rest' => $model['rest'], 'hierarchical' => $model['hierarchical']); }
    private static function normalize($kind, $slug, $raw, $existing, $planned_types = array()) {
        if (!in_array($kind, array('options', 'post_types', 'taxonomies'), true) || !is_string($slug) || !preg_match('/^alify_[a-z][a-z0-9_]{0,12}$/D', $slug) || !is_array($raw) || !is_string($raw['label'] ?? null) || trim($raw['label']) === '' || strlen($raw['label']) > 100) { return new WP_Error('alify_model', 'Use an alify_ prefixed identifier of at most 19 characters and a label of at most 100 bytes.'); }
        if (!isset($existing[$kind][$slug]) && (($kind === 'options' && isset(Alify_CF_Options::pages()[$slug])) || ($kind === 'post_types' && post_type_exists($slug)) || ($kind === 'taxonomies' && taxonomy_exists($slug)))) { return new WP_Error('alify_model_collision', 'Identifier is already registered by another source.'); }
        $out = array('label' => sanitize_text_field($raw['label']));
        if ($kind === 'options') { return $out; }
        foreach (array('public', 'rest', 'hierarchical', 'archive') as $key) { if ($kind === 'taxonomies' && $key === 'archive') { continue; } $value = $raw[$key] ?? false; if (!in_array($value, array(true, false, 0, 1, '0', '1'), true)) { return new WP_Error('alify_model', 'Model flags must be boolean.'); } $out[$key] = (bool) $value; }
        if ($kind === 'taxonomies') {
            $types = $raw['post_types'] ?? array(); if (is_string($types)) { $types = preg_split('/\r?\n/', trim($types)); }
            if (!is_array($types) || !$types || array_values($types) !== $types || count($types) > 20) { return new WP_Error('alify_model', 'Taxonomies require 1–20 target post types.'); }
            $out['post_types'] = array(); foreach ($types as $type) { if (!is_string($type) || !preg_match('/^[a-z][a-z0-9_]{0,19}$/D', trim($type)) || (!post_type_exists(trim($type)) && !in_array(trim($type), $planned_types, true)) || in_array(trim($type), array('attachment', 'revision', Alify_CF::GROUP), true)) { return new WP_Error('alify_model', 'Invalid taxonomy target type.'); } $out['post_types'][] = trim($type); } $out['post_types'] = array_values(array_unique($out['post_types']));
        }
        return $out;
    }
    public static function save($kind, $slug, $raw) {
        if (!current_user_can('manage_options')) { return new WP_Error('alify_forbidden', 'Administrator permission required.'); }
        $data = self::data(); $model = self::normalize($kind, $slug, $raw, $data); if (is_wp_error($model)) { return $model; }
        if (count($data[$kind]) >= 30 && !isset($data[$kind][$slug])) { return new WP_Error('alify_model', 'At most 30 models of each kind are supported.'); }
        $data[$kind][$slug] = $model; update_option('alify_cf_models', $data, false); return self::data() === $data ? true : new WP_Error('alify_storage', 'Model storage failed.');
    }
    public static function remove($kind, $slug) {
        if (!current_user_can('manage_options')) { return new WP_Error('alify_forbidden', 'Administrator permission required.'); }
        $data = self::data(); if (!is_string($kind) || !isset($data[$kind]) || !is_string($slug) || !isset($data[$kind][$slug])) { return new WP_Error('alify_model', 'Unknown managed model.'); }
        if ($kind === 'post_types' && get_posts(array('post_type' => $slug, 'post_status' => array('publish', 'private', 'draft', 'pending', 'future', 'trash'), 'numberposts' => 1))) { return new WP_Error('alify_dependency', 'Model has content. Migrate it before unregistering.'); }
        if ($kind === 'taxonomies' && get_terms(array('taxonomy' => $slug, 'hide_empty' => false, 'number' => 1))) { return new WP_Error('alify_dependency', 'Taxonomy has terms. Migrate them first.'); }
        if ($kind === 'options' && Alify_CF_Options::data($slug)) { return new WP_Error('alify_dependency', 'Options page has saved values. Migrate them first.'); }
        foreach ($data['taxonomies'] as $taxonomy) { if ($kind === 'post_types' && in_array($slug, $taxonomy['post_types'], true)) { return new WP_Error('alify_dependency', 'A managed taxonomy depends on this type.'); } }
        $groups = get_posts(array('post_type' => Alify_CF::GROUP, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => -1));
        foreach ($groups as $group) { if (get_post_meta($group->ID, Alify_CF::LOCATION, true) === ($kind === 'options' ? 'options:' . $slug : $slug) || self::references(Alify_CF::schema($group->ID), $slug)) { return new WP_Error('alify_dependency', 'A field-group configuration depends on this model.'); } }
        foreach (Alify_CF_API::groups() as $group) { if ($group['location'] === ($kind === 'options' ? 'options:' . $slug : $slug) || self::references($group['fields'], $slug)) { return new WP_Error('alify_dependency', 'A PHP group depends on this model.'); } }
        unset($data[$kind][$slug]); update_option('alify_cf_models', $data, false); return true;
    }
    private static function references($fields, $slug) { foreach ($fields as $field) { foreach (array('post_types', 'taxonomies') as $key) { if (in_array($slug, $field[$key] ?? array(), true)) { return true; } } if (self::references($field['sub_fields'] ?? array(), $slug)) { return true; } foreach ($field['layouts'] ?? array() as $layout) { if (self::references($layout['sub_fields'], $slug)) { return true; } } } return false; }
    public static function export_json() { return current_user_can('manage_options') ? wp_json_encode(array('format' => 'alify-models', 'version' => 1, 'models' => self::data()), JSON_PRETTY_PRINT) : new WP_Error('alify_forbidden', 'Administrator permission required.'); }
    public static function import_json($json) {
        if (!current_user_can('manage_options')) { return new WP_Error('alify_forbidden', 'Administrator permission required.'); }
        $raw = is_string($json) && strlen($json) <= 262144 ? json_decode($json, true, 16) : null;
        if (!is_array($raw) || ($raw['format'] ?? '') !== 'alify-models' || ($raw['version'] ?? null) !== 1 || !is_array($raw['models'] ?? null) || array_diff(array_keys($raw['models']), array('options', 'post_types', 'taxonomies'))) { return new WP_Error('alify_model', 'Invalid models JSON (maximum 256 KiB).'); }
        $data = self::data(); $next = $data; $planned_raw = $raw['models']['post_types'] ?? array(); if (!is_array($planned_raw)) { return new WP_Error('alify_model', 'Invalid post type definitions.'); } $planned = array_keys($planned_raw);
        foreach (array('options', 'post_types', 'taxonomies') as $kind) { if (!is_array($raw['models'][$kind] ?? array()) || count($raw['models'][$kind] ?? array()) > 30) { return new WP_Error('alify_model', 'Invalid model list.'); } foreach ($raw['models'][$kind] ?? array() as $slug => $model) { if (isset($data[$kind][$slug])) { return new WP_Error('alify_model_collision', 'Imports create new models only; a model already exists.'); } $normalized = self::normalize($kind, $slug, $model, $data, $planned); if (is_wp_error($normalized)) { return $normalized; } $next[$kind][$slug] = $normalized; } if (count($next[$kind]) > 30) { return new WP_Error('alify_model', 'Model count limit exceeded.'); } }
        update_option('alify_cf_models', $next, false); return self::data() === $next ? true : new WP_Error('alify_storage', 'Model storage failed.');
    }
    public static function handle() {
        if (!current_user_can('manage_options')) { wp_die('Administrator permission required.', '', array('response' => 403)); }
        check_admin_referer('alify_models'); $request = wp_unslash($_POST);
        $result = ($request['operation'] ?? '') === 'import' ? self::import_json($request['json'] ?? '') : (($request['operation'] ?? '') === 'remove' ? self::remove($request['kind'] ?? '', $request['slug'] ?? '') : self::save($request['kind'] ?? '', $request['slug'] ?? '', $request));
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', array('response' => 400)); }
        wp_safe_redirect(admin_url('edit.php?post_type=' . Alify_CF::GROUP . '&page=alify-models')); exit;
    }
    public static function render() {
        if (!current_user_can('manage_options')) { wp_die('Administrator permission required.'); }
        echo '<div class="wrap"><h1>Content Models</h1><p>Create/update managed options pages, post types and taxonomies. Identifiers must begin alify_ (Alify Custom Field). Changes take effect next request; URL rewrite rules may require resaving Permalinks. Unregistering never deletes content; dependencies block removal.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="alify_models">'; wp_nonce_field('alify_models');
        echo '<p><label>Kind <select name="kind"><option value="options">Options page</option><option value="post_types">Post type</option><option value="taxonomies">Taxonomy</option></select></label></p><p><label>Identifier <input name="slug" pattern="alify_[a-z][a-z0-9_]{0,12}" required></label></p><p><label>Label <input name="label" maxlength="100" required></label></p>';
        foreach (array('public', 'rest', 'hierarchical', 'archive') as $flag) { echo '<label><input type="checkbox" name="' . esc_attr($flag) . '" value="1"> ' . esc_html($flag) . '</label> '; }
        echo '<p><label>Taxonomy target post types (one per line)<br><textarea name="post_types">post</textarea></label></p><button class="button button-primary">Save model</button></form><h2>Registered definitions</h2>';
        foreach (self::data() as $kind => $models) { foreach ($models as $slug => $model) { echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="alify_models"><input type="hidden" name="operation" value="remove"><input type="hidden" name="kind" value="' . esc_attr($kind) . '"><input type="hidden" name="slug" value="' . esc_attr($slug) . '">'; wp_nonce_field('alify_models'); echo '<p>' . esc_html($kind . ': ' . $slug . ' — ' . $model['label']) . ' <button class="button">Unregister (keeps data)</button></p></form>'; } }
        echo '<h2>Portable JSON</h2><textarea readonly rows="12" class="large-text code">' . esc_textarea(self::export_json()) . '</textarea><p>Copy this schema-only configuration into source control. Import creates new identifiers only.</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="alify_models"><input type="hidden" name="operation" value="import">'; wp_nonce_field('alify_models'); echo '<textarea name="json" rows="8" class="large-text code" required></textarea><button class="button">Import new models</button></form></div>';
    }
}
