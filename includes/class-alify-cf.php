<?php
if (!defined('ABSPATH')) { exit; }

final class Alify_CF {
    const GROUP = 'alify_field_group';
    const SCHEMA = '_alify_cf_schema';
    const LOCATION = '_alify_cf_location';

    public static function boot() {
        add_action('init', array(__CLASS__, 'register'));
        add_action('add_meta_boxes', array(__CLASS__, 'boxes'));
        add_action('save_post', array(__CLASS__, 'save'), 10, 2);
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_filter('manage_' . self::GROUP . '_posts_columns', array(__CLASS__, 'columns'));
        add_action('manage_' . self::GROUP . '_posts_custom_column', array(__CLASS__, 'column'), 10, 2);
        add_filter('post_updated_messages', array(__CLASS__, 'messages'));
    }

    public static function types() {
        return array('text' => 'Text', 'textarea' => 'Text Area', 'number' => 'Number', 'email' => 'Email', 'url' => 'URL', 'select' => 'Select', 'checkbox' => 'Checkbox', 'true_false' => 'True / False', 'date' => 'Date');
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

    public static function groups($post_type) {
        return get_posts(array('post_type' => self::GROUP, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC', 'meta_key' => self::LOCATION, 'meta_value' => $post_type));
    }

    public static function schema($id) {
        $schema = get_post_meta($id, self::SCHEMA, true);
        return is_array($schema) ? $schema : array();
    }

    public static function assets() {
        $screen = get_current_screen();
        if (!$screen || (!$screen->post_type) || ('post' !== $screen->base && 'edit' !== $screen->base)) { return; }
        if ($screen->post_type !== self::GROUP && !self::groups($screen->post_type)) { return; }
        wp_enqueue_style('alify-cf', plugins_url('assets/admin.css', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION);
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
        foreach (self::groups($screen->post_type) as $group) {
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
        echo '<div class="alify-location"><div><strong>Show this field group when</strong><p>Published groups appear on the selected content type.</p></div><label>Post Type <select name="alify_location">';
        foreach (self::post_types() as $type) {
            if (in_array($type->name, array(self::GROUP, 'attachment'), true)) { continue; }
            echo '<option value="' . esc_attr($type->name) . '" ' . selected($location, $type->name, false) . '>' . esc_html($type->labels->singular_name) . '</option>';
        }
        echo '</select></label></div>';
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

    public static function values($post, $group) {
        wp_nonce_field('alify_values_' . $post->ID . '_' . $group->ID, 'alify_values_nonce_' . $group->ID);
        foreach (self::schema($group->ID) as $field) {
            $name = $field['name'];
            $value = metadata_exists('post', $post->ID, '_alify_cf_' . $name) ? alify_get_field($name, $post->ID) : $field['default'];
            $id = 'alify-' . $group->ID . '-' . $field['key'];
            $input = 'alify_values[' . $group->ID . '][' . $name . ']';
            $required = $field['required'] ? ' required' : '';
            echo '<div class="alify-value"><label class="alify-label" for="' . esc_attr($id) . '">' . esc_html($field['label']) . ($field['required'] ? ' <span class="alify-required">*</span>' : '') . '</label>';
            if ($field['instructions']) { echo '<p id="' . esc_attr($id . '-help') . '" class="description">' . esc_html($field['instructions']) . '</p>'; }
            $attrs = ' id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" aria-describedby="' . esc_attr($id . '-help') . '"';
            switch ($field['type']) {
                case 'textarea':
                    echo '<textarea' . $attrs . $required . ' rows="4">' . esc_textarea(is_scalar($value) ? $value : '') . '</textarea>'; break;
                case 'select':
                    echo '<select' . $attrs . $required . '><option value="">Select an option</option>';
                    foreach (self::choices($field) as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected($value, $key, false) . '>' . esc_html($label) . '</option>'; }
                    echo '</select>'; break;
                case 'checkbox':
                    echo '<fieldset id="' . esc_attr($id) . '"><legend class="screen-reader-text">' . esc_html($field['label']) . '</legend>';
                    foreach (self::choices($field) as $key => $label) { echo '<label class="alify-choice"><input type="checkbox" name="' . esc_attr($input) . '[]" value="' . esc_attr($key) . '" ' . checked(in_array((string) $key, (array) $value, true), true, false) . '> ' . esc_html($label) . '</label>'; }
                    echo '</fieldset>'; break;
                case 'true_false':
                    echo '<label class="alify-choice"><input type="checkbox"' . $attrs . ' value="1" ' . checked($value, '1', false) . $required . '> Enabled</label>'; break;
                default:
                    echo '<input type="' . esc_attr($field['type']) . '"' . $attrs . ' value="' . esc_attr(is_scalar($value) ? $value : '') . '"' . ($field['type'] === 'number' ? ' step="any"' : '') . $required . '>'; break;
            }
            echo '</div>';
        }
    }

    private static function clean_value($field, $value) {
        if ($field['type'] === 'checkbox') {
            return array_values(array_intersect(array_map('strval', array_keys(self::choices($field))), array_map('sanitize_text_field', is_array($value) ? $value : array())));
        }
        if (!is_scalar($value)) { return ''; }
        $value = (string) $value;
        switch ($field['type']) {
            case 'textarea': return sanitize_textarea_field($value);
            case 'email': return is_email($value) ? sanitize_email($value) : '';
            case 'url': return esc_url_raw($value, array('http', 'https'));
            case 'number': return is_numeric($value) ? $value : '';
            case 'true_false': return $value === '1' ? '1' : '0';
            case 'select': return array_key_exists($value, self::choices($field)) ? $value : '';
            case 'date':
                $date = DateTime::createFromFormat('!Y-m-d', $value);
                return $date && $date->format('Y-m-d') === $value ? $value : '';
            default: return sanitize_text_field($value);
        }
    }

    public static function save($id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision($id) || !current_user_can('edit_post', $id)) { return; }
        if ($post->post_type === self::GROUP) {
            if (!current_user_can('manage_options') || !isset($_POST['alify_schema_nonce'], $_POST['alify_schema']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['alify_schema_nonce'])), 'alify_schema_' . $id)) { return; }
            $raw = json_decode(wp_unslash($_POST['alify_schema']), true);
            if (!is_array($raw) || count($raw) > 200) { return; }
            $fields = array(); $names = array();
            foreach ($raw as $field) {
                if (!is_array($field)) { continue; }
                $name = sanitize_key($field['name'] ?? '');
                $type = sanitize_key($field['type'] ?? 'text');
                if (!preg_match('/^[a-z][a-z0-9_]*$/', $name) || isset($names[$name]) || !isset(self::types()[$type])) { continue; }
                $names[$name] = true;
                $clean = array('key' => sanitize_key($field['key'] ?? '') ?: 'field_' . wp_generate_uuid4(), 'name' => $name, 'label' => sanitize_text_field($field['label'] ?? $name), 'type' => $type, 'instructions' => sanitize_textarea_field($field['instructions'] ?? ''), 'required' => !empty($field['required']), 'choices' => sanitize_textarea_field($field['choices'] ?? ''), 'default' => '');
                $clean['default'] = self::clean_value($clean, $field['default'] ?? '');
                $fields[] = $clean;
            }
            update_post_meta($id, self::SCHEMA, $fields);
            $location = sanitize_key(wp_unslash($_POST['alify_location'] ?? 'post'));
            if (isset(self::post_types()[$location]) && !in_array($location, array(self::GROUP, 'attachment'), true)) { update_post_meta($id, self::LOCATION, $location); }
            return;
        }
        foreach (self::groups($post->post_type) as $group) {
            $nonce = $_POST['alify_values_nonce_' . $group->ID] ?? '';
            if (!is_string($nonce) || !wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'alify_values_' . $id . '_' . $group->ID)) { continue; }
            $values = isset($_POST['alify_values'][$group->ID]) && is_array($_POST['alify_values'][$group->ID]) ? wp_unslash($_POST['alify_values'][$group->ID]) : array();
            foreach (self::schema($group->ID) as $field) {
                $value = self::clean_value($field, $values[$field['name']] ?? '');
                // Required values are enforced server-side too; retain the previous value on invalid submissions.
                if ($field['required'] && ($value === '' || $value === array() || ($field['type'] === 'true_false' && $value !== '1'))) { continue; }
                update_post_meta($id, '_alify_cf_' . $field['name'], $value);
            }
        }
    }

    public static function columns($columns) {
        return array_slice($columns, 0, 2, true) + array('alify_location' => 'Location', 'alify_fields' => 'Fields') + array_slice($columns, 2, null, true);
    }
    public static function column($column, $id) {
        if ($column === 'alify_fields') { echo esc_html(count(self::schema($id))); }
        if ($column === 'alify_location') { $type = get_post_type_object(get_post_meta($id, self::LOCATION, true)); echo esc_html($type ? $type->labels->singular_name : 'Not configured'); }
    }
    public static function messages($messages) {
        $messages[self::GROUP] = array(1 => 'Field group updated.', 6 => 'Field group published. Your fields are now available on the selected post type.', 7 => 'Field group saved.', 10 => 'Field group draft updated. Publish it to activate its fields.');
        return $messages;
    }
}
