<?php
if (!defined('ABSPATH')) { exit; }

/** Short-lived, user-scoped editing feedback, never frontend field data. */
final class Alify_CF_Feedback {
    private static function key($context) { return 'alify_cf_feedback_' . get_current_user_id() . '_' . $context; }

    public static function get($context) {
        $feedback = get_transient(self::key($context));
        return is_array($feedback) ? $feedback : array();
    }
    public static function put($context, $groups) {
        if ($groups && strlen(wp_json_encode($groups)) > 262144) {
            foreach ($groups as &$group) { $group['values'] = array(); $group['omitted'] = true; }
            unset($group);
        }
        if ($groups) { set_transient(self::key($context), $groups, 600); }
        else { delete_transient(self::key($context)); }
    }

    public static function group($schema, $values, $previous, $errors) {
        $display = array(); $submitted = array();
        foreach ($schema as $field) { $submitted[$field['key']] = Alify_CF::clean_value($field, $values[$field['name']] ?? ''); }
        foreach ($schema as $field) {
            $name = $field['name'];
            $old = $previous[$name] ?? $field['default'];
            $display[$name] = Alify_CF::visible($field, $schema, $submitted) ? self::display($field, $values[$name] ?? '', $old) : $old;
        }
        return array('values' => $display, 'errors' => $errors, 'schema' => hash('sha256', wp_json_encode($schema)));
    }

    private static function text($raw, $multiline = false) {
        if (!is_scalar($raw)) { return ''; }
        $raw = substr((string) $raw, 0, 10000);
        return $multiline ? sanitize_textarea_field($raw) : sanitize_text_field($raw);
    }

    private static function display($field, $raw, $previous, $allowed_media = array()) {
        if ($field['type'] === 'repeater') {
            if (!is_array($raw) || ($raw['__complete'] ?? '') !== '1' || !is_array($raw['rows'] ?? array()) || count($raw['rows'] ?? array()) > 50) { return $previous; }
            foreach ((array) $previous as $old_row) {
                if (!is_array($old_row)) { continue; }
                foreach ($field['sub_fields'] as $sub) {
                    if (in_array($sub['type'], array('image', 'file', 'gallery'), true)) {
                        foreach ((array) ($old_row[$sub['name']] ?? array()) as $id) { if (is_scalar($id)) { $allowed_media[] = (string) $id; } }
                    }
                }
            }
            $rows = array();
            foreach (($raw['rows'] ?? array()) as $row) {
                if (!is_array($row) || ($row['__row_complete'] ?? '') !== '1') { return $previous; }
                $out = array();
                foreach ($field['sub_fields'] as $sub) {
                    if ($sub['type'] === 'gallery' && (!is_array($row[$sub['name']] ?? null) || ($row[$sub['name']]['__complete'] ?? '') !== '1')) { return $previous; }
                    $out[$sub['name']] = self::display($sub, $row[$sub['name']] ?? '', $sub['default'], $allowed_media);
                }
                $rows[] = $out;
            }
            return $rows;
        }
        if ($field['type'] === 'gallery') {
            if (!is_array($raw) || ($raw['__complete'] ?? '') !== '1' || !is_array($raw['ids'] ?? array()) || count($raw['ids'] ?? array()) > 50) { return $previous; }
            $ids = array();
            foreach (($raw['ids'] ?? array()) as $id) {
                if ((is_int($id) || is_string($id)) && preg_match('/^[1-9][0-9]*$/', (string) $id) && (string) (int) $id === (string) $id) {
                    $old_ids = array_merge(is_array($previous) ? $previous : array(), $allowed_media);
                    if (in_array((string) $id, array_map('strval', $old_ids), true) || current_user_can('edit_post', (int) $id)) { $ids[] = (int) $id; }
                }
            }
            return array_values(array_unique($ids));
        }
        if (in_array($field['type'], array('image', 'file'), true) && is_scalar($raw) && (string) $raw !== '' && (string) $raw !== (string) $previous && !in_array((string) $raw, $allowed_media, true) && !current_user_can('edit_post', (int) $raw)) { return $previous; }
        if (in_array($field['type'], array('checkbox', 'true_false'), true)) { return Alify_CF::clean_value($field, $raw); }
        return self::text($raw, $field['type'] === 'textarea');
    }
}
