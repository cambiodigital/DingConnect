<?php
/**
 * Development-only WordPress stubs for static analysis in VS Code.
 * This file is not loaded by WordPress runtime.
 */

if (!function_exists('add_action')) { function add_action(...$args) { return true; } }
if (!function_exists('add_filter')) { function add_filter(...$args) { return true; } }
if (!function_exists('apply_filters')) { function apply_filters($tag, $value, ...$args) { return $value; } }
if (!function_exists('do_action')) { function do_action(...$args) { return null; } }
if (!function_exists('add_shortcode')) { function add_shortcode(...$args) { return true; } }
if (!function_exists('shortcode_atts')) { function shortcode_atts($pairs, $atts, $shortcode = '') { return array_merge((array) $pairs, (array) $atts); } }
if (!function_exists('add_menu_page')) { function add_menu_page(...$args) { return ''; } }
if (!function_exists('register_setting')) { function register_setting(...$args) { return true; } }
if (!function_exists('register_activation_hook')) { function register_activation_hook(...$args) { return true; } }
if (!function_exists('register_deactivation_hook')) { function register_deactivation_hook(...$args) { return true; } }
if (!function_exists('register_uninstall_hook')) { function register_uninstall_hook(...$args) { return true; } }
if (!function_exists('register_rest_route')) { function register_rest_route(...$args) { return true; } }
if (!function_exists('rest_url')) { function rest_url($path = '') { return $path; } }
if (!function_exists('rest_ensure_response')) { function rest_ensure_response($response) { return $response; } }
if (!function_exists('wp_create_nonce')) { function wp_create_nonce($action = -1) { return 'nonce'; } }
if (!function_exists('wp_verify_nonce')) { function wp_verify_nonce($nonce, $action = -1) { return true; } }
if (!function_exists('check_admin_referer')) { function check_admin_referer(...$args) { return true; } }
if (!function_exists('check_ajax_referer')) { function check_ajax_referer(...$args) { return true; } }
if (!function_exists('current_user_can')) { function current_user_can(...$args) { return true; } }
if (!function_exists('wp_die')) { function wp_die($message = '') { throw new Exception((string) $message); } }
if (!function_exists('wp_safe_redirect')) { function wp_safe_redirect(...$args) { return true; } }
if (!function_exists('wp_redirect')) { function wp_redirect(...$args) { return true; } }
if (!function_exists('admin_url')) { function admin_url($path = '', $scheme = 'admin') { return $path; } }
if (!function_exists('home_url')) { function home_url($path = '', $scheme = null) { return $path; } }
if (!function_exists('site_url')) { function site_url($path = '', $scheme = null) { return $path; } }
if (!function_exists('plugins_url')) { function plugins_url($path = '', $plugin = '') { return $path; } }
if (!function_exists('plugin_dir_path')) { function plugin_dir_path($file) { return dirname((string) $file) . DIRECTORY_SEPARATOR; } }
if (!function_exists('plugin_dir_url')) { function plugin_dir_url($file) { return ''; } }
if (!function_exists('get_option')) { function get_option($option, $default = false) { return $default; } }
if (!function_exists('add_option')) { function add_option($option, $value = '', $deprecated = '', $autoload = 'yes') { return true; } }
if (!function_exists('update_option')) { function update_option($option, $value, $autoload = null) { return true; } }
if (!function_exists('delete_option')) { function delete_option($option) { return true; } }
if (!function_exists('wp_parse_args')) { function wp_parse_args($args, $defaults = []) { return array_merge((array) $defaults, (array) $args); } }
if (!function_exists('wp_generate_password')) { function wp_generate_password($length = 12, $special_chars = true, $extra_special_chars = false) { return str_repeat('a', max(1, (int) $length)); } }
if (!function_exists('register_post_type')) { function register_post_type($post_type, $args = []) { return (object) ['name' => $post_type]; } }
if (!function_exists('untrailingslashit')) { function untrailingslashit($value) { return rtrim((string) $value, '/\\'); } }
if (!function_exists('trailingslashit')) { function trailingslashit($value) { return rtrim((string) $value, '/\\') . '/'; } }
if (!function_exists('wp_remote_request')) { function wp_remote_request($url, $args = []) { return ['response' => ['code' => 200], 'body' => '']; } }
if (!function_exists('wp_remote_retrieve_response_code')) { function wp_remote_retrieve_response_code($response) { return (int) (($response['response']['code'] ?? 0)); } }
if (!function_exists('wp_remote_retrieve_body')) { function wp_remote_retrieve_body($response) { return (string) ($response['body'] ?? ''); } }
if (!function_exists('get_transient')) { function get_transient($transient) { return false; } }
if (!function_exists('set_transient')) { function set_transient($transient, $value, $expiration = 0) { return true; } }
if (!function_exists('delete_transient')) { function delete_transient($transient) { return true; } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($str) { return is_scalar($str) ? trim((string) $str) : ''; } }
if (!function_exists('sanitize_key')) { function sanitize_key($key) { return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $key)); } }
if (!function_exists('sanitize_title')) { function sanitize_title($title, $fallback_title = '', $context = 'save') { return strtolower(trim(preg_replace('/[^a-zA-Z0-9\-]+/', '-', (string) $title), '-')); } }
if (!function_exists('sanitize_email')) { function sanitize_email($email) { return (string) $email; } }
if (!function_exists('sanitize_file_name')) { function sanitize_file_name($filename) { return (string) $filename; } }
if (!function_exists('esc_html')) { function esc_html($text) { return (string) $text; } }
if (!function_exists('esc_attr')) { function esc_attr($text) { return (string) $text; } }
if (!function_exists('esc_url')) { function esc_url($url, $protocols = null, $context = 'display') { return (string) $url; } }
if (!function_exists('esc_url_raw')) { function esc_url_raw($url, $protocols = null) { return (string) $url; } }
if (!function_exists('esc_js')) { function esc_js($text) { return (string) $text; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($value, $flags = 0, $depth = 512) { return json_encode($value, $flags, $depth); } }
if (!function_exists('wp_unslash')) { function wp_unslash($value) { return $value; } }
if (!function_exists('wp_slash')) { function wp_slash($value) { return $value; } }
if (!function_exists('selected')) { function selected($selected, $current = true, $echo = true) { $result = ((string) $selected === (string) $current) ? 'selected="selected"' : ''; if ($echo) { echo $result; } return $result; } }
if (!function_exists('checked')) { function checked($checked, $current = true, $echo = true) { $result = ((string) $checked === (string) $current) ? 'checked="checked"' : ''; if ($echo) { echo $result; } return $result; } }
if (!function_exists('disabled')) { function disabled($disabled, $current = true, $echo = true) { $result = ((string) $disabled === (string) $current) ? 'disabled="disabled"' : ''; if ($echo) { echo $result; } return $result; } }
if (!function_exists('submit_button')) { function submit_button(...$args) { return null; } }
if (!function_exists('settings_fields')) { function settings_fields(...$args) { return null; } }
if (!function_exists('settings_errors')) { function settings_errors(...$args) { return null; } }
if (!function_exists('add_settings_error')) { function add_settings_error(...$args) { return null; } }
if (!function_exists('wp_nonce_field')) { function wp_nonce_field(...$args) { return null; } }
if (!function_exists('wp_nonce_url')) { function wp_nonce_url($actionurl, $action = -1, $name = '_wpnonce') { return (string) $actionurl; } }
if (!function_exists('add_query_arg')) { function add_query_arg($args, $url = '') { return (string) $url; } }
if (!function_exists('remove_query_arg')) { function remove_query_arg($key, $query = false) { return (string) $query; } }
if (!function_exists('wp_enqueue_style')) { function wp_enqueue_style(...$args) { return true; } }
if (!function_exists('wp_enqueue_script')) { function wp_enqueue_script(...$args) { return true; } }
if (!function_exists('wp_register_style')) { function wp_register_style(...$args) { return true; } }
if (!function_exists('wp_register_script')) { function wp_register_script(...$args) { return true; } }
if (!function_exists('wp_localize_script')) { function wp_localize_script(...$args) { return true; } }
if (!function_exists('wp_send_json_success')) { function wp_send_json_success($data = null, $status_code = null) { return ['success' => true, 'data' => $data, 'status' => $status_code]; } }
if (!function_exists('wp_send_json_error')) { function wp_send_json_error($data = null, $status_code = null) { return ['success' => false, 'data' => $data, 'status' => $status_code]; } }
if (!function_exists('get_post_meta')) { function get_post_meta($post_id, $key = '', $single = false) { return $single ? '' : []; } }
if (!function_exists('update_post_meta')) { function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') { return true; } }
if (!function_exists('add_post_meta')) { function add_post_meta($post_id, $meta_key, $meta_value, $unique = false) { return true; } }
if (!function_exists('delete_post_meta')) { function delete_post_meta($post_id, $meta_key, $meta_value = '') { return true; } }
if (!function_exists('wp_insert_post')) { function wp_insert_post($postarr = [], $wp_error = false, $fire_after_hooks = true) { return 0; } }
if (!function_exists('get_posts')) { function get_posts($args = null) { return []; } }
if (!function_exists('get_post')) { function get_post($post = null, $output = OBJECT, $filter = 'raw') { return null; } }
if (!function_exists('get_post_status')) { function get_post_status($post = null) { return 'publish'; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return 0; } }
if (!function_exists('is_admin')) { function is_admin() { return false; } }
if (!function_exists('did_action')) { function did_action($hook_name) { return 0; } }
if (!function_exists('get_user_by')) { function get_user_by($field, $value) { return null; } }
if (!function_exists('wp_check_password')) { function wp_check_password($password, $hash, $user_id = '') { return false; } }
if (!function_exists('wc_get_order')) { function wc_get_order($the_order = false) { return null; } }
if (!function_exists('__')) { function __($text, $domain = 'default') { return (string) $text; } }
if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event($timestamp, $hook, $args = [], $wp_error = false) { return true; } }
if (!function_exists('wp_clear_scheduled_hook')) { function wp_clear_scheduled_hook($hook, $args = [], $wp_error = false) { return 0; } }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled($hook, $args = []) { return false; } }
if (!function_exists('current_time')) { function current_time($type, $gmt = 0) { return date('Y-m-d H:i:s'); } }
if (!function_exists('is_wp_error')) { function is_wp_error($thing) { return $thing instanceof WP_Error; } }
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($text, $remove_breaks = false) { return strip_tags((string) $text); } }
if (!function_exists('wp_upload_dir')) { function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false) { return ['path' => '', 'url' => '', 'subdir' => '', 'basedir' => sys_get_temp_dir(), 'baseurl' => '', 'error' => false]; } }
if (!function_exists('wp_mkdir_p')) { function wp_mkdir_p($target) { return true; } }
if (!function_exists('esc_textarea')) { function esc_textarea($text) { return (string) $text; } }
if (!function_exists('wp_count_posts')) { function wp_count_posts($type = 'post', $perm = '') { return (object) ['publish' => 0]; } }
if (!function_exists('get_the_date')) { function get_the_date($format = '', $post = null) { return date($format ?: 'Y-m-d H:i:s'); } }
if (!function_exists('esc_html__')) { function esc_html__($text, $domain = 'default') { return (string) $text; } }
if (!function_exists('wp_delete_post')) { function wp_delete_post($post_id = 0, $force_delete = false) { return true; } }
if (!function_exists('wc_get_cart_url')) { function wc_get_cart_url() { return ''; } }
if (!function_exists('wc_get_checkout_url')) { function wc_get_checkout_url() { return ''; } }

