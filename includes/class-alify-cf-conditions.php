<?php
if (!defined('ABSPATH')) { exit; }

/** At most ten leaves, three group levels; no regex or conditional-source chains. */
final class Alify_CF_Conditions {
    const MAX_RULES = 10;
    public static function rules($condition, $depth = 0) {
        if (!is_array($condition) || $depth > 3) { return array(); }
        if (!isset($condition['rules'])) { return array($condition); }
        $out = array(); foreach (is_array($condition['rules']) ? $condition['rules'] : array() as $node) { $out = array_merge($out, self::rules($node, $depth + 1)); if (count($out) > 10) { break; } } return $out;
    }
    public static function operators() { return array('equals', 'not_equals', 'empty', 'not_empty', 'contains', 'starts_with', 'ends_with'); }
    public static function clean($raw, &$errors, $name) {
        if (!$raw) { return null; } $count = 0; $clean = self::node($raw, $errors, $name, 0, $count);
        if ($count > self::MAX_RULES) { $errors[] = 'Conditions support at most 10 leaf rules: ' . $name; }
        return $clean;
    }
    private static function node($raw, &$errors, $name, $depth, &$count) {
        if (!is_array($raw) || $depth > 3) { $errors[] = 'Invalid/over-deep conditional rule: ' . $name; return null; }
        if (array_key_exists('rules', $raw) || array_key_exists('relation', $raw)) {
            if ($depth >= 3 || !in_array($raw['relation'] ?? '', array('all', 'any'), true) || !is_array($raw['rules'] ?? null) || !$raw['rules'] || array_values($raw['rules']) !== $raw['rules'] || count($raw['rules']) > 10 || isset($raw['field'])) { $errors[] = 'Conditions need ALL/ANY, 1–10 children, and at most three group levels: ' . $name; return null; }
            $rules = array(); foreach ($raw['rules'] as $rule) { if ($count > 10) { break; } $rules[] = self::node($rule, $errors, $name, $depth + 1, $count); } return array('relation' => $raw['relation'], 'rules' => $rules);
        }
        $count++;
        if (!is_string($raw['field'] ?? null) || !is_string($raw['operator'] ?? null) || !is_string($raw['value'] ?? null) || strlen($raw['value']) > 1000 || !in_array($raw['operator'], self::operators(), true)) { $errors[] = 'Invalid conditional leaf: ' . $name; return null; }
        return array('field' => sanitize_key($raw['field']), 'operator' => $raw['operator'], 'value' => sanitize_text_field($raw['value']));
    }
    public static function visible($field, $schema, $values) {
        $condition = $field['condition'] ?? null; if (!$condition) { return true; }
        $errors = array(); $condition = self::clean($condition, $errors, $field['name'] ?? ''); if ($errors) { return false; }
        $results = array();
        foreach (self::rules($condition) as $rule) {
            $source = null; foreach ($schema as $candidate) { if ($candidate['key'] === $rule['field']) { $source = $candidate; break; } }
            if (!$source || Alify_CF_Editor_Fields::presentation($source['type']) || Alify_CF_API::custom($source['type']) || Alify_CF_Selector::supported($source) || Alify_CF_Reference_Settings::multiple($source) || in_array($source['type'], array('repeater', 'group', 'flexible', 'link', 'wysiwyg', 'relationship', 'password', 'google_map'), true) || !empty($source['condition']) || $source['key'] === $field['key']) { return false; }
            $value = $values[$source['key']] ?? ''; $items = is_array($value) ? array_map('strval', array_filter($value, 'is_scalar')) : array((string) $value);
            $empty = !$items || $items === array('') || ($source['type'] === 'true_false' && $items === array('0')); $matches = in_array($rule['value'], $items, true); $operator = $rule['operator'];
            if (in_array($operator, array('contains', 'starts_with', 'ends_with'), true)) {
                $matches = false; if ($rule['value'] !== '') { foreach ($items as $item) { $needle = $rule['value']; if (($operator === 'contains' && strpos($item, $needle) !== false) || ($operator === 'starts_with' && strpos($item, $needle) === 0) || ($operator === 'ends_with' && substr($item, -strlen($needle)) === $needle)) { $matches = true; break; } } }
            }
            $results[] = in_array($operator, array('equals', 'contains', 'starts_with', 'ends_with'), true) ? $matches : ($operator === 'not_equals' ? !$matches : ($operator === 'empty' ? $empty : !$empty));
        }
        $index = 0; return self::evaluate($condition, $results, $index);
    }
    private static function evaluate($node, $results, &$index) {
        if (!isset($node['rules'])) { return $results[$index++]; }
        $children = array(); foreach ($node['rules'] as $child) { $children[] = self::evaluate($child, $results, $index); } return $node['relation'] === 'any' ? in_array(true, $children, true) : !in_array(false, $children, true);
    }
}
