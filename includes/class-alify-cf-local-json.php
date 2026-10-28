<?php
if (!defined('ABSPATH')) { exit; }

/** Opt-in, trusted deployment directory. No user-supplied filesystem paths. */
final class Alify_CF_Local_JSON {
    private static $syncing = false;
    public static function boot() {
        add_action('init', array(__CLASS__, 'load'), 60);
        add_action('save_post', array(__CLASS__, 'save'), 40, 2);
        add_action('before_delete_post', function($id, $post) { if ($post->post_type === Alify_CF::GROUP) { $tombstone = clone $post; $tombstone->post_status = 'trash'; self::save($id, $tombstone); } }, 10, 2);
        add_action('admin_menu', function() { add_submenu_page('edit.php?post_type=' . Alify_CF::GROUP, 'Local JSON', 'Local JSON', 'manage_options', 'alify-local-json', array(__CLASS__, 'page')); });
        add_action('admin_post_alify_local_sync', array(__CLASS__, 'handle'));
    }
    public static function directory() {
        if (!defined('ALIFY_CF_JSON_DIR') || !is_string(ALIFY_CF_JSON_DIR) || is_link(ALIFY_CF_JSON_DIR)) { return null; }
        $path = realpath(ALIFY_CF_JSON_DIR); return $path && is_dir($path) ? $path : null;
    }
    public static function files() {
        $directory = self::directory(); if (!$directory) { return array(); } $out = array(); $paths = glob($directory . '/*.json') ?: array(); sort($paths);
        foreach (array_slice($paths, 0, 100) as $path) {
            if (is_link($path) || !is_file($path) || !is_readable($path) || filesize($path) > 1048576) { continue; }
            $json = file_get_contents($path); $value = json_decode($json, true, 32);
            if (!is_array($value) || ($value['format'] ?? '') !== 'alify-local-json' || ($value['version'] ?? null) !== 1 || !is_int($value['schema_version'] ?? null) || $value['schema_version'] < 1 || $value['schema_version'] > 9 || !is_string($value['key'] ?? null) || !preg_match('/^[a-z][a-z0-9_-]{0,79}$/D', $value['key']) || basename($path) !== $value['key'] . '.json' || isset($out[$value['key']]) || !is_array($value['group'] ?? null) || !in_array($value['status'] ?? '', array('publish', 'draft', 'pending', 'private', 'future', 'trash'), true)) { continue; }
            $out[$value['key']] = array('data' => $value, 'hash' => hash('sha256', $json));
        }
        return $out;
    }
    private static function database($key) {
        $posts = get_posts(array('post_type' => Alify_CF::GROUP, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future', 'trash'), 'numberposts' => 2, 'meta_key' => '_alify_cf_local_key', 'meta_value' => $key));
        return count($posts) > 1 ? new WP_Error('alify_collision', 'Multiple database groups have this deployment key.') : ($posts[0] ?? null);
    }
    public static function state($post) { return $post ? hash('sha256', wp_json_encode(array($post->post_title, $post->post_status, get_post_meta($post->ID, Alify_CF::LOCATION, true), get_post_meta($post->ID, '_alify_cf_location_rules', true), Alify_CF::schema($post->ID)))) : 'new'; }
    public static function load() {
        foreach (self::files() as $key => $file) { if (self::database($key) !== null || $file['data']['status'] !== 'publish') { continue; } $definition = $file['data']['group']; $definition['key'] = 'local_' . substr(hash('sha256', $key), 0, 40); alify_register_field_group($definition); }
    }
    public static function save($id, $post) {
        $directory = self::directory();
        if (self::$syncing || !$directory || !is_writable($directory) || $post->post_type !== Alify_CF::GROUP || !current_user_can('manage_options') || wp_is_post_revision($id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !metadata_exists('post', $id, Alify_CF::SCHEMA)) { return; }
        if ($post->post_status === 'trash') { $existing_key = get_post_meta($id, '_alify_cf_local_key', true); $files = self::files(); if (!is_string($existing_key) || !isset($files[$existing_key])) { return; } $export = array('version' => $files[$existing_key]['data']['schema_version'], 'group' => $files[$existing_key]['data']['group']); } else { $json = Alify_CF_Transfer::export_json($id, wp_create_nonce('alify_cf_export')); if (is_wp_error($json)) { return; } $export = json_decode($json, true); }
        $key = get_post_meta($id, '_alify_cf_local_key', true); if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_-]{0,79}$/D', $key)) { $key = 'group_' . wp_generate_uuid4(); update_post_meta($id, '_alify_cf_local_key', $key); }
        $owner = self::database($key); if (is_wp_error($owner) || ($owner && (int) $owner->ID !== (int) $id)) { return; }
        $path = $directory . '/' . $key . '.json'; if (is_link($path)) { return; }
        $data = array('format' => 'alify-local-json', 'version' => 1, 'schema_version' => $export['version'], 'key' => $key, 'status' => $post->post_status, 'group' => $export['group']); $encoded = wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || strlen($encoded) > 1048576) { return; }
        if (is_file($path) && file_get_contents($path) === $encoded) { return; }
        $temporary = $path . '.' . wp_generate_uuid4() . '.tmp';
        if (file_put_contents($temporary, $encoded, LOCK_EX) === strlen($encoded)) { chmod($temporary, 0600); if (!rename($temporary, $path)) { unlink($temporary); } } elseif (is_file($temporary)) { unlink($temporary); }
    }
    public static function sync($key, $file_hash, $database_hash) {
        if (!current_user_can('manage_options')) { return new WP_Error('alify_forbidden', 'Administrator permission required.'); }
        $files = self::files(); if (!is_string($key) || !isset($files[$key]) || !is_string($file_hash) || !hash_equals($files[$key]['hash'], $file_hash)) { return new WP_Error('alify_conflict', 'File changed or unavailable; reload before syncing.'); }
        $post = self::database($key); if (is_wp_error($post)) { return $post; } if (!is_string($database_hash) || !hash_equals(self::state($post), $database_hash)) { return new WP_Error('alify_conflict', 'Database changed; reload before syncing.'); }
        $definition = $files[$key]['data']['group']; if (!is_string($definition['title'] ?? null) || trim($definition['title']) === '' || !is_string($definition['location'] ?? null)) { return new WP_Error('alify_schema', 'Local group title/location invalid.'); }
        $virtual_key = 'local_' . substr(hash('sha256', $key), 0, 40); $virtual = null; foreach (Alify_CF_API::groups() as $registered) { if ($registered['key'] === $virtual_key) { $virtual = $registered; break; } }
        Alify_CF_API::unregister_group($virtual_key);
        $errors = array(); $rules = Alify_CF_Location_Rules::clean($definition['rules'] ?? null, $errors); $fields = Alify_CF::validate_schema($definition['fields'] ?? null, $definition['location'], $post ? $post->ID : 0, $errors); if ($errors) { if ($virtual) { Alify_CF_API::register_group($virtual); } return new WP_Error('alify_schema', implode(' ', $errors)); }
        $snapshots = array(); if ($post) { foreach (array(Alify_CF::SCHEMA, Alify_CF::LOCATION, '_alify_cf_local_key', '_alify_cf_location_rules') as $meta_key) { $snapshots[$meta_key] = array(metadata_exists('post', $post->ID, $meta_key), get_post_meta($post->ID, $meta_key, true)); } }
        self::$syncing = true;
        try {
            $id = wp_insert_post(wp_slash(array('ID' => $post ? $post->ID : 0, 'post_type' => Alify_CF::GROUP, 'post_title' => sanitize_text_field($definition['title']), 'post_status' => $post ? $post->post_status : 'draft')), true); if (is_wp_error($id)) { return $id; }
            update_post_meta($id, Alify_CF::SCHEMA, wp_slash($fields)); update_post_meta($id, Alify_CF::LOCATION, $definition['location']); update_post_meta($id, '_alify_cf_local_key', $key); update_post_meta($id, '_alify_cf_location_rules', wp_slash($rules));
            if (Alify_CF::schema($id) !== $fields || get_post_meta($id, Alify_CF::LOCATION, true) !== $definition['location'] || get_post_meta($id, '_alify_cf_local_key', true) !== $key || get_post_meta($id, '_alify_cf_location_rules', true) !== $rules) { if ($post) { foreach ($snapshots as $meta_key => $snapshot) { if ($snapshot[0]) { update_post_meta($id, $meta_key, wp_slash($snapshot[1])); } else { delete_post_meta($id, $meta_key); } } wp_update_post(wp_slash(array('ID' => $id, 'post_title' => $post->post_title))); } else { wp_delete_post($id, true); } if ($virtual) { Alify_CF_API::register_group($virtual); } return new WP_Error('alify_storage', 'Schema storage failed; restoration attempted.'); }
            return $id;
        } finally { self::$syncing = false; }
    }
    public static function page() {
        if (!current_user_can('manage_options')) { wp_die('Permission denied.'); }
        echo '<div class="wrap"><h1>Local JSON</h1><p>Opt in using ALIFY_CF_JSON_DIR: an existing trusted directory, preferably outside the web root. Group saves write schema only. Up to 100 files / 1 MiB each are loaded at init 60. Database definitions take precedence. New syncs create drafts; existing statuses are preserved.</p>';
        if (!self::directory()) { echo '<p>No configured directory.</p></div>'; return; }
        foreach (self::files() as $key => $file) { $post = self::database($key); if (is_wp_error($post)) { echo '<p>' . esc_html($post->get_error_message()) . '</p>'; continue; } echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="alify_local_sync"><input type="hidden" name="key" value="' . esc_attr($key) . '"><input type="hidden" name="hash" value="' . esc_attr($file['hash']) . '"><input type="hidden" name="database_hash" value="' . esc_attr(self::state($post)) . '">'; wp_nonce_field('alify_local_sync'); echo '<p>' . esc_html($key . ' — ' . ($post ? 'database #' . $post->ID : 'file only')) . ' <button class="button">Sync file to database</button></p></form>'; }
        echo '</div>';
    }
    public static function handle() {
        if (!current_user_can('manage_options')) { wp_die('Permission denied.', '', array('response' => 403)); } check_admin_referer('alify_local_sync'); $data = wp_unslash($_POST); $result = self::sync($data['key'] ?? null, $data['hash'] ?? null, $data['database_hash'] ?? null);
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', array('response' => 400)); } wp_safe_redirect(admin_url('post.php?post=' . $result . '&action=edit')); exit;
    }
}
