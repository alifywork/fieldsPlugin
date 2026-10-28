<?php
if (!defined('ABSPATH')) { exit; }

/** Gate editor publication transitions; bridge metabox values into the same REST save. */
final class Alify_CF_Publication {
    private static $active = array();
    private static $updates = array();

    public static function boot() {
        add_filter('wp_insert_post_data', array(__CLASS__, 'classic'), 20, 2);
        add_action('rest_api_init', array(__CLASS__, 'register_rest'));
        add_filter('rest_request_after_callbacks', array(__CLASS__, 'after_rest'), 10, 3);
    }
    public static function after_rest($response, $handler, $request) {
        $key = spl_object_hash($request); unset(self::$active[$key], self::$updates[$key]); return $response;
    }

    public static function stored_payload($field, $value) {
        if ($field['type'] === 'link') { return array('__complete' => '1', 'url' => is_array($value) ? ($value['url'] ?? '') : '', 'title' => is_array($value) ? ($value['title'] ?? '') : '', 'target' => is_array($value) ? ($value['target'] ?? '') : ''); }
        if ($field['type'] === 'gallery') { return array('__complete' => '1', 'ids' => is_array($value) ? $value : array()); }
        if ($field['type'] === 'flexible') {
            $rows = array();
            foreach (is_array($value) ? $value : array() as $row) {
                $layout = is_array($row) ? Alify_CF_Flexible::layout($field, $row['layout'] ?? '') : null;
                if (!$layout) { $rows[] = array('layout' => ''); continue; }
                $layout['type'] = 'group';
                $payload = self::stored_payload($layout, $row['fields'] ?? array()); $payload['layout'] = $row['layout']; $rows[] = $payload;
            }
            return array('__complete' => '1', 'rows' => $rows);
        }
        if ($field['type'] === 'group') {
            $out = array();
            foreach ($field['sub_fields'] as $sub) { $out[$sub['name']] = self::stored_payload($sub, is_array($value) && array_key_exists($sub['name'], $value) ? $value[$sub['name']] : $sub['default']); }
            return array('__complete' => '1', 'fields' => $out);
        }
        if ($field['type'] === 'repeater') {
            $rows = array();
            foreach (is_array($value) ? $value : array() as $row) {
                $out = array('__row_complete' => '1');
                foreach ($field['sub_fields'] as $sub) { $out[$sub['name']] = self::stored_payload($sub, $row[$sub['name']] ?? $sub['default']); }
                $rows[] = $out;
            }
            return array('__complete' => '1', 'rows' => $rows);
        }
        return $value;
    }

    /** Validate all published groups using a complete submitted payload, or saved values. */
    public static function assess($post_type, $id, $submitted = null) {
        $errors = array(); $feedback = array(); $updates = array(); $names = array();
        foreach (Alify_CF::groups($post_type) as $group) {
            $schema = Alify_CF::schema($group->ID); $previous = array(); $values = array();
            foreach ($schema as $field) {
                $name = $field['name'];
                if (isset($names[$name])) { $errors[] = 'Conflicting field name: ' . $name; }
                $names[$name] = true;
                $previous[$name] = $id ? alify_get_field($name, $id) : null;
                $values[$name] = self::stored_payload($field, $previous[$name] ?? $field['default']);
            }
            if ($submitted !== null) {
                if (!is_array($submitted) || !isset($submitted[$group->ID]) || !is_array($submitted[$group->ID]) || ($submitted[$group->ID]['__group_complete'] ?? '') !== '1') {
                    $errors[] = 'Incomplete field group. Reload the editor and try again.'; continue;
                }
                $values = $submitted[$group->ID];
            }
            $field_errors = array();
            $group_updates = Alify_CF::validate_values($schema, $values, $previous, $errors, $field_errors, $locations);
            if ($field_errors) { $feedback[$group->ID] = Alify_CF_Feedback::group($schema, $values, $previous, $field_errors, $locations); }
            foreach ($group_updates as $name => $value) { $updates[$name] = $value; }
        }
        return array('errors' => $errors, 'feedback' => $feedback, 'updates' => $updates);
    }

