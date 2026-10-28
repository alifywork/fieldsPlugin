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
echo "PASS: $count PHP contract assertions\n";
