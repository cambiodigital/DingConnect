<?php
/**
 * Plugin Name: DingConnect Recargas
 * Description: Plugin para vender recargas y bundles con DingConnect desde WordPress.
 * Version: 1.1.0
 * Author: Cubakilos
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: dingconnect-recargas
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DC_RECARGAS_VERSION', '1.1.0');
define('DC_RECARGAS_FILE', __FILE__);
define('DC_RECARGAS_PATH', plugin_dir_path(__FILE__));
define('DC_RECARGAS_URL', plugin_dir_url(__FILE__));

require_once DC_RECARGAS_PATH . 'includes/class-dc-api.php';
require_once DC_RECARGAS_PATH . 'includes/class-dc-admin.php';
require_once DC_RECARGAS_PATH . 'includes/class-dc-rest.php';
require_once DC_RECARGAS_PATH . 'includes/class-dc-frontend.php';

register_activation_hook(DC_RECARGAS_FILE, function () {
    $default_options = [
        'api_base' => 'https://www.dingconnect.com/api/V1',
        'api_key' => '',
        'validate_only' => 1,
        'allow_real_recharge' => 0,
    ];

    if (!get_option('dc_recargas_options')) {
        add_option('dc_recargas_options', $default_options);
    }

    if (!get_option('dc_recargas_bundles')) {
        add_option('dc_recargas_bundles', []);
    }
});

add_action('plugins_loaded', function () {
    $api = new DC_Recargas_API();

    if (is_admin()) {
        new DC_Recargas_Admin($api);
    }

    new DC_Recargas_REST($api);
    new DC_Recargas_Frontend();
});