if (!defined('MINUTE_IN_SECONDS')) { define('MINUTE_IN_SECONDS', 60); }
if (!defined('HOUR_IN_SECONDS')) { define('HOUR_IN_SECONDS', 3600); }
if (!defined('DAY_IN_SECONDS')) { define('DAY_IN_SECONDS', 86400); }
if (!defined('OBJECT')) { define('OBJECT', 'OBJECT'); }
if (!defined('REST_REQUEST')) { define('REST_REQUEST', false); }
if (!defined('DOING_AJAX')) { define('DOING_AJAX', false); }

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct($code = '', $message = '', $data = '') {}
        public function get_error_message($code = '') { return ''; }
        public function get_error_data($code = '') { return null; }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        public function get_param($key) { return null; }
        public function get_json_params() { return []; }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public function __construct($data = null, $status = 200, $headers = []) {}
    }
}

if (!class_exists('WP_REST_Server')) {
    class WP_REST_Server {
        const READABLE = 'GET';
        const CREATABLE = 'POST';
    }
}

if (!class_exists('WP_Query')) {
    class WP_Query {
        public $posts = [];
        public $post_count = 0;
        public $found_posts = 0;
        public $max_num_pages = 0;

        public function __construct($args = []) {}
        public function have_posts() { return false; }
        public function the_post() { return null; }
    }
}

