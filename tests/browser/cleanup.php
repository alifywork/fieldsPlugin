<?php
$root = getenv('ALIFY_WP_ROOT'); $file = getenv('ALIFY_BROWSER_FIXTURE');
if (!$root || !$file || !is_file($file)) { exit; }
$_SERVER['HTTP_HOST'] = 'alify.test'; $_SERVER['REQUEST_METHOD'] = 'GET';
require $root . '/wp-load.php';
if (!defined('ALIFY_CF_TEST_SITE') || !ALIFY_CF_TEST_SITE) { throw new RuntimeException('Refusing unmarked database.'); }
$fixture = json_decode(file_get_contents($file), true);
wp_set_current_user(get_user_by('login', 'alify-test-admin')->ID);
$_POST = array();
foreach (array_merge(array($fixture['group'], $fixture['classic'], $fixture['block'], $fixture['rest'], $fixture['options_group'] ?? 0, $fixture['reference'] ?? 0, $fixture['reference_second'] ?? 0), $fixture['images'], $fixture['imports'] ?? array()) as $id) { if ($id) { wp_delete_post($id, true); } }
require_once ABSPATH . 'wp-admin/includes/user.php';
$user = get_user_by('login', $fixture['username']); if ($user) { wp_delete_user($user->ID); }
delete_option('alify_cf_options_browser-http');
$editor = get_user_by('login', $fixture['editor_username'] ?? 'alify-browser-editor'); if ($editor) { wp_delete_user($editor->ID); }
$mu = WP_CONTENT_DIR . '/mu-plugins/alify-browser-test.php'; if (is_file($mu)) { unlink($mu); }
unlink($file);
echo "Browser fixtures removed.\n";
