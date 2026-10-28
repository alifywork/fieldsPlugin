# Alify Custom Field — development roadmap

Requested scope: all missing (❌) and partial capabilities identified in the ACF Free / ACF PRO comparison on 2026-10-28. This is a feature backlog, not a claim of ACF compatibility or a promise that similarly named features will have identical behavior. Repeated comparison gaps are consolidated below.

## Working rules

- Develop **large related-feature batches**, in dependency order, without per-feature approval pauses. A row is not complete merely because its UI exists.
- Completion includes schema/builder, applicable post and Options editors, applicable containers, validation/save safety, PHP access, conditions/REST/JSON integration where relevant, regression checks, documentation and push.
- Record actual scope and remaining limitations; larger capabilities may require explicitly tracked increments.
- Necessary checks/fixes are part of development, not separate audit/polish phases.
- No automatic ACF schema/content compatibility, performance superiority or security certification is implied.
- `[ ]` = pending; `[x]` = implemented and verified within its documented scope.

## A. Everyday choice and input fields

1. [x] **Radio Button** — v0.18.0: single configured choice key; defaults, required/optional clearing, post/Options/basic subfields, conditional source/target, REST validation and schema JSON. No “other” choices or custom-choice creation.
2. [x] **Button Group** — v0.19.0: single-choice segments, native keyboard/focus/required behavior, optional clear, defaults, post/Options/basic subfields, conditions, REST and JSON. Custom/multiple choices and advanced return/layout settings remain under item 7.
3. [x] **Range** — v0.20.0: paired slider/numeric input, min/max/step, canonical decimals with exact three-place arithmetic and ±1000000 bounds, defaults, optional clear, required/atomic validation, post/Options/basic containers, conditions, REST and JSON.
4. [x] **Password** — v0.21.0: exact bounded UTF-8 strings, masked input/show-hide/clear, no defaults or conditional sources, post/Options/basic containers, conditional targets, REST, JSON and recovery-transient omission. Plaintext, explicitly not encryption or secure-secret storage.
5. [x] **Combined Date Time** — v0.22.0: native seconds, canonical wall-clock strings, explicit no-conversion/DST contract, strict calendar/default validation, optional clearing, post/Options/basic containers, conditions, REST and JSON.
6. [x] **Date/Time display and return-format configuration** — v0.23.0: bounded presets, English live display previews beside native inputs, opt-in formatted PHP getters and basic container traversal; raw storage/getters/conditions unchanged; JSON v3 gate. Native picker layout remains browser-controlled; arbitrary custom format strings/localization are excluded.
7. [x] **Radio/Select/Checkbox richer settings** — v0.24.0 batch: wrapping horizontal/vertical Radio/Checkbox layouts, validated Checkbox defaults, native multiple/local-search Select, optional clearing, opt-in key/label/record formatted returns; post/Options/basic containers/conditions/REST/JSON v4. Raw keys stay unchanged.
8. [x] **Color configuration** — v0.25.0: suggested bounded palettes, six/eight-digit hex alpha, RGB/opacity synchronization, transparent values, defaults/conditions/REST/JSON v5.
9. [x] **Image/File/Gallery configuration** — v0.25.0: opt-in ID/URL/record returns, exact MIME/local byte restrictions, Gallery cardinality and editor preview size. Existing permission/image validation preserved. Custom dimensions/cropping excluded.
10. [x] **Link configuration** — v0.25.0: validated portable URL/title/target defaults, explicit clear, opt-in object/URL returns, basic container/Options/REST support.

## B. Relational and external fields

