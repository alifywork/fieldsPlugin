<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/class-alify-cf-choice.php';
require_once __DIR__ . '/class-alify-cf-selector.php';
require_once __DIR__ . '/class-alify-cf-api.php';
require_once __DIR__ . '/class-alify-cf-objects.php';
require_once __DIR__ . '/class-alify-cf-widgets.php';
require_once __DIR__ . '/class-alify-cf-external.php';
require_once __DIR__ . '/class-alify-cf-location-rules.php';
require_once __DIR__ . '/class-alify-cf-local-json.php';
require_once __DIR__ . '/class-alify-cf-forms.php';
require_once __DIR__ . '/class-alify-cf-revisions.php';
require_once __DIR__ . '/class-alify-cf-models.php';
require_once __DIR__ . '/class-alify-cf-editor-fields.php';
require_once __DIR__ . '/class-alify-cf-reference-settings.php';
require_once __DIR__ . '/class-alify-cf-field-settings.php';
require_once __DIR__ . '/class-alify-cf-temporal-format.php';
require_once __DIR__ . '/class-alify-cf-date-time.php';
require_once __DIR__ . '/class-alify-cf-password.php';
require_once __DIR__ . '/class-alify-cf-range.php';
require_once __DIR__ . '/class-alify-cf-link.php';
require_once __DIR__ . '/class-alify-cf-post-object.php';
require_once __DIR__ . '/class-alify-cf-relationship.php';
require_once __DIR__ . '/class-alify-cf-rich-text.php';
require_once __DIR__ . '/class-alify-cf-repeater.php';
require_once __DIR__ . '/class-alify-cf-group.php';
require_once __DIR__ . '/class-alify-cf-flexible.php';
require_once __DIR__ . '/class-alify-cf-conditions.php';
require_once __DIR__ . '/class-alify-cf-gallery.php';
require_once __DIR__ . '/class-alify-cf-options.php';
require_once __DIR__ . '/class-alify-cf-feedback.php';
require_once __DIR__ . '/class-alify-cf-publication.php';
require_once __DIR__ . '/class-alify-cf-transfer.php';

final class Alify_CF {
    const GROUP = 'alify_field_group';
    const SCHEMA = '_alify_cf_schema';
    const LOCATION = '_alify_cf_location';

    public static function boot() {
        Alify_CF_API::boot();
        Alify_CF_Objects::boot();
        Alify_CF_Widgets::boot();
        Alify_CF_Local_JSON::boot();
        Alify_CF_Forms::boot();
        Alify_CF_Revisions::boot();
        Alify_CF_Models::boot();
        Alify_CF_Options::boot();
        Alify_CF_Post_Object::boot();
        Alify_CF_Selector::boot();
        Alify_CF_Publication::boot();
        Alify_CF_Transfer::boot();
        add_action('init', array(__CLASS__, 'register'));
        add_action('admin_notices', array(__CLASS__, 'notices'));
        add_action('add_meta_boxes', array(__CLASS__, 'boxes'));
        add_action('save_post', array(__CLASS__, 'save'), 10, 2);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_filter('manage_' . self::GROUP . '_posts_columns', array(__CLASS__, 'columns'));
        add_action('manage_' . self::GROUP . '_posts_custom_column', array(__CLASS__, 'column'), 10, 2);
        add_filter('post_updated_messages', array(__CLASS__, 'messages'));
    }

    public static function types() {
        return array_merge(array('oembed' => 'oEmbed (YouTube/Vimeo)', 'google_map' => 'Google Map coordinates', 'tab' => 'Tab', 'accordion' => 'Accordion', 'message' => 'Message', 'icon_picker' => 'Icon Picker', 'taxonomy' => 'Taxonomy', 'user' => 'User', 'page_link' => 'Page Link', 'text' => 'Text', 'password' => 'Password', 'textarea' => 'Text Area', 'wysiwyg' => 'WYSIWYG Editor', 'number' => 'Number', 'range' => 'Range', 'email' => 'Email', 'url' => 'URL', 'link' => 'Link', 'post_object' => 'Post Object', 'relationship' => 'Relationship', 'select' => 'Select', 'radio' => 'Radio Button', 'button_group' => 'Button Group', 'checkbox' => 'Checkbox', 'true_false' => 'True / False', 'date' => 'Date', 'date_time' => 'Date Time', 'color' => 'Color Picker', 'time' => 'Time', 'image' => 'Image', 'file' => 'File', 'repeater' => 'Repeater', 'gallery' => 'Gallery', 'group' => 'Group', 'flexible' => 'Flexible Content'), Alify_CF_API::types());
    }

    public static function register() {
        register_post_type(self::GROUP, array(
            'labels' => array('name' => 'Alify Custom Field', 'singular_name' => 'Field Group', 'add_new' => 'Add Field Group', 'add_new_item' => 'Add Field Group', 'edit_item' => 'Edit Field Group', 'all_items' => 'Field Groups', 'not_found' => 'No field groups yet. Create your first group to get started.'),
            'public' => false, 'show_ui' => true, 'show_in_rest' => false,
            'menu_icon' => 'dashicons-editor-table', 'menu_position' => 80,
            'supports' => array('title'), 'capability_type' => 'post',
            'capabilities' => array('edit_post' => 'manage_options', 'read_post' => 'manage_options', 'delete_post' => 'manage_options', 'edit_posts' => 'manage_options', 'edit_others_posts' => 'manage_options', 'publish_posts' => 'manage_options', 'read_private_posts' => 'manage_options', 'delete_posts' => 'manage_options', 'delete_private_posts' => 'manage_options', 'delete_published_posts' => 'manage_options', 'delete_others_posts' => 'manage_options', 'edit_private_posts' => 'manage_options', 'edit_published_posts' => 'manage_options', 'create_posts' => 'manage_options'),
            'map_meta_cap' => false,
        ));
    }

