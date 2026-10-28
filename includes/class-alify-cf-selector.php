<?php
if (!defined('ABSPATH')) { exit; }

/** Bounded term/user/public-link selectors. Never serialize WP_User objects. */
final class Alify_CF_Selector {
    public static function supported($field) { return in_array($field['type'] ?? '', array('taxonomy', 'user', 'page_link'), true); }
    public static function multiple($field) { return self::supported($field) && !empty($field['multiple']); }
    public static function boot() { add_action('wp_ajax_alify_cf_search_objects', array(__CLASS__, 'ajax')); }
    private static function list_setting($raw, $allowed, &$errors, $empty = false) {
        if (is_string($raw)) { $raw = trim($raw) === '' ? array() : preg_split('/\r?\n/', trim($raw)); }
        if (!is_array($raw) || (!$empty && !$raw) || array_values($raw) !== $raw || count($raw) > 20) { $errors[] = 'Selector filters need a list of up to 20 registered identifiers.'; return array(); }
        $out = array(); foreach ($raw as $item) { if (!is_string($item) || !in_array(trim($item), $allowed, true)) { $errors[] = 'Selector filter contains an unavailable identifier.'; } else { $out[] = trim($item); } } return array_values(array_unique($out));
    }
    public static function settings($field, &$errors) {
        if (!self::supported($field)) { return array(); }
        $out = array();
        foreach (array('multiple', 'allow_archives') as $key) {
            if (!array_key_exists($key, $field) || ($key === 'allow_archives' && $field['type'] !== 'page_link')) { continue; }
            if (!in_array($field[$key], array(true, false, 0, 1, '0', '1'), true)) { $errors[] = 'Selector flags must be boolean.'; } else { $out[$key] = (bool) $field[$key]; }
        }
        if ($field['type'] === 'taxonomy') { $out['taxonomies'] = self::list_setting($field['taxonomies'] ?? array('category'), array_keys(get_taxonomies(array('show_ui' => true), 'objects')), $errors); }
        if ($field['type'] === 'user') { $out['roles'] = self::list_setting($field['roles'] ?? array(), array_keys(wp_roles()->roles), $errors, true); }
        if ($field['type'] === 'page_link') {
            $types = array(); foreach (Alify_CF_Reference_Settings::types() as $name => $type) { if (!empty($type->public)) { $types[] = $name; } }
            $out['post_types'] = self::list_setting($field['post_types'] ?? array('post', 'page'), $types, $errors);
        }
        if (array_key_exists('return_format', $field)) {
            $allowed = $field['type'] === 'taxonomy' ? array('id', 'object') : ($field['type'] === 'user' ? array('id', 'array') : array('url'));
            if (!in_array($field['return_format'], $allowed, true)) { $errors[] = 'Choose a supported selector return format.'; } else { $out['return_format'] = $field['return_format']; }
        }
        return $out;
    }
    private static function positive_id($raw) { return (is_string($raw) || is_int($raw)) && preg_match('/^[1-9][0-9]*$/D', (string) $raw) && (string) (int) $raw === (string) $raw ? (int) $raw : null; }
    private static function object($field, $raw) {
        if ($field['type'] === 'page_link') {
            if (!is_string($raw)) { return null; }
            if (preg_match('/^post:([1-9][0-9]*)$/D', $raw, $match)) {
                $id = self::positive_id($match[1]); $post = $id ? get_post($id) : null; $type = $post ? get_post_type_object($post->post_type) : null;
                return $post && $type && $type->public && $post->post_status === 'publish' && in_array($post->post_type, $field['post_types'] ?? array('post', 'page'), true) ? $post : null;
            }
            if (!empty($field['allow_archives']) && preg_match('/^archive:([a-z0-9_-]+)$/D', $raw, $match) && in_array($match[1], $field['post_types'] ?? array('post', 'page'), true)) {
                $type = get_post_type_object($match[1]); return $type && $type->public && $type->has_archive && get_post_type_archive_link($match[1]) ? $type : null;
            }
            return null;
        }
        $id = self::positive_id($raw); if (!$id) { return null; }
        if ($field['type'] === 'taxonomy') { $term = get_term($id); return $term && !is_wp_error($term) && in_array($term->taxonomy, $field['taxonomies'] ?? array('category'), true) && taxonomy_exists($term->taxonomy) ? $term : null; }
        $user = get_userdata($id); return $user && is_user_member_of_blog($id) && empty($user->spam) && empty($user->deleted) && (empty($field['roles']) || array_intersect($user->roles, $field['roles'])) ? $user : null;
    }
    private static function accessible($field, $object, $writing = false) {
        if ($field['type'] === 'page_link') { return true; }
        if ($field['type'] === 'user') { return current_user_can('list_users') || (!$writing && get_current_user_id() === (int) $object->ID); }
        $taxonomy = get_taxonomy($object->taxonomy);
        return $taxonomy && ((!$writing && !empty($taxonomy->public)) || current_user_can($taxonomy->cap->assign_terms));
    }
    public static function clean($field, $raw, $old = null, $permissions = false) {
        $multi = self::multiple($field);
        if (!$multi && $raw === '') { return ''; }
        if ($multi) {
            if (!is_array($raw) || ($raw['__complete'] ?? '') !== '1') { return ''; }
            $ids = array_key_exists('ids', $raw) ? $raw['ids'] : array();
            if (!is_array($ids) || array_values($ids) !== $ids || count($ids) > 50) { return ''; }
        } else { $ids = array($raw); }
        $existing = array(); foreach (is_array($old) ? $old : array($old) as $id) { if (is_int($id) || is_string($id)) { $existing[] = (string) $id; } }
        $out = array(); foreach ($ids as $id) {
            $object = self::object($field, $id);
            if (!$object || ($permissions && !self::accessible($field, $object, true) && !in_array((string) $id, $existing, true))) { return ''; }
            $id = $field['type'] === 'page_link' ? $id : (int) $id; if (!in_array($id, $out, true)) { $out[] = $id; }
        }
        return $multi ? $out : $out[0];
    }
    public static function format($field, $value) {
        if ($value === null || $value === '' || $value === array()) { return $value; }
        if ($field['type'] !== 'page_link' && ($field['return_format'] ?? 'id') === 'id') { return $value; }
        $one = function($id) use ($field) {
            $object = self::object($field, $id); if (!$object || !self::accessible($field, $object)) { return null; }
            if ($field['type'] === 'taxonomy') { return $object; }
            if ($field['type'] === 'user') { return array('id' => (int) $object->ID, 'display_name' => $object->display_name); }
            return strpos($id, 'post:') === 0 ? (get_permalink($object->ID) ?: null) : (get_post_type_archive_link($object->name) ?: null);
        };
        if (!self::multiple($field)) { return $one($value); }
        $out = array(); foreach (is_array($value) ? $value : array() as $id) { $item = $one($id); if ($item !== null) { $out[] = $item; } } return $out;
    }
    private static function label($field, $id) {
        $object = self::object($field, $id); if (!$object || !self::accessible($field, $object, true)) { return 'Saved selection unavailable — remove or replace it'; }
        if ($field['type'] === 'taxonomy') { return $object->name; }
        if ($field['type'] === 'user') { return $object->display_name; }
        return strpos($id, 'archive:') === 0 ? 'Archive: ' . $object->labels->name : get_the_title($object->ID);
    }
    public static function search($field, $term, $page, $nonce) {
        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'alify_cf_search_objects') || (!current_user_can('edit_posts') && !current_user_can('edit_pages') && !current_user_can('manage_options'))) { return new WP_Error('alify_selector_forbidden', 'Selector search is not permitted.', array('status' => 403)); }
        if (!is_array($field) || !self::supported($field) || !is_string($term) || strlen($term) > 200 || (!is_string($page) && !is_int($page)) || !preg_match('/^[1-9][0-9]{0,2}$/D', (string) $page) || (int) $page > 100) { return new WP_Error('alify_selector_input', 'Invalid selector search.', array('status' => 400)); }
        $errors = array(); $field = array_merge(array('type' => $field['type']), self::settings($field, $errors)); if ($errors) { return new WP_Error('alify_selector_input', 'Invalid selector settings.', array('status' => 400)); }
        $term = sanitize_text_field($term); $offset = ((int) $page - 1) * 20; $items = array();
        if ($field['type'] === 'user') {
            if (!current_user_can('list_users')) { return new WP_Error('alify_selector_forbidden', 'User selection requires list_users permission.', array('status' => 403)); }
            $objects = get_users(array('blog_id' => get_current_blog_id(), 'role__in' => $field['roles'], 'search' => $term === '' ? '' : '*' . $term . '*', 'search_columns' => array('display_name'), 'number' => 20, 'offset' => $offset, 'orderby' => 'ID', 'order' => 'ASC'));
            foreach ($objects as $object) { if (self::object($field, $object->ID)) { $items[] = array('id' => $object->ID, 'title' => $object->display_name); } }
        } elseif ($field['type'] === 'taxonomy') {
            $taxonomies = array_filter($field['taxonomies'], function($name) { $taxonomy = get_taxonomy($name); return $taxonomy && current_user_can($taxonomy->cap->assign_terms); });
            if (!$taxonomies) { return new WP_Error('alify_selector_forbidden', 'Term assignment is not permitted.', array('status' => 403)); }
            $objects = get_terms(array('taxonomy' => array_values($taxonomies), 'hide_empty' => false, 'search' => $term, 'number' => 20, 'offset' => $offset, 'orderby' => 'term_id', 'order' => 'ASC'));
            if (is_wp_error($objects)) { return new WP_Error('alify_selector_input', 'Term search unavailable.', array('status' => 400)); }
            foreach ($objects as $object) { $items[] = array('id' => $object->term_id, 'title' => $object->name . ' (' . $object->taxonomy . ')'); }
        } else {
            $objects = get_posts(array('post_type' => $field['post_types'], 'post_status' => 'publish', 's' => $term, 'posts_per_page' => 20, 'offset' => $offset, 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true));
            foreach ($objects as $object) { if (self::object($field, 'post:' . $object->ID)) { $items[] = array('id' => 'post:' . $object->ID, 'title' => get_the_title($object)); } }
            if ((int) $page === 1 && !empty($field['allow_archives'])) { foreach ($field['post_types'] as $type) { $token = 'archive:' . $type; if (self::object($field, $token) && ($term === '' || stripos(self::label($field, $token), $term) !== false)) { $items[] = array('id' => $token, 'title' => self::label($field, $token)); } } }
        }
        return array('items' => $items, 'page' => (int) $page);
    }
    public static function ajax() {
        $data = wp_unslash($_POST); $field = isset($data['settings']) && is_string($data['settings']) ? json_decode($data['settings'], true) : null;
        $result = self::search($field, $data['search'] ?? '', $data['page'] ?? '1', $data['nonce'] ?? '');
        if (is_wp_error($result)) { $extra = $result->get_error_data(); wp_send_json_error(array('message' => $result->get_error_message()), $extra['status'] ?? 400); } wp_send_json_success($result);
    }
    public static function render($field, $value, $id, $input, $error_id = '') {
        $settings = array_intersect_key($field, array_flip(array('type', 'taxonomies', 'roles', 'post_types', 'allow_archives'))); $multi = self::multiple($field);
        echo '<div class="alify-selector" role="group" aria-label="' . esc_attr($field['label']) . '" aria-describedby="' . esc_attr($id . '-help' . ($error_id ? ' ' . $error_id : '')) . '"' . ($error_id ? ' aria-invalid="true"' : '') . ' id="' . esc_attr($id) . '" data-settings="' . esc_attr(wp_json_encode($settings)) . '" data-multiple="' . ($multi ? '1' : '0') . '" data-input="' . esc_attr($input) . '">' . (!$multi ? '<input type="hidden" data-selector-empty name="' . esc_attr($input) . '" value="">' : '') . '<div class="alify-selector-selected">';
        foreach ($multi ? (is_array($value) ? $value : array()) : ($value === '' || $value === null ? array() : array($value)) as $selected) {
            if (!is_string($selected) && !is_int($selected)) { continue; }
            echo '<div class="alify-selector-item"><input type="hidden" name="' . esc_attr($input . ($multi ? '[ids][]' : '')) . '" value="' . esc_attr($selected) . '"><span>' . esc_html(self::label($field, $selected)) . '</span><button type="button" class="button" data-selector-remove>Remove</button>' . ($multi ? '<button type="button" class="button" data-selector-up>Move earlier</button><button type="button" class="button" data-selector-down>Move later</button>' : '') . '</div>';
        }
        echo '</div>' . ($multi ? '<input type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1">' : '') . '<label>Search ' . esc_html($field['label']) . ' <input type="search" data-selector-search maxlength="200"></label><button type="button" class="button" data-selector-find>Search</button><button type="button" class="button" data-selector-clear>Clear selection</button><div class="alify-selector-results"></div><p data-selector-status role="status" aria-live="polite"></p><button type="button" class="button" data-selector-prev disabled>Previous</button><button type="button" class="button" data-selector-next disabled>Next</button><p class="description">' . ($multi ? 'Ordered selections, maximum 50. ' : 'Single selection. ') . ($field['type'] === 'user' ? 'User search requires list_users; only display names are shown. ' : '') . 'Invalid saves retain previous selections. No term assignment/user modification is performed.</p></div>';
    }
}
