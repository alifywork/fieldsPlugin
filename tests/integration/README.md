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
| 6.2 | PHP.wasm 7.4.33 | SQLite / WP_SQLite_DB | 285 assertions passed, clean debug log |
| 6.8.3 | PHP.wasm 8.3.33 | SQLite / WP_SQLite_DB | 285 assertions passed, clean debug log |

These are explicit test targets, **not a claim that 6.8.3 is the latest WordPress release**, or a recommendation to deploy an old version. Keep production WordPress/PHP patched.

Coverage includes activation persistence, init registration, all 20 field schemas, Unicode/quotes/backslashes, scalar and serialized metadata types, galleries/repeaters, cleared toggles/checkboxes, real nonce rejection, revision-save guards, editor/anonymous access checks, hidden-field preservation, options isolation/atomicity/autoload, real options form rendering, inline validation, rejected-input recovery/clearing, user isolation and stale-schema recovery rejection. Publication regressions cover Classic invalid/valid publish and schedule transitions, existing-public status preservation, REST pre-write rejection, same-request values, omitted payloads, new-post creation, authentication, write-only responses and autosaves.

## Native MySQL 8 CI target

The CI matrix also runs the same backend and Chromium suites against a disposable **MySQL 8.0** service, on both PHP/WordPress pairs. This is separate from the locally observed SQLite results above. No MySQL server/native PHP is installed in the Arena sandbox; MySQL results come from the actual CI jobs.

