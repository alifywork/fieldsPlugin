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
