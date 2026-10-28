# Real WordPress integration suite

This suite boots **real WordPress**, activates the plugin across separate PHP processes, and exercises its hooks with actual users, nonces, `wp_posts`, `wp_postmeta`, `wp_options`, attachments and uploaded fixture files. It is not the stub-based `tests/php-unit.php` suite.

## Run

Requirements: Git, Bash, and PHP with PDO SQLite, SQLite3, mysqli and fileinfo. mbstring/GD are recommended for WordPress. The SQLite driver requires a sufficiently recent SQLite build (3.37+ is recommended).

```sh
bash tests/integration/run.sh
WP_VERSION=6.2 bash tests/integration/run.sh
```

- `PHP_BIN`: alternative PHP executable, default `php`.
- `ALIFY_TEST_DIR`: disposable fixture directory, default `$HOME/.cache/alify-cf-integration-{WP_VERSION}`.
- `WP_VERSION`: WordPress tag, default `6.8.3`.

The runner clones WordPress and SQLite Database Integration **v2.2.23**, links this checkout as a plugin, creates a local SQLite test database, activates the plugin, runs assertions, and fails on unexpected WordPress debug-log output. It does not start a web server or expose test credentials. Its test admin has a randomly generated password that is not printed.

**Never run these scripts against a production site.** Direct execution requires `ALIFY_WP_ROOT` and `define('ALIFY_CF_TEST_SITE', true)` in the disposable site's config. The suite deletes its fixture posts/users/uploads and its test Site Settings option. An unmarked existing `wp-config.php` is rejected by the runner. Fixture directories stay outside Git and can be reused for later runs.

The GitHub Actions workflow runs this suite using native PHP. In the Arena sandbox, the commands were run using a PHP WebAssembly CLI executable via `PHP_BIN`, with `PHP=7.4` or `PHP=8.3` selecting that runtime.

## Verified locally — 2026-10-28

| WordPress | PHP runtime | Database | Result |
|---|---|---|---|
| 6.2 | PHP.wasm 7.4.33 | SQLite / WP_SQLite_DB | 57 assertions passed, clean debug log |
| 6.8.3 | PHP.wasm 8.3.33 | SQLite / WP_SQLite_DB | 57 assertions passed, clean debug log |

These are explicit test targets, **not a claim that 6.8.3 is the latest WordPress release**, or a recommendation to deploy an old version. Keep production WordPress/PHP patched.

Coverage includes activation persistence, init registration, all 13 field schemas, Unicode/quotes/backslashes, scalar and serialized metadata types, galleries/repeaters, cleared toggles/checkboxes, real nonce rejection, revision-save guards, editor/anonymous access checks, hidden-field preservation, options isolation/atomicity/autoload, real options form rendering, inline validation, rejected-input recovery/clearing, user isolation and stale-schema recovery rejection.

## Still pending

- Native MySQL/MariaDB integration runs (SQLite is a translation layer).
- Browser-driven classic and Gutenberg end-to-end flows, actual media-modal interactions, drag sorting, responsive/accessibility checks and real HTTP admin-post redirects.
- A server-validated publication gate covering classic and Gutenberg saves. Current field validation preserves rejected field values but does **not** prevent the WordPress post from publishing.
- Wider compatibility matrix, multisite and third-party plugin/theme interactions.

A passing backend suite does not replace these remaining release checks. GitHub workflow execution is separate from the locally observed results above.
