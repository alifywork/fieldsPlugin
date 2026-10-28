<?php
if (!defined('ABSPATH')) { exit; }

/** Native metadata adapters. Authorization is per object, not inferred from a field ID. */
final class Alify_CF_Objects {
    private static $writing = false;
    public static function boot() {
        add_action('show_user_profile', function($user) { self::render('user:' . $user->ID); });
        add_action('edit_user_profile', function($user) { self::render('user:' . $user->ID); });
        foreach (array('personal_options_update', 'edit_user_profile_update') as $hook) { add_action($hook, function($id) { self::hook_save('user:' . $id); }); }
        add_action('init', function() {
            foreach (get_taxonomies(array('show_ui' => true)) as $taxonomy) {
                add_action($taxonomy . '_edit_form_fields', function($term) { echo '<tr><th>Alify Custom Field</th><td>'; self::render('term:' . $term->term_id); echo '</td></tr>'; });
            }
        }, 40);
        add_action('edited_term', function($id) { self::hook_save('term:' . $id); });
        add_action('add_meta_boxes_comment', function($comment) { add_meta_box('alify-comment-fields', 'Alify Custom Field', function($comment) { self::render('comment:' . $comment->comment_ID); }, 'comment', 'normal'); });
        add_action('edit_comment', function($id) { self::hook_save('comment:' . $id); });
        add_action('wp_nav_menu_item_custom_fields', function($id) { self::render('menu_item:' . $id); });
        add_action('wp_update_nav_menu_item', function($menu, $id) { self::hook_save('menu_item:' . $id); }, 20, 2);
        add_action('admin_menu', function() { add_submenu_page('edit.php?post_type=' . Alify_CF::GROUP, 'Object Fields', 'Object Fields', 'read', 'alify-objects', array(__CLASS__, 'page')); });
        add_action('admin_post_alify_object_save', array(__CLASS__, 'handle'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_notices', function() { $key = 'alify_cf_object_error_' . get_current_user_id(); $error = get_transient($key); if ($error) { delete_transient($key); echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>'; } });
        add_action('rest_api_init', function() {
            register_rest_route('alify/v1', '/objects/(?P<kind>term|user|comment|menu|menu_item)/(?P<id>[1-9][0-9]*)', array('methods' => 'GET,POST', 'permission_callback' => function($request) { return !is_wp_error(self::resolve($request['kind'] . ':' . $request['id'])); }, 'callback' => array(__CLASS__, 'rest')));
        });
    }
    public static function locations() {
        $out = array('widget' => 'Classic widgets (existing instances)', 'user' => 'User profiles', 'comment' => 'Comments', 'menu' => 'Navigation menus', 'menu_item' => 'Navigation menu items');
        foreach (get_taxonomies(array('show_ui' => true), 'objects') as $name => $taxonomy) { $out['term:' . ($taxonomy->name ?? $name)] = 'Term: ' . ($taxonomy->labels->singular_name ?? $name); }
        return $out;
    }
    public static function resolve($context, $authorize = true) {
        if (!is_string($context) || !preg_match('/^(term|user|comment|menu|menu_item):([1-9][0-9]*)$/D', $context, $match) || (string) (int) $match[2] !== $match[2]) { return new WP_Error('alify_object', 'Use a supported kind:positive-ID context.', array('status' => 400)); }
        $kind = $match[1]; $id = (int) $match[2]; $meta = $kind; $location = $kind; $cap = false;
        if ($kind === 'term' || $kind === 'menu') { $term = get_term($id); if (!$term || is_wp_error($term) || ($kind === 'menu' && $term->taxonomy !== 'nav_menu')) { return new WP_Error('alify_object', 'Term unavailable.'); } $meta = 'term'; $location = $kind === 'menu' ? 'menu' : 'term:' . $term->taxonomy; $cap = $kind === 'menu' ? current_user_can('edit_theme_options') : current_user_can('edit_term', $id); }
        elseif ($kind === 'user') { $user = get_userdata($id); if (!$user || !is_user_member_of_blog($id)) { return new WP_Error('alify_object', 'Site user unavailable.'); } $cap = current_user_can('edit_user', $id); }
        elseif ($kind === 'comment') { if (!get_comment($id)) { return new WP_Error('alify_object', 'Comment unavailable.'); } $cap = current_user_can('edit_comment', $id); }
        else { $post = get_post($id); if (!$post || $post->post_type !== 'nav_menu_item') { return new WP_Error('alify_object', 'Menu item unavailable.'); } $meta = 'post'; $cap = current_user_can('edit_theme_options'); }
        if ($authorize && !$cap) { return new WP_Error('alify_forbidden', 'Object editing is not permitted.', array('status' => 403)); }
        return array('kind' => $kind, 'id' => $id, 'meta' => $meta, 'location' => $location);
    }
    public static function get($name, $context) {
        if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/D', $name)) { return null; }
        $object = self::resolve($context, false); if (is_wp_error($object)) { return null; }
        return metadata_exists($object['meta'], $object['id'], '_alify_cf_' . $name) ? get_metadata($object['meta'], $object['id'], '_alify_cf_' . $name, true) : null;
    }
    public static function write($values, $context) {
        if (self::$writing) { return new WP_Error('alify_reentrant', 'Recursive object writes are not permitted.'); }
        $object = self::resolve($context); if (is_wp_error($object)) { return $object; }
        if (!is_array($values) || !$values || count($values) > 200) { return new WP_Error('alify_values', 'Supply 1–200 named editor payloads.'); }
        $known = array(); $updates = array(); $errors = array();
        foreach (Alify_CF::groups($object['location'], $context) as $group) {
            $schema = Alify_CF::schema($group->ID); $payload = array(); $previous = array(); $touched = false;
            foreach ($schema as $field) { $name = $field['name']; $known[$name] = true; $previous[$name] = self::get($name, $context); $payload[$name] = array_key_exists($name, $values) ? $values[$name] : Alify_CF_Publication::stored_payload($field, $previous[$name] ?? $field['default']); if (array_key_exists($name, $values)) { $touched = true; } }
            if (!$touched) { continue; } $clean = Alify_CF::validate_values($schema, $payload, $previous, $errors);
            foreach ($values as $name => $unused) { if (array_key_exists($name, $payload)) { if (!array_key_exists($name, $clean)) { $errors[] = 'Requested field is hidden or invalid: ' . $name; } else { $updates[$name] = $clean[$name]; } } }
        }
        if (array_diff_key($values, $known) || $errors || !$updates) { return new WP_Error('alify_validation', 'Unknown/invalid fields. ' . implode(' ', $errors), array('status' => 400)); }
        $snapshots = array(); foreach ($updates as $name => $value) { $snapshots[$name] = array(metadata_exists($object['meta'], $object['id'], '_alify_cf_' . $name), self::get($name, $context)); }
        self::$writing = true;
        try {
            foreach ($updates as $name => $value) {
                update_metadata($object['meta'], $object['id'], '_alify_cf_' . $name, wp_slash($value)); $stored = self::get($name, $context);
                if (is_array($value) ? $value !== $stored : (string) $value !== (string) $stored) {
                    foreach ($snapshots as $key => $snapshot) { if ($snapshot[0]) { update_metadata($object['meta'], $object['id'], '_alify_cf_' . $key, wp_slash($snapshot[1])); } else { delete_metadata($object['meta'], $object['id'], '_alify_cf_' . $key); } }
                    return new WP_Error('alify_storage', 'Object storage failed; restoration attempted.');
                }
            }
            do_action('alify_cf/after_object_write', $updates, $context); return true;
        } finally { self::$writing = false; }
    }
    public static function render($context) {
        $object = self::resolve($context); if (is_wp_error($object)) { return; }
        $groups = Alify_CF::groups($object['location'], $context); if (!$groups) { return; }
        wp_nonce_field('alify_object_' . $context, 'alify_object_nonce[' . $context . ']');
        foreach ($groups as $group) {
            $data = array(); foreach (Alify_CF::schema($group->ID) as $field) { $value = self::get($field['name'], $context); if ($value !== null) { $data[$field['name']] = $value; } }
            echo '<h3>' . esc_html($group->post_title) . '</h3>'; ob_start(); Alify_CF::values((object) array('ID' => 0), $group, $data, array()); $html = ob_get_clean();
            echo str_replace(array('alify_values[', 'alify-' . $group->ID . '-'), array('alify_object_values[' . esc_attr($context) . '][', 'alify-object-' . substr(hash('sha256', $context), 0, 12) . '-' . $group->ID . '-'), $html);
        }
    }
    public static function hook_save($context) { if (!isset($_POST['alify_object_nonce'][$context])) { return; } $result = self::save_request($context); if (is_wp_error($result)) { set_transient('alify_cf_object_error_' . get_current_user_id(), $result->get_error_message(), 120); } }
    public static function save_request($context) {
        $object = self::resolve($context); if (is_wp_error($object)) { return $object; }
        $request = wp_unslash($_POST); $nonce = $request['alify_object_nonce'][$context] ?? null;
        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'alify_object_' . $context)) { return new WP_Error('alify_nonce', 'Object security check failed.'); }
        $groups = $request['alify_object_values'][$context] ?? null; $values = array();
        if (!is_array($groups)) { return new WP_Error('alify_incomplete', 'Object form incomplete.'); }
        foreach (Alify_CF::groups($object['location'], $context) as $group) { if (!is_array($groups[$group->ID] ?? null) || ($groups[$group->ID]['__group_complete'] ?? '') !== '1') { return new WP_Error('alify_incomplete', 'Object group incomplete; saved values retained.'); } foreach (Alify_CF::schema($group->ID) as $field) { if (Alify_CF_Editor_Fields::presentation($field['type'])) { continue; } $values[$field['name']] = $groups[$group->ID][$field['name']] ?? ''; } }
        return self::write($values, $context);
    }
    public static function assets($hook) {
        if (!in_array($hook, array('profile.php', 'user-edit.php', 'term.php', 'comment.php', 'nav-menus.php'), true) && strpos($hook, 'alify-objects') === false) { return; }
        wp_enqueue_style('alify-cf', plugins_url('assets/admin.css', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION);
        if (current_user_can('upload_files')) { wp_enqueue_media(); }
        Alify_CF_Rich_Text::assets(); Alify_CF_Post_Object::assets();
        foreach (array('gallery', 'flexible', 'values') as $asset) { wp_enqueue_script('alify-cf-' . $asset, plugins_url('assets/' . $asset . '.js', ALIFY_CF_FILE), $asset === 'values' ? array('alify-cf-gallery', 'alify-cf-flexible', 'alify-cf-rich-text', 'alify-cf-post-object', 'alify-cf-relationship') : array(), ALIFY_CF_VERSION, true); }
    }
    public static function page() {
        if (!current_user_can('read')) { wp_die('Permission denied.'); }
        $context = isset($_GET['context']) && is_string($_GET['context']) ? wp_unslash($_GET['context']) : '';
        echo '<div class="wrap"><h1>Object Fields</h1><p>Edit existing user:ID, term:ID, comment:ID, menu:ID or menu_item:ID objects. Per-object permissions apply.</p><form method="get"><input type="hidden" name="post_type" value="' . esc_attr(Alify_CF::GROUP) . '"><input type="hidden" name="page" value="alify-objects"><input name="context" value="' . esc_attr($context) . '"><button class="button">Open object</button></form>';
        if ($context !== '') { $object = self::resolve($context); if (is_wp_error($object)) { echo '<p role="alert">' . esc_html($object->get_error_message()) . '</p>'; } else { echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="alify_object_save"><input type="hidden" name="context" value="' . esc_attr($context) . '">'; self::render($context); echo '<button class="button button-primary">Save object fields</button></form>'; } }
        echo '</div>';
    }
    public static function handle() {
        $context = isset($_POST['context']) && is_string($_POST['context']) ? wp_unslash($_POST['context']) : ''; $result = self::save_request($context);
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', array('response' => 400)); }
        wp_safe_redirect(add_query_arg(array('post_type' => Alify_CF::GROUP, 'page' => 'alify-objects', 'context' => $context), admin_url('edit.php'))); exit;
    }
    public static function rest($request) {
        $context = $request['kind'] . ':' . $request['id']; $object = self::resolve($context); if (is_wp_error($object)) { return $object; } $read = array(); $writable = array(); $schema = array();
        foreach (Alify_CF::groups($object['location'], $context) as $group) { foreach (Alify_CF::schema($group->ID) as $field) { $name = $field['name']; if (!empty($field['api_write'])) { $writable[$name] = true; } if (!empty($field['api_read']) && !Alify_CF_Password::contains($field) && !Alify_CF_API::contains_custom($field)) { $read[$name] = self::get($name, $context); } if (!empty($field['api_write']) || !empty($field['api_read'])) { $schema[$name] = array('type' => $field['type'], 'read' => array_key_exists($name, $read), 'write' => isset($writable[$name])); } } }
        if ($request->get_method() === 'POST') { $values = $request->get_param('values'); if (!is_array($values) || !$values || array_diff_key($values, $writable)) { return new WP_Error('alify_exposure', 'Only opted-in fields are writable.', array('status' => 400)); } $result = self::write($values, $context); return is_wp_error($result) ? $result : array('saved' => true); }
        return array('values' => $read, 'schema' => $schema);
    }
}
