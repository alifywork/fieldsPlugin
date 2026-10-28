<?php
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$root = getenv('ALIFY_WP_ROOT');
if (!$root || !is_file($root . '/wp-load.php')) { fwrite(STDERR, "Set ALIFY_WP_ROOT to a disposable WordPress installation.\n"); exit(1); }
define('WP_ADMIN', true);
$_SERVER['HTTP_HOST'] = 'alify.test';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require $root . '/wp-load.php';
if (!defined('ALIFY_CF_TEST_SITE') || ALIFY_CF_TEST_SITE !== true) { throw new RuntimeException('Refusing unmarked WordPress database.'); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
$count = 0; $fixtures = array();
function expect_same($expected, $actual, $label) {
    global $count;
    if ($expected !== $actual) { throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)); }
    $count++;
}
function test_field($name, $type = 'text', $required = false) {
    return array('key' => 'field_' . $name, 'name' => $name, 'label' => ucfirst($name), 'type' => $type, 'required' => $required, 'default' => '', 'instructions' => 'Test guidance', 'choices' => "a : Alpha\nb : Beta");
}
function fixture_post($type = 'post') {
    global $fixtures;
    $_POST = array();
    $id = wp_insert_post(array('post_type' => $type, 'post_status' => 'draft', 'post_title' => 'Alify test ' . wp_generate_uuid4()), true);
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    $fixtures[] = $id; return $id;
}
function configure_group($id, $fields, $location = 'post') {
    $_POST = wp_slash(array('alify_schema_nonce' => wp_create_nonce('alify_schema_' . $id), 'alify_schema' => wp_json_encode($fields), 'alify_location' => $location));
    return wp_update_post(array('ID' => $id, 'post_status' => 'publish'), true);
}
function save_values($id, $group, $values, $status = 'draft') {
    $_POST = wp_slash(array('alify_values_nonce_' . $group => wp_create_nonce('alify_values_' . $id . '_' . $group), 'alify_values' => array($group => $values)));
    return wp_update_post(array('ID' => $id, 'post_status' => $status), true);
}
$admin = get_user_by('login', 'alify-test-admin');
if (!$admin) { throw new RuntimeException('Run install.php first.'); }
wp_set_current_user($admin->ID);
expect_same(true, is_plugin_active('alify-custom-field/alify-custom-field.php'), 'Activation persisted across requests');
expect_same(true, post_type_exists(Alify_CF::GROUP), 'CPT registered on normal init');
expect_same(true, current_user_can('edit_posts', Alify_CF::GROUP), 'Admin can manage content');
$group = fixture_post(Alify_CF::GROUP);
$post = fixture_post();
$image = wp_insert_attachment(array('post_title' => 'Fixture image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'), false, $post, true);
$pdf = wp_insert_attachment(array('post_title' => 'Fixture PDF', 'post_mime_type' => 'application/pdf', 'post_status' => 'inherit'), false, $post, true);
$fixtures[] = $image; $fixtures[] = $pdf;
$image_file = wp_upload_bits('alify-fixture.png', null, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5V8AAAAASUVORK5CYII='));
if ($image_file['error']) { throw new RuntimeException($image_file['error']); }
update_attached_file($image, $image_file['file']);
wp_update_attachment_metadata($image, array('width' => 1, 'height' => 1, 'file' => _wp_relative_upload_path($image_file['file'])));
$pdf_file = wp_upload_bits('alify-fixture.pdf', null, '%PDF-1.4 test fixture');
if ($pdf_file['error']) { throw new RuntimeException($pdf_file['error']); }
update_attached_file($pdf, $pdf_file['file']);
try {
    $fields = array(test_field('title'), test_field('body', 'textarea'), test_field('amount', 'number'), test_field('email', 'email', true), test_field('website', 'url'), test_field('choice', 'select'), test_field('tags', 'checkbox'), test_field('enabled', 'true_false'), test_field('date', 'date'), test_field('image', 'image'), test_field('file', 'file'), test_field('gallery', 'gallery'));
    $rows = test_field('team', 'repeater'); $rows['sub_fields'] = array(test_field('name', 'text', true), test_field('photos', 'gallery'));
    $fields[] = $rows;
    configure_group($group, $fields);
    expect_same(13, count(Alify_CF::schema($group)), 'All field schemas stored in real postmeta');
    expect_same($group, Alify_CF::groups('post')[0]->ID, 'Published group lookup');
    $values = array('title' => 'سلام "Alify" C:\\site', 'body' => "Line 1\nLine 2", 'amount' => '12.5', 'email' => 'hello@example.com', 'website' => 'https://example.com', 'choice' => 'a', 'tags' => array('a', 'b'), 'enabled' => '1', 'date' => '2024-02-29', 'image' => (string) $image, 'file' => (string) $pdf, 'gallery' => array('__complete' => '1', 'ids' => array((string) $image)), 'team' => array('__complete' => '1', 'rows' => array(array('__row_complete' => '1', 'name' => 'Member', 'photos' => array('__complete' => '1', 'ids' => array((string) $image))))));
    save_values($post, $group, $values);
    expect_same($values['title'], alify_get_field('title', $post), 'Unicode/quotes/backslashes round-trip');
    expect_same($values['body'], alify_get_field('body', $post), 'Multiline round-trip');
    expect_same('12.5', alify_get_field('amount', $post), 'Number round-trip');
    expect_same('hello@example.com', alify_get_field('email', $post), 'Email round-trip');
    expect_same('https://example.com', alify_get_field('website', $post), 'URL round-trip');
    expect_same('a', alify_get_field('choice', $post), 'Select round-trip');
    expect_same(array('a', 'b'), alify_get_field('tags', $post), 'Checkbox serialization');
    expect_same('1', alify_get_field('enabled', $post), 'Toggle round-trip');
    expect_same('2024-02-29', alify_get_field('date', $post), 'Date round-trip');
    // WordPress stores scalar metadata as strings, but serialized arrays retain integer IDs.
    expect_same((string) $image, alify_get_field('image', $post), 'Image metadata return type');
    expect_same((string) $pdf, alify_get_field('file', $post), 'File metadata return type');
    expect_same(array($image), alify_get_field('gallery', $post), 'Gallery retains integer IDs in serialized array');
    expect_same('Member', alify_get_rows('team', $post)[0]['name'], 'Repeater storage');
    expect_same(array($image), alify_get_rows('team', $post)[0]['photos'], 'Gallery subfield storage');
    $invalid = $values; $invalid['email'] = 'invalid'; $invalid['image'] = (string) $pdf;
    save_values($post, $group, $invalid);
    expect_same('hello@example.com', alify_get_field('email', $post), 'Invalid required field retains value');
    expect_same((string) $image, alify_get_field('image', $post), 'Non-image rejected');
    expect_same(true, (bool) get_transient('alify_cf_errors_' . $admin->ID . '_' . $post), 'Validation error recorded');
    $feedback = Alify_CF_Feedback::get('post_' . $post);
    expect_same('invalid', $feedback[$group]['values']['email'], 'Invalid email recovered for editing');
    expect_same(true, isset($feedback[$group]['errors']['email']), 'Field-level error recorded');
    ob_start(); Alify_CF::values(get_post($post), get_post($group)); $form = ob_get_clean();
    expect_same(true, strpos($form, 'aria-invalid="true"') !== false, 'Scalar errors marked accessibly');
    expect_same(true, strpos($form, 'value="invalid"') !== false, 'Rejected input shown without modifying saved data');
    expect_same(true, strpos($form, 'alify-field-error') !== false, 'Field-level error rendered');
    $stale = $feedback[$group]; $stale['schema'] = 'outdated';
    ob_start(); Alify_CF::values(get_post($post), get_post($group), null, $stale); $stale_form = ob_get_clean();
    expect_same(false, strpos($stale_form, 'value="invalid"') !== false, 'Stale schema recovery is ignored');
    expect_same(false, strpos($stale_form, 'alify-field-error') !== false, 'Stale schema errors are not rendered');
    unset($values['tags'], $values['enabled']);
    save_values($post, $group, $values);
    expect_same(array(), alify_get_field('tags', $post), 'Unchecked checkbox clears');
    expect_same('0', alify_get_field('enabled', $post), 'Unchecked toggle clears');
    expect_same(array(), Alify_CF_Feedback::get('post_' . $post), 'Successful correction clears recovery');
    $_POST = array('alify_values_nonce_' . $group => 'invalid', 'alify_values' => array($group => array('title' => 'forged')));
    wp_update_post(array('ID' => $post, 'post_status' => 'draft'));
    expect_same($values['title'], alify_get_field('title', $post), 'Nonce rejection through save_post hook');
    $_POST = wp_slash(array('alify_values_nonce_' . $group => wp_create_nonce('alify_values_' . $post . '_' . $group), 'alify_values' => array($group => array('title' => 'revision overwrite'))));
    $revision = wp_insert_post(array('post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $post, 'post_title' => 'Fixture revision'), true);
    if (is_wp_error($revision)) { throw new RuntimeException($revision->get_error_message()); }
    $fixtures[] = $revision;
    expect_same(false, metadata_exists('post', $revision, '_alify_cf_title'), 'Revision hook does not write field values');
    expect_same($values['title'], alify_get_field('title', $post), 'Revision hook preserves parent fields');
    // Actual role/capability and ownership checks, not test doubles.
    $editor = wp_insert_user(array('user_login' => 'alify-editor-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'editor'));
    wp_set_current_user($editor);
    expect_same(false, current_user_can('edit_post', $group), 'Editor cannot edit group');
    expect_same(true, current_user_can('edit_post', $post), 'Editor can edit content');
    expect_same(array(), Alify_CF_Feedback::get('post_' . $post), 'Recovery scoped to the current user');
    save_values($post, $group, $values);
    expect_same($values['title'], alify_get_field('title', $post), 'Editor field save');
    $errors = array();
    expect_same(false, Alify_CF_Options::save('site', array(), $errors), 'Editor denied global options');
    wp_set_current_user(0);
    Alify_CF::save($post, get_post($post));
    expect_same($values['title'], alify_get_field('title', $post), 'Anonymous cannot mutate fields');
    wp_set_current_user($admin->ID);
    $conditional = test_field('details', 'text', true);
    $conditional['condition'] = array('field' => 'field_show', 'operator' => 'equals', 'value' => '1');
    configure_group($group, array(test_field('show', 'true_false'), $conditional));
    update_post_meta($post, '_alify_cf_details', 'Retained details');
    save_values($post, $group, array('details' => 'forged hidden value'));
    expect_same('Retained details', alify_get_field('details', $post), 'Hidden conditional data preserved');
    // Global settings use real wp_options and never post metadata.
    configure_group($group, array(test_field('contact', 'email', true), $rows), 'options:site');
    $request = array('alify_options_nonce' => wp_create_nonce('alify_options_site'), 'alify_options_complete' => '1', 'alify_values' => array($group => array('__group_complete' => '1', 'contact' => 'site@example.com', 'team' => $values['team'])));
    $errors = array();
    expect_same(true, Alify_CF_Options::save('site', $request, $errors), 'Real options save');
    expect_same('site@example.com', alify_get_option('contact'), 'Options helper storage');
    expect_same('Member', alify_get_option('team')[0]['name'], 'Options repeater storage');
    expect_same(false, metadata_exists('post', $post, '_alify_cf_contact'), 'Options isolated from postmeta');
    $request['alify_values'][$group]['contact'] = 'invalid'; $request['alify_values'][$group]['team'] = array('__complete' => '1'); $errors = array();
    expect_same(false, Alify_CF_Options::save('site', $request, $errors), 'Options atomic failure');
    expect_same('Member', alify_get_option('team')[0]['name'], 'Other options preserved on failure');
    $feedback = Alify_CF_Feedback::get('options_site');
    expect_same('invalid', $feedback[$group]['values']['contact'], 'Options rejected input recovered');
    expect_same(array(), $feedback[$group]['values']['team'], 'Options attempted row removal recovered without changing saved rows');
    ob_start(); Alify_CF_Options::render('site'); $failed_form = ob_get_clean();
    expect_same(true, strpos($failed_form, 'value="invalid"') !== false, 'Options render recovered input');
    expect_same(true, strpos($failed_form, 'alify-field-error') !== false, 'Options render inline errors');
    $request['alify_values'][$group]['contact'] = 'fixed@example.com'; $errors = array();
    expect_same(true, Alify_CF_Options::save('site', $request, $errors), 'Options correction succeeds');
    expect_same(array(), Alify_CF_Feedback::get('options_site'), 'Options success clears recovery');
    $autoload = $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'alify_cf_options_site'));
    expect_same(true, in_array($autoload, array('no', 'off', 'auto-off'), true), 'Options not autoloaded');
    ob_start(); Alify_CF_Options::render('site'); $html = ob_get_clean();
    expect_same(true, strpos($html, 'alify_options_complete') !== false, 'Real options screen render');
    // Drafting a group removes it from its assigned screen.
    $_POST = array(); wp_update_post(array('ID' => $group, 'post_status' => 'draft'));
    expect_same(array(), Alify_CF::groups('options:site'), 'Draft groups do not render');
    echo "PASS: $count real WordPress assertions; WordPress $wp_version; PHP " . PHP_VERSION . '; ' . get_class($wpdb) . "\n";
} finally {
    wp_set_current_user($admin->ID); $_POST = array();
    foreach (array_reverse($fixtures) as $id) { wp_delete_post($id, true); }
    delete_option('alify_cf_options_site');
    Alify_CF_Feedback::put('post_' . $post, array()); Alify_CF_Feedback::put('options_site', array());
    if (isset($editor)) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($editor); }
}