    public static function post_types() {
        return get_post_types(array('show_ui' => true), 'objects');
    }

    public static function groups($post_type, $object = 0) {
        $groups = get_posts(array('post_type' => self::GROUP, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => self::LOCATION, 'meta_value' => $post_type));
        foreach (Alify_CF_API::groups($post_type) as $group) { $groups[] = (object) array('ID' => $group['id'], 'post_title' => $group['title']); }
        return $object ? array_values(array_filter($groups, function($group) use ($post_type, $object) { return Alify_CF_Location_Rules::group($group->ID, $post_type, $object); })) : $groups;
    }

    public static function schema($id) {
        $registered = Alify_CF_API::schema($id); if ($registered !== null) { return $registered; }
        $schema = get_post_meta($id, self::SCHEMA, true);
        return is_array($schema) ? $schema : array();
    }

    public static function assets() {
        $screen = get_current_screen();
        if (!$screen || (!$screen->post_type) || ('post' !== $screen->base && 'edit' !== $screen->base)) { return; }
        if ($screen->post_type !== self::GROUP && !self::groups($screen->post_type)) { return; }
        wp_enqueue_style('alify-cf', plugins_url('assets/admin.css', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION);
        if ($screen->post_type !== self::GROUP && $screen->base === 'post') {
            if (current_user_can('upload_files')) { wp_enqueue_media(); }
            wp_enqueue_script('alify-cf-gallery', plugins_url('assets/gallery.js', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION, true);
            wp_enqueue_script('alify-cf-flexible', plugins_url('assets/flexible.js', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION, true);
            Alify_CF_Rich_Text::assets();
            Alify_CF_Post_Object::assets();
            wp_enqueue_script('alify-cf-values', plugins_url('assets/values.js', ALIFY_CF_FILE), array('alify-cf-gallery', 'alify-cf-flexible', 'alify-cf-rich-text', 'alify-cf-post-object', 'alify-cf-relationship'), ALIFY_CF_VERSION, true);
            if (method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) {
                $type = get_post_type_object($screen->post_type);
                wp_enqueue_script('alify-cf-editor', plugins_url('assets/editor.js', ALIFY_CF_FILE), array('wp-api-fetch', 'alify-cf-values'), ALIFY_CF_VERSION, true);
                wp_localize_script('alify-cf-editor', 'AlifyEditor', array('postId' => get_the_ID(), 'path' => '/' . (!empty($type->rest_namespace) ? $type->rest_namespace : 'wp/v2') . '/' . ($type->rest_base ?: $type->name)));
            }
        }
        if ($screen->post_type === self::GROUP && $screen->base === 'post') {
            wp_enqueue_script('alify-cf-builder', plugins_url('assets/builder.js', ALIFY_CF_FILE), array('jquery', 'jquery-ui-sortable'), ALIFY_CF_VERSION, true);
            wp_localize_script('alify-cf-builder', 'AlifyCF', array('types' => self::types()));
        }
    }

    public static function boxes() {
        add_meta_box('alify-builder', 'Field Builder', array(__CLASS__, 'builder'), self::GROUP, 'normal', 'high');
        add_meta_box('alify-location', 'Location Rules', array(__CLASS__, 'location'), self::GROUP, 'normal');
        $screen = get_current_screen();
        if (!$screen || $screen->post_type === self::GROUP) { return; }
        foreach (self::groups($screen->post_type, isset($GLOBALS['post']->ID) ? $GLOBALS['post']->ID : 0) as $group) {
            add_meta_box('alify-values-' . $group->ID, $group->post_title, function($post) use ($group) { self::values($post, $group); }, $screen->post_type, 'normal', 'default');
        }
    }

    public static function builder($post) {
        wp_nonce_field('alify_schema_' . $post->ID, 'alify_schema_nonce');
        echo '<div class="alify-intro"><span class="alify-badge">ALIFY / FIELDS</span><h2>Good content starts with structure.</h2><p>Add fields, arrange them, and give your editors a clearer way to create.</p></div>';
        echo '<input type="hidden" id="alify-schema" name="alify_schema" value="' . esc_attr(wp_json_encode(self::schema($post->ID))) . '">';
        echo '<div id="alify-fields"></div><div id="alify-empty" class="alify-empty"><span class="dashicons dashicons-editor-table"></span><h3>A blank canvas for your content</h3><p>Add your first field to bring this group to life.</p></div><div class="alify-footer"><button type="button" class="button button-primary" id="alify-add">+ Add Field</button><span id="alify-count" aria-live="polite"></span></div><noscript><p>The field builder requires JavaScript. Existing fields will be preserved.</p></noscript>';
    }

    public static function location($post) {
        $location = get_post_meta($post->ID, self::LOCATION, true) ?: 'post';
        echo '<div class="alify-location"><div><strong>Show this field group when</strong><p>Published groups appear on the selected post type or options page.</p></div><label>Location <select name="alify_location">';
        foreach (self::post_types() as $type) {
            if (in_array($type->name, array(self::GROUP), true)) { continue; }
            echo '<option value="' . esc_attr($type->name) . '" ' . selected($location, $type->name, false) . '>' . esc_html($type->labels->singular_name) . '</option>';
        }
        foreach (Alify_CF_Objects::locations() as $key => $title) { echo '<option value="' . esc_attr($key) . '" ' . selected($location, $key, false) . '>' . esc_html($title) . '</option>'; }
        foreach (Alify_CF_Options::pages() as $slug => $title) {
            echo '<option value="' . esc_attr(Alify_CF_Options::location($slug)) . '" ' . selected($location, Alify_CF_Options::location($slug), false) . '>Options Page: ' . esc_html($title) . '</option>';
        }
        echo '</select></label></div><p>Optional OR groups of AND predicates: post_id, status, template, term (taxonomy:ID), current_user_role or registered x_ rules. Operators == / !=. Maximum 50 rules.</p><textarea class="large-text code" rows="5" name="alify_location_rules">' . esc_textarea(wp_json_encode(get_post_meta($post->ID, '_alify_cf_location_rules', true) ?: array(), JSON_PRETTY_PRINT)) . '</textarea>';
    }

    public static function choices($field) {
        $choices = array();
        foreach (explode("\n", $field['choices'] ?? '') as $line) {
            $parts = explode(':', $line, 2);
            $key = trim($parts[0]);
            if ($key !== '') { $choices[$key] = isset($parts[1]) ? trim($parts[1]) : $key; }
        }
        return $choices;
    }

    /** Flat ALL/ANY conditions, referencing unconditional siblings by stable keys. */
    public static function visible($field, $schema, $values) {
        return Alify_CF_Conditions::visible($field, $schema, $values);
    }

    public static function values($post, $group, $option_values = null, $feedback = null) {
        if ($option_values === null) { wp_nonce_field('alify_values_' . $post->ID . '_' . $group->ID, 'alify_values_nonce_' . $group->ID); }
        $schema = self::schema($group->ID);
        if ($feedback === null) {
            $all_feedback = $option_values === null && current_user_can('edit_post', $post->ID) ? Alify_CF_Feedback::get('post_' . $post->ID) : array();
            $feedback = $all_feedback[$group->ID] ?? array();
        }
        if (isset($feedback['schema']) && $feedback['schema'] !== hash('sha256', wp_json_encode($schema))) { $feedback = array(); }
        $current = array();
        foreach ($schema as $field) {
            $current[$field['key']] = $option_values !== null ? ($option_values[$field['name']] ?? $field['default']) : (metadata_exists('post', $post->ID, '_alify_cf_' . $field['name']) ? alify_get_field($field['name'], $post->ID) : $field['default']);
        }
        foreach (($feedback['values'] ?? array()) as $name => $attempt) {
            foreach ($schema as $field) { if ($field['name'] === $name) { $current[$field['key']] = $attempt; } }
        }
        echo '<div class="alify-values">';
        if ($feedback) { echo '<p class="alify-recovery-note" role="status">' . (!empty($feedback['omitted']) ? 'The attempted form was too large to recover. Review the field errors and enter corrections again.' : 'Unsaved input is shown below for correction. Saved values have not been replaced by rejected input.') . '</p>'; }
        if (!empty($feedback['password_omitted'])) { echo '<p class="description">Password fields and containers containing them show saved values, not recovered attempts. Re-enter unsaved changes in those fields.</p>'; }
        foreach ($schema as $field) {
            $name = $field['name'];
            $value = $current[$field['key']];
            $id = 'alify-' . $group->ID . '-' . $field['key'];
            $input = 'alify_values[' . $group->ID . '][' . $name . ']';
            $required = $field['required'] ? ' required' : '';
            echo '<div class="alify-value" data-field-key="' . esc_attr($field['key']) . '" data-field-type="' . esc_attr($field['type']) . '" data-condition="' . esc_attr(wp_json_encode($field['condition'] ?? null)) . '"' . (self::visible($field, $schema, $current) ? '' : ' hidden') . '><label class="alify-label" for="' . esc_attr($id) . '">' . esc_html($field['label']) . ($field['required'] ? ' <span class="alify-required">*</span>' : '') . '</label>';
            if ($field['instructions']) { echo '<p id="' . esc_attr($id . '-help') . '" class="description">' . esc_html($field['instructions']) . '</p>'; }
            $field_error = $feedback['errors'][$name] ?? '';
            self::control($field, $value, $id, $input, $field_error ? $id . '-error' : '');
            if ($field_error) { echo '<p class="alify-field-error" id="' . esc_attr($id . '-error') . '" role="alert" data-error-location="' . esc_attr(wp_json_encode(empty($feedback['omitted']) ? ($feedback['display_locations'][$name] ?? null) : null)) . '">' . esc_html($field_error) . '</p>'; }
            echo '</div>';
        }
        echo '<input type="hidden" name="alify_values[' . esc_attr($group->ID) . '][__group_complete]" value="1"></div>';
    }

    public static function control($field, $value, $id, $input, $error_id = '') {
        if (Alify_CF_Editor_Fields::presentation($field['type']) || $field['type'] === 'icon_picker') { Alify_CF_Editor_Fields::render($field, $value, $id, $input); return; }
        if (in_array($field['type'], array('oembed', 'google_map'), true)) { Alify_CF_External::render($field, $value, $id, $input); return; }
        if (Alify_CF_API::custom($field['type'])) { Alify_CF_API::custom_render($field, $value, $id, $input, $error_id); return; }
        $required = !empty($field['required']) ? ' required' : '';
        $attrs = ' id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" aria-describedby="' . esc_attr($id . '-help' . ($error_id ? ' ' . $error_id : '')) . '"' . ($error_id ? ' aria-invalid="true"' : '');
        $container_error = $error_id && in_array($field['type'], array('group', 'repeater', 'flexible'), true);
        if ($container_error) { echo '<div class="alify-container-error" role="group" aria-invalid="true" aria-describedby="' . esc_attr($error_id) . '">'; }
        switch ($field['type']) {
            case 'date_time':
                Alify_CF_Date_Time::render($field, $value, $id, $input, $error_id); break;
            case 'password':
                Alify_CF_Password::render($field, $value, $id, $input, $error_id); break;
            case 'range':
                Alify_CF_Range::render($field, $value, $id, $input, $error_id); break;
            case 'link':
                Alify_CF_Link::render($field, $value, $id, $input, $error_id); break;
            case 'gallery':
                Alify_CF_Gallery::render($field, $value, $id, $input); break;
            case 'flexible':
                Alify_CF_Flexible::render($field, $value, $id, $input); break;
            case 'group':
                Alify_CF_Group::render($field, $value, $id, $input); break;
            case 'repeater':
                Alify_CF_Repeater::render($field, $value, $id, $input); break;
            case 'image':
            case 'file':
                self::media_field($field, $value, $id, $input); break;
            case 'taxonomy':
            case 'user':
            case 'page_link':
                Alify_CF_Selector::render($field, $value, $id, $input, $error_id); break;
            case 'relationship':
                Alify_CF_Relationship::render($field, $value, $id, $input); break;
            case 'post_object':
                if (Alify_CF_Reference_Settings::multiple($field)) { Alify_CF_Relationship::render($field, $value, $id, $input); break; }
                Alify_CF_Post_Object::render($field, $value, $id, $input); break;
            case 'wysiwyg':
                echo '<div class="alify-rich"><button type="button" class="button" data-rich-enable>Enable Visual/Text editor</button><textarea class="alify-rich-text" data-rich-editor data-rich-toolbar="' . esc_attr($field['toolbar'] ?? 'full') . '" data-rich-mode="' . esc_attr($field['editor_mode'] ?? 'both') . '" data-rich-media="' . (!empty($field['media_buttons']) && current_user_can('upload_files') ? '1' : '0') . '"' . $attrs . ' aria-required="' . (!empty($field['required']) ? 'true' : 'false') . '" rows="8">' . esc_textarea(is_string($value) ? wp_kses_post($value) : '') . '</textarea><p class="description">WordPress post HTML only. Required fields need text; empty formatting or media-only content is not sufficient.</p></div>'; break;
            case 'textarea':
                echo '<textarea' . $attrs . $required . ' rows="4">' . esc_textarea(is_scalar($value) ? $value : '') . '</textarea>'; break;
            case 'select':
                Alify_CF_Choice::render_select($field, $value, $id, $input, $error_id); break;
            case 'button_group':
            case 'radio':
                $buttons = $field['type'] === 'button_group';
                echo '<fieldset class="alify-radio' . ($buttons ? ' alify-button-group' : (($field['layout'] ?? '') === 'horizontal' ? ' alify-choice-horizontal' : '')) . '" id="' . esc_attr($id) . '" aria-describedby="' . esc_attr($id . '-help' . ($error_id ? ' ' . $error_id : '')) . '"' . ($error_id ? ' aria-invalid="true"' : '') . '><legend class="screen-reader-text">' . esc_html($field['label']) . '</legend><input type="hidden" name="' . esc_attr($input) . '" value="">';
                if ($buttons) { echo '<div class="alify-button-options">'; }
                $index = 0;
                foreach (self::choices($field) as $key => $label) {
                    echo '<label class="alify-choice"><input type="radio" id="' . esc_attr($id . '-choice-' . $index++) . '" name="' . esc_attr($input) . '" value="' . esc_attr($key) . '" ' . checked(is_scalar($value) ? (string) $value : '', (string) $key, false) . $required . ' aria-describedby="' . esc_attr($id . '-help' . ($error_id ? ' ' . $error_id : '')) . '"' . ($error_id ? ' aria-invalid="true"' : '') . '> <span>' . esc_html($label) . '</span></label>';
                }
                if ($buttons) { echo '</div>'; }
                if (empty($field['required'])) { echo '<button type="button" class="button-link" data-radio-clear>Clear selection</button>'; }
                echo '</fieldset>'; break;
            case 'checkbox':
                echo '<fieldset class="' . (($field['layout'] ?? '') === 'horizontal' ? 'alify-choice-horizontal' : '') . '" id="' . esc_attr($id) . '"><legend class="screen-reader-text">' . esc_html($field['label']) . '</legend>';
                foreach (self::choices($field) as $key => $label) { echo '<label class="alify-choice"><input type="checkbox" name="' . esc_attr($input) . '[]" value="' . esc_attr($key) . '" ' . checked(in_array((string) $key, (array) $value, true), true, false) . '> ' . esc_html($label) . '</label>'; }
                echo '</fieldset>'; break;
            case 'color':
                $text = is_scalar($value) ? (string) $value : ''; $alpha = !empty($field['alpha']);
                $swatch = substr(Alify_CF_Field_Settings::color($field, $text) ?: '#000000', 0, 7);
                echo '<div class="alify-color" data-alpha="' . ($alpha ? '1' : '0') . '"><input type="text"' . $attrs . ' value="' . esc_attr($text) . '" placeholder="' . ($alpha ? '#rrggbb or #rrggbbaa' : '#rrggbb') . '" pattern="' . ($alpha ? '#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?' : '#[0-9a-fA-F]{6}') . '" maxlength="' . ($alpha ? '9' : '7') . '" autocomplete="off" spellcheck="false"' . $required . '><input type="color" data-color-picker value="' . esc_attr($swatch) . '" aria-label="' . esc_attr($field['label'] . ': choose color') . '">';
                if ($alpha) { echo '<label>Opacity (0–255) <input type="range" min="0" max="255" step="1" data-color-alpha value="' . (strlen($text) === 9 ? hexdec(substr($text, 7, 2)) : 255) . '"></label>'; }
                echo '<button type="button" class="button" data-color-apply>Apply swatch</button><button type="button" class="button" data-color-clear>Clear color</button>';
                foreach ($field['palette'] ?? array() as $color) { echo '<button type="button" class="button" data-color-value="' . esc_attr($color) . '" aria-label="' . esc_attr('Use color ' . $color) . '">' . esc_html($color) . '</button>'; }
                echo '</div>'; break;
            case 'date':
            case 'time':
                Alify_CF_Temporal_Format::render($field, $value, $id, $input, $error_id); break;
            case 'true_false':
                echo '<label class="alify-choice"><input type="checkbox"' . $attrs . ' value="1" ' . checked($value, '1', false) . $required . '> Enabled</label>'; break;
            default:
                echo '<input type="' . esc_attr($field['type']) . '"' . $attrs . ' value="' . esc_attr(is_scalar($value) ? $value : '') . '"' . ($field['type'] === 'number' ? ' step="any"' : '') . $required . '>'; break;
        }
        if ($container_error) { echo '</div>'; }
    }

    public static function clean_value($field, $value) {
        if (Alify_CF_Editor_Fields::presentation($field['type'])) { return ''; }
        if ($field['type'] === 'icon_picker') { return Alify_CF_Editor_Fields::clean_icon($field, $value); }
        if ($field['type'] === 'oembed') { return Alify_CF_External::video($value); }
        if ($field['type'] === 'google_map') { return Alify_CF_External::map($value); }
        if (Alify_CF_API::custom($field['type'])) { return Alify_CF_API::custom_clean($field, $value); }
        if (Alify_CF_Selector::supported($field)) { return Alify_CF_Selector::clean($field, $value); }
        if (Alify_CF_Choice::multiple($field)) { return Alify_CF_Choice::clean($field, $value); }
        if ($field['type'] === 'date_time') { return Alify_CF_Date_Time::clean($value); }
        if ($field['type'] === 'password') { return Alify_CF_Password::clean($value); }
        if ($field['type'] === 'range') { return Alify_CF_Range::clean($field, $value); }
        if ($field['type'] === 'relationship') { return Alify_CF_Relationship::clean($value, array(), false, $field); }
        if ($field['type'] === 'post_object') { return Alify_CF_Reference_Settings::multiple($field) ? Alify_CF_Relationship::clean($value, array(), false, $field) : Alify_CF_Post_Object::clean($value, null, false, $field); }
        if ($field['type'] === 'wysiwyg') { return Alify_CF_Rich_Text::clean($value); }
        if ($field['type'] === 'link') { return Alify_CF_Link::clean($value); }
        if ($field['type'] === 'flexible') { return Alify_CF_Flexible::clean($field, $value); }
        if ($field['type'] === 'group') { return Alify_CF_Group::clean($field, $value); }
        if ($field['type'] === 'gallery') { return Alify_CF_Field_Settings::constrain($field, Alify_CF_Gallery::clean($value)); }
        if ($field['type'] === 'repeater') { return Alify_CF_Repeater::clean($field, $value); }
        if ($field['type'] === 'checkbox') {
            return array_values(array_intersect(array_map('strval', array_keys(self::choices($field))), array_map('sanitize_text_field', is_array($value) ? $value : array())));
        }
        if (in_array($field['type'], array('radio', 'button_group'), true) && !is_string($value) && !is_int($value)) { return ''; }
        if (!is_scalar($value)) { return ''; }
        $value = (string) $value;
        switch ($field['type']) {
            case 'image':
            case 'file':
                if (!preg_match('/^[1-9][0-9]*$/', $value)) { return ''; }
                $attachment = get_post((int) $value);
                if (!$attachment || $attachment->post_type !== 'attachment' || $attachment->post_status === 'trash' || ($field['type'] === 'image' && !wp_attachment_is_image($attachment->ID))) { return ''; }
                return Alify_CF_Field_Settings::constrain($field, (int) $value);
            case 'textarea': return sanitize_textarea_field($value);
            case 'email': return is_email($value) ? sanitize_email($value) : '';
            case 'url': return esc_url_raw($value, array('http', 'https'));
            case 'number': return is_numeric($value) ? $value : '';
            case 'true_false': return $value === '1' ? '1' : '0';
            case 'button_group':
            case 'radio':
            case 'select': return array_key_exists($value, self::choices($field)) ? $value : '';
            case 'color': return Alify_CF_Field_Settings::color($field, $value);
            case 'time': return preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value) ? $value : '';
            case 'date':
                $date = strpos($value, chr(0)) === false ? DateTime::createFromFormat('!Y-m-d', $value) : false;
                return $date && $date->format('Y-m-d') === $value ? $value : '';
            default: return sanitize_text_field($value);
        }
    }

    /** Shared editor/import validation. No writes occur here. */
    public static function validate_schema($raw, $location, $id, &$errors) {
        if (!is_string($location)) { $errors[] = 'Choose a valid location.'; return array(); }
        if ((!isset(self::post_types()[$location]) && !Alify_CF_Options::is_location($location) && !isset(Alify_CF_Objects::locations()[$location])) || $location === self::GROUP) { $errors[] = 'Choose a valid post type or registered options page.'; }
        if (!is_array($raw) || array_values($raw) !== $raw || count($raw) > 200) { $errors[] = 'Invalid field schema. A maximum of 200 fields is supported.'; return array(); }
        $fields = array(); $names = array(); $keys = array();
        foreach ($raw as $field) {
            if (!is_array($field)) { $errors[] = 'Invalid field configuration.'; continue; }
            foreach (array('name', 'type', 'label', 'key', 'instructions', 'choices') as $property) {
                if (isset($field[$property]) && !is_string($field[$property])) { $errors[] = 'Invalid field setting: ' . $property; continue 2; }
            }
            $name = $field['name'] ?? '';
            $type = $field['type'] ?? 'text';
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $name) || isset($names[$name]) || !isset(self::types()[$type]) || trim($field['label'] ?? '') === '') { $errors[] = 'Each field needs a label, supported type, and unique valid name: ' . $name; continue; }
            $names[$name] = true;
            $key = sanitize_key($field['key'] ?? '') ?: 'field_' . wp_generate_uuid4();
            if (isset($keys[$key])) { $errors[] = 'Duplicate internal field key.'; continue; }
            $keys[$key] = true;
            $clean = array('key' => $key, 'name' => $name, 'label' => sanitize_text_field($field['label']), 'type' => $type, 'instructions' => sanitize_textarea_field($field['instructions'] ?? ''), 'required' => !empty($field['required']), 'choices' => sanitize_textarea_field($field['choices'] ?? ''), 'default' => '');
            $clean = array_merge($clean, Alify_CF_Temporal_Format::settings($field, $errors), Alify_CF_Choice::settings($field, $errors), Alify_CF_Field_Settings::settings($field, $errors), Alify_CF_Reference_Settings::settings($field, $errors), Alify_CF_Selector::settings($field, $errors), Alify_CF_API::settings($field, $errors), Alify_CF_Editor_Fields::settings($field, $errors));
            if ($type === 'range') { $clean = array_merge($clean, Alify_CF_Range::settings($field, $errors)); }
            if ($type === 'flexible') { $clean['layouts'] = Alify_CF_Flexible::schema($field['layouts'] ?? null, $errors); }
            if ($type === 'repeater') {
                $limits = Alify_CF_Repeater::limits($field, $errors);
                if (array_key_exists('min_rows', $field) || array_key_exists('max_rows', $field)) { $clean = array_merge($clean, $limits); }
            }
            if (in_array($type, array('repeater', 'group'), true)) { $clean['sub_fields'] = Alify_CF_Repeater::schema($field['sub_fields'] ?? array(), $errors); }
            $clean['default'] = in_array($type, array('post_object', 'password'), true) ? '' : (in_array($type, array('repeater', 'gallery', 'group', 'flexible', 'link', 'relationship', 'google_map'), true) ? array() : self::clean_value($clean, $field['default'] ?? ''));
            if (Alify_CF_Selector::supported($clean)) { $clean['default'] = Alify_CF_Selector::multiple($clean) ? array() : ''; if (($field['default'] ?? '') !== '' && ($field['default'] ?? array()) !== array()) { $errors[] = 'Selector defaults are not supported.'; } }
            if ($type === 'oembed' && ($field['default'] ?? '') !== '' && $clean['default'] === '') { $errors[] = 'Unsupported video default.'; }
            if ($type === 'link') { $clean['default'] = Alify_CF_Field_Settings::link_default($field['default'] ?? '', $errors); }
            if ($type === 'color' && ($field['default'] ?? '') !== '' && $clean['default'] === '') { $errors[] = 'Color default must be valid hex.'; }
            if ($type === 'checkbox' || Alify_CF_Choice::multiple($clean)) { $clean['default'] = Alify_CF_Choice::defaults($clean, $field['default'] ?? '', $errors); }
            if ($type === 'date_time' && ($field['default'] ?? '') !== '' && $clean['default'] === '') { $errors[] = 'Date Time default must be a valid wall-clock date/time.'; }
            if ($type === 'password') { $clean['choices'] = ''; if (($field['default'] ?? '') !== '') { $errors[] = 'Password defaults are not supported. Clear the default before saving.'; } }
            if ($type === 'range' && ($field['default'] ?? '') !== '' && $clean['default'] === '') { $errors[] = 'Range default must be within bounds and aligned to its step: ' . $name; }
            $clean['condition'] = Alify_CF_Conditions::clean($field['condition'] ?? null, $errors, $name);
            $fields[] = $clean;
        }
        foreach ($fields as $field) {
            if (!$field['condition']) { continue; }
            foreach (Alify_CF_Conditions::rules($field['condition']) as $rule) {
                $source = null;
                foreach ($fields as $candidate) {
                    if ($candidate['key'] === $rule['field']) { $source = $candidate; break; }
                }
                if (!$source || Alify_CF_Editor_Fields::presentation($source['type']) || Alify_CF_API::custom($source['type']) || Alify_CF_Selector::supported($source) || in_array($source['type'], array('repeater', 'group', 'flexible', 'link', 'wysiwyg', 'relationship', 'password', 'google_map'), true) || Alify_CF_Reference_Settings::multiple($source) || $source['key'] === $field['key'] || $source['condition']) {
                    $errors[] = $field['label'] . ': choose an unconditional field from this group as the condition source (no self-reference or chains).';
                }
            }
        }
        // Reserve names across published and draft groups on the same post type.
        $others = get_posts(array('post_type' => self::GROUP, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => -1, 'exclude' => array($id), 'meta_key' => self::LOCATION, 'meta_value' => $location));
        foreach (Alify_CF_API::groups($location) as $group) { if ($group['id'] !== $id) { $others[] = (object) array('ID' => $group['id'], 'post_title' => $group['title']); } }
        foreach ($others as $other) {
            foreach (self::schema($other->ID) as $field) {
                if (isset($names[$field['name']])) { $errors[] = 'Field name "' . $field['name'] . '" is already used by group "' . $other->post_title . '". Use a different name.'; }
            }
        }
        foreach ($fields as $field) { if ((!empty($field['api_read']) || !empty($field['public_output'])) && (Alify_CF_Password::contains($field) || Alify_CF_API::contains_custom($field))) { $errors[] = 'Password/custom-containing fields cannot be exposed for reading/output.'; } }
        return $fields;
    }

