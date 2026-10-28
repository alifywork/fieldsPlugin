<?php
if (!defined('ABSPATH')) { exit; }

/** Bounded presentation formats; storage/validation/conditions remain canonical. */
final class Alify_CF_Temporal_Format {
    public static function formats($type) {
        $all = array(
            'date' => array('Y-m-d', 'd/m/Y', 'm/d/Y', 'j M Y', 'F j, Y'),
            'time' => array('H:i', 'H:i:s', 'g:i a', 'g:i A', 'g:i:s A'),
            'date_time' => array('Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i', 'm/d/Y g:i A', 'j M Y g:i A'),
        );
        return $all[$type] ?? array();
    }
    public static function settings($field, &$errors) {
        $formats = self::formats($field['type']); $out = array();
        if (!$formats) { return $out; }
        foreach (array('display_format', 'return_format') as $key) {
            if (!array_key_exists($key, $field)) { continue; } // Legacy schemas retain their exact shape.
            if (!is_string($field[$key]) || !in_array($field[$key], $formats, true)) { $errors[] = 'Choose a supported ' . $key . ' for ' . $field['type'] . '.'; continue; }
            $out[$key] = $field[$key];
        }
        return $out;
    }
    public static function selected($field, $key) {
        $formats = self::formats($field['type']);
        return in_array($field[$key] ?? null, $formats, true) ? $field[$key] : ($formats[0] ?? '');
    }
    public static function format($field, $value, $key = 'return_format') {
        if ($value === null || $value === '') { return $value; }
        if (!is_string($value) || strpos($value, chr(0)) !== false) { return ''; }
        $type = $field['type']; $canonical = array('date' => 'Y-m-d', 'time' => 'H:i', 'date_time' => 'Y-m-d H:i:s');
        if (!isset($canonical[$type])) { return $value; }
        if ($type === 'date_time') { $value = Alify_CF_Date_Time::clean($value); if ($value === '') { return ''; } }
        // UTC is only a neutral calendar carrier: no timezone tokens or conversion are exposed.
        $date = DateTimeImmutable::createFromFormat('!' . $canonical[$type], $value, new DateTimeZone('UTC'));
        if (!$date || $date->format($canonical[$type]) !== $value) { return ''; }
        return $date->format(self::selected($field, $key));
    }
    public static function field($field, $value) {
        if (Alify_CF_Reference_Settings::supported($field)) { return Alify_CF_Reference_Settings::format($field, $value); }
        if (Alify_CF_Field_Settings::media($field) || $field['type'] === 'link') { return Alify_CF_Field_Settings::format($field, $value); }
        if (Alify_CF_Choice::supported($field['type'])) { return Alify_CF_Choice::format($field, $value); }
        if (self::formats($field['type'])) { return self::format($field, $value); }
        if (!is_array($value)) { return $value; }
        if ($field['type'] === 'group') {
            foreach ($field['sub_fields'] as $sub) { if (array_key_exists($sub['name'], $value)) { $value[$sub['name']] = self::field($sub, $value[$sub['name']]); } }
        } elseif ($field['type'] === 'repeater') {
            foreach ($value as &$row) { if (is_array($row)) { foreach ($field['sub_fields'] as $sub) { if (array_key_exists($sub['name'], $row)) { $row[$sub['name']] = self::field($sub, $row[$sub['name']]); } } } } unset($row);
        } elseif ($field['type'] === 'flexible') {
            foreach ($value as &$row) {
                $layout = is_array($row) ? Alify_CF_Flexible::layout($field, $row['layout'] ?? '') : null;
                if ($layout && is_array($row['fields'] ?? null)) { $layout['type'] = 'group'; $row['fields'] = self::field($layout, $row['fields']); }
            } unset($row);
        }
        return $value;
    }
    public static function location($location, $name, $value) {
        $matches = array();
        foreach (Alify_CF::groups($location) as $group) { foreach (Alify_CF::schema($group->ID) as $field) { if ($field['name'] === $name) { $matches[] = $field; } } }
        return count($matches) === 1 ? self::field($matches[0], $value) : $value;
    }
    public static function attributes($field) {
        return ' data-temporal-type="' . esc_attr($field['type']) . '" data-display-format="' . esc_attr(self::selected($field, 'display_format')) . '"';
    }
    public static function preview($field, $value) {
        echo '<span class="alify-temporal-display">Display: <output data-temporal-preview aria-live="polite">' . esc_html(self::format($field, $value, 'display_format') ?? '') . '</output></span>';
    }
    public static function render($field, $value, $id, $input, $error_id) {
        echo '<div class="alify-temporal"' . self::attributes($field) . '><input data-temporal-input type="' . esc_attr($field['type']) . '" id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" value="' . esc_attr(is_string($value) ? $value : '') . '"' . ($field['type'] === 'time' ? ' step="60"' : '') . ' aria-describedby="' . esc_attr($id . '-help' . ($error_id ? ' ' . $error_id : '')) . '"' . ($error_id ? ' aria-invalid="true"' : '') . (!empty($field['required']) ? ' required' : '') . '>';
        self::preview($field, $value); echo '</div>';
    }
}
