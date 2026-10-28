# Alify Custom Field

An independent WordPress custom-fields plugin with a modern, WordPress-native field-group editor. **Version 0.33.0 — row-scoped conditions and opt-in interactive maps**, not an ACF drop-in replacement.

## Requirements and installation

- WordPress 6.2+; PHP 7.4+ (a currently supported PHP version is recommended).
- Copy this repository folder to `wp-content/plugins/alify-custom-field/`, or ZIP its contents inside an `alify-custom-field` folder and upload through **Plugins → Add New → Upload Plugin**.
- Activate **Alify Custom Field**.
- Open **Alify Custom Field → Add Field Group**, enter a title, add fields, choose a post type, and **Publish**.
- Edit a post of that type, fill in its fields, and save the post.

## Included

- Administrator-only field-group management using WordPress's native publish/draft/trash workflow.
- oEmbed, manual Google Map coordinates, Icon Picker, Tab, Accordion, Message, Taxonomy, User, Page Link, Text, Password, Text Area, WYSIWYG Editor, Number, Range, Email, URL, Link, Post Object, Relationship, Select, Radio Button, Button Group, Checkbox, True / False, Date, Date Time, Color Picker, Time, Image, File, Repeater, Gallery, Group and Flexible Content.
- Single-level repeaters with a subfield builder, row add/remove/move controls and atomic value validation.
- Native WordPress media picker, image thumbnails, file labels and remove controls.
- Multi-image galleries with ordered, deduplicated attachment IDs and move/remove controls.
- Attachment IDs validated by type and attachment edit permissions on selection.
- Atomic schema validation and duplicate-name protection across active/draft groups on the same post type.
- Per-user save-error notices, inline field errors and short-lived unsaved-input recovery, separate from saved field values.
- Labels, stable field keys, field names, instructions, defaults, and required settings.
- Conditional show/hide rules with live editor updates and server-side enforcement.
- Expandable cards, drag sorting, keyboard-accessible move buttons, duplication and deletion confirmation.
- One base location per group (post/CPT, Options or supported native object/widget context), narrowed by optional OR/AND predicates.
- Built-in Site Settings and developer-registered additional options pages, with separate site-scoped storage.
- Custom metaboxes in classic and block post editors (not native block-editor sidebar controls).
- Nonces, edit permissions, autosave/revision guards, allowlisted types, value sanitization and escaped admin output.
- Values stored with a private `_alify_cf_` meta prefix, separate from group schemas.

## Development queue

See [DEVELOPMENT-ROADMAP.md](DEVELOPMENT-ROADMAP.md) for the missing/partial-feature backlog. Work proceeds in large related-feature batches without per-item approval pauses. Version 0.32.0 adds reusable schema snapshots and a separate explicit ACF content migration planner; 0.31.0 added guarded post-field migrations and a declared-subset ACF schema importer; 0.30.0 added conditional dependencies and a scoped native dynamic-block API; 0.29.0 added native contexts/deployment/editing. See the roadmap for exact completion status and unresolved work; this is not an ACF compatibility promise.

## Scoped conditions and interactive maps (0.33.0)

### Independent Group / Repeater / Flexible-layout conditions

Basic children now support `condition` using the same eligible source types, operators, nested ALL/ANY limits, cycle checks and 16-link maximum as root fields. A source must be a **sibling in the same Group or the same individual row/layout**. No ancestor/root, other-row, cross-layout or cross-group lookup. Hidden sources never match any operator; required children validate only when visible. Root container required/cardinality rules still apply. Nested containers remain unsupported.

The builder exposes **Scoped sibling conditions (advanced JSON)** beside each subfield, with its internal key shown. Example: `{"field":"field_local_toggle","operator":"equals","value":"1"}`. Use local keys, not labels/names. Reusable definitions remap these keys when materialized; JSON/PHP configurations retain the conditions. **Portable schema v11 / plugin 0.33.0+** is required, recursively including Flexible children. Earlier-version declarations reject before import/runtime Local JSON loading; versions 1–10 remain supported for their existing features.

Editor submissions ignore hidden child inputs and preserve their old values. Each rendered conditional row carries a **server-generated origin MAC** for its original canonical content, not its visual position. Sorting/duplication retains the intended hidden values; stale/forged origins fail the whole container rather than guessing by row index. New rows carry an empty origin and use schema defaults for hidden children. Origin MACs are identity aids, not authorization: ordinary object capabilities/nonces still apply.

PHP/REST explicit writes reject attempts to change hidden children atomically. Unchanged hidden values in a complete canonical payload are accepted; for omitted hidden values in reordered/edited rows, include `__row_origin` from `Alify_CF_Scoped_Conditions::origin($original_row)` on a Repeater row, or inside a Flexible row's `fields`. Generate it from the **original** canonical row, before edits. Group has one unambiguous previous object. A hidden value without identity or an explicitly unchanged canonical representation refuses to guess. Use `Alify_CF_Publication::stored_payload()` to reconstruct structured child markers; origin metadata is never stored in canonical values or exported as content. These rules also protect Options and shared object editors using the same cleaners. Recovery/revision restoration still validates current visibility/schema and is not an unconditional historical overwrite.

### Explicit-consent Google Maps / Places picker

Manual coordinate/address/zoom inputs remain the default. To enable the optional picker, define trusted site configuration (never put production keys in Git):

```php
define('ALIFY_CF_GOOGLE_MAPS_KEY', 'your-browser-restricted-key');
define('ALIFY_CF_GOOGLE_MAPS_PICKER', true);
```

Restrict the browser key to the intended domains/APIs and configure Google Maps JavaScript / Places access and billing. This is a **public browser key**, not secret server storage. The existing explicit Maps Embed output can use its own appropriate key restrictions.

- The picker starts no Google script/request until an editor clicks **Enable interactive map/search**. Consent is per rendered picker/page; saved coordinates do not automatically enable Google on reload. If another plugin already loaded a compatible Google SDK, that SDK can be reused.
- Use modern Places autocomplete, click the map or drag the blue point. Selected coordinates/address/zoom populate the normal local inputs; the normal form save, permissions and strict canonical validation persist them. A map click/drag clears an old address rather than mislabeling the new point. No device geolocation, automatic background geocoding or arbitrary provider-host requests.
- The provider receives enabled map coordinates and typed searches. Google is a third party and subject to its own terms/billing/privacy policies. The SDK is shared per page; manual inputs remain available if loading/configuration fails. Deferred stale place results cannot replace intervening manual edits or removed/hidden controls. Removed dynamic rows dispose adapter listeners/points.
- Saving/rendering a manual field does not require Google. The new picker changes no stored schema/value shape (Map fields still have the existing v9 minimum); existing Embed rendering remains separate. Provider checks use controlled SDK/Places fixtures, **not live Google service/billing certification**.

## Reusable definitions and separate ACF content migration (0.32.0)

### Reusable schema snapshots / Clone composition

The native field-group builder now has **Reusable field definitions** controls. Save the current validated fields under a new key/title, choose a definition and a name prefix such as `home_`, then **Insert reusable fields**. Review and save the field group normally. Insertion validates the combined schema—including other applicable groups—before changing the editor. Failed/stale asynchronous responses retain current edits. Saving a reusable definition itself does not save the field group. Existing definitions cannot be overwritten accidentally; explicitly delete a managed definition before replacing it.

- Copies have independently generated deterministic keys and prefixed root names; local container-child names remain local. Internal sibling conditions are remapped to the new keys. Name/key collisions, unavailable dependencies, external condition references and recursive Clone dependencies reject.
- **Snapshot semantics, not live inheritance:** saved groups and exports contain ordinary materialized fields. Updating/deleting a source does not rewrite existing copies, metadata or layouts. No automatic content/name migration is implied. Trusted PHP macro registrations must keep their library dependencies available and register definitions before groups.
- PHP definitions and managed administrator snapshots occupy separate ownership slots: at most **50 each**; managed snapshots share one private nonautoload option capped at **1 MiB**. A composition is bounded by **eight structural/composition levels, 2,000 visited nodes, 200 fields per list and 1 MiB expanded output**; ordinary existing container limits still apply (20 basic children, 10 Flexible layouts, 50 rows). These are composition safeguards, not new nested-container support or larger content-row limits. Prefixed names are at most 80 bytes, prefixes at most 40.
- Library composition does not weaken field privacy/REST/public-output flags. Existing settings are copied—review exposure controls before saving. Conditions must be self-contained within their sibling definition. Library records are configuration, not content or callbacks. Materialized JSON/PHP/Local JSON remains portable without the source library; exporting/synchronizing the reusable registry itself is not implemented.

```php
// Trusted PHP, before registering groups. Use explicit keys for condition sources.
alify_register_field_library('contact', 'Contact details', [
    ['key' => 'field_contact_email', 'name' => 'email',
     'label' => 'Email', 'type' => 'email'],
]);
alify_register_field_group([
    'key' => 'page_contacts', 'title' => 'Page contacts', 'location' => 'page',
    'fields' => [
        ['clone' => 'contact', 'prefix' => 'home_'],
        ['clone' => 'contact', 'prefix' => 'work_'],
    ],
]);
// Or obtain concrete fields (array / WP_Error) for an explicit composition:
$fields = alify_clone_fields('contact', 'billing_');
```

Macros are PHP composition input only, **not a new stored field type or JSON schema dialect**. Library insertion/deletion uses authenticated `manage_options` AJAX, a dedicated nonce and an editable field-group ID. Managed writes serialize through an option lock; an interrupted lock requires verified administrator recovery, never automatic expiry. Helpers return `WP_Error` on failure.

### Separate ACF content migration — opt-in declared raw metadata

In **Field Groups → Migrations & ACF Import**, use **ACF content migration**, not the schema importer. Destination Alify fields must already be active/published. This copies an explicitly selected **subset of existing posts in the current database**; it does not run ACF, discover every field or interpret formatted ACF objects. Declare each source name, exact `field_…` reference key, raw type and destination name. The administrator is responsible for an accurate declaration; field types are not inferred from marker strings.

```json
{"posts":[123],"mappings":[
  {"from":"legacy_title","to":"new_title",
   "acf":{"key":"field_legacy_title","type":"text"}},
  {"from":"legacy_photo","to":"new_photo",
   "acf":{"key":"field_legacy_photo","type":"image","id_map":{"42":88}}}
],"overwrite":false}
```

- Supported raw types: the schema adapter's 13 basic/choice/Group/Repeater types, plus **Image, File, Gallery, Post Object, Relationship, Taxonomy, User and Link** (21 total). Reference destinations must be defined separately; the schema-only importer still supports 13 types. Source/destination types and multiplicity must match. Standard scalar/singleton-list single references and complete ordered ID lists are supported; formatted objects, unsupported fields (including Password/Clone/Flexible/WYSIWYG/temporal/map types), nested containers and implicit conversions reject.
- For every media/post/term/user field, supply an explicit **old→existing-destination ID map**, at most 500 entries, **including identity mappings on the same site**. Missing IDs, merged/duplicate list items, unavailable objects, wrong media types and destination permission/filter/cardinality violations reject. No file copying/downloads, user creation, term assignment or automatic cross-site ID reuse.
- Read standard flattened Group keys (`group_child`) and Repeater count/index keys (`rows`, `rows_0_child`) plus each `_name` reference marker. Every declared value/marker must exist exactly once; there is no missing-value/default inference. Declare every destination child in order (maximum 20), with its local `name`, `key`, `type` and optional reference `id_map`. Undeclared source fields remain untouched/unmigrated. Repeater counts are 0–50, never truncated. Group parents must use standard empty parent values.
- Example container descriptor: `"acf":{"key":"field_rows","type":"repeater","sub_fields":[{"name":"caption","key":"field_caption","type":"text"}]}`. A supported multi selector adds `"multiple":true`; otherwise scalar mode is explicit/default. No callbacks/code in JSON.
- Normalization that would strip/change content rejects before applying; choice-definition ordering and canonical integer/string ID representation are handled deliberately. Clean incompatible source values explicitly. No silent flattening, dropped selected IDs, media creation or sanitization loss.
- The same **100 posts / 20 mappings / 1 MiB / ten records / 30-minute preview**, creator ownership, explicit overwrite, write-ahead backups, global lock, dry-run and guarded rollback rules apply. Source values and reference markers are rechecked before applying, with fresh metadata/group queries rather than stale in-request caches. Source ACF metadata is **never written/deleted**, including during rollback; later ACF edits do not block destination-only rollback. Later destination edits remain protected.
- Existing v0.31 migration backups remain compatible with their original hash format. New fingerprints include location and registered-rule definitions; state is reread after lock acquisition. Recovery records/options may contain private source/destination data and must be protected and explicitly forgotten.