11. [x] **Taxonomy selector** — v0.27.0: registered taxonomy filters, assignment-capability search/new selection, ordered single/multiple metadata IDs and opt-in term objects. No actual term assignment/tree UI.
12. [x] **User selector** — v0.27.0: role/current-site filters, list_users-protected display-name search, single/multiple IDs and safe ID/display-name formatted records; never WP_User objects.
13. [x] **Page Link** — v0.27.0: public published post/page/CPT and eligible archive tokens, ordered single/multiple selection, current URL resolution. **Current completed development batch.**
14. [x] **Post Object/Relationship CPT targets** — v0.26.0: registered show_ui types, per-field filters and permission-filtered search/save validation.
15. [x] **Reference status restrictions** — v0.26.0: publish/private/draft/pending/future subsets enforced by search and saves.
16. [x] Reference taxonomy filters and editor filtering controls. — **v0.28.0: bounded taxonomy clauses plus editor narrowing; filters never grant access.**
17. [x] **Multiple Post Object** — v0.26.0: ordered complete ID lists, picker/reordering/clearing, containers/Options/REST/publication/recovery; no scalar/list migration or list conditional sources.
18. [x] **Relationship min/max cardinality** — v0.26.0: shared multiple-reference bounds, maximum 50, optional empty versus Required semantics, no truncation.
19. [x] **Reference return formats** — v0.26.0: opt-in ID/object returns recursively across containers/Options; unavailable or unreadable objects omitted/null; raw IDs unchanged.
20. [ ] Bidirectional relationships — dependency on supported object locations and safe write APIs; avoid recursive writes and partial updates.
21. [x] oEmbed — **v0.29.0:** canonical HTTPS YouTube/Vimeo, fixed provider requests with timeout/size/call limits, success cache and reconstructed validated iframe output. Explicit output API only; no arbitrary-host discovery. HTTP mocks cover provider responses, not live-service certification.
22. [ ] Google Map — **partial v0.29.0:** manual coordinate/address/zoom storage and explicitly keyed Google Maps Embed output; interactive map/search picker remains unimplemented.
23. [x] Icon Picker — supported libraries and safe identifier output. — **v0.28.0: 40 bundled Dashicons, optional allowlist, safe stored identifiers and preview; no arbitrary SVG.**

## C. Content-editor layout and rich text

24. [x] Tab field. — **v0.28.0: root editor sections, keyboard navigation and value-preserving hidden panels.**
25. [x] Accordion field. — **v0.28.0: root collapsible sections with accessible buttons and retained values.**
26. [x] Message/informational field with safe HTML policy. — **v0.28.0: bounded allowlisted informational HTML; no stored field value.**
27. [x] WYSIWYG media buttons and explicit upload permissions. — **v0.28.0: explicit opt-in and upload_files gating; required rich text still needs text.**
28. [x] WYSIWYG toolbar/editor configuration. — **v0.28.0: full/basic toolbar and both/visual/text mode presets.**
29. [x] WYSIWYG in dynamic container subfields, including lifecycle cleanup and cloned editors. — **v0.28.0: basic Group/Repeater/Flexible subfields; pristine templates and native move/remove lifecycle.**

## D. Location rules and content-model registration

30. [x] Taxonomy-term edit locations and storage/getters. — **v0.29.0:** registered `term:<taxonomy>` groups, native edit hooks and authorized `term:ID` APIs/REST.
31. [x] User-profile locations and storage/getters. — **v0.29.0:** native existing-profile fields and authorized `user:ID` metadata APIs/REST; no user creation.
32. [x] Attachment edit locations. — **v0.29.0:** attachment metaboxes and native `edit_attachment` persistence with ordinary post getters/nonces; not attachment-modal fields.
33. [x] Comment locations. — **v0.29.0:** native existing-comment metaboxes, dedicated object editor and authorized `comment:ID` metadata APIs; backend adapter coverage.
34. [x] Navigation-menu/menu-item locations. — **v0.29.0:** dedicated menu editor, native menu-item fields and authorized metadata APIs. Native menu UI requires a theme supporting classic menus; plugin does not change theme support.
35. [x] Widget locations with clearly defined supported widget editors. — **v0.29.0:** existing classic instances only, native update/filter integration, nonce/capability checks and active-schema raw getter. Block widgets/Customizer unsupported; real WordPress backend adapter coverage only.
36. [x] Specific post/page, template, taxonomy and other location predicates. — **v0.29.0:** post_id/status/template/term/current_user_role narrowing; no permission escalation.
37. [x] Multiple location-rule groups with AND/OR evaluation and conflict handling. — **v0.29.0:** OR-of-AND JSON, maximum 10 groups/10 predicates per group/50 total; shared schema/location collision checks and fail-closed unavailable rules.
38. [x] Public custom location-rule extension API. — **v0.29.0:** unique trusted `x_` matcher and optional validator; rendering/read/write/form/revision enforcement where applicable.
39. [x] Options-page creation/configuration UI, beyond built-in Site Settings and PHP registration. — **v0.28.0 Content Models UI: managed Options definitions, bounded portable JSON and dependency-aware unregistering.**
40. [x] CPT registration UI and portable configuration. — **v0.28.0 Content Models UI: bounded managed definitions, standard WP capabilities and portable new-only JSON import/export.**
41. [x] Taxonomy registration UI and portable configuration. — **v0.28.0 Content Models UI: target types, public/REST/hierarchical flags, portable JSON and term/dependency safeguards.**

