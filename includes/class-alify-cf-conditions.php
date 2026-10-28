<?php
if (!defined('ABSPATH')) { exit; }

/** Bounded, flat AND/OR rules; legacy single-rule schemas retain their shape. */
final class Alify_CF_Conditions {
    const MAX_RULES = 10;
    public static function rules($condition) {
        if (!is_array($condition)) { return array(); }
        return isset($condition['rules']) ? (is_array($condition['rules']) ? $condition['rules'] : array()) : array($condition);
    }
    public static function clean($raw, &$errors, $name) {
        if (!$raw) { return null; }
        if (!is_array($raw)) { $errors[] = 'Invalid conditional rule for ' . $name; return null; }
        $compound = array_key_exists('rules', $raw) || array_key_exists('relation', $raw);
        if ($compound && (!in_array($raw['relation'] ?? '', array('all', 'any'), true) || !is_array($raw['rules'] ?? null) || !$raw['rules'] || array_values($raw['rules']) !== $raw['rules'] || count($raw['rules']) > self::MAX_RULES)) {
            $errors[] = 'Conditions need ALL/ANY and 1–10 flat rules: ' . $name; return null;
        }
        $clean = array();
        foreach ($compound ? $raw['rules'] : array($raw) as $rule) {
            if (!is_array($rule) || !isset($rule['field'], $rule['operator'], $rule['value']) || !is_string($rule['field']) || !is_string($rule['operator']) || !is_string($rule['value']) || !in_array($rule['operator'], array('equals', 'not_equals', 'empty', 'not_empty'), true) || array_key_exists('rules', $rule) || array_key_exists('relation', $rule)) {
                $errors[] = 'Invalid conditional rule for ' . $name; return null;
            }
            $clean[] = array('field' => sanitize_key($rule['field']), 'operator' => $rule['operator'], 'value' => sanitize_text_field($rule['value']));
        }
        return $compound ? array('relation' => $raw['relation'], 'rules' => $clean) : $clean[0];
    }
    public static function visible($field, $schema, $values) {
        $condition = $field['condition'] ?? null;
        if (!$condition) { return true; }
        $rules = self::rules($condition);
        if (!$rules || count($rules) > self::MAX_RULES || (isset($condition['rules']) && !in_array($condition['relation'] ?? '', array('all', 'any'), true))) { return false; }
        $results = array();
        foreach ($rules as $rule) {
            if (!is_array($rule) || !is_string($rule['field'] ?? null) || !is_string($rule['operator'] ?? null) || !is_string($rule['value'] ?? null)) { return false; }
            $source = null;
            foreach ($schema as $candidate) { if ($candidate['key'] === $rule['field']) { $source = $candidate; break; } }
            if (!$source || in_array($source['type'], array('repeater', 'group', 'flexible', 'link'), true) || !empty($source['condition']) || $source['key'] === $field['key']) { return false; }
            $value = $values[$source['key']] ?? '';
            $items = is_array($value) ? array_map('strval', array_filter($value, 'is_scalar')) : array((string) $value);
            $empty = !$items || $items === array('') || ($source['type'] === 'true_false' && $items === array('0'));
            $matches = in_array((string) $rule['value'], $items, true);
            switch ($rule['operator']) {
                case 'equals': $results[] = $matches; break;
                case 'not_equals': $results[] = !$matches; break;
                case 'empty': $results[] = $empty; break;
                case 'not_empty': $results[] = !$empty; break;
                default: return false;
            }
        }
        if (!$results) { return false; }
        return ($condition['relation'] ?? 'all') === 'any' ? in_array(true, $results, true) : !in_array(false, $results, true);
    }
}
