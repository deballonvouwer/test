<?php
/**
 * Plugin Name: Google Review Requests
 * Description: Automatiseer Google review verzoeken naar klanten na afronding.
 * Version: 1.0.0
 * Author: Codex
 * Text Domain: grr
 */

if (! defined('ABSPATH')) {
    exit;
}

define('GRR_PLUGIN_FILE', __FILE__);
define('GRR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('GRR_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once GRR_PLUGIN_PATH . 'includes/class-grr-plugin.php';

register_activation_hook(GRR_PLUGIN_FILE, ['GRR_Plugin', 'activate']);
register_deactivation_hook(GRR_PLUGIN_FILE, ['GRR_Plugin', 'deactivate']);

function grr_boot_plugin() {
    GRR_Plugin::instance();
}
add_action('plugins_loaded', 'grr_boot_plugin');
