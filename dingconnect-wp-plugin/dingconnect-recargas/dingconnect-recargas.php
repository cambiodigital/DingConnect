<?php
/**
 * Plugin Name: DingConnect Recargas
 * Description: Plugin para vender recargas y bundles con DingConnect desde WordPress. Hecho por Cambiodigital.net, personalizado para cubakilos.com.
 * Version: 1.2.4
 * Author: Cambiodigital.net (personalizado para cubakilos.com)
 * Author URI: https://cambiodigital.net
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: dingconnect-recargas
 */

if (!defined('ABSPATH')) {
    exit;
}

$dc_recargas_current_file = __FILE__;

if (defined('DC_RECARGAS_FILE') && realpath(DC_RECARGAS_FILE) !== realpath($dc_recargas_current_file)) {
    add_action('admin_notices', function () {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>DingConnect Recargas:</strong> ya existe otra copia del plugin cargada desde una carpeta diferente. Elimina las copias duplicadas en <code>wp-content/plugins</code> antes de activar esta versión. <em>Hecho por Cambiodigital.net, personalizado para cubakilos.com.</em></p></div>';
    });
    return;
}

if (!defined('DC_RECARGAS_VERSION')) {
    define('DC_RECARGAS_VERSION', '1.2.1');
}

if (!defined('DC_RECARGAS_FILE')) {
    define('DC_RECARGAS_FILE', $dc_recargas_current_file);
}

if (!defined('DC_RECARGAS_PATH')) {
    define('DC_RECARGAS_PATH', plugin_dir_path(__FILE__));
}

if (!defined('DC_RECARGAS_URL')) {
    define('DC_RECARGAS_URL', plugin_dir_url(__FILE__));
}

// Registrar hook de inicialización - aca se cargan las clases
add_action('plugins_loaded', function () {
    // Verificar si ya se cargó en otra instancia
    if (class_exists('DC_Recargas_API')) {
        return;
    }

    // Intentar cargar archivos requeridos
    $base_path = DC_RECARGAS_PATH;
    $files = [
        'includes/class-dc-api.php',
        'includes/class-dc-admin.php',
        'includes/class-dc-rest.php',
        'includes/class-dc-frontend.php',
    ];

    $load_errors = [];
    foreach ($files as $file) {
        $full_path = $base_path . $file;
        if (file_exists($full_path)) {
            require_once $full_path;
        } else {
            $load_errors[] = $file;
        }
    }

    // Si hay errores en la carga, mostrar aviso
    if (!empty($load_errors) || !class_exists('DC_Recargas_API')) {
        add_action('admin_notices', function () use ($load_errors, $base_path) {
            if (!current_user_can('manage_options')) {
                return;
            }
            $msg = __('DingConnect Recargas: no se pudieron cargar los archivos requeridos.', 'dingconnect-recargas');
            if (!empty($load_errors)) {
                $msg .= ' ' . __('Archivos no encontrados:', 'dingconnect-recargas') . ' ' . implode(', ', $load_errors);
            }
            $msg .= ' [base_path: ' . $base_path . ']';
            echo '<div class="notice notice-error"><p><strong>DingConnect Recargas:</strong> ' . esc_html($msg) . ' <em>Hecho por Cambiodigital.net, personalizado para cubakilos.com.</em></p></div>';
        });
        return;
    }

    // Inicializar plugin
    $api = new DC_Recargas_API();

    if (is_admin()) {
        new DC_Recargas_Admin($api);
    }

    new DC_Recargas_REST($api);
    new DC_Recargas_Frontend($api);

    if (class_exists('WooCommerce')) {
        if (file_exists(DC_RECARGAS_PATH . 'includes/class-dc-woocommerce.php')) {
            require_once DC_RECARGAS_PATH . 'includes/class-dc-woocommerce.php';
            new DC_Recargas_WooCommerce($api);
        } elseif (file_exists(DC_RECARGAS_PATH . 'includes' . DIRECTORY_SEPARATOR . 'class-dc-woocommerce.php')) {
            require_once DC_RECARGAS_PATH . 'includes' . DIRECTORY_SEPARATOR . 'class-dc-woocommerce.php';
            new DC_Recargas_WooCommerce($api);
        }
    }

    add_action('init', ['DC_Recargas_API', 'register_transfer_log_cpt']);
});

register_activation_hook(DC_RECARGAS_FILE, function () {
    $default_options = [
        'api_base' => 'https://www.dingconnect.com/api/V1',
        'api_key' => '',
        'payment_mode' => 'direct',
        'recharge_mode' => 'test_simulate',
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

