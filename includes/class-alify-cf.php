<?php
if (!defined('ABSPATH')) { exit; }
require_once __DIR__ . '/class-alify-cf-repeater.php';
require_once __DIR__ . '/class-alify-cf-gallery.php';
require_once __DIR__ . '/class-alify-cf-options.php';
require_once __DIR__ . '/class-alify-cf-feedback.php';
require_once __DIR__ . '/class-alify-cf-publication.php';

final class Alify_CF {
    const GROUP = 'alify_field_group';
    const SCHEMA = '_alify_cf_schema';
    const LOCATION = '_alify_cf_location';

    public static function boot() {
        Alify_CF_Options::boot();
        Alify_CF_Publication::boot();
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
        return array('text' => 'Text', 'textarea' => 'Text Area', 'number' => 'Number', 'email' => 'Email', 'url' => 'URL', 'select' => 'Select', 'checkbox' => 'Checkbox', 'true_false' => 'True / False', 'date' => 'Date', 'image' => 'Image', 'file' => 'File', 'repeater' => 'Repeater', 'gallery' => 'Gallery');
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
        if ($screen->post_type !== self::GROUP && $screen->base === 'post') {
            if (current_user_can('upload_files')) { wp_enqueue_media(); }
            wp_enqueue_script('alify-cf-gallery', plugins_url('assets/gallery.js', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION, true);
            wp_enqueue_script('alify-cf-values', plugins_url('assets/values.js', ALIFY_CF_FILE), array('alify-cf-gallery'), ALIFY_CF_VERSION, true);
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
        echo '<div class="alify-location"><div><strong>Show this field group when</strong><p>Published groups appear on the selected post type or options page.</p></div><label>Location <select name="alify_location">';
        foreach (self::post_types() as $type) {
            if (in_array($type->name, array(self::GROUP, 'attachment'), true)) { continue; }
            echo '<option value="' . esc_attr($type->name) . '" ' . selected($location, $type->name, false) . '>' . esc_html($type->labels->singular_name) . '</option>';
        }
        foreach (Alify_CF_Options::pages() as $slug => $title) {
            echo '<option value="' . esc_attr(Alify_CF_Options::location($slug)) . '" ' . selected($location, Alify_CF_Options::location($slug), false) . '>Options Page: ' . esc_html($title) . '</option>';
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

    /** One rule, referencing an unconditional sibling by stable key. */
    public static function visible($field, $schema, $values) {
        $rule = $field['condition'] ?? null;
        if (!$rule) { return true; }
        $source = null;
        foreach ($schema as $candidate) {
            if ($candidate['key'] === $rule['field']) { $source = $candidate; break; }
        }
        if (!$source || $source['type'] === 'repeater' || !empty($source['condition'])) { return false; }
        $value = $values[$source['key']] ?? '';
        $items = is_array($value) ? array_map('strval', $value) : array((string) $value);
        $empty = !$items || $items === array('') || ($source['type'] === 'true_false' && $items === array('0'));
        $matches = in_array((string) $rule['value'], $items, true);
        switch ($rule['operator']) {
            case 'equals': return $matches;
            case 'not_equals': return !$matches;
            case 'empty': return $empty;
            case 'not_empty': return !$empty;
            default: return false;
        }
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
            if ($field_error) { echo '<p class="alify-field-error" id="' . esc_attr($id . '-error') . '" role="alert">' . esc_html($field_error) . '</p>'; }
            echo '</div>';
        }
        echo '<input type="hidden" name="alify_values[' . esc_attr($group->ID) . '][__group_complete]" value="1"></div>';
    }

    public static function control($field, $value, $id, $input, $error_id = '') {
        $required = !empty($field['required']) ? ' required' : '';
        $attrs = ' id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" aria-describedby="' . esc_attr($id . '-help' . ($error_id ? ' ' . $error_id : '')) . '"' . ($error_id ? ' aria-invalid="true"' : '');
        switch ($field['type']) {
            case 'gallery':
                Alify_CF_Gallery::render($field, $value, $id, $input); break;
            case 'repeater':
                Alify_CF_Repeater::render($field, $value, $id, $input); break;
            case 'image':
            case 'file':
                self::media_field($field, $value, $id, $input); break;
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
    }

    public static function clean_value($field, $value) {
        if ($field['type'] === 'gallery') { return Alify_CF_Gallery::clean($value); }
        if ($field['type'] === 'repeater') { return Alify_CF_Repeater::clean($field, $value); }
        if ($field['type'] === 'checkbox') {
            return array_values(array_intersect(array_map('strval', array_keys(self::choices($field))), array_map('sanitize_text_field', is_array($value) ? $value : array())));
        }
        if (!is_scalar($value)) { return ''; }
        $value = (string) $value;
        switch ($field['type']) {
            case 'image':
            case 'file':
                if (!preg_match('/^[1-9][0-9]*$/', $value)) { return ''; }
                $attachment = get_post((int) $value);
                if (!$attachment || $attachment->post_type !== 'attachment' || $attachment->post_status === 'trash' || ($field['type'] === 'image' && !wp_attachment_is_image($attachment->ID))) { return ''; }
                return (int) $value;
            case 'textarea': return sanitize_textarea_field($value);
            case 'email': return is_email($value) ? sanitize_email($value) : '';
            case 'url': return esc_url_raw($value, array('http', 'https'));
            case 'number': return is_numeric($value) ? $value : '';
            case 'true_false': return $value === '1' ? '1' : '0';
            case 'select': return array_key_exists($value, self::choices($field)) ? $value : '';
            case 'date':
                $date = strpos($value, chr(0)) === false ? DateTime::createFromFormat('!Y-m-d', $value) : false;
                return $date && $date->format('Y-m-d') === $value ? $value : '';
            default: return sanitize_text_field($value);
        }
    }

    public static function save($id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision($id) || !current_user_can('edit_post', $id)) { return; }
        if ($post->post_type === self::GROUP) {
            if (!current_user_can('manage_options') || !isset($_POST['alify_schema_nonce'], $_POST['alify_schema']) || !is_string($_POST['alify_schema_nonce']) || !is_string($_POST['alify_schema']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['alify_schema_nonce'])), 'alify_schema_' . $id)) { return; }
            $raw = json_decode(wp_unslash($_POST['alify_schema']), true);
            $location_raw = $_POST['alify_location'] ?? 'post';
            $location_raw = is_string($location_raw) ? wp_unslash($location_raw) : '';
            $location = strpos($location_raw, 'options:') === 0 ? $location_raw : sanitize_key($location_raw);
            $errors = array();
            if ((!isset(self::post_types()[$location]) && !Alify_CF_Options::is_location($location)) || in_array($location, array(self::GROUP, 'attachment'), true)) { $errors[] = 'Choose a valid post type or registered options page.'; }
            if (!is_array($raw) || count($raw) > 200) { self::error($id, array('Invalid field schema. A maximum of 200 fields is supported. Existing configuration was retained.')); return; }
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
                if ($type === 'repeater') { $clean['sub_fields'] = Alify_CF_Repeater::schema($field['sub_fields'] ?? array(), $errors); }
                $clean['default'] = in_array($type, array('repeater', 'gallery'), true) ? array() : self::clean_value($clean, $field['default'] ?? '');
                $clean['condition'] = null;
                if (!empty($field['condition'])) {
                    $rule = $field['condition'];
                    if (!is_array($rule) || !isset($rule['field'], $rule['operator'], $rule['value']) || !is_string($rule['field']) || !is_string($rule['operator']) || !is_string($rule['value']) || !in_array($rule['operator'], array('equals', 'not_equals', 'empty', 'not_empty'), true)) {
                        $errors[] = 'Invalid conditional rule for ' . $name;
                    } else {
                        $clean['condition'] = array('field' => sanitize_key($rule['field']), 'operator' => $rule['operator'], 'value' => sanitize_text_field($rule['value']));
                    }
                }
                $fields[] = $clean;
            }
            foreach ($fields as $field) {
                if (!$field['condition']) { continue; }
                $source = null;
                foreach ($fields as $candidate) {
                    if ($candidate['key'] === $field['condition']['field']) { $source = $candidate; break; }
                }
                if (!$source || $source['type'] === 'repeater' || $source['key'] === $field['key'] || $source['condition']) {
                    $errors[] = $field['label'] . ': choose an unconditional field from this group as the condition source (no self-reference or chains).';
                }
            }
            // Reserve names across published and draft groups on the same post type.
            $others = get_posts(array('post_type' => self::GROUP, 'post_status' => array('publish', 'draft', 'pending', 'private', 'future'), 'numberposts' => -1, 'exclude' => array($id), 'meta_key' => self::LOCATION, 'meta_value' => $location));
            foreach ($others as $other) {
                foreach (self::schema($other->ID) as $field) {
                    if (isset($names[$field['name']])) { $errors[] = 'Field name "' . $field['name'] . '" is already used by group "' . $other->post_title . '". Use a different name.'; }
                }
            }
            if ($errors) { self::error($id, array_merge(array('Field configuration was not saved. Existing fields and location were retained.'), $errors)); return; }
            update_post_meta($id, self::SCHEMA, wp_slash($fields));
            update_post_meta($id, self::LOCATION, $location);
            return;
        }
        $errors = array(); $feedback = Alify_CF_Feedback::get('post_' . $id);
        foreach (self::groups($post->post_type) as $group) {
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
            $updates = self::validate_values($schema, $values, $previous, $errors, $field_errors);
            if ($field_errors) { $feedback[$group->ID] = Alify_CF_Feedback::group($schema, $values, $previous, $field_errors); }
            else { unset($feedback[$group->ID]); }
            foreach ($updates as $name => $value) { update_post_meta($id, '_alify_cf_' . $name, wp_slash($value)); }
        }
        Alify_CF_Feedback::put('post_' . $id, $feedback);
        if ($errors) { self::error($id, $errors); }
    }

