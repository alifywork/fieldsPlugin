<?php
if (!defined('ABSPATH')) { exit; }

/** Bounded, ordered layout rows. Layout names and field keys are schema, never executable code. */
final class Alify_CF_Flexible {
    const MAX_LAYOUTS = 10;
    const MAX_ROWS = 50;
    public static function schema($raw, &$errors) {
        if (!is_array($raw) || array_values($raw) !== $raw || !$raw || count($raw) > self::MAX_LAYOUTS) { $errors[] = 'Flexible Content needs 1–10 layouts.'; return array(); }
        $layouts = array(); $names = array(); $keys = array();
        foreach ($raw as $layout) {
            if (!is_array($layout) || !is_string($layout['name'] ?? null) || !preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $layout['name']) || !is_string($layout['label'] ?? null) || trim(sanitize_text_field($layout['label'])) === '' || !is_string($layout['key'] ?? null) || !preg_match('/^[a-z0-9_-]+$/D', $layout['key']) || isset($names[$layout['name']]) || isset($keys[$layout['key']])) { $errors[] = 'Layouts need unique valid names/keys and labels.'; continue; }
            $names[$layout['name']] = true; $keys[$layout['key']] = true;
            $layouts[] = array('name' => $layout['name'], 'key' => $layout['key'], 'label' => sanitize_text_field($layout['label']), 'sub_fields' => Alify_CF_Repeater::schema($layout['sub_fields'] ?? null, $errors));
        }
        return $layouts;
    }
    public static function layout($field, $name) {
        foreach ($field['layouts'] ?? array() as $layout) { if ($layout['name'] === $name) { return $layout; } }
        return null;
    }
    public static function clean($field, $payload, $old = null, $permissions = false) {
        if (!is_array($payload) || ($payload['__complete'] ?? '') !== '1' || !is_array($payload['rows'] ?? array()) || count($payload['rows'] ?? array()) > self::MAX_ROWS) { return ''; }
        $out = array();
        foreach ($payload['rows'] ?? array() as $row) {
            if (!is_array($row) || !is_string($row['layout'] ?? null) || ($row['__complete'] ?? '') !== '1' || !is_array($row['fields'] ?? null)) { return ''; }
            $layout = self::layout($field, $row['layout']); if (!$layout) { return ''; }
            $previous = array();
            foreach (is_array($old) ? $old : array() as $saved) { if (is_array($saved) && ($saved['layout'] ?? null) === $layout['name'] && is_array($saved['fields'] ?? null)) { $previous[] = $saved['fields']; } }
            $values = $row['fields']; $values['__row_complete'] = '1';
            $clean = Alify_CF_Repeater::clean($layout, array('__complete' => '1', 'rows' => array($values)), $previous, $permissions);
            if (!is_array($clean)) { return ''; }
            $out[] = array('layout' => $layout['name'], 'fields' => $clean[0]);
        }
        return $out;
    }
    public static function render($field, $value, $id, $input) {
        echo '<div class="alify-flexible" data-limit="50"><div class="alify-flex-rows">';
        foreach (is_array($value) ? $value : array() as $index => $row) {
            if (!is_array($row)) { continue; }
            $layout = self::layout($field, $row['layout'] ?? '');
            if ($layout) { self::row($layout, $row['fields'] ?? array(), $id, $input, $index); }
            else { // Never silently drop data when a saved layout was removed/renamed.
                echo '<div class="alify-flex-row"><p role="alert">Unknown saved layout. Restore its schema or explicitly remove this row before saving.</p><input type="hidden" name="' . esc_attr($input . '[rows][' . $index . '][layout]') . '" value=""><span class="alify-flex-label"></span><button type="button" data-flex-action="up">Move up</button><button type="button" data-flex-action="down">Move down</button><button type="button" data-flex-action="remove">Remove row</button></div>';
            }
        }
        echo '</div>';
        foreach ($field['layouts'] as $layout) { echo '<template data-flex-template="' . esc_attr($layout['name']) . '">'; self::row($layout, null, $id, $input, '__FLEX_ROW__'); echo '</template>'; }
        echo '<label>Layout <select class="alify-flex-choice">';
        foreach ($field['layouts'] as $layout) { echo '<option value="' . esc_attr($layout['name']) . '">' . esc_html($layout['label']) . '</option>'; }
        echo '</select></label> <button type="button" class="button" data-flex-action="add">+ Add Layout</button><span class="alify-flex-count" aria-live="polite"></span><input type="hidden" name="' . esc_attr($input . '[__complete]') . '" value="1"></div>';
    }
    private static function row($layout, $values, $id, $input, $index) {
        $base = $input . '[rows][' . $index . ']';
        echo '<div class="alify-flex-row"><div class="alify-row-bar"><strong>' . esc_html($layout['label']) . ' <span class="alify-flex-label"></span></strong><div><button type="button" class="button" data-flex-action="up">Move up</button><button type="button" class="button" data-flex-action="down">Move down</button><button type="button" class="button-link-delete" data-flex-action="remove">Remove row</button></div></div><input type="hidden" name="' . esc_attr($base . '[layout]') . '" value="' . esc_attr($layout['name']) . '">';
        Alify_CF_Group::render($layout, $values, $id . '-' . $index, $base, 'alify-layout-fields');
        echo '</div>';
    }
}