PHP: `alify_plan_acf_content_migration($post_ids,$mappings,$overwrite=false)` returns a safe summary / `WP_Error`. Use the existing apply/rollback/summary/forget APIs. This is a bounded **raw-metadata adapter**, checked with declared fixtures and native WordPress flows—not certification against every ACF version, an installed ACF plugin, all ACF storage variants or drop-in compatibility. Schema transfer remains configuration-only; content copying is always a separate explicit action. No transactional guarantee or non-post migration is added.

## Guarded migrations and ACF schema import (0.31.0)

Open **Field Groups → Migrations & ACF Import** as an administrator. These are explicit tools, not an automatic compatibility upgrade. Pause editing and take a database backup first.

### Existing-post field migrations

- Define both source and destination Alify fields before planning. A name change is a **staged copy**: migrate explicit posts to the new field, verify, then manually retire the old schema. Source metadata is never deleted automatically. Containers use the same staged approach; keep old and new Flexible layouts defined until finished.
- Supply **1–100 unique post IDs** and **1–20 mappings**. Modes: `copy` (matching structural shapes and canonical source data), `text`, `number`, `wrap`, `unwrap` (at most one element; never truncate), and `layouts` (explicit old→new names with no unknown/dropped subfields). Destination validation and permissions remain authoritative. Existing destinations/in-place conversions need `"overwrite":true`.
- Example: `{"posts":[123,456],"mappings":[{"from":"old_name","to":"new_name","mode":"copy"}],"overwrite":false}`. Layout mode adds `"layouts":{"old_layout":"new_layout"}` to a Flexible-field mapping.
- **Create validated plan** runs the real shared write validation without plugin content writes. Plans expire after 30 minutes, are creator-bound, and capture applicable schema fingerprints and exact source/destination presence/values. Apply rejects schema/value/capability changes and duplicate metadata rows. Password-containing source/target fields and groups containing custom callback types are excluded.
- Plans/backups use private nonautoload options, not exports: **1 MiB per record, ten records per administrator**. Applied backups persist until **Forget backup**. Summaries show IDs/counts/status, never values. Backups can contain other private field data: restrict database access and explicitly forget records when no longer needed.
- Apply/rollback use a global `add_option` lock and durable write-ahead backups. Later editorial changes are not overwritten by rollback. Failures attempt guarded restoration and retain recovery state; this is **best effort, not a database transaction or whole-post/content/revision rollback**. External hooks/editor races remain possible. A crash-held `alify_cf_migration_lock` is not automatically stolen: a qualified administrator must verify no migration request is running, preserve the matching private record, remove the stale lock, and use the owning account's rollback/manual database recovery. Never clear a live lock.
- Alify-to-Alify mode handles existing posts only, with no automatic whole-site scans, schema mutation, source deletion or users/terms/options/widget migration. ACF values are read only by the separate explicit v0.32 content planner described above.

PHP: `alify_validate_fields($values,$post_id)` returns canonical proposed updates or `WP_Error`; `alify_plan_migration($post_ids,$mappings,$overwrite=false,$transform=null)` returns a safe summary. Apply/rollback with `alify_apply_migration($id)` / `alify_rollback_migration($id)`; inspect/forget with `Alify_CF_Migrations::summary($id)` / `::forget($id)`. PHP-only `mode=custom` requires a trusted callable passed as `$transform`; it runs during planning, and only its validated output is staged. No callable/code is accepted from JSON. Callbacks/third-party validation filters themselves are trusted code, not sandboxed.

### ACF configuration adapter — not content migration

Paste **one ACF group object or one-element JSON export array**, up to **1 MiB / depth 24**. **Inspect schema** reports all recognized incompatibilities and warnings; **Import as new draft** repeats validation and uses the existing conflict-checked Alify transfer pipeline. Existing groups are never updated; failed/unsupported imports do not create partial groups. Review the draft before publishing.

Supported types: **Text, Textarea, Email, URL, Number, True/False, Select, Checkbox, Radio, Button Group, Color Picker (hex string), Group and Repeater**. Basic names/keys/labels/instructions/required/defaults, static choices/multiple Select/choice return settings, and single-level container subfields are mapped. Root maximum 200 fields; containers at most 20 basic children; Repeater maximum 50 rows. Pagination is DOM paging, not ACF lazy loading. Optional-choice clearing, presentation and formatted-getter behavior differences are reported as warnings; raw getters remain canonical.

Locations require 1–10 OR branches with the **same explicit `post_type == value` base**; each branch can also use supported `post`, `post_status`, `page_template`, `current_user_role` equality/inequality predicates. Destination post types/roles/templates must validate on this site. Unconditional source branches retain their unconditional meaning; unsupported predicates or mixed base types reject, never silently broaden.

Unknown settings/types, active field conditionals, nested containers, numeric min/max/step, textarea formatting, nondefault max length, AJAX/custom choices/toggles, color opacity/non-string returns, binding opt-ins and REST exposure reject. Presentation-only settings produce warnings rather than pretending to reproduce ACF UI. ACF field keys are schema identifiers only: **no source values, `_field_name` references, site-local IDs, credentials or PHP callbacks are read/migrated**. This is neither ACF Local JSON synchronization nor ACF API/drop-in parity. PHP wrappers: `alify_inspect_acf_schema($json)` and `alify_import_acf_schema($json)`; both require administrator permission.

## Conditional dependencies and native custom blocks (0.30.0)

### Conditional chains and reference sources

Root sibling fields can now depend on other conditional sibling fields, independently of their definition/DOM order. **Cycles, self/missing references and dependency paths longer than 16 links are rejected**, including cycles in ANY branches that happen not to run. Invalid/cyclic runtime groups fail closed for conditional fields. Existing limits remain: ten leaves per field and three ALL/ANY group levels.

A hidden source is **unavailable**, not an empty value: none of its leaves match, including `empty` and `not_equals`. Other available branches in an ANY group can still match. Hiding a chain preserves stored values and retained DOM input values. Classic forms ignore hidden submitted values; the explicit PHP/REST write APIs reject a request that explicitly asks to write hidden fields, without partially changing an upstream source. Re-enable a source to edit its dependents.

**Relationship, multiple Post Object, Taxonomy, User and Page Link** can now be condition sources. Single sources compare their canonical ID/token; lists use ANY-selected-token matching. `equals` means exact key/ID/token membership, `not_equals` means no item matches, and `empty`/`not_empty` inspect the selection. Existing literal/pattern operators compare individual canonical token strings, not display labels, URLs or formatted object returns. Search text, complete markers and picker buttons never count as selections. Selection permissions and reference validation remain unchanged; conditions do not grant object access.

Password, WYSIWYG, custom scalar callbacks, Group/Repeater/Flexible, Link and Google Map remain ineligible sources. Cross-group references remain unsupported; v0.33.0 adds independent sibling subfield scopes as described above. Chains/new reference-source semantics require **schema JSON v10**; downgrade attempts reject before import, and Local JSON supports versions 1–11 with their feature gates.

### Native dynamic-block API

Trusted PHP can register a dynamic Gutenberg block on `init`:

```php
add_action('init', function () {
    alify_register_block_type('alify/notice-card', [
        'title' => 'Notice card',
        'fields' => [
            ['name' => 'headline', 'label' => 'Headline', 'type' => 'text'],
            ['name' => 'count', 'label' => 'Count', 'type' => 'number', 'default' => 0],
            ['name' => 'enabled', 'label' => 'Enabled', 'type' => 'true_false', 'default' => false],
        ],
        'render_callback' => function ($values, $block) {
            return '<section><h2>' . esc_html($values['headline']) . '</h2></section>';
        },
    ]);
}, 20);
```

The API returns `true` or `WP_Error`. It registers native API-v2 attribute schemas and a PHP renderer; editor assets provide Inspector controls, insertion and authenticated native server-side preview from immutable debounced attribute snapshots. WordPress handles ordinary post-content saving/reloading, capabilities and core revisions. The optional `$block` is a `WP_Block` during normal block rendering, or null when a caller directly uses `WP_Block_Type::render()`; do not assume a post context always exists.

**Attributes are public serialized post content, not private Alify metadata. Do not put passwords, credentials or private field values in them.** Supported controls are Text, Textarea, integer Number (±1,000,000), True/False and single Select. Text is limited to 2,000 UTF-8 bytes; Select accepts 1–100 configured key/label choices and an empty choice. Scalar types/defaults are strict; zero/false are retained. Maximum 30 blocks/request, 20 controls/block, and 128 KiB rendered HTML. Reserved/duplicate names, unsupported types/settings and malformed defaults reject registration; existing native block names are never replaced.

Only declared sanitized attribute values reach the trusted callback. Rendered output passes `wp_kses_post`; scripts/arbitrary embeds are not passed through. Native WordPress attribute preparation may replace invalid serialized attributes with declared defaults; the extra validator rejects values that still violate plugin limits at render. This is **not** whole-post atomic validation or a new block-content migration endpoint. There is no required-field/publish gate, private field-group reuse, nested/container controls, custom inline canvas editor, static HTML fallback after deactivation, or ACF block/schema compatibility. Use ordinary PHP deployment for these trusted definitions; field-group JSON does not contain callback code.

## Native contexts, deployment and editing (0.29.0)

### Locations and permissions

Field-group Location now includes **Attachment**, **User**, **Comment**, **Menu**, **Menu item**, **Classic widget**, and `term:<taxonomy>` alongside posts/CPTs and Options. Existing user profiles, term edit screens, comment metaboxes, attachment edit screens and navigation-menu items use native WordPress hooks. **Object Fields** also edits existing authorized `user:ID`, `term:ID`, `comment:ID`, `menu:ID` and `menu_item:ID` contexts. There is no anonymous object editing or object creation API.

```php
$value = alify_get_object_field('profile_note', 'user:123');
$result = alify_update_object_fields(['profile_note' => 'Updated'], 'user:123');
```

Object APIs require the corresponding WordPress edit capability, validate complete configured values and retain prior values on malformed submissions. Authenticated `/alify/v1/objects/<kind>/<id>` GET/POST routes use per-field `api_read`/`api_write` opt-ins; they are not anonymous public-data endpoints. Password/custom-containing reads remain excluded. Attachments use the ordinary post getter and the native `edit_attachment` save hook, not a hypothetical `save_post` attachment event.

Classic widgets support **existing saved instances only**, through `in_widget_form`/`widget_update_callback`. Block widgets and Customizer editing are not implemented. Missing plugin nonce retains prior custom values; invalid fields reject the instance update. `alify_get_widget_field('field_name', 'text-2')` reads raw values only for an existing registered widget and active matching field definition. Widget adapter behavior is covered in real WordPress backend checks; no native widgets-browser claim is made.

The optional **Location rules JSON** setting is an OR list of AND lists, with at most 10 groups, 10 predicates per group and 50 total predicates. Each predicate has `param`, `operator` (`==`/`!=`) and string `value`. Built-ins: `post_id`, `status`, `template`, `term` (`taxonomy:ID`), and `current_user_role`. They narrow the group's base location; they never grant object permissions. For example:

```json
[[{"param":"status","operator":"==","value":"publish"}],[{"param":"post_id","operator":"==","value":"123"}]]
```

Trusted extensions register a unique `x_` predicate using `alify_register_location_rule($name, $matcher, $validator = null)`. Matcher arguments are value, base location and object context; return a boolean. Validators accept one value and must return true. Unknown/unavailable predicates fail closed. Location predicates apply to rendering, reads, writes, forms and applicable revision snapshots, not just builder display.

### Automatic Local JSON

Create a trusted deployment directory and define `ALIFY_CF_JSON_DIR` before plugin initialization (for example, an existing `WP_CONTENT_DIR . '/alify-json'`). This is opt-in: no directory is silently created, and no HTTP request can select a filesystem path. Administrator group saves atomically replace their deployment-key JSON file when writable. Files include location predicates and schema version; draft/trash database definitions take precedence over runtime files. Deleting/trashing a deployed definition writes a tombstone, so it cannot silently reappear on another deployment.

**Local JSON** lists files and offers capability-/nonce-checked, hash-guarded database synchronization. Published file-only definitions load automatically as runtime groups. Sync checks key ownership and conflicting definitions and attempts snapshot rollback on failed persistence; it is not a transactional database guarantee. Bounds: 100 files, 1 MiB each, supported schema versions 1–10; symlink files/directories are rejected. This is Alify's own format, not ACF JSON import or content migration.