    public static function validate_values($schema, $values, $previous, &$errors, &$field_errors = null) {
        $field_errors = array();
        $updates = array();
        $submitted_values = array();
        foreach ($schema as $field) {
            $submitted_values[$field['key']] = self::clean_value($field, $values[$field['name']] ?? '');
        }
        foreach ($schema as $field) {
            // Hidden values are preserved, even if a forged request supplies them.
            if (!self::visible($field, $schema, $submitted_values)) { continue; }
            if ($field['type'] === 'repeater') {
                $value = Alify_CF_Repeater::clean($field, $values[$field['name']] ?? '', $previous[$field['name']] ?? null, true);
            } elseif ($field['type'] === 'gallery') {
                $value = Alify_CF_Gallery::clean($values[$field['name']] ?? '', $previous[$field['name']] ?? array(), true);
            } else {
                $value = self::clean_value($field, $values[$field['name']] ?? '');
            }
            $submitted = $values[$field['name']] ?? '';
            $empty = $value === '' || $value === array() || ($field['type'] === 'true_false' && $value !== '1');
            $invalid = (in_array($field['type'], array('repeater', 'gallery'), true) && $value === '') || ($submitted !== '' && $submitted !== array() && $value === '');
            if (($field['required'] && $empty) || $invalid) {
                $message = ($field['required'] && $empty ? 'A valid value is required.' : 'Invalid value.') . ' Previous saved value retained.';
                $field_errors[$field['name']] = $message;
                $errors[] = $field['label'] . ': ' . $message;
                continue;
            }
            if (in_array($field['type'], array('image', 'file'), true) && $value !== '' && (string) ($previous[$field['name']] ?? null) !== (string) $value && !current_user_can('edit_post', $value)) {
                $message = 'You cannot select this attachment. Previous saved value retained.';
                $field_errors[$field['name']] = $message;
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
        $url = $valid && $field['type'] === 'image' ? wp_get_attachment_image_url($attachment->ID, 'thumbnail') : '';
        echo '<div class="alify-media" data-type="' . esc_attr($field['type']) . '"><input type="hidden" id="' . esc_attr($id) . '" name="' . esc_attr($input) . '" value="' . esc_attr($value) . '">';
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
