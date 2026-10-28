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
3. [x] **Range** — v0.20.0: paired slider/numeric input, min/max/step, canonical decimals with exact three-place arithmetic and ±1000000 bounds, defaults, optional clear, required/atomic validation, post/Options/basic containers, conditions, REST and JSON. **Current completed feature.**
4. [ ] **Password** — masked editor input; explicitly not encryption or secure-secret storage. **Next feature.**
5. [ ] Combined Date Time — define storage, display and timezone contract.
6. [ ] Date/Time display and return-format configuration.
7. [ ] Radio/Select/Checkbox richer settings — layouts, checkbox defaults, multiple/searchable Select and documented return formats.
8. [ ] Color configuration — palettes and optional alpha with explicit storage contract.
9. [ ] Image/File/Gallery configuration — return formats, media restrictions and presentation/validation settings.
10. [ ] Link configuration — supported return formats and optional defaults.

## B. Relational and external fields

11. [ ] Taxonomy selector — permitted taxonomies, single/multiple terms, validation and getters.
12. [ ] User selector — permissions/privacy, bounded search, single/multiple and getters.
13. [ ] Page Link — page/post/archive URL selection with defined resolution behavior.
14. [ ] Post Object/Relationship CPT targets and per-field allowed post types.
15. [ ] Configurable reference post-status restrictions.
16. [ ] Reference taxonomy filters and editor filtering controls.
17. [ ] Post Object multiple-selection configuration.
18. [ ] Configurable Relationship min/max cardinality.
19. [ ] Reference return-format configuration.
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
- **v0.20.0 — Range:** bounded decimal slider/numeric input with exact step validation and the shared integration/save-safety contract. Local checks: 474 isolated PHP assertions, 54 JS tests, 374 backend assertions on both existing PHP-WASM/SQLite targets. Native/browser verification is recorded after completion. Next: **Password**. Subsequent work follows the numbered queue unless a dependency requires reordering, which must be recorded here.
