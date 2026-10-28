<?php
if (!defined('ABSPATH')) { exit; }

/** Private revision snapshots; raw getters remain parent-post getters. */
final class Alify_CF_Revisions {
    private static $dirty = array();
    private static $capturing = false;
    public static function boot() {
        foreach (array('added_post_meta', 'updated_post_meta', 'deleted_post_meta') as $hook) { add_action($hook, function($meta_id, $id, $key) { if (is_string($key) && strpos($key, '_alify_cf_') === 0 && !wp_is_post_revision($id)) { self::$dirty[$id] = true; } }, 100, 3); }
        add_action('shutdown', function() { foreach (array_keys(self::$dirty) as $id) { self::capture($id); } });
        add_action('_wp_put_post_revision', function($revision, $parent = null) { self::snapshot($revision, $parent ?? wp_is_post_revision($revision)); }, 20, 2);
        add_action('wp_restore_post_revision', function($id, $revision) { $result = self::restore($id, $revision); if (is_wp_error($result)) { set_transient('alify_cf_revision_error_' . get_current_user_id(), $result->get_error_message(), 120); } }, 20, 2);
        add_action('admin_notices', function() { $key = 'alify_cf_revision_error_' . get_current_user_id(); $error = get_transient($key); if ($error) { delete_transient($key); echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>'; } });
        add_filter('rest_request_after_callbacks', array(__CLASS__, 'autosave'), 30, 3);
    }
    private static function fields($post) { $fields = array(); foreach (Alify_CF::groups($post->post_type, $post->ID) as $group) { foreach (Alify_CF::schema($group->ID) as $field) { if (!Alify_CF_Editor_Fields::presentation($field['type']) && !Alify_CF_Password::contains($field) && !Alify_CF_API::contains_custom($field)) { $fields[$field['name']] = $field; } } } return $fields; }
    private static function data($post, $updates = null) {
        $out = array('version' => 1, 'fields' => array()); foreach (self::fields($post) as $name => $field) { $value = is_array($updates) && array_key_exists($name, $updates) ? $updates[$name] : alify_get_field($name, $post->ID); if ($value !== null) { $out['fields'][$name] = array('schema' => hash('sha256', wp_json_encode($field)), 'value' => $value); } } return $out;
    }
    public static function snapshot($revision, $parent) {
        $post = get_post($parent); $saved = get_post($revision); if (!$post || !$saved || $saved->post_type !== 'revision' || (int) $saved->post_parent !== (int) $parent || !current_user_can('edit_post', $parent)) { return false; }
        update_metadata('post', $revision, '_alify_cf_revision_data', wp_slash(self::data($post))); return true;
    }
    public static function capture($id) {
        $post = get_post($id); if (self::$capturing || !$post || $post->post_type === Alify_CF::GROUP || !current_user_can('edit_post', $id) || !wp_revisions_enabled($post) || !self::fields($post) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) { return null; }
        $revisions = wp_get_post_revisions($id, array('posts_per_page' => 1, 'check_enabled' => false)); $latest = $revisions ? reset($revisions) : null;
        if ($latest && get_post_meta($latest->ID, '_alify_cf_revision_data', true) === self::data($post) && $latest->post_content === $post->post_content && $latest->post_title === $post->post_title && $latest->post_excerpt === $post->post_excerpt) { return $latest->ID; }
        $force = function($check, $last, $current) use ($id) { return (int) $current->ID === (int) $id ? false : $check; }; self::$capturing = true; add_filter('wp_save_post_revision_check_for_changes', $force, 10, 3);
        try { $revision = wp_save_post_revision($id); if (is_int($revision) && $revision > 0) { self::snapshot($revision, $id); } return $revision; } finally { remove_filter('wp_save_post_revision_check_for_changes', $force, 10); self::$capturing = false; }
    }
    public static function get($name, $revision_id) {
        $revision = get_post($revision_id); if (!$revision || $revision->post_type !== 'revision' || !current_user_can('edit_post', $revision->post_parent) || !current_user_can('read_post', $revision_id)) { return null; }
        $data = get_post_meta($revision_id, '_alify_cf_revision_data', true); return is_string($name) && is_array($data) && ($data['version'] ?? null) === 1 ? ($data['fields'][$name]['value'] ?? null) : null;
    }
    public static function restore($id, $revision_id) {
        $revision = get_post($revision_id); $post = get_post($id);
        if (!$post || !$revision || $revision->post_type !== 'revision' || (int) $revision->post_parent !== (int) $id || !current_user_can('edit_post', $id) || !current_user_can('read_post', $revision_id)) { return new WP_Error('alify_forbidden', 'Revision restore not permitted.'); }
        $data = get_post_meta($revision_id, '_alify_cf_revision_data', true); if (!is_array($data) || ($data['version'] ?? null) !== 1) { return new WP_Error('alify_revision', 'No Alify field snapshot; current fields retained.'); }
        $fields = self::fields($post); $values = array();
        foreach ($data['fields'] as $name => $saved) { if (!isset($fields[$name]) || ($saved['schema'] ?? '') !== hash('sha256', wp_json_encode($fields[$name]))) { return new WP_Error('alify_revision_schema', 'Field schema changed; current fields retained.'); } $values[$name] = Alify_CF_Publication::stored_payload($fields[$name], $saved['value']); }
        return $values ? alify_update_fields($values, $id) : true;
    }
    public static function preview($name, $post_id) {
        if (!is_string($name) || !current_user_can('edit_post', $post_id)) { return null; }
        $data = get_transient('alify_cf_autosave_' . get_current_user_id() . '_' . (int) $post_id);
        if (is_array($data) && ($data['version'] ?? null) === 1) { return $data['fields'][$name]['value'] ?? null; }
        $revision = wp_get_post_autosave($post_id, get_current_user_id()); return $revision ? self::get($name, $revision->ID) : null;
    }
    public static function autosave($response, $handler, $request) {
        if (is_wp_error($response) || $request->get_method() !== 'POST' || strpos($request->get_route(), '/autosaves') === false || !($response instanceof WP_REST_Response)) { return $response; }
        $data = $response->get_data(); $saved = isset($data['id']) ? get_post($data['id']) : null; if (!$saved) { return $response; }
        $is_revision = (bool) wp_is_post_autosave($saved->ID); $post = $is_revision ? get_post($saved->post_parent) : $saved;
        if (!$post || $post->post_type === Alify_CF::GROUP || !current_user_can('edit_post', $post->ID)) { return $response; }
        $payload = $request->get_param('alify_fields'); $updates = null;
        if ($payload !== null) {
            if (!is_array($payload) || ($payload['complete'] ?? '') !== '1' || !is_array($payload['groups'] ?? null)) { $response->header('X-Alify-Autosave-Fields', 'rejected'); return $response; }
            $result = Alify_CF_Publication::assess($post->post_type, $post->ID, $payload['groups']); if ($result['errors']) { $response->header('X-Alify-Autosave-Fields', 'rejected'); return $response; } $updates = $result['updates'];
        }
        $snapshot = self::data($post, $updates); if (strlen(wp_json_encode($snapshot)) > 1048576) { $response->header('X-Alify-Autosave-Fields', 'rejected'); return $response; }
        if ($is_revision) { update_metadata('post', $saved->ID, '_alify_cf_revision_data', wp_slash($snapshot)); }
        set_transient('alify_cf_autosave_' . get_current_user_id() . '_' . $post->ID, $snapshot, HOUR_IN_SECONDS);
        return $response;
    }
}
