<?php
if (!defined('ABSPATH')) { exit; }

/** Ordered, atomic list of post/page IDs with explicit completion. */
final class Alify_CF_Relationship {
    const MAX_ITEMS = 50;
    public static function clean($raw, $old = array(), $permissions = false, $field = array()) {
        if (!is_array($raw) || ($raw['__complete'] ?? '') !== '1') { return ''; }
        $ids = array_key_exists('ids', $raw) ? $raw['ids'] : array();
        if (!is_array($ids) || array_values($ids) !== $ids || count($ids) > self::MAX_ITEMS) { return ''; }
        $existing = array();
        foreach (is_array($old) ? $old : array() as $id) { if (is_int($id) || is_string($id)) { $existing[] = (string) $id; } }
        $out = array();
        foreach ($ids as $raw_id) {
            $id = Alify_CF_Post_Object::clean($raw_id, null, false, $field);
            if ($id === '' || ($permissions && !in_array((string) $id, $existing, true) && !Alify_CF_Post_Object::readable($id, $field))) { return ''; }
            if (!in_array($id, $out, true)) { $out[] = $id; }
        }
        if ($out && (count($out) < ($field['min_items'] ?? 0) || count($out) > ($field['max_items'] ?? 50))) { return ''; }
        return $out;
    }
    public static function render($field, $value, $id, $input) {
        echo '<div class="alify-post-object alify-relationship" id="' . esc_attr($id) . '" role="group" aria-label="' . esc_attr($field['label']) . '" data-input="' . esc_attr($input) . '"' . Alify_CF_Reference_Settings::attributes($field) . '><div class="alify-relation-items">';
        foreach (is_array($value) ? $value : array() as $post_id) {
            if ((!is_int($post_id) && !is_string($post_id)) || !preg_match('/^[1-9][0-9]*$/D', (string) $post_id) || (string) (int) $post_id !== (string) $post_id) { continue; }
            $label = Alify_CF_Post_Object::readable($post_id, $field) ? sanitize_text_field(get_the_title($post_id)) : 'Saved selection unavailable — remove or replace it';
            echo '<div class="alify-relation-item"><input type="hidden" name="' . esc_attr($input . '[ids][]') . '" value="' . esc_attr($post_id) . '"><span class="alify-relation-title">' . esc_html($label ?: '(Untitled)') . '</span><button type="button" class="button" data-relation-action="up" aria-label="Move reference earlier">↑</button><button type="button" class="button" data-relation-action="down" aria-label="Move reference later">↓</button><button type="button" class="button-link-delete" data-relation-action="remove">Remove reference</button></div>';
        }
        echo '</div><input type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1"><button type="button" class="button" data-relation-action="clear">Clear all references</button> <span class="alify-relation-count" aria-live="polite"></span>';
        Alify_CF_Post_Object::search_controls($id, $field);
        echo '<p class="description">Ordered post references; configured type/status/count bounds apply, hard maximum 50. Invalid submissions retain the entire saved list. Search pages are permission-filtered.</p></div>';
    }
}
