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
        if ($field['type'] === 'repeater') { $cost = 3; foreach (is_array($value) ? $value : array() as $row) { $cost += 2; foreach ($field['sub_fields'] as $sub) { $cost += self::cost($sub, $row[$sub['name']] ?? $sub['default']); } } return $cost; }
        if ($field['type'] === 'flexible') { $cost = 3; foreach (is_array($value) ? $value : array() as $row) { $layout = Alify_CF_Flexible::layout($field, $row['layout'] ?? ''); if ($layout) { $layout['type'] = 'group'; $cost += 3 + self::cost($layout, $row['fields'] ?? array()); } } return $cost; }
        if (is_array($value)) { return 5 + count($value) * 3; } return 3;
    }
    public static function needs_origin($fields) { return self::children($fields) || Alify_CF_Scoped_Conditions::enabled($fields); }
    public static function old_child($sub, $raw, $previous, $pool, &$issue) {
        if (is_array($previous)) { return $previous[$sub['name']] ?? null; }
        foreach ((array) $pool as $saved) { if (is_array($saved) && $raw === Alify_CF_Publication::stored_payload($sub, $saved[$sub['name']] ?? $sub['default'])) { return $saved[$sub['name']] ?? null; } }
        if ($pool) { $issue = 'Changed nested content needs its parent row origin; positional retention is not safe.'; }
        return null;
    }
    public static function clean($field, $raw, $old, $permissions, &$issue, &$location) {
        if ($field['type'] === 'group') { return Alify_CF_Group::clean($field, $raw, $old, $permissions, $issue, $location); }
        if ($field['type'] === 'flexible') { return Alify_CF_Flexible::clean($field, $raw, $old, $permissions, $issue, $location); }
        return Alify_CF_Repeater::clean($field, $raw, $old, $permissions, $issue, $location);
    }
}
