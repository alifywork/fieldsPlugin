<?php
$root = getenv('ALIFY_WP_ROOT'); $file = getenv('ALIFY_BROWSER_FIXTURE');
if (!$root || !$file) { throw new RuntimeException('Set ALIFY_WP_ROOT and ALIFY_BROWSER_FIXTURE for a disposable test site.'); }
$_SERVER['HTTP_HOST'] = 'alify.test'; $_SERVER['REQUEST_METHOD'] = 'GET';
require $root . '/wp-load.php';
if (!defined('ALIFY_CF_TEST_SITE') || !ALIFY_CF_TEST_SITE) { throw new RuntimeException('Refusing unmarked database.'); }
$admin = get_user_by('login', 'alify-test-admin'); wp_set_current_user($admin->ID);
if (is_file($file)) {
    $old = json_decode(file_get_contents($file), true);
    foreach (array_merge(array($old['group'], $old['classic'], $old['block'], $old['rest'] ?? 0, $old['options_group'] ?? 0, $old['reference'] ?? 0, $old['reference_second'] ?? 0), $old['images'], $old['imports'] ?? array()) as $id) { if ($id) { wp_delete_post($id, true); } }
}

$user = get_user_by('login', 'alify-browser');
$password = wp_generate_password(30, false);
if ($user) { wp_set_password($password, $user->ID); $uid = $user->ID; }
else { $uid = wp_insert_user(array('user_login' => 'alify-browser', 'user_pass' => $password, 'role' => 'administrator')); }
function browser_field($name, $type = 'text', $required = false) { return array('key' => 'field_' . $name, 'name' => $name, 'label' => ucfirst($name), 'type' => $type, 'required' => $required, 'choices' => "a : Alpha\nb : Beta", 'instructions' => '', 'default' => ''); }
$group = wp_insert_post(array('post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => 'Browser fields'));
$details = browser_field('details', 'text', true); $details['condition'] = array('relation' => 'all', 'rules' => array(array('field' => 'field_show_details', 'operator' => 'equals', 'value' => '1'), array('field' => 'field_tags', 'operator' => 'equals', 'value' => 'a')));
$alternate = browser_field('alternate', 'text', true); $alternate['condition'] = array('relation' => 'any', 'rules' => array(array('field' => 'field_show_details', 'operator' => 'equals', 'value' => '1'), array('field' => 'field_tags', 'operator' => 'equals', 'value' => 'b')));
$team = browser_field('team', 'repeater'); $team['min_rows'] = 1; $team['max_rows'] = 2; $team['sub_fields'] = array(browser_field('name', 'text', true), browser_field('photo', 'image'));
$profile = browser_field('profile', 'group'); $profile['sub_fields'] = array(browser_field('name', 'text', true), browser_field('tags', 'checkbox', true), browser_field('portrait', 'image'));
$flexible = browser_field('sections', 'flexible'); $flexible['layouts'] = array(array('key' => 'layout_browser_hero', 'name' => 'hero', 'label' => 'Hero', 'sub_fields' => array(browser_field('title', 'text', true), browser_field('photos', 'gallery'))), array('key' => 'layout_browser_text', 'name' => 'text', 'label' => 'Text', 'sub_fields' => array(browser_field('body', 'textarea', true))));
$radio = browser_field('mode', 'radio'); $radio['choices'] = "0 : Zero\na : Alpha\nb : Beta";
$buttons = browser_field('button_mode', 'button_group'); $buttons['choices'] = "0 : Zero\na : Alpha\nb : Beta"; $buttons['default'] = '0';
$button_details = browser_field('button_details'); $button_details['condition'] = array('field' => $buttons['key'], 'operator' => 'equals', 'value' => 'a');
$range = browser_field('amount', 'range'); $range['min'] = '-1'; $range['max'] = '2'; $range['step'] = '0.125';
$range_details = browser_field('range_details'); $range_details['condition'] = array('field' => $range['key'], 'operator' => 'equals', 'value' => '0.5');
$fields = array(browser_field('subtitle', 'text', true), browser_field('tags', 'checkbox', true), browser_field('show_details', 'true_false'), $details, browser_field('photos', 'gallery'), $team, $profile, $flexible, $alternate, browser_field('accent', 'color'), browser_field('opens', 'time'), browser_field('cta', 'link'), browser_field('rich_copy', 'wysiwyg'), browser_field('related', 'post_object'), browser_field('related_many', 'relationship'), $radio, $buttons, $button_details, $range, $range_details, browser_field('masked', 'password'));
$_POST = wp_slash(array('alify_schema_nonce' => wp_create_nonce('alify_schema_' . $group), 'alify_schema' => wp_json_encode($fields), 'alify_location' => 'post'));
wp_update_post(array('ID' => $group, 'post_status' => 'publish')); $_POST = array();
$classic = wp_insert_post(array('post_title' => 'Browser classic', 'post_status' => 'draft', 'post_author' => $uid));
$block = wp_insert_post(array('post_title' => 'Browser block', 'post_status' => 'draft', 'post_author' => $uid));
$rest = wp_insert_post(array('post_title' => 'Browser REST', 'post_status' => 'draft', 'post_author' => $uid));
$reference = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Browser reference target', 'post_author' => $uid));
$reference_second = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Browser reference target second', 'post_author' => $uid));
// Isolated options fixture; no built-in Site Settings values are touched.
Alify_CF_Options::register('browser-http', 'Browser HTTP Settings');
delete_option('alify_cf_options_browser-http');
$editor_password = wp_generate_password(30, false);
$editor = get_user_by('login', 'alify-browser-editor');
if ($editor) { wp_set_password($editor_password, $editor->ID); wp_update_user(array('ID' => $editor->ID, 'role' => 'editor')); }
else { wp_insert_user(array('user_login' => 'alify-browser-editor', 'user_pass' => $editor_password, 'role' => 'editor')); }
$options_group = wp_insert_post(array('post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => 'Browser options fields'));
$options_profile = browser_field('options_profile', 'group'); $options_profile['sub_fields'] = array(browser_field('name', 'text', true));
$options_team = browser_field('options_team', 'repeater'); $options_team['sub_fields'] = array(browser_field('name', 'text', true)); $options_team['min_rows'] = 1; $options_team['max_rows'] = 2;
$options_range = $range; $options_range['name'] = 'options_amount'; $options_range['key'] = 'field_options_amount';
$options_fields = array(browser_field('options_title', 'text', true), browser_field('options_tags', 'checkbox', true), $options_profile, $options_team, browser_field('options_copy', 'wysiwyg'), browser_field('options_related', 'post_object'), browser_field('options_many', 'relationship'), browser_field('options_mode', 'radio'), browser_field('options_buttons', 'button_group'), $options_range, browser_field('options_masked', 'password'));
$_POST = wp_slash(array('alify_schema_nonce' => wp_create_nonce('alify_schema_' . $options_group), 'alify_schema' => wp_json_encode($options_fields), 'alify_location' => 'options:browser-http'));
wp_update_post(array('ID' => $options_group, 'post_status' => 'publish')); $_POST = array();
$images = array();
foreach (array('One', 'Two') as $name) {
    $upload = wp_upload_bits('alify-browser-' . strtolower($name) . '.png', null, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5V8AAAAASUVORK5CYII='));
    $id = wp_insert_attachment(array('post_title' => 'Alify Browser Image ' . $name, 'post_mime_type' => 'image/png', 'post_status' => 'inherit'), $upload['file']);
    wp_update_attachment_metadata($id, array('width' => 1, 'height' => 1, 'file' => _wp_relative_upload_path($upload['file']))); $images[] = $id;
}
wp_mkdir_p(WP_CONTENT_DIR . '/mu-plugins');
$mu = '<?php if (!defined("ALIFY_CF_TEST_SITE") || !ALIFY_CF_TEST_SITE) { return; } add_filter("use_block_editor_for_post", function($enabled, $post) { return $post->ID === ' . (int) $classic . ' || isset($_GET["alify_classic"]) ? false : $enabled; }, 10, 2); add_action("admin_init", function() { remove_action("admin_init", "_maybe_update_core"); remove_action("admin_init", "_maybe_update_plugins"); remove_action("admin_init", "_maybe_update_themes"); }, 0);';
$mu .= ' add_action("init", function() { alify_add_options_page("browser-http", "Browser HTTP Settings"); });';
file_put_contents(WP_CONTENT_DIR . '/mu-plugins/alify-browser-test.php', $mu);
file_put_contents($file, wp_json_encode(array('username' => 'alify-browser', 'password' => $password, 'classic' => $classic, 'block' => $block, 'rest' => $rest, 'group' => $group, 'images' => $images, 'options_group' => $options_group, 'reference' => $reference, 'reference_second' => $reference_second, 'editor_username' => 'alify-browser-editor', 'editor_password' => $editor_password)));
chmod($file, 0600);
echo "Browser fixtures seeded (credentials kept in private fixture file).\n";
