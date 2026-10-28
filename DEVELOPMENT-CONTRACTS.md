# Alify Custom Field — v0.34 development contracts

This bundle implements the remaining engineering scopes on the 77-item roadmap. It does **not** promise unlimited nesting, every vendor integration, interchangeable ACF APIs, hosted support, or live Google certification. Optional adapters are off until explicitly configured in trusted PHP. Never put real browser keys, credentials or private content in source control.

## 1. Bounded composition and editor scalability

Group, Repeater and Flexible Content can contain each other, up to **four container levels** including the root. Presentation-only Tab/Accordion/Message fields remain root-only. Basic children retain their own settings/permissions/conditions. Nested configuration is available through the builder's **advanced JSON child/layout editor**, PHP registration and portable schema JSON. This is not a recursive graphical drag-and-drop schema designer.

Canonical values and raw getters retain their existing shapes. `Alify_CF_Publication::stored_payload($field, $canonical)` recursively supplies complete transport markers. Editor row identities are path-specific; cloning/reordering/new rows preserve distinct names/IDs, inert descendant templates and scope boundaries. When explicitly editing a nested existing Repeater/Flexible row in PHP, include the origin MAC of **each original containing row**, not just an ordinal. Omitted origin is accepted for an explicitly unchanged canonical nested child; changed nested content does not guess its previous parent. New rows use an empty origin. Group is a fixed slot. Index-specific `alify_update_row` / `alify_delete_row` bind the outer originals before replacement/splicing; explicitly supplied nested origins survive payload repacking. Deeper changed rows still need their own original identities, rather than guessed nested ordinals. Hidden children are retained by the editor and cannot be explicitly changed through PHP/REST, recursively through unconditioned parents too.

Errors retain the full textual nested path, with structured child location metadata; existing UI error jumps identify the outer failing row/child. Recovery/restoration follows current schema/visibility rules, not unconditional historical overwrite.

Reviewed bounds deliberately remain: **200 root fields, 20 children per scope, 10 layouts, 50 rows per individual list**. New shared ceilings: **1 MiB encoded schema/values, 30,000 schema structural nodes, 20,000 value structural nodes**, and a structural depth safety guard. The schema container depth remains four, independent of transport wrapper depth. Limits reject rather than truncate. Rendering additionally estimates named controls, choices and inert template cost across groups and reserves 200 `max_input_vars` slots for WordPress, with a hard 5,000-control estimate ceiling. Oversized groups show a retention notice and omit their complete marker; they cannot silently submit an incomplete group. Client-added rows still require server complete markers and shared validation. Pagination is DOM pagination, not an unlimited/paged-storage API.

Nested composition and the new `woo_order` location require **portable schema v12 / plugin 0.34.0+**. Local JSON accepts v1–v12 within feature gates. Existing unconditioned/non-nested schema keys are unchanged. Recursive password/custom-type exclusion continues to apply to public/revision/API exposure.

## 2. Coordinated bidirectional post references

Register explicit pairs after post types and field groups exist:

```php
add_action('init', function () {
    alify_register_bidirectional_pair(
        'project_people',
        array('type' => 'project', 'field' => 'related_people'),
        array('type' => 'person', 'field' => 'related_projects')
    );
}, 40);
```

Endpoints must resolve to unique **top-level Relationship or multiple Post Object** definitions. CPTs are supported through existing reference filters. Both sides retain their type/status/cardinality/visibility constraints. Endpoints belong to one pair only; at most 20 pairs and 100 related objects per coordinated write. No self links, nested endpoints, single-reference conflict resolution, user/term endpoints or automatic trash/delete cleanup in this contract.

Normal `alify_update_field(s)`, exposed REST/form writes, revision restoration, Classic editor values and Gutenberg save-bridge commits coordinate link/unlink from either side. Canonical existing memberships/order are preserved when unchanged. Every added **and removed** destination needs edit permission; unreadable/uneditable reverse objects are not silently modified. All participating values preflight through the normal API before writes. Source and reverse fields are written under a coordinator ownership lock, fresh snapshots, verification and reverse-order rollback. Recursive API writes during commit reject. Generic raw metadata calls bypass this API and are not supported bidirectional mutations. Separate migration journals refuse paired destinations rather than leave reverse changes outside their rollback boundary.

