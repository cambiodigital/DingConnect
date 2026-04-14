<?php

if (!defined('ABSPATH')) {
    exit;
}

class DC_Recargas_Admin {
    private $api;

    public function __construct($api) {
        $this->api = $api;

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_dc_add_bundle', [$this, 'handle_add_bundle']);
        add_action('admin_post_dc_delete_bundle', [$this, 'handle_delete_bundle']);
        add_action('admin_post_dc_import_country_presets', [$this, 'handle_import_country_presets']);
        add_action('wp_ajax_dc_search_csv_products', [$this, 'ajax_search_csv_products']);
        add_action('wp_ajax_dc_create_bundle_from_csv', [$this, 'ajax_create_bundle_from_csv']);
    }

    public function register_menu() {
        add_menu_page(
            'DingConnect',
            'DingConnect',
            'manage_options',
            'dc-recargas',
            [$this, 'render_page'],
            'dashicons-smartphone',
            57
        );
    }

    public function register_settings() {
        register_setting('dc_recargas_group', 'dc_recargas_options', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_options'],
            'default' => [],
        ]);
    }

    public function sanitize_options($input) {
        return [
            'api_base' => esc_url_raw(trim((string) ($input['api_base'] ?? 'https://www.dingconnect.com/api/V1'))),
            'api_key' => sanitize_text_field((string) ($input['api_key'] ?? '')),
            'validate_only' => empty($input['validate_only']) ? 0 : 1,
            'allow_real_recharge' => empty($input['allow_real_recharge']) ? 0 : 1,
        ];
    }

    public function handle_add_bundle() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_add_bundle');

        $bundle = [
            'id' => uniqid('bundle_', true),
            'country_iso' => strtoupper(sanitize_text_field($_POST['country_iso'] ?? '')),
            'label' => sanitize_text_field($_POST['label'] ?? ''),
            'sku_code' => sanitize_text_field($_POST['sku_code'] ?? ''),
            'send_value' => (float) ($_POST['send_value'] ?? 0),
            'send_currency_iso' => strtoupper(sanitize_text_field($_POST['send_currency_iso'] ?? 'USD')),
            'provider_name' => sanitize_text_field($_POST['provider_name'] ?? ''),
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'is_active' => empty($_POST['is_active']) ? 0 : 1,
        ];

        if (empty($bundle['country_iso']) || empty($bundle['label']) || empty($bundle['sku_code'])) {
            wp_safe_redirect(add_query_arg([
                'page' => 'dc-recargas',
                'dc_msg' => 'bundle_error',
            ], admin_url('admin.php')));
            exit;
        }

        $bundles = get_option('dc_recargas_bundles', []);
        $bundles[] = $bundle;
        update_option('dc_recargas_bundles', $bundles);

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'bundle_added',
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_delete_bundle() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_delete_bundle');

        $id = sanitize_text_field($_GET['bundle_id'] ?? '');
        $bundles = get_option('dc_recargas_bundles', []);

        $bundles = array_values(array_filter($bundles, function ($bundle) use ($id) {
            return ($bundle['id'] ?? '') !== $id;
        }));

        update_option('dc_recargas_bundles', $bundles);

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'bundle_deleted',
        ], admin_url('admin.php')));
        exit;
    }

    public function handle_import_country_presets() {
        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        check_admin_referer('dc_import_country_presets');

        $bundles = get_option('dc_recargas_bundles', []);
        $existing = [];

        foreach ($bundles as $bundle) {
            $key = strtoupper((string) ($bundle['country_iso'] ?? '')) . '|' . strtoupper((string) ($bundle['sku_code'] ?? ''));
            $existing[$key] = true;
        }

        $added = 0;
        foreach ($this->get_preconfigured_country_bundles() as $preset) {
            $key = strtoupper((string) $preset['country_iso']) . '|' . strtoupper((string) $preset['sku_code']);
            if (!isset($existing[$key])) {
                $bundle = $preset;
                $bundle['id'] = uniqid('bundle_', true);
                $bundles[] = $bundle;
                $existing[$key] = true;
                $added++;
            }
        }

        update_option('dc_recargas_bundles', $bundles);

        wp_safe_redirect(add_query_arg([
            'page' => 'dc-recargas',
            'dc_msg' => 'presets_imported',
            'dc_count' => $added,
        ], admin_url('admin.php')));
        exit;
    }

    public function ajax_search_csv_products() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado.'], 403);
        }

        check_ajax_referer('dc_csv_search', 'nonce');

        $query = sanitize_text_field(wp_unslash($_GET['q'] ?? ''));
        $country = sanitize_text_field(wp_unslash($_GET['country'] ?? ''));

        $items = $this->search_products_csv($query, $country, 120);
        wp_send_json_success([
            'items' => $items,
            'csv_found' => !empty($this->get_products_csv_path()),
        ]);
    }

    public function ajax_create_bundle_from_csv() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado.'], 403);
        }

        check_ajax_referer('dc_csv_search', 'nonce');

        $country_iso = strtoupper(sanitize_text_field(wp_unslash($_POST['country_iso'] ?? '')));
        $sku_code = sanitize_text_field(wp_unslash($_POST['sku_code'] ?? ''));
        $operator = sanitize_text_field(wp_unslash($_POST['operator'] ?? ''));
        $receive = sanitize_text_field(wp_unslash($_POST['receive'] ?? ''));
        $send_value = (float) (wp_unslash($_POST['send_value'] ?? 0));
        $send_currency_iso = strtoupper(sanitize_text_field(wp_unslash($_POST['send_currency_iso'] ?? 'EUR')));
        $is_active = empty($_POST['is_active']) ? 0 : 1;

        if (empty($country_iso) || empty($sku_code) || empty($operator)) {
            wp_send_json_error(['message' => 'Faltan datos obligatorios para crear el bundle.'], 400);
        }

        if ($this->bundle_exists_by_country_sku($country_iso, $sku_code)) {
            wp_send_json_error(['message' => 'Ya existe un bundle con ese país y SKU.'], 409);
        }

        $bundle = [
            'id' => uniqid('bundle_', true),
            'country_iso' => $country_iso,
            'label' => $operator . ' - ' . (!empty($receive) ? $receive : $sku_code),
            'sku_code' => $sku_code,
            'send_value' => $send_value,
            'send_currency_iso' => !empty($send_currency_iso) ? $send_currency_iso : 'EUR',
            'provider_name' => $operator,
            'description' => $receive,
            'is_active' => $is_active,
        ];

        $bundles = get_option('dc_recargas_bundles', []);
        $bundles[] = $bundle;
        update_option('dc_recargas_bundles', $bundles);

        wp_send_json_success([
            'message' => 'Bundle creado correctamente desde CSV.',
            'bundle' => $bundle,
        ]);
    }

    public function render_page() {
        $options = $this->api->get_options();
        $bundles = get_option('dc_recargas_bundles', []);
        $msg = sanitize_text_field($_GET['dc_msg'] ?? '');
        $csv_path = $this->get_products_csv_path();
        $csv_found = !empty($csv_path);
        $csv_nonce = wp_create_nonce('dc_csv_search');
        ?>
        <div class="wrap">
            <h1>DingConnect - Configuración de Recargas</h1>
            <p>Configura tu cuenta de DingConnect, define el modo de prueba y administra bundles visibles en el frontend.</p>

            <?php $this->render_notice($msg); ?>

            <h2>1) Credenciales y modo de operación</h2>
            <form method="post" action="options.php">
                <?php settings_fields('dc_recargas_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dc_api_base">API Base URL</label></th>
                        <td><input type="url" id="dc_api_base" name="dc_recargas_options[api_base]" class="regular-text" value="<?php echo esc_attr($options['api_base']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_api_key">API Key DingConnect</label></th>
                        <td>
                            <input type="text" id="dc_api_key" name="dc_recargas_options[api_key]" class="regular-text" value="<?php echo esc_attr($options['api_key']); ?>">
                            <p class="description">Nunca publiques esta clave en JavaScript. El plugin la usa solo en el servidor.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">ValidateOnly por defecto</th>
                        <td>
                            <label>
                                <input type="checkbox" name="dc_recargas_options[validate_only]" value="1" <?php checked(!empty($options['validate_only'])); ?>>
                                Activar modo seguro (simulación) por defecto.
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Permitir recarga real</th>
                        <td>
                            <label>
                                <input type="checkbox" name="dc_recargas_options[allow_real_recharge]" value="1" <?php checked(!empty($options['allow_real_recharge'])); ?>>
                                Permitir usar <code>ValidateOnly: false</code> en el frontend.
                            </label>
                            <p class="description">Deja esta opción desactivada mientras haces pruebas.</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Guardar configuración'); ?>
            </form>

            <hr>

            <h2>2) Bundles preconfigurados por país</h2>
            <p>Importa en un clic un conjunto base para Colombia, España, México y Cuba usando SKUs del catálogo exportado.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dc_import_country_presets">
                <?php wp_nonce_field('dc_import_country_presets'); ?>
                <?php submit_button('Importar bundles sugeridos (CO, ES, MX, CU)', 'secondary', 'submit', false); ?>
            </form>

            <h2>3) Buscar producto en Products-with-sku.csv</h2>
            <?php if (!$csv_found) : ?>
                <div class="notice notice-warning inline"><p>No se encontró <strong>Products-with-sku.csv</strong>. Para habilitar esta función, coloca el archivo junto al repositorio o dentro del plugin.</p></div>
            <?php else : ?>
                <p>Puedes buscar en todo el catálogo, seleccionar un producto y autocompletar el formulario de bundle.</p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dc_csv_query">Buscar producto</label></th>
                        <td>
                            <input type="text" id="dc_csv_query" class="regular-text" placeholder="SKU, operador, país, descripción...">
                            <select id="dc_csv_country" class="regular-text">
                                <option value="">Todos los países</option>
                                <option value="Colombia">Colombia</option>
                                <option value="Spain">España</option>
                                <option value="Mexico">México</option>
                                <option value="Cuba">Cuba</option>
                            </select>
                            <button type="button" class="button" id="dc_csv_search_btn">Buscar</button>
                            <p class="description">Consejo: escribe al menos 3 caracteres para resultados más precisos.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_csv_results">Resultados</label></th>
                        <td>
                            <select id="dc_csv_results" size="10" class="large-text"></select>
                            <p class="description" id="dc_csv_help">Selecciona un resultado para cargarlo en el formulario de bundle.</p>
                            <p>
                                <label>
                                    <input type="checkbox" id="dc_csv_auto_active" checked>
                                    Publicar bundle inmediatamente (activo)
                                </label>
                            </p>
                            <p>
                                <button type="button" class="button button-primary" id="dc_csv_create_btn">Crear bundle automáticamente desde selección</button>
                            </p>
                        </td>
                    </tr>
                </table>
            <?php endif; ?>

            <h2>4) Añadir bundle curado</h2>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dc_add_bundle">
                <?php wp_nonce_field('dc_add_bundle'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="dc_country_iso">País ISO</label></th>
                        <td><input required type="text" id="dc_country_iso" name="country_iso" class="small-text" placeholder="CU"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_label">Nombre comercial</label></th>
                        <td><input required type="text" id="dc_label" name="label" class="regular-text" placeholder="Cubacel 500 CUP"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_sku_code">SKU Code</label></th>
                        <td><input required type="text" id="dc_sku_code" name="sku_code" class="regular-text" placeholder="SKU_REAL_DING"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_send_value">Monto</label></th>
                        <td><input type="number" step="0.01" id="dc_send_value" name="send_value" class="small-text" value="0"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_send_currency_iso">Moneda</label></th>
                        <td><input type="text" id="dc_send_currency_iso" name="send_currency_iso" class="small-text" value="USD"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_provider_name">Operador</label></th>
                        <td><input type="text" id="dc_provider_name" name="provider_name" class="regular-text" placeholder="Cubacel"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="dc_description">Descripción</label></th>
                        <td><input type="text" id="dc_description" name="description" class="regular-text" placeholder="Saldo principal"></td>
                    </tr>
                    <tr>
                        <th scope="row">Activo</th>
                        <td>
                            <label>
                                <input type="checkbox" name="is_active" value="1" checked>
                                Mostrar bundle en el frontend
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Añadir bundle'); ?>
            </form>

            <h2>5) Bundles guardados</h2>
            <p>Estos bundles aparecen como respaldo o catálogo inicial en el formulario frontal.</p>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>País</th>
                        <th>Nombre</th>
                        <th>SKU</th>
                        <th>Monto</th>
                        <th>Moneda</th>
                        <th>Operador</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($bundles)) : ?>
                    <tr>
                        <td colspan="8">Aún no has agregado bundles.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($bundles as $bundle) : ?>
                        <tr>
                            <td><?php echo esc_html($bundle['country_iso'] ?? ''); ?></td>
                            <td><?php echo esc_html($bundle['label'] ?? ''); ?></td>
                            <td><?php echo esc_html($bundle['sku_code'] ?? ''); ?></td>
                            <td><?php echo esc_html(number_format((float) ($bundle['send_value'] ?? 0), 2)); ?></td>
                            <td><?php echo esc_html($bundle['send_currency_iso'] ?? ''); ?></td>
                            <td><?php echo esc_html($bundle['provider_name'] ?? ''); ?></td>
                            <td><?php echo !empty($bundle['is_active']) ? 'Activo' : 'Inactivo'; ?></td>
                            <td>
                                <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(add_query_arg([
                                    'action' => 'dc_delete_bundle',
                                    'bundle_id' => $bundle['id'] ?? '',
                                ], admin_url('admin-post.php')), 'dc_delete_bundle')); ?>" onclick="return confirm('¿Eliminar bundle?');">
                                    Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <hr>
            <h2>6) Uso en frontend</h2>
            <p>Crea una página y coloca el shortcode:</p>
            <p><code>[dingconnect_recargas]</code></p>
        </div>
        <?php if ($csv_found) : ?>
            <script>
            (function () {
                var searchBtn = document.getElementById('dc_csv_search_btn');
                var queryEl = document.getElementById('dc_csv_query');
                var countryEl = document.getElementById('dc_csv_country');
                var resultsEl = document.getElementById('dc_csv_results');
                var helpEl = document.getElementById('dc_csv_help');
                var createBtn = document.getElementById('dc_csv_create_btn');
                var autoActiveEl = document.getElementById('dc_csv_auto_active');

                if (!searchBtn || !queryEl || !resultsEl) {
                    return;
                }

                var countryIsoEl = document.getElementById('dc_country_iso');
                var labelEl = document.getElementById('dc_label');
                var skuEl = document.getElementById('dc_sku_code');
                var sendValueEl = document.getElementById('dc_send_value');
                var sendCurrencyEl = document.getElementById('dc_send_currency_iso');
                var providerEl = document.getElementById('dc_provider_name');
                var descriptionEl = document.getElementById('dc_description');

                function optionLabel(item) {
                    return '[' + (item.country_iso || '--') + '] ' + item.operator + ' | ' + item.send_amount + ' | ' + item.receive + ' | ' + item.sku_code;
                }

                function fillForm(item) {
                    if (countryIsoEl) countryIsoEl.value = item.country_iso || '';
                    if (labelEl) labelEl.value = (item.operator || 'Producto') + ' - ' + (item.receive || item.sku_code || '');
                    if (skuEl) skuEl.value = item.sku_code || '';
                    if (sendValueEl) sendValueEl.value = item.send_value || '';
                    if (sendCurrencyEl) sendCurrencyEl.value = item.send_currency_iso || 'EUR';
                    if (providerEl) providerEl.value = item.operator || '';
                    if (descriptionEl) descriptionEl.value = item.receive || '';
                }

                function renderResults(items) {
                    resultsEl.innerHTML = '';

                    if (!items || !items.length) {
                        var none = document.createElement('option');
                        none.value = '';
                        none.textContent = 'Sin resultados para el filtro actual.';
                        resultsEl.appendChild(none);
                        helpEl.textContent = 'No se encontraron coincidencias.';
                        if (createBtn) createBtn.disabled = true;
                        return;
                    }

                    items.forEach(function (item) {
                        var opt = document.createElement('option');
                        opt.value = item.sku_code;
                        opt.textContent = optionLabel(item);
                        opt.dataset.item = JSON.stringify(item);
                        resultsEl.appendChild(opt);
                    });

                    helpEl.textContent = 'Se encontraron ' + items.length + ' resultados. Selecciona uno para cargarlo en el formulario.';
                    if (createBtn) createBtn.disabled = false;
                }

                async function createBundleFromSelection() {
                    var selected = resultsEl.options[resultsEl.selectedIndex];
                    if (!selected || !selected.dataset.item) {
                        helpEl.textContent = 'Selecciona primero un producto del listado.';
                        return;
                    }

                    var item;
                    try {
                        item = JSON.parse(selected.dataset.item);
                    } catch (e) {
                        helpEl.textContent = 'No se pudo leer el resultado seleccionado.';
                        return;
                    }

                    if (!createBtn) {
                        return;
                    }

                    createBtn.disabled = true;
                    createBtn.textContent = 'Creando...';

                    try {
                        var body = new URLSearchParams();
                        body.append('action', 'dc_create_bundle_from_csv');
                        body.append('nonce', '<?php echo esc_js($csv_nonce); ?>');
                        body.append('country_iso', item.country_iso || '');
                        body.append('sku_code', item.sku_code || '');
                        body.append('operator', item.operator || '');
                        body.append('receive', item.receive || '');
                        body.append('send_value', item.send_value || 0);
                        body.append('send_currency_iso', item.send_currency_iso || 'EUR');
                        body.append('is_active', (autoActiveEl && autoActiveEl.checked) ? '1' : '0');

                        var response = await fetch(ajaxurl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                            },
                            body: body.toString()
                        });

                        var data = await response.json();
                        if (!data || !data.success) {
                            var errorMsg = (data && data.data && data.data.message) ? data.data.message : 'No se pudo crear el bundle.';
                            throw new Error(errorMsg);
                        }

                        fillForm(item);
                        helpEl.textContent = 'Bundle creado correctamente. Recargando lista de bundles...';
                        window.location.href = '<?php echo esc_url(add_query_arg(['page' => 'dc-recargas', 'dc_msg' => 'bundle_added'], admin_url('admin.php'))); ?>';
                    } catch (err) {
                        helpEl.textContent = err.message || 'Error al crear bundle automático.';
                    } finally {
                        createBtn.disabled = false;
                        createBtn.textContent = 'Crear bundle automáticamente desde selección';
                    }
                }

                async function doSearch() {
                    var query = (queryEl.value || '').trim();
                    var country = countryEl ? countryEl.value : '';

                    searchBtn.disabled = true;
                    searchBtn.textContent = 'Buscando...';
                    helpEl.textContent = 'Consultando catálogo...';

                    try {
                        var url = ajaxurl + '?action=dc_search_csv_products'
                            + '&nonce=' + encodeURIComponent('<?php echo esc_js($csv_nonce); ?>')
                            + '&q=' + encodeURIComponent(query)
                            + '&country=' + encodeURIComponent(country);

                        var response = await fetch(url);
                        var data = await response.json();

                        if (!data || !data.success) {
                            throw new Error('No se pudo leer el catálogo CSV.');
                        }

                        renderResults((data.data && data.data.items) ? data.data.items : []);
                    } catch (err) {
                        resultsEl.innerHTML = '';
                        var errorOpt = document.createElement('option');
                        errorOpt.value = '';
                        errorOpt.textContent = 'Error al consultar catálogo CSV.';
                        resultsEl.appendChild(errorOpt);
                        helpEl.textContent = err.message || 'Error inesperado.';
                    } finally {
                        searchBtn.disabled = false;
                        searchBtn.textContent = 'Buscar';
                    }
                }

                searchBtn.addEventListener('click', doSearch);
                queryEl.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        doSearch();
                    }
                });

                resultsEl.addEventListener('change', function () {
                    var selected = resultsEl.options[resultsEl.selectedIndex];
                    if (!selected || !selected.dataset.item) {
                        return;
                    }

                    try {
                        fillForm(JSON.parse(selected.dataset.item));
                    } catch (e) {
                        helpEl.textContent = 'No se pudo interpretar el producto seleccionado.';
                    }
                });

                if (createBtn) {
                    createBtn.disabled = true;
                    createBtn.addEventListener('click', createBundleFromSelection);
                }
            })();
            </script>
        <?php endif; ?>
        <?php
    }

    private function render_notice($msg) {
        $count = isset($_GET['dc_count']) ? (int) $_GET['dc_count'] : 0;
        $map = [
            'bundle_added' => ['success', 'Bundle agregado correctamente.'],
            'bundle_deleted' => ['success', 'Bundle eliminado correctamente.'],
            'bundle_error' => ['error', 'Completa País ISO, Nombre y SKU para añadir un bundle.'],
            'presets_imported' => ['success', sprintf('Importación completada. Bundles nuevos agregados: %d.', $count)],
        ];

        if (!isset($map[$msg])) {
            return;
        }

        [$class, $text] = $map[$msg];
        echo '<div class="notice notice-' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    private function get_preconfigured_country_bundles() {
        return [
            [
                'country_iso' => 'CO',
                'label' => 'Claro Colombia - Recarga libre',
                'sku_code' => 'CO_CO_TopUp',
                'send_value' => 2.29,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'Claro Colombia',
                'description' => 'Top-up de rango para Claro Colombia.',
                'is_active' => 1,
            ],
            [
                'country_iso' => 'CO',
                'label' => 'Claro Colombia - 300 MIN 1 día',
                'sku_code' => 'F4CO50927',
                'send_value' => 0.78,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'Claro Colombia Bundles',
                'description' => '300 MIN for 1 day',
                'is_active' => 1,
            ],
            [
                'country_iso' => 'CO',
                'label' => 'Claro Colombia - 2 GB 7 días',
                'sku_code' => 'F4CO40857',
                'send_value' => 2.50,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'Claro Colombia Bundles',
                'description' => '2GB data - Unlimited WhatsApp, Facebook & Twitter - 7 days',
                'is_active' => 1,
            ],
            [
                'country_iso' => 'ES',
                'label' => 'MasMovil España - 10 EUR',
                'sku_code' => 'ES_AS_TopUp_10.00',
                'send_value' => 12.96,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'MasMovil Spain',
                'description' => 'Top-up de 10 EUR.',
                'is_active' => 1,
            ],
            [
                'country_iso' => 'ES',
                'label' => 'Digimobil España - 10 EUR',
                'sku_code' => 'ES_DS_TopUp_10.00',
                'send_value' => 12.96,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'Digimobil Spain',
                'description' => 'Top-up de 10 EUR.',
                'is_active' => 1,
            ],
            [
                'country_iso' => 'ES',
                'label' => 'Lebara España - 5 EUR',
                'sku_code' => 'ES_ES_TopUp_5.00',
                'send_value' => 6.48,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'Lebara Spain',
                'description' => 'Top-up de 5 EUR.',
                'is_active' => 1,
            ],
            [
                'country_iso' => 'MX',
                'label' => 'Flash Mobile México - MXN 100',
                'sku_code' => '9IMX4550',
                'send_value' => 6.27,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'Flash Mobile Mexico',
                'description' => 'Top-up de MXN 100.',
                'is_active' => 1,
            ],
            [
                'country_iso' => 'MX',
                'label' => 'Flash Mobile México - MXN 300',
                'sku_code' => '9IMX7102',
                'send_value' => 18.81,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'Flash Mobile Mexico',
                'description' => 'Top-up de MXN 300.',
                'is_active' => 1,
            ],
            [
                'country_iso' => 'MX',
                'label' => 'Valor México - MXN 100',
                'sku_code' => '3VMX94204',
                'send_value' => 6.27,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'Valor Mexico',
                'description' => 'Top-up de MXN 100.',
                'is_active' => 1,
            ],
            [
                'country_iso' => 'CU',
                'label' => 'Cubacel Cuba - Recarga libre',
                'sku_code' => 'CU_CU_TopUp',
                'send_value' => 5.23,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'Cubacel Cuba',
                'description' => 'Top-up de rango para Cubacel.',
                'is_active' => 1,
            ],
            [
                'country_iso' => 'CU',
                'label' => 'Cubacel Cuba Bundle - 5GB + 4GB + 75MIN + 80SMS',
                'sku_code' => 'DFCUCU53685',
                'send_value' => 20.90,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'Cubacel Cuba Bundles',
                'description' => '5GB Solo 4G - 4GB 2G/3G/4G - 75 MIN - 80 SMS - Valid for 30 Days',
                'is_active' => 1,
            ],
            [
                'country_iso' => 'CU',
                'label' => 'Nauta Plus Cuba - Internet ilimitado 15 días',
                'sku_code' => 'CYCU35796',
                'send_value' => 8.11,
                'send_currency_iso' => 'EUR',
                'provider_name' => 'Nauta Plus Cuba',
                'description' => 'Unlimited internet 15 days - 50% off',
                'is_active' => 1,
            ],
        ];
    }

    private function get_products_csv_path() {
        $candidates = [
            trailingslashit(dirname(DC_RECARGAS_PATH, 2)) . 'Products-with-sku.csv',
            trailingslashit(DC_RECARGAS_PATH) . 'Products-with-sku.csv',
        ];

        foreach ($candidates as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return '';
    }

    private function search_products_csv($query, $country, $limit = 120) {
        $path = $this->get_products_csv_path();
        if (empty($path)) {
            return [];
        }

        $query = strtolower(trim((string) $query));
        $country = trim((string) $country);
        $limit = max(1, min((int) $limit, 300));

        $results = [];
        $handle = fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        $header = fgetcsv($handle);
        if (!is_array($header)) {
            fclose($handle);
            return [];
        }

        $indexes = array_flip($header);
        $required = ['Operator', 'Country', 'Send amount', 'Receive', 'Product type', 'Validity', 'SkuCode'];
        foreach ($required as $key) {
            if (!isset($indexes[$key])) {
                fclose($handle);
                return [];
            }
        }

        while (($row = fgetcsv($handle)) !== false) {
            $operator = trim((string) ($row[$indexes['Operator']] ?? ''));
            $country_name = trim((string) ($row[$indexes['Country']] ?? ''));
            $send_amount = trim((string) ($row[$indexes['Send amount']] ?? ''));
            $receive = trim((string) ($row[$indexes['Receive']] ?? ''));
            $product_type = trim((string) ($row[$indexes['Product type']] ?? ''));
            $validity = trim((string) ($row[$indexes['Validity']] ?? ''));
            $sku_code = trim((string) ($row[$indexes['SkuCode']] ?? ''));

            if (empty($sku_code) || empty($operator)) {
                continue;
            }

            if (!empty($country) && strcasecmp($country_name, $country) !== 0) {
                continue;
            }

            if (!empty($query)) {
                $haystack = strtolower(implode(' ', [$operator, $country_name, $send_amount, $receive, $product_type, $validity, $sku_code]));
                if (strpos($haystack, $query) === false) {
                    continue;
                }
            }

            $country_iso = $this->country_name_to_iso($country_name);
            $send_value = $this->extract_first_number($send_amount);
            $send_currency = $this->extract_currency_from_send_amount($send_amount);

            $results[] = [
                'operator' => $operator,
                'country' => $country_name,
                'country_iso' => $country_iso,
                'send_amount' => $send_amount,
                'send_value' => $send_value,
                'send_currency_iso' => $send_currency,
                'receive' => $receive,
                'product_type' => $product_type,
                'validity' => $validity,
                'sku_code' => $sku_code,
            ];

            if (count($results) >= $limit) {
                break;
            }
        }

        fclose($handle);
        return $results;
    }

    private function extract_first_number($text) {
        $text = (string) $text;
        if (preg_match('/([0-9]+(?:[\.,][0-9]+)?)/', $text, $matches)) {
            return (float) str_replace(',', '.', $matches[1]);
        }

        return 0.0;
    }

    private function extract_currency_from_send_amount($text) {
        $text = strtoupper((string) $text);
        if (preg_match('/\b([A-Z]{3})\b/', $text, $matches)) {
            return $matches[1];
        }

        return 'EUR';
    }

    private function country_name_to_iso($country_name) {
        $map = [
            'COLOMBIA' => 'CO',
            'SPAIN' => 'ES',
            'MEXICO' => 'MX',
            'CUBA' => 'CU',
            'ARGENTINA' => 'AR',
            'PERU' => 'PE',
            'BRAZIL' => 'BR',
            'VENEZUELA' => 'VE',
            'DOMINICAN REPUBLIC' => 'DO',
        ];

        $key = strtoupper(trim((string) $country_name));
        return $map[$key] ?? '';
    }

    private function bundle_exists_by_country_sku($country_iso, $sku_code) {
        $country_iso = strtoupper((string) $country_iso);
        $sku_code = strtoupper((string) $sku_code);
        $bundles = get_option('dc_recargas_bundles', []);

        foreach ($bundles as $bundle) {
            $bundle_country = strtoupper((string) ($bundle['country_iso'] ?? ''));
            $bundle_sku = strtoupper((string) ($bundle['sku_code'] ?? ''));
            if ($bundle_country === $country_iso && $bundle_sku === $sku_code) {
                return true;
            }
        }

        return false;
    }
}