**Verified in native-PHP CI, 2026-10-28:** [run 40690252968](https://github.com/alifywork/fieldsPlugin/actions/runs/40690252968) passed all four targets:

| WordPress | Native PHP | Database | Backend | Real Chromium |
|---|---|---|---|---|
| 6.2 | 7.4 | MySQL 8.0 | 285 assertions passed | 91 browser + 24 HTTP persistence checks passed |
| 6.8.3 | 8.3 | MySQL 8.0 | 285 assertions passed | 91 browser + 24 HTTP persistence checks passed |
| 6.2 | 7.4 | SQLite | 285 assertions passed | 91 browser + 24 HTTP persistence checks passed |
| 6.8.3 | 8.3 | SQLite | 285 assertions passed | 91 browser + 24 HTTP persistence checks passed |

Browser coverage now includes actual JSON download and schema inspection, uploaded and pasted imports with redirect to a draft, Unicode/quote/backslash preservation, conflict rejection, invalid nonce and malformed upload. Backend transfer checks also cover capabilities, format/version/size, unavailable locations, nested repeaters, duplicate names/keys, site-local media-default resets and no partial groups after rejection. Group regressions cover structured metadata, nested gallery/media types, whole-object retention, Classic publication, REST payload/omitted-payload saves, options atomicity, JSON round trips and rejection of nested containers. Flexible regressions also cover mixed layouts, whole-list retention, Classic/REST publication, omitted REST payloads, options atomicity and schema transfer. Compound regressions cover legacy compatibility, ALL/ANY truth cases, malformed/nested/oversized sets, missing/self/conditional sources, hidden forged-value preservation, visible-required REST rejection, options atomicity and JSON round trips. Native browser checks include Group/Flexible persistence, live ALL/ANY toggles, hidden-input disabling, hidden-value retention and compound target values after Classic/Gutenberg save/reload.

Repeater-bound regressions cover strict schema parsing, legacy defaults, min/max Classic/REST rejection, options atomicity and version-2 transfer. Browser checks verify disabled Add at maximum, disabled Remove at minimum, persistence after reload, and actual v2 downloads/imports.

Container diagnostics are verified across Classic, core REST and options feedback: first failing visual row/layout/subfield, private-value omission, atomic retention and correction clearing. Real Chromium verifies Group/Repeater/Flexible message paths and REST error description links clearing on edit. The first failing subfield now has precise inline placement, focus/jump navigation and edit clearing; structured REST metadata preserves existing error strings. Recovery fallback never marks unrelated saved rows. This does not claim all-error enumeration or a full accessibility audit.

Post Object development checks cover canonical IDs, root/basic container persistence, read/password permissions, redacted unavailable titles, existing-reference retention, search nonce/type/page bounds, anonymous public resolution, REST/Options validation and schema-only JSON. Chromium uses the actual AJAX selector and verifies Classic/Gutenberg/Options save/reload, clearing and invalid-nonce denial; isolated JS covers stale responses and safe text rendering.

WYSIWYG development checks cover actual native Visual/Text editor content in Classic, Gutenberg and Options saves; active Text-tab edits; explicit paragraph preservation; KSES filtering (including legacy render input); blank-format validation; sanitized recovery and JSON defaults. Metabox editor teardown/reinitialization is exercised by an isolated lifecycle test, not a claimed physical drag-browser test.

Link development checks verify sanitized records, unsafe URL/target rejection, root and all basic container locations, saved-value retention, typed getters, Options atomicity and JSON schemas. Chromium verifies URL/title/target persistence, explicit clearing and same-request Gutenberg saves.

Color/Time development checks cover strict formats/defaults, Classic and REST persistence/rejection, all three container kinds, Options atomicity and JSON schema round trips. Chromium additionally exercises empty color versus explicit black, swatch synchronization, minute-format persistence and clearing across real save/reload.

Options HTTP coverage includes valid/failing redirects, attempted-input recovery, detailed row errors, correction clearing, invalid nonces, incomplete groups and denial of editor page/save access. The 24 persistence checks are six CLI inspections of the actual database (four assertions each), **not extra UI assertions**: stored values remain atomic after failure, post metadata stays separate, rich HTML remains synchronized/retained, and options do not autoload. The dedicated `browser-http` option/group and editor are removed on cleanup; Site Settings is not touched.

All jobs also passed PHP syntax, 348 isolated PHP assertions, 44 jsdom tests and eight runner safety guards; backend and browser debug-log checks were clean. This verifies the named targets, not every MySQL/MariaDB release or plugin/theme combination.

For a local native-PHP run, first create a dedicated empty database and least-privileged database user on a local MySQL instance, then export:

```sh
export DB_ENGINE=mysql ALIFY_ALLOW_MYSQL_TESTS=1
export ALIFY_MYSQL_HOST=127.0.0.1:3306
export ALIFY_MYSQL_DATABASE=alify_test
export ALIFY_MYSQL_USER=alify_test
# Set ALIFY_MYSQL_PASSWORD in your shell without committing it or sharing it.
bash tests/integration/run.sh
```

The runner never creates/drops a MySQL database. It installs WordPress tables and mutates fixtures inside the supplied database. Opt-in, an `alify_test`-prefixed database name, explicit loopback host and supplied credentials are mandatory. These guards reduce mistakes; they cannot prove a database is disposable. **Never point this at valuable data or a production tunnel.** `bash tests/integration/guards.sh` checks eight refusal paths without connecting or cloning.

MySQL defaults to a separate `$HOME/.cache/alify-cf-integration-{WP_VERSION}-mysql` directory and refuses an existing database drop-in, preventing SQLite from silently standing in for MySQL. Installation verifies the expected database driver and prints the actual server version. Keep the exported environment available to subsequent PHP seed/server/cleanup processes; the generated disposable config reads it at runtime. For browser testing, set `ALIFY_WP_ROOT`/`ALIFY_BROWSER_FIXTURE` to this MySQL directory instead of the SQLite directory.

## Real Chromium browser suite

After running the integration installer, install browser dependencies and seed the **marked disposable** site:

```sh
npm ci
npx playwright-core install --with-deps chromium
export ALIFY_WP_ROOT="$HOME/.cache/alify-cf-integration-6.8.3/wordpress"
export ALIFY_BROWSER_FIXTURE="$HOME/.cache/alify-cf-integration-6.8.3/browser.json"
php tests/browser/seed.php
# In a separate terminal (native PHP):
php -S 0.0.0.0:8080 -t "$ALIFY_WP_ROOT" tests/browser/router.php
# In the original terminal:
npm run test:browser # also invokes native php for persisted options checks
php tests/browser/cleanup.php
```

Use only a disposable test server; never expose this setup as a production service. Seed creates an administrator and editor with separate random passwords in a chmod-0600 JSON fixture **outside the document root**, test groups/posts, real PNG uploads and a test-only MU plugin forcing the seeded Classic post into that editor. Cleanup removes them. Do not commit/upload the fixture JSON or server logs. The CI workflow cleans up even on browser failure.

`ALIFY_BROWSER_URL` defaults to `http://127.0.0.1:8080`; `CHROMIUM_EXECUTABLE` optionally selects an installed Chromium executable. The test blocks external browser requests. Its options-flow persistence verifier invokes `PHP_BIN` (default `php`) against `ALIFY_WP_ROOT`; keep both variables available when using an alternative PHP runtime. The native PHP router is for this disposable server only. Local PHP.wasm's CLI server was unstable under browser concurrency, so local browser verification used a scratch Node PHPRequestHandler server instead; the native-PHP server is exercised in CI.

**Earlier local browser baseline (0.6.2):** 14 Chromium assertions passed on WordPress 6.8.3 / PHP.wasm 8.3.33: actual Classic rejection/redirect/recovery/correction; native gallery media modal; ordered gallery/repeater persistence after save/reload; actual Gutenberg rejection/inline feedback/correction; raw REST omitted-field rejection; no uncaught page JavaScript errors. These are not jsdom/media mocks. The current native-PHP CI results above supersede this baseline with 91 browser assertions plus 24 independent HTTP-persistence assertions per target; its remote results are separate from this local observation.

## Still pending

- MariaDB and database versions beyond the explicit MySQL 8.0 CI target.
- Repeater image-subfield media selection, drag sorting, responsive/accessibility audits.
- Direct SQL/`wp_publish_post()`/cron/custom publishing-pipeline policy enforcement: the current gate covers Classic insertion/update hooks and core post REST saves, not every possible status mutation.
- Wider compatibility matrix, multisite and third-party plugin/theme interactions.

Passing backend/browser suites do not replace these remaining release checks.
