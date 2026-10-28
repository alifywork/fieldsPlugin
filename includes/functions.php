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

/** Return a validated link record or []; themes still escape URL/title and add rel for new tabs. */
function alify_get_link($name, $post_id = 0) {
    $value = alify_get_field($name, $post_id);
    if (!is_array($value)) { return array(); }
    $value['__complete'] = '1';
    $clean = Alify_CF_Link::clean($value);
    return is_array($clean) ? $clean : array();
}

/** Return a currently viewable referenced post/page, or null; raw ID remains available via alify_get_field. */
function alify_get_post_object($name, $post_id = 0) {
    return Alify_CF_Post_Object::resolve(alify_get_field($name, $post_id));
}

/** Resolve readable Relationship targets in saved order, without rewriting stored IDs. */
function alify_get_relationship($name, $post_id = 0) {
    $ids = alify_get_field($name, $post_id); $posts = array(); $seen = array();
    if (!is_array($ids)) { return $posts; }
    foreach (array_slice($ids, 0, Alify_CF_Relationship::MAX_ITEMS) as $id) {
        $post = Alify_CF_Post_Object::resolve($id);
        if ($post && !isset($seen[$post->ID])) { $posts[] = $post; $seen[$post->ID] = true; }
    }
    return $posts;
}

/** Opt-in return formats; raw getters and all storage/save paths remain unchanged. */
function alify_get_formatted_field($name, $post_id = 0) {
    $post_id = $post_id ? absint($post_id) : get_the_ID();
    $value = alify_get_field($name, $post_id);
    if ($value === null) { return null; }
    $post = get_post($post_id);
    return $post ? Alify_CF_Temporal_Format::location($post->post_type, $name, $value) : $value;
}

/** Apply return formats to a site-scoped option, including basic container subfields. */
function alify_get_formatted_option($name, $page = 'site') {
    if (!is_string($page)) { return null; }
    $value = alify_get_option($name, $page);
    return $value === null ? null : Alify_CF_Temporal_Format::location(Alify_CF_Options::location($page), $name, $value);
}

/** Trusted deployment registration; these APIs do not create editable database copies. */
function alify_register_field_group($definition) { return Alify_CF_API::register_group($definition); }
function alify_register_field($group_key, $field) { return Alify_CF_API::register_field($group_key, $field); }
function alify_register_field_type($type, $callbacks) { return Alify_CF_API::register_type($type, $callbacks); }
/** Capability checked, editor-payload writes. Return true or WP_Error; no implicit bypass. */
function alify_update_fields($values, $post_id) { return Alify_CF_API::write($values, $post_id); }
function alify_update_field($name, $value, $post_id) { return is_string($name) ? Alify_CF_API::write(array($name => $value), $post_id) : new WP_Error('alify_name', 'Invalid field name.'); }
function alify_update_options($values, $page = 'site') { return Alify_CF_API::write($values, $page, true); }
function alify_update_option($name, $value, $page = 'site') { return is_string($name) ? Alify_CF_API::write(array($name => $value), $page, true) : new WP_Error('alify_name', 'Invalid field name.'); }
/** Stored canonical row objects are repacked and whole-container validated; indices are zero-based. */
function alify_update_row($name, $index, $row, $post_id) { return Alify_CF_API::mutate_row($name, $index, $row, $post_id); }
function alify_delete_row($name, $index, $post_id) { return Alify_CF_API::mutate_row($name, $index, null, $post_id, true); }
function alify_each_row($name, $post_id = 0) { foreach (alify_get_rows($name, $post_id) as $index => $row) { yield $index => $row; } }
function alify_get_sub_field($row, $name) { return is_array($row) && is_string($name) ? ($row['fields'][$name] ?? $row[$name] ?? null) : null; }
function alify_export_group_php($group_id) { return Alify_CF_API::export_php($group_id); }

/** Trusted raw native-metadata getter, like alify_get_field; not a public authorization boundary. */
function alify_get_object_field($name, $context) { return Alify_CF_Objects::get($name, $context); }
function alify_update_object_fields($values, $context) { return Alify_CF_Objects::write($values, $context); }

function alify_form_head() { Alify_CF_Forms::prepare(); }
function alify_form($post_id) { return Alify_CF_Forms::render($post_id); }
function alify_get_revision_field($name, $revision_id) { return Alify_CF_Revisions::get($name, $revision_id); }
function alify_restore_field_revision($post_id, $revision_id) { return Alify_CF_Revisions::restore($post_id, $revision_id); }

function alify_register_location_rule($name, $matcher, $validator = null) { return Alify_CF_Location_Rules::register($name, $matcher, $validator); }

/** Explicit rendering only; themes decide privacy/consent before contacting external providers. */
function alify_get_oembed($name, $post_id = 0) { $value = alify_get_field($name, $post_id); return is_string($value) && $value !== '' ? Alify_CF_External::embed($value) : ''; }
function alify_get_google_map_html($name, $post_id = 0) { return Alify_CF_External::map_html(alify_get_field($name, $post_id)); }

function alify_get_widget_field($name, $widget_id) { return Alify_CF_Widgets::get($name, $widget_id); }

function alify_get_autosave_field($name, $post_id) { return Alify_CF_Revisions::preview($name, $post_id); }

/** Trusted dynamic blocks with public scalar attributes; register on init. */
function alify_register_block_type($name, $settings) { return Alify_CF_Blocks::register($name, $settings); }
