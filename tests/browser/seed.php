<?php
$root = getenv('ALIFY_WP_ROOT'); $file = getenv('ALIFY_BROWSER_FIXTURE');
if (!$root || !$file) { throw new RuntimeException('Set ALIFY_WP_ROOT and ALIFY_BROWSER_FIXTURE for a disposable test site.'); }
$_SERVER['HTTP_HOST'] = 'alify.test'; $_SERVER['REQUEST_METHOD'] = 'GET';
require $root . '/wp-load.php';
if (!defined('ALIFY_CF_TEST_SITE') || !ALIFY_CF_TEST_SITE) { throw new RuntimeException('Refusing unmarked database.'); }
$admin = get_user_by('login', 'alify-test-admin'); wp_set_current_user($admin->ID);
if (is_file($file)) {
    $old = json_decode(file_get_contents($file), true);
    foreach (array_merge(array($old['group'], $old['classic'], $old['block'], $old['rest'] ?? 0), $old['images']) as $id) { if ($id) { wp_delete_post($id, true); } }
}

$user = get_user_by('login', 'alify-browser');
$password = wp_generate_password(30, false);
if ($user) { wp_set_password($password, $user->ID); $uid = $user->ID; }
else { $uid = wp_insert_user(array('user_login' => 'alify-browser', 'user_pass' => $password, 'role' => 'administrator')); }
function browser_field($name, $type = 'text', $required = false) { return array('key' => 'field_' . $name, 'name' => $name, 'label' => ucfirst($name), 'type' => $type, 'required' => $required, 'choices' => "a : Alpha\nb : Beta", 'instructions' => '', 'default' => ''); }
$group = wp_insert_post(array('post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => 'Browser fields'));
$details = browser_field('details', 'text', true); $details['condition'] = array('field' => 'field_show_details', 'operator' => 'equals', 'value' => '1');
$team = browser_field('team', 'repeater'); $team['sub_fields'] = array(browser_field('name', 'text', true), browser_field('photo', 'image'));
$fields = array(browser_field('subtitle', 'text', true), browser_field('tags', 'checkbox', true), browser_field('show_details', 'true_false'), $details, browser_field('photos', 'gallery'), $team);
$_POST = wp_slash(array('alify_schema_nonce' => wp_create_nonce('alify_schema_' . $group), 'alify_schema' => wp_json_encode($fields), 'alify_location' => 'post'));
wp_update_post(array('ID' => $group, 'post_status' => 'publish')); $_POST = array();
$classic = wp_insert_post(array('post_title' => 'Browser classic', 'post_status' => 'draft', 'post_author' => $uid));
$block = wp_insert_post(array('post_title' => 'Browser block', 'post_status' => 'draft', 'post_author' => $uid));
$rest = wp_insert_post(array('post_title' => 'Browser REST', 'post_status' => 'draft', 'post_author' => $uid));
$images = array();
foreach (array('One', 'Two') as $name) {
    $upload = wp_upload_bits('alify-browser-' . strtolower($name) . '.png', null, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5V8AAAAASUVORK5CYII='));
    $id = wp_insert_attachment(array('post_title' => 'Alify Browser Image ' . $name, 'post_mime_type' => 'image/png', 'post_status' => 'inherit'), $upload['file']);
    wp_update_attachment_metadata($id, array('width' => 1, 'height' => 1, 'file' => _wp_relative_upload_path($upload['file']))); $images[] = $id;
}
wp_mkdir_p(WP_CONTENT_DIR . '/mu-plugins');
$mu = '<?php if (!defined("ALIFY_CF_TEST_SITE") || !ALIFY_CF_TEST_SITE) { return; } add_filter("use_block_editor_for_post", function($enabled, $post) { return $post->ID === ' . (int) $classic . ' || isset($_GET["alify_classic"]) ? false : $enabled; }, 10, 2); add_action("admin_init", function() { remove_action("admin_init", "_maybe_update_core"); remove_action("admin_init", "_maybe_update_plugins"); remove_action("admin_init", "_maybe_update_themes"); });';
file_put_contents(WP_CONTENT_DIR . '/mu-plugins/alify-browser-test.php', $mu);
file_put_contents($file, wp_json_encode(array('username' => 'alify-browser', 'password' => $password, 'classic' => $classic, 'block' => $block, 'rest' => $rest, 'group' => $group, 'images' => $images)));
chmod($file, 0600);
echo "Browser fixtures seeded (credentials kept in private fixture file).\n";