### Revisions, autosaves and frontend forms

Post field snapshots have a 1 MiB ceiling and schema hashes, with explicit permission-checked `alify_get_revision_field($name, $revision_id)` / `alify_restore_field_revision($post_id, $revision_id)` APIs. Password/custom-containing fields are omitted. Changed schemas or invalid data reject field restore without replacing current field metadata. Core post-content restoration is not part of an all-or-nothing custom-field transaction.

Authenticated own-post REST autosaves validate complete groups and preserve parent field metadata. Pending values use private revision storage or a one-hour per-user/post fallback; `alify_get_autosave_field($name, $post_id)` explicitly reads that pending state. Ordinary raw getters still read saved parent values. WordPress 6.2's revision hook is supported without assuming the second hook argument added in 6.4.

For authenticated **existing-post** editing, call `alify_form_head()` before theme output/`wp_head()`, then `alify_form($post_id)` in the page, and keep `wp_footer()`. The API returns `WP_Error` on denial; callers should handle it. Forms use complete-group markers, a post-bound nonce, edit authorization, shared validated writes and a safe redirect. Native media/editor assets reuse WordPress capabilities and upload handling; there is no custom upload endpoint, anonymous submission or new-post creation flow. A failed POST renders an error and retains saved values, not a client-side draft/recovery UI. Never nest the generated form in another form.

### Containers and bounded patterns

Repeater `page_size` is 0 (off) or 1–50. Pagination hides existing DOM rows but **never disables or drops their inputs**: all rows remain in submission. Required-field validation reveals all rows. This is presentation pagination, not lazy loading or an increased 50-row bound.

Flexible layouts support `min_rows`/`max_rows` (0–50) and `disabled`. Limits are validated on the server before cleaning; disabled layouts prohibit row-count growth while existing rows remain editable. Picker/Add/Clone/Remove controls reflect those limits. No nested containers, layout renaming/migration or unlimited content promise is implied.

Conditional operators now include **`glob`**, alongside literal contains/prefix/suffix. This is an anchored, case-sensitive, literal ASCII pattern, **not regex/PCRE**: only `*` is special (zero or more characters); `.`, `?`, brackets and backslashes are literal. Pattern: 1–256 printable ASCII characters and at most four stars. Source: at most 4,096 ASCII characters (tabs/CR/LF allowed). Non-ASCII/over-budget inputs do not match. Matching uses bounded literal searches on both PHP and JavaScript, never evaluates user-supplied regex, and supports selected scalar lists with ANY-item semantics. Version 0.30.0 adds root conditional chains and reference-selection sources; v0.33.0 adds scoped sibling subfield conditions.

### Explicit remote output

`oembed` stores only canonical HTTPS YouTube/Vimeo video URLs. `alify_get_oembed($name, $post_id)` explicitly fetches a fixed approved provider (3-second timeout, 128 KiB, no redirects, five calls/request, one-hour success cache) and reconstructs an origin/video-validated iframe; it never trusts arbitrary provider HTML or runs discovery against a supplied host. Network/provider failures return an error; schema saves do not fetch embeds. Provider responses were exercised using WordPress HTTP mocks, not live external-service certification.

`google_map` currently stores **manual** latitude/longitude/zoom/address with complete-marker and bounds validation. `alify_get_google_map_html()` emits an explicitly requested Google Maps Embed iframe only when `ALIFY_CF_GOOGLE_MAPS_KEY` is configured. Restrict that browser-visible key at the provider. An interactive map/search picker is **not implemented**, so roadmap item 22 remains open. New contexts/settings/types and glob conditions require schema JSON **v9**; earlier versions remain accepted within their declared gates.


## Date/Time display and return formats

Date, Time and Date Time now have separate **Editor display preview** and **Formatted getter return format** preset selectors, at the root and inside basic Group/Repeater/Flexible subfields.

| Type | Supported presets (PHP-style tokens) |
|---|---|
| Date | `Y-m-d`, `d/m/Y`, `m/d/Y`, `j M Y`, `F j, Y` |
| Time | `H:i`, `H:i:s`, `g:i a`, `g:i A`, `g:i:s A` |
| Date Time | `Y-m-d H:i:s`, `Y-m-d H:i`, `d/m/Y H:i`, `m/d/Y g:i A`, `j M Y g:i A` |

- **Backward compatibility:** `alify_get_field()`, `alify_get_option()`, existing typed/container helpers, metadata/Options, defaults, validation and conditions remain **raw/canonical**. Native controls still submit canonical values; their own visual layout remains browser-localized. The configured editor format controls an adjacent, live **Display** preview, not the native date-picker UI.
- Opt in to configured return formats with **`alify_get_formatted_field($name, $post_id)`** or **`alify_get_formatted_option($name, $page)`**. These resolve a uniquely matching field in published groups for the object's location and format its basic temporal subfields recursively through supported containers. Missing values remain `null`, cleared values remain `''`; without a unique published definition, return the raw value unchanged. Non-temporal values/unknown saved keys/layouts remain unchanged. Getters do not write data or add authorization; Password values are still plaintext.
- Example: `alify_get_field('day', $post_id)` returns `2024-02-29`; with return preset `d/m/Y`, `alify_get_formatted_field('day', $post_id)` returns `29/02/2024`. Escape formatted strings for the output context. Helpers perform schema lookup per call; no performance claim or automatic formatted HTML output is implied.
- Presets use **English** month names and deterministic AM/PM. No timezone conversion, Unix timestamps, locale translation, arbitrary PHP format strings or timezone tokens. Time storage stays minute precision; `H:i:s` returns seconds `00`. Date Time retains actual saved seconds; native zero-only millisecond suffixes are normalized to whole seconds, while nonzero fractions remain invalid.
- Invalid preset values reject schema changes atomically. Changing presets never migrates content or changes canonical rule values. Invalid legacy temporal strings format as `''`; use raw getters for inspection. Legacy schemas without settings keep their exact schema shape and default canonical presets.
- **JSON version 3** is emitted whenever temporal formats exist anywhere, including layout subfields, so older importers reject rather than silently drop settings. Requires **0.23.0+**; version 1/2 schemas remain supported. Native controls and saved-value recovery remain unchanged apart from the additional preview.

## Combined Date Time field

**Date Time (`date_time`)** uses a native `datetime-local` input with second precision. Storage and PHP getters use **`YYYY-MM-DD HH:MM:SS`**. Native `YYYY-MM-DDTHH:MM[:SS]` and canonical space-separated strings are accepted; omitted seconds become `00`.

- **Floating wall-clock contract:** no timezone ID/offset, UTC conversion or timestamp is stored. Site/browser timezone changes do not shift the value. DST gaps/folds are not resolved or rejected as instants: for example `2026-03-08 02:30:00` remains that wall-clock value even where that time does not occur. Applications needing an instant must explicitly choose a timezone and handle ambiguity; do not append `Z` or assume UTC.
- Years **1000–9999**, real Gregorian calendar dates, hours `00–23`, minutes/seconds `00–59`. Reject impossible dates, leap seconds, nonzero fractions, offsets/`Z`, whitespace padding, non-strings and malformed payloads. Nothing silently rolls over. Invalid schema defaults reject the schema update; valid defaults canonicalize.
- Supported on posts/Options/basic Group, Repeater and Flexible subfields. Required rejects blank; optional fields have **Clear value**. Invalid values retain previous scalar/container/Options data under existing save-safety contracts.
- Conditional source/target support compares canonical values (e.g. `2024-02-29 09:30:00`), not native `T` input strings. Hidden dependent values remain saved. Classic/Gutenberg/core REST use the same server validator.
- Invalid dates that cannot be represented by the native input recover from saved values rather than appearing as accidental clears; containers with such invalid dates recover saved rows, with parent diagnostics when row correspondence is lost.
- Schema JSON transfers defaults, conditions and basic subfields; destination plugins need **0.22.0+**. Use `alify_get_field()` / `alify_get_option()` for canonical strings. Browser locale controls the native UI; configurable previews and opt-in formatted returns are available in 0.23.0 (see above).

## Password field

**Password (`password`)** is a masked, reversible text field—not authentication, hashing, encryption or a secret vault. **Do not use it for account passwords, API keys or confidential credentials.** Stored values are plaintext in post metadata/Options, backups and authorized editor HTML. PHP getters return the raw value; `alify_the_field()` / `alify_the_option()` can expose it publicly if used in a template. Masking is not an access-control boundary, and `autocomplete="new-password"` is only a browser hint.

- A labeled password input has keyboard-operable **Show value / Hide value** controls. Each page reload starts masked. Optional fields have **Clear value**; blank means explicit clear, not “keep unchanged.” Required means nonempty (`0` and spaces count as supplied strings).
- Saves exact UTF-8 strings, preserving spaces, quotes, slashes, markup and percent sequences. No trim/HTML stripping/entity decoding. Values are escaped when inserted into editor HTML. Maximum **1024 bytes**; Unicode control characters, invalid UTF-8 and non-string payloads are rejected, retaining the old value. Native maxlength is a character limit, so the server additionally enforces bytes.
- Posts/Options and basic Group/Repeater/Flexible subfields are supported, including Classic and the authenticated Gutenberg/core REST save bridge. Current core REST responses do not echo custom Password values; the write-only bridge is not a general value-read API.
- Can be conditionally shown, but **cannot be a conditional source**. Password defaults are not supported: builder type changes clear defaults, schema saves reject nonempty defaults, and JSON imports strip them, including basic subfields.
- **Recovery privacy:** fields with current Password definitions, and entire containers containing them, are omitted from rejected-input recovery transients—including hidden fields and saved-value fallbacks. On a Classic/Options redirect those controls show saved data instead; re-enter unsaved changes in those fields/containers. Other fields retain normal recovery. Container diagnostics fall back to the parent when the attempted row data is not recoverable. This does not prevent plaintext existing in the current browser DOM, requests, database or third-party logs.
- Schema-only JSON transfers Password definitions with empty defaults, never stored values. Destination plugins need **0.21.0+**. Password-policy rules, strength meters, account-password updates, encrypted storage and secret-management integration are outside this increment.

## Range field

**Range (`range`)** pairs a named numeric input with an unnamed native slider. Configure `min`, `max` and `step` in the root/subfield builder; omitted settings become `0`, `100` and `1`. Values/getters are canonical decimal strings (`1.500` → `1.5`, `-0.000` → `0`) or `''` for an explicitly cleared optional field.

- Bounds/settings/values support **±1000000**, with **at most three decimal places**. Minimum must be less than maximum; positive step must not exceed the span. Exact scaled-integer arithmetic avoids floating-point step tolerance. Step is anchored at the minimum; an unaligned maximum is not selectable.
- Strings use plain decimals: no whitespace, leading zeros, leading `+`, exponent notation or `step=any`. Finite numeric JSON values are accepted if their converted decimal meets the same bounds/precision rules. Booleans, arrays and null submissions are rejected. Invalid defaults/settings reject the schema update, preserving its old definition.
- An empty numeric input means **unset**. Merely rendering the slider never submits its fallback position. Moving it selects a value; numeric edits synchronize it only when valid, without silently clamping invalid input. Optional fields have **Clear value**; required fields reject empty values, but accept `0` when aligned/in bounds.
- Supported on posts/Options and basic Group/Repeater/Flexible subfields. Invalid submissions retain old scalar/container/Options values under the existing save-safety contracts.
- Conditional source/target support uses the canonical number (compare `0.5`, not `0.500`). Hidden dependent values stay preserved. Classic and Gutenberg/core REST share server validation.
- JSON transfers bounds/step/defaults/conditions and subfields. Destination plugins need **0.20.0+**. Get values through `alify_get_field()` / `alify_get_option()`; display formatting, arbitrary precision and unit prefixes/suffixes are outside this increment.

## Button Group field

**Button Group (`button_group`)** is a segmented, single-choice control. Define `value : Label` choices and an optional matching default. It stores one string key (including `0`), or `''` after optional clearing; getters return the key, not a label/object.

