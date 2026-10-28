<?php
if (!defined('ABSPATH')) { exit; }

/** Single post/page reference with permission-filtered, bounded search. */
final class Alify_CF_Post_Object {
    public static function boot() { add_action('wp_ajax_alify_cf_search_posts', array(__CLASS__, 'ajax')); }
    private static function id($raw) {
        if ((!is_int($raw) && !is_string($raw)) || !preg_match('/^[1-9][0-9]*$/D', (string) $raw) || (string) (int) $raw !== (string) $raw) { return ''; }
        return (int) $raw;
    }
    public static function clean($raw, $old = null, $permissions = false, $field = array()) {
        if ($raw === '') { return ''; }
        $id = self::id($raw); $post = $id ? get_post($id) : null;
        if (!$post || !Alify_CF_Reference_Settings::allowed($post, $field)) { return ''; }
        if ($permissions && self::id($old) !== $id && !self::readable($id, $field)) { return ''; }
        return $id;
    }
    public static function readable($id, $field = array()) {
        if (self::clean($id, null, false, $field) === '' || !current_user_can('read_post', $id)) { return false; }
        return !post_password_required(get_post($id)) || current_user_can('edit_post', $id);
    }
    public static function resolve($raw, $field = array()) {
        if (!$field) { $candidate = self::id($raw); $post = $candidate ? get_post($candidate) : null; if ($post && isset(Alify_CF_Reference_Settings::types()[$post->post_type])) { $field['post_types'] = array($post->post_type); } }
        $id = self::clean($raw, null, false, $field); if ($id === '') { return null; }
        $post = get_post($id);
        return (is_post_publicly_viewable($post) && !post_password_required($post)) || self::readable($id, $field) ? $post : null;
    }
    public static function assets() {
        wp_enqueue_script('alify-cf-selector', plugins_url('assets/selector.js', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION, true);
        wp_localize_script('alify-cf-selector', 'AlifySelector', array('url' => admin_url('admin-ajax.php', 'relative'), 'nonce' => wp_create_nonce('alify_cf_search_objects')));
        wp_enqueue_script('alify-cf-post-object', plugins_url('assets/post-object.js', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION, true);
        wp_enqueue_script('alify-cf-relationship', plugins_url('assets/relationship.js', ALIFY_CF_FILE), array('alify-cf-post-object'), ALIFY_CF_VERSION, true);
        wp_localize_script('alify-cf-post-object', 'AlifyPostObject', array('url' => admin_url('admin-ajax.php', 'relative'), 'nonce' => wp_create_nonce('alify_cf_search_posts')));
    }
    public static function search($term, $type, $page, $nonce, $settings = array()) {
        $errors = array(); $field = Alify_CF_Reference_Settings::settings(array_merge(is_array($settings) ? $settings : array(), array('type' => 'post_object')), $errors);
        if ($errors || !is_array($settings)) { return new WP_Error('alify_search_input', 'Invalid reference filters.', array('status' => 400)); }
        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'alify_cf_search_posts')) { return new WP_Error('alify_search_nonce', 'Security check failed. Reload the editor.', array('status' => 403)); }
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages') && !current_user_can('manage_options')) { return new WP_Error('alify_search_forbidden', 'You cannot search post references.', array('status' => 403)); }
        if (!is_string($term) || strlen($term) > 200 || !is_string($type) || !in_array($type, array_merge(array(''), $field['post_types'] ?? array('post', 'page')), true) || (!is_int($page) && !is_string($page)) || !preg_match('/^[1-9][0-9]{0,2}$/D', (string) $page) || (int) $page > 100) { return new WP_Error('alify_search_input', 'Use a valid search and page from 1 to 100.', array('status' => 400)); }
        $posts = get_posts(array('post_type' => $type ?: ($field['post_types'] ?? array('post', 'page')), 'post_status' => $field['post_statuses'] ?? array('publish', 'private', 'draft', 'pending', 'future'), 's' => sanitize_text_field($term), 'posts_per_page' => 20, 'paged' => (int) $page, 'orderby' => 'ID', 'order' => 'DESC', 'no_found_rows' => true, 'suppress_filters' => false));
        $items = array();
        foreach ($posts as $post) {
            if (!self::readable($post->ID, $field)) { continue; }
            $items[] = array('id' => $post->ID, 'title' => sanitize_text_field(get_the_title($post)) ?: '(Untitled)', 'type' => $post->post_type);
        }
        // Never return unfiltered totals, private titles or unavailable identities.
        return array('items' => $items, 'page' => (int) $page);
    }
    public static function ajax() {
        $data = wp_unslash($_POST);
        $result = self::search($data['search'] ?? '', $data['type'] ?? '', $data['page'] ?? '1', $data['nonce'] ?? '', $data['reference_settings'] ?? array());
        if (is_wp_error($result)) { $extra = $result->get_error_data(); wp_send_json_error(array('message' => $result->get_error_message()), $extra['status'] ?? 400); }
        wp_send_json_success($result);
    }
    public static function render($field, $value, $id, $input) {
        $selected = self::id($value);
        $label = $selected ? 'Saved selection unavailable. Clear or choose a replacement.' : 'No post selected.';
        if ($selected && self::readable($selected, $field)) { $label = sanitize_text_field(get_the_title($selected)) ?: '(Untitled)'; }
        echo '<div class="alify-post-object" role="group" aria-label="' . esc_attr($field['label']) . '"' . Alify_CF_Reference_Settings::attributes($field) . '><input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" value="' . esc_attr($selected) . '"><p class="alify-post-selected" role="status">' . esc_html($label) . '</p><button type="button" class="button" data-post-clear>Clear selection</button>'; self::search_controls($id, $field); echo '<p class="description">Single post reference ID. Configured type/status filters and read permissions are enforced.</p></div>';
    }
    private static function type_options($field) { $html = ''; foreach ($field['post_types'] ?? array('post', 'page') as $type) { $html .= '<option value="' . esc_attr($type) . '">' . esc_html($type) . '</option>'; } return $html; }
    public static function search_controls($id, $field = array()) {
        echo '<div class="alify-post-search"><label for="' . esc_attr($id . '-search') . '">Search posts/pages</label><input type="search" id="' . esc_attr($id . '-search') . '" maxlength="200" data-post-search><label for="' . esc_attr($id . '-type') . '">Type</label><select id="' . esc_attr($id . '-type') . '" data-post-type><option value="">All allowed types</option>' . self::type_options($field) . '</select><button type="button" class="button" data-post-find>Search</button><div class="alify-post-results"></div><p class="alify-post-status" role="status" aria-live="polite"></p><button type="button" class="button" data-post-prev disabled>Previous</button> <button type="button" class="button" data-post-next disabled>Next</button></div>';
    }

}
