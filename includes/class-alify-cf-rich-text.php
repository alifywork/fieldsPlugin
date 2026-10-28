<?php
if (!defined('ABSPATH')) { exit; }

/** Restricted post HTML, independent of unfiltered_html; no shortcode execution. */
final class Alify_CF_Rich_Text {
    public static function clean($raw) {
        if (!is_string($raw)) { return ''; }
        $html = wp_kses_post($raw);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Empty editor paragraphs, NBSP and invisible spacing do not satisfy required fields.
        $text = preg_replace('/[\s\p{Z}\x{200B}\x{FEFF}]+/u', '', $text);
        return $text !== null && $text !== '' ? $html : '';
    }
    public static function assets() {
        wp_enqueue_editor();
        wp_enqueue_script('alify-cf-rich-text', plugins_url('assets/rich-text.js', ALIFY_CF_FILE), array('editor'), ALIFY_CF_VERSION, true);
    }
}
