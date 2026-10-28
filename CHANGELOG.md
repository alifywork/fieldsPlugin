# Changelog

## 0.7.0

- Add an admin-only JSON Import / Export page, with JSON downloads, file upload and pasted-JSON import.
- Introduce a bounded, versioned, single-group configuration format; import always creates a new draft, never overwrites or auto-publishes existing groups.
- Reuse the editor schema validator for supported fields, repeater/condition rules, registered locations and name conflicts.
- Preserve field keys and portable defaults while resetting site-local image/file defaults; never transfer content values, media files, source IDs or arbitrary metadata.
- Require capability and nonce checks, reject malformed/oversized/unsupported documents before insertion, and remove newly created drafts if metadata verification fails.
- Verify 119 backend and 23 real-browser assertions per target across the four native-PHP SQLite/MySQL jobs, including actual download, upload, pasted import, conflicts, malformed files and nonce rejection.

## 0.6.3

- Add native MySQL 8.0 CI targets for both PHP/WordPress pairs, including backend assertions and real Chromium Classic/Gutenberg/media flows alongside SQLite.
- Add an explicit MySQL runner opt-in, loopback/test-database-name guards, separate fixture directories and rejection of database drop-ins.
- Assert the actual database driver during installation and print the database server version, preventing accidental SQLite substitution.
- Verify all four native-PHP CI targets: 80 backend and 14 browser assertions per target, with clean debug-log checks.
- Add eight database-runner refusal tests; database credentials are supplied through the environment rather than committed configuration.
- MariaDB and wider database versions remain unverified; this increment does not add JSON import/export yet.

## 0.6.2

- Validate Classic publication/scheduling/private transitions against active field groups; preserve existing public status on invalid edits rather than silently unpublishing content.
- Bridge Gutenberg metabox values into the same core REST request and reject invalid saves before writing the post; validate saved/default values when a publication request omits fields.
- Return structured REST errors and show inline editor feedback, retaining core authentication/capability checks and normal autosave availability.
- Require complete metabox-group markers before saving values; preserve scalar values on incomplete submissions.
- Add guarded disposable browser fixtures and 14 real-Chromium assertions covering Classic/Gutenberg publication, the native media modal and persisted gallery/repeater order.
- Add Chromium flows to the native-PHP CI matrix and expand backend WordPress coverage to 80 assertions per target, isolated PHP coverage to 160, and jsdom coverage to 23 tests.
- MySQL/MariaDB, wider compatibility and options-page browser flows remain pending. This is not a universal gate for direct SQL/cron/custom publishing pipelines.

## 0.6.1

- Add real WordPress integration tests with disposable SQLite-backed installations and native-PHP GitHub Actions matrix configuration.
- Verify 57 backend assertions each on WordPress 6.2 / PHP.wasm 7.4.33 and WordPress 6.8.3 / PHP.wasm 8.3.33, with clean debug logs.
- Add field-level validation messages and accessible scalar error attributes.
- Recover bounded, sanitized unsaved input using 10-minute user/context-scoped transients; never expose rejected values through frontend helpers.
- Preserve hidden values and restricted existing media; reject stale-schema recovery and clear feedback after correction.
- Clear stale inline highlighting on input/media changes.
- Correct the documented scalar Image/File post-meta return type to numeric strings, while options and gallery arrays retain integers.
- Expand isolated coverage to 159 PHP assertions and 20 admin interaction tests.
- Browser E2E, MySQL/MariaDB and a publication gate remain pending; this is a stabilization increment, not a full compatibility certification.

## 0.6.0

- Add Gallery fields to posts, options pages and single-level repeater subfields.
- Add native multi-image selection, thumbnails, deduplication, earlier/later moves and single/all removal controls.
- Store ordered integer attachment IDs, bounded to 50 images per gallery.
- Validate image types, existence, trash state and new-selection permissions; retain previous values for invalid/incomplete submissions.
- Extend conditional logic to gallery membership and empty/nonempty checks, preserving hidden selections.
- Preserve control limits after conditional refresh and support galleries in dynamically added repeater rows.
- Expand verification to 19 admin interaction tests and 145 PHP contract assertions.

## 0.5.0

- Add built-in Site Settings and `alify_add_options_page()` for additional global settings pages.
- Extend group location choices and listing labels to registered options pages.
- Add separate per-page site-option storage (autoload disabled), plus `alify_get_option()` and escaped `alify_the_option()` helpers.
- Reuse field rendering and validation for options, including media, conditional logic and repeaters.
- Add capability/nonce-protected admin-post saves, redirect notices and whole-page atomic validation with completion guards.
- Preserve stale/hidden option values and isolate data from posts and other pages.
- Expand isolated PHP coverage to 113 assertions, including actual options form rendering.

## 0.4.0

- Add single-level Repeater fields with 1–20 local subfields and up to 50 content rows.
- Add subfield schema controls, row add/remove and accessible move-up/down controls.
- Reuse all eleven basic field renderers, sanitizers and media picker inside rows.
- Validate repeaters atomically; retain prior rows on invalid, incomplete or truncated submissions.
- Support whole-repeater conditional visibility; reject repeaters as condition sources.
- Add `alify_get_rows()` for ordered frontend access.
- Expand tests to 13 admin interaction cases and 76 PHP contract assertions, including real row-template rendering.

## 0.3.0

- Add one-rule-per-field conditional logic with equal, not-equal, empty and not-empty comparisons.
- Reference unconditional siblings by stable field key; reject missing references, self-references and chains/cycles atomically.
- Apply initial server-rendered visibility and live group-scoped visibility updates in the post editor.
- Disable hidden controls to avoid browser required validation and skip their values during server saves, preserving stored data even for forged submissions.
- Support checkbox membership, unchecked-toggle emptiness and media-ID comparisons.
- Expand coverage to 10 admin interaction tests and 45 PHP contract assertions.

## 0.2.0

- Add Image and File field types using the native WordPress media frame.
- Add attachment previews, safe filename display and remove controls; return attachment IDs to templates.
- Validate attachment existence, media type and permissions when selecting new attachments.
- Reject invalid group schemas atomically instead of silently dropping invalid fields.
- Protect field names across non-trashed saved groups on the same post type.
- Show scoped save-error notices while retaining previous invalid/required values.
- Fix backslash loss when saving values and schema strings through WordPress metadata APIs.
- Guard malformed schema settings and null-byte date input.
- Add jsdom interaction tests and isolated PHP contract tests.

## 0.1.0

- Introduce field groups, nine basic field types, post-type locations, sortable builder and template helpers.