## E. Structured content

42. [ ] Nested Group support with bounded recursive schema/value handling.
43. [ ] Nested Repeater and Flexible Content composition; preserve atomic validation and error paths.
44. [x] Repeater/Flexible drag sorting, with keyboard alternatives. — **v0.28.0: native jQuery UI handles plus existing keyboard move buttons; rich editor teardown/recreation.**
45. [x] Content-row duplication with fresh names/IDs and safe editor state. — **v0.28.0: pristine-template clones with fresh IDs/names, synchronized HTML and ordered selected IDs.**
46. [x] Container presentation settings and collapsible row labels. — **v0.28.0: initially collapsed rows and safe direct-scalar labels; no layout/display-mode redesign.**
47. [x] Repeater pagination/large-content workflow; do not silently discard unloaded rows. — **v0.29.0:** page_size 0 or 1–50, all DOM rows remain submitted, invalid controls reveal all rows. Presentation only, not lazy loading or increased bounds.
48. [x] Flexible layout-specific limits, configuration and richer layout-management controls. — **v0.29.0:** per-layout min/max (0–50), disabled count-growth policy and corresponding picker/Add/Clone/Remove controls; existing disabled rows stay editable. No layout rename/content migration.
49. [ ] Clone/reusable field definitions with collision and recursion protection.
50. [ ] Review fixed field/subfield/layout/row bounds after scalable editing support; no unbounded payload promise.

## F. Conditional logic

51. [x] Nested/mixed AND/OR rule groups. — **v0.28.0: mixed ALL/ANY advanced JSON, at most three group levels and ten total leaves; no conditional sources/chains.**
52. [x] Contains/pattern and other appropriate type-specific operators with bounded evaluation. — **v0.29.0:** literal contains/prefix/suffix plus anchored case-sensitive ASCII `glob` (only * special, at most four stars, 256-character pattern/4,096-character source); bounded literal searches in PHP/JS, no regex evaluation. Unicode still works for the existing literal operators; glob rejects non-ASCII/over-budget values. Arbitrary regex/PCRE is deliberately excluded.
53. [ ] Subfield/container-scoped conditions and correct dynamic-row context.
54. [x] Conditional chains with cycle detection and deterministic server/client parity. — **v0.30.0:** same-group root siblings, whole-graph cycles/missing/self references rejected, at most 16 links, order-independent PHP/JS evaluation; hidden sources never match any operator. No cross-group or row-scoped conditions.
55. [x] Richer eligible condition sources. — **v0.30.0:** Relationship, multiple Post Object, Taxonomy, User and Page Link selected canonical token lists/scalars; equality is membership, never labels/search/complete markers. Composite Link/Map/container/custom callback sources remain excluded.

## G. Developer APIs, portability and migrations

