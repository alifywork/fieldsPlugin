<?php
if (!defined('ABSPATH')) { exit; }

/** Single post/page reference with permission-filtered, bounded search. */
final class Alify_CF_Post_Object {
    public static function boot() { add_action('wp_ajax_alify_cf_search_posts', array(__CLASS__, 'ajax')); }
    private static function id($raw) {
        if ((!is_int($raw) && !is_string($raw)) || !preg_match('/^[1-9][0-9]*$/D', (string) $raw) || (string) (int) $raw !== (string) $raw) { return ''; }
        return (int) $raw;
    }
    public static function clean($raw, $old = null, $permissions = false) {
        if ($raw === '') { return ''; }
        $id = self::id($raw); $post = $id ? get_post($id) : null;
        if (!$post || !in_array($post->post_type, array('post', 'page'), true) || !in_array($post->post_status, array('publish', 'private', 'draft', 'pending', 'future'), true)) { return ''; }
        if ($permissions && self::id($old) !== $id && !self::readable($id)) { return ''; }
        return $id;
    }
    public static function readable($id) {
        if (self::clean($id) === '' || !current_user_can('read_post', $id)) { return false; }
        return !post_password_required(get_post($id)) || current_user_can('edit_post', $id);
    }
    public static function resolve($raw) {
        $id = self::clean($raw); if ($id === '') { return null; }
        $post = get_post($id);
        return (is_post_publicly_viewable($post) && !post_password_required($post)) || self::readable($id) ? $post : null;
    }
    public static function assets() {
        wp_enqueue_script('alify-cf-post-object', plugins_url('assets/post-object.js', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION, true);
        wp_enqueue_script('alify-cf-relationship', plugins_url('assets/relationship.js', ALIFY_CF_FILE), array('alify-cf-post-object'), ALIFY_CF_VERSION, true);
        wp_localize_script('alify-cf-post-object', 'AlifyPostObject', array('url' => admin_url('admin-ajax.php', 'relative'), 'nonce' => wp_create_nonce('alify_cf_search_posts')));
    }
    public static function search($term, $type, $page, $nonce) {
        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'alify_cf_search_posts')) { return new WP_Error('alify_search_nonce', 'Security check failed. Reload the editor.', array('status' => 403)); }
        if (!current_user_can('edit_posts') && !current_user_can('edit_pages') && !current_user_can('manage_options')) { return new WP_Error('alify_search_forbidden', 'You cannot search post references.', array('status' => 403)); }
        if (!is_string($term) || strlen($term) > 200 || !is_string($type) || !in_array($type, array('', 'post', 'page'), true) || (!is_int($page) && !is_string($page)) || !preg_match('/^[1-9][0-9]{0,2}$/D', (string) $page) || (int) $page > 100) { return new WP_Error('alify_search_input', 'Use a valid search and page from 1 to 100.', array('status' => 400)); }
        $posts = get_posts(array('post_type' => $type ?: array('post', 'page'), 'post_status' => array('publish', 'private', 'draft', 'pending', 'future'), 's' => sanitize_text_field($term), 'posts_per_page' => 20, 'paged' => (int) $page, 'orderby' => 'ID', 'order' => 'DESC', 'no_found_rows' => true, 'suppress_filters' => false));
        $items = array();
        foreach ($posts as $post) {
            if (!self::readable($post->ID)) { continue; }
            $items[] = array('id' => $post->ID, 'title' => sanitize_text_field(get_the_title($post)) ?: '(Untitled)', 'type' => $post->post_type);
        }
        // Never return unfiltered totals, private titles or unavailable identities.
        return array('items' => $items, 'page' => (int) $page);
    }
    public static function ajax() {
        $data = wp_unslash($_POST);
        $result = self::search($data['search'] ?? '', $data['type'] ?? '', $data['page'] ?? '1', $data['nonce'] ?? '');
        if (is_wp_error($result)) { $extra = $result->get_error_data(); wp_send_json_error(array('message' => $result->get_error_message()), $extra['status'] ?? 400); }
        wp_send_json_success($result);
    }
    public static function render($field, $value, $id, $input) {
        $selected = self::id($value);
        $label = $selected ? 'Saved selection unavailable. Clear or choose a replacement.' : 'No post selected.';
        if ($selected && self::readable($selected)) { $label = sanitize_text_field(get_the_title($selected)) ?: '(Untitled)'; }
        echo '<div class="alify-post-object" role="group" aria-label="' . esc_attr($field['label']) . '"><input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" value="' . esc_attr($selected) . '"><p class="alify-post-selected" role="status">' . esc_html($label) . '</p><button type="button" class="button" data-post-clear>Clear selection</button>'; self::search_controls($id); echo '<p class="description">Single post/page ID. Search pages are permission-filtered and may be empty. No custom post types in this increment.</p></div>';
    }
    public static function search_controls($id) {
        echo '<div class="alify-post-search"><label for="' . esc_attr($id . '-search') . '">Search posts/pages</label><input type="search" id="' . esc_attr($id . '-search') . '" maxlength="200" data-post-search><label for="' . esc_attr($id . '-type') . '">Type</label><select id="' . esc_attr($id . '-type') . '" data-post-type><option value="">Posts and Pages</option><option value="post">Posts</option><option value="page">Pages</option></select><button type="button" class="button" data-post-find>Search</button><div class="alify-post-results"></div><p class="alify-post-status" role="status" aria-live="polite"></p><button type="button" class="button" data-post-prev disabled>Previous</button> <button type="button" class="button" data-post-next disabled>Next</button></div>';
    }

}
