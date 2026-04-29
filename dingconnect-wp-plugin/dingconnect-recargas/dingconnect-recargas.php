<?php
/**
 * Plugin Name: DingConnect Recargas
 * Description: Plugin para vender recargas y bundles con DingConnect
 * desde WordPress. Hecho por Cambiodigital.net, personalizado para cubakilos.com.
 * Version: 2.5.0
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
        echo '<div class="notice notice-error"><p><strong>DingConnect Recargas:</strong> ya existe otra copia del plugin cargada desde una carpeta diferente. Elimina las copias duplicadas en <code>wp-content/plugins</code> antes de activar esta versi�n. <em>Hecho por Cambiodigital.net, personalizado para cubakilos.com.</em></p></div>';
    });
    return;
}

if (!defined('DC_RECARGAS_VERSION')) {
    define('DC_RECARGAS_VERSION', '1.3.0');
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

// Registrar hook de inicializaci�n - aca se cargan las clases
add_action('plugins_loaded', function () {
    // Verificar si ya se carg� en otra instancia
    if (class_exists('DC_Recargas_API')) {
        return;
    }

    // Intentar cargar archivos requeridos
    $base_path = DC_RECARGAS_PATH;
    $base_paths = [rtrim($base_path, '/\\') . DIRECTORY_SEPARATOR];

    $nested_base = rtrim($base_path, '/\\') . DIRECTORY_SEPARATOR . 'dingconnect-recargas' . DIRECTORY_SEPARATOR;
    if (is_dir($nested_base)) {
        $base_paths[] = $nested_base;
    }

    $resolve_required_file = static function ($relative_file, $candidate_base_paths) {
        $normalized = trim(str_replace('\\', '/', $relative_file), '/');
        $variants = [
            $normalized,
            str_replace('/', DIRECTORY_SEPARATOR, $normalized),
            str_replace('/', '\\', $normalized),
        ];

        foreach ($candidate_base_paths as $candidate_base) {
            $candidate_base = rtrim($candidate_base, '/\\') . DIRECTORY_SEPARATOR;
            foreach ($variants as $variant) {
                $candidate = $candidate_base . ltrim($variant, '/\\');
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return '';
    };

    $files = [
        'includes/class-dc-api.php',
        'includes/class-dc-admin.php',
        'includes/class-dc-rest.php',
        'includes/class-dc-frontend.php',
    ];

    $load_errors = [];
    foreach ($files as $file) {
        $full_path = $resolve_required_file($file, $base_paths);
        if ($full_path !== '') {
            require_once $full_path;
        } else {
            $load_errors[] = $file;
        }
    }

    // Si hay errores en la carga, mostrar aviso
    if (!empty($load_errors) || !class_exists('DC_Recargas_API')) {
        add_action('admin_notices', function () use ($load_errors, $base_path, $base_paths) {
            if (!current_user_can('manage_options')) {
                return;
            }
            $msg = __('DingConnect Recargas: no se pudieron cargar los archivos requeridos.', 'dingconnect-recargas');
            if (!empty($load_errors)) {
                $msg .= ' ' . __('Archivos no encontrados:', 'dingconnect-recargas') . ' ' . implode(', ', $load_errors);
            }
            $msg .= ' [base_path: ' . $base_path . ']';
            $msg .= ' [rutas probadas: ' . implode(' | ', $base_paths) . ']';
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
        $woo_file = $resolve_required_file('includes/class-dc-woocommerce.php', $base_paths);
        if ($woo_file !== '') {
            require_once $woo_file;
            new DC_Recargas_WooCommerce($api);
        }

        // Registrar email de confirmación de recarga en el sistema de emails de WooCommerce
        $email_file = $resolve_required_file('includes/class-dc-email-recarga-confirmacion.php', $base_paths);
        if ($email_file !== '') {
            add_filter('woocommerce_email_classes', function ($email_classes) use ($email_file) {
                if (!isset($email_classes['WC_DC_Email_Recarga_Confirmacion'])) {
                    require_once $email_file;
                    $email_classes['WC_DC_Email_Recarga_Confirmacion'] = new WC_DC_Email_Recarga_Confirmacion();
                }
                return $email_classes;
            });
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
        'manual_amount_mode' => 'range_products',
        'validate_only' => 1,
        'allow_real_recharge' => 0,
        'woo_allowed_gateways' => [],
        'submitted_retry_max_attempts' => 4,
        'submitted_retry_backoff_minutes' => '10,20,40,80',
        'submitted_max_window_hours' => 12,
        'submitted_escalation_email' => '',
        'submitted_non_retryable_codes' => 'InsufficientBalance,AccountNumberInvalid,RechargeNotAllowed',
    ];

    if (!get_option('dc_recargas_options')) {
        add_option('dc_recargas_options', $default_options);
    }

    if (!get_option('dc_recargas_bundles')) {
        add_option('dc_recargas_bundles', []);
    }
});