56. [x] Public PHP field-group/field registration API. — **v0.28.0: trusted PHP groups/fields, stable generated keys and shared schema/collision validation.**
57. [x] General field/Options write APIs with validation and authorization contracts. — **v0.28.0: edit_post/manage_options authorization, editor payloads and prevalidated writes; best-effort post rollback, not transactions.**
58. [x] Row/subfield iteration and mutation helpers. — **v0.28.0: explicit row-context getter/generator and zero-based post row append/update/delete helpers; full-container validation.**
59. [x] Documented lifecycle hooks, filters and custom field-type extension API. — **v0.28.0: validated registration filter, registration/API-write actions and trusted scalar custom-type callbacks.**
60. [x] Automatic Local JSON save/load and synchronization UI. — **v0.29.0:** opt-in trusted directory, automatic atomic file exports/runtime definitions, deployment ownership/hash guards, database sync with attempted snapshot rollback, draft/trash precedence and tombstones. Up to 100 files/1 MiB each; not ACF JSON.
61. [x] PHP configuration export and version-controlled deployment workflow. — **v0.28.0: administrator PHP configuration export, reviewed manual version-controlled deployment; no automatic sync.**
62. [ ] Explicit rename/type-change/container-layout migration tools; schema transfer is not content migration.
63. [ ] ACF schema importer with declared supported types/settings and rejection reporting.
64. [ ] Separate ACF content migration, including reference/media remapping and rollback planning; not automatic drop-in compatibility.

## H. REST, revisions and frontend editing

65. [x] General opt-in REST read/schema API with field-level exposure decisions. — **v0.28.0: authenticated opt-in raw read/minimal schema routes for posts and registered Options; Password/custom-containing reads blocked.**
66. [x] General authenticated REST write API across supported object locations, beyond the current core-post save bridge. — **v0.28.0:** authenticated opt-in post/Options writes; **v0.29.0:** explicit authorized term/user/comment/menu/menu-item object routes. Classic widget storage remains a separate native instance adapter.
67. [x] Custom-field revision capture/restore and autosave previews with permissions and validation. — **v0.29.0:** private schema-hashed post snapshots, authorized validated restore, own-user pending autosave getter and parent-metadata retention. Password/custom-containing fields omitted; no all-or-nothing core-content restore guarantee.
68. [x] Frontend forms API with CSRF protection, object authorization, uploads and safe validation. — **v0.29.0:** authenticated existing-post forms, complete-group/post nonce/capability enforcement, shared validation and safe redirects; native media assets reuse WordPress upload capabilities/handling, no custom upload endpoint. No anonymous/new-post flow or client draft-recovery UI.
69. [x] Optional safe shortcode/output integration with explicit exposure/escaping policy. — **v0.28.0: explicitly exposed public scalar shortcode, escaped output; no protected/private/rich/secret values.**
70. [x] Custom blocks framework — **v0.30.0:** trusted PHP registration, native API-v2 public scalar attribute schemas, Inspector controls, native authenticated server-side preview, dynamic post-safe rendering and core content persistence. Text/textarea/integer/toggle/select only; no private field reuse, container controls, inline canvas editor or ACF block compatibility.
71. [ ] Native Gutenberg sidebar/datastore integration and appropriate inline editing.
72. [x] Core Block Bindings integration. — **v0.28.0: version-gated alify/field native source with public scalar policy; no Attributes picker/datastore/live-preview claim.**
73. [ ] Optional WordPress Abilities/MCP integration with explicit opt-in and permissions.

## I. Dedicated integration development

74. [ ] Builder/theme dynamic-data adapters: choose and declare supported integrations individually; ACF APIs are not interchangeable with `alify_*` APIs.
75. [ ] WooCommerce-specific storage/editor integration, including an explicit HPOS contract where applicable.
76. [ ] Multilingual field-definition/value translation integration.
77. [ ] Optional managed update/distribution and support workflow; pricing and vendor support are product decisions, not missing field types.

## Not missing features: evidence and deployment checks

These were comparison uncertainties, **not ❌ feature implementations**. Keep them separate from this development queue and do not label them complete without evidence:

- Latest WordPress/PHP versions, MariaDB, multisite, mobile/accessibility breadth and third-party compatibility beyond the current matrix.
- Same-dataset performance/scalability benchmarks; small code size is not proof of speed.
- Independent security audit; passing feature checks is not certification.
- Published support service, marketplace maturity, documentation breadth and recurring maintenance cost.