This is **optimistic coordination, not a database transaction** or a race-proof post-content publication transaction. Failures restore only slots still equal to the coordinator's own writes. Unknown editorial changes are never overwritten. An incomplete rollback returns `alify_rollback_failed` with an opaque backup key; original snapshots remain in a private transient for 30 minutes. `Alify_CF_Bidirectional::backup($key)` requires the creating account and edit permission on every affected post. Copy needed recovery data to secure storage before expiry and reconcile deliberately; the helper does not force restoration. A crash-stranded `alify_cf_pair_lock` requires administrator review before manual removal—never automatically steal a possibly active lock.

## 3. Gutenberg sidebar / datastore

```php
define('ALIFY_CF_SIDEBAR', true);
```

The native **Alify Custom Field** PluginSidebar provides root scalar post-field controls backed by the `alify/fields` WordPress data store. Only `api_read`-exposed supported scalars appear; writes additionally require `api_write`, editable object permission, a loaded/enabled metabox control and current visibility. Supported controls: text, textarea, email, URL, number, date, time, color, toggle and single Select. Structured/multiple/rich/private fields remain in their established editors.

Changes mirror the canonical metabox controls and save through the **normal WordPress Update/Publish bridge**, not a competing immediate-write request. Other metabox edits are reflected when the sidebar renders. The data store holds transient drafts, not a second persistent value store. Existing registered scalar blocks provide their own appropriate inline/Inspector editing; this is not ACF datastore compatibility or arbitrary inline editing of every core block.

## 4. Optional WordPress Abilities / external MCP adapter

```php
define('ALIFY_CF_ABILITIES', true);
```

On WordPress **6.9+**, registers category `alify-fields` and abilities `alify/read-fields`, `alify/write-fields`. Input: positive integer `post_id`; writes also require an object of named editor-payload `values` (1–200 properties). Read/write reuse the existing authenticated `api_read` / `api_write` post endpoint semantics, including password/custom read exclusions, object edit capabilities, hidden-write rejection and coordinated pairs. No schema administration, bulk object discovery, Options access or permission escalation.

Abilities advertise opt-in REST/MCP discovery metadata. An independently installed/configured **WordPress MCP adapter and authenticated client/server transport** are still required for MCP. This plugin does not ship an MCP server, authentication service or automatic AI content sharing. An MCP discovery flag does not make field values anonymously readable. On older WordPress the optional registration is a no-op.

## 5. Explicit theme adapter: Timber 2.x / Twig 3

```php
define('ALIFY_CF_TIMBER', true);
```

With Timber's Twig environment available, use:

```twig
{{ 'public_headline'|alify_public_field(post_id) }}
```

Only explicitly `public_output`-enabled scalar fields on publicly viewable, non-password-protected posts are returned. The adapter emits escaped plain text with an HTML-safe declaration for the **already escaped** string, including when a theme disables Twig autoescaping. It never marks raw field HTML safe. User/WYSIWYG/password/custom/container output remains ineligible under the shared public policy. Raw `alify_get_*` theme getters are unchanged and still require contextual escaping.

Supported third-party adapter here: **Timber**, not Elementor/Divi/Bricks or generic ACF dynamic tags. Existing shortcodes and PHP helpers remain available separately.

## 6. WooCommerce orders: CRUD-only HPOS contract

```php
define('ALIFY_CF_WOOCOMMERCE', true);
```

When WooCommerce is loaded, **WooCommerce orders (CRUD / HPOS)** becomes the `woo_order` field-group location. It has a native legacy/HPOS order-admin metabox, nonce/completeness checks, normal fields/conditions and validation feedback. Existing WooCommerce product post-type fields continue to use the supported ordinary post editor/API; product variations, checkout/customer forms, subscriptions/refunds and dedicated product-block-editor controls are not added by this adapter.

```php
$note = alify_get_order_field('delivery_note', $order_id);
$result = alify_update_order_fields(array('delivery_note' => 'Ring twice'), $order_id);
```

Order IDs are positive integers. Both reads and writes require a **manage_woocommerce** account. Only existing `shop_order` objects are supported. Storage exclusively uses `wc_get_order`, WooCommerce metadata methods and `save()`; it never writes a shadow `wp_postmeta` order field. It works with legacy WooCommerce order storage and HPOS via the same CRUD contract. Explicit API writes reject hidden changes; editor submissions retain them. All named values validate before save, storage is re-read through CRUD and failures attempt snapshot restoration. A failed restoration is reported, not concealed. WooCommerce saves/hooks are not a SQL transaction and arbitrary concurrent order edits are not claimed race-proof.

