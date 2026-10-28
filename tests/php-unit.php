<?php
// Isolated contract tests with WordPress stubs, not a substitute for WP integration tests.
define('ABSPATH', __DIR__);
$meta = array(); $transients = array(); $posts = array(); $allowed = true; $denied = array();
function wp_is_post_revision($id) { return false; }
function current_user_can($cap, $id = 0) { global $allowed, $denied; return $allowed && !in_array($id, $denied, true); }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
function sanitize_text_field($s) { return is_scalar($s) ? trim(strip_tags((string) $s)) : ''; }
function sanitize_textarea_field($s) { return sanitize_text_field($s); }
function sanitize_key($s) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($s)); }
function wp_unslash($s) { return is_array($s) ? array_map('wp_unslash', $s) : (is_string($s) ? stripslashes($s) : $s); }
function wp_slash($s) { return is_array($s) ? array_map('wp_slash', $s) : (is_string($s) ? addslashes($s) : $s); }
function wp_generate_uuid4() { return 'test-uuid'; }
function is_email($s) { return filter_var($s, FILTER_VALIDATE_EMAIL); }
function sanitize_email($s) { return $s; }
function esc_url_raw($s, $protocols) { return preg_match('~^https?://~', $s) ? $s : ''; }
function get_post($id) { global $posts; return $posts[$id] ?? null; }
function wp_attachment_is_image($id) { return get_post($id)->post_mime_type === 'image/png'; }
function get_post_types($args, $output) { return array('post' => (object) array('name' => 'post'), 'page' => (object) array('name' => 'page')); }
function get_post_meta($id, $key, $single) { global $meta; return $meta[$id][$key] ?? ''; }
function metadata_exists($type, $id, $key) { global $meta; return isset($meta[$id][$key]); }
function get_the_ID() { return 10; }
function absint($value) { return abs((int) $value); }
function update_post_meta($id, $key, $value) { global $meta; $meta[$id][$key] = wp_unslash($value); }
function get_posts($args) {
    global $posts; $result = array();
    foreach ($posts as $id => $post) {
        if ($post->post_type !== $args['post_type'] || in_array($id, $args['exclude'] ?? array(), true)) { continue; }
        if (!in_array($post->post_status, (array) $args['post_status'], true)) { continue; }
        if (get_post_meta($id, $args['meta_key'], true) === $args['meta_value']) { $result[] = $post; }
    }
    return $result;
}
function get_current_user_id() { return 1; }
function set_transient($key, $value, $ttl) { global $transients; $transients[$key] = $value; }
require __DIR__ . '/../includes/class-alify-cf.php';
require __DIR__ . '/../includes/functions.php';
$count = 0;
function same($expected, $actual, $message) { global $count; if ($expected !== $actual) { throw new Exception($message . ': ' . var_export($actual, true)); } $count++; }
function field($name, $type = 'text', $required = false) { return array('name' => $name, 'key' => 'field_' . $name, 'label' => ucfirst($name), 'type' => $type, 'required' => $required, 'choices' => "a : Alpha\nb : Beta", 'default' => '', 'instructions' => ''); }
function configure($id, $fields, $location = 'post') {
    $_POST = array('alify_schema_nonce' => 'valid', 'alify_schema' => wp_slash(json_encode($fields)), 'alify_location' => $location);
    Alify_CF::save($id, get_post($id));
}
$posts[1] = (object) array('ID' => 1, 'post_type' => Alify_CF::GROUP, 'post_status' => 'publish', 'post_title' => 'Details');
$posts[2] = (object) array('ID' => 2, 'post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => 'Other');
$posts[10] = (object) array('ID' => 10, 'post_type' => 'post', 'post_status' => 'draft');
$posts[50] = (object) array('ID' => 50, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/png');
$posts[51] = (object) array('ID' => 51, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'application/pdf');
same(50, Alify_CF::clean_value(field('hero', 'image'), '50'), 'Image IDs are integers');
same('', Alify_CF::clean_value(field('hero', 'image'), '51'), 'Reject non-image');
same('', Alify_CF::clean_value(field('hero', 'file'), '10'), 'Reject non-attachment');
same('', Alify_CF::clean_value(field('hero', 'file'), '-50'), 'Reject negative ID');
same(51, Alify_CF::clean_value(field('download', 'file'), '51'), 'File accepts PDF');
same('', Alify_CF::clean_value(field('date', 'date'), "2026-01-01\0"), 'Null date safe');
same('', Alify_CF::clean_value(field('date', 'date'), '2026-02-30'), 'Invalid calendar date');
same('2024-02-29', Alify_CF::clean_value(field('date', 'date'), '2024-02-29'), 'Leap date');
same(array('a'), Alify_CF::clean_value(field('tags', 'checkbox'), array('a', 'evil')), 'Choice allowlist');
configure(1, array(field('title')));
same('title', Alify_CF::schema(1)[0]['name'], 'Schema saves');
configure(2, array(field('title')));
same(array(), Alify_CF::schema(2), 'Duplicate across draft groups rejected');
same(true, isset($transients['alify_cf_errors_1_2']), 'Schema error notice');
configure(2, array(field('title')), 'page');
same('title', Alify_CF::schema(2)[0]['name'], 'Different post type can reuse name');
configure(1, array(field('bad-name')));
same('title', Alify_CF::schema(1)[0]['name'], 'Invalid names retain entire schema');
configure(1, array(field('title'), field('title')));
same(1, count(Alify_CF::schema(1)), 'Duplicate schema rejected atomically');
configure(1, array(field('fresh')), 'nonsense');
same('title', Alify_CF::schema(1)[0]['name'], 'Invalid location retains schema');
configure(1, array(field('email', 'email', true), field('hero', 'image'), field('path'), field('tags', 'checkbox')));
$meta[10]['_alify_cf_email'] = 'old@example.com';
$meta[10]['_alify_cf_hero'] = 50;
$meta[10]['_alify_cf_tags'] = array('a');
$_POST = array('alify_values_nonce_1' => 'valid', 'alify_values' => array(1 => array('email' => 'bad-email', 'hero' => '51', 'path' => wp_slash('C:\\test'))));
Alify_CF::save(10, $posts[10]);
same('old@example.com', alify_get_field('email', 10), 'Invalid required value retained');
same(50, alify_get_field('hero', 10), 'Invalid media retained');
same('C:\\test', alify_get_field('path', 10), 'Backslash round trip');
same(array(), alify_get_field('tags', 10), 'Unchecked choices cleared');
same(2, count($transients['alify_cf_errors_1_10']), 'Field-specific save notices');
$_POST['alify_values_nonce_1'] = 'invalid';
$_POST['alify_values'][1]['path'] = 'overwrite';
Alify_CF::save(10, $posts[10]);
same('C:\\test', alify_get_field('path', 10), 'Invalid nonce cannot overwrite');
$allowed = false;
$_POST['alify_values_nonce_1'] = 'valid';
Alify_CF::save(10, $posts[10]);
same('C:\\test', alify_get_field('path', 10), 'Permission check');
$allowed = true;
$_POST['alify_values'][1]['hero'] = '';
Alify_CF::save(10, $posts[10]);
same('', alify_get_field('hero', 10), 'Optional media can be cleared');
$denied = array(50);
$_POST['alify_values'][1]['hero'] = '50';
Alify_CF::save(10, $posts[10]);
same('', alify_get_field('hero', 10), 'Attachment selection permission enforced');
same(null, alify_get_field('unknown'), 'Missing value is null');
// Conditional logic: stable references, operator semantics and authoritative saves.
$source = field('enabled', 'true_false');
$target = field('details', 'text', true);
$target['condition'] = array('field' => $source['key'], 'operator' => 'equals', 'value' => '1');
configure(1, array($source, $target));
same('field_enabled', Alify_CF::schema(1)[1]['condition']['field'], 'Rule persisted');
$schema = Alify_CF::schema(1);
same(false, Alify_CF::visible($target, $schema, array('field_enabled' => '0')), 'False toggle hides field');
same(true, Alify_CF::visible($target, $schema, array('field_enabled' => '1')), 'True toggle shows field');
$meta[10]['_alify_cf_details'] = 'keep me';
$transients = array();
$_POST = array('alify_values_nonce_1' => 'valid', 'alify_values' => array(1 => array('details' => 'forged hidden value')));
Alify_CF::save(10, $posts[10]);
same('keep me', alify_get_field('details', 10), 'Hidden forged values ignored');
same(array(), $transients, 'Hidden required field does not raise errors');
$_POST['alify_values'][1] = array('enabled' => '1', 'details' => '');
Alify_CF::save(10, $posts[10]);
same('keep me', alify_get_field('details', 10), 'Visible required field retains value');
same(true, isset($transients['alify_cf_errors_1_10']), 'Visible required field raises error');
$_POST['alify_values'][1]['details'] = 'new value';
Alify_CF::save(10, $posts[10]);
same('new value', alify_get_field('details', 10), 'Visible field saves');
foreach (array('equals' => false, 'not_equals' => true, 'empty' => true, 'not_empty' => false) as $operator => $expected) {
    $target['condition']['operator'] = $operator;
    same($expected, Alify_CF::visible($target, $schema, array('field_enabled' => '0')), 'Toggle operator ' . $operator);
}
$choice = field('choices', 'checkbox');
$target['condition'] = array('field' => $choice['key'], 'operator' => 'equals', 'value' => 'a');
same(true, Alify_CF::visible($target, array($choice, $target), array($choice['key'] => array('a', 'b'))), 'Checkbox membership');
$target['condition']['operator'] = 'empty';
same(true, Alify_CF::visible($target, array($choice, $target), array($choice['key'] => array())), 'Empty checkbox group');
$previous = Alify_CF::schema(1);
$target['condition']['field'] = 'missing';
configure(1, array($source, $target));
same($previous, Alify_CF::schema(1), 'Missing source rejects schema');
$target['condition']['field'] = $target['key'];
configure(1, array($source, $target));
same($previous, Alify_CF::schema(1), 'Self-reference rejects schema');
$source['condition'] = array('field' => $target['key'], 'operator' => 'empty', 'value' => '');
$target['condition']['field'] = $source['key'];
configure(1, array($source, $target));
same($previous, Alify_CF::schema(1), 'Chains and cycles reject schema');
$source['condition'] = null;
$target['condition']['operator'] = 'eval';
configure(1, array($source, $target));
same($previous, Alify_CF::schema(1), 'Invalid operator rejects schema');
$target['condition'] = array('field' => array());
configure(1, array($source, $target));
same($previous, Alify_CF::schema(1), 'Malformed rule rejects schema safely');
echo "PASS: $count PHP contract assertions\n";
