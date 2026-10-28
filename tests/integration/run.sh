#!/usr/bin/env bash
set -euo pipefail
WP_VERSION="${WP_VERSION:-6.8.3}"
PHP_BIN="${PHP_BIN:-php}"
DB_ENGINE="${DB_ENGINE:-sqlite}"
case "$DB_ENGINE" in
  sqlite) SUFFIX='' ;;
  mysql)
    SUFFIX='-mysql'
    [[ "${ALIFY_ALLOW_MYSQL_TESTS:-}" == 1 ]] || { echo 'MySQL requires ALIFY_ALLOW_MYSQL_TESTS=1 (disposable database only)'; exit 1; }
    [[ "${ALIFY_MYSQL_DATABASE:-}" =~ ^alify_test[a-zA-Z0-9_]*$ ]] || { echo 'Use an alify_test-prefixed disposable database'; exit 1; }
    [[ "${ALIFY_MYSQL_HOST:-}" =~ ^(127\.0\.0\.1|localhost)(:[0-9]+)?$ ]] || { echo 'MySQL tests require an explicit loopback host'; exit 1; }
    [[ -n "${ALIFY_MYSQL_USER:-}" && "${ALIFY_MYSQL_PASSWORD+x}" == x ]] || { echo 'Set ALIFY_MYSQL_USER and ALIFY_MYSQL_PASSWORD'; exit 1; }
    ;;
  *) echo 'DB_ENGINE must be sqlite or mysql'; exit 1 ;;
esac
ROOT="${ALIFY_TEST_DIR:-${HOME}/.cache/alify-cf-integration-${WP_VERSION}${SUFFIX}}"
REPO="$(cd "$(dirname "$0")/../.." && pwd)"
mkdir -p "$ROOT"
if [[ ! -d "$ROOT/wordpress/.git" ]]; then
  git clone --depth 1 --branch "$WP_VERSION" https://github.com/WordPress/WordPress.git "$ROOT/wordpress"
fi
if [[ "$DB_ENGINE" == sqlite && ! -d "$ROOT/sqlite/.git" ]]; then
  git clone --depth 1 --branch v2.2.23 https://github.com/WordPress/sqlite-database-integration.git "$ROOT/sqlite"
fi
[[ "$(git -C "$ROOT/wordpress" describe --tags --exact-match)" == "$WP_VERSION" ]] || { echo 'WordPress version mismatch'; exit 1; }
if [[ "$DB_ENGINE" == sqlite ]]; then
  [[ "$(git -C "$ROOT/sqlite" rev-parse HEAD)" == f3ea1a43ba525be382c7a9c17735b6b4d4b11d49 ]] || { echo 'SQLite fixture revision mismatch'; exit 1; }
elif [[ -e "$ROOT/wordpress/wp-content/db.php" ]]; then
  echo 'MySQL requires a separate fixture directory without a database drop-in'; exit 1
fi
if [[ -f "$ROOT/wordpress/wp-config.php" ]] && ! grep -q "define('ALIFY_CF_TEST_SITE', true)" "$ROOT/wordpress/wp-config.php"; then
  echo 'Refusing to overwrite an unmarked wp-config.php'; exit 1
fi
# Links point only into this disposable installation; plugin source remains in the checkout.
ln -sfn "$REPO" "$ROOT/wordpress/wp-content/plugins/alify-custom-field"
if [[ "$DB_ENGINE" == sqlite ]]; then
ln -sfn "$ROOT/sqlite/packages/plugin-sqlite-database-integration" "$ROOT/wordpress/wp-content/plugins/sqlite-database-integration"
cp "$ROOT/sqlite/packages/plugin-sqlite-database-integration/db.copy" "$ROOT/wordpress/wp-content/db.php"
fi
export DB_ENGINE
cat > "$ROOT/wordpress/wp-config.php" <<'PHP'
<?php
define('ALIFY_CF_TEST_SITE', true);
if (getenv('DB_ENGINE') === 'mysql') {
    if (getenv('ALIFY_ALLOW_MYSQL_TESTS') !== '1' || !preg_match('/^alify_test[a-zA-Z0-9_]*$/D', getenv('ALIFY_MYSQL_DATABASE') ?: '') || !preg_match('/^(127\.0\.0\.1|localhost)(:[0-9]+)?$/D', getenv('ALIFY_MYSQL_HOST') ?: '')) {
        throw new RuntimeException('Disposable loopback MySQL test configuration required.');
    }
    define('DB_NAME', getenv('ALIFY_MYSQL_DATABASE'));
    define('DB_USER', getenv('ALIFY_MYSQL_USER'));
    define('DB_PASSWORD', getenv('ALIFY_MYSQL_PASSWORD'));
    define('DB_HOST', getenv('ALIFY_MYSQL_HOST'));
} else {
    define('DB_NAME', 'alify_test'); define('DB_USER', 'unused');
    define('DB_PASSWORD', 'unused'); define('DB_HOST', 'localhost');
}
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
define('DB_ENGINE', getenv('DB_ENGINE') === 'mysql' ? 'mysql' : 'sqlite');
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
status=0
"$PHP_BIN" "$REPO/tests/integration/install.php" || status=$?
if [[ "$status" == 0 ]]; then
  "$PHP_BIN" "$REPO/tests/integration/wordpress.php" || status=$?
fi
if [[ -s "$ROOT/debug.log" ]]; then
  echo 'Unexpected WordPress debug output:'; cat "$ROOT/debug.log"
  if [[ "${GITHUB_ACTIONS:-}" == true ]]; then
    while IFS= read -r line; do
      line="${line//%/%25}"
      printf '::error title=WordPress integration debug::%s\n' "$line"
    done < "$ROOT/debug.log"
  fi
  exit 1
fi
exit "$status"
