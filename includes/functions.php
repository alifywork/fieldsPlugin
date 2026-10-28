<?php
if (!defined('ABSPATH')) { exit; }

/** Retrieve a field value; escape it for its output context before displaying. */
function alify_get_field($name, $post_id = 0) {
    $post_id = $post_id ? absint($post_id) : get_the_ID();
    if (!$post_id || !is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) { return null; }
    if (!metadata_exists('post', $post_id, '_alify_cf_' . $name)) { return null; }
    return get_post_meta($post_id, '_alify_cf_' . $name, true);
}

/** Output a scalar field as escaped plain text (never raw HTML). */
function alify_the_field($name, $post_id = 0) {
    $value = alify_get_field($name, $post_id);
    if (is_scalar($value)) { echo esc_html((string) $value); }
}

/** Return a repeater's stored rows, or an empty array before its first save. */
function alify_get_rows($name, $post_id = 0) {
    $rows = alify_get_field($name, $post_id);
    if (!is_array($rows)) { return array(); }
    return array_values(array_filter($rows, 'is_array'));
}

/** Register an additional options page on init, before admin_menu runs. */
function alify_add_options_page($slug, $title) {
    return Alify_CF_Options::register($slug, $title);
}

/** Retrieve a site-scoped option value. Escape for its eventual output context. */
function alify_get_option($name, $page = 'site') {
    if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) { return null; }
    $data = Alify_CF_Options::data($page);
    return array_key_exists($name, $data) ? $data[$name] : null;
}

/** Output scalar option values as escaped plain text; arrays are not printed. */
function alify_the_option($name, $page = 'site') {
    $value = alify_get_option($name, $page);
    if (is_scalar($value)) { echo esc_html((string) $value); }
}

/** Return a Group field's saved associative array, or an empty array before save. */
function alify_get_group($name, $post_id = 0) {
    $value = alify_get_field($name, $post_id);
    return is_array($value) ? $value : array();
}

/** Ordered Flexible Content rows: each row has layout and fields. */
function alify_get_layouts($name, $post_id = 0) {
    $rows = alify_get_field($name, $post_id);
    return is_array($rows) ? array_values(array_filter($rows, function($row) { return is_array($row) && is_string($row['layout'] ?? null) && is_array($row['fields'] ?? null); })) : array();
}
