<?php
if (!defined('ABSPATH')) { exit; }

/** Single-level, bounded repeaters. Stored rows use local subfield names. */
final class Alify_CF_Repeater {
    const MAX_ROWS = 50;
    const MAX_FIELDS = 20;

    /** Schema limits are strict integers, not silently clamped values. */
    public static function limits($raw, &$errors) {
        $limits = array();
        foreach (array('min_rows' => 0, 'max_rows' => self::MAX_ROWS) as $name => $default) {
            $value = array_key_exists($name, $raw) ? $raw[$name] : $default;
            if ((!is_int($value) && !is_string($value)) || !preg_match('/^(0|[1-9][0-9]?)$/D', (string) $value) || (int) $value > self::MAX_ROWS) { $errors[] = 'Repeater row limits must be whole numbers from 0 to 50.'; return array(); }
            $limits[$name] = (int) $value;
        }
        if ($limits['max_rows'] < 1 || $limits['min_rows'] > $limits['max_rows']) { $errors[] = 'Repeater limits require 0 ≤ minimum ≤ maximum and 1 ≤ maximum ≤ 50.'; return array(); }
        return $limits;
    }
    private static function bounds($field) {
        if (($field['type'] ?? '') !== 'repeater') { return array(0, self::MAX_ROWS); }
        return array(max((int) ($field['min_rows'] ?? 0), !empty($field['required']) ? 1 : 0), (int) ($field['max_rows'] ?? self::MAX_ROWS));
    }

