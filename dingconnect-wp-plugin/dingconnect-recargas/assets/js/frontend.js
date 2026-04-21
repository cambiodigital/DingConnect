(function () {
    var app = document.getElementById('dc-recargas-app');
    if (!app || typeof DC_RECARGAS_DATA === 'undefined') return;

    function findInApp(id) {
        return app.querySelector('#' + id);
    }

    /* -- DOM refs -- */
    var countryBtn       = findInApp('dc-country-btn');
    var countryFlag      = findInApp('dc-country-flag');
    var countryDial      = findInApp('dc-country-dial');
    var phoneEl          = findInApp('dc-phone');
    var overlay          = findInApp('dc-country-overlay');
    var countrySearch    = findInApp('dc-country-search');
    var countryList      = findInApp('dc-country-list');
    var countryClose     = findInApp('dc-country-close');
    var loadingEl        = findInApp('dc-loading');
    var activeStepEl     = findInApp('dc-active-step');
    var activeBundleSel  = findInApp('dc-active-bundle-select');
    var activeBundleInfo = findInApp('dc-active-bundle-info');
    var changeBundleBtn  = findInApp('dc-change-bundle-btn');
    var providerFilter   = findInApp('dc-provider-filter');
    var providerButtons  = findInApp('dc-provider-buttons');
    var bundlesEl        = findInApp('dc-bundles');
    var confirmEl        = findInApp('dc-confirm');
    var confirmSummary   = findInApp('dc-confirm-summary');
    var confirmBtn       = findInApp('dc-confirm-btn');
    var feedbackEl       = findInApp('dc-feedback');
    var resultEl         = findInApp('dc-result');

    var requiredNodes = [
        countryBtn,
        countryFlag,
        countryDial,
        phoneEl,
        overlay,
        countrySearch,
        countryList,
        countryClose,
        loadingEl,
        activeStepEl,
        activeBundleSel,
        activeBundleInfo,
        changeBundleBtn,
        bundlesEl,
        confirmEl,
        confirmSummary,
        confirmBtn,
        feedbackEl,
        resultEl,
    ];
    if (requiredNodes.some(function (node) { return !node; })) {
        console.warn('DingConnect: markup incompleto en el shortcode.');
        return;
    }

    function parseJsonAttr(name) {
        var raw = String(app.getAttribute(name) || '');
        if (!raw) return null;

        try {
            return JSON.parse(raw);
        } catch (err) {
            return null;
        }
    }

    var appCountries = parseJsonAttr('data-available-countries');
    var allCountries = Array.isArray(appCountries) && appCountries.length
        ? appCountries
        : (Array.isArray(DC_RECARGAS_DATA.countries) ? DC_RECARGAS_DATA.countries : []);
    var defaultCountryIso = String(app.getAttribute('data-default-country-iso') || '').toUpperCase();
    var allowedBundleIds = String(app.getAttribute('data-allowed-bundle-ids') || '')
        .split(',')
        .map(function (id) { return id.trim(); })
        .filter(function (id) { return !!id; });
    var allowedBundleMap = {};
    allowedBundleIds.forEach(function (id) {
        allowedBundleMap[id] = true;
    });
    var autoTimer = null;
    var SEARCH_CACHE_TTL_MS = 10000;

    var state = {
        country: null,
        bundles: [],
        filteredBundles: [],
        selectedProvider: '',
        selected: null,
        fullPhone: '',
        lastSearchKey: '',
        lastSearchAt: 0,
        inFlightSearchKey: '',
        dataSource: '', // 'saved' | 'dingconnect' | 'fallback'
        allowedBundleMap: allowedBundleMap,
    };

    /* -- Helpers -- */
    function isoToFlag(iso) {
        if (!iso || iso.length !== 2) return '';
        var a = iso.toUpperCase().charCodeAt(0) - 65 + 0x1F1E6;
        var b = iso.toUpperCase().charCodeAt(1) - 65 + 0x1F1E6;
        return String.fromCodePoint(a, b);
    }

    function setFeedback(msg, type) {
        feedbackEl.className = 'dc-feedback' + (type ? ' ' + type : '');
        feedbackEl.textContent = msg || '';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function getProviderLabel(bundle) {
        return String((bundle && (bundle.ProviderName || bundle.ProviderCode)) || 'Operador');
    }

    function formatMoney(amount, currency) {
        return Number(amount || 0).toFixed(2) + ' ' + String(currency || 'USD');
    }

    function renderActiveBundleInfo(bundle) {
        if (!bundle) {
            activeBundleInfo.innerHTML = '';
            return;
        }

        var benefit = String(bundle.Description || bundle.DefaultDisplayText || 'Sin beneficios adicionales informados.');
        var providerLabel = getProviderLabel(bundle);
        var amount = formatMoney(bundle.SendValue, bundle.SendCurrencyIso);

        activeBundleInfo.innerHTML = '<div class="dc-active-field">'
            + '<span class="dc-active-field-label">Beneficios recibidos</span>'
            + '<span class="dc-active-field-value">' + escapeHtml(benefit) + '</span>'
            + '</div>'
            + '<div class="dc-active-field">'
            + '<span class="dc-active-field-label">Operador</span>'
            + '<span class="dc-active-field-value">' + escapeHtml(providerLabel) + '</span>'
            + '</div>'
            + '<div class="dc-active-field">'
            + '<span class="dc-active-field-label">Monto y moneda</span>'
            + '<span class="dc-active-field-value">' + escapeHtml(amount) + '</span>'
            + '</div>';
    }

    function setReviewMode(enabled) {
        if (enabled) {
            providerFilter.hidden = true;
            bundlesEl.hidden = true;
            activeStepEl.hidden = false;
            confirmEl.hidden = false;
            return;
        }

        activeStepEl.hidden = true;
        confirmEl.hidden = true;
        showProviderStep();
    }

    function resetActiveStep() {
        state.selected = null;
        activeBundleSel.innerHTML = '';
        renderActiveBundleInfo(null);
        activeStepEl.hidden = true;
        confirmEl.hidden = true;
    }

    function syncActiveBundleSelect(selectedSku) {
        var bundles = state.filteredBundles || [];
        activeBundleSel.innerHTML = '';

        bundles.forEach(function (bundle) {
            var option = document.createElement('option');
            option.value = String(bundle.SkuCode || '');
            option.textContent = (bundle.DefaultDisplayText || bundle.SkuCode || 'Paquete')
                + ' · ' + formatMoney(bundle.SendValue, bundle.SendCurrencyIso);
            if (option.value === String(selectedSku || '')) {
                option.selected = true;
            }
            activeBundleSel.appendChild(option);
        });
    }

    /* -- Country picker -- */
    function selectCountry(c) {
        state.country = c;
        countryFlag.textContent = isoToFlag(c.iso);
        countryDial.textContent = '+' + c.dial;
        closeOverlay();
    }

    function openOverlay() {
        overlay.hidden = false;
        countrySearch.value = '';
        renderCountryList('');
        countrySearch.focus();
    }

    function closeOverlay() {
        overlay.hidden = true;
    }

    function renderCountryList(query) {
        var q = (query || '').toLowerCase();
        countryList.innerHTML = '';

        var matches = allCountries.filter(function (c) {
            if (!q) return true;
            return c.name.toLowerCase().indexOf(q) !== -1
                || String(c.dial || '').indexOf(q) !== -1
                || c.iso.toLowerCase().indexOf(q) !== -1;
        });

        if (!matches.length) {
            var noRes = document.createElement('div');
            noRes.className = 'dc-country-no-result';
            noRes.textContent = 'Sin resultados';
            countryList.appendChild(noRes);
            return;
        }

        matches.forEach(function (c) {
            var opt = document.createElement('div');
            opt.className = 'dc-country-option' + (state.country && state.country.iso === c.iso ? ' active' : '');
            opt.innerHTML = '<span class="dc-country-option-flag">' + isoToFlag(c.iso) + '</span>'
                + '<span class="dc-country-option-name">' + escapeHtml(c.name) + '</span>'
                + '<span class="dc-country-option-dial">' + (c.dial ? '+' + escapeHtml(c.dial) : escapeHtml(c.iso)) + '</span>';
            opt.addEventListener('click', function () {
                selectCountry(c);
                phoneEl.focus();
                scheduleAutoSearch();
            });
            countryList.appendChild(opt);
        });
    }

    countryBtn.addEventListener('click', openOverlay);
    countryClose.addEventListener('click', closeOverlay);
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeOverlay();
    });
    countrySearch.addEventListener('input', function () {
        renderCountryList(countrySearch.value);
    });

    /* -- Phone helpers -- */
    function normalizePhone() {
        if (!state.country) return '';
        var local = String(phoneEl.value || '').replace(/\D/g, '');
        if (!local || local.length < 5) return '';
        
        // Si el usuario ingresó el número completo con el código de país, quitarlo
        // Ej: usuario escribe "573001234567" pero debe ser solo "73001234567" para Cuba (53)
        var dial = String(state.country.dial);
        if (local.startsWith(dial)) {
            local = local.substring(dial.length);
        }
        
        return '+' + dial + local;
    }

    function scheduleAutoSearch() {
        if (autoTimer) clearTimeout(autoTimer);
        autoTimer = setTimeout(function () {
            searchBundles({ silent: true });
        }, 500);
    }

    phoneEl.addEventListener('input', scheduleAutoSearch);

    /* -- API -- */
    async function fetchJson(path, options) {
        var url = DC_RECARGAS_DATA.restBase.replace(/\/$/, '') + path;
        var opts = options || {};
        if (DC_RECARGAS_DATA.nonce) {
            opts.headers = Object.assign({}, opts.headers || {}, {
                'X-WP-Nonce': DC_RECARGAS_DATA.nonce,
            });
        }
        var response = await fetch(url, opts);
        var data = await response.json();
        if (!response.ok) {
            throw new Error((data && data.message) || 'Error en la solicitud.');
        }
        return data;
    }

    /* -- Search bundles -- */
    async function searchBundles(opts) {
        opts = opts || {};
        
        // Validar país antes de nada
        if (!state.country) {
            if (!opts.silent) setFeedback('Selecciona un país primero en el botón de la izquierda →', 'warning');
            hideBundles();
            return;
        }
        
        var fullPhone = normalizePhone();
        if (!fullPhone) {
            if (!opts.silent) setFeedback('Ingresa un número móvil válido.', 'error');
            hideBundles();
            return;
        }

        var searchKey = state.country.iso + '|' + fullPhone;
        var withinCacheWindow = (Date.now() - state.lastSearchAt) < SEARCH_CACHE_TTL_MS;
        if (!opts.force && state.lastSearchKey === searchKey && withinCacheWindow) return;
        if (!opts.force && state.inFlightSearchKey === searchKey) return;

        state.fullPhone = fullPhone;
        state.inFlightSearchKey = searchKey;
        resetActiveStep();
        resultEl.hidden = true;
        bundlesEl.hidden = true;
        loadingEl.hidden = false;
        setFeedback('', '');

        try {
            var allowedBundleParam = allowedBundleIds.length
                ? '&allowed_bundle_ids=' + encodeURIComponent(allowedBundleIds.join(','))
                : '';

            var res = await fetchJson(
                '/products?account_number=' + encodeURIComponent(fullPhone)
                + '&country_iso=' + encodeURIComponent(state.country.iso)
                + allowedBundleParam
            );
            var items = res.result || [];
            state.bundles = Array.isArray(items) ? items : [];

            if (Object.keys(state.allowedBundleMap).length > 0) {
                state.bundles = state.bundles.filter(function (bundle) {
                    var bundleId = String(bundle.BundleId || '').trim();
                    return bundleId && !!state.allowedBundleMap[bundleId];
                });
            }

            state.dataSource = res.source || 'unknown';
            state.selectedProvider = '';
            populateProviderFilter();
            showProviderStep();

            // Retroalimentación según fuente de datos
            if (!res.ok) {
                setFeedback('⚠️ Sin conexión a DingConnect. Mostrando catálogo guardado.', 'warning');
            } else if (res.source === 'saved') {
                setFeedback('📋 Paquetes cargados desde catálogo guardado.', 'info');
            } else if (res.source === 'dingconnect') {
                setFeedback('🌐 Paquetes cargados desde API DingConnect.', 'success');
            } else if (res.source === 'fallback') {
                setFeedback('⚠️ Usando catálogo de respaldo (DingConnect no respondió).', 'warning');
            }
            state.lastSearchKey = searchKey;
            state.lastSearchAt = Date.now();
        } catch (err) {
            state.bundles = [];
            hideBundles();
            setFeedback(err.message || 'No se pudo consultar DingConnect.', 'error');
        } finally {
            state.inFlightSearchKey = '';
            loadingEl.hidden = true;
        }
    }

    /* -- Provider step (mandatory) -- */
    function populateProviderFilter() {
        if (!providerButtons) return;

        var providers = [];
        var seen = {};
        state.bundles.forEach(function (b) {
            var name = getProviderLabel(b);
            if (name && !seen[name]) {
                seen[name] = true;
                providers.push(name);
            }
        });
        providers.sort();
        state._providers = providers;

        providerButtons.innerHTML = '';
        providers.forEach(function (p) {
            var count = state.bundles.filter(function (b) { return getProviderLabel(b) === p; }).length;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dc-provider-btn';
            btn.dataset.provider = p;
            btn.innerHTML = '<span class="dc-provider-btn-name">' + escapeHtml(p) + '</span>'
                + '<span class="dc-provider-btn-count">' + count + '</span>';
            btn.addEventListener('click', function () {
                selectProviderBtn(p);
            });
            providerButtons.appendChild(btn);
        });
    }

    function showProviderStep() {
        var providers = state._providers || [];
        if (!providerFilter || !providerButtons) {
            state.filteredBundles = state.bundles.slice();
            renderBundles();
            return;
        }

        if (providers.length === 0) {
            state.filteredBundles = [];
            bundlesEl.hidden = true;
            providerFilter.hidden = true;
            renderBundles();
            bundlesEl.hidden = false;
            return;
        }
        if (providers.length === 1) {
            // Only one provider -- auto-select and show bundles directly
            providerFilter.hidden = true;
            selectProviderBtn(providers[0]);
            return;
        }
        // Multiple providers -- show buttons, hide bundles until user picks
        providerFilter.hidden = false;
        bundlesEl.hidden = true;
        bundlesEl.innerHTML = '';
        confirmEl.hidden = true;
    }

    function selectProviderBtn(provider) {
        state.selectedProvider = provider;
        // Update active state on buttons
        if (providerButtons) {
            var btns = providerButtons.querySelectorAll('.dc-provider-btn');
            btns.forEach(function (b) {
                b.classList.toggle('active', b.dataset.provider === provider);
            });
        }
        state.filteredBundles = state.bundles.filter(function (b) {
            return getProviderLabel(b) === provider;
        });
        resetActiveStep();
        renderBundles();
    }

    /* -- Render bundles as cards -- */
    function renderBundles() {
        bundlesEl.innerHTML = '';
        if (!state.filteredBundles.length) {
            bundlesEl.innerHTML = '<div class="dc-empty-state"><p>No hay paquetes disponibles para este número.</p></div>';
            bundlesEl.hidden = false;
            return;
        }

        var label = document.createElement('div');
        label.className = 'dc-bundles-label';
        var sourceBadge = '';
        if (state.dataSource === 'saved') {
            sourceBadge = '<span class="dc-source-badge dc-source-saved">📋 Guardado</span>';
        } else if (state.dataSource === 'dingconnect') {
            sourceBadge = '<span class="dc-source-badge dc-source-dingconnect">🌐 DingConnect</span>';
        } else if (state.dataSource === 'fallback') {
            sourceBadge = '<span class="dc-source-badge dc-source-fallback">⚠️ Respaldo</span>';
        }
        label.innerHTML = 'Paquetes disponibles (' + state.filteredBundles.length + ') ' + sourceBadge;
        bundlesEl.appendChild(label);

        state.filteredBundles.forEach(function (bundle) {
            var card = document.createElement('div');
            card.className = 'dc-bundle-card';
            card.dataset.sku = String(bundle.SkuCode || '');
            var benefit = bundle.Description || '';
            var isUssd = benefit && /dial|\bUSSD\b|\*\d/i.test(benefit);
            var providerLabel = getProviderLabel(bundle);
            var benefitHtml = (benefit && !isUssd)
                ? '<div class="dc-bundle-benefit">' + escapeHtml(benefit.length > 80 ? benefit.substring(0, 80) + '…' : benefit) + '</div>'
                : '';
            card.innerHTML = '<div class="dc-bundle-info">'
                + '<div class="dc-bundle-operator">' + escapeHtml(providerLabel) + '</div>'
                + '<div class="dc-bundle-name">' + escapeHtml(bundle.DefaultDisplayText || bundle.SkuCode) + '</div>'
                + benefitHtml
                + '</div>'
                + '<div class="dc-bundle-price">'
                + '<div class="dc-bundle-amount">' + Number(bundle.SendValue || 0).toFixed(2) + '</div>'
                + '<div class="dc-bundle-currency">' + escapeHtml(bundle.SendCurrencyIso || 'USD') + '</div>'
                + '</div>';

            card.addEventListener('click', function () {
                selectBundle(bundle, card, { promoteStep: true });
            });
            bundlesEl.appendChild(card);
        });

        bundlesEl.hidden = false;
    }

    function hideBundles() {
        bundlesEl.innerHTML = '';
        bundlesEl.hidden = true;
        if (providerFilter) providerFilter.hidden = true;
        resetActiveStep();
    }

    /* -- Select bundle ? show confirm -- */
    function selectBundle(bundle, cardEl, opts) {
        opts = opts || {};
        state.selected = bundle;

        // Visual selection
        var cards = bundlesEl.querySelectorAll('.dc-bundle-card');
        cards.forEach(function (c) { c.classList.remove('selected'); });
        if (cardEl) {
            cardEl.classList.add('selected');
        } else {
            cards.forEach(function (c) {
                if (String(c.dataset.sku || '') === String(bundle.SkuCode || '')) {
                    c.classList.add('selected');
                }
            });
        }

        syncActiveBundleSelect(bundle.SkuCode || '');
        renderActiveBundleInfo(bundle);

        // Build summary
        var countryName = state.country ? state.country.name : '';
        var benefit = bundle.Description || '';
        var isUssd = benefit && /dial|\bUSSD\b|\*\d/i.test(benefit);
        var providerLabel = getProviderLabel(bundle);
        confirmSummary.innerHTML = '<strong>' + escapeHtml(bundle.DefaultDisplayText || bundle.SkuCode) + '</strong>'
            + escapeHtml(countryName) + ' · +' + escapeHtml(state.country.dial) + ' ' + escapeHtml(phoneEl.value)
            + ' · ' + escapeHtml(providerLabel)
            + '<br><strong>' + Number(bundle.SendValue || 0).toFixed(2) + ' ' + escapeHtml(bundle.SendCurrencyIso || 'USD') + '</strong>'
            + (benefit && !isUssd ? '<br><span class="dc-confirm-benefit">✓ ' + escapeHtml(benefit) + '</span>' : '');

        // Button text per mode
        if (DC_RECARGAS_DATA.woocommerce_active) {
            confirmBtn.textContent = 'Añadir al carrito';
        } else {
            confirmBtn.textContent = 'Confirmar recarga';
        }
        confirmBtn.disabled = false;

        if (opts.promoteStep !== false) {
            setReviewMode(true);
            activeStepEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    if (activeBundleSel) {
        activeBundleSel.addEventListener('change', function () {
            var selectedSku = String(activeBundleSel.value || '');
            var nextBundle = (state.filteredBundles || []).find(function (bundle) {
                return String(bundle.SkuCode || '') === selectedSku;
            });

            if (!nextBundle) return;
            selectBundle(nextBundle, null, { promoteStep: false });
        });
    }

    if (changeBundleBtn) {
        changeBundleBtn.addEventListener('click', function () {
            setReviewMode(false);
            bundlesEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    /* -- Confirm action -- */
    if (confirmBtn) {
        confirmBtn.addEventListener('click', async function () {
            if (!state.selected) return;
            confirmBtn.disabled = true;

            if (DC_RECARGAS_DATA.woocommerce_active) {
                await addToCart(state.selected);
            } else {
                await processDirectTransfer(state.selected);
            }
        });
    }

    /* -- WooCommerce: add to cart -- */
    async function addToCart(selected) {
        var payload = {
            account_number: state.fullPhone,
            country_iso: state.country.iso,
            sku_code: selected.SkuCode,
            send_value: Number(selected.SendValue || 0),
            send_currency_iso: selected.SendCurrencyIso || 'EUR',
            provider_name: getProviderLabel(selected),
            bundle_label: selected.DefaultDisplayText || selected.SkuCode,
        };

        confirmBtn.textContent = 'Añadiendo...';
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
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Añadir al carrito';
            }
        } catch (err) {
            setFeedback(err.message || 'No se pudo añadir al carrito.', 'error');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Añadir al carrito';
        }
    }

    /* -- Direct transfer -- */
    async function processDirectTransfer(selected) {
        var payload = {
            account_number: state.fullPhone,
            sku_code: selected.SkuCode,
            send_value: Number(selected.SendValue || 0),
            send_currency_iso: selected.SendCurrencyIso || 'USD',
        };

        confirmBtn.textContent = 'Procesando...';
        setFeedback('Enviando operación a DingConnect...', 'info');

        try {
            var transferRes = await fetchJson('/transfer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            setFeedback('Operación enviada correctamente.', 'success');
            confirmEl.hidden = true;
            showFriendlyResult(transferRes.result || transferRes);
        } catch (err) {
            setFeedback(err.message || 'No se pudo procesar la recarga.', 'error');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirmar recarga';
        }
    }

    /* -- Result display -- */
    function showFriendlyResult(result) {
        // DingConnect SendTransfer puede devolver TransferRecord (objeto) o Items/Result (array)
        var record = result.TransferRecord || {};
        var transferId = record.TransferId || {};
        var items = result.Items || result.Result || [];
        var item = items[0] || record;
        var transferRef = transferId.TransferRef || transferId.DistributorRef
            || result.TransferRef || result.DistributorRef || 'N/A';
        var status = item.Status || '';
        var html = '<div class="dc-result-card">'
            + '<div class="dc-result-header">'
            + '<span class="dc-result-icon">' + (status === 'Approved' ? '✓' : '⏳') + '</span>'
            + '<strong>' + (status === 'Approved' ? 'Recarga procesada' : 'Recarga en validación') + '</strong>'
            + '</div><div class="dc-result-body">'
            + '<div class="dc-result-row"><span>Referencia</span><span>' + escapeHtml(String(transferRef)) + '</span></div>'
            + '<div class="dc-result-row"><span>Estado</span><span>' + escapeHtml(String(status || item.ProcessingState || 'Pendiente')) + '</span></div>'
            + '<div class="dc-result-row"><span>Número</span><span>' + escapeHtml(String(item.AccountNumber || 'N/A')) + '</span></div>';
        if (item.ReceiveValue) {
            html += '<div class="dc-result-row"><span>Recibe</span><span>' + escapeHtml(String(item.ReceiveCurrencyIso || '') + ' ' + Number(item.ReceiveValue).toFixed(2)) + '</span></div>';
        }
        html += '</div></div>';
        resultEl.innerHTML = html;
        resultEl.hidden = false;
    }

    /* -- Init -- */
    var defaultCountry = allCountries.find(function (c) { return c.iso === 'CU'; }) || allCountries[0];
    if (defaultCountryIso) {
        defaultCountry = allCountries.find(function (c) { return c.iso === defaultCountryIso; }) || defaultCountry;
    }
    if (defaultCountry) selectCountry(defaultCountry);
})();