    public static function save($id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision($id) || !current_user_can('edit_post', $id)) { return; }
        if ($post->post_type === self::GROUP) {
            if (!current_user_can('manage_options') || !isset($_POST['alify_schema_nonce'], $_POST['alify_schema']) || !is_string($_POST['alify_schema_nonce']) || !is_string($_POST['alify_schema']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['alify_schema_nonce'])), 'alify_schema_' . $id)) { return; }
            $raw = json_decode(wp_unslash($_POST['alify_schema']), true);
            $location_raw = $_POST['alify_location'] ?? 'post';
            $location_raw = is_string($location_raw) ? wp_unslash($location_raw) : '';
            $location = (strpos($location_raw, 'options:') === 0 || strpos($location_raw, 'term:') === 0) ? $location_raw : sanitize_key($location_raw);
            $errors = array();
            $rules = Alify_CF_Location_Rules::clean(isset($_POST['alify_location_rules']) ? wp_unslash($_POST['alify_location_rules']) : get_post_meta($id, '_alify_cf_location_rules', true), $errors);
            $fields = self::validate_schema($raw, $location, $id, $errors);
            if ($errors) { self::error($id, array_merge(array('Field configuration was not saved. Existing fields and location were retained.'), $errors)); return; }
            update_post_meta($id, self::SCHEMA, wp_slash($fields));
            update_post_meta($id, self::LOCATION, $location);
            update_post_meta($id, '_alify_cf_location_rules', wp_slash($rules));
            return;
        }
        $errors = array(); $feedback = Alify_CF_Feedback::get('post_' . $id);
        foreach (self::groups($post->post_type, $id) as $group) {
            $nonce = $_POST['alify_values_nonce_' . $group->ID] ?? '';
            if (!is_string($nonce) || !wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'alify_values_' . $id . '_' . $group->ID)) { continue; }
            $values = isset($_POST['alify_values'][$group->ID]) && is_array($_POST['alify_values'][$group->ID]) ? wp_unslash($_POST['alify_values'][$group->ID]) : array();
            if (($values['__group_complete'] ?? '') !== '1') {
                $errors[] = $group->post_title . ': incomplete field group; previous values retained. Reload the editor.';
                continue;
            }
            $schema = self::schema($group->ID);
            $previous = array();
            foreach ($schema as $field) { $previous[$field['name']] = alify_get_field($field['name'], $id); }
            $field_errors = array();
            $updates = self::validate_values($schema, $values, $previous, $errors, $field_errors, $locations);
            if ($field_errors) { $feedback[$group->ID] = Alify_CF_Feedback::group($schema, $values, $previous, $field_errors, $locations); }
            else { unset($feedback[$group->ID]); }
            foreach ($updates as $name => $value) { update_post_meta($id, '_alify_cf_' . $name, wp_slash($value)); }
        }
        Alify_CF_Feedback::put('post_' . $id, $feedback);
        if ($errors) { self::error($id, $errors); }
    }

    public static function validate_values($schema, $values, $previous, &$errors, &$field_errors = null, &$locations = null) {
        $field_errors = array(); $locations = array();
        $updates = array();
        $submitted_values = array();
        foreach ($schema as $field) {
            $submitted_values[$field['key']] = self::clean_value($field, $values[$field['name']] ?? '');
        }
        foreach ($schema as $field) {
            if (Alify_CF_Editor_Fields::presentation($field['type'])) { continue; }
            // Hidden values are preserved, even if a forged request supplies them.
            if (!self::visible($field, $schema, $submitted_values)) { continue; }
            $issue = ''; $location = null;
            if (Alify_CF_Selector::supported($field)) {
                $value = Alify_CF_Selector::clean($field, $values[$field['name']] ?? '', $previous[$field['name']] ?? null, true);
            } elseif ($field['type'] === 'flexible') {
                $value = Alify_CF_Flexible::clean($field, $values[$field['name']] ?? '', $previous[$field['name']] ?? null, true, $issue, $location);
            } elseif ($field['type'] === 'group') {
                $value = Alify_CF_Group::clean($field, $values[$field['name']] ?? '', $previous[$field['name']] ?? null, true, $issue, $location);
            } elseif ($field['type'] === 'repeater') {
                $value = Alify_CF_Repeater::clean($field, $values[$field['name']] ?? '', $previous[$field['name']] ?? null, true, $issue, $location);
            } elseif ($field['type'] === 'relationship' || Alify_CF_Reference_Settings::multiple($field)) {
                $value = Alify_CF_Relationship::clean($values[$field['name']] ?? '', $previous[$field['name']] ?? array(), true, $field);
            } elseif ($field['type'] === 'post_object') {
                $value = Alify_CF_Post_Object::clean($values[$field['name']] ?? '', $previous[$field['name']] ?? null, true, $field);
            } elseif ($field['type'] === 'gallery') {
                $value = Alify_CF_Gallery::clean($values[$field['name']] ?? '', $previous[$field['name']] ?? array(), true);
            } else {
                $value = self::clean_value($field, $values[$field['name']] ?? '');
            }
            $submitted = $values[$field['name']] ?? '';
            $value = Alify_CF_Field_Settings::constrain($field, $value);
            $empty = $value === '' || $value === array() || ($field['type'] === 'true_false' && $value !== '1') || ($field['type'] === 'group' && Alify_CF_Group::empty_value($field, $value));
            $invalid = (in_array($field['type'], array('repeater', 'gallery', 'group', 'flexible', 'link', 'relationship', 'google_map'), true) && $value === '') || ($submitted !== '' && $submitted !== array() && $value === '');
            if ($field['type'] === 'range' && array_key_exists($field['name'], $values) && $values[$field['name']] !== '' && Alify_CF_Range::units($values[$field['name']]) === null) { $invalid = true; }
            if ($field['type'] === 'color' && array_key_exists($field['name'], $values) && !is_string($values[$field['name']])) { $invalid = true; }
            if (Alify_CF_Choice::multiple($field) && $value === '') { $invalid = true; }
            if ($field['type'] === 'date_time' && array_key_exists($field['name'], $values) && !is_string($values[$field['name']])) { $invalid = true; }
            if ($field['type'] === 'password' && array_key_exists($field['name'], $values) && !is_string($values[$field['name']])) { $invalid = true; }
            if ($field['type'] === 'oembed' && !is_string($submitted)) { $invalid = true; }
            if ($field['type'] === 'wysiwyg') { $invalid = !is_string($submitted); }
            if (Alify_CF_Selector::supported($field) && !Alify_CF_Selector::multiple($field) && array_key_exists($field['name'], $values) && !is_string($submitted) && !is_int($submitted)) { $invalid = true; }
            if (Alify_CF_Selector::multiple($field) && $value === '') { $invalid = true; }
            if (Alify_CF_Reference_Settings::multiple($field) && $value === '') { $invalid = true; }
            if (!Alify_CF_Reference_Settings::multiple($field) && in_array($field['type'], array('post_object', 'radio', 'button_group'), true) && !is_string($submitted) && !is_int($submitted)) { $invalid = true; }
            if (($field['required'] && $empty) || $invalid) {
                if (!$issue && $field['type'] === 'group' && $field['required'] && $empty) { $issue = 'Fill at least one subfield in this Group.'; }
                if (!$issue && $field['type'] === 'flexible' && $field['required'] && $empty) { $issue = 'Add at least one layout row.'; }
                $message = ($issue ?: ($field['required'] && $empty ? 'A valid value is required.' : 'Invalid value.')) . ' Previous saved value retained.';
                $field_errors[$field['name']] = $message;
                if ($location) { $locations[$field['name']] = $location; }
                $errors[] = $field['label'] . ': ' . $message;
                continue;
            }
            if (in_array($field['type'], array('image', 'file'), true) && $value !== '' && (string) ($previous[$field['name']] ?? null) !== (string) $value && !current_user_can('edit_post', $value)) {
                $message = 'You cannot select this attachment. Previous saved value retained.';
                $field_errors[$field['name']] = $message;
                if ($location) { $locations[$field['name']] = $location; }
                $errors[] = $field['label'] . ': ' . $message;
                continue;
            }
            $updates[$field['name']] = $value;
        }
        return $updates;
    }

    private static function error($id, $messages) {
        set_transient('alify_cf_errors_' . get_current_user_id() . '_' . $id, $messages, 120);
    }

    public static function notices() {
        $screen = get_current_screen();
        $id = isset($_GET['post']) && is_scalar($_GET['post']) ? absint($_GET['post']) : 0;
        if (!$screen || $screen->base !== 'post' || !$id || !current_user_can('edit_post', $id)) { return; }
        $publication_key = 'alify_cf_publication_' . get_current_user_id() . '_' . $id;
        if (get_transient($publication_key)) {
            delete_transient($publication_key);
            echo '<div class="notice notice-error" role="alert"><p><strong>Alify: publication/scheduling was not accepted.</strong> Correct the fields and try again. The previous publication status was retained (new posts remain drafts).</p></div>';
        }
        $key = 'alify_cf_errors_' . get_current_user_id() . '_' . $id;
        $errors = get_transient($key);
        if (!is_array($errors) || !$errors) { return; }
        delete_transient($key);
        echo '<div class="notice notice-error" role="alert"><p><strong>Alify Custom Field — some changes were not saved</strong></p><ul>';
        foreach ($errors as $error) { echo '<li>' . esc_html($error) . '</li>'; }
        echo '</ul><p>The post itself may still have been saved or published. Correct the fields and save again.</p></div>';
    }

    private static function media_field($field, $value, $id, $input) {
        $attachment = $value ? get_post((int) $value) : null;
        $valid = $attachment && $attachment->post_type === 'attachment' && $attachment->post_status !== 'trash';
        $url = $valid && $field['type'] === 'image' ? wp_get_attachment_image_url($attachment->ID, $field['preview_size'] ?? 'thumbnail') : '';
        echo '<div class="alify-media" data-type="' . esc_attr($field['type']) . '"' . Alify_CF_Field_Settings::attributes($field) . '><input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" value="' . esc_attr($value) . '">';
        echo '<div class="alify-media-preview" aria-live="polite"><img alt="" src="' . esc_url($url ?: '') . '"' . ($url ? '' : ' hidden') . '><span>' . esc_html($valid ? $attachment->post_title : ($value ? 'Attachment unavailable — remove or replace it.' : 'No ' . $field['type'] . ' selected')) . '</span></div>';
        if (current_user_can('upload_files')) { echo '<button type="button" class="button" data-media="choose">Choose ' . esc_html($field['type']) . '</button> '; }
        echo '<button type="button" class="button-link-delete" data-media="remove"' . ($value ? '' : ' hidden') . '>Remove</button></div>';
    }

    public static function columns($columns) {
        return array_slice($columns, 0, 2, true) + array('alify_location' => 'Location', 'alify_fields' => 'Fields') + array_slice($columns, 2, null, true);
    }
    public static function column($column, $id) {
        if ($column === 'alify_fields') { echo esc_html(count(self::schema($id))); }
        if ($column === 'alify_location') {
            $location = get_post_meta($id, self::LOCATION, true);
            foreach (Alify_CF_Options::pages() as $slug => $title) {
                if ($location === Alify_CF_Options::location($slug)) { echo esc_html('Options Page: ' . $title); return; }
            }
            $type = get_post_type_object($location); echo esc_html($type ? $type->labels->singular_name : 'Not configured');
        }
    }
    public static function messages($messages) {
        $messages[self::GROUP] = array(1 => 'Field group updated.', 6 => 'Field group published. Your fields are now available at the selected location.', 7 => 'Field group saved.', 10 => 'Field group draft updated. Publish it to activate its fields.');
        return $messages;
    }
}
