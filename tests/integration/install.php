<?php
// Only run against a disposable WordPress database explicitly marked for this suite.
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
$root = getenv('ALIFY_WP_ROOT');
if (!$root || !is_file($root . '/wp-load.php')) { fwrite(STDERR, "Set ALIFY_WP_ROOT to a disposable WordPress installation.\n"); exit(1); }
define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST'] = 'alify.test';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require $root . '/wp-load.php';
if (!defined('ALIFY_CF_TEST_SITE') || ALIFY_CF_TEST_SITE !== true) { fwrite(STDERR, "Refusing unmarked database; define ALIFY_CF_TEST_SITE=true in test wp-config.php.\n"); exit(1); }
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (!is_blog_installed()) { wp_install('Alify Tests', 'alify-test-admin', 'tests@example.invalid', false, '', bin2hex(random_bytes(24))); }
$admin = get_user_by('login', 'alify-test-admin');
if (!$admin) { throw new RuntimeException('Test admin not found. Use a fresh disposable database.'); }
wp_set_current_user($admin->ID);
$result = activate_plugin('alify-custom-field/alify-custom-field.php');
if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
echo "Installed WordPress $wp_version; " . get_class($wpdb) . '; PHP ' . PHP_VERSION . "; plugin activated.\n";
