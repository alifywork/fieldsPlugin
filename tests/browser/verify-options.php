<?php
// CLI-only persistence checks: recovered editor input is deliberately not trusted as saved data.
define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
function browser_verify_fail($message) { fwrite(STDERR, $message . "\n"); exit(1); }
$root = getenv('ALIFY_WP_ROOT'); $file = getenv('ALIFY_BROWSER_FIXTURE');
if (PHP_SAPI !== 'cli' || !$root || !$file || !is_file($file)) { browser_verify_fail('Private browser fixture and CLI required.'); }
$_SERVER['HTTP_HOST'] = 'alify.test'; $_SERVER['REQUEST_METHOD'] = 'GET';
require $root . '/wp-load.php';
if (!defined('ALIFY_CF_TEST_SITE') || !ALIFY_CF_TEST_SITE) { browser_verify_fail('Refusing unmarked database.'); }
$fixture = json_decode(file_get_contents($file), true);
$phase = $argv[1] ?? '';
if (!in_array($phase, array('baseline', 'corrected'), true)) { browser_verify_fail('Unknown persistence phase.'); }
$expected = array('options_title' => $phase === 'baseline' ? 'Saved title' : 'Corrected title', 'options_tags' => array('a'), 'options_profile' => array('name' => $phase === 'baseline' ? 'Saved profile' : 'Corrected profile'), 'options_team' => array(array('name' => $phase === 'baseline' ? 'Option member' : 'Corrected member')), 'options_copy' => '<p>Options <strong>copy</strong></p>', 'options_related' => $fixture['reference'], 'options_many' => array($fixture['reference_second'], $fixture['reference']), 'options_mode' => 'a');
if (Alify_CF_Options::data('browser-http') !== $expected) { browser_verify_fail('HTTP options save violated persisted-value/atomicity expectations.'); }
if (alify_get_field('subtitle', $fixture['classic']) !== 'Classic subtitle') { browser_verify_fail('Options HTTP save changed post metadata.'); }
if (alify_get_option('options_copy', 'browser-http') !== '<p>Options <strong>copy</strong></p>') { browser_verify_fail('Rich Options content was not synchronized or retained.'); }
$autoload = $wpdb->get_var($wpdb->prepare("SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", 'alify_cf_options_browser-http'));
if (!in_array($autoload, array('no', 'off', 'auto-off'), true)) { browser_verify_fail('Browser options unexpectedly autoloaded.'); }
echo "PASS: 4 HTTP options persistence assertions ($phase).\n";
