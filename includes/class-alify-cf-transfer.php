<?php
if (!defined('ABSPATH')) { exit; }

/** Versioned, configuration-only, single-group JSON transfer. Never updates existing groups. */
final class Alify_CF_Transfer {
    const MAX_BYTES = 1048576;

    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_alify_cf_export', array(__CLASS__, 'handle_export'));
        add_action('admin_post_alify_cf_import', array(__CLASS__, 'handle_import'));
    }
    private static function authorize($nonce, $action) {
        if (!current_user_can('manage_options')) { return new WP_Error('alify_transfer_forbidden', 'Administrator permission is required.'); }
        if (!is_string($nonce) || !wp_verify_nonce($nonce, $action)) { return new WP_Error('alify_transfer_nonce', 'Security check failed. Reload the page.'); }
        return true;
    }
    public static function export_json($id, $nonce) {
        $auth = self::authorize($nonce, 'alify_cf_export');
        if (is_wp_error($auth)) { return $auth; }
        $post = get_post($id);
        if (!$post || $post->post_type !== Alify_CF::GROUP || in_array($post->post_status, array('trash', 'auto-draft'), true)) { return new WP_Error('alify_transfer_group', 'Choose an existing, non-trashed field group.'); }
        $location = get_post_meta($id, Alify_CF::LOCATION, true);
        $errors = array(); $fields = Alify_CF::validate_schema(Alify_CF::schema($id), $location, $id, $errors);
        if ($errors) { return new WP_Error('alify_transfer_schema', implode(' ', $errors)); }
        $json = wp_json_encode(array('format' => 'alify-custom-field', 'version' => self::version($fields), 'group' => array('title' => $post->post_title, 'location' => $location, 'fields' => self::portable($fields))), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || strlen($json) > self::MAX_BYTES) { return new WP_Error('alify_transfer_size', 'Export exceeds the 1 MiB transfer limit.'); }
        return $json;
    }
    /** Older importers must reject row-bound schemas rather than silently drop their limits. */
    private static function version($fields) {
        $version = 1;
        foreach ($fields as $field) {
            if (Alify_CF_Choice::supported($field['type']) && (array_key_exists('layout', $field) || array_key_exists('multiple', $field) || array_key_exists('searchable', $field) || array_key_exists('return_format', $field) || ($field['type'] === 'checkbox' && !empty($field['default'])))) { $version = 4; }
            if (Alify_CF_Temporal_Format::formats($field['type']) && (array_key_exists('display_format', $field) || array_key_exists('return_format', $field))) { $version = max($version, 3); }
            if ($field['type'] === 'repeater' && (array_key_exists('min_rows', $field) || array_key_exists('max_rows', $field))) { $version = max($version, 2); }
            if (isset($field['sub_fields'])) { $version = max($version, self::version($field['sub_fields'])); }
            foreach ($field['layouts'] ?? array() as $layout) { $version = max($version, self::version($layout['sub_fields'])); }
        }
        return $version;
    }
    private static function portable($fields) {
        foreach ($fields as &$field) {
            if (($field['type'] ?? '') === 'flexible' && is_array($field['layouts'] ?? null)) { foreach ($field['layouts'] as &$layout) { if (is_array($layout) && is_array($layout['sub_fields'] ?? null)) { $layout['sub_fields'] = self::portable_input($layout['sub_fields']); } } unset($layout); }
            if (in_array($field['type'], array('image', 'file', 'post_object', 'password'), true)) { $field['default'] = ''; }
            if (in_array($field['type'], array('repeater', 'group'), true)) { $field['sub_fields'] = self::portable($field['sub_fields']); }
        }
        unset($field); return $fields;
    }
    private static function portable_input($fields) {
        foreach ($fields as &$field) {
            if (!is_array($field)) { continue; }
            if (($field['type'] ?? '') === 'flexible' && is_array($field['layouts'] ?? null)) { foreach ($field['layouts'] as &$layout) { if (is_array($layout) && is_array($layout['sub_fields'] ?? null)) { $layout['sub_fields'] = self::portable_input($layout['sub_fields']); } } unset($layout); }
            if (in_array($field['type'] ?? '', array('image', 'file', 'post_object', 'password'), true)) { $field['default'] = ''; }
            if (in_array(($field['type'] ?? ''), array('repeater', 'group'), true) && is_array($field['sub_fields'] ?? null)) { $field['sub_fields'] = self::portable_input($field['sub_fields']); }
        }
        unset($field); return $fields;
    }
    public static function import_json($json, $nonce) {
        $auth = self::authorize($nonce, 'alify_cf_import');
        if (is_wp_error($auth)) { return $auth; }
        if (!is_string($json) || strlen($json) > self::MAX_BYTES || trim($json) === '') { return new WP_Error('alify_transfer_size', 'Provide JSON no larger than 1 MiB.'); }
        $object = json_decode($json, false, 32);
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($object) || ($object->format ?? null) !== 'alify-custom-field' || !in_array(($object->version ?? null), array(1, 2, 3, 4), true) || !isset($object->group) || !is_object($object->group) || !isset($object->group->fields) || !is_array($object->group->fields)) {
            return new WP_Error('alify_transfer_format', 'Invalid Alify JSON or unsupported format version.');
        }
        $group = json_decode($json, true, 32)['group'];
        if (!is_string($group['title'] ?? null) || !is_string($group['location'] ?? null) || trim(sanitize_text_field($group['title'])) === '') { return new WP_Error('alify_transfer_group', 'Group title and registered location are required.'); }
        $errors = array(); $fields = Alify_CF::validate_schema(self::portable_input($group['fields']), $group['location'], 0, $errors);
        if ($errors) { return new WP_Error('alify_transfer_schema', implode(' ', $errors)); }
        // Do not pass through source IDs, statuses, values, capabilities or arbitrary metadata.
        $id = wp_insert_post(wp_slash(array('post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => sanitize_text_field($group['title']))), true);
        if (is_wp_error($id)) { return $id; }
        update_post_meta($id, Alify_CF::SCHEMA, wp_slash($fields));
        update_post_meta($id, Alify_CF::LOCATION, $group['location']);
        if (Alify_CF::schema($id) !== $fields || get_post_meta($id, Alify_CF::LOCATION, true) !== $group['location']) {
            wp_delete_post($id, true);
            return new WP_Error('alify_transfer_write', 'Import could not be stored. The new group was removed.');
        }
        return $id;
    }
    public static function menu() {
        add_submenu_page('edit.php?post_type=' . Alify_CF::GROUP, 'JSON Import / Export', 'JSON Import / Export', 'manage_options', 'alify-transfer', array(__CLASS__, 'render'));
    }
    public static function render() {
        if (!current_user_can('manage_options')) { wp_die('Administrator permission is required.', '', array('response' => 403)); }
        echo '<div class="wrap"><h1>JSON Import / Export</h1><p>Transfer one field-group configuration. Content values and media files are never included. Image/file defaults are reset because attachment IDs are site-local. Imports create a new draft and never overwrite a group. Review it before publishing.</p>';
        echo '<h2>Export a group</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="alify_cf_export">';
        wp_nonce_field('alify_cf_export', 'alify_transfer_nonce');
        echo '<label for="alify-export-group">Field group </label><select id="alify-export-group" name="group_id" required><option value="">Choose a group</option>';
        foreach (get_posts(array('post_type' => Alify_CF::GROUP, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC')) as $group) {
            echo '<option value="' . esc_attr($group->ID) . '">' . esc_html($group->post_title . ' (' . $group->post_status . ')') . '</option>';
        }
        echo '</select> <button class="button" type="submit">Download JSON</button></form><h2>Import a group</h2><p>Upload a JSON file or paste JSON (not both). Maximum 1 MiB. Name conflicts and invalid/unregistered locations are rejected without changing existing data.</p><form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="alify_cf_import">';
        wp_nonce_field('alify_cf_import', 'alify_transfer_nonce');
        echo '<p><label for="alify-json-file">JSON file</label> <input type="file" id="alify-json-file" name="alify_json_file" accept=".json,application/json"></p><p><label for="alify-json">Or paste JSON</label></p><textarea id="alify-json" name="alify_json" rows="14" class="large-text code" spellcheck="false"></textarea><p><button class="button button-primary" type="submit">Import as draft</button></p></form></div>';
    }
    private static function fail($error) { wp_die(esc_html($error->get_error_message()), 'JSON transfer failed', array('response' => in_array($error->get_error_code(), array('alify_transfer_forbidden', 'alify_transfer_nonce'), true) ? 403 : 400, 'back_link' => true)); }
    public static function handle_export() {
        $request = wp_unslash($_POST);
        $id = isset($request['group_id']) && is_scalar($request['group_id']) ? absint($request['group_id']) : 0;
        $json = self::export_json($id, $request['alify_transfer_nonce'] ?? '');
        if (is_wp_error($json)) { self::fail($json); return; }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="alify-field-group-' . $id . '.json"');
        echo $json; exit;
    }
    public static function handle_import() {
        $request = wp_unslash($_POST);
        $auth = self::authorize($request['alify_transfer_nonce'] ?? '', 'alify_cf_import');
        if (is_wp_error($auth)) { self::fail($auth); return; }
        $json = $request['alify_json'] ?? '';
        if (!is_string($json)) { self::fail(new WP_Error('alify_transfer_format', 'Invalid JSON input.')); return; }
        $file = $_FILES['alify_json_file'] ?? null;
        if ($file && (!is_array($file) || !isset($file['error']) || !is_int($file['error']))) { self::fail(new WP_Error('alify_transfer_upload', 'Invalid upload.')); return; }
        if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
            if (trim($json) !== '' || $file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) { self::fail(new WP_Error('alify_transfer_upload', 'Choose one valid uploaded file or pasted JSON.')); return; }
            $json = file_get_contents($file['tmp_name'], false, null, 0, self::MAX_BYTES + 1);
        }
        $id = self::import_json($json, $request['alify_transfer_nonce'] ?? '');
        if (is_wp_error($id)) { self::fail($id); return; }
        wp_safe_redirect(admin_url('post.php?post=' . $id . '&action=edit')); exit;
    }
}
