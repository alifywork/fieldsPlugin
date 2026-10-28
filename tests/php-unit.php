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
Alify_CF::save(10, $posts[10]);
same('B', alify_get_rows('team', 10)[0]['name'], 'Rows saved and retrieved');
$_POST['alify_values'][1] = array();
Alify_CF::save(10, $posts[10]);
same(2, count(alify_get_rows('team', 10)), 'Missing repeater preserves data');
$_POST['alify_values'][1]['team'] = array('__complete' => '1');
Alify_CF::save(10, $posts[10]);
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
Alify_CF::save(10, $posts[10]);
same(2, count(alify_get_rows('team', 10)), 'Hidden repeater payload ignored');
same(array(), alify_get_rows('missing', 10), 'Missing rows return empty array');
$required_repeater = $stored[0]; $required_repeater['required'] = true;
configure(1, array($required_repeater));
$meta[10]['_alify_cf_team'] = $clean;
$_POST = array('alify_values_nonce_1' => 'valid', 'alify_values' => array(1 => array('team' => array('__complete' => '1'))));
Alify_CF::save(10, $posts[10]);
same(2, count(alify_get_rows('team', 10)), 'Required repeater needs at least one row');
$_POST['alify_values'][1]['team'] = array('__complete' => '1', 'rows' => array(array('name' => wp_slash('C:\\team'), '__row_complete' => '1')));
Alify_CF::save(10, $posts[10]);
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
echo "PASS: $count PHP contract assertions\n";
