# Alify Custom Field — sequential development roadmap

Requested scope: all missing (❌) and partial capabilities identified in the ACF Free / ACF PRO comparison on 2026-10-28. This is a feature backlog, not a claim of ACF compatibility or a promise that similarly named features will have identical behavior. Repeated comparison gaps are consolidated below.

## Working rules

- Develop **one feature at a time**, in dependency order. A row is not complete merely because its UI exists.
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
16. [ ] Reference taxonomy filters and editor filtering controls.
17. [x] **Multiple Post Object** — v0.26.0: ordered complete ID lists, picker/reordering/clearing, containers/Options/REST/publication/recovery; no scalar/list migration or list conditional sources.
18. [x] **Relationship min/max cardinality** — v0.26.0: shared multiple-reference bounds, maximum 50, optional empty versus Required semantics, no truncation.
19. [x] **Reference return formats** — v0.26.0: opt-in ID/object returns recursively across containers/Options; unavailable or unreadable objects omitted/null; raw IDs unchanged.
20. [ ] Bidirectional relationships — dependency on supported object locations and safe write APIs; avoid recursive writes and partial updates.
21. [ ] oEmbed — bounded remote provider handling and rendering contract.
22. [ ] Google Map — provider/key configuration, location storage and external-service constraints.
23. [ ] Icon Picker — supported libraries and safe identifier output.

## C. Content-editor layout and rich text

24. [ ] Tab field.
25. [ ] Accordion field.
26. [ ] Message/informational field with safe HTML policy.
27. [ ] WYSIWYG media buttons and explicit upload permissions.
28. [ ] WYSIWYG toolbar/editor configuration.
29. [ ] WYSIWYG in dynamic container subfields, including lifecycle cleanup and cloned editors.

## D. Location rules and content-model registration

30. [ ] Taxonomy-term edit locations and storage/getters.
31. [ ] User-profile locations and storage/getters.
32. [ ] Attachment edit locations.
33. [ ] Comment locations.
34. [ ] Navigation-menu/menu-item locations.
35. [ ] Widget locations with clearly defined supported widget editors.
36. [ ] Specific post/page, template, taxonomy and other location predicates.
37. [ ] Multiple location-rule groups with AND/OR evaluation and conflict handling.
38. [ ] Public custom location-rule extension API.
39. [ ] Options-page creation/configuration UI, beyond built-in Site Settings and PHP registration.
40. [ ] CPT registration UI and portable configuration.
41. [ ] Taxonomy registration UI and portable configuration.

## E. Structured content

42. [ ] Nested Group support with bounded recursive schema/value handling.
43. [ ] Nested Repeater and Flexible Content composition; preserve atomic validation and error paths.
44. [ ] Repeater/Flexible drag sorting, with keyboard alternatives.
45. [ ] Content-row duplication with fresh names/IDs and safe editor state.
46. [ ] Container presentation settings and collapsible row labels.
47. [ ] Repeater pagination/large-content workflow; do not silently discard unloaded rows.
48. [ ] Flexible layout-specific limits, configuration and richer layout-management controls.
49. [ ] Clone/reusable field definitions with collision and recursion protection.
50. [ ] Review fixed field/subfield/layout/row bounds after scalable editing support; no unbounded payload promise.

## F. Conditional logic

51. [ ] Nested/mixed AND/OR rule groups.
52. [ ] Contains/pattern and other appropriate type-specific operators with bounded evaluation.
53. [ ] Subfield/container-scoped conditions and correct dynamic-row context.
54. [ ] Conditional chains with cycle detection and deterministic server/client parity.
55. [ ] Richer eligible condition sources (e.g. Relationship/structured fields), with explicit comparison semantics.

## G. Developer APIs, portability and migrations

56. [ ] Public PHP field-group/field registration API.
57. [ ] General field/Options write APIs with validation and authorization contracts.
58. [ ] Row/subfield iteration and mutation helpers.
59. [ ] Documented lifecycle hooks, filters and custom field-type extension API.
60. [ ] Automatic Local JSON save/load and synchronization UI.
61. [ ] PHP configuration export and version-controlled deployment workflow.
62. [ ] Explicit rename/type-change/container-layout migration tools; schema transfer is not content migration.
63. [ ] ACF schema importer with declared supported types/settings and rejection reporting.
64. [ ] Separate ACF content migration, including reference/media remapping and rollback planning; not automatic drop-in compatibility.

## H. REST, revisions and frontend editing

65. [ ] General opt-in REST read/schema API with field-level exposure decisions.
66. [ ] General authenticated REST write API across supported object locations, beyond the current core-post save bridge.
67. [ ] Custom-field revision capture/restore and autosave previews with permissions and validation.
68. [ ] Frontend forms API with CSRF protection, object authorization, uploads and safe validation.
69. [ ] Optional safe shortcode/output integration with explicit exposure/escaping policy.
70. [ ] Custom blocks framework — registration, rendering, schema and editor integration.
71. [ ] Native Gutenberg sidebar/datastore integration and appropriate inline editing.
72. [ ] Core Block Bindings integration.
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

- **v0.27.0 — New selector batch:** items 11–13 complete on the shared selector/search infrastructure. Local: 783 PHP / 74 JS / 600 backend on both SQLite targets; native/browser verification pending. Roadmap: 18 complete / 59 pending. Next unresolved relational item is 16 (taxonomy filtering on references), followed by 20+ and editor-layout batches; no claim that the remaining backlog is complete.
