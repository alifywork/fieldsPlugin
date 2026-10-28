<?php
if (!defined('ABSPATH')) { exit; }

/** Authenticated editing of existing posts. Not anonymous submission or post creation. */
final class Alify_CF_Forms {
    public static function boot() { add_action('admin_post_alify_frontend_save', array(__CLASS__, 'handle')); }
    public static function prepare() { Alify_CF_Objects::assets('alify-objects'); }
    public static function render($post_id) {
        $post = get_post($post_id); if (!$post || wp_is_post_revision($post->ID) || $post->post_type === Alify_CF::GROUP || !current_user_can('edit_post', $post->ID)) { return new WP_Error('alify_forbidden', 'You cannot edit this post.'); }
        $groups = Alify_CF::groups($post->post_type, $post->ID); $count = 0; foreach ($groups as $group) { $count += count(Alify_CF::schema($group->ID)); } if (!$count || $count > 200) { return new WP_Error('alify_form', 'Form needs 1–200 configured fields.'); }
        echo '<form class="alify-frontend-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="alify_frontend_save"><input type="hidden" name="post_id" value="' . esc_attr($post->ID) . '">'; wp_nonce_field('alify_frontend_' . $post->ID, 'alify_frontend_nonce');
        foreach ($groups as $group) { echo '<h2>' . esc_html($group->post_title) . '</h2>'; Alify_CF::values($post, $group); } echo '<button type="submit">Save fields</button></form>'; return true;
    }
    public static function submit($post_id, $request) {
        $post = is_int($post_id) || is_string($post_id) ? get_post($post_id) : null;
        if (!$post || wp_is_post_revision($post->ID) || $post->post_type === Alify_CF::GROUP || !current_user_can('edit_post', $post->ID)) { return new WP_Error('alify_forbidden', 'Post update not permitted.'); }
        if (!is_array($request) || !is_string($request['alify_frontend_nonce'] ?? null) || !wp_verify_nonce($request['alify_frontend_nonce'], 'alify_frontend_' . $post->ID)) { return new WP_Error('alify_nonce', 'Form security check failed.'); }
        $values = array(); foreach (Alify_CF::groups($post->post_type, $post->ID) as $group) {
            $payload = $request['alify_values'][$group->ID] ?? null;
            if (!is_array($payload) || ($payload['__group_complete'] ?? '') !== '1') { return new WP_Error('alify_incomplete', 'Incomplete field group; no changes saved.'); }
            foreach (Alify_CF::schema($group->ID) as $field) { if (!Alify_CF_Editor_Fields::presentation($field['type']) && array_key_exists($field['name'], $payload)) { $values[$field['name']] = $payload[$field['name']]; } }
        }
        return alify_update_fields($values, $post->ID);
    }
    public static function handle() {
        $request = wp_unslash($_POST); $id = $request['post_id'] ?? null; $result = self::submit($id, $request);
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', array('response' => 400)); }
        wp_safe_redirect(wp_get_referer() ?: admin_url('post.php?post=' . (int) $id . '&action=edit'), 303); exit;
    }
}
