<?php
if (!defined('ABSPATH')) { exit; }

/** Bounded, ordered layout rows. Layout names and field keys are schema, never executable code. */
final class Alify_CF_Flexible {
    const MAX_LAYOUTS = 10;
    const MAX_ROWS = 50;
    public static function schema($raw, &$errors, $depth = 1) {
        if (!is_array($raw) || array_values($raw) !== $raw || !$raw || count($raw) > self::MAX_LAYOUTS) { $errors[] = 'Flexible Content needs 1–10 layouts.'; return array(); }
        $layouts = array(); $names = array(); $keys = array();
        foreach ($raw as $layout) {
            if (!is_array($layout) || !is_string($layout['name'] ?? null) || !preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $layout['name']) || !is_string($layout['label'] ?? null) || trim(sanitize_text_field($layout['label'])) === '' || !is_string($layout['key'] ?? null) || !preg_match('/^[a-z0-9_-]+$/D', $layout['key']) || isset($names[$layout['name']]) || isset($keys[$layout['key']])) { $errors[] = 'Layouts need unique valid names/keys and labels.'; continue; }
            $names[$layout['name']] = true; $keys[$layout['key']] = true;
            $settings = array(); foreach (array('min_rows' => 0, 'max_rows' => 50) as $key => $default) { if (!array_key_exists($key, $layout)) { continue; } $value = $layout[$key]; if ((!is_int($value) && !is_string($value)) || !preg_match('/^(?:0|[1-9]|[1-4][0-9]|50)$/D', (string) $value)) { $errors[] = 'Layout bounds need integers 0–50.'; } else { $settings[$key] = (int) $value; } } if (($settings['min_rows'] ?? 0) > ($settings['max_rows'] ?? 50)) { $errors[] = 'Layout minimum exceeds maximum.'; }
            if (array_key_exists('disabled', $layout)) { if (!in_array($layout['disabled'], array(true, false, 0, 1, '0', '1'), true)) { $errors[] = 'Layout disabled must be boolean.'; } else { $settings['disabled'] = (bool) $layout['disabled']; } }
            $layouts[] = array_merge(array('name' => $layout['name'], 'key' => $layout['key'], 'label' => sanitize_text_field($layout['label']), 'sub_fields' => Alify_CF_Repeater::schema($layout['sub_fields'] ?? null, $errors, $depth)), $settings);
        }
        return $layouts;
    }
    public static function layout($field, $name) {
        foreach ($field['layouts'] ?? array() as $layout) { if ($layout['name'] === $name) { return $layout; } }
        return null;
    }
    public static function clean($field, $payload, $old = null, $permissions = false, &$issue = null, &$location = null) {
        $issue = ''; $location = null;
        if (!is_array($payload) || ($payload['__complete'] ?? '') !== '1' || !is_array($payload['rows'] ?? array()) || count($payload['rows'] ?? array()) > self::MAX_ROWS) { $issue = 'Use a complete layout list with no more than 50 rows. Reload if the submission was incomplete.'; return ''; }
        $counts = array(); foreach ($payload['rows'] ?? array() as $row) { if (is_array($row) && is_string($row['layout'] ?? null)) { $counts[$row['layout']] = ($counts[$row['layout']] ?? 0) + 1; } }
        foreach ($field['layouts'] as $layout) { $count = $counts[$layout['name']] ?? 0; $old_count = 0; foreach (is_array($old) ? $old : array() as $saved) { if (is_array($saved) && ($saved['layout'] ?? '') === $layout['name']) { $old_count++; } } if ($count < ($layout['min_rows'] ?? 0) || $count > ($layout['max_rows'] ?? 50) || (!empty($layout['disabled']) && $count > $old_count)) { $issue = 'Layout ' . $layout['label'] . ': row limits or disabled-layout policy violated. Previous value retained.'; return ''; } }
        $out = array();
        $position = 0;
        foreach ($payload['rows'] ?? array() as $row) {
            $position++;
            if (!is_array($row) || !is_string($row['layout'] ?? null) || ($row['__complete'] ?? '') !== '1' || !is_array($row['fields'] ?? null)) { $issue = 'Row ' . $position . ': Incomplete layout row. Reload the editor.'; return ''; }
            $layout = self::layout($field, $row['layout']); if (!$layout) { $issue = 'Row ' . $position . ': Unknown layout. Restore its schema or remove the row explicitly.'; return ''; }
            $previous = array();
            foreach (is_array($old) ? $old : array() as $saved) { if (is_array($saved) && ($saved['layout'] ?? null) === $layout['name'] && is_array($saved['fields'] ?? null)) { $previous[] = $saved['fields']; } }
            $values = $row['fields']; $values['__row_complete'] = '1';
            $clean = Alify_CF_Repeater::clean($layout, array('__complete' => '1', 'rows' => array($values)), $previous, $permissions, $issue, $location);
            if (!is_array($clean)) {
                if ($location) { $location['row'] = $position - 1; }
                if (strpos($issue, 'Row 1 → ') === 0) { $issue = substr($issue, strlen('Row 1 → ')); }
                $issue = 'Row ' . $position . ' (' . $layout['label'] . ') → ' . $issue;
                return '';
            }
            $out[] = array('layout' => $layout['name'], 'fields' => $clean[0]);
        }
        return $out;
    }
    public static function render($field, $value, $id, $input) {
        echo '<div class="alify-flexible" data-limit="50" data-layout-limits="' . esc_attr(wp_json_encode(array_map(function($layout) { return array('name' => $layout['name'], 'min' => $layout['min_rows'] ?? 0, 'max' => $layout['max_rows'] ?? 50, 'disabled' => !empty($layout['disabled'])); }, $field['layouts']))) . '"' . Alify_CF_Editor_Fields::attributes($field) . '><div class="alify-flex-rows">';
        foreach (is_array($value) ? $value : array() as $index => $row) {
            if (!is_array($row)) { continue; }
            $layout = self::layout($field, $row['layout'] ?? '');
            if ($layout) { self::row($layout, $row['fields'] ?? array(), $id, $input, $index); }
            else { // Never silently drop data when a saved layout was removed/renamed.
                echo '<div class="alify-flex-row" data-row-prefix="' . esc_attr($input . '[rows][' . $index . ']') . '" data-row-id-prefix="' . esc_attr($id . '-' . $index) . '"><p role="alert">Unknown saved layout. Restore its schema or explicitly remove this row before saving.</p><input type="hidden" name="' . esc_attr($input . '[rows][' . $index . '][layout]') . '" value=""><span class="alify-flex-label"></span><button type="button" data-flex-action="up">Move up</button><button type="button" data-flex-action="down">Move down</button><button type="button" data-flex-action="remove">Remove row</button></div>';
            }
        }
        echo '</div>';
        $token = '__FLEX_ROW_' . substr(hash('sha256', $id), 0, 12) . '__'; foreach ($field['layouts'] as $layout) { echo '<template data-row-token="' . esc_attr($token) . '" data-flex-template="' . esc_attr($layout['name']) . '">'; self::row($layout, null, $id, $input, $token); echo '</template>'; }
        echo '<label>Layout <select class="alify-flex-choice">';
        foreach ($field['layouts'] as $layout) { echo '<option value="' . esc_attr($layout['name']) . '">' . esc_html($layout['label']) . '</option>'; }
        echo '</select></label> <button type="button" class="button" data-flex-action="add">+ Add Layout</button><span class="alify-flex-count" aria-live="polite"></span><input type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1"></div>';
    }
    private static function row($layout, $values, $id, $input, $index) {
        $base = $input . '[rows][' . $index . ']';
        echo '<div class="alify-flex-row" data-row-prefix="' . esc_attr($base) . '" data-row-id-prefix="' . esc_attr($id . '-' . $index) . '"><div class="alify-row-bar"><strong>' . esc_html($layout['label']) . ' <span class="alify-flex-label"></span></strong><div><button type="button" class="button" data-flex-action="up">Move up</button><button type="button" class="button" data-flex-action="down">Move down</button><button type="button" class="button-link-delete" data-flex-action="remove">Remove row</button></div></div><input type="hidden" name="' . esc_attr($base . '[layout]') . '" value="' . esc_attr($layout['name']) . '">';
        Alify_CF_Group::render($layout, $values, $id . '-' . $index, $base, 'alify-layout-fields');
        echo '</div>';
    }
}
