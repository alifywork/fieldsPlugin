# Changelog

## 0.15.0

- Feature checks pass on all four existing native-PHP targets: 262 backend, 85 browser and 24 HTTP persistence assertions; 325 isolated PHP assertions and 41 JS tests also pass.

- Add top-level WYSIWYG fields on posts/Options Pages with escaped textarea fallback and on-demand native WordPress Visual/Text editor.
- Synchronize Visual content before form/REST serialization, preserve Text-tab edits, and save/remove/reinitialize native editor instances around Classic metabox sorting.
- Apply WordPress post-HTML KSES filtering regardless of administrator unfiltered-HTML capability. Normalize empty formatting/invisible spacing; required fields need textual content.
- Support HTML defaults, conditional targets, bounded sanitized recovery and JSON schemas. Rich subfields, condition sources, upload buttons and toolbar configuration remain excluded.

## 0.14.0

- Feature checks pass on all four existing native-PHP targets: 245 backend, 81 browser and 18 additional HTTP persistence assertions; 314 isolated PHP assertions and 37 JS tests also pass.

- Add atomic Link records (HTTP(S) URL, optional sanitized title and same/new-tab target) to the builder, post/Options controls and basic container subfields.
- Add explicit Clear link, required URL validation, complete-payload checks and `alify_get_link()`; preserve saved records/containers on rejection and recover escaped attempts separately.
- Reject executable/relative/credential-bearing URLs, malformed structures and unsupported targets. No URL fetching or automatic HTML rendering.
- Carry Link values through Classic and core REST saves, Options page atomicity and schema-only JSON transfer. Configurable composite defaults and Link-as-condition-source are not included.

## 0.13.0

- Verify feature development on all four existing native-PHP targets: 230 backend, 76 browser and 18 HTTP persistence assertions; 287 isolated PHP assertions and 35 JS tests also pass.

- Add Color Picker and Time to the shared field registry, builder and all basic subfield builders.
- Color uses a native nameless swatch, submitted editable hex text, explicit Apply swatch (including unchanged black) and Clear actions; empty is distinct from black. Store only lowercase six-digit hex, rejecting unsupported color syntaxes.
- Time uses a native minute-resolution input with strict `HH:MM` storage, without seconds or timezone conversion.
- Support shared defaults/required rules/conditions, Classic/core REST and Options validation, atomic container retention and JSON schema transfer.
- Add focused development checks for formats, clearing, nested basic values, post/Options/REST saves, JSON round trips and actual browser save/reload behavior.

## 0.12.0

- Feature checks pass on all four existing native-PHP targets: 215 backend, 70 browser and 18 HTTP persistence assertions, plus 264 isolated PHP assertions and 33 JS tests.

- Place the first failing Group/Repeater/Flexible subfield diagnostic beside its exact input; preserve the parent summary and atomic saved-container behavior.
- Carry structured schema names and zero-based visual row ordinals through Classic/Options recovery and additive REST `alify_error_locations`, preserving existing string errors. Never parse labels or expose submitted row tokens.
- Focus the first invalid control, open closed metabox/details ancestors, and add a jump action. Existing row bodies remain expanded.
- Mark only the target subfield invalid, preserve help references, and clear inline/jump feedback on edit. Ignore stale REST field errors after the submitted field has changed.
- Keep structural/count errors at the parent boundary and avoid mislabeling saved rows when attempted input cannot be recovered. All-error enumeration remains out of scope.

## 0.11.2

- Add dedicated disposable Options Page/group and editor-role browser fixtures without touching Site Settings.
- Exercise actual admin-post success/failure redirects, detailed errors, attempted-input recovery, correction clearing, invalid nonces, incomplete forms and editor permission denial.
- Verify persisted database values separately from recovery-rendered inputs after each save/rejection, including post/options isolation and autoload-disabled storage.
- Clean up the dedicated options data, group and editor user; keep credentials in the existing private fixture file.
- Verify all four native-PHP SQLite/MySQL targets: 211 backend assertions, 64 browser assertions and 18 additional HTTP persistence assertions per target; existing 258 isolated PHP and 31 JS checks also pass.
- This is a verification increment, not a full accessibility or compatibility certification.

