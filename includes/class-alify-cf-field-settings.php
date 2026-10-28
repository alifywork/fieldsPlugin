<?php
if (!defined('ABSPATH')) { exit; }

/** Bounded color, media and link configuration. Raw getters are never reformatted. */
final class Alify_CF_Field_Settings {
    public static function media($field) { return in_array($field['type'], array('image', 'file', 'gallery'), true); }
    public static function color($field, $value) {
        if (!is_string($value)) { return ''; }
        return preg_match(!empty($field['alpha']) ? '/^#[0-9a-fA-F]{6}(?:[0-9a-fA-F]{2})?$/D' : '/^#[0-9a-fA-F]{6}$/D', $value) ? strtolower($value) : '';
    }
    public static function settings($field, &$errors) {
        $out = array(); $type = $field['type'];
        if ($type === 'color') {
            if (array_key_exists('alpha', $field)) {
                if (!in_array($field['alpha'], array(true, false, 0, 1, '0', '1'), true)) { $errors[] = 'Color alpha must be boolean.'; }
                else { $out['alpha'] = (bool) $field['alpha']; }
            }
            if (array_key_exists('palette', $field)) {
                $raw = $field['palette']; if (is_string($raw)) { $raw = trim($raw) === '' ? array() : preg_split('/\r?\n/', trim($raw)); }
                if (!is_array($raw) || array_values($raw) !== $raw || count($raw) > 20) { $errors[] = 'Choose up to 20 palette colors.'; }
                else { $out['palette'] = array(); foreach ($raw as $color) { $color = self::color($out + array('type' => 'color'), is_string($color) ? trim($color) : $color); if (!$color) { $errors[] = 'Palette colors must use supported hex notation.'; } elseif (!in_array($color, $out['palette'], true)) { $out['palette'][] = $color; } } }
            }
        }
        if (self::media($field)) {
            foreach (array('min_bytes', 'max_bytes', 'min_items', 'max_items') as $key) {
                if (!array_key_exists($key, $field) || ($type !== 'gallery' && in_array($key, array('min_items', 'max_items'), true))) { continue; }
                $raw = $field[$key]; $limit = strpos($key, 'items') !== false ? 50 : 1073741824;
                if ((!is_int($raw) && !is_string($raw)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string) $raw) || strlen((string) $raw) > 10 || (float) $raw > $limit || ($key === 'max_items' && (int) $raw === 0)) { $errors[] = 'Media bounds must be whole numbers in the supported range.'; }
                else { $out[$key] = (int) $raw; }
            }
            if (($out['max_bytes'] ?? 0) && ($out['min_bytes'] ?? 0) > $out['max_bytes']) { $errors[] = 'Minimum bytes cannot exceed maximum bytes.'; }
            if (($out['min_items'] ?? 0) > ($out['max_items'] ?? 50)) { $errors[] = 'Gallery minimum cannot exceed maximum.'; }
            if (array_key_exists('allowed_mimes', $field)) {
                $raw = $field['allowed_mimes']; if (is_string($raw)) { $raw = trim($raw) === '' ? array() : preg_split('/\r?\n/', trim($raw)); }
                if (!is_array($raw) || array_values($raw) !== $raw || count($raw) > 20) { $errors[] = 'Choose up to 20 exact MIME types.'; }
                else { $out['allowed_mimes'] = array(); foreach ($raw as $mime) { if (!is_string($mime) || !preg_match('~^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$~D', trim($mime))) { $errors[] = 'Use exact lowercase MIME types, not extensions or wildcards.'; } else { $out['allowed_mimes'][] = trim($mime); } } $out['allowed_mimes'] = array_values(array_unique($out['allowed_mimes'])); }
            }
            if (array_key_exists('preview_size', $field)) {
                if (!in_array($field['preview_size'], array('thumbnail', 'medium', 'large', 'full'), true)) { $errors[] = 'Choose a supported media preview size.'; } else { $out['preview_size'] = $field['preview_size']; }
            }
        }
        if ((self::media($field) || $type === 'link') && array_key_exists('return_format', $field)) {
            if (!in_array($field['return_format'], $type === 'link' ? array('array', 'url') : array('id', 'url', 'array'), true)) { $errors[] = 'Choose a supported media/link return format.'; } else { $out['return_format'] = $field['return_format']; }
        }
        return $out;
    }
    public static function link_default($raw, &$errors) {
        if ($raw === '' || $raw === array()) { return array(); }
        $value = is_array($raw) ? Alify_CF_Link::clean(array_merge($raw, array('__complete' => '1'))) : '';
        if ($value === '') { $errors[] = 'Link default must be a valid HTTP(S) URL/title/target object.'; return array(); }
        return $value;
    }
    public static function constrain($field, $value) {
        if (!self::media($field) || $value === '' || $value === array()) { return $value; }
        $ids = $field['type'] === 'gallery' ? $value : array($value);
        if ($field['type'] === 'gallery' && (count($ids) < ($field['min_items'] ?? 0) || count($ids) > ($field['max_items'] ?? 50))) { return ''; }
        foreach ($ids as $id) {
            $post = get_post((int) $id);
            if (!empty($field['allowed_mimes']) && (!$post || !in_array($post->post_mime_type, $field['allowed_mimes'], true))) { return ''; }
            if (!empty($field['min_bytes']) || !empty($field['max_bytes'])) {
                // Read only a local attached file; never fetch remote URLs for size checks.
                $path = get_attached_file((int) $id, true); $bytes = $path && strpos($path, '://') === false && is_file($path) ? filesize($path) : false;
                if ($bytes === false || $bytes < ($field['min_bytes'] ?? 0) || (!empty($field['max_bytes']) && $bytes > $field['max_bytes'])) { return ''; }
            }
        }
        return $value;
    }
    public static function format($field, $value) {
        if ($value === null || $value === '' || $value === array()) { return $value; }
        if ($field['type'] === 'link') { return ($field['return_format'] ?? 'array') === 'url' ? (is_array($value) ? ($value['url'] ?? '') : '') : $value; }
        $mode = $field['return_format'] ?? 'id'; if ($mode === 'id') { return $value; }
        $one = function($id) use ($mode) {
            $post = is_scalar($id) ? get_post((int) $id) : null;
            if (!$post || $post->post_type !== 'attachment' || $post->post_status === 'trash') { return null; }
            $url = wp_get_attachment_url((int) $id); if (!$url) { return null; }
            return $mode === 'url' ? $url : array('id' => (int) $id, 'url' => $url, 'title' => $post->post_title, 'mime' => $post->post_mime_type);
        };
        if ($field['type'] !== 'gallery') { return $one($value); }
        $out = array(); foreach (is_array($value) ? $value : array() as $id) { $item = $one($id); if ($item !== null) { $out[] = $item; } } return $out;
    }
    public static function attributes($field) {
        return ' data-mimes="' . esc_attr(implode(',', $field['allowed_mimes'] ?? array())) . '" data-preview-size="' . esc_attr($field['preview_size'] ?? 'thumbnail') . '"';
    }
}
