<?php
if (!defined('ABSPATH')) { exit; }

/** Trusted PHP registration; capability-checked writes; explicit REST/output exposure. */
final class Alify_CF_API {
    private static $groups = array();
    private static $types = array();
    private static $writing = false;
    public static function boot() {
        add_action('rest_api_init', array(__CLASS__, 'routes'));
        add_action('init', function() {
            add_shortcode('alify_field', array(__CLASS__, 'shortcode'));
            if (function_exists('register_block_bindings_source')) {
                register_block_bindings_source('alify/field', array('label' => 'Alify public scalar field', 'uses_context' => array('postId'), 'get_value_callback' => function($args, $block) { return self::public_value($args['name'] ?? '', $block->context['postId'] ?? 0); }));
            }
        }, 20);
    }
    public static function groups($location = null) { return array_values(array_filter(self::$groups, function($group) use ($location) { return $location === null || $group['location'] === $location; })); }
    public static function schema($id) { foreach (self::$groups as $group) { if ($group['id'] === $id) { return $group['fields']; } } return null; }
    public static function types() { $out = array(); foreach (self::$types as $type => $callbacks) { $out[$type] = $callbacks['label']; } return $out; }
    public static function custom($type) { return isset(self::$types[$type]); }
    public static function register_type($type, $callbacks) {
        if (!is_string($type) || !preg_match('/^x_[a-z][a-z0-9_]{0,39}$/D', $type) || !is_array($callbacks) || empty($callbacks['label']) || !is_string($callbacks['label'])) { return new WP_Error('alify_type', 'Custom field types need an x_ identifier and label.'); }
        foreach (array('clean', 'render') as $callback) { if (!is_callable($callbacks[$callback] ?? null)) { return new WP_Error('alify_type', 'Custom field types require clean/render callbacks.'); } }
        foreach (array('settings', 'format') as $callback) { if (isset($callbacks[$callback]) && !is_callable($callbacks[$callback])) { return new WP_Error('alify_type', 'Invalid optional callback.'); } }
        if (isset(self::$types[$type])) { return new WP_Error('alify_type', 'Custom type already registered.'); }
        self::$types[$type] = $callbacks; return true;
    }
    public static function custom_clean($field, $value) {
        $value = call_user_func(self::$types[$field['type']]['clean'], $field, $value);
        return is_string($value) && strlen($value) <= 65536 ? $value : '';
    }
    public static function custom_render($field, $value, $id, $input, $error) { call_user_func(self::$types[$field['type']]['render'], $field, $value, $id, $input, $error); }
    public static function custom_format($field, $value) { return isset(self::$types[$field['type']]['format']) ? call_user_func(self::$types[$field['type']]['format'], $field, $value) : $value; }
    public static function contains_custom($field) { if (self::custom($field['type'])) { return true; } foreach ($field['sub_fields'] ?? array() as $sub) { if (self::contains_custom($sub)) { return true; } } foreach ($field['layouts'] ?? array() as $layout) { foreach ($layout['sub_fields'] as $sub) { if (self::contains_custom($sub)) { return true; } } } return false; }
    public static function settings($field, &$errors) {
        $out = array();
        foreach (array('api_read', 'api_write', 'public_output') as $key) { if (array_key_exists($key, $field)) { if (!in_array($field[$key], array(true, false, 0, 1, '0', '1'), true)) { $errors[] = 'Exposure flags must be boolean.'; } else { $out[$key] = (bool) $field[$key]; } } }
        if (self::custom($field['type']) && isset(self::$types[$field['type']]['settings'])) {
            $extra = call_user_func_array(self::$types[$field['type']]['settings'], array($field, &$errors));
            if (!is_array($extra) || array_intersect(array_keys($extra), array('name', 'key', 'type', 'label', 'required', 'default', 'condition', 'sub_fields', 'layouts', 'api_read', 'api_write', 'public_output'))) { $errors[] = 'Custom settings cannot replace structural field properties.'; } else { $out = array_merge($extra, $out); }
        }
        return $out;
    }
    public static function register_group($definition) {
        if (!is_array($definition) || !is_string($definition['key'] ?? null) || !preg_match('/^[a-z][a-z0-9_-]{0,79}$/D', $definition['key']) || !is_string($definition['title'] ?? null) || trim($definition['title']) === '') { return new WP_Error('alify_group', 'Registration requires a stable key and title.'); }
        $key = $definition['key']; $id = -1 - (int) hexdec(substr(hash('sha256', $key), 0, 7));
        foreach (self::$groups as $other_key => $group) { if ($other_key !== $key && $group['id'] === $id) { return new WP_Error('alify_group', 'Registration identifier collision.'); } }
        $location = $definition['location'] ?? 'post'; $errors = array();
        $raw_fields = $definition['fields'] ?? null; self::stable_keys($raw_fields, $key);
        $fields = Alify_CF::validate_schema($raw_fields, $location, $id, $errors);
        if ($errors) { return new WP_Error('alify_schema', implode(' ', $errors)); }
        self::$groups[$key] = array('key' => $key, 'id' => $id, 'title' => sanitize_text_field($definition['title']), 'location' => $location, 'fields' => $fields);
        do_action('alify_cf/registered_group', $key, self::$groups[$key]); return true;
    }
    private static function stable_keys(&$fields, $prefix) { if (!is_array($fields)) { return; } foreach ($fields as &$field) { if (!is_array($field) || !is_string($field['name'] ?? '')) { continue; } $path = $prefix . '/' . ($field['name'] ?? ''); if (empty($field['key'])) { $field['key'] = 'field_' . substr(hash('sha256', $path), 0, 24); } if (isset($field['sub_fields'])) { self::stable_keys($field['sub_fields'], $path); } if (isset($field['layouts']) && is_array($field['layouts'])) { foreach ($field['layouts'] as &$layout) { if (is_array($layout) && isset($layout['sub_fields'])) { self::stable_keys($layout['sub_fields'], $path . '/' . (is_string($layout['name'] ?? null) ? $layout['name'] : '')); } } unset($layout); } } unset($field); }
    public static function register_field($key, $field) {
        if (!is_string($key) || !isset(self::$groups[$key])) { return new WP_Error('alify_group', 'Unknown registered group.'); }
        $definition = self::$groups[$key]; $definition['fields'][] = $field; return self::register_group($definition);
    }
    public static function field($location, $name) {
        $matches = array(); foreach (Alify_CF::groups($location) as $group) { foreach (Alify_CF::schema($group->ID) as $field) { if ($field['name'] === $name) { $matches[] = $field; } } } return count($matches) === 1 ? $matches[0] : null;
    }
    public static function write($values, $object, $options = false) {
        if (self::$writing) { return new WP_Error('alify_reentrant', 'Recursive Alify API writes are not permitted.'); }
        if ($options) {
            if (!is_string($object) || !isset(Alify_CF_Options::pages()[$object]) || !current_user_can('manage_options')) { return new WP_Error('alify_forbidden', 'Options update not permitted.', array('status' => 403)); }
            $location = Alify_CF_Options::location($object); $previous = Alify_CF_Options::data($object);
        } else {
            $post = (is_int($object) || is_string($object)) && ctype_digit((string) $object) ? get_post((int) $object) : null;
            if (!$post || wp_is_post_revision($post->ID) || $post->post_type === Alify_CF::GROUP || !current_user_can('edit_post', $post->ID)) { return new WP_Error('alify_forbidden', 'Post update not permitted.', array('status' => 403)); }
            $object = $post->ID; $location = $post->post_type; $previous = array();
        }
        if (!is_array($values) || !$values || count($values) > 200) { return new WP_Error('alify_values', 'Supply 1–200 named editor-payload values.'); }
        $known = array(); $updates = array(); $errors = array();
        foreach (Alify_CF::groups($location) as $group) {
            $schema = Alify_CF::schema($group->ID); $payload = array(); $touches = false;
            foreach ($schema as $field) {
                $name = $field['name']; $known[$name] = true;
                if (!$options) { $previous[$name] = alify_get_field($name, $object); }
                $payload[$name] = Alify_CF_Publication::stored_payload($field, $previous[$name] ?? $field['default']);
                if (array_key_exists($name, $values)) { $payload[$name] = $values[$name]; $touches = true; }
            }
            if (!$touches) { continue; }
            $clean = Alify_CF::validate_values($schema, $payload, $previous, $errors);
            foreach ($values as $name => $unused) { if (isset($known[$name]) && array_key_exists($name, $payload) && !array_key_exists($name, $clean)) { $errors[] = 'Requested field is hidden or invalid: ' . $name; } }
            foreach ($clean as $name => $value) { if (array_key_exists($name, $values)) { $updates[$name] = $value; } }
        }
        if (array_diff_key($values, $known)) { $errors[] = 'Unknown field names are not writable.'; }
        if ($errors || !$updates) { return new WP_Error('alify_validation', implode(' ', $errors) ?: 'No writable fields.', array('status' => 400)); }
        self::$writing = true;
        try {
            do_action('alify_cf/before_api_write', $updates, $object, $options);
            if ($options) {
                $next = array_replace($previous, $updates); update_option('alify_cf_options_' . $object, $next, false);
                if (Alify_CF_Options::data($object) !== $next) { return new WP_Error('alify_storage', 'Options storage failed.'); }
            } else {
                $snapshots = array(); foreach ($updates as $name => $value) { $snapshots[$name] = array(metadata_exists('post', $object, '_alify_cf_' . $name), alify_get_field($name, $object)); }
                foreach ($updates as $name => $value) {
                    update_post_meta($object, '_alify_cf_' . $name, wp_slash($value)); $stored = alify_get_field($name, $object);
                    if (is_array($value) ? $stored !== $value : (string) $stored !== (string) $value) {
                        foreach ($snapshots as $key => $snapshot) { if ($snapshot[0]) { update_post_meta($object, '_alify_cf_' . $key, wp_slash($snapshot[1])); } else { delete_post_meta($object, '_alify_cf_' . $key); } }
                        return new WP_Error('alify_storage', 'Post storage failed; restoration attempted.');
                    }
                }
            }
            do_action('alify_cf/after_api_write', $updates, $object, $options); return true;
        } finally { self::$writing = false; }
    }
    public static function mutate_row($name, $index, $row, $post_id, $remove = false) {
        if (!current_user_can('edit_post', $post_id)) { return new WP_Error('alify_forbidden', 'Post update not permitted.'); }
        $post = get_post($post_id); $field = $post ? self::field($post->post_type, $name) : null;
        if (!$field || !in_array($field['type'], array('repeater', 'flexible'), true) || !is_int($index) || $index < 0) { return new WP_Error('alify_row', 'Use a valid zero-based row index and container field.'); }
        $rows = alify_get_field($name, $post_id); $rows = is_array($rows) ? $rows : array();
        if ($remove) { if (!array_key_exists($index, $rows)) { return new WP_Error('alify_row', 'Row does not exist.'); } array_splice($rows, $index, 1); }
        else { if (!is_array($row) || $index > count($rows)) { return new WP_Error('alify_row', 'Invalid row.'); } $rows[$index] = $row; }
        return self::write(array($name => Alify_CF_Publication::stored_payload($field, array_values($rows))), $post_id);
    }
    public static function export_php($group_id) {
        if (!current_user_can('manage_options')) { return new WP_Error('alify_forbidden', 'Administrator permission required.'); }
        $registered = null; foreach (self::$groups as $group) { if ($group['id'] === $group_id) { $registered = $group; } }
        if ($registered) { $definition = array('key' => $registered['key'], 'title' => $registered['title'], 'location' => $registered['location'], 'fields' => $registered['fields']); }
        else { $post = get_post($group_id); if (!$post || $post->post_type !== Alify_CF::GROUP) { return new WP_Error('alify_group', 'Unknown group.'); } $definition = array('key' => 'export_' . $group_id, 'title' => $post->post_title, 'location' => get_post_meta($group_id, Alify_CF::LOCATION, true), 'fields' => Alify_CF::schema($group_id)); }
        foreach ($definition['fields'] as &$field) { self::portable($field); } unset($field);
        return "<?php\n// Register after post types, taxonomies and options pages exist. Configuration only.\nadd_action('init', function () {\n    alify_register_field_group(" . var_export($definition, true) . ");\n}, 30);\n";
    }
    private static function portable(&$field) { if (in_array($field['type'], array('image', 'file', 'post_object', 'password'), true)) { $field['default'] = ''; } if (isset($field['sub_fields'])) { foreach ($field['sub_fields'] as &$sub) { self::portable($sub); } unset($sub); } if (isset($field['layouts'])) { foreach ($field['layouts'] as &$layout) { foreach ($layout['sub_fields'] as &$sub) { self::portable($sub); } unset($sub); } unset($layout); } }
    public static function routes() {
        foreach (array('posts/(?P<id>[1-9][0-9]*)', 'options/(?P<page>[a-z][a-z0-9_-]{0,49})') as $route) {
            register_rest_route('alify/v1', '/' . $route, array('methods' => 'GET,POST', 'permission_callback' => function($request) { return isset($request['page']) ? current_user_can('manage_options') && isset(Alify_CF_Options::pages()[$request['page']]) : current_user_can('edit_post', (int) $request['id']); }, 'callback' => array(__CLASS__, 'rest')));
        }
    }
    public static function rest($request) {
        $options = isset($request['page']); $object = $options ? $request['page'] : (int) $request['id']; $post = $options ? null : get_post($object);
        if (!$options && (!$post || $post->post_type === Alify_CF::GROUP || wp_is_post_revision($object))) { return new WP_Error('alify_object', 'Unsupported object.', array('status' => 400)); }
        $location = $options ? Alify_CF_Options::location($object) : $post->post_type; $read = array(); $writable = array(); $schema = array();
        foreach (Alify_CF::groups($location) as $group) { foreach (Alify_CF::schema($group->ID) as $field) {
            if (!empty($field['api_write'])) { $writable[$field['name']] = true; }
            if (!empty($field['api_read']) && !Alify_CF_Password::contains($field) && !self::contains_custom($field)) { $read[$field['name']] = $options ? alify_get_option($field['name'], $object) : alify_get_field($field['name'], $object); }
            if (!empty($field['api_read']) || !empty($field['api_write'])) { $schema[$field['name']] = array('type' => $field['type'], 'required' => $field['required'], 'read' => array_key_exists($field['name'], $read), 'write' => isset($writable[$field['name']])); }
        } }
        if ($request->get_method() === 'POST') { $values = $request->get_param('values'); if (!is_array($values) || !$values || array_diff_key($values, $writable)) { return new WP_Error('alify_exposure', 'Only opted-in fields are writable.', array('status' => 400)); } $result = self::write($values, $object, $options); if (is_wp_error($result)) { return $result; } return array('saved' => true); }
        return array('values' => $read, 'schema' => $schema);
    }
    public static function public_value($name, $post_id) {
        if (!is_string($name) || !is_numeric($post_id)) { return null; } $post = get_post((int) $post_id);
        if (!$post || !is_post_publicly_viewable($post) || !empty($post->post_password)) { return null; }
        $field = self::field($post->post_type, $name);
        if (!$field || empty($field['public_output']) || Alify_CF_Password::contains($field) || self::custom($field['type']) || in_array($field['type'], array('user', 'wysiwyg'), true)) { return null; }
        $value = alify_get_field($name, $post->ID); return is_scalar($value) ? (string) $value : null;
    }
    public static function shortcode($attributes) { $attributes = shortcode_atts(array('name' => '', 'post_id' => get_the_ID()), $attributes, 'alify_field'); return esc_html(self::public_value($attributes['name'], $attributes['post_id']) ?? ''); }
}
