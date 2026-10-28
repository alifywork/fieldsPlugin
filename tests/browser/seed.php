<?php
$root = getenv('ALIFY_WP_ROOT'); $file = getenv('ALIFY_BROWSER_FIXTURE');
if (!$root || !$file) { throw new RuntimeException('Set ALIFY_WP_ROOT and ALIFY_BROWSER_FIXTURE for a disposable test site.'); }
$_SERVER['HTTP_HOST'] = 'alify.test'; $_SERVER['REQUEST_METHOD'] = 'GET';
require $root . '/wp-load.php';
if (!defined('ALIFY_CF_TEST_SITE') || !ALIFY_CF_TEST_SITE) { throw new RuntimeException('Refusing unmarked database.'); }
$admin = get_user_by('login', 'alify-test-admin'); wp_set_current_user($admin->ID);
if (is_file($file)) {
    $old = json_decode(file_get_contents($file), true);
    if (!empty($old['native_menu'])) { wp_delete_nav_menu($old['native_menu']); }
    foreach ($old['selector_terms'] ?? array() as $term_id) { wp_delete_term($term_id, 'category'); }
    foreach (array_merge(array($old['group'], $old['classic'], $old['block'], $old['rest'] ?? 0, $old['migration_group'] ?? 0, $old['migration_post'] ?? 0, $old['scoped_group'] ?? 0, $old['scoped_post'] ?? 0, $old['nested_group'] ?? 0, $old['nested_post'] ?? 0, $old['profile_group'] ?? 0, $old['term_group'] ?? 0, $old['attachment_group'] ?? 0, $old['menu_group'] ?? 0, $old['item_group'] ?? 0, $old['advanced_group'] ?? 0, $old['advanced_post'] ?? 0, $old['options_group'] ?? 0, $old['reference'] ?? 0, $old['reference_second'] ?? 0), $old['images'], $old['imports'] ?? array()) as $id) { if ($id) { wp_delete_post($id, true); } }
}

