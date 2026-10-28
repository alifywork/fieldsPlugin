<?php
if (!defined('ABSPATH')) { exit; }

/** Ordered image IDs with an explicit completion marker for safe empty saves. */
final class Alify_CF_Gallery {
    const MAX_IMAGES = 50;

    public static function clean($payload, $old = array(), $permissions = false) {
        if (!is_array($payload) || ($payload['__complete'] ?? '') !== '1') { return ''; }
        $ids = $payload['ids'] ?? array();
        if (!is_array($ids) || count($ids) > self::MAX_IMAGES) { return ''; }
        $existing = array();
        foreach ((array) $old as $id) { if (is_int($id) || is_string($id)) { $existing[(string) $id] = true; } }
        $result = array();
        foreach ($ids as $id) {
            if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]*$/', (string) $id) || (string) (int) $id !== (string) $id) { return ''; }
            $id = (int) $id;
            $attachment = get_post($id);
            if (!$attachment || $attachment->post_type !== 'attachment' || $attachment->post_status === 'trash' || !wp_attachment_is_image($id)) { return ''; }
            if ($permissions && !isset($existing[(string) $id]) && !current_user_can('edit_post', $id)) { return ''; }
            if (!in_array($id, $result, true)) { $result[] = $id; }
        }
        return $result;
    }

    public static function render($field, $value, $id, $input) {
        $ids = is_array($value) ? $value : array();
        echo '<div class="alify-gallery" id="' . esc_attr($id) . '" role="group" aria-label="' . esc_attr($field['label']) . '" data-limit="' . esc_attr($field['max_items'] ?? self::MAX_IMAGES) . '"' . Alify_CF_Field_Settings::attributes($field) . '><div class="alify-gallery-items">';
        foreach ($ids as $image_id) {
            if (!is_scalar($image_id) || !preg_match('/^[1-9][0-9]*$/', (string) $image_id)) { continue; }
            $attachment = get_post((int) $image_id);
            $valid = $attachment && $attachment->post_type === 'attachment' && $attachment->post_status !== 'trash' && wp_attachment_is_image((int) $image_id);
            $url = $valid ? wp_get_attachment_image_url((int) $image_id, $field['preview_size'] ?? 'thumbnail') : false;
            echo '<div class="alify-gallery-item" data-image-id="' . esc_attr($image_id) . '"><input type="hidden" name="' . esc_attr($input . '[ids][]') . '" value="' . esc_attr($image_id) . '">';
            if ($url) { echo '<img alt="" src="' . esc_url($url) . '">'; }
            echo '<span class="alify-gallery-title">' . esc_html($valid ? $attachment->post_title : 'Image unavailable — remove or replace it') . '</span><div class="alify-gallery-actions"><button type="button" class="button" data-gallery="up" aria-label="Move image earlier">←</button><button type="button" class="button" data-gallery="down" aria-label="Move image later">→</button><button type="button" class="button-link-delete" data-gallery="remove">Remove</button></div></div>';
        }
        echo '</div><input class="alify-gallery-complete" type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1"><div class="alify-gallery-footer">';
        if (current_user_can('upload_files')) { echo '<button type="button" class="button" data-gallery="add">+ Add Images</button>'; }
        echo '<button type="button" class="button-link-delete" data-gallery="clear">Remove all</button><span class="alify-gallery-count" aria-live="polite">' . esc_html(count($ids)) . ' / 50 images</span></div><p class="alify-gallery-status description" role="status"></p></div>';
    }
}
