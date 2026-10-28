<?php
if (!defined('ABSPATH')) { exit; }

/** Floating wall-clock date/time, never an implicit UTC instant or timezone conversion. */
final class Alify_CF_Date_Time {
    public static function clean($raw) {
        if (!is_string($raw) || !preg_match('/^([1-9][0-9]{3})-(0[1-9]|1[0-2])-([0-2][0-9]|3[01])[T ]([01][0-9]|2[0-3]):([0-5][0-9])(?::([0-5][0-9]))?$/D', $raw, $parts)) { return ''; }
        if (!checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) { return ''; }
        return $parts[1] . '-' . $parts[2] . '-' . $parts[3] . ' ' . $parts[4] . ':' . $parts[5] . ':' . ($parts[6] ?? '00');
    }
    public static function render($field, $value, $id, $input, $error_id) {
        $value = self::clean($value);
        echo '<div class="alify-date-time"><input type="datetime-local" id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" value="' . esc_attr(str_replace(' ', 'T', $value)) . '" min="1000-01-01T00:00" max="9999-12-31T23:59:59" step="1" aria-describedby="' . esc_attr($id . '-help ' . $id . '-timezone' . ($error_id ? ' ' . $error_id : '')) . '"' . (!empty($field['required']) ? ' required' : '') . ($error_id ? ' aria-invalid="true"' : '') . '>';
        if (empty($field['required'])) { echo '<button type="button" class="button" data-date-time-clear>Clear value</button>'; }
        echo '<p class="description" id="' . esc_attr($id . '-timezone') . '">Local wall-clock date/time; no timezone or UTC conversion. Seconds are stored; omitted seconds become 00. Changing the site timezone does not change this value.</p></div>';
    }
}
