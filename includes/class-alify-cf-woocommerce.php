<?php
if (!defined('ABSPATH')) { exit; }

/** Order custom fields live exclusively in WooCommerce CRUD, including HPOS. */
final class Alify_CF_WooCommerce {
    private static $writing = false;
    public static function available() { return Alify_CF_Integrations::enabled('WOOCOMMERCE') && function_exists('wc_get_order'); }
    public static function boot() {
        add_action('before_woocommerce_init', function() { if (Alify_CF_Integrations::enabled('WOOCOMMERCE') && class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) { \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', ALIFY_CF_FILE, !Alify_CF::groups('shop_order')); } });
        add_filter('woocommerce_rest_pre_insert_shop_order_object', array(__CLASS__, 'rest_guard'), 10, 3);
        add_filter('woocommerce_rest_prepare_shop_order_object', function($response) { if (self::available() && is_object($response) && method_exists($response, 'get_data')) { $data = $response->get_data(); if (is_array($data['meta_data'] ?? null)) { $data['meta_data'] = array_values(array_filter($data['meta_data'], function($meta) { $data = is_object($meta) && method_exists($meta, 'get_data') ? $meta->get_data() : $meta; return !is_array($data) || !is_string($data['key'] ?? null) || strpos($data['key'], '_alify_cf_') !== 0; })); $response->set_data($data); } } return $response; }, 10);
        add_action('add_meta_boxes', function() { if (!self::available()) { return; } foreach (array('shop_order', 'woocommerce_page_wc-orders') as $screen) { add_meta_box('alify-order-fields', 'Alify Custom Field', array(__CLASS__, 'render'), $screen, 'normal'); } });
        add_action('woocommerce_process_shop_order_meta', function($id) { if (isset($_POST['alify_order_nonce'])) { $result = self::save_request((int) $id); if (is_wp_error($result) && class_exists('WC_Admin_Meta_Boxes')) { WC_Admin_Meta_Boxes::add_error($result->get_error_message()); } } }, 90);
        add_action('admin_enqueue_scripts', function() { $screen = get_current_screen(); if (self::available() && $screen && in_array($screen->id, array('shop_order', 'woocommerce_page_wc-orders'), true)) { wp_enqueue_style('alify-cf', plugins_url('assets/admin.css', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION); Alify_CF_Rich_Text::assets(); Alify_CF_Post_Object::assets(); foreach (array('gallery', 'flexible', 'values') as $asset) { wp_enqueue_script('alify-cf-' . $asset, plugins_url('assets/' . $asset . '.js', ALIFY_CF_FILE), array(), ALIFY_CF_VERSION, true); } } });
    }
    public static function rest_guard($order, $request, $creating = false) {
        if (!self::available() || is_wp_error($order)) { return $order; } $incoming = $request->get_param('meta_data'); if (!is_array($incoming)) { return $order; }
        $protected = array(); if ($order->get_id()) { $stored = new WC_Order($order->get_id()); $stored->read_meta_data(true); foreach ($stored->get_meta_data() as $meta) { $data = $meta->get_data(); if (strpos($data['key'], '_alify_cf_') === 0) { $protected[] = (int) $data['id']; } } }
        foreach ($incoming as $meta) { if (is_array($meta) && ((is_string($meta['key'] ?? null) && strpos($meta['key'], '_alify_cf_') === 0) || (!empty($meta['id']) && in_array((int) $meta['id'], $protected, true)))) { return new WP_Error('alify_order_exposure', 'Alify order fields require their dedicated validated write API, not arbitrary WooCommerce meta_data.', array('status' => 400)); } }
        return $order;
    }
    public static function order($id) {
        if (!self::available() || !is_int($id) || $id < 1 || !current_user_can('manage_woocommerce')) { return new WP_Error('alify_forbidden', 'Enable WooCommerce integration and use a store-management account.'); }
        $order = wc_get_order($id); return $order && $order->get_type() === 'shop_order' ? $order : new WP_Error('alify_order', 'Only existing shop orders are supported.');
    }
    public static function get($name, $id) { $order = self::order($id); if (is_wp_error($order) || !is_string($name) || !Alify_CF_API::field('woo_order', $name)) { return null; } return $order->meta_exists('_alify_cf_' . $name) ? $order->get_meta('_alify_cf_' . $name, true) : null; }
    public static function write($values, $id, $editor = false) {
        if (self::$writing) { return new WP_Error('alify_reentrant', 'Recursive order writes are not permitted.'); } $order = self::order($id); if (is_wp_error($order)) { return $order; }
        if (!is_array($values) || !$values || count($values) > 200) { return new WP_Error('alify_values', 'Supply 1–200 explicit order fields.'); }
        $known = array(); $updates = array(); $errors = array(); $old = array();
        foreach (Alify_CF::groups('woo_order') as $group) { $schema = Alify_CF::schema($group->ID); $payload = array(); $touch = false; foreach ($schema as $field) { $name = $field['name']; $known[$name] = true; $old[$name] = $order->meta_exists('_alify_cf_' . $name) ? $order->get_meta('_alify_cf_' . $name, true) : null; $payload[$name] = Alify_CF_Publication::stored_payload($field, $old[$name] ?? $field['default']); if (array_key_exists($name, $values)) { $payload[$name] = $values[$name]; $touch = true; } } if (!$touch) { continue; } $clean = Alify_CF::validate_values($schema, $payload, $old, $errors); foreach ($schema as $field) { $name = $field['name']; if (!$editor && array_key_exists($name, $values) && (!array_key_exists($name, $clean) || !Alify_CF_Scoped_Conditions::audit($field, $values[$name], $clean[$name]))) { $errors[] = 'Hidden order fields cannot be changed explicitly.'; } } foreach ($clean as $name => $value) { if (array_key_exists($name, $values)) { $updates[$name] = $value; } } }
        if (array_diff_key($values, $known) || $errors) { return new WP_Error('alify_validation', implode(' ', $errors) ?: 'Unknown order field.'); }
        self::$writing = true; $snapshots = array();
        try { foreach ($updates as $name => $value) { $key = '_alify_cf_' . $name; $snapshots[$name] = array($order->meta_exists($key), $order->get_meta($key, true)); $order->update_meta_data($key, $value); } $order->save(); $fresh = wc_get_order($id); $fresh->read_meta_data(true); foreach ($updates as $name => $value) { $stored = $fresh->get_meta('_alify_cf_' . $name, true); if (is_array($value) ? $stored !== $value : (string) $stored !== (string) $value) { throw new RuntimeException('Order storage verification failed.'); } } return true;
        } catch (Throwable $error) { foreach ($snapshots as $name => $snapshot) { if ($snapshot[0]) { $order->update_meta_data('_alify_cf_' . $name, $snapshot[1]); } else { $order->delete_meta_data('_alify_cf_' . $name); } } try { $order->save(); } catch (Throwable $restore) { return new WP_Error('alify_rollback_failed', 'Order write failed and restoration failed. Review order storage before retrying.'); } return new WP_Error('alify_storage', 'Order write failed; restoration attempted.');
        } finally { self::$writing = false; }
    }
    public static function render($object) {
        $id = is_object($object) && method_exists($object, 'get_id') ? (int) $object->get_id() : (int) ($object->ID ?? 0); $order = self::order($id); if (is_wp_error($order)) { return; }
        wp_nonce_field('alify_order_' . $id, 'alify_order_nonce'); foreach (Alify_CF::groups('woo_order') as $group) { $values = array(); foreach (Alify_CF::schema($group->ID) as $field) { $value = self::get($field['name'], $id); if ($value !== null) { $values[$field['name']] = $value; } } ob_start(); Alify_CF::values((object) array('ID' => 0), $group, $values, array()); echo str_replace('alify_values[', 'alify_order_values[', ob_get_clean()); }
    }
    public static function save_request($id) {
        if (!is_string($_POST['alify_order_nonce'] ?? null) || !wp_verify_nonce(wp_unslash($_POST['alify_order_nonce']), 'alify_order_' . $id)) { return new WP_Error('alify_nonce', 'Order security check failed.'); }
        $payload = wp_unslash($_POST['alify_order_values'] ?? array()); $values = array(); foreach (Alify_CF::groups('woo_order') as $group) { if (!is_array($payload[$group->ID] ?? null) || ($payload[$group->ID]['__group_complete'] ?? '') !== '1') { return new WP_Error('alify_incomplete', 'Incomplete order field group.'); } foreach (Alify_CF::schema($group->ID) as $field) { if (!Alify_CF_Editor_Fields::presentation($field['type'])) { $values[$field['name']] = $payload[$group->ID][$field['name']] ?? ''; } } } return self::write($values, $id, true);
    }
}
