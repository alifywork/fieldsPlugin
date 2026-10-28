<?php
if (!defined('ABSPATH')) { exit; }

/** Optional GitHub release distribution. No activation, accounts, telemetry or licensing service. */
final class Alify_CF_Updates {
    const REPOSITORY = 'alifywork/fieldsPlugin';
    public static function boot() {
        add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'offer'));
        add_filter('upgrader_pre_download', array(__CLASS__, 'download'), 10, 4);
    }
    public static function parse($release) {
        if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease']) || !is_string($release['tag_name'] ?? null) || !preg_match('/^v?([0-9]+\.[0-9]+\.[0-9]+)$/D', $release['tag_name'], $match)) { return null; }
        $out = array('version' => $match[1]); $prefix = 'https://github.com/' . self::REPOSITORY . '/releases/download/' . $release['tag_name'] . '/';
        foreach ($release['assets'] ?? array() as $asset) { if (!is_array($asset) || !is_string($asset['browser_download_url'] ?? null)) { continue; } foreach (array('package' => 'alify-custom-field.zip', 'checksum' => 'alify-custom-field.zip.sha256') as $key => $name) { if (($asset['name'] ?? '') === $name && $asset['browser_download_url'] === $prefix . $name) { $out[$key] = $asset['browser_download_url']; } } }
        return isset($out['package'], $out['checksum']) ? $out : null;
    }
    public static function release() {
        if (!Alify_CF_Integrations::enabled('GITHUB_UPDATES')) { return null; }
        $cached = get_site_transient('alify_cf_github_release'); if ($cached !== false) { return is_array($cached) ? $cached : null; }
        $response = wp_safe_remote_get('https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest', array('timeout' => 10, 'redirection' => 0, 'limit_response_size' => 262144, 'headers' => array('Accept' => 'application/vnd.github+json')));
        $release = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200 ? self::parse(json_decode(wp_remote_retrieve_body($response), true)) : null;
        set_site_transient('alify_cf_github_release', $release ?: 'unavailable', $release ? 21600 : 900); return $release;
    }
    public static function offer($transient) {
        if (!is_object($transient) || empty($transient->checked)) { return $transient; } $release = self::release(); $plugin = plugin_basename(ALIFY_CF_FILE);
        if ($release && version_compare($release['version'], ALIFY_CF_VERSION, '>')) { $transient->response[$plugin] = (object) array('slug' => dirname($plugin), 'plugin' => $plugin, 'new_version' => $release['version'], 'url' => 'https://github.com/' . self::REPOSITORY, 'package' => $release['package'], 'requires' => '6.2', 'requires_php' => '7.4'); }
        return $transient;
    }
    public static function download($reply, $package, $upgrader = null, $extra = array()) {
        if (!is_string($package) || strpos($package, 'https://github.com/' . self::REPOSITORY . '/releases/download/') !== 0) { return $reply; }
        $release = self::release(); if (!$release || $package !== $release['package']) { return new WP_Error('alify_package', 'Managed Alify package is unavailable or no longer the verified offered release.'); }
        if ($reply !== false) { return new WP_Error('alify_package', 'Another downloader replaced the Alify package; checksum verification cannot be skipped.'); }
        $response = wp_safe_remote_get($release['checksum'], array('timeout' => 15, 'redirection' => 3, 'limit_response_size' => 512)); $body = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200 ? trim(wp_remote_retrieve_body($response)) : '';
        if (!preg_match('/^([a-fA-F0-9]{64})(?:\s+\*?alify-custom-field\.zip)?$/D', $body, $match)) { return new WP_Error('alify_checksum', 'Release checksum is unavailable or invalid.'); }
        if (!function_exists('download_url')) { require_once ABSPATH . 'wp-admin/includes/file.php'; } $file = download_url($package, 60); if (is_wp_error($file)) { return $file; }
        if (!hash_equals(strtolower($match[1]), hash_file('sha256', $file))) { unlink($file); return new WP_Error('alify_checksum', 'Release archive checksum mismatch. Installation stopped.'); }
        return $file;
    }
}
