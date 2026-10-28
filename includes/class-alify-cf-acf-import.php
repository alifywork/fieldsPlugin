<?php
if (!defined('ABSPATH')) { exit; }

/** Configuration adapter only. Never reads ACF values or executes exported callbacks. */
final class Alify_CF_ACF_Import {
    private static function error($path, $message, &$errors) { $errors[] = $path . ': ' . $message; }
    public static function inspect($json) {
        if (!current_user_can('manage_options')) { return new WP_Error('alify_forbidden', 'Administrator permission required.'); }
        if (!is_string($json) || strlen($json) > 1048576) { return new WP_Error('alify_acf_size', 'Provide at most 1 MiB of ACF schema JSON.'); }
        $raw = json_decode($json, true, 24); if (json_last_error() !== JSON_ERROR_NONE || !is_array($raw)) { return new WP_Error('alify_acf_json', 'Invalid schema JSON.'); }
        if (array_values($raw) === $raw) { if (count($raw) !== 1 || !is_array($raw[0])) { return new WP_Error('alify_acf_groups', 'Import exactly one group at a time; no partial multi-group imports.'); } $raw = $raw[0]; }
        $errors = array(); $warnings = array();
        foreach ($raw as $key => $value) { if (!in_array($key, array('key', 'title', 'fields', 'location', 'active', 'menu_order', 'position', 'style', 'label_placement', 'instruction_placement', 'hide_on_screen', 'description', 'show_in_rest', 'modified'), true)) { self::error('group.' . $key, 'Unsupported group setting.', $errors); } }
        if (!is_string($raw['title'] ?? null) || trim(sanitize_text_field($raw['title'])) === '' || strlen($raw['title']) > 200) { self::error('group.title', 'A bounded title is required.', $errors); }
        foreach (array('show_in_rest', 'active') as $flag) { if (isset($raw[$flag]) && !in_array($raw[$flag], array(0, 1, false, true, '0', '1'), true)) { self::error('group.' . $flag, 'Expected boolean.', $errors); } }
        if (!empty($raw['show_in_rest'])) { self::error('group.show_in_rest', 'REST exposure is not imported; review explicit Alify exposure settings separately.', $errors); }
        foreach (array('menu_order', 'position', 'style', 'label_placement', 'instruction_placement', 'hide_on_screen', 'description', 'active') as $setting) { if (array_key_exists($setting, $raw)) { $warnings[] = 'group.' . $setting . ': source presentation/status is not deployed; the destination is a new draft.'; } }
        $location = null; $rules = array(); $locations = $raw['location'] ?? null;
        if (!is_array($locations) || !$locations || array_values($locations) !== $locations || count($locations) > 10) { self::error('group.location', 'Need 1–10 OR groups, each with a post_type equality base.', $errors); }
        else { foreach ($locations as $index => $clauses) {
            $base = null; $predicates = array(); if (!is_array($clauses) || !$clauses || array_values($clauses) !== $clauses || count($clauses) > 10) { self::error('location.' . $index, 'Need 1–10 AND clauses.', $errors); continue; }
            foreach ($clauses as $clause) {
                if (!is_array($clause) || array_diff(array_keys($clause), array('param', 'operator', 'value')) || !is_string($clause['param'] ?? null) || !is_string($clause['value'] ?? null) || !in_array($clause['operator'] ?? '', array('==', '!='), true)) { self::error('location.' . $index, 'Malformed clause.', $errors); continue; }
                if ($clause['param'] === 'post_type') { if ($base !== null || $clause['operator'] !== '==') { self::error('location.' . $index, 'Exactly one equality post_type base is supported.', $errors); } $base = $clause['value']; }
                else { $map = array('post' => 'post_id', 'post_status' => 'status', 'page_template' => 'template', 'current_user_role' => 'current_user_role'); if (!isset($map[$clause['param']])) { self::error('location.' . $index . '.' . $clause['param'], 'Unsupported predicate; no flattening or silent broadening.', $errors); } else { $predicates[] = array('param' => $map[$clause['param']], 'operator' => $clause['operator'], 'value' => $clause['value']); } }
            }
            if ($base === null || ($location !== null && $base !== $location)) { self::error('location.' . $index, 'All OR groups must use the same explicit post_type.', $errors); } else { $location = $base; }
            $rules[] = $predicates;
        } }
        // An unconditional OR branch makes predicates redundant, exactly as in the source.
        if (in_array(array(), $rules, true)) { $rules = array(); }
        $fields = self::fields($raw['fields'] ?? null, 'fields', 0, $errors, $warnings);
        if (!$errors) { $rule_errors = array(); $rules = Alify_CF_Location_Rules::clean($rules, $rule_errors); $fields = Alify_CF::validate_schema($fields, $location, 0, $rule_errors); foreach ($rule_errors as $message) { self::error('schema', $message, $errors); } }
        return array('valid' => !$errors, 'errors' => $errors, 'warnings' => $warnings, 'group' => $errors ? null : array('title' => sanitize_text_field($raw['title']), 'location' => $location, 'rules' => $rules, 'fields' => $fields));
    }
    private static function fields($raw, $path, $depth, &$errors, &$warnings) {
        if (!is_array($raw) || !$raw || array_values($raw) !== $raw || count($raw) > ($depth ? 20 : 200)) { self::error($path, 'Invalid field list or field count.', $errors); return array(); }
        $types = array('text' => 'text', 'textarea' => 'textarea', 'email' => 'email', 'url' => 'url', 'number' => 'number', 'true_false' => 'true_false', 'select' => 'select', 'checkbox' => 'checkbox', 'radio' => 'radio', 'button_group' => 'button_group', 'color_picker' => 'color', 'group' => 'group', 'repeater' => 'repeater'); $out = array();
        foreach ($raw as $index => $field) {
            $here = $path . '.' . $index;
            if (!is_array($field) || !is_string($field['type'] ?? null) || !isset($types[$field['type']])) { self::error($here, 'Unsupported/malformed field type.', $errors); continue; }
            foreach (array('key', 'name', 'label') as $required_key) { if (!is_string($field[$required_key] ?? null) || trim(sanitize_text_field($field[$required_key])) === '') { self::error($here . '.' . $required_key, 'A nonempty string is required.', $errors); } }
            if (array_key_exists('default_value', $field) && !is_scalar($field['default_value']) && !in_array($field['type'], array('checkbox', 'select'), true)) { self::error($here . '.default_value', 'Malformed default value.', $errors); }
            $type = $types[$field['type']]; $allowed = array('key', 'label', 'name', 'type', 'instructions', 'required', 'default_value', 'conditional_logic', 'wrapper', 'aria-label', 'allow_in_bindings');
            $extras = array('text' => array('placeholder', 'prepend', 'append', 'maxlength'), 'textarea' => array('placeholder', 'rows', 'maxlength', 'new_lines'), 'email' => array('placeholder', 'prepend', 'append'), 'url' => array('placeholder'), 'number' => array('placeholder', 'prepend', 'append', 'min', 'max', 'step'), 'true_false' => array('message', 'ui', 'ui_on_text', 'ui_off_text'), 'select' => array('choices', 'multiple', 'allow_null', 'ui', 'ajax', 'return_format'), 'checkbox' => array('choices', 'layout', 'toggle', 'return_format', 'allow_custom', 'save_custom'), 'radio' => array('choices', 'layout', 'allow_null', 'other_choice', 'save_other_choice', 'return_format'), 'button_group' => array('choices', 'layout', 'allow_null', 'return_format'), 'color' => array('enable_opacity', 'return_format'), 'group' => array('layout', 'sub_fields'), 'repeater' => array('layout', 'min', 'max', 'button_label', 'collapsed', 'pagination', 'rows_per_page', 'sub_fields'));
            foreach ($field as $key => $value) { if (!in_array($key, array_merge($allowed, $extras[$type]), true)) { self::error($here . '.' . $key, 'Unsupported setting.', $errors); } }
            foreach (array('allow_in_bindings', 'multiple', 'allow_null', 'ui', 'ajax', 'allow_custom', 'save_custom', 'other_choice', 'save_other_choice', 'toggle', 'enable_opacity', 'pagination') as $flag) { if (isset($field[$flag]) && !in_array($field[$flag], array(0, 1, false, true, '0', '1'), true)) { self::error($here . '.' . $flag, 'Expected boolean.', $errors); } }
            foreach (array('allow_in_bindings', 'conditional_logic', 'maxlength', 'new_lines', 'ajax', 'allow_custom', 'save_custom', 'other_choice', 'save_other_choice', 'toggle', 'enable_opacity') as $key) { if (!empty($field[$key])) { self::error($here . '.' . $key, 'Non-default behavior is not supported by this adapter.', $errors); } }
            if ($type === 'number') { foreach (array('min', 'max', 'step') as $key) { if (isset($field[$key]) && $field[$key] !== '') { self::error($here . '.' . $key, 'Numeric bounds/step are not mapped to a different field type.', $errors); } } }
            foreach (array('placeholder', 'prepend', 'append', 'rows', 'wrapper', 'aria-label', 'message', 'ui', 'ui_on_text', 'ui_off_text', 'layout', 'button_label', 'collapsed') as $key) { if (!empty($field[$key])) { $warnings[] = $here . '.' . $key . ': source editor presentation is not reproduced.'; } }
            if (isset($field['required']) && !in_array($field['required'], array(0, 1, false, true, '0', '1'), true)) { self::error($here . '.required', 'Expected boolean.', $errors); }
            $target = array('key' => $field['key'] ?? '', 'name' => $field['name'] ?? '', 'label' => $field['label'] ?? '', 'type' => $type, 'instructions' => $field['instructions'] ?? '', 'required' => !empty($field['required']), 'default' => $field['default_value'] ?? '');
            if ($target['default'] === false && $type !== 'true_false') { $target['default'] = ''; }
            if ($type === 'true_false') { if (!in_array($target['default'], array('', 0, 1, false, true, '0', '1'), true)) { self::error($here . '.default_value', 'Expected boolean default.', $errors); } $target['default'] = !empty($target['default']) ? '1' : '0'; }
            if (in_array($type, array('select', 'radio', 'checkbox', 'button_group'), true)) {
                $choices = $field['choices'] ?? null; $lines = array(); if (!is_array($choices) || !$choices || count($choices) > 100) { self::error($here . '.choices', 'Need 1–100 static key/label choices.', $errors); }
                else { foreach ($choices as $key => $label) { if (!is_string($label) || strlen((string) $key) > 64 || trim((string) $key) !== (string) $key || (string) $key === '' || strpbrk((string) $key, ":\r\n") !== false || strpbrk($label, "\r\n") !== false || sanitize_text_field($label) !== $label) { self::error($here . '.choices', 'Choice keys/labels cannot be represented losslessly.', $errors); } else { $lines[] = $key . ' : ' . $label; } } } $target['choices'] = implode("\n", $lines);
                $format = $field['return_format'] ?? 'value'; if (!in_array($format, array('value', 'label', 'array'), true)) { self::error($here . '.return_format', 'Unsupported choice return format.', $errors); } else { $target['return_format'] = $format; }
                if ($type === 'select') { if (!in_array($field['multiple'] ?? 0, array(0, 1, false, true, '0', '1'), true)) { self::error($here . '.multiple', 'Expected boolean.', $errors); } $target['multiple'] = !empty($field['multiple']); }
                if ($type === 'select' && !$target['multiple'] && is_array($target['default'])) { if (array_values($target['default']) !== $target['default'] || count($target['default']) > 1) { self::error($here . '.default_value', 'Single Select permits at most one default key.', $errors); } else { $target['default'] = $target['default'] ? $target['default'][0] : ''; } }
                if (!$target['required'] && empty($field['allow_null']) && $type !== 'checkbox') { $warnings[] = $here . '.allow_null: optional Alify choices can be cleared; null/UI behavior is not ACF parity.'; }
                $warnings[] = $here . '.return_format: raw getters remain canonical keys; only Alify formatted getters use the configured format.';
            }
            if ($type === 'color' && isset($field['return_format']) && $field['return_format'] !== 'string') { self::error($here . '.return_format', 'Only hex string color is supported.', $errors); }
            if (in_array($type, array('group', 'repeater'), true)) {
                if ($depth) { self::error($here, 'Nested containers are not supported; no flattening.', $errors); continue; }
                if (!empty($field['default_value'])) { self::error($here . '.default_value', 'Container content/default rows are not imported.', $errors); } unset($target['default']);
                $target['sub_fields'] = self::fields($field['sub_fields'] ?? null, $here . '.sub_fields', 1, $errors, $warnings);
                if ($type === 'repeater') { $target['min_rows'] = $field['min'] ?? 0; $target['max_rows'] = empty($field['max']) ? 50 : $field['max']; if (!empty($field['pagination'])) { $target['page_size'] = $field['rows_per_page'] ?? 20; } $warnings[] = $here . ': maximum 50 rows; pagination retains all DOM rows, not ACF lazy loading.'; }
            }
            $out[] = $target;
        } return $out;
    }
    public static function import($json) {
        $report = self::inspect($json); if (is_wp_error($report)) { return $report; } if (!$report['valid']) { return new WP_Error('alify_acf_unsupported', implode("\n", $report['errors']), array('report' => $report)); }
        $group = $report['group']; $transfer = wp_json_encode(array('format' => 'alify-custom-field', 'version' => Alify_CF_Transfer::minimum_version($group['fields'], $group['location'], $group['rules']), 'group' => $group)); $id = Alify_CF_Transfer::import_json($transfer, wp_create_nonce('alify_cf_import'));
        return is_wp_error($id) ? $id : array('group_id' => $id, 'warnings' => $report['warnings']);
    }
}
