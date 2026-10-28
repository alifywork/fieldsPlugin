<?php
if (!defined('ABSPATH')) { exit; }

/** Independent sibling scope per Group or content row; origin MACs prevent index-based retention. */
final class Alify_CF_Scoped_Conditions {
    public static function enabled($fields) { foreach ($fields as $field) { if (!empty($field['condition'])) { return true; } } return false; }
    public static function validate($fields, &$errors) {
        foreach ($fields as $field) { foreach (Alify_CF_Conditions::rules($field['condition'] ?? null) as $rule) { $source = null; foreach ($fields as $candidate) { if ($candidate['key'] === ($rule['field'] ?? null)) { $source = $candidate; break; } } if (!$source || !Alify_CF_Conditions::source($source) || $source['key'] === $field['key']) { $errors[] = 'Subfield conditions need eligible local siblings, without self-reference.'; } } } Alify_CF_Conditions::graph($fields, $errors);
    }
    public static function values($fields, $row) { $values = array(); foreach ($fields as $sub) { $values[$sub['key']] = Alify_CF_Conditions::source($sub) ? Alify_CF::clean_value($sub, $row[$sub['name']] ?? '') : ''; } return $values; }
    public static function origin($row) { $json = is_array($row) ? wp_json_encode($row) : false; return is_string($json) && strlen($json) <= 1048576 ? hash_hmac('sha256', $json, wp_salt('nonce')) : ''; }
    public static function attributes($sub, $fields, $row) {
        $values = array(); foreach ($fields as $field) { $values[$field['key']] = is_array($row) && array_key_exists($field['name'], $row) ? $row[$field['name']] : $field['default']; }
        return ' data-field-key="' . esc_attr($sub['key']) . '" data-field-type="' . esc_attr($sub['type']) . '" data-condition="' . esc_attr(wp_json_encode($sub['condition'] ?? null)) . '"' . (Alify_CF_Conditions::visible($sub, $fields, $values) ? '' : ' hidden');
    }
    public static function previous($field, $row, $old, &$issue) {
        if (($field['type'] ?? '') === 'group') { return is_array($old[0] ?? null) ? $old[0] : array(); }
        if (!array_key_exists('__row_origin', $row)) { return null; } // PHP canonical callers can retain explicitly unchanged values.
        $token = $row['__row_origin']; if ($token === '') { return array(); }
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) { $issue = 'Invalid row origin. Reload the editor.'; return false; }
        foreach (is_array($old) ? $old : array() as $saved) { if (is_array($saved) && hash_equals(self::origin($saved), $token)) { return $saved; } }
        $issue = 'Row origin changed or is unavailable. Reload; previous container retained.'; return false;
    }
    /** Explicit API writes reject changed hidden children; editor submissions only ignore them. */
    public static function audit($field, $payload, $canonical) {
        if (!is_array($payload)) { return true; }
        if ($field['type'] === 'flexible') { foreach (array_values($payload['rows'] ?? array()) as $index => $row) { $layout = Alify_CF_Flexible::layout($field, $row['layout'] ?? ''); if ($layout && !self::audit(array('type' => 'group', 'sub_fields' => $layout['sub_fields']), array('fields' => $row['fields'] ?? array()), $canonical[$index]['fields'] ?? array())) { return false; } } return true; }
        if (!in_array($field['type'], array('group', 'repeater'), true) || !self::enabled($field['sub_fields'])) { return true; }
        $rows = $field['type'] === 'group' ? array($payload['fields'] ?? array()) : array_values($payload['rows'] ?? array()); $clean = $field['type'] === 'group' ? array($canonical) : $canonical;
        foreach ($rows as $index => $row) { $values = self::values($field['sub_fields'], $row); foreach ($field['sub_fields'] as $sub) { if (!Alify_CF_Conditions::visible($sub, $field['sub_fields'], $values) && array_key_exists($sub['name'], $row) && $row[$sub['name']] !== Alify_CF_Publication::stored_payload($sub, $clean[$index][$sub['name']] ?? $sub['default'])) { return false; } } } return true;
    }
    public static function retained($sub, $row, $previous, $old, &$issue) {
        if (is_array($previous)) { return $previous[$sub['name']] ?? $sub['default']; }
        foreach (is_array($old) ? $old : array() as $saved) { if (is_array($saved) && array_key_exists($sub['name'], $row) && $row[$sub['name']] === Alify_CF_Publication::stored_payload($sub, $saved[$sub['name']] ?? $sub['default'])) { return $saved[$sub['name']] ?? $sub['default']; } }
        if (!$old) { return $sub['default']; }
        $issue = 'Hidden subfield requires an original row token or an explicitly unchanged canonical value; no positional guessing.'; return null;
    }
}
