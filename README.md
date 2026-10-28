# Alify Custom Field

An independent WordPress custom-fields plugin with a modern, WordPress-native field-group editor. **Version 0.8.0 — structured Group fields**, not an ACF drop-in replacement.

## Requirements and installation

- WordPress 6.2+; PHP 7.4+ (a currently supported PHP version is recommended).
- Copy this repository folder to `wp-content/plugins/alify-custom-field/`, or ZIP its contents inside an `alify-custom-field` folder and upload through **Plugins → Add New → Upload Plugin**.
- Activate **Alify Custom Field**.
- Open **Alify Custom Field → Add Field Group**, enter a title, add fields, choose a post type, and **Publish**.
- Edit a post of that type, fill in its fields, and save the post.

## Included

- Administrator-only field-group management using WordPress's native publish/draft/trash workflow.
- Text, Text Area, Number, Email, URL, Select, Checkbox, True / False, Date, Image, File, Repeater, Gallery and Group.
- Single-level repeaters with a subfield builder, row add/remove/move controls and atomic value validation.
- Native WordPress media picker, image thumbnails, file labels and remove controls.
- Multi-image galleries with ordered, deduplicated attachment IDs and move/remove controls.
- Attachment IDs validated by type and attachment edit permissions on selection.
- Atomic schema validation and duplicate-name protection across active/draft groups on the same post type.
- Per-user save-error notices, inline field errors and short-lived unsaved-input recovery, separate from saved field values.
- Labels, stable field keys, field names, instructions, defaults, and required settings.
- Conditional show/hide rules with live editor updates and server-side enforcement.
- Expandable cards, drag sorting, keyboard-accessible move buttons, duplication and deletion confirmation.
- One location per group: a post type (including UI-enabled custom types) or registered Options Page.
- Built-in Site Settings and developer-registered additional options pages, with separate site-scoped storage.
- Custom metaboxes in classic and block post editors (not native block-editor sidebar controls).
- Nonces, edit permissions, autosave/revision guards, allowlisted types, value sanitization and escaped admin output.
- Values stored with a private `_alify_cf_` meta prefix, separate from group schemas.

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
- Top-level galleries support conditional logic as sources and targets. Equality means selected-ID membership; empty/not-empty checks the selection count. Hidden galleries preserve their stored values. Subfield conditions remain unsupported.
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

All fourteen field types, including media and repeaters, use the same editing controls as post fields. Conditional logic remains scoped to a single field group. The options screen and its save handler require `manage_options`.

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

Rows are stored as a single ordered array in the repeater's post-meta value, using subfield names as array keys. `alify_get_rows()` returns an empty array for missing values; `alify_the_field()` does not print repeater arrays. There is no ACF-style `have_rows()` cursor or per-row update API yet.

### Repeater limits and save behavior

- **Single level only**, maximum **20 subfields** and **50 rows** per repeater. No pagination, row duplication, drag sorting, nested repeaters or subfield conditional rules yet; row ordering uses accessible move buttons.
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

- Each field supports **one rule**, referencing an **unconditional non-repeater field in the same group**. AND/OR groups, cross-group references and conditional chains are not implemented.
- Supported operators: **Is equal to**, **Is not equal to**, **Is empty**, **Is not empty**.
- Equality is case-sensitive string matching, not numeric comparison. Use stored choice keys (not labels), `1` / `0` for True / False, and attachment IDs for media.
- Checkbox/Gallery equality tests whether the comparison key/image ID is selected; inequality tests absence. An unchecked True / False field counts as empty; number/text `0` does not.
- Rules reference stable field keys, so renaming/reordering the source does not break them. Removing a referenced source requires updating/disabling its rules before the schema can save. Self-references, cycles and chains are rejected with the previous schema retained.
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
- Whole-Group conditional visibility is supported; Groups cannot be condition sources. Subfield conditions and nested Group/Repeater containers are not supported in this increment.
- Group schemas and subfield keys participate in JSON transfer (the destination needs Group support, version 0.8.0+). Image/file defaults reset during transfer as with other fields. Errors/recovery are shown at the Group boundary, not individually per subfield.
- `alify_get_field()` also returns the associative object. `alify_get_group()` returns an empty array before the first save; escape subfield output for its context. Renaming subfields does not migrate existing data.

