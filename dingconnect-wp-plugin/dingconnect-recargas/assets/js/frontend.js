(function () {
    const app = document.getElementById('dc-recargas-app');
    if (!app || typeof DC_RECARGAS_DATA === 'undefined') {
        return;
    }

    const countryEl = document.getElementById('dc-country');
    const phoneEl = document.getElementById('dc-phone');
    const bundleEl = document.getElementById('dc-bundle');
    const searchBtn = document.getElementById('dc-search');
    const transferBtn = document.getElementById('dc-transfer');
    const feedbackEl = document.getElementById('dc-feedback');
    const resultEl = document.getElementById('dc-result');

    const state = {
        bundles: [],
        selected: null,
        fullPhone: '',
        mode: 'idle',
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
        const countries = Array.isArray(DC_RECARGAS_DATA.countries) ? DC_RECARGAS_DATA.countries : [];
        countryEl.innerHTML = '';
        countries.forEach(function (country) {
            const opt = document.createElement('option');
            opt.value = country.iso;
            opt.textContent = '+' + country.dial + ' ' + country.name;
            opt.dataset.dial = country.dial;
            countryEl.appendChild(opt);
        });
    }

    function getSelectedCountry() {
        const option = countryEl.options[countryEl.selectedIndex];
        return {
            iso: option ? option.value : '',
            dial: option ? option.dataset.dial : '',
        };
    }

    function normalizePhone() {
        const country = getSelectedCountry();
        const local = String(phoneEl.value || '').replace(/\D/g, '');
        if (!local || local.length < 5) {
            return '';
        }
        return '+' + country.dial + local;
    }

    function setBundles(items) {
        state.bundles = Array.isArray(items) ? items : [];
        bundleEl.innerHTML = '';

        if (!state.bundles.length) {
            const none = document.createElement('option');
            none.value = '';
            none.textContent = 'No hay bundles para este número';
            bundleEl.appendChild(none);
            bundleEl.disabled = true;
            transferBtn.disabled = true;
            state.selected = null;
            return;
        }

        const first = document.createElement('option');
        first.value = '';
        first.textContent = 'Selecciona un bundle';
        bundleEl.appendChild(first);

        state.bundles.forEach(function (bundle) {
            const opt = document.createElement('option');
            opt.value = bundle.SkuCode;
            opt.textContent = [
                bundle.ProviderName || 'Operador',
                bundle.DefaultDisplayText || bundle.SkuCode,
                bundle.SendCurrencyIso + ' ' + Number(bundle.SendValue || 0).toFixed(2),
            ].join(' - ');
            bundleEl.appendChild(opt);
        });

        bundleEl.disabled = false;
    }

    async function fetchJson(path, options) {
        const url = DC_RECARGAS_DATA.restBase.replace(/\/$/, '') + path;
        const response = await fetch(url, options || {});
        const data = await response.json();
        if (!response.ok) {
            const message = (data && data.message) || 'Error en la solicitud.';
            throw new Error(message);
        }
        return data;
    }

    async function searchBundles() {
        const fullPhone = normalizePhone();
        if (!fullPhone) {
            setFeedback('Ingresa un número móvil válido.', 'error');
            return;
        }

        const country = getSelectedCountry();
        state.fullPhone = fullPhone;

        setLoading(searchBtn, DC_RECARGAS_DATA.texts.loading || 'Cargando...', true);
        setFeedback('Buscando paquetes disponibles...', 'info');
        resultEl.hidden = true;

        try {
            const productsRes = await fetchJson(
                '/products?account_number=' + encodeURIComponent(fullPhone) + '&country_iso=' + encodeURIComponent(country.iso)
            );
            setBundles(productsRes.result || []);

            if (!productsRes.ok) {
                setFeedback('DingConnect no respondió. Se mostraron bundles curados guardados en admin.', 'warning');
            } else {
                setFeedback('Paquetes actualizados desde DingConnect.', 'success');
            }
        } catch (error) {
            setBundles([]);
            setFeedback(error.message || 'No se pudo consultar DingConnect.', 'error');
        } finally {
            setLoading(searchBtn, '', false);
        }
    }

    async function processTransfer() {
        const sku = bundleEl.value;
        if (!sku) {
            setFeedback('Selecciona un bundle antes de continuar.', 'error');
            return;
        }

        const selected = state.bundles.find(function (item) {
            return item.SkuCode === sku;
        });

        if (!selected) {
            setFeedback('No se encontró el bundle seleccionado.', 'error');
            return;
        }

        state.selected = selected;

        const payload = {
            account_number: state.fullPhone,
            sku_code: selected.SkuCode,
            send_value: Number(selected.SendValue || 0),
            send_currency_iso: selected.SendCurrencyIso || 'USD',
        };

        setLoading(transferBtn, 'Procesando...', true);
        setFeedback('Enviando operación a DingConnect...', 'info');

        try {
            const transferRes = await fetchJson('/transfer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });

            setFeedback('Operación enviada correctamente. Revisa la respuesta para validar estado.', 'success');
            resultEl.hidden = false;
            resultEl.textContent = JSON.stringify(transferRes.result || transferRes, null, 2);
        } catch (error) {
            setFeedback(error.message || 'No se pudo procesar la recarga.', 'error');
        } finally {
            setLoading(transferBtn, '', false);
        }
    }

    bundleEl.addEventListener('change', function () {
        transferBtn.disabled = !bundleEl.value;
    });

    searchBtn.addEventListener('click', searchBundles);
    transferBtn.addEventListener('click', processTransfer);

    fillCountries();
})();
