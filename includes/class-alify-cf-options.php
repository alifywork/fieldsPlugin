<?php
if (!defined('ABSPATH')) { exit; }

/** Site-scoped option storage, separate from post metadata and network options. */
final class Alify_CF_Options {
    private static $pages = array('site' => 'Site Settings');
    private static $hooks = array();

    public static function boot() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_post_alify_cf_save_options', array(__CLASS__, 'handle'));
    }

    public static function register($slug, $title) {
        if (!self::valid_slug($slug) || !is_string($title) || trim($title) === '' || isset(self::$pages[$slug])) { return false; }
        $title = sanitize_text_field($title);
        if ($title === '') { return false; }
        self::$pages[$slug] = $title;
        return true;
    }

    private static function valid_slug($slug) {
        return is_string($slug) && preg_match('/^[a-z][a-z0-9_-]{0,49}$/', $slug);
    }

    public static function pages() { return self::$pages; }
    public static function location($slug) { return 'options:' . $slug; }
    public static function is_location($location) {
        foreach (self::$pages as $slug => $title) {
            if ($location === self::location($slug)) { return true; }
        }
        return false;
    }
    public static function data($slug) {
        if (!self::valid_slug($slug)) { return array(); }
        $data = get_option('alify_cf_options_' . $slug, array());
        return is_array($data) ? $data : array();
    }

    public static function menu() {
        foreach (self::$pages as $slug => $title) {
            $hook = add_submenu_page('edit.php?post_type=' . Alify_CF::GROUP, $title, $title, 'manage_options', 'alify-options-' . $slug, function() use ($slug) { self::render($slug); });
            if ($hook) { self::$hooks[] = $hook; }
        }
    }

    public static function assets($hook) {
        if (!in_array($hook, self::$hooks, true)) { return; }
        wp_enqueue_style('alify-cf', plugins_url('assets/admin.css', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION);
        if (current_user_can('upload_files')) { wp_enqueue_media(); }
        wp_enqueue_script('alify-cf-gallery', plugins_url('assets/gallery.js', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION, true);
        wp_enqueue_script('alify-cf-flexible', plugins_url('assets/flexible.js', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION, true);
        wp_enqueue_script('alify-cf-values', plugins_url('assets/values.js', ALIFY_CF_FILE), array('alify-cf-gallery', 'alify-cf-flexible'), ALIFY_CF_VERSION, true);
    }

    /** $request is already unslashed. A failed save leaves the entire page unchanged. */
    public static function save($slug, $request, &$errors) {
        if (!current_user_can('manage_options')) { $errors[] = 'You do not have permission to save options.'; return false; }
        if (!is_string($slug) || !isset(self::$pages[$slug])) { $errors[] = 'Unknown options page.'; return false; }
        $nonce = $request['alify_options_nonce'] ?? '';
        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'alify_options_' . $slug)) { $errors[] = 'Security check failed. Reload the page and try again.'; return false; }
        if (($request['alify_options_complete'] ?? '') !== '1' || !isset($request['alify_values']) || !is_array($request['alify_values'])) { $errors[] = 'Incomplete options form. No settings were changed.'; return false; }
        $previous = self::data($slug); $next = $previous; $names = array(); $feedback = array();
        $groups = Alify_CF::groups(self::location($slug));
        if (!$groups) { $errors[] = 'No published field groups are assigned to this page.'; return false; }
        foreach ($groups as $group) {
            $values = $request['alify_values'][$group->ID] ?? null;
            if (!is_array($values) || ($values['__group_complete'] ?? '') !== '1') { $errors[] = 'Incomplete field group. Reload the page and try again.'; continue; }
            $schema = Alify_CF::schema($group->ID);
            foreach ($schema as $field) {
                if (isset($names[$field['name']])) { $errors[] = 'Conflicting field names on this options page: ' . $field['name']; }
                $names[$field['name']] = true;
            }
            $field_errors = array();
            $updates = Alify_CF::validate_values($schema, $values, $previous, $errors, $field_errors);
            $feedback[$group->ID] = Alify_CF_Feedback::group($schema, $values, $previous, $field_errors);
            foreach ($updates as $name => $value) { $next[$name] = $value; }
        }
        if ($errors) { Alify_CF_Feedback::put('options_' . $slug, $feedback); return false; }
        Alify_CF_Feedback::put('options_' . $slug, array());
        // Unlike post meta, update_option() does not strip slashes. Do not wp_slash().
        update_option('alify_cf_options_' . $slug, $next, false);
        return true;
    }

    public static function handle() {
        if (!current_user_can('manage_options')) { wp_die('You do not have permission to save options.', '', array('response' => 403)); }
        $request = wp_unslash($_POST);
        $slug = isset($request['alify_options_page']) && is_string($request['alify_options_page']) ? $request['alify_options_page'] : '';
        if (!isset(self::$pages[$slug])) { wp_die('Unknown options page.', '', array('response' => 400)); }
        $errors = array();
        $saved = self::save($slug, $request, $errors);
        set_transient('alify_cf_options_notice_' . get_current_user_id() . '_' . $slug, array('saved' => $saved, 'errors' => $errors), 120);
        wp_safe_redirect(admin_url('edit.php?post_type=' . Alify_CF::GROUP . '&page=alify-options-' . $slug));
        exit;
    }

    public static function render($slug) {
        if (!current_user_can('manage_options') || !isset(self::$pages[$slug])) { wp_die('You do not have permission to view this page.', '', array('response' => 403)); }
        $title = self::$pages[$slug];
        echo '<div class="wrap alify-options-page"><h1>' . esc_html($title) . '</h1><p class="description">Global fields for your site. Values are stored separately from posts.</p>';
        $key = 'alify_cf_options_notice_' . get_current_user_id() . '_' . $slug;
        $notice = get_transient($key);
        if (is_array($notice)) {
            delete_transient($key);
            echo '<div class="notice ' . (!empty($notice['saved']) ? 'notice-success' : 'notice-error') . '" role="status"><p><strong>' . (!empty($notice['saved']) ? 'Settings saved.' : 'Settings were not saved. All previous settings were retained.') . '</strong></p>';
            foreach (($notice['errors'] ?? array()) as $error) { echo '<p>' . esc_html($error) . '</p>'; }
            echo '</div>';
        }
        $groups = Alify_CF::groups(self::location($slug));
        if (!$groups) {
            echo '<div class="alify-empty"><h2>No settings fields yet</h2><p>Create a field group, choose Options Page: ' . esc_html($title) . ' as its location, and publish it.</p><a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=' . Alify_CF::GROUP)) . '">Add Field Group</a></div></div>';
            return;
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="alify_cf_save_options"><input type="hidden" name="alify_options_page" value="' . esc_attr($slug) . '">';
        wp_nonce_field('alify_options_' . $slug, 'alify_options_nonce');
        $data = self::data($slug);
        $feedback = Alify_CF_Feedback::get('options_' . $slug);
        foreach ($groups as $group) {
            echo '<section class="alify-options-group"><h2>' . esc_html($group->post_title) . '</h2>';
            Alify_CF::values(null, $group, $data, $feedback[$group->ID] ?? array());
            echo '</section>';
        }
        echo '<input type="hidden" name="alify_options_complete" value="1">';
        submit_button('Save Settings');
        echo '</form></div>';
    }
}
