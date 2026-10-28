<?php
if (!defined('ABSPATH')) { exit; }

/** Classic existing widget instances only; never changes the site's widget editor choice. */
final class Alify_CF_Widgets {
    public static function boot() {
        add_action('in_widget_form', array(__CLASS__, 'render'), 20, 3);
        add_filter('widget_update_callback', array(__CLASS__, 'update'), 20, 4);
        add_action('admin_enqueue_scripts', function($hook) { if ($hook === 'widgets.php' && (!function_exists('wp_use_widgets_block_editor') || !wp_use_widgets_block_editor())) { Alify_CF_Objects::assets('alify-objects'); } });
    }
    public static function get($name, $id) {
        global $wp_registered_widgets;
        if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/D', $name) || !is_string($id) || !preg_match('/^([a-zA-Z0-9_-]{1,80})-([1-9][0-9]*)$/D', $id, $match) || (string) (int) $match[2] !== $match[2]) { return null; }
        $widget = $wp_registered_widgets[$id]['callback'][0] ?? null; if (!($widget instanceof WP_Widget) || $widget->id_base !== $match[1]) { return null; } $data = get_option($widget->option_name, array()); return $data[(int) $match[2]]['_alify_cf'][$name] ?? null;
    }
    public static function render($widget, $return, $instance) {
        if (!current_user_can('edit_theme_options') || (function_exists('wp_use_widgets_block_editor') && wp_use_widgets_block_editor()) || !Alify_CF::groups('widget')) { return; }
        if (!is_numeric($widget->number) || (int) $widget->number < 1) { echo '<p>Save this widget once, then reopen to configure Alify fields.</p>'; return; }
        wp_nonce_field('alify_widget_' . $widget->id, $widget->get_field_name('_alify_cf_nonce'));
        foreach (Alify_CF::groups('widget', 'widget:' . $widget->id) as $group) {
            echo '<h4>' . esc_html($group->post_title) . '</h4>'; ob_start(); Alify_CF::values((object) array('ID' => 0), $group, is_array($instance['_alify_cf'] ?? null) ? $instance['_alify_cf'] : array(), array()); $html = ob_get_clean();
            echo str_replace(array('alify_values[', 'alify-' . $group->ID . '-'), array(esc_attr($widget->get_field_name('_alify_cf_values')) . '[', 'alify-widget-' . esc_attr($widget->id) . '-' . $group->ID . '-'), $html);
        }
    }
    public static function update($instance, $new, $old, $widget) {
        if (!is_array($instance)) { return $instance; } $previous = is_array($old['_alify_cf'] ?? null) ? $old['_alify_cf'] : array(); if ($previous) { $instance['_alify_cf'] = $previous; }
        if (!is_array($new) || !isset($new['_alify_cf_nonce'])) { return $instance; }
        if (!current_user_can('edit_theme_options') || !is_string($new['_alify_cf_nonce']) || !wp_verify_nonce($new['_alify_cf_nonce'], 'alify_widget_' . $widget->id)) { return $old; }
        $errors = array(); $updates = array(); foreach (Alify_CF::groups('widget', 'widget:' . $widget->id) as $group) {
            $payload = $new['_alify_cf_values'][$group->ID] ?? null; if (!is_array($payload) || ($payload['__group_complete'] ?? '') !== '1') { $errors[] = 'Incomplete widget field group.'; continue; }
            $updates = array_replace($updates, Alify_CF::validate_values(Alify_CF::schema($group->ID), $payload, $previous, $errors));
        }
        if ($errors) { set_transient('alify_cf_object_error_' . get_current_user_id(), 'Widget fields were not saved: ' . implode(' ', $errors), 120); return $old; }
        $instance['_alify_cf'] = array_replace($previous, $updates); return $instance;
    }
}
