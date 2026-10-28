<?php
$root = getenv('ALIFY_WP_ROOT'); if (!$root || !is_file($root . '/wp-load.php') || !getenv('ALIFY_PROVIDER_VENDOR')) { throw new RuntimeException('Disposable provider fixture paths required.'); }
$mu = $root . '/wp-content/mu-plugins'; if (!is_dir($mu)) { mkdir($mu, 0755, true); }
file_put_contents($mu . '/alify-provider-fixture.php', '<?php if (getenv("ALIFY_NATIVE_PROVIDERS") === "1") { define("ALIFY_CF_WOOCOMMERCE", true); define("ALIFY_CF_POLYLANG", true); define("ALIFY_CF_TIMBER", true); define("ALIFY_CF_ABILITIES", true); require getenv("ALIFY_PROVIDER_VENDOR") . "/autoload.php"; Timber\\Timber::init(); }');
// CLI-style activation avoids Woo admin UI reading its tables before activation creates them.
define('WP_ADMIN', false); $_SERVER['REQUEST_URI'] = '/wp-admin/index.php'; $_SERVER['PHP_SELF'] = '/wp-admin/index.php'; $_SERVER['SCRIPT_NAME'] = '/wp-admin/index.php'; $_SERVER['HTTP_HOST'] = 'alify.test'; $_SERVER['REQUEST_METHOD'] = 'GET'; $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require $root . '/wp-load.php'; if (!defined('ALIFY_CF_TEST_SITE') || !ALIFY_CF_TEST_SITE) { throw new RuntimeException('Unmarked database.'); }
require_once ABSPATH . 'wp-admin/includes/plugin.php'; $admin = get_user_by('login', 'alify-test-admin'); wp_set_current_user($admin->ID);
foreach (array('woocommerce/woocommerce.php', 'polylang/polylang.php') as $plugin) { $result = activate_plugin($plugin); if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); } }
// Finish the vendor's initial cron setup after init, before the next plugins_loaded pass.
// Otherwise Jetpack schedules its first nonce cleanup before Woo's translated cron labels are available.
(new Automattic\Jetpack\Connection\Nonce_Handler())->init_schedule();
if (!wp_next_scheduled('jetpack_clean_nonces')) { throw new RuntimeException('Vendor cron fixture initialization failed.'); }
update_option('woocommerce_custom_orders_table_enabled', 'yes'); update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');
echo "::notice title=Native provider setup::PASS: native provider plugins activated for isolated adapter fixtures.\n";
