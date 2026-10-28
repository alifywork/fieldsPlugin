<?php
if (!defined('ABSPATH')) { exit; }

/** OR groups of AND predicates, separate from field-value conditions. */
final class Alify_CF_Location_Rules {
    private static $custom = array();
    public static function register($name, $match, $validate = null) {
        if (!is_string($name) || !preg_match('/^x_[a-z][a-z0-9_]{0,39}$/D', $name) || isset(self::$custom[$name]) || !is_callable($match) || ($validate !== null && !is_callable($validate))) { return new WP_Error('alify_location_rule', 'Custom predicates need a unique x_ name and callable matcher.'); }
        self::$custom[$name] = array('match' => $match, 'validate' => $validate); return true;
    }
    public static function clean($raw, &$errors) {
        if ($raw === null || $raw === array() || $raw === '') { return array(); }
        if (is_string($raw) && strlen($raw) <= 32768) { $raw = json_decode($raw, true, 8); }
        if (!is_array($raw) || array_values($raw) !== $raw || count($raw) > 10) { $errors[] = 'Locations need up to 10 OR groups.'; return array(); }
        $out = array(); $count = 0;
        foreach ($raw as $group) {
            if (!is_array($group) || !$group || array_values($group) !== $group || count($group) > 10) { $errors[] = 'Each location group needs 1–10 AND predicates.'; continue; } $clean = array();
            foreach ($group as $rule) {
                if (++$count > 50 || !is_array($rule) || !is_string($rule['param'] ?? null) || !is_string($rule['value'] ?? null) || strlen($rule['value']) > 200 || !in_array($rule['operator'] ?? '', array('==', '!='), true)) { $errors[] = 'Invalid location predicate (maximum 50).'; continue; }
                $param = $rule['param']; $value = $rule['value']; $valid = false;
                if ($param === 'post_id') { $valid = preg_match('/^[1-9][0-9]*$/D', $value) && (string) (int) $value === $value && get_post((int) $value); }
                elseif ($param === 'status') { $valid = in_array($value, get_post_stati(), true); }
                elseif ($param === 'template') { $valid = (bool) preg_match('~^(default|[a-zA-Z0-9_/-]+\.php)$~D', $value) && strpos($value, '..') === false; }
                elseif ($param === 'term') { if (preg_match('/^([a-z][a-z0-9_]*):([1-9][0-9]*)$/D', $value, $match)) { $term = get_term((int) $match[2], $match[1]); $valid = $term && !is_wp_error($term) && $term->taxonomy === $match[1]; } }
                elseif ($param === 'current_user_role') { $valid = isset(wp_roles()->roles[$value]); }
                elseif (isset(self::$custom[$param])) { $valid = self::$custom[$param]['validate'] === null || call_user_func(self::$custom[$param]['validate'], $value) === true; }
                if (!$valid) { $errors[] = 'Unknown/unavailable location predicate: ' . $param; continue; }
                $clean[] = array('param' => $param, 'operator' => $rule['operator'], 'value' => $value);
            }
            $out[] = $clean;
        }
        return $out;
    }
    public static function matches($rules, $location, $object) {
        if (!$rules) { return true; } $errors = array(); $rules = self::clean($rules, $errors); if ($errors) { return false; }
        foreach ($rules as $group) { $all = true; foreach ($group as $rule) {
            $param = $rule['param']; $value = $rule['value']; $match = false;
            $post = is_int($object) || (is_string($object) && ctype_digit($object)) ? get_post((int) $object) : null;
            if ($param === 'post_id') { $match = $post && (string) $post->ID === $value; }
            elseif ($param === 'status') { $match = $post && $post->post_status === $value; }
            elseif ($param === 'template') { $match = $post && (get_page_template_slug($post->ID) ?: 'default') === $value; }
            elseif ($param === 'term') { list($taxonomy, $id) = explode(':', $value, 2); $match = $post ? has_term((int) $id, $taxonomy, $post->ID) : ($object === 'term:' . $id && $location === 'term:' . $taxonomy); }
            elseif ($param === 'current_user_role') { $match = in_array($value, wp_get_current_user()->roles, true); }
            elseif (isset(self::$custom[$param])) { $match = call_user_func(self::$custom[$param]['match'], $value, $location, $object) === true; }
            // Unsupported object/predicate combinations fail closed, including inequality.
            if (in_array($param, array('post_id', 'status', 'template'), true) && !$post) { $all = false; break; }
            if ($rule['operator'] === '!=' ? $match : !$match) { $all = false; break; }
        } if ($all) { return true; } }
        return false;
    }
    public static function group($id, $location, $object) { $registered = Alify_CF_API::definition($id); return self::matches($registered ? ($registered['rules'] ?? array()) : get_post_meta($id, '_alify_cf_location_rules', true), $location, $object); }
}