    public static function classic($data, $postarr) {
        if (self::$active || (defined('REST_REQUEST') && REST_REQUEST) || !in_array($data['post_status'], array('publish', 'future', 'private'), true)) { return $data; }
        $id = isset($postarr['ID']) ? absint($postarr['ID']) : 0;
        $type = $data['post_type'];
        if ($type === Alify_CF::GROUP || !Alify_CF::groups($type) || ($id && !current_user_can('edit_post', $id))) { return $data; }
        $submitted = null; $nonce_errors = array();
        if (isset($_POST['alify_values'])) {
            $submitted = is_array($_POST['alify_values']) ? wp_unslash($_POST['alify_values']) : array();
            foreach (Alify_CF::groups($type) as $group) {
                $nonce = $_POST['alify_values_nonce_' . $group->ID] ?? '';
                if (!is_string($nonce) || !wp_verify_nonce(sanitize_text_field(wp_unslash($nonce)), 'alify_values_' . $id . '_' . $group->ID)) { $nonce_errors[] = 'Field security check failed. Reload the editor.'; }
            }
        }
        $result = self::assess($type, $id, $submitted);
        if (!$result['errors'] && !$nonce_errors) { return $data; }
        // Never unpublish existing content as a side effect of a failed edit.
        $old = $id ? get_post_status($id) : 'draft';
        $data['post_status'] = in_array($old, array('publish', 'future', 'private', 'pending'), true) ? $old : 'draft';
        if ($id) {
            set_transient('alify_cf_publication_' . get_current_user_id() . '_' . $id, true, 120);
            if (!$nonce_errors) { Alify_CF_Feedback::put('post_' . $id, $result['feedback']); }
        }
        return $data;
    }

    public static function register_rest() {
        foreach (get_post_types(array('show_in_rest' => true), 'objects') as $type) {
            if (in_array($type->name, array(Alify_CF::GROUP, 'attachment'), true)) { continue; }
            add_filter('rest_pre_insert_' . $type->name, function($prepared, $request) use ($type) { return self::pre_rest($prepared, $request, $type->name); }, 20, 2);
            add_action('rest_after_insert_' . $type->name, function($post, $request) {
                if ($request->get_param('alify_fields') === null && isset(self::$updates[spl_object_hash($request)])) { self::write_rest(null, $post, 'alify_fields', $request); }
            }, 10, 2);
            register_rest_field($type->name, 'alify_fields', array(
                'update_callback' => array(__CLASS__, 'write_rest'),
                'schema' => array('description' => 'Complete Alify editor field payload. Not a public read API.', 'type' => 'object', 'context' => array('edit'), 'properties' => array('complete' => array('type' => 'string', 'enum' => array('1')), 'groups' => array('type' => 'object', 'additionalProperties' => true))),
            ));
        }
    }

    public static function pre_rest($prepared, $request, $type) {
        if (is_wp_error($prepared) || strpos($request->get_route(), '/autosaves') !== false) { return $prepared; }
        $id = absint($request->get_param('id'));
        $payload = $request->get_param('alify_fields');
        $status = isset($prepared->post_status) ? $prepared->post_status : ($id ? get_post_status($id) : 'draft');
        if ($payload === null && !in_array($status, array('publish', 'future', 'private'), true)) { self::$active[spl_object_hash($request)] = true; return $prepared; }
        if ($payload !== null && (!is_array($payload) || ($payload['complete'] ?? '') !== '1' || !isset($payload['groups']) || !is_array($payload['groups']))) {
            return new WP_Error('alify_incomplete_fields', 'Incomplete Alify fields. Reload the editor.', array('status' => 400));
        }
        $result = self::assess($type, $id, $payload === null ? null : $payload['groups']);
        if ($result['errors']) {
            if ($id) { Alify_CF_Feedback::put('post_' . $id, $result['feedback']); }
            $inline = array(); $paths = array();
            foreach ($result['feedback'] as $group_id => $feedback) { $inline[$group_id] = $feedback['errors']; $paths[$group_id] = $feedback['locations'] ?? array(); }
            return new WP_Error('alify_invalid_fields', 'Alify fields need correction: ' . implode(' ', $result['errors']), array('status' => 400, 'alify_errors' => $inline, 'alify_error_locations' => $paths));
        }
        self::$active[spl_object_hash($request)] = true;
        self::$updates[spl_object_hash($request)] = $result['updates'];
        return $prepared;
    }

    public static function write_rest($payload, $post, $field, $request) {
        if (!current_user_can('edit_post', $post->ID)) { return new WP_Error('alify_forbidden', 'You cannot edit these fields.', array('status' => 403)); }
        $key = spl_object_hash($request);
        if (!isset(self::$updates[$key])) { return new WP_Error('alify_unvalidated_fields', 'Alify fields were not validated.', array('status' => 400)); }
        foreach (self::$updates[$key] as $name => $value) { update_post_meta($post->ID, '_alify_cf_' . $name, wp_slash($value)); }
        Alify_CF_Feedback::put('post_' . $post->ID, array());
        return true;
    }
}