if (!class_exists('WP_User')) {
    class WP_User {
        public $ID = 0;
        public $user_pass = '';
    }
}

if (!class_exists('WC_Payment_Gateways')) {
    class WC_Payment_Gateways {
        public static function instance() { return new self(); }
        public function payment_gateways() { return []; }
    }
}

if (!class_exists('WC_Product_Simple')) {
    class WC_Product_Simple {
        public function set_name($name) {}
        public function set_status($status) {}
        public function set_catalog_visibility($visibility) {}
        public function set_sold_individually($sold_individually) {}
        public function set_virtual($virtual) {}
        public function set_regular_price($price) {}
        public function save() { return 0; }
    }
}

if (!class_exists('WC_Session_Handler')) {
    class WC_Session_Handler {
        public function init() {}
    }
}

if (!class_exists('WC_Cart')) {
    class WC_Cart {
        public function get_cart() { return []; }
        public function add_to_cart($product_id, $quantity = 1, $variation_id = 0, $variation = [], $cart_item_data = []) { return true; }
    }
}

if (!class_exists('WC_Customer')) {
    class WC_Customer {
        public function __construct($id = 0) {}
    }
}

if (!class_exists('WC_Order_Item_Product')) {
    class WC_Order_Item_Product {
        public function get_product_id() { return 0; }
        public function get_meta($key = '', $single = true) { return null; }
        public function add_meta_data($key, $value, $unique = false) {}
    }
}

if (!class_exists('WC_Order')) {
    class WC_Order {
        public function get_items($types = 'line_item') { return []; }
        public function get_meta($key = '', $single = true) { return null; }
        public function update_meta_data($key, $value) {}
        public function add_order_note($note, $is_customer_note = 0, $added_by_user = false) {}
        public function save() {}
        public function is_paid() { return false; }
        public function get_id() { return 0; }
    }
}

if (!function_exists('WC')) {
    function WC() {
        static $instance = null;
        if ($instance === null) {
            $instance = (object) [
                'session' => null,
                'cart' => null,
                'customer' => null,
            ];
        }
        return $instance;
    }
}
