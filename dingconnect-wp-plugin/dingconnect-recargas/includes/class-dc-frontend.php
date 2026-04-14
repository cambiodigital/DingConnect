<?php

if (!defined('ABSPATH')) {
    exit;
}

if (class_exists('DC_Recargas_Frontend')) {
    return;
}

class DC_Recargas_Frontend {
    public function __construct() {
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
            'countries' => $this->countries(),
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

    public function render_shortcode() {
        wp_enqueue_style('dc-recargas-frontend');
        wp_enqueue_script('dc-recargas-frontend');

        ob_start();
        ?>
        <div class="dc-recargas" id="dc-recargas-app">
            <div class="dc-card">
                <div class="dc-header">
                    <h2>Recargas Internacionales</h2>
                    <p>Ingresa el número y elige tu paquete</p>
                </div>

                <div class="dc-phone-row" id="dc-phone-row">
                    <button type="button" class="dc-country-btn" id="dc-country-btn">
                        <span class="dc-country-flag" id="dc-country-flag"></span>
                        <span class="dc-country-dial" id="dc-country-dial"></span>
                        <svg class="dc-chevron-icon" viewBox="0 0 12 8" width="10" height="8"><path d="M1 1.5l5 5 5-5" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/></svg>
                    </button>
                    <input id="dc-phone" type="tel" inputmode="numeric" maxlength="15" placeholder="Número móvil" autocomplete="tel-national">
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

                <div id="dc-confirm" class="dc-confirm" hidden>
                    <div class="dc-confirm-summary" id="dc-confirm-summary"></div>
                    <button type="button" class="dc-confirm-btn" id="dc-confirm-btn">Confirmar recarga</button>
                </div>

                <div id="dc-feedback" class="dc-feedback" aria-live="polite"></div>
                <div id="dc-result" class="dc-result" hidden></div>

                <p class="dc-credit">Hecho por Cambiodigital.net · cubakilos.com</p>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    private function countries() {
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
