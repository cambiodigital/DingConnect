(function () {
    var app = document.getElementById('dc-recargas-app');
    if (!app || typeof DC_RECARGAS_DATA === 'undefined') {
        return;
    }

    var countryEl = document.getElementById('dc-country');
    var phoneEl = document.getElementById('dc-phone');
    var bundleEl = document.getElementById('dc-bundle');
    var searchBtn = document.getElementById('dc-search');
    var transferBtn = document.getElementById('dc-transfer');
    var feedbackEl = document.getElementById('dc-feedback');
    var resultEl = document.getElementById('dc-result');
    var autoSearchTimer = null;

    var state = {
        bundles: [],
        selected: null,
        fullPhone: '',
        mode: 'idle',
        lastSearchKey: '',
        inFlightSearchKey: '',
    };

    function setFeedback(message, type) {
        feedbackEl.className = 'dc-feedback ' + (type || '');
        feedbackEl.textContent = message || '';
    }

    function setLoading(button, loadingText, isLoading) {
        if (!button.dataset.defaultText) {
            button.dataset.defaultText = button.textContent;
        }
        button.disabled = isLoading;
        button.textContent = isLoading ? loadingText : button.dataset.defaultText;
    }

    function fillCountries() {
        var countries = Array.isArray(DC_RECARGAS_DATA.countries) ? DC_RECARGAS_DATA.countries : [];
        countryEl.innerHTML = '';
        countries.forEach(function (country) {
            var opt = document.createElement('option');
            opt.value = country.iso;
            opt.textContent = '+' + country.dial + ' ' + country.name;
            opt.dataset.dial = country.dial;
            countryEl.appendChild(opt);
        });
    }

    function getSelectedCountry() {
        var option = countryEl.options[countryEl.selectedIndex];
        return {
            iso: option ? option.value : '',
            dial: option ? option.dataset.dial : '',
        };
    }

    function normalizePhone() {
        var country = getSelectedCountry();
        var local = String(phoneEl.value || '').replace(/\D/g, '');
        if (!local || local.length < 5) {
            return '';
        }
        return '+' + country.dial + local;
    }

    function setBundles(items) {
        state.bundles = Array.isArray(items) ? items : [];
        bundleEl.innerHTML = '';

        if (!state.bundles.length) {
            var none = document.createElement('option');
            none.value = '';
            none.textContent = 'No hay bundles para este número';
            bundleEl.appendChild(none);
            bundleEl.disabled = true;
            transferBtn.disabled = true;
            state.selected = null;
            return;
        }

        var first = document.createElement('option');
        first.value = '';
        first.textContent = 'Selecciona un bundle';
        bundleEl.appendChild(first);

        state.bundles.forEach(function (bundle) {
            var opt = document.createElement('option');
            opt.value = bundle.SkuCode;
            opt.textContent = [
                bundle.ProviderName || 'Operador',
                bundle.DefaultDisplayText || bundle.SkuCode,
                bundle.SendCurrencyIso + ' ' + Number(bundle.SendValue || 0).toFixed(2),
            ].join(' — ');
            bundleEl.appendChild(opt);
        });

        bundleEl.disabled = false;
    }

    function clearBundles(message) {
        bundleEl.innerHTML = '';
        var none = document.createElement('option');
        none.value = '';
        none.textContent = message || 'Primero consulta paquetes';
        bundleEl.appendChild(none);
        bundleEl.disabled = true;
        transferBtn.disabled = true;
        state.selected = null;
    }

    async function fetchJson(path, options) {
        var url = DC_RECARGAS_DATA.restBase.replace(/\/$/, '') + path;
        var opts = options || {};
        // Include nonce for authenticated endpoints
        if (DC_RECARGAS_DATA.nonce) {
            opts.headers = Object.assign({}, opts.headers || {}, {
                'X-WP-Nonce': DC_RECARGAS_DATA.nonce,
            });
        }
        var response = await fetch(url, opts);
        var data = await response.json();
        if (!response.ok) {
            var message = (data && data.message) || 'Error en la solicitud.';
            throw new Error(message);
        }
        return data;
    }

    async function searchBundles(options) {
        var opts = options || {};
        var silentValidation = !!opts.silentValidation;
        var force = !!opts.force;
        var fullPhone = normalizePhone();
        if (!fullPhone) {
            if (!silentValidation) {
                setFeedback('Ingresa un número móvil válido.', 'error');
            }
            clearBundles('Ingresa un número válido para consultar paquetes');
            resultEl.hidden = true;
            state.lastSearchKey = '';
            state.inFlightSearchKey = '';
            return;
        }

        var country = getSelectedCountry();
        var searchKey = country.iso + '|' + fullPhone;

        if (!force && (state.lastSearchKey === searchKey || state.inFlightSearchKey === searchKey)) {
            return;
        }

        state.fullPhone = fullPhone;
        state.inFlightSearchKey = searchKey;

        setLoading(searchBtn, DC_RECARGAS_DATA.texts.loading || 'Cargando...', true);
        setFeedback('Buscando paquetes disponibles...', 'info');
        resultEl.hidden = true;

        try {
            var productsRes = await fetchJson(
                '/products?account_number=' + encodeURIComponent(fullPhone) + '&country_iso=' + encodeURIComponent(country.iso)
            );
            setBundles(productsRes.result || []);

            if (!productsRes.ok) {
                setFeedback('DingConnect no respondió. Se mostraron bundles curados guardados en admin.', 'warning');
            } else {
                setFeedback('Paquetes actualizados desde DingConnect.', 'success');
            }
            state.lastSearchKey = searchKey;
        } catch (error) {
            setBundles([]);
            setFeedback(error.message || 'No se pudo consultar DingConnect.', 'error');
        } finally {
            state.inFlightSearchKey = '';
            setLoading(searchBtn, '', false);
        }
    }

    function showConfirmation(selected) {
        var country = getSelectedCountry();
        var countryOption = countryEl.options[countryEl.selectedIndex];
        var countryName = countryOption ? countryOption.textContent : country.iso;
        var phone = phoneEl.value || '';
        var msg = '¿Confirmas esta recarga?\n\n'
            + 'País: ' + countryName + '\n'
            + 'Número: ' + phone + '\n'
            + 'Paquete: ' + (selected.DefaultDisplayText || selected.SkuCode) + '\n'
            + 'Operador: ' + (selected.ProviderName || 'N/A') + '\n'
            + 'Precio: ' + selected.SendCurrencyIso + ' ' + Number(selected.SendValue || 0).toFixed(2);

        return confirm(msg);
    }

    function showFriendlyResult(result) {
        var items = result.Items || result.Result || [];
        var item = items[0] || {};

        var html = '<div class="dc-result-card">'
            + '<div class="dc-result-header">'
            + '<span class="dc-result-icon">' + (item.Status === 'Approved' ? '✅' : '⏳') + '</span>'
            + '<strong>' + (item.Status === 'Approved' ? 'Recarga procesada' : 'Recarga en validación') + '</strong>'
            + '</div>'
            + '<div class="dc-result-body">'
            + '<div class="dc-result-row"><span>Referencia</span><span>' + (result.TransferRef || result.DistributorRef || 'N/A') + '</span></div>'
            + '<div class="dc-result-row"><span>Estado</span><span>' + (item.Status || 'Pendiente') + '</span></div>'
            + '<div class="dc-result-row"><span>Número</span><span>' + (item.AccountNumber || 'N/A') + '</span></div>';

        if (item.ReceiveValue) {
            html += '<div class="dc-result-row"><span>Recibe</span><span>' + item.ReceiveCurrencyIso + ' ' + Number(item.ReceiveValue).toFixed(2) + '</span></div>';
        }

        html += '</div></div>';

        resultEl.innerHTML = html;
        resultEl.hidden = false;
    }

    async function addToCart(selected) {
        var country = getSelectedCountry();

        var payload = {
            account_number: state.fullPhone,
            country_iso: country.iso,
            sku_code: selected.SkuCode,
            send_value: Number(selected.SendValue || 0),
            send_currency_iso: selected.SendCurrencyIso || 'EUR',
            provider_name: selected.ProviderName || '',
            bundle_label: selected.DefaultDisplayText || selected.SkuCode,
        };

        setLoading(transferBtn, 'Añadiendo al carrito...', true);
        setFeedback('Preparando tu recarga...', 'info');

        try {
            var res = await fetchJson('/add-to-cart', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            if (res.ok && res.redirect) {
                setFeedback('Redirigiendo al checkout...', 'success');
                window.location.href = res.redirect;
            } else {
                setFeedback(res.message || 'Error al añadir al carrito.', 'error');
            }
        } catch (error) {
            setFeedback(error.message || 'No se pudo añadir al carrito.', 'error');
        } finally {
            setLoading(transferBtn, '', false);
        }
    }

    async function processDirectTransfer(selected) {
        var payload = {
            account_number: state.fullPhone,
            sku_code: selected.SkuCode,
            send_value: Number(selected.SendValue || 0),
            send_currency_iso: selected.SendCurrencyIso || 'USD',
        };

        setLoading(transferBtn, 'Procesando...', true);
        setFeedback('Enviando operación a DingConnect...', 'info');

        try {
            var transferRes = await fetchJson('/transfer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            setFeedback('Operación enviada correctamente.', 'success');
            showFriendlyResult(transferRes.result || transferRes);
        } catch (error) {
            setFeedback(error.message || 'No se pudo procesar la recarga.', 'error');
        } finally {
            setLoading(transferBtn, '', false);
        }
    }

    async function processTransfer() {
        var sku = bundleEl.value;
        if (!sku) {
            setFeedback('Selecciona un bundle antes de continuar.', 'error');
            return;
        }

        var selected = state.bundles.find(function (item) {
            return item.SkuCode === sku;
        });

        if (!selected) {
            setFeedback('No se encontró el bundle seleccionado.', 'error');
            return;
        }

        state.selected = selected;

        // Confirmation dialog
        if (!showConfirmation(selected)) {
            return;
        }

        // WooCommerce mode: add to cart and redirect
        if (DC_RECARGAS_DATA.woocommerce_active) {
            await addToCart(selected);
            return;
        }

        // Direct transfer mode (no WooCommerce)
        await processDirectTransfer(selected);
    }

    bundleEl.addEventListener('change', function () {
        transferBtn.disabled = !bundleEl.value;
    });

    function scheduleAutoSearch() {
        if (autoSearchTimer) {
            clearTimeout(autoSearchTimer);
        }

        autoSearchTimer = setTimeout(function () {
            searchBundles({ silentValidation: true });
        }, 350);
    }

    searchBtn.addEventListener('click', function () {
        searchBundles({ force: true });
    });
    phoneEl.addEventListener('input', scheduleAutoSearch);
    countryEl.addEventListener('change', scheduleAutoSearch);
    transferBtn.addEventListener('click', processTransfer);

    // Update button text based on mode
    if (DC_RECARGAS_DATA.woocommerce_active) {
        transferBtn.textContent = 'Añadir al carrito';
        transferBtn.dataset.defaultText = 'Añadir al carrito';
    }

    fillCountries();
    clearBundles('Ingresa un número para consultar paquetes');
})();
