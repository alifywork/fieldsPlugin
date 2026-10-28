<?php
if (!defined('ABSPATH')) { exit; }

/** Global budgets complement per-container cardinalities; never multiply bounds without a ceiling. */
final class Alify_CF_Composition {
    const DEPTH = 4;
    public static function container($field) { return in_array($field['type'] ?? '', array('group', 'repeater', 'flexible'), true); }
    public static function children($fields) { foreach ($fields as $field) { if (self::container($field)) { return true; } } return false; }
    public static function budget($value, $schema = false) {
        $json = wp_json_encode($value); if (!is_string($json) || strlen($json) > 1048576) { return false; }
        $stack = array(array($value, 0)); $nodes = 0;
        while ($stack) { list($item, $depth) = array_pop($stack); if (++$nodes > ($schema ? 30000 : 20000) || $depth > 32 || is_object($item) || is_resource($item)) { return false; } if (is_array($item)) { foreach ($item as $child) { $stack[] = array($child, $depth + 1); } } }
        return true;
    }
    private static $rendered = 0;
    public static function reset_render_budget() { self::$rendered = 0; }
    public static function renderable($fields, $values) {
        if (!self::budget($values)) { return false; } $count = 0;
        foreach ($fields as $field) { $count += self::cost($field, $values[$field['key']] ?? $field['default']); }
        $limit = (int) ini_get('max_input_vars'); $limit = $limit > 0 ? max(0, min(5000, $limit - 200)) : 5000;
        if (self::$rendered + $count > $limit) { return false; } self::$rendered += $count; return true;
    }
    private static function cost($field, $value) {
        if ($field['type'] === 'group') { $cost = 3; foreach ($field['sub_fields'] as $sub) { $cost += self::cost($sub, is_array($value) ? ($value[$sub['name']] ?? $sub['default']) : $sub['default']); } return $cost; }
        if ($field['type'] === 'repeater') { $cost = 5; foreach ($field['sub_fields'] as $sub) { $cost += self::cost($sub, $sub['default']); } foreach (is_array($value) ? $value : array() as $row) { $cost += 2; foreach ($field['sub_fields'] as $sub) { $cost += self::cost($sub, $row[$sub['name']] ?? $sub['default']); } } return $cost; }
        if ($field['type'] === 'flexible') { $cost = 3; foreach ($field['layouts'] as $layout) { $layout['type'] = 'group'; $cost += self::cost($layout, array()); } foreach (is_array($value) ? $value : array() as $row) { $layout = Alify_CF_Flexible::layout($field, $row['layout'] ?? ''); if ($layout) { $layout['type'] = 'group'; $cost += 3 + self::cost($layout, $row['fields'] ?? array()); } } return $cost; }
        if (in_array($field['type'], array('select', 'checkbox', 'radio', 'button_group'), true)) { return 3 + 2 * (substr_count($field['choices'] ?? '', "\n") + 1); }
        if (is_array($value)) { return 5 + count($value) * 3; } return 3;
    }
    public static function needs_origin($fields) { return self::children($fields) || Alify_CF_Scoped_Conditions::enabled($fields); }
    public static function old_child($sub, $raw, $previous, $pool, &$issue) {
        if (is_array($previous)) { return $previous[$sub['name']] ?? null; }
        foreach ((array) $pool as $saved) { if (is_array($saved) && $raw === Alify_CF_Publication::stored_payload($sub, $saved[$sub['name']] ?? $sub['default'])) { return $saved[$sub['name']] ?? null; } }
        if ($pool) { $issue = 'Changed nested content needs its parent row origin; positional retention is not safe.'; }
        return null;
    }
    /** Rebuild validated canonical values while retaining only submitted row identities, never raw values. */
    public static function repack($field, $canonical, $raw) {
        $payload = Alify_CF_Publication::stored_payload($field, $canonical);
        if (!self::container($field) || !is_array($raw)) { return $payload; }
        if ($field['type'] === 'flexible') { $rows = array_values(is_array($raw['rows'] ?? null) ? $raw['rows'] : array()); foreach ($payload['rows'] as $index => &$row) { $layout = Alify_CF_Flexible::layout($field, $row['layout']); if ($layout) { $layout['type'] = 'group'; $transport = self::repack($layout, $canonical[$index]['fields'], $rows[$index] ?? array()); $row['fields'] = $transport['fields']; } } unset($row); return $payload; }
        $originals = $field['type'] === 'group' ? array(is_array($raw['fields'] ?? null) ? $raw['fields'] : array()) : array_values(is_array($raw['rows'] ?? null) ? $raw['rows'] : array()); $values = $field['type'] === 'group' ? array($canonical) : $canonical;
        $rows = $field['type'] === 'group' ? array($payload['fields']) : $payload['rows'];
        foreach ($rows as $index => &$row) { $original = is_array($originals[$index] ?? null) ? $originals[$index] : array(); if (array_key_exists('__row_origin', $original)) { $row['__row_origin'] = $original['__row_origin']; } $sources = array(); foreach ($field['sub_fields'] as $sub) { $sources[$sub['key']] = $values[$index][$sub['name']] ?? $sub['default']; } foreach ($field['sub_fields'] as $sub) { $value = $values[$index][$sub['name']] ?? $sub['default']; $row[$sub['name']] = Alify_CF_Conditions::visible($sub, $field['sub_fields'], $sources) ? self::repack($sub, $value, $original[$sub['name']] ?? null) : Alify_CF_Publication::stored_payload($sub, $value); } } unset($row);
        if ($field['type'] === 'group') { $payload['fields'] = $rows[0]; } else { $payload['rows'] = $rows; } return $payload;
    }
    public static function clean($field, $raw, $old, $permissions, &$issue, &$location) {
        if ($field['type'] === 'group') { return Alify_CF_Group::clean($field, $raw, $old, $permissions, $issue, $location); }
        if ($field['type'] === 'flexible') { return Alify_CF_Flexible::clean($field, $raw, $old, $permissions, $issue, $location); }
        return Alify_CF_Repeater::clean($field, $raw, $old, $permissions, $issue, $location);
    }
}
