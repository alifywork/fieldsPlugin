# Alify Custom Field

An independent WordPress custom-fields plugin with a modern, WordPress-native field-group editor. **Version 0.4.0 — repeater release**, not an ACF drop-in replacement.

## Requirements and installation

- WordPress 6.2+; PHP 7.4+ (a currently supported PHP version is recommended).
- Copy this repository folder to `wp-content/plugins/alify-custom-field/`, or ZIP its contents inside an `alify-custom-field` folder and upload through **Plugins → Add New → Upload Plugin**.
- Activate **Alify Custom Field**.
- Open **Alify Custom Field → Add Field Group**, enter a title, add fields, choose a post type, and **Publish**.
- Edit a post of that type, fill in its fields, and save the post.

## Included

- Administrator-only field-group management using WordPress's native publish/draft/trash workflow.
- Text, Text Area, Number, Email, URL, Select, Checkbox, True / False, Date, Image, File and Repeater.
- Single-level repeaters with a subfield builder, row add/remove/move controls and atomic value validation.
- Native WordPress media picker, image thumbnails, file labels and remove controls.
- Attachment IDs validated by type and attachment edit permissions on selection.
- Atomic schema validation and duplicate-name protection across active/draft groups on the same post type.
- Per-user, per-post save-error notices that name rejected fields and preserve prior values.
- Labels, stable field keys, field names, instructions, defaults, and required settings.
- Conditional show/hide rules with live editor updates and server-side enforcement.
- Expandable cards, drag sorting, keyboard-accessible move buttons, duplication and deletion confirmation.
- One post-type location rule per group, including custom post types with an admin UI.
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

Both types return an **attachment ID** (integer), not a URL or an ACF-style array. An empty saved selection is `''`. Media defaults can be supplied as an existing attachment ID. The WordPress media picker is available to users with `upload_files`; selecting a different attachment additionally requires permission to edit that attachment. Removing an optional selection does not delete the attachment.

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

## Repeater fields

Select **Repeater** as the field type, then add 1–20 subfields in its card. Each subfield has a local name, label, type, instructions, choices, default and required setting. All eleven basic types (including Image/File) work as subfields. Add, remove and move subfields with the builder buttons.

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
- If any row is invalid, **all previous rows for that repeater are retained** and the existing save-error notice identifies the repeater. Other valid fields may still save. Row-level error highlighting and rejected-input repopulation are not implemented.
- Completion markers detect missing/truncated repeater submissions (including common PHP `max_input_vars` truncation) and preserve previous rows. Your host's input limits may be reached before the plugin's row cap; test realistic forms and adjust limits or reduce fields/rows. Explicitly removing every optional row and saving clears its value to `[]`.
- Subfield names need only be unique within their repeater. Renaming/removing subfields does **not** migrate content. On the next post save, unknown/removed subfield values are dropped and renamed fields receive the currently submitted value. Back up content before structural changes. Changing an existing scalar field to Repeater is not a data migration.

### Repeater staging checks

1. Create a `team_members` repeater with required `name`, optional `email`, checkbox `roles`, and image `photo`. Add three rows, save/reload, reorder, remove and save again. Check template output order.
2. Test all eleven subfield types, Unicode, quotes and backslashes. Confirm choice arrays, unchecked toggles and cleared media round-trip correctly.
3. Try invalid email, a PDF in an Image subfield and a missing required name. Verify the entire previous repeater is retained with a notice.
4. Remove every optional row; confirm it stays empty after reload. Mark the repeater required and try saving zero rows.
5. Add a top-level toggle condition to the repeater; hide it, save, reveal it and verify rows survive and hidden required subfields do not block saving.
6. Test the row cap, large forms near `max_input_vars`, and both classic/block editors. Verify media selection works inside newly added rows. Test keyboard moves and narrow screens.

## Conditional Logic

Expand a field card and enable **Conditional Logic**, then select a source field, comparison and value. Example: a True / False field named `show_cta`, followed by a required `cta_text` field shown when `show_cta` **Is equal to** `1`.

- Each field supports **one rule**, referencing an **unconditional non-repeater field in the same group**. AND/OR groups, cross-group references and conditional chains are not implemented.
- Supported operators: **Is equal to**, **Is not equal to**, **Is empty**, **Is not empty**.
- Equality is case-sensitive string matching, not numeric comparison. Use stored choice keys (not labels), `1` / `0` for True / False, and attachment IDs for media.
- Checkbox equality tests whether the comparison key is selected; inequality tests absence. An unchecked True / False field counts as empty; number/text `0` does not.
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

## Current limits

- Field names must start with a lowercase letter and contain only lowercase letters, numbers and underscores. Names are reserved across published, draft, pending, private and scheduled groups matching the same post type. Conflicts reject the entire configuration change, preserving its previous fields and location. Trashed groups do not reserve names; avoid restoring legacy conflicting groups without reviewing them.
- Renaming a field does not migrate existing values. Removing fields/groups or deactivating the plugin does not delete saved content. No uninstall deletion is performed.
- Required scalar fields use browser validation. Required checkbox groups are checked on the server. Invalid/empty required submissions retain the previous stored value rather than blocking publication. Invalid nonempty optional scalar submissions also retain previous values. Save-error notices list affected fields on the next post-edit page load (reload after a block-editor save if needed). Other valid fields still save. Rejected input is not repopulated.
- Checkbox defaults are not supported. For True / False defaults use `1` or `0`. Setting a True / False field as required means it must be enabled.
- No nested repeaters, Gallery, Flexible Content, Options Pages, import/export, REST integration, programmatic registration, revision restoration or ACF data/API compatibility yet.
- UI strings are English in this initial release; full localization is pending.

## Verification

Automated checks for 0.4.0:

```sh
npm ci
npm test       # 13 jsdom tests for builder, media, conditions and repeaters
npm run check # JavaScript syntax
php tests/php-unit.php # 76 assertions with isolated WordPress stubs
```

PHP contract tests were executed using a PHP 8.5 WebAssembly CLI runtime; PHP files were also parsed against PHP 7.4 grammar. Tests use stubs for WordPress and the media frame, not a live WordPress database or browser. Activation, real media uploads, native sorting and editor integration still require staging checks below.

### Manual staging checklist

1. Activate with `WP_DEBUG` enabled. Confirm no PHP notices or errors in classic and block editors.
2. As an administrator, create a group with all twelve types and a Posts location. Publish it; check field counts and location in the list.
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
