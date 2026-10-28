#!/usr/bin/env bash
set -euo pipefail
WP_VERSION="${WP_VERSION:-6.8.3}"
PHP_BIN="${PHP_BIN:-php}"
ROOT="${ALIFY_TEST_DIR:-${HOME}/.cache/alify-cf-integration-${WP_VERSION}}"
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
mkdir -p "$ROOT"
if [[ ! -d "$ROOT/wordpress/.git" ]]; then
  git clone --depth 1 --branch "$WP_VERSION" https://github.com/WordPress/WordPress.git "$ROOT/wordpress"
fi
if [[ ! -d "$ROOT/sqlite/.git" ]]; then
  git clone --depth 1 --branch v2.2.23 https://github.com/WordPress/sqlite-database-integration.git "$ROOT/sqlite"
fi
[[ "$(git -C "$ROOT/wordpress" describe --tags --exact-match)" == "$WP_VERSION" ]] || { echo 'WordPress version mismatch'; exit 1; }
[[ "$(git -C "$ROOT/sqlite" rev-parse HEAD)" == f3ea1a43ba525be382c7a9c17735b6b4d4b11d49 ]] || { echo 'SQLite fixture revision mismatch'; exit 1; }
if [[ -f "$ROOT/wordpress/wp-config.php" ]] && ! grep -q "define('ALIFY_CF_TEST_SITE', true)" "$ROOT/wordpress/wp-config.php"; then
  echo 'Refusing to overwrite an unmarked wp-config.php'; exit 1
fi
# Links point only into this disposable installation; plugin source remains in the checkout.
ln -sfn "$REPO" "$ROOT/wordpress/wp-content/plugins/alify-custom-field"
ln -sfn "$ROOT/sqlite/packages/plugin-sqlite-database-integration" "$ROOT/wordpress/wp-content/plugins/sqlite-database-integration"
cp "$ROOT/sqlite/packages/plugin-sqlite-database-integration/db.copy" "$ROOT/wordpress/wp-content/db.php"
cat > "$ROOT/wordpress/wp-config.php" <<'PHP'
<?php
define('ALIFY_CF_TEST_SITE', true);
define('DB_NAME', 'alify_test');
define('DB_USER', 'unused');
define('DB_PASSWORD', 'unused');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
define('DB_ENGINE', 'sqlite');
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', __DIR__ . '/../debug.log');
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('DISABLE_WP_CRON', true);
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') { $_SERVER['HTTPS'] = 'on'; }
define('WP_HOME', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'alify.test'));
define('WP_SITEURL', WP_HOME);
$table_prefix = 'wp_';
if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }
require_once ABSPATH . 'wp-settings.php';
PHP
: > "$ROOT/debug.log"
export ALIFY_WP_ROOT="$ROOT/wordpress"
"$PHP_BIN" "$REPO/tests/integration/install.php"
"$PHP_BIN" "$REPO/tests/integration/wordpress.php"
if [[ -s "$ROOT/debug.log" ]]; then
  echo 'Unexpected WordPress debug output:'; cat "$ROOT/debug.log"; exit 1
fi
