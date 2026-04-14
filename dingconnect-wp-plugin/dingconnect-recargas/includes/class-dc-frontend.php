<?php

if (!defined('ABSPATH')) {
    exit;
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

        wp_localize_script('dc-recargas-frontend', 'DC_RECARGAS_DATA', [
            'restBase' => esc_url_raw(rest_url('dingconnect/v1')),
            'countries' => $this->countries(),
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
                <h2>Recargas Internacionales</h2>
                <p class="dc-subtitle">Selecciona país, número móvil y bundle para procesar tu recarga.</p>

                <div class="dc-grid">
                    <div class="dc-field">
                        <label for="dc-country">País</label>
                        <select id="dc-country"></select>
                    </div>

                    <div class="dc-field">
                        <label for="dc-phone">Número móvil</label>
                        <input id="dc-phone" type="tel" maxlength="15" placeholder="Número sin prefijo">
                    </div>

                    <div class="dc-field dc-field-full">
                        <button id="dc-search" class="dc-btn" type="button">Buscar paquetes</button>
                    </div>

                    <div class="dc-field dc-field-full">
                        <label for="dc-bundle">Bundle</label>
                        <select id="dc-bundle" disabled>
                            <option value="">Primero consulta paquetes</option>
                        </select>
                    </div>

                    <div class="dc-field dc-field-full">
                        <button id="dc-transfer" class="dc-btn dc-btn-success" type="button" disabled>Procesar recarga</button>
                    </div>
                </div>

                <div id="dc-feedback" class="dc-feedback" aria-live="polite"></div>
                <pre id="dc-result" class="dc-result" hidden></pre>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    private function countries() {
        return [
            ['iso' => 'CU', 'name' => 'Cuba', 'dial' => '53'],
            ['iso' => 'DO', 'name' => 'República Dominicana', 'dial' => '1'],
            ['iso' => 'MX', 'name' => 'México', 'dial' => '52'],
            ['iso' => 'CO', 'name' => 'Colombia', 'dial' => '57'],
            ['iso' => 'VE', 'name' => 'Venezuela', 'dial' => '58'],
            ['iso' => 'PE', 'name' => 'Perú', 'dial' => '51'],
            ['iso' => 'AR', 'name' => 'Argentina', 'dial' => '54'],
            ['iso' => 'BR', 'name' => 'Brasil', 'dial' => '55'],
        ];
    }
}
