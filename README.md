# Alify Custom Field

An independent WordPress custom-fields plugin with a modern, WordPress-native field-group editor. **Version 0.2.0 — media and validation release**, not an ACF drop-in replacement.

## Requirements and installation

- WordPress 6.2+; PHP 7.4+ (a currently supported PHP version is recommended).
- Copy this repository folder to `wp-content/plugins/alify-custom-field/`, or ZIP its contents inside an `alify-custom-field` folder and upload through **Plugins → Add New → Upload Plugin**.
- Activate **Alify Custom Field**.
- Open **Alify Custom Field → Add Field Group**, enter a title, add fields, choose a post type, and **Publish**.
- Edit a post of that type, fill in its fields, and save the post.

## Included

- Administrator-only field-group management using WordPress's native publish/draft/trash workflow.
- Text, Text Area, Number, Email, URL, Select, Checkbox, True / False, Date, Image and File.
- Native WordPress media picker, image thumbnails, file labels and remove controls.
- Attachment IDs validated by type and attachment edit permissions on selection.
- Atomic schema validation and duplicate-name protection across active/draft groups on the same post type.
- Per-user, per-post save-error notices that name rejected fields and preserve prior values.
- Labels, stable field keys, field names, instructions, defaults, and required settings.
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

## Current limits

- Field names must start with a lowercase letter and contain only lowercase letters, numbers and underscores. Names are reserved across published, draft, pending, private and scheduled groups matching the same post type. Conflicts reject the entire configuration change, preserving its previous fields and location. Trashed groups do not reserve names; avoid restoring legacy conflicting groups without reviewing them.
- Renaming a field does not migrate existing values. Removing fields/groups or deactivating the plugin does not delete saved content. No uninstall deletion is performed.
- Required scalar fields use browser validation. Required checkbox groups are checked on the server. Invalid/empty required submissions retain the previous stored value rather than blocking publication. Invalid nonempty optional scalar submissions also retain previous values. Save-error notices list affected fields on the next post-edit page load (reload after a block-editor save if needed). Other valid fields still save. Rejected input is not repopulated.
- Checkbox defaults are not supported. For True / False defaults use `1` or `0`. Setting a True / False field as required means it must be enabled.
- No Repeater, Gallery, Flexible Content, conditional logic, Options Pages, import/export, REST integration, programmatic registration, revision restoration or ACF data/API compatibility yet.
- UI strings are English in this initial release; full localization is pending.

## Verification

Automated checks for 0.2.0:

```sh
npm ci
npm test       # Six jsdom tests for builder interactions and media behavior
npm run check # JavaScript syntax
php tests/php-unit.php # 26 assertions with isolated WordPress stubs
```

PHP contract tests were executed using a PHP 8.5 WebAssembly CLI runtime; PHP files were also parsed against PHP 7.4 grammar. Tests use stubs for WordPress and the media frame, not a live WordPress database or browser. Activation, real media uploads, native sorting and editor integration still require staging checks below.

### Manual staging checklist

1. Activate with `WP_DEBUG` enabled. Confirm no PHP notices or errors in classic and block editors.
2. As an administrator, create a group with all eleven types and a Posts location. Publish it; check field counts and location in the list.
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
