# Alify Custom Field

An independent WordPress custom-fields plugin with a modern, WordPress-native field-group editor. **Version 0.23.0 — temporal display and return formats**, not an ACF drop-in replacement.

## Requirements and installation

- WordPress 6.2+; PHP 7.4+ (a currently supported PHP version is recommended).
- Copy this repository folder to `wp-content/plugins/alify-custom-field/`, or ZIP its contents inside an `alify-custom-field` folder and upload through **Plugins → Add New → Upload Plugin**.
- Activate **Alify Custom Field**.
- Open **Alify Custom Field → Add Field Group**, enter a title, add fields, choose a post type, and **Publish**.
- Edit a post of that type, fill in its fields, and save the post.

## Included

- Administrator-only field-group management using WordPress's native publish/draft/trash workflow.
- Text, Password, Text Area, WYSIWYG Editor, Number, Range, Email, URL, Link, Post Object, Relationship, Select, Radio Button, Button Group, Checkbox, True / False, Date, Date Time, Color Picker, Time, Image, File, Repeater, Gallery, Group and Flexible Content.
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

## Development queue

See [DEVELOPMENT-ROADMAP.md](DEVELOPMENT-ROADMAP.md) for the sequential missing/partial-feature backlog from the ACF comparison. Current completed increment: **Date/Time display and return formats**. Next: **Richer choice-field settings**. Features are delivered individually with their save/validation/integration checks; this is not an ACF compatibility promise.

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

**Relationship (`relationship`)** selects an ordered list of up to **50 unique posts/pages**. It reuses Post Object's permission-filtered search and adds Move earlier/later, Remove reference and Clear all references. Supported on posts, Options Pages and basic Group/Repeater/Flexible subfields.

- Values are stored as ordered integer-ID arrays. Duplicate IDs keep their first occurrence. The raw submitted list is capped at 50 before deduplication; excess items are rejected, never truncated.
- The form/REST payload is `{__complete: '1', ids: [42, 51]}`. An explicit clear sends the marker with an empty/omitted `ids` list. Missing markers, malformed/non-list IDs, unavailable targets or new unreadable references reject the entire list; a container subfield failure retains its whole container. Options saves remain page-atomic.
- Existing saved unreadable references may be retained/reordered or explicitly removed; their titles remain redacted. Deleted targets must be removed/replaced rather than silently discarded.
- Required means at least one reference. This increment has fixed 0–50 limits (required minimum 1), empty defaults, no custom post types, no bidirectional updates, no configurable cardinality and no cross-site ID migration.
- Relationship can be conditionally shown, but is not a condition source in this increment. JSON transfers schemas only with empty defaults; destination plugins need **0.17.0+**.

```php
$posts = alify_get_relationship('related_many', get_the_ID()); // Viewable WP_Post objects, saved order.
foreach ($posts as $related) {
    printf('<a href="%s">%s</a>', esc_url(get_permalink($related)), esc_html(get_the_title($related)));
}
// Raw IDs (including saved unavailable references): alify_get_field('related_many', $post_id).
// Options: alify_get_option('related_many', 'site'). Typed getter filtering never changes stored IDs.
```

## Post Object field

**Post Object (`post_object`)** is a single post/page reference with a searchable selector, type filter, Previous/Next pages and explicit Clear selection. It works on post/Options fields and as a basic Group/Repeater/Flexible subfield. References store canonical IDs, not copied content or titles. WordPress scalar post-meta reads return a string ID; serialized container/Options values retain integer IDs.

- This increment supports **posts and pages only**, with publish/private/draft/pending/future statuses. Attachments, revisions, trash, auto-drafts and custom post types are excluded. For ordered multi-selection, use the Relationship field.
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

**WYSIWYG Editor (`wysiwyg`)** is a top-level post/Options field. Start with the escaped HTML textarea, or choose **Enable Visual/Text editor** for WordPress's native TinyMCE/Quicktags interface: paragraphs/headings, bold/italic, lists, quotes, links and undo/redo. JavaScript/editor initialization is not required to submit the textarea fallback.