- Uses real labeled radio inputs styled as segments: native Tab/arrow-key navigation, visible keyboard focus and native required-group validation, without custom keyboard emulation. Segments wrap when space is limited; selected/disabled/forced-colors states have explicit styles.
- Optional groups have **Clear selection**. Required groups cannot clear, and the server rejects empty, unknown or malformed selections while retaining old saved data under the existing scalar/container/Options contracts.
- Supported on posts/Options and basic Group/Repeater/Flexible subfields. Cloned rows have independent names/IDs; clearing one group does not affect another.
- Can be a condition source/target. The checked segment is used, not the hidden empty-submission sentinel. Hidden dependent values remain saved.
- Classic and Gutenberg/core REST saves use the same value contract. Schema JSON preserves choices/defaults/conditions/basic subfields; destination plugins need **0.19.0+**.
- Custom-choice creation, multiple selection, configurable return formats and orientation settings are not part of this increment. Richer choice settings remain in the roadmap.

## Radio Button field

**Radio Button (`radio`)** stores one configured choice key as a string, or `''` when an optional selection is cleared. Define choices using `value : Label`, one per line; defaults must match a configured key. The key `0` is a valid selection, including for required fields.

- Supported on posts, existing UI-enabled CPT editors, Options Pages and basic Group/Repeater/Flexible subfields. Dynamic rows receive independent radio group names/IDs.
- Optional fields have **Clear selection**; required fields use native radio-group validation and reject empty server submissions. Native arrow-key behavior is retained.
- Unknown keys, arrays, booleans and other malformed values are rejected by value validation. Existing scalar/container/Options data is retained under the normal save-safety contract.
- Can be a conditional source or target. Browser rules read the checked radio, not the empty submission sentinel; hidden dependent values remain preserved.
- Classic forms and the Gutenberg core-save bridge submit the same single-key contract. JSON transfers choice definitions/defaults/conditions and basic subfields; destination plugins need **0.18.0+**.
- `alify_get_field('mode', $post_id)` / `alify_get_option('mode')` return the choice key, not its label. Custom “other” choices are not supported; layout/opt-in formatted returns are configurable (see below).

## Relationship field

**Relationship (`relationship`)** selects an ordered list of up to **50 unique references** (posts/pages by default; configured CPTs supported). It reuses Post Object's permission-filtered search and adds Move earlier/later, Remove reference and Clear all references. Supported on posts, Options Pages and basic Group/Repeater/Flexible subfields.

- Values are stored as ordered integer-ID arrays. Duplicate IDs keep their first occurrence. The raw submitted list is capped at 50 before deduplication; excess items are rejected, never truncated.
- The form/REST payload is `{__complete: '1', ids: [42, 51]}`. An explicit clear sends the marker with an empty/omitted `ids` list. Missing markers, malformed/non-list IDs, unavailable targets or new unreadable references reject the entire list; a container subfield failure retains its whole container. Options saves remain page-atomic.
- Existing saved unreadable references may be retained/reordered or explicitly removed; their titles remain redacted. Deleted targets must be removed/replaced rather than silently discarded.
- Required means at least one reference; configured minimum can require more when nonempty. Legacy limits are 0–50; v0.26 adds CPT/status filters and cardinality settings (see Reference configuration). Defaults, bidirectional writes and cross-site ID migration remain unsupported.
- Relationship can be conditionally shown and, since 0.30.0, can drive conditions through selected-ID membership. JSON transfers schemas only with empty defaults; destination plugins need **0.17.0+**.

```php
$posts = alify_get_relationship('related_many', get_the_ID()); // Viewable WP_Post objects, saved order.
foreach ($posts as $related) {
    printf('<a href="%s">%s</a>', esc_url(get_permalink($related)), esc_html(get_the_title($related)));
}
// Raw IDs (including saved unavailable references): alify_get_field('related_many', $post_id).
// Options: alify_get_option('related_many', 'site'). Typed getter filtering never changes stored IDs.
```

## Post Object field

**Post Object (`post_object`)** defaults to a single post/page reference with a searchable selector, type filter, Previous/Next pages and explicit Clear selection. It works on post/Options fields and as a basic Group/Repeater/Flexible subfield. References store canonical IDs, not copied content or titles. WordPress scalar post-meta reads return a string ID; serialized container/Options values retain integer IDs.

