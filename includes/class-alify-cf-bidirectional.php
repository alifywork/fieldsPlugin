<?php
if (!defined('ABSPATH')) { exit; }

/** Explicit paired top-level post-reference lists. Optimistic multi-object coordinator, not SQL transactions. */
final class Alify_CF_Bidirectional {
    private static $pairs = array();
    private static $active = false;
    public static function active() { return self::$active; }
    public static function register($key, $left, $right) {
        if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,49}$/D', $key) || count(self::$pairs) >= 20) { return new WP_Error('alify_pair', 'Use a stable key; at most 20 pairs.'); }
        foreach (array($left, $right) as $end) { if (!is_array($end) || count($end) !== 2 || array_diff(array_keys($end), array('type', 'field')) || !is_string($end['type']) || !is_string($end['field']) || !preg_match('/^[a-z][a-z0-9_]*$/D', $end['field']) || !post_type_exists($end['type'])) { return new WP_Error('alify_pair', 'Each endpoint needs type and field, in that order.'); } foreach (self::$pairs as $pair) { if (in_array($end, $pair, true)) { return new WP_Error('alify_pair', 'An endpoint may belong to one pair only.'); } } }
        if (isset(self::$pairs[$key])) { return new WP_Error('alify_pair', 'Pair already registered.'); } self::$pairs[$key] = array($left, $right); return true;
    }
    public static function touches($values, $id) { if ((!is_int($id) && !is_string($id)) || !ctype_digit((string) $id)) { return false; } $post = get_post($id); if (!$post || !is_array($values)) { return false; } foreach (self::$pairs as $pair) { foreach ($pair as $end) { if ($end['type'] === $post->post_type && array_key_exists($end['field'], $values)) { return true; } } } return false; }
    private static function field($id, $name) { $post = get_post($id); $field = $post ? Alify_CF_API::field($post->post_type, $name, $id) : null; return $field && ($field['type'] === 'relationship' || ($field['type'] === 'post_object' && Alify_CF_Reference_Settings::multiple($field))) ? $field : null; }
    private static function fresh($id, $name) { wp_cache_delete($id, 'post_meta'); return array(metadata_exists('post', $id, '_alify_cf_' . $name), alify_get_field($name, $id)); }
    private static function same($a, $b) { return is_array($a) || is_array($b) ? $a === $b : (string) $a === (string) $b; }
    public static function write($values, $id) {
        if (self::$active) { return new WP_Error('alify_reentrant', 'Recursive relationship writes are not permitted.'); }
        if ((!is_int($id) && !is_string($id)) || !ctype_digit((string) $id) || !current_user_can('edit_post', (int) $id)) { return new WP_Error('alify_forbidden', 'Source edit permission required.'); }
        $token = wp_generate_uuid4(); if (!add_option('alify_cf_pair_lock', $token, '', false)) { return new WP_Error('alify_busy', 'Another paired write is running; retry later. A stranded lock requires administrator review.'); }
        self::$active = true; $journal = array(); $written = array(); $backup = '';
        try {
            $clean = Alify_CF_API::write($values, $id, false, true); if (is_wp_error($clean)) { return $clean; } $post = get_post($id); $plan = array($id => $clean);
            foreach (self::$pairs as $pair) { foreach ($pair as $index => $end) {
                if ($end['type'] !== $post->post_type || !array_key_exists($end['field'], $clean)) { continue; }
                $field = self::field($id, $end['field']); if (!$field) { return new WP_Error('alify_pair', 'Paired endpoints must be uniquely defined top-level post-reference lists.'); }
                $other = $pair[1 - $index]; $before = alify_get_field($end['field'], $id); $before = is_array($before) ? array_map('intval', $before) : array(); $after = array_map('intval', $clean[$end['field']]);
                foreach (array_unique(array_merge($before, $after)) as $target) {
                    if ($target === (int) $id || !current_user_can('edit_post', $target) || get_post_type($target) !== $other['type']) { return new WP_Error('alify_pair_permission', 'Every linked/unlinked destination must be editable and match its endpoint type; self links are not supported.'); }
                    $reverse = self::field($target, $other['field']); if (!$reverse) { return new WP_Error('alify_pair', 'Reverse definition unavailable.'); }
                    if (!isset($plan[$target][$other['field']])) { $old = alify_get_field($other['field'], $target); $plan[$target][$other['field']] = is_array($old) ? array_values($old) : array(); }
                    $existing = $plan[$target][$other['field']]; $ids = in_array($target, $after, true) ? $existing : array_values(array_filter($existing, function($value) use ($id) { return (int) $value !== (int) $id; }));
                    if (in_array($target, $after, true) && !in_array((int) $id, array_map('intval', $ids), true)) { $ids[] = (string) $id; } $plan[$target][$other['field']] = $ids;
                    if (count($plan) > 101) { return new WP_Error('alify_pair_limit', 'At most 100 related objects may participate.'); }
                }
            } }
            $payloads = array();
            foreach ($plan as $object => $fields) { foreach ($fields as $name => $value) { $post_field = Alify_CF_API::field(get_post_type($object), $name, $object); if (!$post_field) { return new WP_Error('alify_pair', 'Definition changed during planning.'); } $payloads[$object][$name] = Alify_CF_Publication::stored_payload($post_field, $value); $journal[$object][$name] = self::fresh($object, $name); } $validated = Alify_CF_API::write($payloads[$object], $object, false, true); if (is_wp_error($validated)) { return $validated; } $plan[$object] = $validated; }
            if (!Alify_CF_Composition::budget($journal) || !Alify_CF_Composition::budget($plan)) { return new WP_Error('alify_pair_limit', 'Paired snapshot exceeds the shared payload budget.'); }
            $backup = 'alify_cf_pair_backup_' . wp_generate_uuid4(); if (!set_transient($backup, array('creator' => get_current_user_id(), 'snapshots' => $journal), 1800)) { return new WP_Error('alify_backup', 'Recovery snapshot could not be stored; no write attempted.'); }
            foreach ($plan as $object => $fields) {
                foreach ($journal[$object] as $name => $snapshot) { if (self::fresh($object, $name) !== $snapshot) { throw new RuntimeException('Concurrent changes detected.'); } }
                // Record intent before invoking external hooks: a throwing write can have stored data already.
                $written[$object] = $fields; $result = Alify_CF_API::write($payloads[$object], $object); if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
                foreach ($fields as $name => $value) { if (!self::same(self::fresh($object, $name)[1], $value)) { throw new RuntimeException('Paired storage verification failed.'); } }
            }
            delete_transient($backup); return true;
        } catch (Throwable $error) {
            $restored = true; foreach (array_reverse($written, true) as $object => $fields) { foreach ($fields as $name => $value) { $snapshot = $journal[$object][$name]; $current = self::fresh($object, $name); if ($current === $snapshot) { continue; } if (!self::same($current[1], $value)) { $restored = false; continue; } if ($snapshot[0]) { update_post_meta($object, '_alify_cf_' . $name, wp_slash($snapshot[1])); } else { delete_post_meta($object, '_alify_cf_' . $name); } if (self::fresh($object, $name) !== $snapshot) { $restored = false; } } }
            if ($restored && $backup) { delete_transient($backup); } return new WP_Error($restored ? 'alify_pair_failed' : 'alify_rollback_failed', $restored ? 'Paired write failed; original values restored.' : 'Paired write failed; unknown changes were not overwritten. Recovery backup retained for 30 minutes.', $restored ? null : array('backup' => $backup));
        } finally { self::$active = false; if (get_option('alify_cf_pair_lock') === $token) { delete_option('alify_cf_pair_lock'); } }
    }
    public static function editor($updates, $id) { $payload = array(); foreach ($updates as $name => $value) { $field = Alify_CF_API::field(get_post_type($id), $name, $id); if (!$field) { return new WP_Error('alify_pair', 'Editor definition unavailable.'); } $payload[$name] = Alify_CF_Publication::stored_payload($field, $value); } return self::write($payload, $id); }
    public static function backup($key) { if (!is_string($key) || !preg_match('/^alify_cf_pair_backup_[a-f0-9-]{36}$/D', $key)) { return new WP_Error('alify_backup', 'Invalid recovery key.'); } $data = get_transient($key); if (!is_array($data) || (int) $data['creator'] !== get_current_user_id()) { return new WP_Error('alify_forbidden', 'Backup unavailable to this account.'); } foreach ($data['snapshots'] as $id => $unused) { if (!current_user_can('edit_post', $id)) { return new WP_Error('alify_forbidden', 'Recovery requires every affected object permission.'); } } return $data['snapshots']; }
}
