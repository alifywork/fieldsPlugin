<?php
// Isolated contract tests with WordPress stubs, not a substitute for WP integration tests.
define('ABSPATH', __DIR__);
$meta = array(); $transients = array(); $posts = array(); $allowed = true; $denied = array();
function post_password_required($post) { return !empty($post->post_password); }
function is_post_publicly_viewable($post) { return $post->post_status === 'publish'; }
function wp_is_post_revision($id) { return false; }
function current_user_can($cap, $id = 0) { global $allowed, $denied; return $allowed && !in_array($id, $denied, true); }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid'; }
function sanitize_text_field($s) { return is_scalar($s) ? trim(strip_tags((string) $s)) : ''; }
// Isolated format stub only; real WordPress integration verifies KSES security.
function wp_kses_post($s) { return strip_tags($s, '<p><strong><em><a><ul><ol><li><br>'); }
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
function get_current_user_id() { return $GLOBALS['test_user_id'] ?? 1; }
function set_transient($key, $value, $ttl) { global $transients; $transients[$key] = $value; }
require __DIR__ . '/../includes/class-alify-cf.php';
require __DIR__ . '/../includes/functions.php';
$count = 0;
function same($expected, $actual, $message) { global $count; if ($expected !== $actual) { throw new Exception($message . ': ' . var_export($actual, true)); } $count++; }
function field($name, $type = 'text', $required = false) { return array('name' => $name, 'key' => 'field_' . $name, 'label' => ucfirst($name), 'type' => $type, 'required' => $required, 'choices' => "a : Alpha\nb : Beta", 'default' => '', 'instructions' => ''); }
function save_fixture($id, $post) {
    foreach (($_POST['alify_values'] ?? array()) as $group => $values) {
        if (is_array($values)) { $_POST['alify_values'][$group]['__group_complete'] = '1'; }
    }
    Alify_CF::save($id, $post);
}
function configure($id, $fields, $location = 'post') {
    $_POST = array('alify_schema_nonce' => 'valid', 'alify_schema' => wp_slash(json_encode($fields)), 'alify_location' => $location);
    save_fixture($id, get_post($id));
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
save_fixture(10, $posts[10]);
same('old@example.com', alify_get_field('email', 10), 'Invalid required value retained');
same(50, alify_get_field('hero', 10), 'Invalid media retained');
same('C:\\test', alify_get_field('path', 10), 'Backslash round trip');
same(array(), alify_get_field('tags', 10), 'Unchecked choices cleared');
same(2, count($transients['alify_cf_errors_1_10']), 'Field-specific save notices');
$_POST['alify_values_nonce_1'] = 'invalid';
$_POST['alify_values'][1]['path'] = 'overwrite';
save_fixture(10, $posts[10]);
same('C:\\test', alify_get_field('path', 10), 'Invalid nonce cannot overwrite');
$allowed = false;
$_POST['alify_values_nonce_1'] = 'valid';
save_fixture(10, $posts[10]);
same('C:\\test', alify_get_field('path', 10), 'Permission check');
$allowed = true;
$_POST['alify_values'][1]['hero'] = '';
save_fixture(10, $posts[10]);
same('', alify_get_field('hero', 10), 'Optional media can be cleared');
$denied = array(50);
$_POST['alify_values'][1]['hero'] = '50';
save_fixture(10, $posts[10]);
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
save_fixture(10, $posts[10]);
same('keep me', alify_get_field('details', 10), 'Hidden forged values ignored');
same(array(), $transients, 'Hidden required field does not raise errors');
$_POST['alify_values'][1] = array('enabled' => '1', 'details' => '');
save_fixture(10, $posts[10]);
same('keep me', alify_get_field('details', 10), 'Visible required field retains value');
same(true, isset($transients['alify_cf_errors_1_10']), 'Visible required field raises error');
$_POST['alify_values'][1]['details'] = 'new value';
save_fixture(10, $posts[10]);
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
// Repeaters: local schema, bounded payload, atomic rows, permissions and helpers.
$denied = array();
$repeater = field('team', 'repeater');
$repeater['sub_fields'] = array(field('name', 'text', true), field('email', 'email'), field('photo', 'image'), field('tags', 'checkbox'));
configure(1, array($repeater));
$stored = Alify_CF::schema(1);
same(4, count($stored[0]['sub_fields']), 'Repeater schema saved');
same(array(), $stored[0]['default'], 'Repeater has no default rows');
$payload = array('__complete' => '1', 'rows' => array('second' => array('name' => 'B', '__row_complete' => '1'), 'first' => array('name' => 'A', 'email' => 'a@example.com', 'tags' => array('a'), '__row_complete' => '1')));
$clean = Alify_CF_Repeater::clean($stored[0], $payload);
same('B', $clean[0]['name'], 'Rows follow submitted order');
same('A', $clean[1]['name'], 'Rows normalize indexes');
same(array('a'), $clean[1]['tags'], 'Checkbox subfields sanitized');
same('', Alify_CF_Repeater::clean($stored[0], array('rows' => array())), 'Missing end sentinel rejected');
same('', Alify_CF_Repeater::clean($stored[0], array('__complete' => '1', 'rows' => 'bad')), 'Malformed rows rejected');
same('', Alify_CF_Repeater::clean($stored[0], array('__complete' => '1', 'rows' => array(array('name' => 'A')))), 'Truncated row rejected');
$bad = $payload; $bad['rows']['first']['email'] = 'invalid';
same('', Alify_CF_Repeater::clean($stored[0], $bad), 'One invalid subfield rejects all rows');
$bad = $payload; $bad['rows']['first']['name'] = '';
same('', Alify_CF_Repeater::clean($stored[0], $bad), 'Required subfield checked');
$bad = array('__complete' => '1', 'rows' => array_fill(0, 51, array('name' => 'A', '__row_complete' => '1')));
same('', Alify_CF_Repeater::clean($stored[0], $bad), 'Row limit enforced');
$bad = $payload; $bad['rows']['first']['photo'] = '51';
same('', Alify_CF_Repeater::clean($stored[0], $bad), 'Media subfield validates type');
$bad['rows']['first']['photo'] = '50'; $denied = array(50);
same('', Alify_CF_Repeater::clean($stored[0], $bad, array(), true), 'New media selection needs permission');
same(true, is_array(Alify_CF_Repeater::clean($stored[0], $bad, array(array('photo' => 50)), true)), 'Existing media can survive row reordering');
$denied = array();
$_POST = array('alify_values_nonce_1' => 'valid', 'alify_values' => array(1 => array('team' => $payload)));
save_fixture(10, $posts[10]);
same('B', alify_get_rows('team', 10)[0]['name'], 'Rows saved and retrieved');
$_POST['alify_values'][1] = array();
save_fixture(10, $posts[10]);
same(2, count(alify_get_rows('team', 10)), 'Missing repeater preserves data');
$_POST['alify_values'][1]['team'] = array('__complete' => '1');
save_fixture(10, $posts[10]);
same(array(), alify_get_rows('team', 10), 'Explicit empty repeater clears rows');
$previous = Alify_CF::schema(1);
$bad_schema = $repeater; $bad_schema['sub_fields'][] = field('name');
configure(1, array($bad_schema));
same($previous, Alify_CF::schema(1), 'Duplicate local names reject entire schema');
$bad_schema['sub_fields'] = array(field('nested', 'repeater'));
configure(1, array($bad_schema));
same($previous, Alify_CF::schema(1), 'Nested repeaters rejected');
$bad_schema['sub_fields'] = array();
configure(1, array($bad_schema));
same($previous, Alify_CF::schema(1), 'Empty subfield schema rejected');
$target = field('target'); $target['condition'] = array('field' => $repeater['key'], 'operator' => 'empty', 'value' => '');
configure(1, array($repeater, $target));
same($previous, Alify_CF::schema(1), 'Repeater cannot be a condition source');
// A whole repeater may be a conditional target; its hidden rows are preserved.
$toggle = field('toggle', 'true_false');
$repeater['condition'] = array('field' => $toggle['key'], 'operator' => 'equals', 'value' => '1');
configure(1, array($toggle, $repeater));
$meta[10]['_alify_cf_team'] = $clean;
$_POST = array('alify_values_nonce_1' => 'valid', 'alify_values' => array(1 => array('team' => array('__complete' => '1'))));
save_fixture(10, $posts[10]);
same(2, count(alify_get_rows('team', 10)), 'Hidden repeater payload ignored');
same(array(), alify_get_rows('missing', 10), 'Missing rows return empty array');
$required_repeater = $stored[0]; $required_repeater['required'] = true;
configure(1, array($required_repeater));
$meta[10]['_alify_cf_team'] = $clean;
$_POST = array('alify_values_nonce_1' => 'valid', 'alify_values' => array(1 => array('team' => array('__complete' => '1'))));
save_fixture(10, $posts[10]);
same(2, count(alify_get_rows('team', 10)), 'Required repeater needs at least one row');
$_POST['alify_values'][1]['team'] = array('__complete' => '1', 'rows' => array(array('name' => wp_slash('C:\\team'), '__row_complete' => '1')));
save_fixture(10, $posts[10]);
same('C:\\team', alify_get_rows('team', 10)[0]['name'], 'Repeater backslash round trip');
$invalid_subfields = $repeater; $invalid_subfields['sub_fields'] = array_fill(0, 21, field('x'));
$errors = array();
Alify_CF_Repeater::schema($invalid_subfields['sub_fields'], $errors);
same(true, count($errors) > 0, 'Subfield limit enforced');
$errors = array(); $conditional_sub = field('child'); $conditional_sub['condition'] = array('field' => 'x');
Alify_CF_Repeater::schema(array($conditional_sub), $errors);
same(true, count($errors) > 0, 'Subfield conditions rejected');
// Render the real row template (not just a hand-written browser fixture).
function esc_attr($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_html($s) { return esc_attr($s); }
function esc_textarea($s) { return esc_attr($s); }
$render_field = field('team', 'repeater'); $render_field['sub_fields'] = array(field('name'));
ob_start();
Alify_CF_Repeater::render($render_field, array(array('name' => '<script>bad()</script>')), 'team', 'values[team]');
$html = ob_get_clean();
same(false, strpos($html, '<script>') !== false, 'Row values escaped');
same(true, strpos($html, 'values[team][rows][0][__row_complete]') !== false, 'Real row completion sentinel');
same(true, strpos($html, 'values[team][__complete]') !== false, 'Repeater completion sentinel');
same(true, strpos($html, 'values[team][rows][__ROW__][name]') !== false, 'Template row input names');
// Options pages use a separate namespace and shared field validation.
$options = array(); $autoload = array();
function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
function update_option($key, $value, $load = null) { global $options, $autoload; $options[$key] = $value; $autoload[$key] = $load; return true; }
function delete_transient($key) { global $transients; unset($transients[$key]); }
function get_transient($key) { global $transients; return $transients[$key] ?? false; }
function wp_json_encode($value) { return json_encode($value); }
function wp_nonce_field($action, $name) { echo '<input type="hidden" name="' . esc_attr($name) . '" value="valid">'; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function esc_url($url) { return esc_attr($url); }
function submit_button($text) { echo '<button type="submit">' . esc_html($text) . '</button>'; }
function selected($left, $right, $echo = true) { return $left === $right ? 'selected' : ''; }
function checked($left, $right, $echo = true) { return $left === $right ? 'checked' : ''; }
same(true, alify_add_options_page('footer', 'Footer Settings'), 'Register additional page');
same(false, alify_add_options_page('footer', 'Duplicate'), 'Duplicate registration rejected');
same(false, alify_add_options_page('../bad', 'Bad'), 'Unsafe page slug rejected');
same(false, alify_add_options_page('empty', ''), 'Empty title rejected');
same(null, alify_get_option('contact_email'), 'Missing option is null');
$option_email = field('contact_email', 'email', true);
$option_text = field('copyright');
configure(1, array($option_email, $option_text), 'options:site');
same('options:site', get_post_meta(1, Alify_CF::LOCATION, true), 'Options location stored without stripping colon');
$old_schema = Alify_CF::schema(1);
configure(1, array($option_email), 'options:unknown');
same($old_schema, Alify_CF::schema(1), 'Unknown options location rejected');
$errors = array();
$request = array('alify_options_nonce' => 'valid', 'alify_options_complete' => '1', 'alify_values' => array(1 => array('__group_complete' => '1', 'contact_email' => 'hello@example.com', 'copyright' => 'C:\\site')));
same(true, Alify_CF_Options::save('site', $request, $errors), 'Options save succeeds');
same(array(), $errors, 'Valid options have no errors');
same('hello@example.com', alify_get_option('contact_email'), 'Options helper reads value');
same('C:\\site', alify_get_option('copyright'), 'Options preserve backslashes without extra slashing');
same(false, $autoload['alify_cf_options_site'], 'Options autoload disabled');
same(null, alify_get_field('contact_email', 10), 'Options do not leak into post storage');
$old_options = $options;
$invalid_request = $request; $invalid_request['alify_values'][1]['contact_email'] = 'bad-email'; $invalid_request['alify_values'][1]['copyright'] = 'Should not save';
$errors = array();
same(false, Alify_CF_Options::save('site', $invalid_request, $errors), 'Invalid required option rejects page');
same($old_options, $options, 'Entire page retained atomically');
$errors = array(); $invalid_request = $request; $invalid_request['alify_options_nonce'] = 'invalid';
same(false, Alify_CF_Options::save('site', $invalid_request, $errors), 'Invalid nonce rejected');
$errors = array(); $invalid_request = $request; unset($invalid_request['alify_options_complete']);
same(false, Alify_CF_Options::save('site', $invalid_request, $errors), 'Truncated page rejected');
$errors = array(); $invalid_request = $request; unset($invalid_request['alify_values'][1]['__group_complete']);
same(false, Alify_CF_Options::save('site', $invalid_request, $errors), 'Missing group marker rejected');
$errors = array(); $allowed = false;
same(false, Alify_CF_Options::save('site', $request, $errors), 'Options capability enforced');
$allowed = true; $errors = array();
same(false, Alify_CF_Options::save('unknown', $request, $errors), 'Unknown page rejected');
same($old_options, $options, 'Failed security checks never change options');
// Same names can be used on separate pages, but not two groups on one page.
configure(2, array($option_email), 'options:site');
same('page', get_post_meta(2, Alify_CF::LOCATION, true), 'Collision rejects options group assignment');
configure(2, array($option_email), 'options:footer');
same('options:footer', get_post_meta(2, Alify_CF::LOCATION, true), 'Different pages can reuse names');
$errors = array(); $footer_request = $request; $footer_request['alify_values'] = array(2 => array('__group_complete' => '1', 'contact_email' => 'footer@example.com'));
$posts[2]->post_status = 'publish';
same(true, Alify_CF_Options::save('footer', $footer_request, $errors), 'Second page saves independently');
same('footer@example.com', alify_get_option('contact_email', 'footer'), 'Named page helper');
same('hello@example.com', alify_get_option('contact_email'), 'Default page untouched');
// Conditional and repeater validation works for options as it does for posts.
$option_toggle = field('enabled', 'true_false');
$option_repeater = field('links', 'repeater'); $option_repeater['sub_fields'] = array(field('label', 'text', true), field('url', 'url'));
$option_repeater['condition'] = array('field' => $option_toggle['key'], 'operator' => 'equals', 'value' => '1');
configure(1, array($option_toggle, $option_repeater), 'options:site');
$errors = array(); $request['alify_values'][1] = array('__group_complete' => '1', 'enabled' => '1', 'links' => array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'label' => 'Home', 'url' => 'https://example.test'))));
same(true, Alify_CF_Options::save('site', $request, $errors), 'Options repeater saves');
same('Home', alify_get_option('links')[0]['label'], 'Options repeater retrieval');
$request['alify_values'][1] = array('__group_complete' => '1'); $errors = array();
same(true, Alify_CF_Options::save('site', $request, $errors), 'Hidden options repeater skipped');
same('Home', alify_get_option('links')[0]['label'], 'Hidden options rows retained');
$request['alify_values'][1]['enabled'] = '1'; $errors = array();
same(false, Alify_CF_Options::save('site', $request, $errors), 'Visible incomplete repeater rejects options save');
// Render the real form to exercise the storage-independent field renderer.
ob_start(); Alify_CF_Options::render('site'); $options_html = ob_get_clean();
same(true, strpos($options_html, 'alify_options_complete') !== false, 'Options form completion marker rendered');
same(true, strpos($options_html, 'alify_values[1][__group_complete]') !== false, 'Options group completion marker rendered');
same(false, strpos($options_html, 'alify_values_nonce_') !== false, 'Options form does not emit post nonces');
same(true, strpos($options_html, 'action="https://example.test/wp-admin/admin-post.php"') !== false, 'Options form posts to admin handler');
$options['alify_cf_options_site']['safe_output'] = '<b>hello</b>';
ob_start(); alify_the_option('safe_output'); $output = ob_get_clean();
same('&lt;b&gt;hello&lt;/b&gt;', $output, 'Option output helper escapes text');
same(null, alify_get_option('../invalid'), 'Invalid option field name rejected');
// Gallery: ordered image IDs, whole-value validation and all storage contexts.
$posts[52] = (object) array('ID' => 52, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/png', 'post_title' => '<b>Second</b>');
$posts[50]->post_title = 'First';
$posts[53] = (object) array('ID' => 53, 'post_type' => 'attachment', 'post_status' => 'trash', 'post_mime_type' => 'image/png');
$gallery = field('photos', 'gallery');
$gallery_payload = array('__complete' => '1', 'ids' => array('52', '50', '52'));
same(array(52, 50), Alify_CF_Gallery::clean($gallery_payload), 'Gallery preserves order and deduplicates');
same(array(), Alify_CF_Gallery::clean(array('__complete' => '1')), 'Explicit empty gallery');
same('', Alify_CF_Gallery::clean(array('ids' => array(50))), 'Missing gallery marker rejected');
same('', Alify_CF_Gallery::clean(array('__complete' => '1', 'ids' => '50')), 'Malformed IDs rejected');
same('', Alify_CF_Gallery::clean(array('__complete' => '1', 'ids' => array(51))), 'Non-image rejected');
same('', Alify_CF_Gallery::clean(array('__complete' => '1', 'ids' => array(10))), 'Non-attachment rejected');
same('', Alify_CF_Gallery::clean(array('__complete' => '1', 'ids' => array(53))), 'Trashed image rejected');
same('', Alify_CF_Gallery::clean(array('__complete' => '1', 'ids' => array(-50))), 'Negative ID rejected');
same('', Alify_CF_Gallery::clean(array('__complete' => '1', 'ids' => array(array(50)))), 'Nested ID rejected');
same('', Alify_CF_Gallery::clean(array('__complete' => '1', 'ids' => array('9999999999999999999999'))), 'Integer overflow rejected');
same('', Alify_CF_Gallery::clean(array('__complete' => '1', 'ids' => array_fill(0, 51, 50))), 'Gallery cap enforced');
$denied = array(52);
same('', Alify_CF_Gallery::clean($gallery_payload, array(50), true), 'New image requires permission');
same(array(52, 50), Alify_CF_Gallery::clean($gallery_payload, array(50, 52), true), 'Existing IDs can be reordered without attachment edit permission');
$denied = array();
configure(1, array($gallery), 'post');
same(array(), Alify_CF::schema(1)[0]['default'], 'Gallery defaults to empty array');
$_POST = array('alify_values_nonce_1' => 'valid', 'alify_values' => array(1 => array('photos' => $gallery_payload)));
save_fixture(10, $posts[10]);
same(array(52, 50), alify_get_field('photos', 10), 'Post gallery saves IDs');
$_POST['alify_values'][1] = array();
save_fixture(10, $posts[10]);
same(array(52, 50), alify_get_field('photos', 10), 'Missing post gallery preserves old IDs');
$_POST['alify_values'][1]['photos'] = array('__complete' => '1');
save_fixture(10, $posts[10]);
same(array(), alify_get_field('photos', 10), 'Optional post gallery can be cleared');
$gallery['required'] = true; configure(1, array($gallery), 'post');
$meta[10]['_alify_cf_photos'] = array(50);
save_fixture(10, $posts[10]); // No valid values nonce in schema request: must not overwrite.
$_POST = array('alify_values_nonce_1' => 'valid', 'alify_values' => array(1 => array('photos' => array('__complete' => '1'))));
save_fixture(10, $posts[10]);
same(array(50), alify_get_field('photos', 10), 'Required empty gallery retains old IDs');
$target = field('caption'); $target['condition'] = array('field' => $gallery['key'], 'operator' => 'equals', 'value' => '52');
same(true, Alify_CF::visible($target, array($gallery, $target), array($gallery['key'] => array(50, 52))), 'Gallery condition uses ID membership');
$target['condition']['operator'] = 'empty';
same(true, Alify_CF::visible($target, array($gallery, $target), array($gallery['key'] => array())), 'Gallery empty condition');
$toggle = field('show', 'true_false'); $gallery['condition'] = array('field' => $toggle['key'], 'operator' => 'equals', 'value' => '1');
configure(1, array($toggle, $gallery), 'post');
$_POST = array('alify_values_nonce_1' => 'valid', 'alify_values' => array(1 => array('photos' => array('__complete' => '1'))));
save_fixture(10, $posts[10]);
same(array(50), alify_get_field('photos', 10), 'Hidden gallery preserves IDs');
$gallery['condition'] = null; $gallery['required'] = false;
$repeat_gallery = field('slides', 'repeater'); $repeat_gallery['sub_fields'] = array($gallery);
configure(1, array($repeat_gallery), 'post');
$repeat_payload = array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'photos' => $gallery_payload)));
same(array(array('photos' => array(52, 50))), Alify_CF_Repeater::clean($repeat_gallery, $repeat_payload), 'Gallery subfield cleaned');
$bad_repeat = $repeat_payload; unset($bad_repeat['rows'][0]['photos']);
same('', Alify_CF_Repeater::clean($repeat_gallery, $bad_repeat), 'Missing gallery subfield rejects repeater');
$denied = array(52);
same('', Alify_CF_Repeater::clean($repeat_gallery, $repeat_payload, array(), true), 'Repeater gallery permission checked');
same(array(array('photos' => array(52, 50))), Alify_CF_Repeater::clean($repeat_gallery, $repeat_payload, array(array('photos' => array(50, 52))), true), 'Existing gallery IDs survive row reordering');
$denied = array();
configure(1, array($gallery, field('footer')), 'options:site');
$errors = array(); $gallery_request = array('alify_options_nonce' => 'valid', 'alify_options_complete' => '1', 'alify_values' => array(1 => array('__group_complete' => '1', 'photos' => $gallery_payload, 'footer' => 'old')));
same(true, Alify_CF_Options::save('site', $gallery_request, $errors), 'Options gallery saves');
same(array(52, 50), alify_get_option('photos'), 'Options gallery IDs retrieved');
$gallery_request['alify_values'][1]['photos']['ids'] = array(51); $gallery_request['alify_values'][1]['footer'] = 'new'; $errors = array();
same(false, Alify_CF_Options::save('site', $gallery_request, $errors), 'Invalid options gallery rejects page');
same('old', alify_get_option('footer'), 'Other options remain unchanged');
function wp_get_attachment_image_url($id, $size) { return 'https://example.test/image-' . $id . '.png'; }
ob_start(); Alify_CF_Gallery::render($gallery, array(52), 'photos', 'values[photos]'); $html = ob_get_clean();
same(false, strpos($html, '<b>Second</b>') !== false, 'Gallery attachment title escaped');
same(true, strpos($html, 'values[photos][ids][]') !== false, 'Gallery ID inputs rendered');
same(true, strpos($html, 'values[photos][__complete]') !== false, 'Gallery completion marker rendered');
// Feedback recovery is separate from saved values, bounded, and per user/context.
$feedback_schema = array(field('email', 'email', true), field('note', 'textarea'));
$feedback = Alify_CF_Feedback::group($feedback_schema, array('email' => 'bad-email', 'note' => "Line 1\nLine 2"), array('email' => 'saved@example.com'), array('email' => 'Invalid email'));
same('bad-email', $feedback['values']['email'], 'Invalid scalar recovery');
same("Line 1\nLine 2", $feedback['values']['note'], 'Multiline recovery');
Alify_CF_Feedback::put('post_10', array(1 => $feedback));
same('bad-email', Alify_CF_Feedback::get('post_10')[1]['values']['email'], 'Feedback stored');
$test_user_id = 2;
same(array(), Alify_CF_Feedback::get('post_10'), 'Feedback not shared across users');
$test_user_id = 1;
same(array(), Alify_CF_Feedback::get('options_site_unrelated'), 'Feedback not shared across contexts');
Alify_CF_Feedback::put('post_10', array());
same(array(), Alify_CF_Feedback::get('post_10'), 'Feedback cleared');
$large = Alify_CF_Feedback::group(array(field('note', 'textarea')), array('note' => str_repeat('x', 20000)), array(), array());
same(10000, strlen($large['values']['note']), 'Scalar recovery bounded');
Alify_CF_Feedback::put('post_10', array(1 => array('values' => array('note' => str_repeat('x', 262145)), 'errors' => array('note' => 'Invalid'))));
same(array(), Alify_CF_Feedback::get('post_10')[1]['values'], 'Oversized recovery omits values');
same(true, Alify_CF_Feedback::get('post_10')[1]['omitted'], 'Oversized recovery annotated');
$hidden = field('detail'); $hidden['condition'] = array('field' => 'field_enabled', 'operator' => 'equals', 'value' => '1');
$group_feedback = Alify_CF_Feedback::group(array(field('enabled', 'true_false'), $hidden), array('detail' => 'forged'), array('detail' => 'saved hidden'), array());
same('saved hidden', $group_feedback['values']['detail'], 'Hidden recovery preserves saved value');
$denied = array(52);
$group_feedback = Alify_CF_Feedback::group(array(field('photo', 'image'), field('photos', 'gallery')), array('photo' => '52', 'photos' => array('__complete' => '1', 'ids' => array(52))), array('photo' => '50', 'photos' => array(50)), array());
same('50', $group_feedback['values']['photo'], 'Recovery does not expose unauthorized media');
same(array(), $group_feedback['values']['photos'], 'Recovery excludes unauthorized new gallery IDs');
$denied = array();
Alify_CF_Feedback::put('post_10', array());
$rows_field = field('team', 'repeater'); $rows_field['sub_fields'] = array(field('photos', 'gallery'));
$denied = array(52);
$recovered = Alify_CF_Feedback::group(array($rows_field), array('team' => array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'photos' => array('__complete' => '1', 'ids' => array(52)))))), array('team' => array(array('photos' => array(52)))), array('team' => 'Invalid'));
same(array(52), $recovered['values']['team'][0]['photos'], 'Recovery preserves existing restricted media in rows');
same(hash('sha256', wp_json_encode(array($rows_field))), $recovered['schema'], 'Recovery records schema identity');
$denied = array();
// Complete-group marker protects scalar fields too, not only repeaters/galleries.
configure(1, array(field('title')), 'post');
$meta[10]['_alify_cf_title'] = 'Keep me';
$_POST = array('alify_values_nonce_1' => 'valid', 'alify_values' => array(1 => array('title' => 'truncated attempt')));
Alify_CF::save(10, $posts[10]);
same('Keep me', alify_get_field('title', 10), 'Incomplete metabox submission preserves scalar data');
// Group fields are fixed objects with atomic, permission-aware subfield validation.
$profile = field('profile', 'group'); $profile['sub_fields'] = array(field('name', 'text', true), field('enabled', 'true_false'), field('photos', 'gallery'));
configure(1, array($profile));
same('group', Alify_CF::schema(1)[0]['type'], 'Group schema accepted');
$payload = array('__complete' => '1', 'fields' => array('name' => 'Member', 'enabled' => '1', 'photos' => array('__complete' => '1', 'ids' => array(50))));
$clean_profile = Alify_CF_Group::clean($profile, $payload, null, true);
same(array('name' => 'Member', 'enabled' => '1', 'photos' => array(50)), $clean_profile, 'Group stores associative subfield object');
same('', Alify_CF_Group::clean($profile, array('fields' => $payload['fields'])), 'Group completion marker required');
$bad = $payload; $bad['fields']['name'] = '';
same('', Alify_CF_Group::clean($profile, $bad), 'Invalid required subfield rejects complete object');
$bad = $payload; unset($bad['fields']['photos']['__complete']);
same('', Alify_CF_Group::clean($profile, $bad), 'Incomplete gallery rejects complete object');
$denied = array(52); $bad = $payload; $bad['fields']['photos']['ids'] = array(52);
same('', Alify_CF_Group::clean($profile, $bad, $clean_profile, true), 'Unauthorized new group media rejected');
$old_profile = $clean_profile; $old_profile['photos'] = array(52);
same(array(52), Alify_CF_Group::clean($profile, $bad, $old_profile, true)['photos'], 'Previously stored restricted group media preserved');
$denied = array();
$_POST = array('alify_values_nonce_1' => 'valid', 'alify_values' => array(1 => array('profile' => $payload)));
save_fixture(10, $posts[10]);
same($clean_profile, alify_get_group('profile', 10), 'Group frontend helper returns saved object');
same(array(), alify_get_group('missing', 10), 'Unsaved group helper returns empty object array');
$recovery = Alify_CF_Feedback::group(array($profile), array('profile' => $bad), array('profile' => $clean_profile), array('profile' => 'Invalid'));
same('Member', $recovery['values']['profile']['name'], 'Group supports bounded input recovery');
$empty_group = field('empty_profile', 'group', true); $empty_group['sub_fields'] = array(field('enabled', 'true_false'));
$errors = array();
same(array(), Alify_CF::validate_values(array($empty_group), array('empty_profile' => array('__complete' => '1', 'fields' => array())), array(), $errors), 'Required group rejects all-empty/false subfields');
$errors = array();
$updates = Alify_CF::validate_values(array($empty_group), array('empty_profile' => array('__complete' => '1', 'fields' => array('enabled' => '1'))), array(), $errors);
same(array('enabled' => '1'), $updates['empty_profile'], 'Required group accepts populated subfield');
foreach (array('repeater', 'group') as $nested) {
    $raw = $profile; $raw['sub_fields'][0]['type'] = $nested; $errors = array();
    Alify_CF::validate_schema(array($raw), 'page', 0, $errors);
    same(true, (bool) $errors, 'Nested container types rejected inside Group');
}
ob_start(); Alify_CF_Group::render($profile, $clean_profile, 'profile-id', 'alify_values[1][profile]'); $html = ob_get_clean();
same(true, strpos($html, '[fields][name]') !== false && strpos($html, '[__complete]') !== false, 'Group renders namespaced controls and completion marker');
$flags = field('flags', 'group'); $flags['sub_fields'] = array(field('enabled', 'true_false'), field('tags', 'checkbox'));
same(array('enabled' => '0', 'tags' => array()), Alify_CF_Group::clean($flags, array('__complete' => '1', 'fields' => array('__fields_complete' => '1'))), 'Checkbox/toggle-only Group clears all unchecked values');
same('', Alify_CF_Group::clean($flags, array('__complete' => '1')), 'Missing Group field structure retains previous object');
ob_start(); Alify_CF_Group::render($flags, array(), 'flags', 'flags'); $flags_html = ob_get_clean();
same(true, strpos($flags_html, '[fields][__fields_complete]') !== false, 'Group renders structure marker even when no checkbox is checked');
$conditional = field('dependent'); $conditional['condition'] = array('field' => $profile['key'], 'operator' => 'not_empty', 'value' => ''); $errors = array();
Alify_CF::validate_schema(array($profile, $conditional), 'page', 0, $errors);
same(true, (bool) $errors, 'Group cannot be used as a condition source');
// Flexible Content validates named layouts as one ordered atomic list.
$flex = field('content', 'flexible');
$flex['layouts'] = array(array('key' => 'layout_hero', 'name' => 'hero', 'label' => 'Hero', 'sub_fields' => array(field('title', 'text', true), field('photos', 'gallery'))), array('key' => 'layout_text', 'name' => 'text', 'label' => 'Text', 'sub_fields' => array(field('body', 'textarea', true))));
$errors = array(); $clean_schema = Alify_CF::validate_schema(array($flex), 'page', 0, $errors);
same(array(), $errors, 'Flexible layout schema accepted');
$flex = $clean_schema[0];
$flex_payload = array('__complete' => '1', 'rows' => array(array('layout' => 'hero', '__complete' => '1', 'fields' => array('title' => 'Welcome', 'photos' => array('__complete' => '1', 'ids' => array(50)))), array('layout' => 'text', '__complete' => '1', 'fields' => array('body' => 'Details'))));
$flex_saved = Alify_CF_Flexible::clean($flex, $flex_payload, null, true);
same(array('hero', 'text'), array_column($flex_saved, 'layout'), 'Mixed layout order preserved');
same(array(50), $flex_saved[0]['fields']['photos'], 'Flexible gallery stores integer IDs');
$bad = $flex_payload; $bad['rows'][1]['fields']['body'] = '';
same('', Alify_CF_Flexible::clean($flex, $bad), 'One invalid row rejects whole Flexible value');
$bad = $flex_payload; $bad['rows'][0]['layout'] = 'missing';
same('', Alify_CF_Flexible::clean($flex, $bad), 'Unknown layout rejected');
$bad = $flex_payload; unset($bad['rows'][0]['__complete']);
same('', Alify_CF_Flexible::clean($flex, $bad), 'Incomplete layout row rejected');
same('', Alify_CF_Flexible::clean($flex, array('rows' => array())), 'Flexible parent completion required');
same(array(), Alify_CF_Flexible::clean($flex, array('__complete' => '1')), 'Explicit complete empty Flexible clears rows');
$bad = $flex_payload; $bad['rows'] = array_fill(0, 51, $bad['rows'][0]);
same('', Alify_CF_Flexible::clean($flex, $bad), 'Flexible caps 50 content rows');
$bad = $flex_payload; $bad['rows'][0]['fields']['photos']['ids'] = array(52); $denied = array(52);
same('', Alify_CF_Flexible::clean($flex, $bad, $flex_saved, true), 'Unauthorized new Flexible gallery ID rejected');
$prior = $flex_saved; $prior[0]['fields']['photos'] = array(52);
same(array(52), Alify_CF_Flexible::clean($flex, $bad, $prior, true)[0]['fields']['photos'], 'Existing restricted media preserved in same layout');
$denied = array();
$reverse = $flex_payload; $reverse['rows'] = array_reverse($reverse['rows']);
same(array('text', 'hero'), array_column(Alify_CF_Flexible::clean($flex, $reverse, $flex_saved, true), 'layout'), 'Reorder keeps layout identities');
foreach (array('flexible', 'group', 'repeater') as $nested) {
    $invalid_schema = $flex; $invalid_schema['layouts'][0]['sub_fields'][0]['type'] = $nested; $errors = array();
    Alify_CF::validate_schema(array($invalid_schema), 'page', 0, $errors);
    same(true, (bool) $errors, 'Nested container rejected inside layout');
}
$invalid_schema = $flex; $invalid_schema['layouts'][1]['name'] = 'hero'; $errors = array();
Alify_CF::validate_schema(array($invalid_schema), 'page', 0, $errors);
same(true, (bool) $errors, 'Duplicate layout name rejected');
$invalid_schema = $flex; $invalid_schema['layouts'] = array_fill(0, 11, $flex['layouts'][0]); $errors = array();
Alify_CF::validate_schema(array($invalid_schema), 'page', 0, $errors);
same(true, (bool) $errors, 'Schema caps ten layouts');
$payload_again = Alify_CF_Publication::stored_payload($flex, $flex_saved);
same($flex_saved, Alify_CF_Flexible::clean($flex, $payload_again), 'Publication reconstructs saved layout payload');
$meta[10]['_alify_cf_content'] = $flex_saved;
same($flex_saved, alify_get_layouts('content', 10), 'Flexible frontend helper returns ordered persisted rows');
same(array(), alify_get_layouts('unknown', 10), 'Missing Flexible helper returns empty list');
ob_start(); Alify_CF_Flexible::render($flex, $flex_saved, 'flex', 'content'); $flex_html = ob_get_clean();
same(true, strpos($flex_html, 'data-flex-template="hero"') !== false && strpos($flex_html, '[rows][0][layout]') !== false, 'Flexible templates and row layout markers rendered');
ob_start(); Alify_CF_Flexible::render($flex, array(array('layout' => 'removed', 'fields' => array())), 'flex', 'content'); $flex_html = ob_get_clean();
same(true, strpos($flex_html, 'Unknown saved layout') !== false, 'Removed saved layouts never silently disappear');
// Compound rules are flat, bounded and backward compatible with single rules.
$toggle = field('show_detail', 'true_false'); $choice = field('kind', 'select'); $detail = field('compound_detail', 'text', true);
$rules = array(array('field' => $toggle['key'], 'operator' => 'equals', 'value' => '1'), array('field' => $choice['key'], 'operator' => 'equals', 'value' => 'a'));
$detail['condition'] = array('relation' => 'all', 'rules' => $rules); $schema = array($toggle, $choice, $detail);
$errors = array(); $validated = Alify_CF::validate_schema($schema, 'page', 0, $errors);
same(array(), $errors, 'ALL rule set schema accepted');
same($detail['condition'], $validated[2]['condition'], 'Compound condition shape retained');
same(true, Alify_CF::visible($detail, $schema, array($toggle['key'] => '1', $choice['key'] => 'a')), 'ALL true when both match');
same(false, Alify_CF::visible($detail, $schema, array($toggle['key'] => '1', $choice['key'] => 'b')), 'ALL false when one fails');
$detail['condition']['relation'] = 'any'; $schema[2] = $detail;
same(true, Alify_CF::visible($detail, $schema, array($toggle['key'] => '0', $choice['key'] => 'a')), 'ANY true when one matches');
same(false, Alify_CF::visible($detail, $schema, array($toggle['key'] => '0', $choice['key'] => 'b')), 'ANY false when neither matches');
$legacy = $rules[0]; $errors = array();
same($legacy, Alify_CF_Conditions::clean($legacy, $errors, 'detail'), 'Legacy single rule retains original shape');
foreach (array(array('relation' => 'xor', 'rules' => $rules), array('relation' => 'all', 'rules' => array()), array('relation' => 'all', 'rules' => array_fill(0, 11, $rules[0])), array('relation' => 'any', 'rules' => array(array('relation' => 'all', 'rules' => $rules))), array('relation' => 'all', 'rules' => array('bad')), array('relation' => 'all', 'rules' => array(array('field' => array(), 'operator' => 'equals', 'value' => '1')))) as $invalid) {
    $errors = array(); Alify_CF_Conditions::clean($invalid, $errors, 'detail');
    same(true, (bool) $errors, 'Malformed, oversized or nested condition set rejected');
}
$bad_detail = $detail; $bad_detail['condition']['rules'][1]['field'] = 'missing'; $errors = array();
Alify_CF::validate_schema(array($toggle, $choice, $bad_detail), 'page', 0, $errors);
same(true, (bool) $errors, 'Missing source in ANY set rejected');
$bad_detail['condition']['rules'][1]['field'] = $bad_detail['key']; $errors = array();
Alify_CF::validate_schema(array($toggle, $choice, $bad_detail), 'page', 0, $errors);
same(true, (bool) $errors, 'Compound self reference rejected');
$conditional_source = $choice; $conditional_source['condition'] = $rules[0]; $errors = array();
Alify_CF::validate_schema(array($toggle, $conditional_source, $detail), 'page', 0, $errors);
same(true, (bool) $errors, 'Conditional source chain rejected within compound set');
$detail['condition']['relation'] = 'all'; $schema[2] = $detail; $errors = array();
$updates = Alify_CF::validate_values($schema, array('show_detail' => '0', 'kind' => 'a', 'compound_detail' => 'forged'), array('compound_detail' => 'Keep'), $errors);
same(false, array_key_exists('compound_detail', $updates), 'Hidden compound target ignores forged submitted value');
same(array(), $errors, 'Hidden required compound target does not create validation error');
$errors = array(); Alify_CF::validate_values($schema, array('show_detail' => '1', 'kind' => 'a'), array(), $errors);
same(true, (bool) $errors, 'Visible required compound target validated');
$recovered = Alify_CF_Feedback::group($schema, array('show_detail' => '0', 'kind' => 'a', 'compound_detail' => 'forged'), array('compound_detail' => 'Keep'), array());
same('Keep', $recovered['values']['compound_detail'], 'Compound hidden recovery preserves saved value');
$gallery_source = field('condition_photos', 'gallery'); $checkbox_source = field('condition_tags', 'checkbox');
$detail['condition'] = array('relation' => 'all', 'rules' => array(array('field' => $gallery_source['key'], 'operator' => 'equals', 'value' => '50'), array('field' => $checkbox_source['key'], 'operator' => 'not_empty', 'value' => '')));
same(true, Alify_CF::visible($detail, array($gallery_source, $checkbox_source, $detail), array($gallery_source['key'] => array(50), $checkbox_source['key'] => array('a'))), 'Compound gallery membership and checkbox emptiness agree');
// Configurable row bounds are enforced atomically and never migrate/truncate saved data.
$limited = field('limited_rows', 'repeater'); $limited['sub_fields'] = array(field('name', 'text', true));
$errors = array(); same(array('min_rows' => 0, 'max_rows' => 50), Alify_CF_Repeater::limits($limited, $errors), 'Legacy repeater limits default 0–50');
$limited['min_rows'] = '1'; $limited['max_rows'] = '2'; $errors = array();
$limited_schema = Alify_CF::validate_schema(array($limited), 'page', 0, $errors);
same(1, $limited_schema[0]['min_rows'], 'Minimum normalized to integer'); same(2, $limited_schema[0]['max_rows'], 'Maximum normalized to integer');
$one = array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'name' => 'One')));
same('', Alify_CF_Repeater::clean($limited, array('__complete' => '1')), 'Below-minimum complete submission rejected');
same(array(array('name' => 'One')), Alify_CF_Repeater::clean($limited, $one), 'Minimum row count accepted');
$two = $one; $two['rows'][] = array('__row_complete' => '1', 'name' => 'Two');
same(2, count(Alify_CF_Repeater::clean($limited, $two)), 'Maximum row count accepted');
$three = $two; $three['rows'][] = array('__row_complete' => '1', 'name' => 'Three');
same('', Alify_CF_Repeater::clean($limited, $three), 'Over-maximum submission rejected rather than truncated');
foreach (array(array('min_rows' => -1), array('min_rows' => null), array('max_rows' => true), array('min_rows' => '1.5'), array('max_rows' => '1e1'), array('min_rows' => '01'), array('max_rows' => 51), array('max_rows' => 0), array('min_rows' => 3, 'max_rows' => 2), array('max_rows' => array())) as $bad) {
    $errors = array(); Alify_CF_Repeater::limits(array_merge($limited, $bad), $errors); same(true, (bool) $errors, 'Invalid row bounds rejected');
}
$required_rows = $limited; $required_rows['min_rows'] = 0; $required_rows['required'] = true;
same('', Alify_CF_Repeater::clean($required_rows, array('__complete' => '1')), 'Required repeater effective minimum is at least one');
ob_start(); Alify_CF_Repeater::render($limited, array(array('name' => 'One'), array('name' => 'Two'), array('name' => 'Three')), 'limited', 'limited'); $bounds_html = ob_get_clean();
same(true, strpos($bounds_html, 'data-min="1" data-limit="2"') !== false, 'Editor receives configured row bounds');
same(3, substr_count($bounds_html, 'class="alify-row"') - 1, 'Existing out-of-bounds rows render without truncation (excluding template)');
$hidden_rows = $limited; $hidden_rows['condition'] = array('field' => $toggle['key'], 'operator' => 'equals', 'value' => '1'); $errors = array();
$updates = Alify_CF::validate_values(array($toggle, $hidden_rows), array('show_detail' => '0', 'limited_rows' => array('__complete' => '1')), array('limited_rows' => array(array('name' => 'Keep'))), $errors);
same(false, array_key_exists('limited_rows', $updates), 'Hidden repeater ignores unmet row bounds and forged clearing');
same(array(), $errors, 'Hidden below-minimum repeater does not block validation');
// Actionable diagnostics never echo rejected values, row tokens or attachment identities.
$diagnostic_rows = field('people', 'repeater'); $diagnostic_rows['sub_fields'] = array(field('name', 'text', true), field('email', 'email'));
$diagnostic_payload = array('__complete' => '1', 'rows' => array('new_secret_100' => array('__row_complete' => '1', 'name' => 'Okay', 'email' => ''), 'new_secret_200' => array('__row_complete' => '1', 'name' => '', 'email' => '')));
$issue = ''; same('', Alify_CF_Repeater::clean($diagnostic_rows, $diagnostic_payload, null, true, $issue), 'Diagnostic validation still rejects whole list');
same('Row 2 → Name: A valid value is required.', $issue, 'Repeater identifies visual row position and subfield');
same(false, strpos($issue, 'new_secret') !== false, 'Diagnostics omit submitted row tokens');
$diagnostic_payload['rows']['new_secret_200']['name'] = 'Okay'; $diagnostic_payload['rows']['new_secret_200']['email'] = 'private-invalid@example';
Alify_CF_Repeater::clean($diagnostic_rows, $diagnostic_payload, null, true, $issue);
same('Row 2 → Email: Enter a valid email value.', $issue, 'Invalid optional subfield identifies type');
same(false, strpos($issue, 'private-invalid') !== false, 'Diagnostics omit rejected scalar value');
$diagnostic_group = $diagnostic_rows; $diagnostic_group['type'] = 'group';
Alify_CF_Group::clean($diagnostic_group, array('__complete' => '1', 'fields' => array('name' => '')), null, true, $issue);
same('Name: A valid value is required.', $issue, 'Group names subfield without artificial Row 1');
Alify_CF_Group::clean($diagnostic_group, array('fields' => array()), null, true, $issue);
same(true, strpos($issue, 'Incomplete Group') !== false, 'Incomplete Group explains reload requirement');
$diagnostic_flex = field('blocks', 'flexible'); $diagnostic_flex['layouts'] = array(array('key' => 'layout_contact', 'name' => 'contact', 'label' => 'Contact', 'sub_fields' => $diagnostic_rows['sub_fields']));
$diagnostic_flex_payload = array('__complete' => '1', 'rows' => array(array('layout' => 'contact', '__complete' => '1', 'fields' => array('name' => 'Okay')), array('layout' => 'contact', '__complete' => '1', 'fields' => array('name' => ''))));
Alify_CF_Flexible::clean($diagnostic_flex, $diagnostic_flex_payload, null, true, $issue);
same('Row 2 (Contact) → Name: A valid value is required.', $issue, 'Flexible identifies row, layout and subfield');
$diagnostic_flex_payload['rows'][1]['layout'] = '<script>secret-layout</script>';
Alify_CF_Flexible::clean($diagnostic_flex, $diagnostic_flex_payload, null, true, $issue);
same('Row 2: Unknown layout. Restore its schema or remove the row explicitly.', $issue, 'Unknown layout gives corrective action without raw submitted name');
$diagnostic_rows['min_rows'] = 2; $diagnostic_rows['max_rows'] = 3;
Alify_CF_Repeater::clean($diagnostic_rows, array('__complete' => '1'), null, true, $issue);
same('Use between 2 and 3 rows; received 0.', $issue, 'Count diagnostic includes effective limits and actual count');
Alify_CF_Repeater::clean($diagnostic_rows, array(), null, true, $issue);
same(true, strpos($issue, 'Incomplete submission') !== false, 'Incomplete root diagnoses marker rather than clearing');
$diagnostic_rows['sub_fields'] = array(field('photo', 'image')); unset($diagnostic_rows['min_rows'], $diagnostic_rows['max_rows']); $denied = array(52);
Alify_CF_Repeater::clean($diagnostic_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'photo' => '52'))), null, true, $issue);
same('Row 1 → Photo: You cannot select this attachment.', $issue, 'Restricted media diagnostic omits identity');
$denied = array();
$diagnostic_rows['sub_fields'] = array(field('name', 'text', true)); $errors = array(); $field_errors = array();
Alify_CF::validate_values(array($diagnostic_rows), array('people' => array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'name' => '')))), array(), $errors, $field_errors);
same('Row 1 → Name: A valid value is required. Previous saved value retained.', $field_errors['people'], 'Detailed diagnostic reaches existing feedback/REST error contract');
$issue = 'stale';
Alify_CF_Repeater::clean($diagnostic_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'name' => 'Fixed'))), null, true, $issue);
same('', $issue, 'Successful correction clears previous diagnostic');
$location = array('stale' => true);
Alify_CF_Repeater::clean($diagnostic_rows, array('__complete' => '1', 'rows' => array('private-token' => array('__row_complete' => '1', 'name' => ''))), null, true, $issue, $location);
same(array('row' => 0, 'subfield' => 'name'), $location, 'Machine location uses zero-based visual position, never row tokens');
Alify_CF_Group::clean($diagnostic_group, array('__complete' => '1', 'fields' => array('name' => '')), null, true, $issue, $location);
same(array('subfield' => 'name'), $location, 'Group location has no artificial row');
$diagnostic_flex_payload['rows'][1]['layout'] = 'contact';
Alify_CF_Flexible::clean($diagnostic_flex, $diagnostic_flex_payload, null, true, $issue, $location);
same(array('row' => 1, 'subfield' => 'name'), $location, 'Flexible location remaps temporary row to actual visual row');
Alify_CF_Repeater::clean($diagnostic_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'name' => 'Fixed'))), null, true, $issue, $location);
same(null, $location, 'Successful correction resets stale machine location');
Alify_CF_Repeater::clean($diagnostic_rows, array(), null, true, $issue, $location);
same(null, $location, 'Incomplete container never targets an unrelated subfield');
$errors = array(); $field_errors = array(); $locations = array();
Alify_CF::validate_values(array($diagnostic_rows), array('people' => array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'name' => '')))), array(), $errors, $field_errors, $locations);
same(array('people' => array('row' => 0, 'subfield' => 'name')), $locations, 'Validation returns parallel location metadata without changing error strings');
// Color/time are strict optional scalars, shared by root fields and all basic subfields.
foreach (array('#AAbB09' => '#aabb09', '#000000' => '#000000', '#ffffff' => '#ffffff', '' => '', '#abc' => '', 'red' => '', '#12345678' => '', "#123456\n" => '', ' #123456' => '') as $raw => $expected) {
    same($expected, Alify_CF::clean_value(field('accent', 'color'), $raw), 'Strict six-digit color: ' . $raw);
}
foreach (array('00:00' => '00:00', '23:59' => '23:59', '09:30' => '09:30', '' => '', '24:00' => '', '9:30' => '', '12:60' => '', '09:30:00' => '', "09:30\n" => '') as $raw => $expected) {
    same($expected, Alify_CF::clean_value(field('opens', 'time'), $raw), 'Strict HH:MM time: ' . $raw);
}
same('', Alify_CF::clean_value(field('accent', 'color'), array('#123456')), 'Color rejects arrays');
same('', Alify_CF::clean_value(field('opens', 'time'), array('09:30')), 'Time rejects arrays');
$new_rows = field('palette', 'repeater'); $new_rows['sub_fields'] = array(field('accent', 'color'), field('opens', 'time'));
same(array(array('accent' => '#aabbcc', 'opens' => '09:30')), Alify_CF_Repeater::clean($new_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'accent' => '#AABBCC', 'opens' => '09:30')))), 'New types clean inside structured rows');
$old_scalar = array('accent' => '#112233', 'opens' => '08:00'); $errors = array();
same(array(), Alify_CF::validate_values(array(field('accent', 'color'), field('opens', 'time')), array('accent' => 'bad', 'opens' => '24:00'), $old_scalar, $errors), 'Invalid nonempty optional color/time are rejected, not silently cleared');
$errors = array(); same(array('accent' => '', 'opens' => ''), Alify_CF::validate_values(array(field('accent', 'color'), field('opens', 'time')), array('accent' => '', 'opens' => ''), $old_scalar, $errors), 'Optional color and time explicitly clear');
$link_payload = array('__complete' => '1', 'url' => 'https://example.com/path?q=1&b=2#part', 'title' => '<b>Visit</b>', 'target' => '_blank');
$link_value = array('url' => $link_payload['url'], 'title' => 'Visit', 'target' => '_blank');
same($link_value, Alify_CF::clean_value(field('cta', 'link'), $link_payload), 'Link returns a structured sanitized record');
$empty_link = array('__complete' => '1', 'url' => '', 'title' => '', 'target' => '');
same(array(), Alify_CF_Link::clean($empty_link), 'Explicit complete empty link clears');
foreach (array('javascript:alert(1)', 'data:text/html,test', '//example.com', '/relative', 'mailto:a@example.com', 'ftp://example.com', 'https://user:pass@example.com', "https://example.com/\n", 'https://example.com/a b', 'https://example.com/\\evil') as $url) {
    $bad = $link_payload; $bad['url'] = $url; same('', Alify_CF_Link::clean($bad), 'Link rejects unsupported or malformed URL');
}
foreach (array(array(), '', array('url' => 'https://example.com'), array_merge($link_payload, array('title' => array())), array_merge($link_payload, array('target' => '_top')), array_merge($empty_link, array('title' => 'Partial')), array_merge($empty_link, array('target' => '_blank'))) as $bad) {
    same('', Alify_CF_Link::clean($bad), 'Link rejects incomplete or malformed structures');
}
$errors = array(); same(array('cta' => array()), Alify_CF::validate_values(array(field('cta', 'link')), array('cta' => $empty_link), array('cta' => $link_value), $errors), 'Optional link explicitly clears');
$errors = array(); same(array(), Alify_CF::validate_values(array(field('cta', 'link', true)), array('cta' => $empty_link), array('cta' => $link_value), $errors), 'Required link rejects empty');
$errors = array(); same(array(), Alify_CF::validate_values(array(field('cta', 'link')), array(), array('cta' => $link_value), $errors), 'Missing optional Link payload cannot silently delete saved data');
$link_rows = field('links', 'repeater'); $link_rows['sub_fields'] = array(field('cta', 'link'));
same(array(array('cta' => $link_value)), Alify_CF_Repeater::clean($link_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'cta' => $link_payload)))), 'Link works inside repeaters');
same('', Alify_CF_Repeater::clean($link_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1')))), 'Incomplete optional Link rejects the whole container');
$meta[10]['_alify_cf_cta'] = $link_value; same($link_value, alify_get_link('cta', 10), 'Typed link getter returns saved record');
same(array(), alify_get_link('missing_link', 10), 'Typed link getter returns empty array before save');
$errors = array(); $link_target = field('details'); $link_target['condition'] = array('field' => 'field_cta', 'operator' => 'not_empty', 'value' => '');
Alify_CF::validate_schema(array(field('cta', 'link'), $link_target), 'post', 0, $errors);
same(true, (bool) $errors, 'Link cannot be an ambiguous conditional source');
$rich = field('copy', 'wysiwyg');
same('<p>Hello <strong>world</strong></p>', Alify_CF::clean_value($rich, '<p>Hello <strong>world</strong></p>'), 'WYSIWYG preserves permitted markup');
foreach (array('', '<p><br></p>', '<p>&nbsp;</p>', '<p>&#8203;</p>', "<p>\n </p>") as $empty_html) {
    same('', Alify_CF::clean_value($rich, $empty_html), 'Empty formatting normalizes to empty rich text');
}
$errors = array(); same(array('copy' => ''), Alify_CF::validate_values(array($rich), array('copy' => '<p><br></p>'), array('copy' => 'Old'), $errors), 'Optional rich text explicitly clears empty formatting');
$rich['required'] = true; $errors = array();
same(array(), Alify_CF::validate_values(array($rich), array('copy' => '<p>&nbsp;</p>'), array('copy' => 'Old'), $errors), 'Required rich text rejects blank formatting');
$rich['required'] = false; $errors = array();
same(array(), Alify_CF::validate_values(array($rich), array('copy' => array()), array('copy' => 'Old'), $errors), 'Rich text rejects structured payloads even when optional');
$errors = array(); Alify_CF_Repeater::schema(array($rich), $errors); same(true, (bool) $errors, 'Dynamic containers reject unsupported rich-text subfields');
$target = field('conditional_copy'); $target['condition'] = array('field' => 'field_copy', 'operator' => 'not_empty', 'value' => ''); $errors = array();
Alify_CF::validate_schema(array($rich, $target), 'post', 0, $errors); same(true, (bool) $errors, 'Rich text cannot be an ambiguous condition source');
$posts[801] = (object) array('ID' => 801, 'post_type' => 'post', 'post_status' => 'publish');
$posts[802] = (object) array('ID' => 802, 'post_type' => 'page', 'post_status' => 'private');
$posts[803] = (object) array('ID' => 803, 'post_type' => 'post', 'post_status' => 'trash');
$posts[804] = (object) array('ID' => 804, 'post_type' => 'attachment', 'post_status' => 'inherit');
same(801, Alify_CF_Post_Object::clean('801'), 'Post reference stores an integer ID');
same(802, Alify_CF_Post_Object::clean(802), 'Post reference accepts supported private pages structurally');
foreach (array('0801', '0', '-1', 0, true, array(), '801x', '99999999999999999999999', '9999', '803', '804') as $bad) { same('', Alify_CF_Post_Object::clean($bad), 'Post reference rejects noncanonical/dead/unsupported targets'); }
$denied = array(802);
same('', Alify_CF_Post_Object::clean('802', null, true), 'New unreadable reference is rejected');
same(802, Alify_CF_Post_Object::clean('802', 802, true), 'Unchanged existing unreadable reference may be retained without exposing its title');
$ref_field = field('related', 'post_object'); $errors = array();
same(array(), Alify_CF::validate_values(array($ref_field), array('related' => '802'), array(), $errors), 'Root validation rejects new unreadable reference');
$errors = array(); same(array('related' => ''), Alify_CF::validate_values(array($ref_field), array('related' => ''), array('related' => 801), $errors), 'Optional post reference explicitly clears');
$ref_field['required'] = true; $errors = array(); same(array(), Alify_CF::validate_values(array($ref_field), array('related' => ''), array('related' => 801), $errors), 'Required post reference rejects clear');
$ref_rows = field('refs', 'repeater'); $ref_rows['sub_fields'] = array($ref_field); $issue = ''; $location = null;
same('', Alify_CF_Repeater::clean($ref_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'related' => '802'))), null, true, $issue, $location), 'Unreadable post selection rejects entire row container');
same(array('row' => 0, 'subfield' => 'related'), $location, 'Post reference rejection identifies exact subfield');
same(array(array('related' => 802)), Alify_CF_Repeater::clean($ref_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'related' => '802'))), array(array('related' => 802)), true), 'Existing reference pool permits safe row reorder');
$meta[10]['_alify_cf_related'] = 802; same(null, alify_get_post_object('related', 10), 'Typed getter does not return unreadable private object');
$meta[10]['_alify_cf_related'] = 801; same($posts[801], alify_get_post_object('related', 10), 'Typed getter returns viewable post object');
$denied = array();
$relation = field('related_list', 'relationship');
$relation_payload = array('__complete' => '1', 'ids' => array('802', '801', '802'));
same(array(802, 801), Alify_CF::clean_value($relation, $relation_payload), 'Relationship preserves first-seen order and deduplicates');
foreach (array('', array(), array('ids' => array(801)), array('__complete' => '1', 'ids' => '801'), array('__complete' => '1', 'ids' => array('key' => 801)), array('__complete' => '1', 'ids' => array('0801')), array('__complete' => '1', 'ids' => array(803)), array('__complete' => '1', 'ids' => array_fill(0, 51, 801))) as $bad) {
    same('', Alify_CF_Relationship::clean($bad), 'Relationship rejects malformed/incomplete/dead/over-limit list atomically');
}
same(array(), Alify_CF_Relationship::clean(array('__complete' => '1')), 'Complete empty Relationship clears');
$denied = array(802);
same('', Alify_CF_Relationship::clean($relation_payload, array(), true), 'Unreadable new relationship target rejects whole list');
same(array(802, 801), Alify_CF_Relationship::clean($relation_payload, array(802), true), 'Existing unreadable target may be retained/reordered');
$errors = array(); $relation['required'] = true;
same(array(), Alify_CF::validate_values(array($relation), array('related_list' => array('__complete' => '1')), array('related_list' => array(801)), $errors), 'Required Relationship rejects empty selection');
$relation['required'] = false; $errors = array();
same(array('related_list' => array()), Alify_CF::validate_values(array($relation), array('related_list' => array('__complete' => '1')), array('related_list' => array(801)), $errors), 'Optional Relationship explicitly clears');
$meta[10]['_alify_cf_related_list'] = array(802, 801, 801);
same(array($posts[801]), alify_get_relationship('related_list', 10), 'Relationship getter omits unreadable objects and duplicates without changing storage');
same(array(802, 801, 801), alify_get_field('related_list', 10), 'Filtered Relationship getter leaves raw IDs unchanged');
$denied = array();
$radio = field('mode', 'radio'); $radio['choices'] = "0 : Zero\na : Alpha\nb : Beta";
same('Radio Button', Alify_CF::types()['radio'], 'Radio is a registered field type');
same('0', Alify_CF::clean_value($radio, 0), 'Radio preserves zero choice key');
same('a', Alify_CF::clean_value($radio, 'a'), 'Radio returns a single choice key');
foreach (array('unknown', array(), array('a'), true, 1.5) as $bad) {
    $errors = array(); same(array(), Alify_CF::validate_values(array($radio), array('mode' => $bad), array('mode' => 'b'), $errors), 'Malformed/unknown radio retains old value');
    same(true, count($errors) > 0, 'Radio rejection reports error');
}
$errors = array(); same(array('mode' => ''), Alify_CF::validate_values(array($radio), array('mode' => ''), array('mode' => 'b'), $errors), 'Optional radio explicitly clears');
$radio['required'] = true; $errors = array();
same(array(), Alify_CF::validate_values(array($radio), array('mode' => ''), array('mode' => 'a'), $errors), 'Required radio rejects empty');
$errors = array(); same(array('mode' => '0'), Alify_CF::validate_values(array($radio), array('mode' => '0'), array(), $errors), 'Required radio accepts zero');
$radio_rows = field('radio_rows', 'repeater'); $radio_rows['sub_fields'] = array($radio);
same(array(array('mode' => 'b')), Alify_CF_Repeater::clean($radio_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'mode' => 'b')))), 'Radio is a basic container subfield');
$issue = ''; $location = null;
same('', Alify_CF_Repeater::clean($radio_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'mode' => array()))), null, false, $issue, $location), 'Malformed radio rejects whole container');
same(array('row' => 0, 'subfield' => 'mode'), $location, 'Radio rejection identifies exact subfield');
$dependent = field('details'); $dependent['condition'] = array('field' => $radio['key'], 'operator' => 'equals', 'value' => '0');
same(true, Alify_CF::visible($dependent, array($radio, $dependent), array($radio['key'] => '0')), 'Radio is a server condition source');
same(false, Alify_CF::visible($dependent, array($radio, $dependent), array($radio['key'] => '')), 'Empty radio hides dependent');
$buttons = field('button_mode', 'button_group'); $buttons['choices'] = "0 : Zero\na : Alpha\nb : Beta";
same('Button Group', Alify_CF::types()['button_group'], 'Button Group is a registered field type');
same('0', Alify_CF::clean_value($buttons, 0), 'Button Group preserves zero choice key');
same('a', Alify_CF::clean_value($buttons, 'a'), 'Button Group returns a single choice key');
foreach (array('unknown', array(), array('a'), true, 1.5) as $bad) {
    $errors = array(); same(array(), Alify_CF::validate_values(array($buttons), array('button_mode' => $bad), array('button_mode' => 'b'), $errors), 'Malformed/unknown button_group retains old value');
    same(true, count($errors) > 0, 'Button Group rejection reports error');
}
$errors = array(); same(array('button_mode' => ''), Alify_CF::validate_values(array($buttons), array('button_mode' => ''), array('button_mode' => 'b'), $errors), 'Optional button_group explicitly clears');
$buttons['required'] = true; $errors = array();
same(array(), Alify_CF::validate_values(array($buttons), array('button_mode' => ''), array('button_mode' => 'a'), $errors), 'Required button_group rejects empty');
$errors = array(); same(array('button_mode' => '0'), Alify_CF::validate_values(array($buttons), array('button_mode' => '0'), array(), $errors), 'Required button_group accepts zero');
$buttons_rows = field('button_group_rows', 'repeater'); $buttons_rows['sub_fields'] = array($buttons);
same(array(array('button_mode' => 'b')), Alify_CF_Repeater::clean($buttons_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'button_mode' => 'b')))), 'Button Group is a basic container subfield');
$issue = ''; $location = null;
same('', Alify_CF_Repeater::clean($buttons_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'button_mode' => array()))), null, false, $issue, $location), 'Malformed button_group rejects whole container');
same(array('row' => 0, 'subfield' => 'button_mode'), $location, 'Button Group rejection identifies exact subfield');
$dependent = field('details'); $dependent['condition'] = array('field' => $buttons['key'], 'operator' => 'equals', 'value' => '0');
same(true, Alify_CF::visible($dependent, array($buttons, $dependent), array($buttons['key'] => '0')), 'Button Group is a server condition source');
same(false, Alify_CF::visible($dependent, array($buttons, $dependent), array($buttons['key'] => '')), 'Empty button_group hides dependent');
$range = field('amount', 'range'); $range['min'] = '-1'; $range['max'] = '2'; $range['step'] = '0.125';
same('Range', Alify_CF::types()['range'], 'Range is registered');
foreach (array('-1' => '-1', '-0.000' => '0', '0.125' => '0.125', '1.500' => '1.5', '2' => '2') as $raw => $expected) { same($expected, Alify_CF::clean_value($range, $raw), 'Range canonicalizes exact step-aligned decimal'); }
foreach (array('3', '-1.125', '0.1', '1e0', '+1', '01', '0.0001', ' 1', array(), array('1'), true, false, null, INF, NAN) as $bad) {
    $errors = array(); same(array(), Alify_CF::validate_values(array($range), array('amount' => $bad), array('amount' => '1.5'), $errors), 'Range rejects out-of-bounds/off-step/malformed values');
    same(true, count($errors) > 0, 'Range invalid submission reports error');
}
$errors = array(); same(array('amount' => ''), Alify_CF::validate_values(array($range), array('amount' => ''), array('amount' => '1.5'), $errors), 'Optional Range clears explicitly');
$range['required'] = true; $errors = array(); same(array(), Alify_CF::validate_values(array($range), array('amount' => ''), array(), $errors), 'Required Range rejects blank');
$errors = array(); same(array('amount' => '0'), Alify_CF::validate_values(array($range), array('amount' => 0), array(), $errors), 'Required Range accepts zero');
$errors = array(); same(array('min' => '0', 'max' => '100', 'step' => '1'), Alify_CF_Range::settings(array(), $errors), 'Range supplies explicit default bounds');
foreach (array(array('min' => '1', 'max' => '1'), array('min' => '2', 'max' => '1'), array('step' => '0'), array('step' => '-1'), array('step' => '101'), array('min' => array()), array('max' => '1000001'), array('step' => '0.0001'), array('step' => 'any')) as $bad) {
    $errors = array(); same(array(), Alify_CF_Range::settings($bad, $errors), 'Range rejects invalid configuration'); same(true, count($errors) > 0, 'Invalid settings report error');
}
$errors = array(); $schema = Alify_CF::validate_schema(array($range), 'post', 9999, $errors); same(array(), $errors, 'Root Range schema accepted'); same('0.125', $schema[0]['step'], 'Root schema preserves Range step');
$range['default'] = '0.1'; $errors = array(); Alify_CF::validate_schema(array($range), 'post', 9999, $errors); same(true, count($errors) > 0, 'Invalid Range default rejects schema');
$range['default'] = '1.500'; $errors = array(); $subs = Alify_CF_Repeater::schema(array($range), $errors); same('1.5', $subs[0]['default'], 'Subfield default canonicalizes'); same('-1', $subs[0]['min'], 'Subfield retains bounds');
$range_rows = field('range_rows', 'repeater'); $range_rows['sub_fields'] = $subs; $issue = ''; $location = null;
same('', Alify_CF_Repeater::clean($range_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'amount' => array()))), null, false, $issue, $location), 'Malformed Range rejects complete container');
same(array('row' => 0, 'subfield' => 'amount'), $location, 'Range identifies exact invalid subfield');
$edge = field('edge', 'range'); $edge['min'] = '-1000000'; $edge['max'] = '1000000'; $edge['step'] = '0.001'; same('1000000', Alify_CF::clean_value($edge, '1000000'), 'Bounded integer arithmetic accepts largest edge');
$offset = field('offset', 'range'); $offset['min'] = '0.1'; $offset['max'] = '1'; $offset['step'] = '0.2'; same('0.3', Alify_CF::clean_value($offset, '0.300'), 'Step anchors at minimum'); same('', Alify_CF::clean_value($offset, '1'), 'Unaligned maximum is not implicitly snapped');
$password = field('masked_value', 'password');
same('Password', Alify_CF::types()['password'], 'Password is registered');
$password_value = "  Quotes' \\ <b>&%20 سلام  ";
same($password_value, Alify_CF::clean_value($password, $password_value), 'Password preserves whitespace, markup, percent sequences, slashes and UTF-8 exactly');
same(str_repeat('a', 1024), Alify_CF_Password::clean(str_repeat('a', 1024)), 'Password accepts 1024 byte bound');
foreach (array(array(), array('value'), true, false, 123, null, "line\nbreak", "tab\tvalue", "null\0byte", "bad\x7f", "bad\u{0085}", "\xff", str_repeat('a', 1025), str_repeat('س', 513)) as $bad) {
    $errors = array(); same(array(), Alify_CF::validate_values(array($password), array('masked_value' => $bad), array('masked_value' => 'saved'), $errors), 'Invalid Password retains saved value');
    same(true, count($errors) > 0, 'Invalid Password reports error');
}
$errors = array(); same(array('masked_value' => ''), Alify_CF::validate_values(array($password), array('masked_value' => ''), array('masked_value' => 'saved'), $errors), 'Optional Password explicitly clears');
$password['required'] = true; $errors = array(); same(array(), Alify_CF::validate_values(array($password), array('masked_value' => ''), array('masked_value' => 'saved'), $errors), 'Required Password rejects clear');
$errors = array(); same(array('masked_value' => '0'), Alify_CF::validate_values(array($password), array('masked_value' => '0'), array(), $errors), 'Required Password accepts zero string');
$password['default'] = 'forbidden-default'; $errors = array(); $schema = Alify_CF::validate_schema(array($password), 'post', 9999, $errors);
same(true, count($errors) > 0, 'Password schema rejects defaults'); same('', $schema[0]['default'], 'Password schema never retains a default');
$errors = array(); $subs = Alify_CF_Repeater::schema(array($password), $errors); same(true, count($errors) > 0, 'Password subfield rejects defaults'); same('', $subs[0]['default'], 'Password subfield default is empty');
$password['default'] = ''; $dependent = field('dependent'); $dependent['condition'] = array('field' => $password['key'], 'operator' => 'equals', 'value' => 'saved');
$errors = array(); Alify_CF::validate_schema(array($password, $dependent), 'post', 9999, $errors); same(true, count($errors) > 0, 'Password cannot be a condition source');
same(false, Alify_CF::visible($dependent, array($password, $dependent), array($password['key'] => 'saved')), 'Forged Password source fails closed');
$password_group = field('password_group', 'group'); $password_group['sub_fields'] = array($password);
$password_rows = field('password_rows', 'repeater'); $password_rows['sub_fields'] = array($password);
$password_flex = field('password_flex', 'flexible'); $password_flex['layouts'] = array(array('key' => 'layout_masked', 'name' => 'masked', 'label' => 'Masked', 'sub_fields' => array($password)));
$feedback_schema = array($password, $password_group, $password_rows, $password_flex, field('note'));
$previous = array('masked_value' => 'old-sensitive', 'password_group' => array('masked_value' => 'old-sensitive'), 'password_rows' => array(array('masked_value' => 'old-sensitive')), 'password_flex' => array(array('layout' => 'masked', 'fields' => array('masked_value' => 'old-sensitive'))));
$recovery = Alify_CF_Feedback::group($feedback_schema, array('masked_value' => 'attempt-sensitive', 'password_group' => array(), 'password_rows' => array(), 'password_flex' => array(), 'note' => 'recover me'), $previous, array('note' => 'Invalid'));
same(array('note' => 'recover me'), $recovery['values'], 'Recovery omits whole Password-containing fields/containers, not their saved or attempted values');
same(true, $recovery['password_omitted'], 'Password recovery omission is explained');
same(false, strpos(wp_json_encode($recovery), 'sensitive') !== false, 'Feedback contains no attempted or previous Password value');
$issue = ''; $location = null;
same('', Alify_CF_Repeater::clean($password_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'masked_value' => array()))), null, false, $issue, $location), 'Malformed Password rejects whole Repeater');
same(array('row' => 0, 'subfield' => 'masked_value'), $location, 'Password validation reports exact row/subfield');
$date_time = field('meeting_at', 'date_time');
same('Date Time', Alify_CF::types()['date_time'], 'Date Time is registered');
foreach (array('2024-02-29T09:30' => '2024-02-29 09:30:00', '2026-10-28 09:30:45' => '2026-10-28 09:30:45', '1000-01-01T00:00' => '1000-01-01 00:00:00', '9999-12-31T23:59:59' => '9999-12-31 23:59:59', '2026-03-08T02:30' => '2026-03-08 02:30:00') as $raw => $expected) { same($expected, Alify_CF::clean_value($date_time, $raw), 'Date Time canonicalizes without timezone conversion'); }
foreach (array('2023-02-29T09:30', '2026-04-31T09:30', '2026-01-00T09:30', '0999-12-31T23:59', '10000-01-01T00:00', '2026-10-28T24:00', '2026-10-28T09:60', '2026-10-28T09:30:60', '2026-10-28T09:30Z', '2026-10-28T09:30+05:00', '2026-10-28T09:30:00.5', '2026-10-28', "2026-10-28T09:30\n", array(), array('value'), false, 123, null) as $bad) {
    $errors = array(); same(array(), Alify_CF::validate_values(array($date_time), array('meeting_at' => $bad), array('meeting_at' => '2026-10-28 09:30:00'), $errors), 'Date Time rejects malformed/nonexistent calendar values'); same(true, count($errors) > 0, 'Date Time rejection reports error');
}
$errors = array(); same(array('meeting_at' => ''), Alify_CF::validate_values(array($date_time), array('meeting_at' => ''), array(), $errors), 'Optional Date Time clears');
$date_time['required'] = true; $errors = array(); same(array(), Alify_CF::validate_values(array($date_time), array('meeting_at' => ''), array(), $errors), 'Required Date Time rejects blank');
$date_time['default'] = '2024-02-29T09:30'; $errors = array(); $schema = Alify_CF::validate_schema(array($date_time), 'post', 9999, $errors);
same(array(), $errors, 'Date Time schema accepted'); same('2024-02-29 09:30:00', $schema[0]['default'], 'Date Time default canonicalized');
$date_time['default'] = '2023-02-29T09:30'; $errors = array(); Alify_CF::validate_schema(array($date_time), 'post', 9999, $errors); same(true, count($errors) > 0, 'Invalid Date Time default rejects schema');
$errors = array(); Alify_CF_Repeater::schema(array($date_time), $errors); same(true, count($errors) > 0, 'Invalid subfield default rejects schema');
$date_time['default'] = ''; $date_rows = field('date_rows', 'repeater'); $date_rows['sub_fields'] = array($date_time);
$issue = ''; $location = null;
same('', Alify_CF_Repeater::clean($date_rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'meeting_at' => array()))), null, false, $issue, $location), 'Malformed Date Time rejects whole container');
same(array('row' => 0, 'subfield' => 'meeting_at'), $location, 'Date Time error locates subfield');
$dependent = field('date_detail'); $dependent['condition'] = array('field' => $date_time['key'], 'operator' => 'equals', 'value' => '2024-02-29 09:30:00');
same(true, Alify_CF::visible($dependent, array($date_time, $dependent), array($date_time['key'] => '2024-02-29 09:30:00')), 'Date Time is an eligible condition source');
$date_recovery = Alify_CF_Feedback::group(array($date_time), array('meeting_at' => '2023-02-29T09:30'), array('meeting_at' => '2024-02-29 09:30:00'), array('meeting_at' => 'Invalid'));
same('2024-02-29 09:30:00', $date_recovery['values']['meeting_at'], 'Unrepresentable Date Time recovery uses saved value, not an accidental blank');
$date_recovery = Alify_CF_Feedback::group(array($date_rows), array('date_rows' => array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'meeting_at' => 'bad-date')))), array('date_rows' => array(array('meeting_at' => '2024-02-29 09:30:00'))), array('date_rows' => 'Invalid'));
same(array(array('meeting_at' => '2024-02-29 09:30:00')), $date_recovery['values']['date_rows'], 'Unrepresentable Date Time subfield uses saved container recovery');
$format_cases = array(
    'date' => array('2024-02-29', array('Y-m-d' => '2024-02-29', 'd/m/Y' => '29/02/2024', 'm/d/Y' => '02/29/2024', 'j M Y' => '29 Feb 2024', 'F j, Y' => 'February 29, 2024')),
    'time' => array('13:05', array('H:i' => '13:05', 'H:i:s' => '13:05:00', 'g:i a' => '1:05 pm', 'g:i A' => '1:05 PM', 'g:i:s A' => '1:05:00 PM')),
    'date_time' => array('2024-02-29 13:05:09', array('Y-m-d H:i:s' => '2024-02-29 13:05:09', 'Y-m-d H:i' => '2024-02-29 13:05', 'd/m/Y H:i' => '29/02/2024 13:05', 'm/d/Y g:i A' => '02/29/2024 1:05 PM', 'j M Y g:i A' => '29 Feb 2024 1:05 PM')),
);
foreach ($format_cases as $type => $case) {
    $field = field('formatted', $type);
    foreach ($case[1] as $format => $expected) {
        $field['display_format'] = $format; $field['return_format'] = $format; $errors = array();
        same(array('display_format' => $format, 'return_format' => $format), Alify_CF_Temporal_Format::settings($field, $errors), 'Supported temporal preset accepted');
        same($expected, Alify_CF_Temporal_Format::format($field, $case[0]), 'Return format matches preset');
        same($expected, Alify_CF_Temporal_Format::format($field, $case[0], 'display_format'), 'Display format matches return formatter');
    }
    foreach (array('U', 'c', '<script>', array(), null, '') as $bad) { $field['return_format'] = $bad; $errors = array(); Alify_CF_Temporal_Format::settings($field, $errors); same(true, count($errors) > 0, 'Unsupported format rejected'); }
    same('', Alify_CF_Temporal_Format::format($field, ''), 'Empty temporal remains empty'); same(null, Alify_CF_Temporal_Format::format($field, null), 'Missing temporal remains null');
    same('', Alify_CF_Temporal_Format::format($field, "bad\0value"), 'Null-byte legacy values do not throw');
}
$formatted_date = field('day', 'date'); $formatted_date['return_format'] = 'd/m/Y';
$formatted_time = field('clock', 'time'); $formatted_time['return_format'] = 'g:i A';
$formatted_group = field('schedule', 'group'); $formatted_group['sub_fields'] = array($formatted_date, $formatted_time);
$raw_schedule = array('day' => '2024-02-29', 'clock' => '00:00', 'legacy' => 'untouched');
same(array('day' => '29/02/2024', 'clock' => '12:00 AM', 'legacy' => 'untouched'), Alify_CF_Temporal_Format::field($formatted_group, $raw_schedule), 'Group formats known temporal subfields without losing unknown keys');
same('2024-02-29', $raw_schedule['day'], 'Formatting never mutates raw container');
$formatted_group['type'] = 'repeater'; same(array(array('day' => '29/02/2024', 'clock' => '12:00 AM', 'legacy' => 'untouched')), Alify_CF_Temporal_Format::field($formatted_group, array($raw_schedule)), 'Repeater applies subfield return formats');
$errors = array(); same(array(), Alify_CF_Temporal_Format::settings(field('legacy', 'date'), $errors), 'Legacy schema settings retain shape');
same('', Alify_CF_Temporal_Format::format($formatted_date, '2023-02-29'), 'Impossible legacy date does not normalize');
same('', Alify_CF_Temporal_Format::format($formatted_time, '24:00'), 'Invalid legacy time does not normalize');
// Choice batch: layouts, defaults, multi/searchable Select and formatted returns.
$multi = field('choice_many', 'select'); $multi['multiple'] = true; $multi['searchable'] = true; $multi['return_format'] = 'array'; $multi['choices'] = "0 : Zero\na : Alpha\nb : Beta";
$errors = array(); same(array('return_format' => 'array', 'multiple' => true, 'searchable' => true), Alify_CF_Choice::settings($multi, $errors), 'Multi/search/return settings validated together');
$multi_payload = array('__complete' => '1', 'values' => array('b', '0', 'a', 'b'));
same(array('0', 'a', 'b'), Alify_CF::clean_value($multi, $multi_payload), 'Multi Select stores unique keys in definition order including zero');
foreach (array('', array(), array('values' => array('a')), array('__complete' => '1', 'values' => null), array('__complete' => '1', 'values' => 'a'), array('__complete' => '1', 'values' => array('unknown')), array('__complete' => '1', 'values' => array('key' => 'a')), array('__complete' => '1', 'values' => array(true)), array('__complete' => '1', 'values' => array_fill(0, 101, 'a'))) as $bad) {
    $errors = array(); same(array(), Alify_CF::validate_values(array($multi), array('choice_many' => $bad), array('choice_many' => array('a')), $errors), 'Malformed/incomplete/unknown/over-limit multi Select retains whole field'); same(true, count($errors) > 0, 'Invalid multi Select reports error');
}
$errors = array(); same(array('choice_many' => array()), Alify_CF::validate_values(array($multi), array('choice_many' => array('__complete' => '1')), array(), $errors), 'Complete empty multi Select clears');
$multi['required'] = true; $errors = array(); same(array(), Alify_CF::validate_values(array($multi), array('choice_many' => array('__complete' => '1')), array(), $errors), 'Required multi Select rejects clear');
$multi['default'] = "b\n0"; $errors = array(); $multi_schema = Alify_CF::validate_schema(array($multi), 'post', 9999, $errors); same(array(), $errors, 'Multi Select default settings accepted'); same(array('0', 'b'), $multi_schema[0]['default'], 'Multi defaults normalize key order');
$checks = field('choice_checks', 'checkbox'); $checks['layout'] = 'horizontal'; $checks['default'] = "b\na"; $checks['return_format'] = 'label';
$errors = array(); $checked_schema = Alify_CF_Repeater::schema(array($checks), $errors); same(array('a', 'b'), $checked_schema[0]['default'], 'Checkbox subfield defaults accept key-per-line input'); same('horizontal', $checked_schema[0]['layout'], 'Checkbox subfield layout preserved');
$checks['default'] = 'unknown'; $errors = array(); Alify_CF::validate_schema(array($checks), 'post', 9999, $errors); same(true, count($errors) > 0, 'Unknown default key rejects entire schema');
foreach (array(array('layout' => 'diagonal', 'type' => 'radio'), array('multiple' => 'yes', 'type' => 'select'), array('searchable' => array(), 'type' => 'select'), array('return_format' => 'html', 'type' => 'checkbox')) as $bad) { $errors = array(); Alify_CF_Choice::settings($bad, $errors); same(true, count($errors) > 0, 'Invalid choice configuration rejected'); }
same(array(array('value' => '0', 'label' => 'Zero'), array('value' => 'a', 'label' => 'Alpha')), Alify_CF_Choice::format($multi, array('0', 'a')), 'Multi return format produces records');
same(array('Alpha', 'Beta'), Alify_CF_Choice::format($checks, array('a', 'b')), 'Checkbox return format produces labels');
$one = field('single', 'radio'); $one['return_format'] = 'array'; same(array('value' => 'a', 'label' => 'Alpha'), Alify_CF_Choice::format($one, 'a'), 'Scalar choice formatted return record'); same(null, Alify_CF_Choice::format($one, 'deleted-key'), 'Unknown saved key formats to null without rewriting storage');
$rows = field('many_rows', 'repeater'); $rows['sub_fields'] = array($multi); $issue = ''; $location = null;
same('', Alify_CF_Repeater::clean($rows, array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'choice_many' => array()))), null, false, $issue, $location), 'Incomplete multi Select rejects entire row container');
same(array('row' => 0, 'subfield' => 'choice_many'), $location, 'Multi Select error identifies exact row/subfield');
same(array('__complete' => '1', 'values' => array('a')), Alify_CF_Publication::stored_payload($multi, array('a')), 'Stored multi Select packs for omitted-payload publication validation');
$dependent = field('multi_detail'); $dependent['condition'] = array('field' => $multi['key'], 'operator' => 'equals', 'value' => '0'); same(true, Alify_CF::visible($dependent, array($multi, $dependent), array($multi['key'] => array('0', 'b'))), 'Multi Select condition source uses key membership');
// Configuration batch: explicit alpha hex, palettes, bounded media, link defaults/formatting.
$alpha_color = field('alpha_color', 'color'); $alpha_color['alpha'] = true;
foreach (array('#AABBCC80' => '#aabbcc80', '#123456' => '#123456', '#00000000' => '#00000000') as $raw => $expected) { same($expected, Alify_CF::clean_value($alpha_color, $raw), 'Alpha supports canonical 6/8 digit hex'); }
foreach (array('#abc', '#aabbcc8', 'rgba(1,2,3,0.5)', '#aabbccffx', '#123456\n') as $raw) { same('', Alify_CF::clean_value($alpha_color, $raw), 'Reject noncanonical alpha notation'); }
same('', Alify_CF::clean_value(field('opaque', 'color'), '#aabbcc80'), 'Opaque color remains six digit only');
$alpha_color['palette'] = "#AABBCC80\n#000000\n#000000"; $errors = array(); $config = Alify_CF_Field_Settings::settings($alpha_color, $errors); same(array(), $errors, 'Alpha palette validates'); same(array('#aabbcc80', '#000000'), $config['palette'], 'Palette normalizes/deduplicates');
foreach (array(array('alpha' => 'yes'), array('palette' => array_fill(0, 21, '#000000')), array('palette' => array('#bad')), array('palette' => array('x' => '#000000'))) as $bad) { $errors = array(); Alify_CF_Field_Settings::settings(array_merge($alpha_color, $bad), $errors); same(true, count($errors) > 0, 'Malformed color settings reject schema'); }
$config_link = field('config_link', 'link'); $config_link['return_format'] = 'url'; $config_link['default'] = array('url' => 'https://example.com/path', 'title' => 'Example', 'target' => '_blank');
$errors = array(); $config_schema = Alify_CF::validate_schema(array($config_link, $alpha_color), 'post', 9999, $errors); same(array(), $errors, 'Color and Link configuration validate together'); same($config_link['default'], $config_schema[0]['default'], 'Link default is unmarked canonical object'); same('https://example.com/path', Alify_CF_Temporal_Format::field($config_schema[0], $config_link['default']), 'Link URL formatted getter');
same($config_link['default'], Alify_CF_Temporal_Format::field(field('array_link', 'link'), $config_link['default']), 'Legacy Link return object remains unchanged');
foreach (array('https://example.com', array('url' => 'javascript:alert(1)', 'title' => '', 'target' => ''), array('url' => 'https://example.com', 'title' => '', 'target' => 'popup')) as $bad) { $errors = array(); Alify_CF_Field_Settings::link_default($bad, $errors); same(true, count($errors) > 0, 'Invalid Link defaults reject schema'); }
$media_config = field('restricted_media', 'image'); $media_config['allowed_mimes'] = array('application/pdf'); same('', Alify_CF::clean_value($media_config, '50'), 'Image MIME restriction enforced on server'); $media_config['allowed_mimes'] = array('image/png'); same(50, Alify_CF::clean_value($media_config, '50'), 'Matching image MIME accepted');
$gallery_config = field('bounded_gallery', 'gallery'); $gallery_config['min_items'] = 2; $gallery_config['max_items'] = 3;
same('', Alify_CF::clean_value($gallery_config, array('__complete' => '1', 'ids' => array(50))), 'Gallery minimum enforced when nonempty'); same(array(), Alify_CF::clean_value($gallery_config, array('__complete' => '1')), 'Optional bounded Gallery can still clear'); same(array(50, 52), Alify_CF::clean_value($gallery_config, array('__complete' => '1', 'ids' => array(50, 52))), 'Gallery within bounds accepted');
foreach (array(array('min_items' => 4, 'max_items' => 3), array('max_items' => 51), array('min_bytes' => 20, 'max_bytes' => 10), array('max_bytes' => '1e6'), array('min_bytes' => -1), array('allowed_mimes' => array('image/*')), array('allowed_mimes' => array('key' => 'image/png')), array('preview_size' => 'custom'), array('return_format' => 'html')) as $bad) { $errors = array(); Alify_CF_Field_Settings::settings(array_merge($gallery_config, $bad), $errors); same(true, count($errors) > 0, 'Invalid media settings rejected atomically'); }
$errors = array(); $media_config['return_format'] = 'url'; $media_config['max_bytes'] = '1024'; same(array('max_bytes' => 1024, 'allowed_mimes' => array('image/png'), 'return_format' => 'url'), Alify_CF_Field_Settings::settings($media_config, $errors), 'Media settings normalize decimal byte limits');
echo "PASS: $count PHP contract assertions\n";