Legacy Alify groups attached directly to `shop_order` are **not automatically migrated/relabelled**. Audit those definitions and their WooCommerce migration/synchronization before moving to `woo_order`. The adapter does not declare HPOS compatibility while published legacy `shop_order` groups remain. Existing raw post getters must not be used as order getters on HPOS. Generic WooCommerce order REST metadata responses strip Alify keys, and arbitrary `meta_data` writes/ID-based renames to those slots reject; use the dedicated validated PHP/editor API instead. No automatic changes to pricing, stock, payment or WooCommerce core metadata.

## 7. Polylang definition presentation and independent translations

```php
define('ALIFY_CF_POLYLANG', true);
alify_register_translation_policy('headline', 'translate');
alify_register_translation_policy('sku_caption', 'copy');
alify_register_translation_policy('internal_note', 'ignore');
```

With Polylang active, an administrator visit registers field labels, instructions, choice labels and Flexible layout labels as Polylang strings. The shared metabox renderer translates presentation copies only. Names, keys, choice keys, defaults, conditions, saved definitions and revision schema hashes are never translated.

Post values stay independent per translated object:

```php
$french = alify_get_translated_field('headline', $source_post_id, 'fr');
$result = alify_copy_translation_fields($source_post_id, $linked_target_id, array('sku_caption'));
```

The getter follows Polylang's explicit target language, never falls back to source values, respects explicit `ignore`, and remains a **raw trusted-theme getter** (not a public-output authorization wrapper). Escape output and enforce your surrounding application access policy as with raw getters. Missing targets/providers return null. Explicit copying requires edit permission on both linked translations of the same type, matching definitions and a `copy` policy for every named field. Only supported single scalars copy, through the shared atomic API. No reference-ID remapping, password/custom/container copying, automatic background synchronization or machine translation. Ordinary `translate` fields are edited independently; default copy policy is deny. Polylang's generic custom-meta copy list has Alify keys removed to prevent bypassing these rules.

Supported multilingual provider here: **Polylang**, not WPML/TranslatePress compatibility by assertion.

## 8. Optional managed releases and local support workflow

```php
define('ALIFY_CF_GITHUB_UPDATES', true);
```

The adapter checks the explicitly trusted public repository **alifywork/fieldsPlugin**, not a configurable arbitrary manifest URL. It offers only stable `vX.Y.Z`/`X.Y.Z` releases with **both** exact assets `alify-custom-field.zip` and `alify-custom-field.zip.sha256` under that release's GitHub path. Source-code archives alone are not installable offers. Publisher archives must contain the proper installable plugin folder and honor advertised WordPress/PHP requirements; this does not create/publish a release automatically.

Opt-out means no managed update checks. Checks are cached six hours (15 minutes after unavailable/error). Normal WordPress update permission/settings control installation. The download filter freshly retrieves the SHA-256 file, verifies the downloaded temporary archive and fails closed on mismatch, stale/unavailable offered release, or another filter attempting to replace the verified package. A checksum served by the same trusted publisher is integrity checking over HTTPS, **not independent code signing** or protection from a compromised publisher. No license server, account activation, telemetry, automatic support uploads or invented commercial promises.

For a manual installation/update: back up the site and field definitions, review the changelog and supported scopes, verify the release/checksum, install the plugin folder and retain the previous archive/database backup for rollback. WordPress updates do not migrate field values automatically.

`alify_support_snapshot()` is an explicit administrator-only local diagnostic helper containing plugin/WordPress/PHP versions, multisite flag, composition depth and enabled-adapter flags. It contains no keys, values, usernames, paths or customer/order identifiers and sends nothing anywhere. Share only reviewed diagnostics and a redacted reproducible issue through the repository's issue workflow. Pricing, hosted distribution and vendor support commitments are product decisions outside this implementation.

## Verification boundaries

The implementation has focused PHP/JavaScript contracts and real WordPress/Chromium flows. Provider checks use pinned real WooCommerce (authoritative MySQL HPOS), Polylang and Timber packages plus the native WordPress 6.9 Abilities registry. Managed release HTTP/archive responses are deterministic integrity fixtures, not a production update/release publication. Google remains a controlled SDK fixture, not live billing/service certification. Final run results are recorded in README / CHANGELOG / roadmap after verification.