## Delivery log

- **v0.18.0 — Radio Button:** first sequential feature. See README/CHANGELOG and the feature tests for the exact supported scope. Verified in [CI run 40693944722](https://github.com/alifywork/fieldsPlugin/actions/runs/40693944722): all four native targets pass (325 backend / 101 browser / 24 HTTP persistence checks), plus 385 isolated PHP assertions and 48 JS tests. The next item at that release was Button Group.
- **v0.19.0 — Button Group:** native radio-backed single-choice segments with optional clearing and the shared integration/save-safety contract. Local checks: 406 isolated PHP assertions, 51 JS tests, 349 backend assertions on both existing PHP-WASM/SQLite targets. Verified in [CI run 40694812698](https://github.com/alifywork/fieldsPlugin/actions/runs/40694812698): all four native targets pass (349 backend / 112 browser / 24 HTTP persistence checks). The next item at that release was Range.
- **v0.20.0 — Range:** bounded decimal slider/numeric input with exact step validation and the shared integration/save-safety contract. Local checks: 474 isolated PHP assertions, 54 JS tests, 374 backend assertions on both existing PHP-WASM/SQLite targets. Verified in [CI run 40699760663](https://github.com/alifywork/fieldsPlugin/actions/runs/40699760663): all four native targets pass (374 backend / 122 browser / 24 HTTP persistence checks). The next item at that release was Password.
- **v0.21.0 — Password:** UI masking and show/hide/clear, exact bounded UTF-8 storage, shared validation/integrations, no defaults/condition sources and no Password-containing field/container values in recovery transients. Local checks: 519 PHP assertions, 57 JS tests, 408 backend assertions on both existing PHP-WASM/SQLite targets. Verified in [CI run 40702653613](https://github.com/alifywork/fieldsPlugin/actions/runs/40702653613): all four native targets pass (408 backend / 134 browser / 24 HTTP persistence checks). The next item at that release was Combined Date Time.
- **v0.22.0 — Combined Date Time:** second-precision floating wall-clock values, strict calendar validation and shared integrations/save safety. Local checks: 572 PHP assertions, 60 JS tests, 433 backend assertions on both existing PHP-WASM/SQLite targets. Verified in [CI run 40703913791](https://github.com/alifywork/fieldsPlugin/actions/runs/40703913791): all four native targets pass (433 backend / 141 browser / 24 HTTP persistence checks). The next item at that release was Date/Time display and return formats.
- **v0.23.0 — Date/Time formats:** validated live display previews, opt-in return formats/container getters, canonical storage compatibility and JSON v3 gating. Local checks: 650 PHP assertions, 62 JS tests, 460 backend assertions on both existing PHP-WASM/SQLite targets. Verified in [CI run 40705342031](https://github.com/alifywork/fieldsPlugin/actions/runs/40705342031): all four native targets pass (460 backend / 149 browser / 24 HTTP persistence checks). Next: **Richer choice-field settings**. Subsequent work follows the numbered queue unless a dependency requires reordering, which must be recorded here.

- **v0.24.0 — Rich choice settings batch:** six related choice capabilities delivered together, one combined end-of-batch check/CI cycle. Local: 689 PHP assertions on both runtimes, 65 JS tests, 494 backend assertions on both existing SQLite targets. Verified in [CI run 40707061371](https://github.com/alifywork/fieldsPlugin/actions/runs/40707061371): all four native targets green (494 backend / 161 browser / 24 HTTP persistence checks). Next queue item: Color configuration; continue related-feature batches.

- **v0.25.0 — Color/media/Link configuration batch:** items 8–10 developed together, including defaults, server validation, formatted getters and JSON v5. Local: 726 PHP / 68 JS / 528 backend assertions on both existing SQLite targets. Verified in [CI run 40708689961](https://github.com/alifywork/fieldsPlugin/actions/runs/40708689961): all four native targets green (528 backend / 168 browser / 24 HTTP persistence checks). Roadmap: 10 complete, 67 pending; this is not a claim that the full backlog is finished.

- **v0.26.0 — Shared reference-engine batch:** complete items 14, 15, 17, 18 and 19 together before new selectors (11–13). Dependency ordering: expand the existing shared reference engine first rather than duplicate target/filter/permission infrastructure in new selectors. Item 16 taxonomy filtering remains pending. Local 755 PHP / 70 JS / 558 backend on both SQLite targets; verified in [CI run 40709951548](https://github.com/alifywork/fieldsPlugin/actions/runs/40709951548): all four native targets pass (558 backend / 175 browser / 24 HTTP persistence checks). Roadmap now 15 complete / 62 pending.

- **v0.27.0 — New selector batch:** items 11–13 complete on the shared selector/search infrastructure. Local: 783 PHP / 74 JS / 600 backend on both SQLite targets; verified in [CI run 40711904295](https://github.com/alifywork/fieldsPlugin/actions/runs/40711904295): all four native targets pass (600 backend / 187 browser / 24 HTTP persistence checks). Roadmap: 18 complete / 59 pending. Next unresolved relational item is 16 (taxonomy filtering on references), followed by 20+ and editor-layout batches; no claim that the remaining backlog is complete.

- **v0.28.0 — Large editor/developer bundle:** 24 previously open items implemented together (16, 23–29, 39–41, 44–46, 51, 56–59, 61, 65–66, 69, 72), within the explicit scopes above. Item 52 is partial and remains unchecked. Local: 783 PHP contracts on both runtimes / 78 JS tests / 694 backend on WordPress 6.2 and 697 on 6.8.3 (three additional native Block Binding callback checks) using SQLite. Implementation verified in [CI run 40716485422](https://github.com/alifywork/fieldsPlugin/actions/runs/40716485422): all four native PHP/SQLite/MySQL targets green with 694 backend, 204 browser and 24 HTTP persistence checks before the three extra version-gated binding assertions. Roadmap: **42 complete / 35 pending**; the user's request for all 59 remaining items is **not fully delivered**.

- **v0.29.0 — Native contexts/deployment/editing bundle:** 16 previously open items implemented within the scopes above (21, 30–38, 47–48, 52, 60, 67–68). Local checks: 783 PHP contracts on both runtimes, 81 JS tests, 851 backend assertions on WordPress 6.2/PHP 7.4 and 854 on WordPress 6.8.3/PHP 8.3. Verified in [CI run 40754083740](https://github.com/alifywork/fieldsPlugin/actions/runs/40754083740): all four native PHP/SQLite/MySQL targets pass, including Chromium profile, term, attachment, menu/menu-item and authenticated frontend-form flows with clean WordPress debug logs. Roadmap: **58 complete within declared scopes / 19 pending**. This does **not** finish the user's request for all remaining features.

- **v0.30.0 — Conditional/editor framework bundle:** items 54, 55 and 70 implemented within the scopes above. Local: 805 PHP contracts on both runtimes, 84 JS tests, 886 backend assertions on WordPress 6.2/PHP 7.4 and 889 on WordPress 6.8.3/PHP 8.3 using SQLite. Verified in [CI run 40759192779](https://github.com/alifywork/fieldsPlugin/actions/runs/40759192779): all four native PHP/SQLite/MySQL targets pass, including Chromium conditional-chain/token-source frontend forms and dynamic-block preview/save/reload/public rendering with clean WordPress debug logs. Roadmap: **61 complete within declared scopes / 16 pending**, not all remaining development finished.

### Remaining development, not hidden completion claims

The 16 open items are **20, 22, 42–43, 49–50, 53, 62–64, 71 and 73–77**: bidirectional relationships, interactive maps, recursive containers, Clone/reuse, a post-scalability bound review, row-scoped conditions, explicit migrations/ACF import, native sidebar/datastore, and declared ecosystem/distribution integrations. Manual coordinates are not an interactive picker; classic widgets are not block widgets; public scalar custom blocks are not private field-group/container blocks. Provider credentials, integration environments and product decisions need explicit contracts, but do not make all remaining work externally blocked. Continue combined development batches without per-item approval requests.
