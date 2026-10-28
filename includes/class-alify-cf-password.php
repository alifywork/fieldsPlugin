<?php
if (!defined('ABSPATH')) { exit; }

/** UI masking only: plain recoverable metadata, never authentication or a secret vault. */
final class Alify_CF_Password {
    const MAX_BYTES = 1024;

    public static function clean($raw) {
        if (!is_string($raw) || strlen($raw) > self::MAX_BYTES || preg_match('//u', $raw) !== 1 || preg_match('/\p{Cc}/u', $raw)) { return ''; }
        // Do not trim, strip tags, decode entities, or otherwise change the supplied value.
        return $raw;
    }
    public static function contains($field) {
        if (($field['type'] ?? '') === 'password') { return true; }
        foreach ($field['sub_fields'] ?? array() as $sub) { if (self::contains($sub)) { return true; } }
        foreach ($field['layouts'] ?? array() as $layout) { if (self::contains($layout)) { return true; } }
        return false;
    }
    public static function render($field, $value, $id, $input, $error_id) {
        echo '<div class="alify-password"><input type="password" data-password-input id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" value="' . esc_attr(self::clean($value)) . '" autocomplete="new-password" autocapitalize="off" spellcheck="false" maxlength="1024" aria-describedby="' . esc_attr($id . '-help ' . $id . '-warning' . ($error_id ? ' ' . $error_id : '')) . '"' . (!empty($field['required']) ? ' required' : '') . ($error_id ? ' aria-invalid="true"' : '') . '><button type="button" class="button" data-password-toggle aria-pressed="false">Show value</button>';
        if (empty($field['required'])) { echo '<button type="button" class="button" data-password-clear>Clear value</button>'; }
        echo '<p class="description" id="' . esc_attr($id . '-warning') . '">Masking only. Saved as plaintext, available in the editor HTML and PHP getters. Not for account passwords, API keys or other secrets. Maximum 1024 UTF-8 bytes; no control characters.</p></div>';
    }
}