    public static function schema($raw, &$errors, $depth = 1) {
        if (!is_array($raw) || !$raw || count($raw) > self::MAX_FIELDS) {
            $errors[] = 'Groups and repeaters require between 1 and 20 subfields.'; return array();
        }
        $result = array(); $names = array(); $keys = array();
        foreach ($raw as $field) {
            if (!is_array($field)) { $errors[] = 'Invalid repeater subfield.'; continue; }
            foreach (array('name', 'key', 'label', 'type', 'instructions', 'choices') as $prop) {
                if (isset($field[$prop]) && !is_string($field[$prop])) { $errors[] = 'Invalid repeater subfield setting.'; continue 2; }
            }
            $name = $field['name'] ?? ''; $type = $field['type'] ?? 'text';
            $key = sanitize_key($field['key'] ?? '') ?: 'field_' . wp_generate_uuid4();
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $name) || isset($names[$name]) || isset($keys[$key]) || trim($field['label'] ?? '') === '' || !isset(Alify_CF::types()[$type]) || in_array($type, array('tab', 'accordion', 'message'), true)) {
                $errors[] = 'Container subfields need unique names/keys, labels and a supported value type.'; continue;
            }
            $names[$name] = true; $keys[$key] = true;
            $clean = array('name' => $name, 'key' => $key, 'type' => $type, 'label' => sanitize_text_field($field['label']), 'instructions' => sanitize_textarea_field($field['instructions'] ?? ''), 'choices' => sanitize_textarea_field($field['choices'] ?? ''), 'required' => !empty($field['required']));
            $clean = array_merge($clean, Alify_CF_Temporal_Format::settings($field, $errors), Alify_CF_Choice::settings($field, $errors), Alify_CF_Field_Settings::settings($field, $errors), Alify_CF_Reference_Settings::settings($field, $errors), Alify_CF_Selector::settings($field, $errors), Alify_CF_API::settings($field, $errors), Alify_CF_Editor_Fields::settings($field, $errors));
            if ($type === 'range') { $clean = array_merge($clean, Alify_CF_Range::settings($field, $errors)); }
            if (Alify_CF_Composition::container($clean)) {
                if ($depth >= Alify_CF_Composition::DEPTH) { $errors[] = 'At most four container levels are supported.'; continue; }
                if ($type === 'flexible') { $clean['layouts'] = Alify_CF_Flexible::schema($field['layouts'] ?? null, $errors, $depth + 1); }
                else { $clean['sub_fields'] = self::schema($field['sub_fields'] ?? null, $errors, $depth + 1); }
                if ($type === 'repeater' && (isset($field['min_rows']) || isset($field['max_rows']))) { $clean = array_merge($clean, self::limits($field, $errors)); }
                $clean['default'] = array(); if (!empty($field['default'])) { $errors[] = 'Container defaults must be empty.'; }
                if (!empty($field['condition'])) { $clean['condition'] = Alify_CF_Conditions::clean($field['condition'], $errors, $name); }
                $result[] = $clean; continue;
            }
            $clean['default'] = in_array($type, array('post_object', 'password'), true) ? '' : (in_array($type, array('gallery', 'link', 'relationship', 'google_map'), true) ? array() : Alify_CF::clean_value($clean, $field['default'] ?? ''));
            if (Alify_CF_Selector::supported($clean)) { $clean['default'] = Alify_CF_Selector::multiple($clean) ? array() : ''; if (($field['default'] ?? '') !== '' && ($field['default'] ?? array()) !== array()) { $errors[] = 'Selector defaults are not supported.'; } }
            if ($type === 'link') { $clean['default'] = Alify_CF_Field_Settings::link_default($field['default'] ?? '', $errors); }
            if ($type === 'color' && ($field['default'] ?? '') !== '' && $clean['default'] === '') { $errors[] = 'Color default must be valid hex.'; }
            if ($type === 'checkbox' || Alify_CF_Choice::multiple($clean)) { $clean['default'] = Alify_CF_Choice::defaults($clean, $field['default'] ?? '', $errors); }
            if ($type === 'date_time' && ($field['default'] ?? '') !== '' && $clean['default'] === '') { $errors[] = 'Date Time subfield default must be valid.'; }
            if ($type === 'password') { $clean['choices'] = ''; if (($field['default'] ?? '') !== '') { $errors[] = 'Password subfield defaults are not supported.'; } }
            if ($type === 'range' && ($field['default'] ?? '') !== '' && $clean['default'] === '') { $errors[] = 'Range subfield default must be within bounds and aligned to its step.'; }
            if (!empty($field['condition'])) { $clean['condition'] = Alify_CF_Conditions::clean($field['condition'], $errors, $name); }
            $result[] = $clean;
        }
        Alify_CF_Scoped_Conditions::validate($result, $errors);
        return $result;
    }

    /** Invalid/truncated payloads return '', causing the caller to retain all old rows. */
    public static function clean($field, $payload, $old = null, $permissions = false, &$issue = null, &$location = null) {
        $issue = ''; $location = null;
        if (!is_array($payload) || ($payload['__complete'] ?? '') !== '1') { $issue = 'Incomplete submission. Reload the editor.'; return ''; }
        $rows = $payload['rows'] ?? array();
        list($minimum, $maximum) = self::bounds($field);
        if (!is_array($rows) || empty($field['sub_fields'])) { $issue = 'Invalid row structure. Reload the editor.'; return ''; }
        if (count($rows) < $minimum || count($rows) > min($maximum, self::MAX_ROWS)) { $issue = 'Use between ' . $minimum . ' and ' . min($maximum, self::MAX_ROWS) . ' rows; received ' . count($rows) . '.'; return ''; }
        $allowed_media = array(); $allowed_posts = array(); $allowed_selectors = array();
        foreach ((array) $old as $row) {
            if (!is_array($row)) { continue; }
            foreach ($field['sub_fields'] as $sub) {
                if (Alify_CF_Selector::supported($sub)) { foreach (Alify_CF_Selector::multiple($sub) ? (is_array($row[$sub['name']] ?? null) ? $row[$sub['name']] : array()) : array($row[$sub['name']] ?? '') as $saved) { if (is_int($saved) || is_string($saved)) { $allowed_selectors[$sub['name']][] = $saved; } } }
                if ($sub['type'] === 'relationship' || Alify_CF_Reference_Settings::multiple($sub)) { foreach (is_array($row[$sub['name']] ?? null) ? $row[$sub['name']] : array() as $saved_id) { if (is_int($saved_id) || is_string($saved_id)) { $allowed_posts[] = (string) $saved_id; } } }
                if ($sub['type'] === 'post_object' && is_scalar($row[$sub['name']] ?? null)) { $allowed_posts[] = (string) $row[$sub['name']]; }
                if ($sub['type'] === 'gallery') {
                    foreach ((array) ($row[$sub['name']] ?? array()) as $image_id) { if (is_scalar($image_id)) { $allowed_media[] = (string) $image_id; } }
                }
                if (in_array($sub['type'], array('image', 'file'), true) && !empty($row[$sub['name']])) { $allowed_media[] = (string) $row[$sub['name']]; }
            }
        }
        $clean = array();
        $position = 0;
        foreach ($rows as $row) {
            $position++;
            if (!is_array($row) || ($row['__row_complete'] ?? '') !== '1') { $issue = 'Row ' . $position . ': Incomplete row. Reload the editor.'; return ''; }
            $out = array(); $scoped = Alify_CF_Scoped_Conditions::enabled($field['sub_fields']); $values = $scoped ? Alify_CF_Scoped_Conditions::values($field['sub_fields'], $row) : array(); $previous = Alify_CF_Composition::needs_origin($field['sub_fields']) ? Alify_CF_Scoped_Conditions::previous($field, $row, $old, $issue) : null;
            if ($previous === false) { $location = array('row' => $position - 1); return ''; }
            foreach ($field['sub_fields'] as $sub) {
                if ($scoped && !Alify_CF_Conditions::visible($sub, $field['sub_fields'], $values)) { $out[$sub['name']] = Alify_CF_Scoped_Conditions::retained($sub, $row, $previous, $old, $issue); if ($issue !== '') { $location = array('row' => $position - 1, 'subfield' => $sub['name']); return ''; } continue; }
                $raw = $row[$sub['name']] ?? '';
                $value = Alify_CF_Composition::container($sub) ? '' : (($sub['type'] === 'relationship' || Alify_CF_Reference_Settings::multiple($sub)) ? Alify_CF_Relationship::clean($raw, $allowed_posts, $permissions, $sub) : ($sub['type'] === 'gallery' ? Alify_CF_Gallery::clean($raw, $allowed_media, $permissions) : Alify_CF::clean_value($sub, $raw)));
                if (Alify_CF_Composition::container($sub)) {
                    $child_old = Alify_CF_Composition::old_child($sub, $raw, $previous, $old, $issue);
                    $child_location = null;
                    if ($issue === '') { $value = Alify_CF_Composition::clean($sub, $raw, $child_old, $permissions, $issue, $child_location); }
                    if ($issue !== '') { $issue = 'Row ' . $position . ' → ' . $sub['label'] . ' → ' . $issue; $location = array('row' => $position - 1, 'subfield' => $sub['name'], 'child' => $child_location); return ''; }
                }
                if (Alify_CF_Selector::supported($sub)) { $value = Alify_CF_Selector::clean($sub, $raw, $allowed_selectors[$sub['name']] ?? array(), $permissions); }
                $value = Alify_CF_Field_Settings::constrain($sub, $value);
                $empty = ($sub['type'] === 'group' && Alify_CF_Group::empty_value($sub, $value)) || $value === '' || $value === array() || ($sub['type'] === 'true_false' && $value !== '1');
                $path = 'Row ' . $position . ' → ' . $sub['label'] . ': ';
                if ((in_array($sub['type'], array('wysiwyg', 'oembed'), true) && !is_string($raw)) || (Alify_CF_Selector::supported($sub) && !Alify_CF_Selector::multiple($sub) && array_key_exists($sub['name'], $row) && !is_string($raw) && !is_int($raw)) || (Alify_CF_Selector::multiple($sub) && $value === '') || ($sub['type'] === 'color' && array_key_exists($sub['name'], $row) && !is_string($raw)) || (Alify_CF_Choice::multiple($sub) && $value === '') || ($sub['type'] === 'date_time' && array_key_exists($sub['name'], $row) && !is_string($row[$sub['name']])) || ($sub['type'] === 'password' && array_key_exists($sub['name'], $row) && !is_string($row[$sub['name']])) || ($sub['type'] === 'range' && array_key_exists($sub['name'], $row) && $row[$sub['name']] !== '' && Alify_CF_Range::units($row[$sub['name']]) === null) || (Alify_CF_Reference_Settings::multiple($sub) && $value === '') || (!Alify_CF_Reference_Settings::multiple($sub) && in_array($sub['type'], array('post_object', 'radio', 'button_group'), true) && !is_string($raw) && !is_int($raw)) || (in_array($sub['type'], array('gallery', 'link', 'relationship', 'google_map'), true) && $value === '') || (!empty($sub['required']) && $empty) || ($raw !== '' && $raw !== array() && $value === '')) {
                    $location = array('row' => $position - 1, 'subfield' => $sub['name']);
                    $issue = $path . ($sub['type'] === 'gallery' && $value === '' ? 'Choose up to 50 available images you can edit, with a complete gallery selection.' : (!empty($sub['required']) && $empty ? 'A valid value is required.' : 'Enter a valid ' . $sub['type'] . ' value.'));
                    return '';
                }
                if ($permissions && in_array($sub['type'], array('image', 'file'), true) && $value !== '' && !in_array((string) $value, $allowed_media, true) && !current_user_can('edit_post', $value)) { $location = array('row' => $position - 1, 'subfield' => $sub['name']); $issue = $path . 'You cannot select this attachment.'; return ''; }
                if ($permissions && $sub['type'] === 'post_object' && !Alify_CF_Reference_Settings::multiple($sub) && $value !== '' && !in_array((string) $value, $allowed_posts, true) && !Alify_CF_Post_Object::readable($value, $sub)) { $location = array('row' => $position - 1, 'subfield' => $sub['name']); $issue = $path . 'You cannot select this post.'; return ''; }
                $out[$sub['name']] = $value;
            }
            $clean[] = $out;
        }
        return $clean;
    }

    public static function render($field, $value, $id, $input) {
        $rows = is_array($value) ? $value : array();
        list($minimum, $maximum) = self::bounds($field);
        echo '<div class="alify-repeater" data-base-id="' . esc_attr($id) . '" data-min="' . esc_attr($minimum) . '" data-limit="' . esc_attr($maximum) . '"' . Alify_CF_Editor_Fields::attributes($field) . '><div class="alify-rows">';
        foreach ($rows as $index => $row) { self::row($field, is_array($row) ? $row : array(), $id, $input, $index); }
        $token = '__ROW_' . substr(hash('sha256', $id), 0, 12) . '__'; echo '</div><template class="alify-row-template" data-row-token="' . esc_attr($token) . '">';
        self::row($field, null, $id, $input, $token);
        echo '</template><input type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1"><div class="alify-repeater-footer"><button type="button" class="button" data-row-action="add">+ Add Row</button> <span class="alify-row-count" aria-live="polite">' . esc_html(count($rows)) . ' / ' . esc_html($maximum) . ' rows</span></div><p class="description">Allowed rows: ' . esc_html($minimum) . '–' . esc_html($maximum) . '. Invalid rows retain the entire previously saved repeater.</p></div>';
    }

    private static function row($field, $row, $id, $input, $index) {
        echo '<div class="alify-row" data-row-prefix="' . esc_attr($input . '[rows][' . $index . ']') . '" data-row-id-prefix="' . esc_attr($id . '-' . $index) . '" data-condition-scope="1"><div class="alify-row-bar"><strong class="alify-row-label">Row</strong><div><button type="button" class="button" data-row-action="up" aria-label="Move row up">↑</button> <button type="button" class="button" data-row-action="down" aria-label="Move row down">↓</button> <button type="button" class="button-link-delete" data-row-action="remove">Remove row</button></div></div>';
        foreach ($field['sub_fields'] as $sub) {
            $sub_id = $id . '-' . $index . '-' . $sub['key'];
            $name = $input . '[rows][' . $index . '][' . $sub['name'] . ']';
            $value = $row === null ? $sub['default'] : ($row[$sub['name']] ?? '');
            echo '<div class="alify-sub-value"' . Alify_CF_Scoped_Conditions::attributes($sub, $field['sub_fields'], $row) . ' data-subfield="' . esc_attr($sub['name']) . '" data-sub-type="' . esc_attr($sub['type']) . '"><label class="alify-label" for="' . esc_attr($sub_id) . '">' . esc_html($sub['label']) . (!empty($sub['required']) ? ' <span class="alify-required">*</span>' : '') . '</label><p class="description" id="' . esc_attr($sub_id . '-help') . '">' . esc_html($sub['instructions']) . '</p>';
            Alify_CF::control($sub, $value, $sub_id, $name);
            echo '</div>';
        }
        if (Alify_CF_Composition::needs_origin($field['sub_fields'])) { echo '<input type="hidden" name="' . esc_attr($input . '[rows][' . $index . '][__row_origin]') . '" value="' . esc_attr(Alify_CF_Scoped_Conditions::origin($row)) . '">'; }
        echo '<input type="hidden" name="' . esc_attr($input . '[rows][' . $index . '][__row_complete]') . '" value="1"></div>';
    }
}
