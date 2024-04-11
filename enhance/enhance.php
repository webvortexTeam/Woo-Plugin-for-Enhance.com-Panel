<?php
/**
 * Plugin Name: Enhance Integration by Vortex
 * Plugin URI: https://webvortex.com/enhance-integration
 * Description: A professional WordPress starter plugin focusing on enhanced security measures.
 * Version: 1.0.0
 * Author: WebVortex
 * Author URI: https://webvortex.com
 * License: GPL-2.0-or-later
 */

define('VORTEX_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VORTEX_PLUGIN_URL', plugin_dir_url(__FILE__));

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

require_once VORTEX_PLUGIN_DIR . 'includes/vortex-admin.php';
require_once VORTEX_PLUGIN_DIR . 'includes/vortex-product.php';
require_once VORTEX_PLUGIN_DIR . 'includes/vortex-domain-validation.php';
require_once VORTEX_PLUGIN_DIR . 'includes/vortex-user-check.php';
require_once VORTEX_PLUGIN_DIR . 'includes/vortex-create-user.php';

add_action('plugins_loaded', 'vortex_init_plugin');

function vortex_init_plugin() {
    if (is_admin()) {
        new \Vortex\Admin();
    }
}
