<?php
/**
 * Plugin Name: Alify Custom Field
 * Description: Build custom field groups, repeatable content and global site settings with a clean editor.
 * Version: 0.18.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: Alify
 * License: GPL-2.0-or-later
 * Text Domain: alify-custom-field
 */
if (!defined('ABSPATH')) { exit; }
define('ALIFY_CF_VERSION', '0.18.0');
define('ALIFY_CF_FILE', __FILE__);
require_once __DIR__ . '/includes/class-alify-cf.php';
require_once __DIR__ . '/includes/functions.php';
Alify_CF::boot();