- Visual content is synchronized to the real field before normal form submission and Gutenberg's same-request REST payload. Text-tab edits are not overwritten by the inactive Visual editor. Explicit HTML paragraphs are preserved (`wpautop` conversion is disabled). Native editor instances are saved/removed/reinitialized around Classic metabox sorting.
- All submitted HTML and defaults use **`wp_kses_post()`**, including administrator input; `unfiltered_html` does not bypass this field policy. Event handlers, executable URLs and disallowed elements are removed. No shortcodes, embeds, blocks or theme filters are executed by the field.
- Empty paragraphs, NBSP and invisible spacing normalize to `''`. Required means **nonblank text**: formatting-only or media-only content does not satisfy it. Optional blank formatting explicitly clears; invalid non-string payloads are rejected. Existing saved-value retention/publication rules apply.
- Recovery is separately KSES-filtered and escaped into the textarea. A hidden conditional rich field preserves its stored value. WYSIWYG can be a conditional target but **not a source**.
- This increment excludes Group/Repeater/Flexible subfields, media-upload buttons and configurable toolbars. Unsupported rich subfields are rejected, not silently downgraded.
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

- Each field supports **1–10 flat rules** with **ALL (AND)** or **ANY (OR)** matching. Use **Add Rule**, select sources/operators/values, then choose the match relation. Sources must be unconditional scalar/choice/media/gallery fields in the same field group; containers, Relationship, structured Link, WYSIWYG and Password fields cannot be sources. Nested rule groups, cross-group references and conditional chains are not implemented.
- Existing single-rule schemas and JSON exports retain their original shape. Compound schemas use `condition: {relation: "all" | "any", rules: [...]}` and require version 0.10.0+ on the destination site. Each rule still uses `field`, `operator` and `value`.
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

## Flexible Content

Choose **Flexible Content** and define 1–10 uniquely named layouts, each containing 1–20 basic subfields (including media/gallery). Editors choose a layout, add rows and move/remove them. A field holds at most 50 rows in any mixture of its layouts. There are no nested containers or layout/subfield conditions in this increment; whole-field visibility is supported, but Flexible fields cannot be condition sources.

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
- Whole-Group conditional visibility is supported; Groups cannot be condition sources. Subfield conditions and nested Group/Repeater containers are not supported in this increment.
- Group schemas and subfield keys participate in JSON transfer (the destination needs Group support, version 0.8.0+). Image/file defaults reset during transfer as with other fields. Errors/recovery are shown at the Group boundary, not individually per subfield.
- `alify_get_field()` also returns the associative object. `alify_get_group()` returns an empty array before the first save; escape subfield output for its context. Renaming subfields does not migrate existing data.

## Field-group JSON import / export

Open **Alify Custom Field → JSON Import / Export** (requires `manage_options`). Select one configured group and download JSON; on the destination site upload that file or paste its JSON, then choose **Import as draft**. Review the new draft before publishing.

- Versioned Alify format (`format: "alify-custom-field"`, integer `version: 1`, `2`, `3`, `4` or `5`), one group per document, maximum **1 MiB**. Not an ACF JSON importer. Version 2 is emitted for configured repeater bounds so older importers reject the file instead of silently discarding limits; temporal format settings (including subfields) require version 3; richer choice settings/nonempty Checkbox defaults require version 4; Color/media/Link configuration requires version 5; schemas without these features continue to use their existing version.
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
- No nested containers, public REST value-read API, programmatic registration, revision restoration or ACF data/API compatibility yet. The editor-only REST write bridge is described below.
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

## Verification

Automated checks for 0.25.0:

```sh
npm ci
npm test       # 68 jsdom tests for builder, media, conditions, repeaters and feedback
npm run check # JavaScript syntax
php tests/php-unit.php # 726 assertions with isolated WordPress stubs
```

Isolated PHP contracts passed in PHP.wasm 7.4.33 and 8.3.33; JavaScript tests use jsdom and a mocked media frame. **Real WordPress backend integration** also passed with 528 assertions per target:

| WordPress | PHP | Database |
|---|---|---|
| 6.2 | PHP.wasm 7.4.33 | SQLite / WP_SQLite_DB |
| 6.8.3 | PHP.wasm 8.3.33 | SQLite / WP_SQLite_DB |

Both integration runs had clean WordPress debug logs. The previous 0.24.0 choice batch passes all four native-PHP CI targets, including real Chromium and HTTP persistence flows ([run 40707061371](https://github.com/alifywork/fieldsPlugin/actions/runs/40707061371): 494 backend / 161 browser / 24 HTTP assertions per target). These are explicit test targets, not a latest-version claim or a recommendation to deploy old versions. The suite exercises actual activation, hooks, capabilities, nonces, uploads, metadata, options, rendering, feedback, Classic publication/scheduling, core REST validation/creation/authentication and autosaves. This backend suite is separate from the browser suite.

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
