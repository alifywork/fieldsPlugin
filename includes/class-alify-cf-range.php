<?php
if (!defined('ABSPATH')) { exit; }

/** Exact thousandth-unit arithmetic, bounded to fit even 32-bit integer differences. */
final class Alify_CF_Range {
    public static function units($raw) {
        if ((!is_string($raw) && !is_int($raw) && !is_float($raw)) || !preg_match('/^-?(?:0|[1-9][0-9]{0,6})(?:\.[0-9]{1,3})?$/D', (string) $raw)) { return null; }
        $text = (string) $raw; $parts = explode('.', ltrim($text, '-'));
        if ((int) $parts[0] > 1000000 || ((int) $parts[0] === 1000000 && (int) ($parts[1] ?? 0) !== 0)) { return null; }
        $units = (int) $parts[0] * 1000 + (int) str_pad($parts[1] ?? '', 3, '0');
        return strpos($text, '-') === 0 ? -$units : $units;
    }
    public static function decimal($units) {
        $absolute = abs($units); $fraction = rtrim(str_pad((string) ($absolute % 1000), 3, '0', STR_PAD_LEFT), '0');
        return ($units < 0 ? '-' : '') . intdiv($absolute, 1000) . ($fraction !== '' ? '.' . $fraction : '');
    }
    public static function settings($raw, &$errors) {
        $out = array(); $units = array();
        foreach (array('min' => '0', 'max' => '100', 'step' => '1') as $key => $default) {
            $units[$key] = self::units(array_key_exists($key, $raw) ? $raw[$key] : $default);
            if ($units[$key] === null) { $errors[] = 'Range settings require plain decimals within ±1000000, with at most three decimal places.'; return array(); }
            $out[$key] = self::decimal($units[$key]);
        }
        if ($units['min'] >= $units['max'] || $units['step'] <= 0 || $units['step'] > $units['max'] - $units['min']) { $errors[] = 'Range requires minimum < maximum and 0 < step ≤ span.'; return array(); }
        return $out;
    }
    public static function clean($field, $raw) {
        $errors = array(); $settings = self::settings($field, $errors);
        if (!$settings) { return ''; }
        $value = self::units($raw);
        $min = self::units($settings['min']); $max = self::units($settings['max']); $step = self::units($settings['step']);
        if ($value === null || $value < $min || $value > $max || ($value - $min) % $step !== 0) { return ''; }
        return self::decimal($value);
    }
    public static function render($field, $value, $id, $input, $error_id) {
        $errors = array(); $settings = self::settings($field, $errors);
        if (!$settings) { echo '<p role="alert">Invalid Range settings. Correct the field configuration.</p>'; return; }
        $attributes = '';
        foreach ($settings as $key => $setting) { $attributes .= ' ' . $key . '="' . esc_attr($setting) . '"'; }
        $description = $id . '-help' . ($error_id ? ' ' . $error_id : '');
        $text = is_scalar($value) ? (string) $value : '';
        $valid = self::clean($field, $text);
        echo '<div class="alify-range"><input type="number" data-range-number id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" value="' . esc_attr($text) . '"' . $attributes . ' aria-describedby="' . esc_attr($description) . '"' . ($error_id ? ' aria-invalid="true"' : '') . (!empty($field['required']) ? ' required' : '') . '><input type="range" data-range-slider id="' . esc_attr($id . '-slider') . '" value="' . esc_attr($valid !== '' ? $valid : $settings['min']) . '"' . $attributes . ' aria-label="' . esc_attr($field['label'] . ': slider') . '" aria-describedby="' . esc_attr($description) . '"' . ($valid === '' ? ' aria-valuetext="No value selected"' : '') . '>';
        if (empty($field['required'])) { echo '<button type="button" class="button" data-range-clear>Clear value</button>'; }
        echo '<p class="description">Blank numeric input means no value. Moving the slider selects a value. Step is measured from the minimum; maximum is selectable only when aligned.</p></div>';
    }
}
