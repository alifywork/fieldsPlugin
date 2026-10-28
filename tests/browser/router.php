<?php
// Router for the disposable native-PHP CI server, not a production entry point.
$root = getenv('ALIFY_WP_ROOT');
if (!$root || !is_file($root . '/wp-config.php') || strpos(file_get_contents($root . '/wp-config.php'), "define('ALIFY_CF_TEST_SITE', true)") === false) {
    http_response_code(503); exit('Disposable test site required.');
}
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if (is_file($root . $path) || is_dir($root . $path)) { return false; }
require $root . '/index.php';