## 0.11.1

- Report the first failing Group subfield, Repeater row/subfield and Flexible row/layout/subfield through the existing field-level feedback contract.
- Add actionable row-count/incomplete-structure messages while preserving atomic values and publication checks.
- Keep rejected values, submitted row tokens, unknown layout names and attachment identities out of diagnostic messages.
- Link container alerts and REST errors accessibly; clear stale error description references on edit while retaining help references.
- Verify four native-PHP SQLite/MySQL targets: 211 backend and 52 real-browser assertions per target, plus 258 isolated PHP assertions and 31 JS tests. Per-subfield inline placement, focus management and full accessibility auditing remain pending.

## 0.11.0

- Add configurable Repeater minimum/maximum row counts, with strict 0–50 / 1–50 schema validation and minimum ≤ maximum.
- Preserve legacy 0–50 defaults and enforce an effective minimum of one for required repeaters.
- Disable add/remove controls at configured bounds, including after visibility refresh, without automatically inserting or removing content.
- Enforce bounds atomically across Classic/core REST publication, options and JSON schema transfer; retain complete previous row lists on invalid submissions.
- Export configured row bounds using transfer format v2 to prevent older importers silently discarding limits; continue accepting legacy v1 documents.
- Render existing out-of-bounds rows for explicit correction instead of silently truncating them. Hidden repeaters retain data without enforcing inactive bounds.
- Verify four native-PHP SQLite/MySQL targets: 197 backend and 45 real-browser assertions per target, plus 244 isolated PHP assertions and 30 JS tests. Nested repeaters and data migration remain out of scope.

## 0.10.0

- Add flat compound conditional logic with ALL/ANY matching and 1–10 rules per top-level field.
- Add rule add/remove and relation controls; preserve legacy single-rule schema shape and independently clone compound rule sets when duplicating fields.
- Share bounded PHP condition parsing/evaluation across Classic, REST publication, options and rejected-input recovery; retain hidden values even for forged payloads.
- Validate every source, rejecting self-references, missing fields, conditional chains, container sources and nested/oversized rule sets.
- Transfer compound conditions through the existing versioned JSON format; destination sites need compound support.
- Verify four native-PHP SQLite/MySQL CI targets: 178 backend and 40 real-browser assertions per target, plus 222 isolated PHP assertions and 28 jsdom tests. Subfield/layout conditions and nested rule groups remain out of scope.

## 0.9.0

- Add bounded Flexible Content with 1–10 named layouts, 1–20 basic subfields per layout and up to 50 mixed ordered rows.
- Add layout/subfield builder controls and row add/remove/move controls, with stable layout names and keys.
- Validate whole values atomically across posts, options and core REST saves; reject unknown layouts, incomplete rows and unauthorized new media while retaining previous values.
- Support JSON schema transfer, bounded recovery, whole-field conditions, publication validation and `alify_get_layouts()`.
- Warn on removed saved layouts instead of silently dropping them. Nested containers, per-layout conditions and migrations remain unsupported.
- Verify all four native-PHP SQLite/MySQL targets with 160 backend and 30 real-browser assertions per target, plus 201 isolated PHP assertions and 26 jsdom tests.

## 0.8.0

- Add single-level Group fields with 1–20 basic subfields and one associative object per field, on posts and options pages.
- Reuse bounded subfield/media/gallery validation; retain the complete previous Group on invalid or incomplete submission, including permission-aware media preservation.
- Support Group rendering, builder subfield controls, user-scoped recovery, whole-Group conditions, Classic/core REST publication checks and versioned JSON configuration transfer.
- Add `alify_get_group()` and preserve the existing frontend helpers and options APIs.
- Reject nested Group/Repeater containers and Group condition sources. Required Groups need a nonempty subfield; subfield requirements remain enforced.
- Verify all four native-PHP SQLite/MySQL targets: 141 real WordPress assertions and 27 Chromium assertions per target, plus 179 isolated PHP assertions and 24 jsdom tests. Flexible Content, nesting and per-subfield error UI remain outside this increment.

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