$user = get_user_by('login', 'alify-browser');
$password = wp_generate_password(30, false);
if ($user) { wp_set_password($password, $user->ID); $uid = $user->ID; }
else { $uid = wp_insert_user(array('user_login' => 'alify-browser', 'user_pass' => $password, 'role' => 'administrator')); }
function browser_field($name, $type = 'text', $required = false) { return array('key' => 'field_' . $name, 'name' => $name, 'label' => ucfirst($name), 'type' => $type, 'required' => $required, 'choices' => "a : Alpha\nb : Beta", 'instructions' => '', 'default' => ''); }
$group = wp_insert_post(array('post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => 'Browser fields'));
$details = browser_field('details', 'text', true); $details['condition'] = array('relation' => 'all', 'rules' => array(array('field' => 'field_show_details', 'operator' => 'equals', 'value' => '1'), array('field' => 'field_tags', 'operator' => 'equals', 'value' => 'a')));
$alternate = browser_field('alternate', 'text', true); $alternate['condition'] = array('relation' => 'any', 'rules' => array(array('field' => 'field_show_details', 'operator' => 'equals', 'value' => '1'), array('field' => 'field_tags', 'operator' => 'equals', 'value' => 'b')));
$team = browser_field('team', 'repeater'); $team['min_rows'] = 1; $team['max_rows'] = 2; $team['sub_fields'] = array(browser_field('name', 'text', true), browser_field('photo', 'image'));
$profile = browser_field('profile', 'group'); $profile['sub_fields'] = array(browser_field('name', 'text', true), browser_field('tags', 'checkbox', true), browser_field('portrait', 'image'));
$flexible = browser_field('sections', 'flexible'); $flexible['layouts'] = array(array('key' => 'layout_browser_hero', 'name' => 'hero', 'label' => 'Hero', 'sub_fields' => array(browser_field('title', 'text', true), browser_field('photos', 'gallery'))), array('key' => 'layout_browser_text', 'name' => 'text', 'label' => 'Text', 'sub_fields' => array(browser_field('body', 'textarea', true))));
$radio = browser_field('mode', 'radio'); $radio['choices'] = "0 : Zero\na : Alpha\nb : Beta";
$buttons = browser_field('button_mode', 'button_group'); $buttons['choices'] = "0 : Zero\na : Alpha\nb : Beta"; $buttons['default'] = '0';
$button_details = browser_field('button_details'); $button_details['condition'] = array('field' => $buttons['key'], 'operator' => 'equals', 'value' => 'a');
$range = browser_field('amount', 'range'); $range['min'] = '-1'; $range['max'] = '2'; $range['step'] = '0.125';
$range_details = browser_field('range_details'); $range_details['condition'] = array('field' => $range['key'], 'operator' => 'equals', 'value' => '0.5');
$meeting = browser_field('meeting_at', 'date_time'); $meeting['display_format'] = 'j M Y g:i A'; $meeting['return_format'] = 'm/d/Y g:i A';
$calendar = browser_field('calendar_day', 'date'); $calendar['display_format'] = 'F j, Y'; $calendar['return_format'] = 'd/m/Y';
$opens = browser_field('opens', 'time'); $opens['display_format'] = 'g:i A'; $opens['return_format'] = 'g:i a';
$meeting_detail = browser_field('meeting_detail'); $meeting_detail['condition'] = array('field' => $meeting['key'], 'operator' => 'equals', 'value' => '2024-02-29 09:30:45');
$choice_multi = browser_field('choice_multi', 'select'); $choice_multi['choices'] = "0 : Zero\na : Alpha\nb : Beta"; $choice_multi['multiple'] = true; $choice_multi['searchable'] = true; $choice_multi['default'] = array('b'); $choice_multi['return_format'] = 'array';
$choice_checks = browser_field('choice_checks', 'checkbox'); $choice_checks['layout'] = 'horizontal'; $choice_checks['default'] = array('b'); $choice_checks['return_format'] = 'label'; $radio['layout'] = 'horizontal';
$choice_detail = browser_field('choice_detail'); $choice_detail['condition'] = array('field' => $choice_multi['key'], 'operator' => 'equals', 'value' => '0');
$accent = browser_field('accent', 'color'); $accent['alpha'] = true; $accent['palette'] = array('#2271b180', '#abcdef00');
$cta = browser_field('cta', 'link'); $cta['return_format'] = 'url'; $cta['default'] = array('url' => 'https://example.com/default', 'title' => 'Default CTA', 'target' => '_blank');
$photos = browser_field('photos', 'gallery'); $photos['allowed_mimes'] = array('image/png'); $photos['max_items'] = 4; $photos['max_bytes'] = 1048576; $photos['preview_size'] = 'medium'; $photos['return_format'] = 'url';
$related = browser_field('related', 'post_object'); $related['post_types'] = array('page'); $related['post_statuses'] = array('publish'); $related['return_format'] = 'object';
$related_many = browser_field('related_many', 'relationship'); $related_many['post_types'] = array('page'); $related_many['post_statuses'] = array('publish'); $related_many['min_items'] = 1; $related_many['max_items'] = 2; $related_many['return_format'] = 'object';
$extra_refs = browser_field('extra_refs', 'post_object'); $extra_refs['multiple'] = true; $extra_refs['post_types'] = array('page'); $extra_refs['post_statuses'] = array('publish'); $extra_refs['min_items'] = 1; $extra_refs['max_items'] = 2; $extra_refs['return_format'] = 'object';
$topics = browser_field('topics', 'taxonomy'); $topics['multiple'] = true; $topics['taxonomies'] = array('category'); $topics['return_format'] = 'object';
$assignee = browser_field('assignee', 'user'); $assignee['roles'] = array('administrator'); $assignee['return_format'] = 'array';
$destination = browser_field('destination', 'page_link'); $destination['post_types'] = array('page');
$fields = array(browser_field('subtitle', 'text', true), browser_field('tags', 'checkbox', true), browser_field('show_details', 'true_false'), $details, $photos, $team, $profile, $flexible, $alternate, $accent, $opens, $cta, browser_field('rich_copy', 'wysiwyg'), $related, $related_many, $radio, $buttons, $button_details, $range, $range_details, browser_field('masked', 'password'), $meeting, $meeting_detail, $calendar, $choice_multi, $choice_checks, $choice_detail, $extra_refs, $topics, $assignee, $destination);
$_POST = wp_slash(array('alify_schema_nonce' => wp_create_nonce('alify_schema_' . $group), 'alify_schema' => wp_json_encode($fields), 'alify_location' => 'post'));
wp_update_post(array('ID' => $group, 'post_status' => 'publish')); $_POST = array();
$classic = wp_insert_post(array('post_title' => 'Browser classic', 'post_status' => 'draft', 'post_author' => $uid));
$block = wp_insert_post(array('post_title' => 'Browser block', 'post_status' => 'draft', 'post_author' => $uid));
$rest = wp_insert_post(array('post_title' => 'Browser REST', 'post_status' => 'draft', 'post_author' => $uid));
$reference = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Browser reference target', 'post_author' => $uid));
$reference_second = wp_insert_post(array('post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Browser reference target second', 'post_author' => $uid));
$selector_terms = array(); foreach (array('One', 'Two') as $label) { $term = wp_insert_term('Browser selector topic ' . $label . ' ' . wp_generate_uuid4(), 'category'); $selector_terms[] = $term['term_id']; }
// Isolated options fixture; no built-in Site Settings values are touched.
Alify_CF_Options::register('browser-http', 'Browser HTTP Settings');
delete_option('alify_cf_options_browser-http');
$editor_password = wp_generate_password(30, false);
$editor = get_user_by('login', 'alify-browser-editor');
if ($editor) { wp_set_password($editor_password, $editor->ID); wp_update_user(array('ID' => $editor->ID, 'role' => 'editor')); }
else { wp_insert_user(array('user_login' => 'alify-browser-editor', 'user_pass' => $editor_password, 'role' => 'editor')); }
$options_group = wp_insert_post(array('post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => 'Browser options fields'));
$options_profile = browser_field('options_profile', 'group'); $options_profile['sub_fields'] = array(browser_field('name', 'text', true));
$options_team = browser_field('options_team', 'repeater'); $options_team['sub_fields'] = array(browser_field('name', 'text', true)); $options_team['min_rows'] = 1; $options_team['max_rows'] = 2;
$options_range = $range; $options_range['name'] = 'options_amount'; $options_range['key'] = 'field_options_amount';
$option_meeting = $meeting; $option_meeting['name'] = 'options_meeting'; $option_meeting['key'] = 'field_options_meeting';
$options_fields = array(browser_field('options_title', 'text', true), browser_field('options_tags', 'checkbox', true), $options_profile, $options_team, browser_field('options_copy', 'wysiwyg'), browser_field('options_related', 'post_object'), browser_field('options_many', 'relationship'), browser_field('options_mode', 'radio'), browser_field('options_buttons', 'button_group'), $options_range, browser_field('options_masked', 'password'), $option_meeting);
$_POST = wp_slash(array('alify_schema_nonce' => wp_create_nonce('alify_schema_' . $options_group), 'alify_schema' => wp_json_encode($options_fields), 'alify_location' => 'options:browser-http'));
wp_update_post(array('ID' => $options_group, 'post_status' => 'publish')); $_POST = array();
$advanced_group = wp_insert_post(array('post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => 'Advanced editor fields'));
$advanced_post = wp_insert_post(array('post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Advanced editor draft'));
$advanced_rows = browser_field('advanced_rows', 'repeater'); $advanced_rows['sub_fields'] = array(browser_field('heading', 'text', true), browser_field('body', 'wysiwyg')); $advanced_rows['page_size'] = 1; $advanced_rows['collapsed'] = true; $advanced_rows['row_label'] = 'heading';
$advanced_flex = browser_field('advanced_flex', 'flexible'); $advanced_flex['layouts'] = array(array('key' => 'layout_section', 'name' => 'section', 'label' => 'Section', 'max_rows' => 2, 'sub_fields' => array(browser_field('body', 'wysiwyg'))));
$advanced_message = browser_field('advanced_message', 'message'); $advanced_message['content'] = '<p><strong>Editor notice</strong></p>';
$chain_root = browser_field('advanced_chain_root', 'true_false'); $chain_middle = browser_field('advanced_chain_middle'); $chain_tail = browser_field('advanced_chain_tail'); $chain_middle['condition'] = array('field' => $chain_root['key'], 'operator' => 'equals', 'value' => '1'); $chain_tail['condition'] = array('field' => $chain_middle['key'], 'operator' => 'equals', 'value' => 'trigger'); $advanced_assignee = browser_field('advanced_assignee', 'user'); $assignee_detail = browser_field('advanced_assignee_detail'); $assignee_detail['condition'] = array('field' => $advanced_assignee['key'], 'operator' => 'equals', 'value' => (string) $uid);
$advanced_fields = array(browser_field('advanced_tab_one', 'tab'), $advanced_message, $advanced_rows, $advanced_flex, browser_field('advanced_icon', 'icon_picker'), browser_field('advanced_tab_two', 'tab'), browser_field('advanced_accordion', 'accordion'), browser_field('advanced_text'), $chain_tail, $chain_middle, $chain_root, $advanced_assignee, $assignee_detail);
foreach ($advanced_fields as &$sidebar_field) { if ($sidebar_field['name'] === 'advanced_text') { $sidebar_field['api_read'] = true; $sidebar_field['api_write'] = true; } } unset($sidebar_field);
$_POST = wp_slash(array('alify_schema_nonce' => wp_create_nonce('alify_schema_' . $advanced_group), 'alify_schema' => wp_json_encode($advanced_fields), 'alify_location' => 'page')); wp_update_post(array('ID' => $advanced_group, 'post_status' => 'publish')); $_POST = array();
if (!post_type_exists('alify_mig_ui')) { register_post_type('alify_mig_ui', array('public' => true, 'show_ui' => true, 'supports' => array('title'))); }
$migration_group = wp_insert_post(array('post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => 'Browser migration fields')); $_POST = wp_slash(array('alify_schema_nonce' => wp_create_nonce('alify_schema_' . $migration_group), 'alify_schema' => wp_json_encode(array(browser_field('browser_migration_old'), browser_field('browser_migration_new'), browser_field('browser_migration_image', 'image'))), 'alify_location' => 'alify_mig_ui')); wp_update_post(array('ID' => $migration_group, 'post_status' => 'publish')); $_POST = array(); $migration_post = wp_insert_post(array('post_type' => 'alify_mig_ui', 'post_status' => 'draft', 'post_title' => 'Browser migration fixture')); alify_update_field('browser_migration_old', 'Browser migration value', $migration_post);
update_post_meta($migration_post, 'legacy_browser_title', 'Native ACF content value'); update_post_meta($migration_post, '_legacy_browser_title', 'field_legacy_browser_title'); update_post_meta($migration_post, 'legacy_browser_image', '9002'); update_post_meta($migration_post, '_legacy_browser_image', 'field_legacy_browser_image');
if (!post_type_exists('alify_scoped_ui')) { register_post_type('alify_scoped_ui', array('public' => true, 'show_ui' => true, 'supports' => array('title'))); }
$scope_toggle = browser_field('toggle', 'true_false'); $scope_detail = browser_field('detail', 'text', true); $scope_detail['condition'] = array('field' => $scope_toggle['key'], 'operator' => 'equals', 'value' => '1'); $scope_tail = browser_field('tail', 'text', true); $scope_tail['condition'] = array('field' => $scope_detail['key'], 'operator' => 'equals', 'value' => 'go'); $scope_subs = array($scope_tail, $scope_detail, $scope_toggle);
$scope_group_field = browser_field('browser_scope_group', 'group'); $scope_group_field['sub_fields'] = $scope_subs; $scope_rows_field = browser_field('browser_scope_rows', 'repeater'); $scope_rows_field['sub_fields'] = $scope_subs; $scope_flex_field = browser_field('browser_scope_flex', 'flexible'); $scope_flex_field['layouts'] = array(array('name' => 'hero', 'key' => 'layout_scope_hero', 'label' => 'Scoped hero', 'sub_fields' => $scope_subs));
$scoped_group = wp_insert_post(array('post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => 'Browser scoped fields')); $_POST = wp_slash(array('alify_schema_nonce' => wp_create_nonce('alify_schema_' . $scoped_group), 'alify_schema' => wp_json_encode(array($scope_group_field, $scope_rows_field, $scope_flex_field, browser_field('browser_scope_map', 'google_map'))), 'alify_location' => 'alify_scoped_ui')); wp_update_post(array('ID' => $scoped_group, 'post_status' => 'publish')); $_POST = array(); $scoped_post = wp_insert_post(array('post_type' => 'alify_scoped_ui', 'post_status' => 'draft', 'post_title' => 'Browser scoped fixture'));
$scope_a = array('tail' => '', 'detail' => 'Keep A', 'toggle' => '1'); $scope_b = array('tail' => '', 'detail' => 'Keep B', 'toggle' => '1'); alify_update_fields(array('browser_scope_group' => Alify_CF_Publication::stored_payload($scope_group_field, $scope_a), 'browser_scope_rows' => Alify_CF_Publication::stored_payload($scope_rows_field, array($scope_a, $scope_b)), 'browser_scope_flex' => Alify_CF_Publication::stored_payload($scope_flex_field, array(array('layout' => 'hero', 'fields' => $scope_a)))), $scoped_post);
register_post_type('alify_nested_ui', array('public' => true, 'show_ui' => true, 'supports' => array('title')));
$nested_card = browser_field('card', 'group'); $nested_card['sub_fields'] = array(browser_field('caption', 'text', true)); $nested_flex = browser_field('sections', 'flexible'); $nested_flex['layouts'] = array(array('name' => 'hero', 'key' => 'layout_nested_hero', 'label' => 'Hero', 'sub_fields' => array($nested_card))); $nested_rows = browser_field('nested_rows', 'repeater'); $nested_rows['sub_fields'] = array($nested_flex); $nested_root = browser_field('nested_root', 'group'); $nested_root['sub_fields'] = array($nested_rows);
$nested_group = wp_insert_post(array('post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => 'Nested browser fields')); $_POST = wp_slash(array('alify_schema_nonce' => wp_create_nonce('alify_schema_' . $nested_group), 'alify_schema' => wp_json_encode(array($nested_root)), 'alify_location' => 'alify_nested_ui')); wp_update_post(array('ID' => $nested_group, 'post_status' => 'publish')); $_POST = array(); $nested_post = wp_insert_post(array('post_type' => 'alify_nested_ui', 'post_status' => 'draft', 'post_title' => 'Nested browser fixture')); alify_update_field('nested_root', Alify_CF_Publication::stored_payload($nested_root, array('nested_rows' => array(array('sections' => array(array('layout' => 'hero', 'fields' => array('card' => array('caption' => 'Nested original')))))))), $nested_post);
$images = array();
foreach (array('One', 'Two') as $name) {
    $upload = wp_upload_bits('alify-browser-' . strtolower($name) . '.png', null, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5V8AAAAASUVORK5CYII='));
    $id = wp_insert_attachment(array('post_title' => 'Alify Browser Image ' . $name, 'post_mime_type' => 'image/png', 'post_status' => 'inherit'), $upload['file']);
    wp_update_attachment_metadata($id, array('width' => 1, 'height' => 1, 'file' => _wp_relative_upload_path($upload['file']))); $images[] = $id;
}
$native_groups = array();
foreach (array('profile' => array('user', 'profile_note'), 'term' => array('term:category', 'term_note'), 'attachment' => array('attachment', 'media_note'), 'menu' => array('menu', 'menu_note'), 'item' => array('menu_item', 'item_note')) as $kind => $settings) {
    $native_id = wp_insert_post(array('post_type' => Alify_CF::GROUP, 'post_status' => 'draft', 'post_title' => 'Browser ' . $kind . ' fields'));
    $_POST = wp_slash(array('alify_schema_nonce' => wp_create_nonce('alify_schema_' . $native_id), 'alify_schema' => wp_json_encode(array(browser_field($settings[1]))), 'alify_location' => $settings[0])); wp_update_post(array('ID' => $native_id, 'post_status' => 'publish')); $_POST = array(); $native_groups[$kind . '_group'] = $native_id;
}
$native_menu = wp_create_nav_menu('Browser native menu ' . wp_generate_uuid4()); $native_item = wp_update_nav_menu_item($native_menu, 0, array('menu-item-title' => 'Browser native item', 'menu-item-type' => 'custom', 'menu-item-url' => home_url('/'), 'menu-item-status' => 'publish'));
wp_mkdir_p(WP_CONTENT_DIR . '/mu-plugins');
$mu = '<?php if (!defined("ALIFY_CF_TEST_SITE") || !ALIFY_CF_TEST_SITE) { return; } add_filter("use_block_editor_for_post", function($enabled, $post) { return $post->ID === ' . (int) $classic . ' || ($post->ID === ' . (int) $advanced_post . ' && !isset($_GET["alify_block"])) || isset($_GET["alify_classic"]) ? false : $enabled; }, 10, 2); add_action("admin_init", function() { remove_action("admin_init", "_maybe_update_core"); remove_action("admin_init", "_maybe_update_plugins"); remove_action("admin_init", "_maybe_update_themes"); }, 0);';
// Profile language controls must not contact WordPress.org in the disposable fixture.
$mu .= ' add_filter("translations_api", function() { return array("translations" => array()); });';
$mu .= ' add_action("after_setup_theme", function() { add_theme_support("menus"); register_nav_menu("alify_browser", "Browser fixture menu"); });';
$mu .= ' define("ALIFY_CF_SIDEBAR", true); add_action("init", function() { register_post_type("alify_nested_ui", array("public" => true, "show_ui" => true, "supports" => array("title"))); }); define("ALIFY_CF_GOOGLE_MAPS_PICKER", true); define("ALIFY_CF_GOOGLE_MAPS_KEY", "alify-browser-maps-test-key"); add_action("init", function() { register_post_type("alify_scoped_ui", array("public" => true, "show_ui" => true, "supports" => array("title"))); });';
$mu .= ' add_action("init", function() { register_post_type("alify_mig_ui", array("public" => true, "show_ui" => true, "supports" => array("title"))); });';
$mu .= ' add_action("init", function() { alify_add_options_page("browser-http", "Browser HTTP Settings"); });';
$mu .= ' add_action("init", function() { alify_register_block_type("alify/browser-card", array("title" => "Alify Browser Card", "fields" => array(array("name" => "headline", "label" => "Card headline", "type" => "text"), array("name" => "count", "label" => "Card count", "type" => "number"), array("name" => "enabled", "label" => "Card enabled", "type" => "true_false")), "render_callback" => function($values) { return "<section class=\"alify-browser-card\"><h2>" . esc_html($values["headline"]) . "</h2><p>" . (int) $values["count"] . "</p></section>"; })); }, 25);';
$mu .= ' add_action("template_redirect", function() { if (!isset($_GET["alify_frontend_test"])) return; $id = absint($_GET["alify_frontend_test"]); if (!current_user_can("edit_post", $id)) { status_header(403); exit("Denied"); } alify_form_head(); echo "<!doctype html><html><head>"; wp_head(); echo "</head><body>"; alify_form($id); wp_footer(); echo "</body></html>"; exit; });';
file_put_contents(WP_CONTENT_DIR . '/mu-plugins/alify-browser-test.php', $mu);
file_put_contents($file, wp_json_encode(array_merge($native_groups, array('nested_group' => $nested_group, 'nested_post' => $nested_post, 'scoped_group' => $scoped_group, 'scoped_post' => $scoped_post, 'migration_group' => $migration_group, 'migration_post' => $migration_post, 'native_menu' => $native_menu, 'native_item' => $native_item, 'username' => 'alify-browser', 'password' => $password, 'classic' => $classic, 'block' => $block, 'rest' => $rest, 'group' => $group, 'images' => $images, 'advanced_group' => $advanced_group, 'advanced_post' => $advanced_post, 'options_group' => $options_group, 'reference' => $reference, 'reference_second' => $reference_second, 'selector_terms' => $selector_terms, 'user_id' => $uid, 'editor_username' => 'alify-browser-editor', 'editor_password' => $editor_password))));
chmod($file, 0600);
echo "Browser fixtures seeded (credentials kept in private fixture file).\n";
