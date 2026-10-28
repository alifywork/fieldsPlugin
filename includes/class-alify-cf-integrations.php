<?php
if (!defined('ABSPATH')) { exit; }

/** Optional adapters, never ACF impersonation or ambient third-party data exposure. */
final class Alify_CF_Integrations {
    public static function enabled($name) { return defined('ALIFY_CF_' . $name) && constant('ALIFY_CF_' . $name) === true; }
    public static function boot() {
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'sidebar'));
        add_action('wp_abilities_api_categories_init', function() { if (self::enabled('ABILITIES') && function_exists('wp_register_ability_category')) { wp_register_ability_category('alify-fields', array('label' => 'Alify fields', 'description' => 'Explicitly exposed post fields.')); } });
        add_action('wp_abilities_api_init', array(__CLASS__, 'abilities'));
        add_filter('timber/twig', function($twig) {
            if (self::enabled('TIMBER') && class_exists('Twig\\TwigFilter') && is_object($twig) && method_exists($twig, 'addFilter')) { $twig->addFilter(new \Twig\TwigFilter('alify_public_field', function($name, $id = 0) { return esc_html(Alify_CF_API::public_value($name, $id ?: get_the_ID()) ?? ''); }, array('is_safe' => array('html')))); }
            return $twig;
        });
    }
    public static function sidebar() {
        global $post;
        if (!self::enabled('SIDEBAR') || !$post || !current_user_can('edit_post', $post->ID)) { return; }
        wp_enqueue_script('alify-cf-sidebar', plugins_url('assets/sidebar.js', ALIFY_CF_FILE), array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch'), ALIFY_CF_VERSION, true);
        wp_localize_script('alify-cf-sidebar', 'AlifySidebar', array('postId' => $post->ID));
    }
    public static function ability_allowed($input) { return is_array($input) && is_int($input['post_id'] ?? null) && $input['post_id'] > 0 && current_user_can('edit_post', $input['post_id']); }
    public static function ability($input, $write = false) {
        if (!self::enabled('ABILITIES') || !self::ability_allowed($input)) { return new WP_Error('alify_forbidden', 'Ability requires explicit opt-in and object edit permission.'); }
        $request = new WP_REST_Request($write ? 'POST' : 'GET'); $request['id'] = $input['post_id']; if ($write) { $request->set_param('values', $input['values'] ?? null); }
        return Alify_CF_API::rest($request);
    }
    public static function abilities() {
        if (!self::enabled('ABILITIES') || !function_exists('wp_register_ability')) { return; }
        foreach (array('read' => false, 'write' => true) as $verb => $write) {
            $properties = array('post_id' => array('type' => 'integer', 'minimum' => 1)); if ($write) { $properties['values'] = array('type' => 'object', 'minProperties' => 1, 'maxProperties' => 200); }
            wp_register_ability('alify/' . $verb . '-fields', array('label' => ucfirst($verb) . ' Alify fields', 'description' => 'Use only explicitly api_' . $verb . '-enabled fields on an editable post. No schema administration.', 'category' => 'alify-fields', 'input_schema' => array('type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false), 'output_schema' => array('type' => 'object'), 'permission_callback' => array(__CLASS__, 'ability_allowed'), 'execute_callback' => function($input) use ($write) { return self::ability($input, $write); }, 'meta' => array('show_in_rest' => true, 'mcp' => array('public' => true), 'annotations' => array('readonly' => !$write, 'destructive' => $write, 'idempotent' => true))));
        }
    }
}