- Legacy schemas support **posts and pages**, with publish/private/draft/pending/future statuses. Version 0.26 adds configured CPT/status filters and optional ordered multi-selection (see Reference configuration). Attachments, revisions, trash and auto-drafts remain excluded.
- Search requires authentication, a user-scoped nonce and `edit_posts`, `edit_pages` or `manage_options`. Each result also passes object-level read permissions; password-protected targets require password access or edit permission. Only IDs, readable titles and types are returned—no content or unfiltered totals.
- Queries fetch at most 20 candidates per page, pages 1–100. Permission-filtered pages can be empty; Next does not imply that another readable result exists. Refine the search instead of expecting exhaustive browsing. Search terms are capped at 200 bytes.
- New references require read access. An unchanged pre-existing reference (or the same saved container's reference moved between rows) can be retained after access loss, without disclosing its title. Deleted/unsupported targets are rejected until explicitly cleared/replaced; saved content is not silently rewritten.
- Required fields need a valid nonempty reference. Optional fields explicitly clear to `''`; malformed/forged IDs reject with the previous scalar/container retained. Options saves stay page-atomic.
- Defaults are always empty and reset during JSON transfer because IDs are site-local. JSON transfers schema only, not referenced posts; destination plugins need **0.16.0+**.
- Whole-field conditional visibility works; source comparisons use the canonical ID string. Existing subfield-condition/nesting limits still apply.

```php
$related = alify_get_post_object('related', get_the_ID()); // WP_Post or null.
// Resolves only currently public/viewable or permitted targets, not unreadable private content.
if ($related) {
    printf('<a href="%s">%s</a>', esc_url(get_permalink($related)), esc_html(get_the_title($related)));
}
// Raw ID: alify_get_field('related', $post_id), or alify_get_option('related', 'site').
```

## WYSIWYG rich-text field

**WYSIWYG Editor (`wysiwyg`)** supports post/Options roots and basic Group/Repeater/Flexible subfields (0.28.0+). Start with the escaped HTML textarea, or choose **Enable Visual/Text editor** for WordPress's native TinyMCE/Quicktags interface: paragraphs/headings, bold/italic, lists, quotes, links and undo/redo. JavaScript/editor initialization is not required to submit the textarea fallback.

- Visual content is synchronized to the real field before normal form submission and Gutenberg's same-request REST payload. Text-tab edits are not overwritten by the inactive Visual editor. Explicit HTML paragraphs are preserved (`wpautop` conversion is disabled). Native editor instances are saved/removed/reinitialized around Classic metabox sorting.
- All submitted HTML and defaults use **`wp_kses_post()`**, including administrator input; `unfiltered_html` does not bypass this field policy. Event handlers, executable URLs and disallowed elements are removed. No shortcodes, embeds, blocks or theme filters are executed by the field.
- Empty paragraphs, NBSP and invisible spacing normalize to `''`. Required means **nonblank text**: formatting-only or media-only content does not satisfy it. Optional blank formatting explicitly clears; invalid non-string payloads are rejected. Existing saved-value retention/publication rules apply.
- Recovery is separately KSES-filtered and escaped into the textarea. A hidden conditional rich field preserves its stored value. WYSIWYG can be a conditional target but **not a source**.
- Version 0.28.0 adds dynamic subfields, opt-in upload-capability-checked media buttons and bounded toolbar/editor presets; see the bundled features below.
- JSON transfers the field schema and sanitized HTML default only; destination plugins need **0.15.0+**.

```php
$html = alify_get_field('rich_copy', get_the_ID());
// For Options: $html = alify_get_option('rich_copy', 'site');
if (is_string($html)) {
    echo wp_kses_post($html); // Deliberate HTML output; alify_the_field() still escapes as text.
}
```

## Link field

**Link (`link`)** combines URL, optional title and same/new-tab selection in one atomic value. It works on posts and Options Pages and as a basic Group/Repeater/Flexible subfield. Stored values are `['url' => 'https://example.com', 'title' => 'Visit', 'target' => '_blank']`; same-tab target is `''`. A completely cleared optional link stores `[]`.

- Only absolute **HTTP(S)** URLs are supported. Relative/protocol-relative URLs, mailto/tel/FTP, embedded credentials, whitespace/control characters and executable schemes are rejected. Use ASCII/punycode hostnames.
- Required means a valid URL is mandatory; title remains optional. Use **Clear link** to reset all components. A title/target without a URL is an incomplete value, not a clear request.
- Missing or malformed structures retain the entire previous Link; invalid container subfields retain the whole container. Options saves remain page-atomic. Attempted input recovery is escaped and separate from saved data.
- Link defaults are empty in this increment (no configurable composite defaults). Link may be conditionally shown but is **not a condition source**. Existing nesting/subfield-condition limits still apply.
- REST submits `{__complete: '1', url: 'https://example.com', title: 'Visit', target: '_blank'}` under the normal field name. All components and the completion marker are required; an explicit clear sends three empty strings with the marker.
- JSON transfers schema only; destination versions older than **0.14.0** reject the unknown type. No URL fetching, link search or content migration is performed.

```php
$link = alify_get_link('cta', get_the_ID()); // Validated record, or [].
// Options: $link = alify_get_option('cta', 'site');
if ($link) {
    printf('<a href="%s" target="%s"%s>%s</a>',
        esc_url($link['url']), esc_attr($link['target'] ?: '_self'),
        $link['target'] === '_blank' ? ' rel="noopener noreferrer"' : '',
        esc_html($link['title'] ?: $link['url'])
    );
}
```

## Color Picker and Time

Both new basic types work as post fields, Options Page fields, and Group/Repeater/Flexible subfields. They support defaults, required validation, conditional visibility and configuration-only JSON transfer like other basic fields.

- **Color Picker (`color`)**: native color swatch plus editable hex text, **Apply swatch** and **Clear color**. Apply explicitly commits the displayed swatch, including black when it has not changed. By default saves six-digit hex as lowercase (`#2271b1`); optional alpha additionally accepts `#rrggbbaa` (see configuration below). Rejects shorthand, named colors, rgba() and CSS expressions. An empty optional field stays empty—an untouched swatch never implicitly saves black. Required means the submitted hex text cannot be empty.
- **Time (`time`)**: native browser time input, stored as a strict 24-hour **`HH:MM`** string (`00:00`–`23:59`). No seconds, date, timezone conversion or timestamp semantics. The browser may display a localized clock; the submitted/stored format stays `HH:MM`.
- Invalid nonempty values are rejected rather than silently cleared. Optional empty values explicitly clear; invalid container subfields retain the entire previous saved container.
- Builder defaults use the same format rules. Invalid defaults normalize to empty, following existing basic-field behavior. Themes read strings with `alify_get_field()` / `alify_get_option()` and must escape output for its context.
- JSON preserves these schemas/defaults. Destination plugins need **0.13.0+**; older versions reject the unsupported field types. Existing JSON version-selection rules are unchanged.

## Theme API

```php
// Optional post ID; otherwise uses the current post in the loop.
$subtitle = alify_get_field('subtitle', get_the_ID());
if ($subtitle !== null) {
    echo '<h2>' . esc_html($subtitle) . '</h2>';
}

// Convenience helper: escaped plain text for scalar values only.
alify_the_field('subtitle');

// Checkbox values are arrays of selected choice keys.
$amenities = alify_get_field('amenities');
foreach ((array) $amenities as $amenity) {
    echo '<span>' . esc_html($amenity) . '</span>';
}
```

`alify_get_field()` returns `null` if the value has not been saved. It does not apply schema defaults; defaults populate the editor and become stored values on save. Number values are numeric strings; True / False values are `'1'` or `'0'`. It is a template helper, not an authorization boundary: check permissions before exposing private post data in an endpoint. Escape returned values for their output context (e.g. `esc_url()` for link destinations).

Select / Checkbox choices accept one entry per line:

```text
small : Small
medium : Medium
large : Large
```


## Image and File fields

Both types return an **attachment ID**, not a URL or an ACF-style array. WordPress scalar post metadata normally reads as a **numeric string**; options-page values retain an integer ID. Cast to `(int)` when an integer is needed. An empty saved selection is `''`. Media defaults can be supplied as an existing attachment ID. The WordPress media picker is available to users with `upload_files`; selecting a different attachment additionally requires permission to edit that attachment. Removing an optional selection does not delete the attachment.

```php
$hero_id = alify_get_field('hero_image');
if ($hero_id) {
    echo wp_get_attachment_image((int) $hero_id, 'large');
}

$file_id = alify_get_field('download');
$url = $file_id ? wp_get_attachment_url((int) $file_id) : false;
if ($url) {
    echo '<a href="' . esc_url($url) . '">Download</a>';
}
```

On staging, select/upload an image and a PDF; save and reload, replace and remove them, then test a required empty media field. Attempt to submit a PDF ID for an Image field or a non-attachment post ID for either field. Confirm the previous value survives with an error notice. Verify media selection as administrator and editor, and clearing as a custom role without upload permission.

## Gallery fields

Choose **Gallery** in a field group or repeater subfield. Use **Add Images** to select multiple images in the native WordPress media picker. New selections append in selection order; images already in the gallery are not duplicated. Use the earlier/later arrow buttons to reorder, **Remove** for a single image, or **Remove all** to clear it. These actions never delete files from the Media Library.

```php
// Gallery values are ordered arrays of attachment IDs, not URLs or image arrays.
foreach ((array) alify_get_field('property_gallery', get_the_ID()) as $image_id) {
    echo wp_get_attachment_image((int) $image_id, 'large');
}

// Options Pages use the same value shape.
foreach ((array) alify_get_option('brand_gallery') as $image_id) {
    echo wp_get_attachment_image((int) $image_id, 'thumbnail');
}

// Inside a repeater row:
foreach (alify_get_rows('projects') as $project) {
    foreach (($project['photos'] ?? array()) as $image_id) {
        echo wp_get_attachment_image((int) $image_id, 'medium');
    }
}
```

### Gallery behavior and limits

- Maximum **50 images per gallery**, including galleries inside rows. Gallery defaults, drag sorting, pagination, captions editing and configurable min/max settings are not implemented. New galleries start empty; a required gallery needs at least one valid image.
- JavaScript is required for gallery controls. Selecting an over-limit batch rejects the whole new selection with a message; current images remain unchanged.
- Saved values are unique integer attachment IDs in display order. Unsaved values read as `null`; an explicitly cleared optional gallery saves as `[]`. `alify_the_field()` / `alify_the_option()` do not output arrays.
- The server validates positive integer IDs, image attachment type, existence and trash status. Newly selected IDs require attachment edit permission; existing IDs can be retained/reordered without that permission. The Add Images button requires `upload_files`; removal does not.
- An invalid image, missing completion marker or truncated submission retains the **whole previous gallery**. An invalid gallery in a repeater retains the whole repeater; an invalid gallery on an options page rejects the whole page save. Host input-variable limits still apply to large galleries/repeaters.
- Deleted/unavailable images are shown with a warning label; remove them before saving a visible gallery. This avoids silently dropping stale content.
- Top-level galleries support conditional logic as sources and targets. Equality means selected-ID membership; empty/not-empty checks the selection count. Hidden galleries preserve their stored values. v0.33.0 adds local sibling subfield conditions.
- Converting an existing field to/from Gallery does not migrate its old value. Structural changes should be tested on a backup/staging site.

### Gallery staging checks

1. Select/upload three images, save/reload, reorder them, remove one, save again, and verify frontend order in classic and block editors.
2. Select an existing image again and confirm it is not duplicated. Remove all, save/reload, and verify `[]` for an optional gallery; repeat with required enabled and confirm previous data is retained.
3. Test Gallery in a newly added repeater row and on Site Settings. Verify independent control names, row ordering and options-page atomic saves.
4. Test each conditional operator with selected image IDs and hide/reveal a required gallery; confirm hidden values survive and hidden inputs do not block validation.
5. Test non-image IDs, trashed/deleted attachments, invalid/missing markers, restricted attachment permissions, and a large form near the host's input-variable limit.
6. Verify keyboard earlier/later controls, mobile layouts, no-upload roles, real media-modal cancellation, and the 50-image cap.

## Options Pages

A built-in **Site Settings** submenu is available under **Alify Custom Field**. To populate it:

1. Create a field group (or edit an existing one).
2. Set its **Location** to **Options Page: Site Settings**.
3. Publish the group, then visit **Alify Custom Field → Site Settings**.
4. Enter global values and click **Save Settings**.

All fifteen field types, including media and repeaters, use the same editing controls as post fields. Conditional logic remains scoped to a single field group. The options screen and its save handler require `manage_options`.

```php
// Read from the built-in 'site' page (not from a post).
$email = alify_get_option('contact_email');
if ($email) {
    echo '<span>' . esc_html($email) . '</span>';
}

// Escaped plain-text output for scalars only.
alify_the_option('footer_text');

// Repeater values are ordered arrays. alify_get_rows() is for post fields only.
foreach ((array) alify_get_option('social_links') as $link) {
    echo '<a href="' . esc_url($link['url'] ?? '') . '">'
        . esc_html($link['label'] ?? '') . '</a>';
}
```

### Additional pages (developer API)

Register pages on every request, on `init`, before `admin_menu` runs:

```php
add_action('init', function () {
    alify_add_options_page('footer', 'Footer Settings');
});

// Once its published field group has been filled and saved:
$footer_text = alify_get_option('footer_text', 'footer');
alify_the_option('footer_text', 'footer');
```

`alify_add_options_page($slug, $title)` returns `true` on successful registration, `false` for invalid/duplicate registration. Slugs must start with a lowercase letter, contain only lowercase letters, numbers, underscores or hyphens, and be at most 50 characters. The built-in `site` page cannot be overridden. Additional-page creation through an admin UI, custom capabilities, nesting and menu customization are not implemented yet.

### Options storage and validation

- Each page uses one namespaced WordPress option (`alify_cf_options_{slug}`), with autoload disabled. Values are **site-scoped**, not multisite network settings, and are isolated from post metadata and other options pages.
- Field names must be unique across groups on the same options page; different pages/posts may reuse a name. Only published groups assigned to the submitted, registered page are accepted.
- Saves use a page-bound nonce, capability checks and completion markers. Successful and failed saves redirect back with a short-lived per-user/page notice.
- Options saves are **all-or-nothing for the whole page**: any invalid field, media permission failure, incomplete group, truncated form or conflicting legacy names retains all previous settings. This is deliberately stricter than post saves, which allow other valid fields to save.
- Hidden fields preserve old values, optional unchecked toggles/checkboxes clear normally, and repeaters retain their existing bounds and validation rules. Host input limits still apply.
- Helpers return `null` before a value has been saved. Defaults populate the editor, not the read API. Helpers are not an authorization boundary: don't expose private settings through an endpoint without permission checks, and escape values in templates.
- Removing a registration/group/field does not automatically delete its stored option values. Renaming a page slug or moving a field group between post and options locations does not migrate data. No reset/delete UI or programmatic option-update helper is included yet.

### Options staging checks

1. Open empty Site Settings and verify its setup instructions. Assign/publish a group and verify its fields appear there, not in post editors.
2. Save text, multiline text, backslashes, checkbox/toggle values, media and repeaters; reload and verify helper output.
3. Try invalid required/email/media values; confirm **no** settings on the page change. Test a missing/incomplete form marker and an expired nonce.
4. Hide a required field/repeater with conditional logic; save and reveal it, verifying stored values survive.
5. Register a second page, use the same field name on each, and verify isolation. Attempt conflicting names within one page and verify group-schema rejection.
6. As an editor/subscriber, verify the menu and direct page/save URLs do not grant access. Test administrator media selection and real WordPress redirect notices.
7. Test both small/mobile admin layouts and large repeaters near the host's input-variable limit.

## Repeater fields

Select **Repeater** as the field type, then add 1–20 subfields in its card. Each subfield has a local name, label, type, instructions, choices, default and required setting. All twelve basic/non-container types (including Image/File/Gallery) work as subfields. Add, remove and move subfields with the builder buttons.

**Row limits:** configure **Minimum rows** (0–50) and **Maximum rows** (1–50), with minimum ≤ maximum. Legacy repeaters default to 0–50; a required repeater has an effective minimum of at least one. Limits apply on posts, options, Classic/core REST publication and JSON schema transfer (destination version 0.11.0+).

The editor disables Add at the maximum and Remove at the effective minimum; keyboard move controls remain available. New rows are not automatically inserted. Server checks remain authoritative: forged or incomplete/below-minimum/above-maximum submissions retain the complete previous list, not a truncated subset. Hidden repeaters preserve values and do not enforce their bounds until visible.

Changing limits never migrates or silently removes existing rows. Out-of-bounds saved/recovered lists remain rendered so editors can add/remove rows to fix them; publication/new saves can require correction. Parent required status and each subfield's requirements still apply. Limits are strict whole numbers; malformed bounds reject the entire schema change. Groups and Flexible layouts keep their own existing limits; this increment does not add nesting or alter storage/helper return types.


In the post editor, use **Add Row**, **Remove row**, and the up/down buttons to manage row order. A repeater starts empty; subfield defaults apply to newly added rows, not retroactively to existing rows. Marking the repeater required means at least one valid row is required. JavaScript is required for row management.

```php
// Same rows are also returned by alify_get_field('team_members').
foreach (alify_get_rows('team_members', get_the_ID()) as $member) {
    echo '<h3>' . esc_html($member['name'] ?? '') . '</h3>';
    if (!empty($member['photo'])) {
        echo wp_get_attachment_image((int) $member['photo'], 'thumbnail');
    }
}
```

Rows are stored as a single ordered array in the repeater's post-meta value, using subfield names as array keys. `alify_get_rows()` returns an empty array for missing values; `alify_the_field()` does not print repeater arrays. There is no ACF-style `have_rows()` cursor. Explicit `alify_update_row()` / `alify_delete_row()` helpers are available; see the developer API section.

### Repeater limits and save behavior

- **Single level only**, maximum **20 subfields** and **50 rows** per repeater. DOM pagination, duplication and drag/keyboard ordering are available; v0.33.0 adds local sibling conditions. Nested repeaters remain unsupported.
- A whole repeater can be a conditional target. It cannot be a condition source. Hidden repeaters preserve all saved rows and skip validation.
- Each row is sanitized against its schema, including required and media checks. A newly selected media ID requires attachment edit permission; existing repeater attachment IDs may survive reordering without that permission.
- If any row is invalid, **all previous rows for that repeater are retained** and the existing save-error notice identifies the repeater. Other valid fields may still save. Errors are displayed at repeater level, not against individual subfields. Best-effort rejected-input recovery is available as described below.
- Completion markers detect missing/truncated repeater submissions (including common PHP `max_input_vars` truncation) and preserve previous rows. Your host's input limits may be reached before the plugin's row cap; test realistic forms and adjust limits or reduce fields/rows. Explicitly removing every optional row and saving clears its value to `[]`.
- Subfield names need only be unique within their repeater. Renaming/removing subfields does **not** migrate content. On the next post save, unknown/removed subfield values are dropped and renamed fields receive the currently submitted value. Back up content before structural changes. Changing an existing scalar field to Repeater is not a data migration.

### Repeater staging checks

1. Create a `team_members` repeater with required `name`, optional `email`, checkbox `roles`, and image `photo`. Add three rows, save/reload, reorder, remove and save again. Check template output order.
2. Test all twelve subfield types, Unicode, quotes and backslashes. Confirm choice arrays, unchecked toggles and cleared media round-trip correctly.
3. Try invalid email, a PDF in an Image subfield and a missing required name. Verify the entire previous repeater is retained with a notice.
4. Remove every optional row; confirm it stays empty after reload. Mark the repeater required and try saving zero rows.
5. Add a top-level toggle condition to the repeater; hide it, save, reveal it and verify rows survive and hidden required subfields do not block saving.
6. Test the row cap, large forms near `max_input_vars`, and both classic/block editors. Verify media selection works inside newly added rows. Test keyboard moves and narrow screens.

## Conditional Logic

Expand a field card and enable **Conditional Logic**, then select a source field, comparison and value. Example: a True / False field named `show_cta`, followed by a required `cta_text` field shown when `show_cta` **Is equal to** `1`.

- Each field supports **1–10 total leaf rules** with **ALL (AND)** or **ANY (OR)** matching. Use **Add Rule**, select sources/operators/values, then choose the match relation. Sources are eligible scalar/choice/media/gallery/reference selections in the same group, including acyclic conditional sources (0.30.0+). Containers, structured Link/Map, WYSIWYG, custom callbacks and Password cannot be sources. Nested ALL/ANY groups are available through advanced JSON; cross-group and per-row conditions remain unsupported.
- Existing single-rule schemas and JSON exports retain their original shape. Compound schemas use `condition: {relation: "all" | "any", rules: [...]}` and require version 0.10.0+ on the destination site. Each rule still uses `field`, `operator` and `value`.
- Supported operators: **Is equal to**, **Is not equal to**, **Is empty**, **Is not empty**, literal **Contains/Starts with/Ends with**, and bounded ASCII **Matches * pattern**.
- Equality is case-sensitive string matching, not numeric comparison. Use stored choice keys (not labels), `1` / `0` for True / False, and attachment IDs for media.
- Checkbox/Gallery equality tests whether the comparison key/image ID is selected; inequality tests absence. An unchecked True / False field counts as empty; number/text `0` does not.
- Rules reference stable field keys, so renaming/reordering the source does not break them. Removing a referenced source requires updating/disabling its rules before the schema can save. Self-references, cycles and chains exceeding 16 links are rejected with the previous schema retained.
- Initially visible fields are calculated from saved values/defaults. JavaScript is required for live updates and disabling hidden inputs so browser validation ignores them.
- At save time, rules use sanitized submitted source values, not old saved values. Hidden field values are **preserved**, not cleared, even if a forged request submits them. Visible fields retain the existing validation behavior. Sanitization of invalid/HTML input can change a source value on the server; use plain, valid source values for comparisons.
- Conditional logic is an editing convenience, **not authorization**. Template helpers still return saved hidden values; explicitly apply any frontend display and permission checks in your template.

### Conditional staging checks

1. Create the `show_cta` / required `cta_text` example above. Toggle it, save, reload, and verify visible/hidden states and required behavior in both classic and block editors.
2. Save CTA text, hide it, save, reveal it, and confirm it survives. Confirm hidden required fields do not trigger validation notices.
3. Test Select/Checkbox choice keys, empty text, number `0`, unchecked toggles, and each of the four operators.
4. Rename/reorder the source, duplicate the target, then reload; ensure rules persist and duplicates can be edited independently.
5. Try deleting the source or making it conditional; verify the group configuration is rejected until affected rules are fixed.
6. Test groups containing identical legacy keys independently; rules must never read another group's controls.

## Flexible Content

Choose **Flexible Content** and define 1–10 uniquely named layouts, each containing 1–20 basic subfields (including media/gallery). Editors choose a layout, add rows and move/remove them. A field holds at most 50 rows in any mixture of its layouts. There are no nested containers or layout-level conditions; v0.33.0 supports conditions between basic siblings inside each row/layout; whole-field visibility is supported, but Flexible fields cannot be condition sources.

```php
foreach (alify_get_layouts('sections', get_the_ID()) as $row) {
    if ($row['layout'] === 'hero') {
        echo '<h2>' . esc_html($row['fields']['title'] ?? '') . '</h2>';
    }
}
// Options return the same ordered row array:
$sections = alify_get_option('sections', 'site');
```

Each persisted row is `['layout' => 'hero', 'fields' => ['title' => '...']]`. Templates must explicitly handle known layout names and escape values; layout names never execute code. `alify_get_field()` also returns the raw stored array. `alify_get_layouts()` returns an empty array before save.

All rows validate atomically. Invalid subfields, unknown layouts, unauthorized new media or missing completion markers retain the previous complete list. A required Flexible field needs at least one valid row. A complete empty optional list clears it. Existing restricted media is retained only within its original layout's media pool, including reordering.

Layout names/keys and definitions transfer through JSON; content does not. Destination sites need Flexible support (0.9.0+). Image/file defaults reset. Removing/renaming a layout does not migrate data: saved unknown rows show a warning and block replacement until their schema is restored or the editor explicitly removes them. Removing/renaming subfields similarly requires review; no data migration or revision restoration is provided. Errors/recovery are field-level; diagnostic text names the first failing row/layout/subfield, with an inline message beside the offending subfield and a jump action.

## Group fields

A **Group** stores related basic subfields as one associative object rather than a list of rows. Add a Group in the field builder, then define 1–20 local subfields. The same local names can be used in different Groups without colliding with top-level fields.

```php
$profile = alify_get_group('profile', get_the_ID());
echo esc_html($profile['name'] ?? '');
// Options-page Groups use the normal options helper:
$settings = alify_get_option('profile', 'site');
```

- Posts and options support Group text/choice/media/gallery subfields. Subfield media IDs are integers inside the serialized object; gallery IDs remain ordered integer arrays.
- The whole Group is validated atomically. An invalid required subfield, unauthorized new attachment or incomplete payload retains the previous complete object. Post publication and core REST validation include Groups.
- A required Group needs at least one nonempty subfield (an unchecked toggle does not count); individually required subfields must always validate when the Group is visible.
- Optional checkbox/toggle-only Groups can be cleared. Missing completion markers never clear saved data.
- Whole-Group conditional visibility is supported; Groups cannot be condition sources. v0.33.0 adds sibling subfield conditions; nested Group/Repeater containers remain unsupported.
- Group schemas and subfield keys participate in JSON transfer (the destination needs Group support, version 0.8.0+). Image/file defaults reset during transfer as with other fields. Errors/recovery are shown at the Group boundary, not individually per subfield.
- `alify_get_field()` also returns the associative object. `alify_get_group()` returns an empty array before the first save; escape subfield output for its context. Renaming subfields does not migrate existing data.

## Field-group JSON import / export

Open **Alify Custom Field → JSON Import / Export** (requires `manage_options`). Select one configured group and download JSON; on the destination site upload that file or paste its JSON, then choose **Import as draft**. Review the new draft before publishing.

- Versioned Alify format (`format: "alify-custom-field"`, integer `version: 1`–`7`), one group per document, maximum **1 MiB**. Not an ACF JSON importer. Version 2 is emitted for configured repeater bounds so older importers reject the file instead of silently discarding limits; temporal format settings (including subfields) require version 3; richer choice settings/nonempty Checkbox defaults require version 4; Color/media/Link configuration requires version 5; new reference configuration requires version 6; Taxonomy/User/Page Link selectors require version 7; schemas without these features continue to use their existing version.
- Transfers the title, registered location, field definitions, stable field keys, scalar defaults, choices, conditions and single-level repeater/gallery configuration.
- Never transfers content values, media files, source post IDs, statuses, arbitrary metadata or options-page registrations. Image/file defaults are reset because attachment IDs are site-local; gallery/repeater content is never a schema default.
- Imports always create a **new draft**, never update or publish an existing group. Duplicate names in another non-trashed group at the same location reject the import—even on the source site. Rename the fields/change location first if intentionally creating a copy.
- Uses the same schema validator as the field editor, including 200-field/20-subfield caps, supported types, name/key and condition checks. Unavailable post types/options pages must be registered on the destination first.
- Both actions require admin capability and a valid nonce. Invalid imports create no group; storage verification failures remove the newly created draft. Database-wide transactions/concurrent administration are not provided.
- Upload and PHP request limits can be lower than 1 MiB. Exported configuration can contain sensitive labels/default text: review files before sharing.

## Current limits

- Field names must start with a lowercase letter and contain only lowercase letters, numbers and underscores. Names are reserved across published, draft, pending, private and scheduled groups matching the same post type. Conflicts reject the entire configuration change, preserving its previous fields and location. Trashed groups do not reserve names; avoid restoring legacy conflicting groups without reviewing them.
- Renaming a field does not migrate existing values. Removing fields/groups or deactivating the plugin does not delete saved content. No uninstall deletion is performed.
- Required scalar fields use browser validation. Required checkbox groups are checked on the server. Invalid/empty required submissions retain the previous stored value; invalid new publication/scheduling is blocked. Invalid nonempty optional scalar submissions also retain previous values. Save-error notices list affected fields on the next post-edit page load (reload after a block-editor save if needed). Other valid fields still save. Best-effort rejected-input recovery is described below.
- Checkbox defaults are not supported. For True / False defaults use `1` or `0`. Setting a True / False field as required means it must be enabled.
- No nested containers, anonymous REST value reads or ACF data/API compatibility. Validated revision restoration is available since 0.29.0. Version 0.28.0 adds trusted PHP registration and opt-in authenticated read/write routes; the existing editor-only bridge remains distinct.
- UI strings are English in this initial release; full localization is pending.

## Validation feedback and input recovery

Rejected fields now show an error underneath the field; scalar text-like controls also receive `aria-invalid` and error-description references. Editing the field clears stale client-side error highlighting, while the server validates again on save. Repeater errors remain at the repeater boundary rather than individual rows/subfields.

After a rejected save, a highlighted note distinguishes **attempted unsaved input** from persisted values. Recovery is private to the current WordPress user and the specific post/options page. It expires after **10 minutes** and clears after successful correction. Frontend helpers always read saved data, never recovery data.

- Recovery is best-effort: text is sanitized and limited to 10,000 bytes per value; unsupported/malformed values are not preserved verbatim. Native number/date controls may not be able to display invalid formats.
- Gallery/repeater completion markers must be valid to recover their submitted structure; incomplete structures fall back to previously saved values. Hidden fields also retain saved values.
- Unauthorized new media selections are not exposed through recovered media previews. Existing allowed IDs can survive row reordering.
- Recovery values larger than 256 KiB in aggregate are omitted; errors remain and the form explains that values must be entered again.
- Recovery is ignored after the group's schema changes, avoiding stale values being rendered with a different field type.
- Options-page validation remains atomic; Classic post saves can still save other valid fields/content. Incomplete metabox group submissions retain previous values, including scalars.

### Publication protection

Classic editor attempts to publish, schedule or make a post private validate all active field groups first. Invalid new publication remains a draft (or keeps its previous pending/public status), with a reload notice and inline errors. Editing an already published/scheduled/private post never silently unpublishes it; Classic can still save content and other valid fields.

Gutenberg injects enabled metabox controls into the same core REST save as `alify_fields`. Server-side validation rejects an invalid request with HTTP 400 **before the post write**, and the editor shows field errors. Requests without this payload validate saved/default values before publication, so simply omitting fields is not a required-field bypass. Core authentication/capabilities still apply. The bridge is write-only, not an ACF-compatible or public value-read REST API.

Autosaves and ordinary draft saves without a field payload remain available. Direct SQL, `wp_publish_post()`, cron status transitions and custom third-party publishing pipelines are not comprehensively gated; this is editor/core REST protection, not a database-wide publication policy.

### Container validation diagnostics

Group errors identify the subfield; Repeater errors identify the visual row number and subfield; Flexible errors also identify the schema's layout label. For example: **Row 2 (Contact) → Email: A valid value is required.** Row counts and incomplete structures have explicit correction/reload messages.

The **first failing location per container** appears in the parent alert and beside the exact offending subfield, across Classic, Gutenberg/core REST and options feedback. The first invalid control receives focus; a “Go to invalid field” action returns to it, opening closed WordPress metaboxes/details ancestors. Rows themselves currently stay expanded. Structural/count errors remain at the parent boundary. When recovery falls back to saved values, it does not falsely mark unrelated saved rows. All-failure enumeration is not yet provided. Fix the reported issue and save again to reveal another failure. The complete previous container stays saved until validation succeeds.

Messages never include rejected scalar values, submitted row tokens, unknown layout names or attachment identities. Container alerts have accessible description references; REST-created errors link to controls, and editing clears stale invalid/error references without removing existing help descriptions. This is targeted accessibility hardening, not a completed accessibility audit.

## Rich choice settings (0.24.0)

- **Radio / Checkbox:** vertical (default) or wrapping horizontal layout. Checkbox defaults accept one configured key per line in the builder (JSON uses a list); defaults apply only when no saved value exists.
- **Select:** optional multiple selection, up to **100 submitted keys**. Defaults use one key per line when multiple is enabled. Lists are deduplicated and stored in configured-choice order; key `0` remains valid. Native multi-select keyboard behavior applies (Ctrl/Cmd or Shift as appropriate).
- **Searchable Select:** local, case-insensitive key/label filtering, not a remote/Select2 picker. Selected options stay visible, filtering never changes selected values, and optional fields have a clear-selection button. Native option rendering is browser-controlled.
- **Return format:** `value` (default), `label`, or `array` (a `{value, label}` PHP array). Only the opt-in `alify_get_formatted_field()` / `alify_get_formatted_option()` apply this setting, including basic container subfields. Checkbox/multiple Select return a list of labels or records. Raw getters, storage and conditions **always use keys**. Unknown previously saved keys become `null` for formatted single choices or are omitted from formatted lists; raw getters remain available for inspection.
- Same settings work on post/Options roots and supported Group/Repeater/Flexible basic subfields. Choice defaults/settings are validated atomically; unknown defaults are rejected. Changing single/multiple mode is a schema change, **not** a stored-data migration.
- Gutenberg and Classic collect all selected options. Programmatic write-bridge submissions for multiple Select require `{"__complete":"1","values":["a","b"]}`; `{"__complete":"1"}` explicitly clears an optional selection. Incomplete/malformed/unknown-key/over-limit submissions retain the previous field/container (Options retains the whole page); required selections cannot be cleared. This is the existing authenticated editor write bridge, not a general public write API.
- Rich choice settings/defaults require **schema JSON v4 / plugin 0.24.0+**; imports still accept versions 1–4. No custom-choice creation, asynchronous search or nested containers.

## Color, media and Link configuration (0.25.0)

These settings also work on supported basic Group/Repeater/Flexible subfields and Options. Raw getters/storage remain unchanged; formatted getters recurse through containers. New settings/defaults require **JSON v5 / plugin 0.25.0+**; imports accept v1–v5. Schema changes do not migrate saved data.

### Color

Enable **alpha** to accept six- or eight-digit lowercase hex. `#rrggbbaa` uses an alpha byte from `00` (transparent) to `ff` (opaque); six-digit values stay six-digit. No percentages, rgba(), shorthand or color-space conversion. The RGB picker and 0–255 opacity slider synchronize with text. Up to 20 palette colors are suggested choices, not an allowlist. Palette buttons preserve explicit zero alpha; clearing remains `''`. Invalid palettes/defaults reject the schema update. Native picker appearance remains browser-controlled.

### Image / File / Gallery

- **Return format:** opt-in formatted getters support `id` (legacy raw value), `url`, or `array`. Records contain `id` (integer), `url`, `title`, and `mime`; Gallery returns an ordered list. URLs resolve the original attachment, independently of editor preview size. Missing/trashed attachments format to `null` (single) or are omitted (Gallery). Empty and missing values preserve the existing empty/null contract. These are not ACF-compatible attachment arrays.
- **Restrictions:** up to 20 exact lowercase MIME types, one per line (`image/png`, `application/pdf`); empty means no additional restriction. No MIME wildcards or extension aliases. Existing image-only checks, upload/edit permissions and 50-image hard cap still apply. These settings do not enable new upload types or grant access.
- **File size:** integer `min_bytes`/`max_bytes`, 0–1073741824 bytes; maximum 0 means unlimited. Bounds apply per attachment, including every Gallery image. When configured, a readable local attached file is required; remote URLs are never fetched. Checks use WordPress's stored attachment MIME, not a new binary-content security scanner.
- **Gallery count:** minimum 0–50, maximum 1–50. Minimum applies to nonempty selections; optional fields may explicitly clear. Use Required to disallow empty. Invalid selections retain the whole previous field/container; Options retains the whole page. No silent truncation.
- **Preview:** choose `thumbnail`, `medium`, `large` or `full` for editor image previews. MIME picker filters and Gallery maximum help selection; the server remains authoritative for MIME, byte and count validation. Custom image dimensions/cropping and custom preview sizes are not implemented.

### Link

Configure an optional default with URL, title, and target (`''` or `_blank`). Only valid HTTP(S) URLs are accepted, with existing credential/control-character restrictions. Defaults initialize missing values; explicit clearing saves an empty array instead of reapplying the default. Opt-in formatted getters support `array` (legacy URL/title/target object) or `url`; raw getters always return the object. Themes must still escape URLs/text for their output context; a new-tab link needs the theme's appropriate `rel` policy.

## Reference configuration (0.26.0)

Post Object and Relationship support the following on post/Options roots and supported basic container subfields:

- **Allowed post types:** 1–20 registered `show_ui` post types, entered as one slug per line. Attachments, revisions and Alify field groups are excluded. Legacy schemas still target only posts/pages. A CPT must be registered on every request and on the destination site before schema import.
- **Allowed statuses:** a nonempty subset of `publish`, `private`, `draft`, `pending`, `future`. Type/status constraints apply to server validation as well as search. Changing filters does not migrate stored references; invalid saves retain the previous value/container/page rather than silently dropping targets.
- **Multiple Post Object:** ordered, deduplicated IDs, using the same add/remove/reorder picker as Relationship. The complete write-bridge payload is `{"__complete":"1","ids":[12,34]}`; `{"__complete":"1"}` clears an optional list. Incomplete/malformed payloads retain the previous list. Since 0.30.0, multiple Post Object can be a condition source using selected-ID membership; single Post Object keeps its scalar-ID behavior. Defaults are unsupported; changing scalar/list mode does not migrate saved values.
- **Cardinality:** multiple Post Object and Relationship accept minimum 0–50 and maximum 1–50. Minimum applies when nonempty; Required disallows empty. The picker prevents additions above maximum, while the server enforces both bounds without truncation.
- **Formatted returns:** `id` (default/raw behavior) or `object` through opt-in formatted getters. Single Post Object returns a viewable `WP_Post` or `null`; lists return ordered viewable objects and omit unavailable targets. Current permissions/password protection still apply; filters do not grant access. Raw getters remain IDs. Existing `alify_get_post_object()` / `alify_get_relationship()` resolvers also recognize registered CPTs; use configured formatted getters when field-specific filters must govern resolution.
- Search remains nonce/capability checked, paginated (20 queried candidates per page, up to 100 pages), and read-permission filtered. Filter values sent by the picker are search constraints, **not authorization**; no private totals or unreadable titles are returned. Changing permissions can leave a page empty. No taxonomy filtering or bidirectional writes yet.
- New reference settings require **schema JSON v6 / plugin 0.26.0+**, including subfield-only configuration. Imports continue accepting v1–v6.

## Taxonomy, User and Page Link selectors (0.27.0)

All three work on post/Options roots and supported basic container subfields. Use the search button/Enter, select results, remove or clear selections; multiple lists support moving earlier/later, deduplication and a hard maximum of 50 submitted items. Search has no unfiltered totals and supports pages 1–100. Requests are nonce/capability checked; stale responses are ignored after a query changes.

- **Taxonomy (`taxonomy`)**: configure 1–20 registered `show_ui` taxonomies (default `category`). Raw values are term IDs; formatted return is `id` or a currently available `WP_Term` (`object`). New selections/search require that taxonomy's `assign_terms` capability. Public taxonomies resolve for frontend formatted reads; nonpublic terms require assignment permission. This field stores metadata only: it does **not** create terms, assign WordPress post terms or synchronize term relationships. Term selection is flat/searchable, not a hierarchical tree.
- **User (`user`)**: optional allowlist of up to 20 registered role slugs; empty permits any current-site member. Search/new selections require **`list_users`** (plus editor/settings capability for the AJAX endpoint), so ordinary editors do not automatically get a user directory. Search uses display names only and returns ID/display-name labels, not email/login/password fields. Formatted return is `id` or `array` containing only `{id, display_name}`; safe records require `list_users` or the selected user's own identity. Anonymous/unavailable records become `null` or are omitted from lists. This deliberately never returns a `WP_User` object. Nonmembers/deleted/spam users and role mismatches are rejected; existing valid saved IDs may be retained without granting directory access. This is current-site membership checking, not a verified multisite compatibility claim.
- **Page Link (`page_link`)**: configure public UI-enabled post types (default post/page) and optionally allow archives for those types. Only published public posts and eligible `has_archive` types are selectable. Raw values are stable strings **`post:123`** / **`archive:book`**, not copied URLs; formatted getters resolve current permalinks/archive links. Lists preserve order. Missing/draft/trashed/nonpublic targets format to `null` or are omitted. A public password-protected post's URL does not bypass WordPress password protection. There is no arbitrary external URL or private/draft preview URL selection; use Link for external URLs.

Single values clear with `''`; multiple editor-bridge payloads use `{"__complete":"1","ids":[...]}` and explicit empty `{"__complete":"1"}`. Malformed, truncated, unknown or disallowed values retain the previous field/container; Options retains the whole page. Required values cannot clear. Raw IDs/tokens remain available even where formatted records are privacy-filtered: **raw metadata is not an authorization API**, so themes must enforce their own access policy and context-escape output.

Defaults, automatic taxonomy/user modification, cross-site ID migration and nested containers are unsupported. Selectors may be conditional targets and, since 0.30.0, canonical token sources. Single/multiple schema changes do not migrate saved data. Imports require destination taxonomies/roles/post types to be registered; **schema JSON v7 / plugin 0.27.0+** is required, recursively including subfield-only selectors. Current imports accept v1–v11 within their feature gates, and exports contain configuration, not selected site-local IDs or user records.

## Bundled editor and developer features (0.28.0)

### Presentation and rich rows

- **Icon Picker** stores a validated `dashicons-*` identifier from 40 bundled WordPress Dashicons; an optional `icons` allowlist narrows choices. No uploaded SVG, arbitrary HTML, external icon libraries or executable markup.
- **Tab, Accordion and Message** are root-level, editor-only presentation fields. Tabs/accordions organize following siblings; they do not disable or clear values in closed sections. Messages allow bounded paragraph/list/emphasis/link HTML (HTTP/HTTPS only), not scripts, event handlers or embeds. These fields cannot be required, conditional, REST-exposed or stored as values. Use unique field names even for presentation fields. Native invalid controls reveal their section.
- Rich text is now supported in **basic Group, Repeater and Flexible** subfields. `toolbar` selects `full` or `basic`; `editor_mode` selects `both`, `visual` or `text`; `media_buttons` is opt-in and also requires the current user's `upload_files` capability. This does not grant upload privileges or make media-only HTML satisfy a required field.
- Repeater/Flexible rows have drag handles, existing keyboard move buttons, duplicate and expand/collapse controls. Duplication synchronizes the source editor and uses a pristine template with fresh names/IDs, never a live TinyMCE iframe clone. Native row moves destroy/recreate the affected editors after the DOM move; removal destroys editors. Selected ordered IDs, clear markers and input state are preserved.
- `collapsed` sets initial row presentation; `row_label` names a direct safe scalar subfield. Passwords, custom types and rich/structured values are never used as row-label sources. Row labels are editor text, not a new stored value. Existing 200 root / 20 subfield / 50 row / 10 Flexible layout limits remain; there is no nested-container or pagination promise.

### Content Models UI

Open **Alify Custom Field → Content Models** to create/update Options pages, post types and taxonomies. Models have `alify_` identifiers (maximum 19 characters), labels, applicable public/REST/hierarchical/archive flags, and taxonomy target types. At most 30 definitions of each kind are supported. Existing other-source identifiers cannot be overwritten. Post types use ordinary WordPress post capabilities and title/editor/thumbnail/revision supports; dedicated custom capability generation is not included.

Definitions are stored as configuration, not content. Changes take effect on the next request; resave **Settings → Permalinks** after changing public routes. The page provides schema-only JSON export and bounded, atomic **new-identifiers-only** import (256 KiB); planned imported post types can be taxonomy targets. Unregistering never deletes posts, terms or values. Saved content and declared group/taxonomy dependencies block unregistering. This UI is not a term/profile/comment/widget field editor or a content migration tool.

### Trusted PHP registration and writes

Register deployment code after the relevant post types/taxonomies/Options pages exist, e.g. at `init` priority 30:

```php
add_action('init', function () {
    alify_register_field_group(array(
        'key' => 'theme_article_fields',
        'title' => 'Article details',
        'location' => 'post',
        'fields' => array(array(
            'name' => 'article_credit', 'label' => 'Credit', 'type' => 'text',
            'api_read' => false, 'api_write' => false, 'public_output' => false,
        )),
    ));
}, 30);
```

- Registration returns `true` or `WP_Error`. Missing keys are stable hashes of the deployment group/name path. `alify_register_field($group_key, $field)` appends/revalidates a field. Duplicate names are rejected across database and registered groups on the same location. Re-registering the same key replaces that in-memory definition; it does not migrate stored data. PHP groups appear in applicable editors but are not database-editable copies.
- `alify_update_fields($name_to_editor_payload, $post_id)` / `alify_update_field($name, $payload, $post_id)` require the current user's `edit_post` capability. Revisions and internal field-group posts are not writable through these helpers. `alify_update_options($values, $page = 'site')` / `alify_update_option($name, $payload, $page = 'site')` require `manage_options`.
- Writes merge unchanged schema siblings, validate affected groups before persistence, reject unknown/hidden fields, preserve permission checks and return `true` or `WP_Error`. Composite arguments are the documented **complete editor payloads**, not arbitrary canonical getter values. Required invalid unchanged siblings can block a write. Options use one option write; post multi-meta storage errors trigger best-effort restoration, **not a database transaction or concurrent-write lock**. Trusted hooks must not mutate storage behind these helpers.
- `alify_each_row($name, $post_id)` yields canonical rows; `alify_get_sub_field($row, $name)` reads from the explicit row context (no global loop pointer). `alify_update_row($name, $zero_based_index, $canonical_row, $post_id)` replaces a row or appends at the current row count. `alify_delete_row($name, $zero_based_index, $post_id)` removes/reindexes. Row helpers repack canonical containers and revalidate the entire value; sparse indices are rejected. Options-specific row mutation wrappers are not provided.
- `alify_export_group_php($group_id)` requires `manage_options` and returns PHP registration source for database or registered groups. Review and save that source in your theme/plugin repository. Export strips site-local media/post and Password defaults; custom types and referenced configurations must exist on the destination. Do not evaluate untrusted exported/uploaded PHP. This is manual configuration deployment, **not automatic Local JSON synchronization**.

Trusted extensions can use `alify_cf/field_group_definition` (filter before registration validation), `alify_cf/registered_group` (action: key, normalized definition), and `alify_cf/before_api_write` / `alify_cf/after_api_write` (actions: normalized updates, object ID/page slug, is-options boolean). The latter actions cover these API writes, not every legacy WordPress metadata mutation. Reentrant API writes are rejected.

`alify_register_field_type('x_example', $callbacks)` registers a trusted PHP scalar extension: required `label`, callable `clean($field, $raw)` and `render($field, $value, $id, $input_name, $error_id)`; optional `settings($field, &$errors)` and `format($field, $raw)`. Cleaners return strings up to 65,536 bytes. Settings cannot override structural or exposure properties. Plugins own their callbacks' validation, output escaping and assets. Raw getters stay raw; formatted getters invoke the optional formatter. Custom types are not condition sources, and custom-containing fields cannot be exposed for REST reads/public output. Unknown custom types fail schema validation; there is no JavaScript settings-plugin interface yet.

### Explicit REST and public disclosure

All exposure flags default off. Root-field builder checkboxes control `api_read`, `api_write`, and `public_output`; nested fields follow the opted-in containing value, not independent endpoints.

- Authenticated **GET/POST** `/wp-json/alify/v1/posts/{id}` require `edit_post` for that post. `/wp-json/alify/v1/options/{page}` requires `manage_options` and a registered page. Standard WordPress REST authentication/nonces apply; no custom token bypass is provided.
- GET returns opted-in raw `values` plus a minimal `schema` map (`type`, `required`, `read`, `write`), not user objects or a full JSON Schema generator. POST accepts `{"values":{"article_credit":"Example"}}`; only `api_write` fields are accepted and the general validation/write helper runs. A successful POST returns `{"saved":true}`. Password values may be explicitly write-only; Password/custom-containing values cannot opt into read/output. No revision, term, user, comment or menu endpoints are implied.
- `[alify_field name="article_credit" post_id="123"]` returns HTML-escaped scalar text only for `public_output` fields on publicly viewable, non-password-protected posts. Draft/private/protected posts, Password, User, custom types, WYSIWYG and composite values produce no public output. **Opting in intentionally discloses that value publicly.** This is not a rich-HTML rendering shortcut.
- On WordPress versions with Block Bindings, source **`alify/field`** takes `args: {name: "article_credit"}` and the block's `postId` context. It shares the same public scalar policy. This is a basic native source, **not a custom-block framework, sidebar/datastore, Attributes-panel picker or live-preview implementation**. On WordPress 6.2 the binding registration is safely skipped.

### Reference filters and nested conditions

- Post Object/Relationship `taxonomy_filters` accept up to 10 ANDed clauses: `[{"taxonomy":"category","terms":[12,34],"operator":"IN"}]` (`IN` / `NOT IN`; maximum 50 existing IDs per clause). Terms in an IN clause are alternatives, and descendants are not implicitly included. The builder accepts JSON; the editor dropdown can narrow a configured IN selection. Filters apply to search and save and never grant object access. Nonpublic taxonomy names are shown only to users with assignment capability. IDs are site-local configuration; remap them explicitly on deployment.
- Conditions support mixed nested `all`/`any` groups with at most **three group levels and ten total leaves**. Flat controls remain; **Edit nested ALL/ANY JSON** opens the advanced representation. Leaf shapes remain `{field: "field_key", operator: "equals", value: "text"}`.
- New literal, case-sensitive operators `contains`, `starts_with`, `ends_with` compare each scalar/selected item; an empty comparison never matches. Comparison strings are limited to 1,000 bytes. Arbitrary regex is not implemented; bounded ASCII glob patterns are available in 0.29.0+. Version 0.30.0 adds acyclic sibling chains and reference-token sources; scoped subfield/cross-group conditions remain pending. Cycles are rejected.
- **Schema JSON v8 / plugin 0.28.0+** gates new editor/exposure/filter settings, nested/extended conditions, custom types and dynamic rich subfields. The original v0.28 importer accepted v1–v8; the current importer also accepts v9/v10 within their gates. Older destinations must reject v8 rather than silently discard these settings. Schema transfer is not selected-ID/content migration.

The entire 77-item roadmap is **not** complete. Versions 0.29/0.30 add scoped object editors, revisions/forms, conditional dependencies and scalar blocks. Deeper containers, bidirectional relationships, native post-field sidebar/datastore and dedicated integrations/distribution remain tracked in `DEVELOPMENT-ROADMAP.md`.

## Verification

Automated checks for 0.28.0:

```sh
npm ci
npm test       # 84 jsdom tests for builder, media, conditions, repeaters and feedback
npm run check # JavaScript syntax
php tests/php-unit.php # 783 assertions with isolated WordPress stubs
```

**Current bundle (0.33.0):** 805 PHP contracts on PHP 7.4/8.3, 93 JavaScript tests, 1,041 minimum / 1,044 modern WordPress SQLite assertions locally. Verified in [CI run 40770133129](https://github.com/alifywork/fieldsPlugin/actions/runs/40770133129): all four native PHP/WordPress SQLite/MySQL targets pass, including real Chromium local-condition reorder/duplicate/new-row persistence and consent-triggered mocked Maps/Places selection/save/reload, with clean WordPress debug logs. Google provider responses remain controlled fixtures, not live-service certification. Roadmap: **67/77 complete within declared scopes; 10 pending**.

**Previous bundle (0.32.0):** local checks pass: 805 PHP contracts on PHP 7.4/8.3, 88 JS tests, 1,016 minimum / 1,019 modern WordPress SQLite assertions. Verified in [CI run 40767256561](https://github.com/alifywork/fieldsPlugin/actions/runs/40767256561): all four native PHP/WordPress SQLite/MySQL targets pass, including real Chromium ACF content planning/apply/media remapping/rollback and reusable-definition save/insert/group persistence/delete/nonce flows, with clean WordPress debug logs. At that release: **65/77 complete within declared scopes; 12 pending**.

**Previous bundle (0.31.0):** 805 PHP contracts on both runtimes, 84 JavaScript tests, and local SQLite runs with 963 assertions on WordPress 6.2/PHP 7.4 and 966 on WordPress 6.8.3/PHP 8.3. [CI run 40762878282](https://github.com/alifywork/fieldsPlugin/actions/runs/40762878282) passes all four native PHP/WordPress SQLite/MySQL targets, including real Chromium migration plan/apply/rollback and ACF inspection/new-draft import, with clean WordPress debug logs. At that release: **63/77 scoped items complete; 14 pending.**

**Previous bundle (0.30.0):** 805 PHP contracts on PHP 7.4/8.3, 84 JavaScript tests, and clean-debug-log SQLite runs with 886 assertions on WordPress 6.2/PHP 7.4 and 889 on WordPress 6.8.3/PHP 8.3. All four native PHP/SQLite/MySQL targets pass in [CI run 40759192779](https://github.com/alifywork/fieldsPlugin/actions/runs/40759192779), including real Chromium chain/User-source frontend flows and custom-block Inspector, refreshed PHP preview, save/reload and public rendering, with clean WordPress debug logs. The declared matrix remains WordPress 6.2/PHP 7.4 and WordPress 6.8.3/PHP 8.3, each on SQLite/MySQL 8, not a latest-version certification.

Historical selector-batch baseline follows for comparison, not the current test count.

Isolated PHP contracts passed in PHP.wasm 7.4.33 and 8.3.33; JavaScript tests use jsdom and a mocked media frame. **Real WordPress backend integration** also passed with 600 assertions per target:

| WordPress | PHP | Database |
|---|---|---|
| 6.2 | PHP.wasm 7.4.33 | SQLite / WP_SQLite_DB |
| 6.8.3 | PHP.wasm 8.3.33 | SQLite / WP_SQLite_DB |

Both integration runs had clean WordPress debug logs. The 0.27.0 selector batch passes all four native-PHP CI targets, including real Chromium and HTTP persistence flows ([run 40711904295](https://github.com/alifywork/fieldsPlugin/actions/runs/40711904295): 600 backend / 187 browser / 24 HTTP assertions per target). These are explicit test targets, not a latest-version claim or a recommendation to deploy old versions. The suite exercises actual activation, hooks, capabilities, nonces, uploads, metadata, options, rendering, feedback, Classic publication/scheduling, core REST validation/creation/authentication and autosaves. This backend suite is separate from the browser suite.

```sh
# Requires Git/Bash and a native PHP executable with SQLite extensions.
bash tests/integration/run.sh
WP_VERSION=6.2 bash tests/integration/run.sh
```

See [integration setup, coverage and limitations](tests/integration/README.md). The runner creates an explicitly marked disposable database under `$HOME/.cache`, never a production database. A GitHub Actions matrix is included; its remote execution is separate from these local results.

**Earlier local browser baseline (0.6.2):** 14 end-to-end assertions passed locally on WordPress 6.8.3 / PHP.wasm 8.3.33, including Classic rejection/redirect/correction, Gutenberg REST rejection/correction, native media-modal gallery selection, gallery/repeater ordering after save/reload, and raw REST publication protection. Repeatable browser seed/cleanup scripts and native-PHP CI steps cover both matrix targets; see the integration guide.

**Still pending:** MariaDB, wider MySQL versions, repeater image-subfield media interactions, responsive/accessibility audits, multisite and wider third-party compatibility.

The CI matrix now includes isolated MySQL 8.0 services alongside SQLite for both PHP/WordPress pairs, running the same backend and browser suites. The MySQL runner requires explicit disposable-database opt-in and loopback credentials; see the integration guide. All four native-PHP targets passed 460 backend assertions, 149 browser assertions and 24 additional HTTP options persistence assertions in [CI run 40705342031](https://github.com/alifywork/fieldsPlugin/actions/runs/40705342031), with clean debug-log checks. Coverage includes actual options-page success/failure redirects, attempted-input recovery, correction, invalid nonce, incomplete forms and editor-role denial. Six independent CLI database inspections distinguish persisted values from recovery-rendered inputs and verify options/post isolation plus autoload-disabled storage. Existing container/media/JSON/conditional flows remain covered. These remote results are separate from the local sandbox SQLite checks.

### Manual staging checklist

1. Activate with `WP_DEBUG` enabled. Confirm no PHP notices or errors in classic and block editors.
2. As an administrator, create a group with all 26 types and a Posts location. Publish it; check field counts and location in the list.
3. Add, duplicate, delete, drag and move fields with the keyboard. Save/reload; confirm configuration/order persists. Try duplicate names and invalid names.
4. Fill a post with Unicode text, decimals, multiline text, email, HTTPS URL, date and choice values. Save/reload and check helper output.
5. Clear optional fields and uncheck all checkboxes. Save/reload; confirm old selections are cleared. Verify defaults, including a false toggle.
6. Try invalid email, URL, date and required values. Confirm invalid values are not stored and previous required values survive server validation.
7. Switch location to Pages or a UI-enabled custom post type. Confirm it disappears from Posts and appears on the new type. Draft/trash the group; confirm it stops rendering.
8. As an editor, confirm content values can be saved but group administration is inaccessible. Confirm subscribers cannot edit posts or groups.
9. Verify missing/invalid nonces, quick edit, autosaves and post revisions do not overwrite field values. Test HTML/script payloads in labels, instructions, choices and values.
10. Deactivate/reactivate and confirm content remains. Test a narrow admin viewport and keyboard interaction.

## License

GPL-2.0-or-later. Original implementation; not affiliated with ACF or its vendors.
