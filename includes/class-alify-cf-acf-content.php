<?php
if (!defined('ABSPATH')) { exit; }

/** Explicit existing-database ACF metadata reader. No ACF API, discovery, deletion or file transfer. */
final class Alify_CF_ACF_Content {
    private static function error($message) { return new WP_Error('alify_acf_content', $message); }
    private static function name($name) { return is_string($name) && preg_match('/^[a-z][a-z0-9_]{0,79}$/D', $name) && strpos($name, 'alify_cf_') !== 0; }
    private static function types() { return array('text' => 'text', 'textarea' => 'textarea', 'email' => 'email', 'url' => 'url', 'number' => 'number', 'true_false' => 'true_false', 'select' => 'select', 'checkbox' => 'checkbox', 'radio' => 'radio', 'button_group' => 'button_group', 'color_picker' => 'color', 'group' => 'group', 'repeater' => 'repeater', 'image' => 'image', 'file' => 'file', 'gallery' => 'gallery', 'post_object' => 'post_object', 'relationship' => 'relationship', 'taxonomy' => 'taxonomy', 'user' => 'user', 'link' => 'link'); }
    public static function descriptor($raw, $depth = 0) {
        if (!is_array($raw) || array_diff(array_keys($raw), array('key', 'type', 'multiple', 'id_map', 'sub_fields', 'name')) || !is_string($raw['key'] ?? null) || !preg_match('/^field_[a-zA-Z0-9_-]{1,100}$/D', $raw['key']) || !is_string($raw['type'] ?? null) || !isset(self::types()[$raw['type']]) || (isset($raw['name']) && !self::name($raw['name']))) { return self::error('Declare a supported ACF type and exact field_ key; unknown descriptor settings reject.'); }
        if (!$depth && array_key_exists('name', $raw)) { return self::error('Root names belong in from/to, not in the ACF descriptor.'); }
        if (isset($raw['multiple']) && (!in_array($raw['type'], array('select', 'post_object', 'taxonomy', 'user'), true) || !is_bool($raw['multiple']))) { return self::error('Multiple must be an explicit boolean on a supported selector.'); }
        $reference = in_array($raw['type'], array('image', 'file', 'gallery', 'post_object', 'relationship', 'taxonomy', 'user'), true);
        if ($reference) { if (!is_array($raw['id_map'] ?? null) || count($raw['id_map']) > 500) { return self::error('Reference/media fields require an explicit old:new ID map (at most 500 entries).'); } foreach ($raw['id_map'] as $old => $new) { if (!preg_match('/^[1-9][0-9]*$/D', (string) $old) || (!is_int($new) && !is_string($new)) || !preg_match('/^[1-9][0-9]*$/D', (string) $new) || (string) (int) $old !== (string) $old || (string) (int) $new !== (string) $new) { return self::error('ID maps require canonical positive integer IDs.'); } } }
        elseif (array_key_exists('id_map', $raw)) { return self::error('ID maps apply only to reference/media fields.'); }
        if (in_array($raw['type'], array('group', 'repeater'), true)) {
            if ($depth || !is_array($raw['sub_fields'] ?? null) || !$raw['sub_fields'] || array_values($raw['sub_fields']) !== $raw['sub_fields'] || count($raw['sub_fields']) > 20) { return self::error('Containers need 1–20 declared basic children; nesting is unsupported.'); }
            $names = array(); $keys = array(); foreach ($raw['sub_fields'] as $sub) { $result = self::descriptor($sub, 1); if (is_wp_error($result)) { return $result; } if (!self::name($sub['name'] ?? null) || isset($names[$sub['name']]) || isset($keys[$sub['key']])) { return self::error('Children need unique local names and field keys.'); } $names[$sub['name']] = true; $keys[$sub['key']] = true; }
        } elseif (array_key_exists('sub_fields', $raw)) { return self::error('Only Group/Repeater descriptors accept children.'); }
        return true;
    }
    public static function mapping($mapping) { return is_array($mapping) && !array_diff(array_keys($mapping), array('from', 'to', 'acf')) && self::name($mapping['from'] ?? null) && is_string($mapping['to'] ?? null) && preg_match('/^[a-z][a-z0-9_]{0,79}$/D', $mapping['to']) && self::descriptor($mapping['acf'] ?? null) === true; }
    private static function read($id, $key, &$snapshots) {
        if (strlen($key) > 240 || count($snapshots) >= 22000) { return self::error('ACF metadata key/read budget exceeded.'); } $values = get_post_meta($id, $key, false); if (count($values) !== 1 || is_object($values[0] ?? null) || is_resource($values[0] ?? null)) { return self::error('Every declared ACF value/reference marker must exist exactly once.'); } $snapshots[$key] = $values; return $values[0];
    }
    private static function decode($id, $name, $source, $target, &$snapshots) {
        $expected = self::types()[$source['type']]; if ($target['type'] !== $expected || !empty($source['multiple']) !== (!empty($target['multiple']) && in_array($target['type'], array('select', 'post_object', 'taxonomy', 'user'), true))) { return self::error('Source and destination type/multiplicity must match; convert separately, explicitly.'); }
        $marker = self::read($id, '_' . $name, $snapshots); if (is_wp_error($marker)) { return $marker; } if ($marker !== $source['key']) { return self::error('ACF field-key reference does not match the declared identity.'); }
        $value = self::read($id, $name, $snapshots); if (is_wp_error($value)) { return $value; }
        if ($expected === 'group' || $expected === 'repeater') {
            $children = $source['sub_fields']; if (array_column($children, 'name') !== array_column($target['sub_fields'], 'name')) { return self::error('Declare all destination children in the same order; no implicit flattening or dropped children.'); }
            if ($expected === 'group') { if ($value !== '') { return self::error('Only standard ACF flattened Group storage with an empty parent value is supported.'); } $count = 1; }
            else { if ((!is_int($value) && !is_string($value)) || !preg_match('/^(?:0|[1-9][0-9]?)$/D', (string) $value) || (int) $value > 50) { return self::error('ACF repeater count must be 0–50; no row truncation.'); } $count = (int) $value; }
            $rows = array(); for ($row = 0; $row < $count; $row++) { $fields = array(); foreach ($children as $index => $child) { $child_name = $name . '_' . ($expected === 'repeater' ? $row . '_' : '') . $child['name']; $decoded = self::decode($id, $child_name, $child, $target['sub_fields'][$index], $snapshots); if (is_wp_error($decoded)) { return $decoded; } $fields[$child['name']] = $decoded; } $rows[] = $fields; } return $expected === 'group' ? $rows[0] : $rows;
        }
        $list = in_array($expected, array('checkbox', 'gallery', 'relationship'), true) || !empty($source['multiple']);
        if (isset($source['id_map'])) {
            if ($list && ($value === '' || $value === false)) { $value = array(); }
            if ($list && (!is_array($value) || array_values($value) !== $value || count($value) > 50)) { return self::error('Expected a bounded complete ID list, never formatted objects.'); }
            if (!$list && is_array($value)) { if (array_values($value) !== $value || count($value) > 1) { return self::error('Single references accept only scalar IDs or lists of at most one ID.'); } $value = $value ? $value[0] : ''; }
            if (!$list && ($value === '' || $value === '0' || $value === 0 || $value === false)) { return ''; }
            $ids = $list ? $value : array($value); $mapped = array(); foreach ($ids as $old) { if ((!is_int($old) && !is_string($old)) || !preg_match('/^[1-9][0-9]*$/D', (string) $old) || !array_key_exists($old, $source['id_map'])) { return self::error('Every nonempty source ID needs an explicit mapping, including same-site identity mappings.'); } $mapped[] = (string) $source['id_map'][$old]; } if (count(array_unique($mapped)) !== count($mapped)) { return self::error('ID remapping cannot merge or discard selected items.'); } return $list ? $mapped : $mapped[0];
        }
        if ($expected === 'true_false') { if (!in_array($value, array('', '0', '1', 0, 1, false, true), true)) { return self::error('Malformed ACF boolean.'); } return !empty($value) ? '1' : '0'; }
        if ($expected === 'link' && ($value === '' || $value === false || $value === array('url' => '', 'title' => '', 'target' => ''))) { return array(); }
        if ($expected === 'link') { return is_array($value) && !array_diff(array_keys($value), array('url', 'title', 'target')) ? $value : self::error('Expected an ACF link record.'); }
        if ($list) { if ($value === '' || $value === false) { return array(); } if (!is_array($value) || array_values($value) !== $value || count($value) > 100 || count(array_filter($value, 'is_scalar')) !== count($value)) { return self::error('Expected a complete static choice-key list.'); } return array_map('strval', $value); }
        if ($expected === 'select' && !$list && is_array($value)) { if (array_values($value) !== $value || count($value) > 1) { return self::error('Single Select cannot discard multiple keys.'); } $value = $value ? $value[0] : ''; }
        return is_scalar($value) ? (string) $value : self::error('Expected a raw scalar, never an ACF formatted object.');
    }
    public static function prepare($post_id, $mapping, $target) {
        if (!self::mapping($mapping)) { return self::error('Malformed ACF content mapping.'); } $snapshots = array(); $value = self::decode($post_id, $mapping['from'], $mapping['acf'], $target, $snapshots); return is_wp_error($value) ? $value : array('value' => $value, 'snapshots' => $snapshots);
    }
    public static function equivalent($field, $source, $clean) {
        if (in_array($field['type'], array('group', 'repeater'), true)) { $sources = $field['type'] === 'group' ? array($source) : $source; $cleans = $field['type'] === 'group' ? array($clean) : $clean; if (!is_array($cleans) || count($sources) !== count($cleans)) { return false; } foreach ($sources as $index => $row) { foreach ($field['sub_fields'] as $sub) { if (!self::equivalent($sub, $row[$sub['name']] ?? null, $cleans[$index][$sub['name']] ?? null)) { return false; } } } return true; }
        if ($field['type'] === 'link') { if ($source === array() && $clean === array()) { return true; } if (!is_array($source) || !is_array($clean) || count($source) !== 3 || count($clean) !== 3) { return false; } foreach (array('url', 'title', 'target') as $key) { if (!isset($source[$key], $clean[$key]) || $source[$key] !== $clean[$key]) { return false; } } return true; }
        if ($field['type'] === 'checkbox' || Alify_CF_Choice::multiple($field)) { if (!is_array($source) || !is_array($clean)) { return false; } sort($source, SORT_STRING); sort($clean, SORT_STRING); return $source === $clean; }
        if (in_array($field['type'], array('gallery', 'relationship'), true) || Alify_CF_Reference_Settings::multiple($field) || Alify_CF_Selector::multiple($field)) { return is_array($source) && is_array($clean) && array_map('strval', $source) === array_map('strval', $clean); }
        return is_array($source) ? $source === $clean : (is_scalar($clean) && (string) $source === (string) $clean);
    }
    public static function unchanged($post_id, $snapshots) { if (!is_array($snapshots)) { return false; } wp_cache_delete($post_id, 'post_meta'); foreach ($snapshots as $key => $value) { if (get_post_meta($post_id, $key, false) !== $value) { return false; } } return true; }
}
