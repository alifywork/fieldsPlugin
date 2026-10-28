<?php
if (!defined('ABSPATH')) { exit; }

/** At most ten leaves, three group levels; bounded dependency chains, no regex. */
final class Alify_CF_Conditions {
    const MAX_RULES = 10;
    public static function rules($condition, $depth = 0) {
        if (!is_array($condition) || $depth > 3) { return array(); }
        if (!isset($condition['rules'])) { return array($condition); }
        $out = array(); foreach (is_array($condition['rules']) ? $condition['rules'] : array() as $node) { $out = array_merge($out, self::rules($node, $depth + 1)); if (count($out) > 10) { break; } } return $out;
    }
    public static function operators() { return array('equals', 'not_equals', 'empty', 'not_empty', 'contains', 'starts_with', 'ends_with', 'glob'); }
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
        if ($raw['operator'] === 'glob' && !self::pattern($raw['value'])) { $errors[] = 'Patterns need 1–256 printable ASCII characters and at most four * wildcards.'; return null; }
        return array('field' => sanitize_key($raw['field']), 'operator' => $raw['operator'], 'value' => sanitize_text_field($raw['value']));
    }
    public static function pattern($pattern) { return is_string($pattern) && preg_match('/^[\x20-\x7e]{1,256}$/D', $pattern) && substr_count($pattern, '*') <= 4; }
    public static function glob($text, $pattern) {
        if (!is_string($text) || !self::pattern($pattern) || !preg_match('/^[\x09\x0a\x0d\x20-\x7e]{0,4096}$/D', $text)) { return false; }
        if (strpos($pattern, '*') === false) { return $text === $pattern; }
        $parts = explode('*', $pattern); $offset = 0; $last = count($parts) - 1;
        foreach ($parts as $index => $part) { if ($part === '') { continue; } if ($index === $last) { $position = strlen($text) - strlen($part); if ($position < $offset || substr($text, $position) !== $part) { return false; } } else { $position = strpos($text, $part, $offset); if ($position === false || ($index === 0 && $position !== 0)) { return false; } } $offset = $position + strlen($part); } return true;
    }
    const MAX_CHAIN = 16;
    public static function source($field) { return !Alify_CF_Editor_Fields::presentation($field['type']) && !Alify_CF_API::custom($field['type']) && !in_array($field['type'], array('repeater', 'group', 'flexible', 'link', 'wysiwyg', 'password', 'google_map'), true); }
    /** Validate every dependency, including branches which would short-circuit at runtime. */
    public static function graph($schema, &$errors) {
        $fields = array(); foreach ($schema as $field) { $fields[$field['key']] = $field; }
        $heights = array(); $visiting = array();
        $visit = function($key) use (&$visit, &$heights, &$visiting, $fields) {
            if (isset($heights[$key])) { return $heights[$key]; }
            if (!isset($fields[$key]) || isset($visiting[$key]) || count($visiting) > self::MAX_CHAIN) { return self::MAX_CHAIN + 1; }
            $visiting[$key] = true; $height = 0; $issues = array(); $condition = self::clean($fields[$key]['condition'] ?? null, $issues, $fields[$key]['name'] ?? '');
            if ($issues) { $height = self::MAX_CHAIN + 1; }
            foreach (self::rules($condition) as $rule) { $height = max($height, 1 + $visit($rule['field'] ?? '')); if ($height > self::MAX_CHAIN) { break; } }
            unset($visiting[$key]); return $heights[$key] = $height;
        };
        foreach ($fields as $key => $field) { if ($visit($key) > self::MAX_CHAIN) { $errors[] = 'Conditional dependencies must be acyclic and at most 16 links deep.'; return false; } } return true;
    }
    public static function visible($field, $schema, $values) {
        if (empty($field['condition'])) { return true; } $errors = array(); if (!self::graph($schema, $errors)) { return false; } $memo = array(); return self::resolve($field, $schema, $values, $memo);
    }
    private static function resolve($field, $schema, $values, &$memo) {
        if (array_key_exists($field['key'], $memo)) { return $memo[$field['key']]; }
        $condition = $field['condition'] ?? null; if (!$condition) { return $memo[$field['key']] = true; }
        $errors = array(); $condition = self::clean($condition, $errors, $field['name'] ?? ''); if ($errors) { return false; }
        $results = array();
        foreach (self::rules($condition) as $rule) {
            $source = null; foreach ($schema as $candidate) { if ($candidate['key'] === $rule['field']) { $source = $candidate; break; } }
            if (!$source || !self::source($source) || $source['key'] === $field['key']) { return $memo[$field['key']] = false; }
            if (!self::resolve($source, $schema, $values, $memo)) { $results[] = false; continue; }
            $value = $values[$source['key']] ?? ''; $items = is_array($value) ? array_map('strval', array_filter($value, 'is_scalar')) : array((string) $value);
            $empty = !$items || $items === array('') || ($source['type'] === 'true_false' && $items === array('0')); $matches = in_array($rule['value'], $items, true); $operator = $rule['operator'];
            if (in_array($operator, array('contains', 'starts_with', 'ends_with'), true)) {
                $matches = false; if ($rule['value'] !== '') { foreach ($items as $item) { $needle = $rule['value']; if (($operator === 'contains' && strpos($item, $needle) !== false) || ($operator === 'starts_with' && strpos($item, $needle) === 0) || ($operator === 'ends_with' && substr($item, -strlen($needle)) === $needle)) { $matches = true; break; } } }
            }
            if ($operator === 'glob') { $matches = false; foreach ($items as $item) { if (self::glob($item, $rule['value'])) { $matches = true; break; } } }
            $results[] = in_array($operator, array('equals', 'contains', 'starts_with', 'ends_with', 'glob'), true) ? $matches : ($operator === 'not_equals' ? !$matches : ($operator === 'empty' ? $empty : !$empty));
        }
        $index = 0; return $memo[$field['key']] = self::evaluate($condition, $results, $index);
    }
    private static function evaluate($node, $results, &$index) {
        if (!isset($node['rules'])) { return $results[$index++]; }
        $children = array(); foreach ($node['rules'] as $child) { $children[] = self::evaluate($child, $results, $index); } return $node['relation'] === 'any' ? in_array(true, $children, true) : !in_array(false, $children, true);
    }
}