## Field-group JSON import / export

Open **Alify Custom Field → JSON Import / Export** (requires `manage_options`). Select one configured group and download JSON; on the destination site upload that file or paste its JSON, then choose **Import as draft**. Review the new draft before publishing.

- Versioned Alify format (`format: "alify-custom-field"`, integer `version: 1`), one group per document, maximum **1 MiB**. Not an ACF JSON importer.
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
- No nested Groups/repeaters, Flexible Content, public REST value-read API, programmatic registration, revision restoration or ACF data/API compatibility yet. The editor-only REST write bridge is described below.
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

## Verification

Automated checks for 0.8.0:

```sh
npm ci
npm test       # 24 jsdom tests for builder, media, conditions, repeaters and feedback
npm run check # JavaScript syntax
php tests/php-unit.php # 179 assertions with isolated WordPress stubs
```

Isolated PHP contracts passed in PHP.wasm 7.4.33 and 8.3.33; JavaScript tests use jsdom and a mocked media frame. **Real WordPress backend integration** also passed with 141 assertions per target:

| WordPress | PHP | Database |
|---|---|---|
| 6.2 | PHP.wasm 7.4.33 | SQLite / WP_SQLite_DB |
| 6.8.3 | PHP.wasm 8.3.33 | SQLite / WP_SQLite_DB |

Both integration runs had clean WordPress debug logs. These are explicit test targets, not a latest-version claim or a recommendation to deploy old versions. The suite exercises actual activation, hooks, capabilities, nonces, uploads, metadata, options, rendering, feedback, Classic publication/scheduling, core REST validation/creation/authentication and autosaves. This backend suite is separate from the browser suite.

```sh
# Requires Git/Bash and a native PHP executable with SQLite extensions.
bash tests/integration/run.sh
WP_VERSION=6.2 bash tests/integration/run.sh
```

See [integration setup, coverage and limitations](tests/integration/README.md). The runner creates an explicitly marked disposable database under `$HOME/.cache`, never a production database. A GitHub Actions matrix is included; its remote execution is separate from these local results.

**Earlier local browser baseline (0.6.2):** 14 end-to-end assertions passed locally on WordPress 6.8.3 / PHP.wasm 8.3.33, including Classic rejection/redirect/correction, Gutenberg REST rejection/correction, native media-modal gallery selection, gallery/repeater ordering after save/reload, and raw REST publication protection. Repeatable browser seed/cleanup scripts and native-PHP CI steps cover both matrix targets; see the integration guide.

**Still pending:** MariaDB, wider MySQL versions, options-page HTTP save/redirect flows, repeater image-subfield media interactions, responsive/accessibility audits, multisite and wider third-party compatibility.

The CI matrix now includes isolated MySQL 8.0 services alongside SQLite for both PHP/WordPress pairs, running the same backend and browser suites. The MySQL runner requires explicit disposable-database opt-in and loopback credentials; see the integration guide. All four native-PHP targets passed 141 backend and 27 Chromium assertions in [CI run 40668605180](https://github.com/alifywork/fieldsPlugin/actions/runs/40668605180), with clean debug-log checks. The browser checks include actual JSON transfer plus Group text/checkbox/image persistence in Classic and Group persistence after Gutenberg save/reload. These remote results are separate from the local sandbox SQLite checks.

### Manual staging checklist

1. Activate with `WP_DEBUG` enabled. Confirm no PHP notices or errors in classic and block editors.
2. As an administrator, create a group with all fourteen types and a Posts location. Publish it; check field counts and location in the list.
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
