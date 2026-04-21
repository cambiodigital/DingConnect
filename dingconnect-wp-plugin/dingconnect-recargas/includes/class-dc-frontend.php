<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('DC_Recargas_Frontend')) {
    return;
}

class DC_Recargas_Frontend {
    private $api;

    public function __construct($api = null) {
        $this->api = $api;
        add_shortcode('dingconnect_recargas', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
    }

    public function register_assets() {
        wp_register_style(
            'dc-recargas-frontend',
            DC_RECARGAS_URL . 'assets/css/frontend.css',
            [],
            DC_RECARGAS_VERSION
        );

        wp_register_script(
            'dc-recargas-frontend',
            DC_RECARGAS_URL . 'assets/js/frontend.js',
            [],
            DC_RECARGAS_VERSION,
            true
        );

        $opts = get_option('dc_recargas_options', []);

        wp_localize_script('dc-recargas-frontend', 'DC_RECARGAS_DATA', [
            'restBase' => esc_url_raw(rest_url('dingconnect/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'countries' => self::get_country_reference_list(),
            'woocommerce_active' => (($opts['payment_mode'] ?? 'direct') === 'woocommerce') && class_exists('WooCommerce'),
            'cartUrl' => class_exists('WooCommerce') ? wc_get_cart_url() : '',
            'checkoutUrl' => class_exists('WooCommerce') ? wc_get_checkout_url() : '',
            'texts' => [
                'loading' => 'Consultando paquetes...',
                'search' => 'Buscar paquetes',
                'pay' => 'Procesar recarga',
            ],
        ]);
    }

    public function render_shortcode($atts = []) {
        wp_enqueue_style('dc-recargas-frontend');
        wp_enqueue_script('dc-recargas-frontend');

        $atts = shortcode_atts([
            'landing_key' => '',
            'bundles' => '',
            'country' => '',
            'title' => 'Recargas Internacionales',
            'subtitle' => 'Ingresa el número y elige tu paquete',
        ], (array) $atts, 'dingconnect_recargas');

        $landing_key = sanitize_key((string) $atts['landing_key']);
        $config = $this->get_landing_shortcode_config($landing_key);

        $bundle_ids = $this->parse_bundle_ids((string) $atts['bundles']);
        if (!empty($config['bundle_ids']) && is_array($config['bundle_ids'])) {
            $bundle_ids = array_values(array_unique(array_merge($bundle_ids, $this->parse_bundle_ids(implode(',', $config['bundle_ids'])))));
        }

        $default_country_iso = strtoupper(sanitize_text_field((string) $atts['country']));
        if ($default_country_iso === '' && !empty($config['country_iso'])) {
            $default_country_iso = strtoupper(sanitize_text_field((string) $config['country_iso']));
        }

        $title = sanitize_text_field((string) $atts['title']);
        if ($title === 'Recargas Internacionales' && !empty($config['title'])) {
            $title = sanitize_text_field((string) $config['title']);
        }

        $subtitle = sanitize_text_field((string) $atts['subtitle']);
        if ($subtitle === 'Ingresa el número y elige tu paquete' && !empty($config['subtitle'])) {
            $subtitle = sanitize_text_field((string) $config['subtitle']);
        }

        $bundle_attr = implode(',', $bundle_ids);
        $available_countries = $this->get_available_countries_for_shortcode($bundle_ids);
        if ($default_country_iso === '' && count($available_countries) === 1) {
            $default_country_iso = strtoupper(sanitize_text_field((string) ($available_countries[0]['iso'] ?? '')));
        }

        ob_start();
        ?>
        <div class="dc-recargas" id="dc-recargas-app" data-allowed-bundle-ids="<?php echo esc_attr($bundle_attr); ?>" data-default-country-iso="<?php echo esc_attr($default_country_iso); ?>" data-available-countries="<?php echo esc_attr(wp_json_encode($available_countries)); ?>">
            <div class="dc-card">
                <div class="dc-header">
                    <h2><?php echo esc_html($title); ?></h2>
                    <p><?php echo esc_html($subtitle); ?></p>
                </div>

                <div class="dc-phone-row" id="dc-phone-row">
                    <button type="button" class="dc-country-btn" id="dc-country-btn">
                        <span class="dc-country-flag" id="dc-country-flag"></span>
                        <span class="dc-country-dial" id="dc-country-dial"></span>
                        <svg class="dc-chevron-icon" viewBox="0 0 12 8" width="10" height="8"><path d="M1 1.5l5 5 5-5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
                    </button>
                    <input id="dc-phone" type="tel" inputmode="numeric" maxlength="15" placeholder="Número móvil" autocomplete="tel-national">
                </div>

                <div id="dc-active-step" class="dc-active-step" hidden>
                    <div class="dc-active-step-header">
                        <span class="dc-active-step-kicker">Paquete activo</span>
                        <button type="button" class="dc-change-bundle-btn" id="dc-change-bundle-btn">Cambiar paquete</button>
                    </div>
                    <select id="dc-active-bundle-select" class="dc-active-bundle-select" aria-label="Seleccionar paquete activo"></select>
                    <div id="dc-active-bundle-info" class="dc-active-bundle-info"></div>
                </div>

                <div id="dc-confirm" class="dc-confirm" hidden>
                    <div class="dc-confirm-summary" id="dc-confirm-summary"></div>
                    <button type="button" class="dc-confirm-btn" id="dc-confirm-btn">Confirmar recarga</button>
                </div>

                <div class="dc-country-overlay" id="dc-country-overlay" hidden>
                    <div class="dc-country-panel">
                        <div class="dc-country-panel-header">
                            <span>Selecciona país</span>
                            <button type="button" class="dc-country-close" id="dc-country-close">&times;</button>
                        </div>
                        <input id="dc-country-search" type="text" class="dc-country-search" placeholder="Buscar país..." autocomplete="off">
                        <div id="dc-country-list" class="dc-country-list"></div>
                    </div>
                </div>

                <div id="dc-loading" class="dc-loading" hidden>
                    <div class="dc-spinner"></div>
                    <span>Buscando paquetes disponibles...</span>
                </div>

                <div id="dc-provider-filter" class="dc-provider-filter" hidden>
                    <div class="dc-provider-label">Selecciona un operador</div>
                    <div id="dc-provider-buttons" class="dc-provider-buttons"></div>
                </div>

                <div id="dc-bundles" class="dc-bundles" hidden></div>

                <div id="dc-feedback" class="dc-feedback" aria-live="polite"></div>
                <div id="dc-result" class="dc-result" hidden></div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    private function get_landing_shortcode_config($landing_key) {
        if ($landing_key === '') {
            return [];
        }

        $configs = get_option('dc_recargas_landing_shortcodes', []);
        if (!is_array($configs)) {
            return [];
        }

        foreach ($configs as $config) {
            if (!is_array($config)) {
                continue;
            }

            if (sanitize_key((string) ($config['key'] ?? '')) === $landing_key) {
                return $config;
            }
        }

        return [];
    }

    private function parse_bundle_ids($raw_ids) {
        $parts = array_map('trim', explode(',', (string) $raw_ids));
        $clean = [];

        foreach ($parts as $id) {
            $id = sanitize_text_field($id);
            if ($id !== '') {
                $clean[] = $id;
            }
        }

        return array_values(array_unique($clean));
    }

    private function get_available_countries_for_shortcode($bundle_ids) {
        $reference_map = self::get_country_reference_map();
        $selected_isos = [];
        $bundle_ids = array_values(array_unique(array_filter(array_map('strval', (array) $bundle_ids))));
        $bundle_id_map = !empty($bundle_ids) ? array_fill_keys($bundle_ids, true) : [];
        $bundles = get_option('dc_recargas_bundles', []);

        foreach ((array) $bundles as $bundle) {
            if (!is_array($bundle)) {
                continue;
            }

            $bundle_id = sanitize_text_field((string) ($bundle['id'] ?? ''));
            if (!empty($bundle_id_map) && !isset($bundle_id_map[$bundle_id])) {
                continue;
            }

            if (empty($bundle_id_map) && empty($bundle['is_active'])) {
                continue;
            }

            $country_iso = strtoupper(sanitize_text_field((string) ($bundle['country_iso'] ?? '')));
            if ($country_iso !== '') {
                $selected_isos[$country_iso] = true;
            }
        }

        if (empty($selected_isos)) {
            return self::get_country_reference_list();
        }

        $countries = [];
        foreach (array_keys($selected_isos) as $country_iso) {
            if (isset($reference_map[$country_iso])) {
                $countries[] = $reference_map[$country_iso];
            } else {
                $countries[] = [
                    'iso' => $country_iso,
                    'name' => $country_iso,
                    'dial' => '',
                ];
            }
        }

        usort($countries, function ($left, $right) {
            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return array_values($countries);
    }

    public static function get_country_reference_map() {
        $map = [];
        foreach (self::get_country_reference_list() as $country) {
            $iso = strtoupper((string) ($country['iso'] ?? ''));
            if ($iso === '') {
                continue;
            }

            $map[$iso] = $country;
        }

        return $map;
    }

    public static function get_country_reference_list() {
        return [
            ['iso' => 'AF', 'name' => 'Afganistán', 'dial' => '93'],
            ['iso' => 'AL', 'name' => 'Albania', 'dial' => '355'],
            ['iso' => 'DZ', 'name' => 'Argelia', 'dial' => '213'],
            ['iso' => 'AO', 'name' => 'Angola', 'dial' => '244'],
            ['iso' => 'AR', 'name' => 'Argentina', 'dial' => '54'],
            ['iso' => 'AM', 'name' => 'Armenia', 'dial' => '374'],
            ['iso' => 'BD', 'name' => 'Bangladés', 'dial' => '880'],
            ['iso' => 'BY', 'name' => 'Bielorrusia', 'dial' => '375'],
            ['iso' => 'BZ', 'name' => 'Belice', 'dial' => '501'],
            ['iso' => 'BJ', 'name' => 'Benín', 'dial' => '229'],
            ['iso' => 'BO', 'name' => 'Bolivia', 'dial' => '591'],
            ['iso' => 'BR', 'name' => 'Brasil', 'dial' => '55'],
            ['iso' => 'BF', 'name' => 'Burkina Faso', 'dial' => '226'],
            ['iso' => 'BI', 'name' => 'Burundi', 'dial' => '257'],
            ['iso' => 'KH', 'name' => 'Camboya', 'dial' => '855'],
            ['iso' => 'CM', 'name' => 'Camerún', 'dial' => '237'],
            ['iso' => 'CF', 'name' => 'Rep. Centroafricana', 'dial' => '236'],
            ['iso' => 'TD', 'name' => 'Chad', 'dial' => '235'],
            ['iso' => 'CL', 'name' => 'Chile', 'dial' => '56'],
            ['iso' => 'CN', 'name' => 'China', 'dial' => '86'],
            ['iso' => 'CO', 'name' => 'Colombia', 'dial' => '57'],
            ['iso' => 'KM', 'name' => 'Comoras', 'dial' => '269'],
            ['iso' => 'CG', 'name' => 'Congo', 'dial' => '242'],
            ['iso' => 'CD', 'name' => 'Congo (RDC)', 'dial' => '243'],
            ['iso' => 'CR', 'name' => 'Costa Rica', 'dial' => '506'],
            ['iso' => 'CI', 'name' => 'Costa de Marfil', 'dial' => '225'],
            ['iso' => 'CU', 'name' => 'Cuba', 'dial' => '53'],
            ['iso' => 'DO', 'name' => 'República Dominicana', 'dial' => '1809'],
            ['iso' => 'EC', 'name' => 'Ecuador', 'dial' => '593'],
            ['iso' => 'EG', 'name' => 'Egipto', 'dial' => '20'],
            ['iso' => 'SV', 'name' => 'El Salvador', 'dial' => '503'],
            ['iso' => 'ES', 'name' => 'España', 'dial' => '34'],
            ['iso' => 'ET', 'name' => 'Etiopía', 'dial' => '251'],
            ['iso' => 'FJ', 'name' => 'Fiyi', 'dial' => '679'],
            ['iso' => 'PH', 'name' => 'Filipinas', 'dial' => '63'],
            ['iso' => 'GA', 'name' => 'Gabón', 'dial' => '241'],
            ['iso' => 'GM', 'name' => 'Gambia', 'dial' => '220'],
            ['iso' => 'GE', 'name' => 'Georgia', 'dial' => '995'],
            ['iso' => 'GH', 'name' => 'Ghana', 'dial' => '233'],
            ['iso' => 'GT', 'name' => 'Guatemala', 'dial' => '502'],
            ['iso' => 'GN', 'name' => 'Guinea', 'dial' => '224'],
            ['iso' => 'GW', 'name' => 'Guinea-Bisáu', 'dial' => '245'],
            ['iso' => 'GY', 'name' => 'Guyana', 'dial' => '592'],
            ['iso' => 'HT', 'name' => 'Haití', 'dial' => '509'],
            ['iso' => 'HN', 'name' => 'Honduras', 'dial' => '504'],
            ['iso' => 'IN', 'name' => 'India', 'dial' => '91'],
            ['iso' => 'ID', 'name' => 'Indonesia', 'dial' => '62'],
            ['iso' => 'IQ', 'name' => 'Irak', 'dial' => '964'],
            ['iso' => 'JM', 'name' => 'Jamaica', 'dial' => '1876'],
            ['iso' => 'JO', 'name' => 'Jordania', 'dial' => '962'],
            ['iso' => 'KZ', 'name' => 'Kazajistán', 'dial' => '7'],
            ['iso' => 'KE', 'name' => 'Kenia', 'dial' => '254'],
            ['iso' => 'KG', 'name' => 'Kirguistán', 'dial' => '996'],
            ['iso' => 'LA', 'name' => 'Laos', 'dial' => '856'],
            ['iso' => 'LR', 'name' => 'Liberia', 'dial' => '231'],
            ['iso' => 'MG', 'name' => 'Madagascar', 'dial' => '261'],
            ['iso' => 'MW', 'name' => 'Malaui', 'dial' => '265'],
            ['iso' => 'MY', 'name' => 'Malasia', 'dial' => '60'],
            ['iso' => 'ML', 'name' => 'Malí', 'dial' => '223'],
            ['iso' => 'MR', 'name' => 'Mauritania', 'dial' => '222'],
            ['iso' => 'MX', 'name' => 'México', 'dial' => '52'],
            ['iso' => 'MD', 'name' => 'Moldavia', 'dial' => '373'],
            ['iso' => 'MZ', 'name' => 'Mozambique', 'dial' => '258'],
            ['iso' => 'MM', 'name' => 'Myanmar', 'dial' => '95'],
            ['iso' => 'NA', 'name' => 'Namibia', 'dial' => '264'],
            ['iso' => 'NP', 'name' => 'Nepal', 'dial' => '977'],
            ['iso' => 'NI', 'name' => 'Nicaragua', 'dial' => '505'],
            ['iso' => 'NE', 'name' => 'Níger', 'dial' => '227'],
            ['iso' => 'NG', 'name' => 'Nigeria', 'dial' => '234'],
            ['iso' => 'PK', 'name' => 'Pakistán', 'dial' => '92'],
            ['iso' => 'PA', 'name' => 'Panamá', 'dial' => '507'],
            ['iso' => 'PG', 'name' => 'Papúa Nueva Guinea', 'dial' => '675'],
            ['iso' => 'PY', 'name' => 'Paraguay', 'dial' => '595'],
            ['iso' => 'PE', 'name' => 'Perú', 'dial' => '51'],
            ['iso' => 'PR', 'name' => 'Puerto Rico', 'dial' => '1787'],
            ['iso' => 'RW', 'name' => 'Ruanda', 'dial' => '250'],
            ['iso' => 'RO', 'name' => 'Rumanía', 'dial' => '40'],
            ['iso' => 'RU', 'name' => 'Rusia', 'dial' => '7'],
            ['iso' => 'SN', 'name' => 'Senegal', 'dial' => '221'],
            ['iso' => 'SL', 'name' => 'Sierra Leona', 'dial' => '232'],
            ['iso' => 'SO', 'name' => 'Somalia', 'dial' => '252'],
            ['iso' => 'ZA', 'name' => 'Sudáfrica', 'dial' => '27'],
            ['iso' => 'LK', 'name' => 'Sri Lanka', 'dial' => '94'],
            ['iso' => 'SD', 'name' => 'Sudán', 'dial' => '249'],
            ['iso' => 'TZ', 'name' => 'Tanzania', 'dial' => '255'],
            ['iso' => 'TH', 'name' => 'Tailandia', 'dial' => '66'],
            ['iso' => 'TG', 'name' => 'Togo', 'dial' => '228'],
            ['iso' => 'TT', 'name' => 'Trinidad y Tobago', 'dial' => '1868'],
            ['iso' => 'TN', 'name' => 'Túnez', 'dial' => '216'],
            ['iso' => 'TR', 'name' => 'Turquía', 'dial' => '90'],
            ['iso' => 'UG', 'name' => 'Uganda', 'dial' => '256'],
            ['iso' => 'UA', 'name' => 'Ucrania', 'dial' => '380'],
            ['iso' => 'UY', 'name' => 'Uruguay', 'dial' => '598'],
            ['iso' => 'UZ', 'name' => 'Uzbekistán', 'dial' => '998'],
            ['iso' => 'VE', 'name' => 'Venezuela', 'dial' => '58'],
            ['iso' => 'VN', 'name' => 'Vietnam', 'dial' => '84'],
            ['iso' => 'ZM', 'name' => 'Zambia', 'dial' => '260'],
            ['iso' => 'ZW', 'name' => 'Zimbabue', 'dial' => '263'],
        ];
    }
}
