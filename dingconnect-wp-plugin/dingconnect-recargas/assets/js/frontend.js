(function () {
    if (typeof DC_RECARGAS_DATA === 'undefined') return;

    function initApp(app) {

    function findInApp(id) {
        return app.querySelector('#' + id);
    }

    /* -- DOM refs -- */
    var countryBtn        = findInApp('dc-country-btn');
    var countryFlag       = findInApp('dc-country-flag');
    var countryDial       = findInApp('dc-country-dial');
    var phoneEl           = findInApp('dc-phone');
    var overlay           = findInApp('dc-country-overlay');
    var countrySearch     = findInApp('dc-country-search');
    var countryList       = findInApp('dc-country-list');
    var countryClose      = findInApp('dc-country-close');
    var loadingEl         = findInApp('dc-loading');
    var feedbackEl        = findInApp('dc-feedback');
    var providerFilter    = findInApp('dc-provider-filter');
    var providerButtons   = findInApp('dc-provider-buttons');
    var bundlesEl         = findInApp('dc-bundles');
    var confirmCard       = findInApp('dc-confirm-card');
    var feedbackConfirmEl = findInApp('dc-feedback-confirm');
    var confirmBtn        = findInApp('dc-confirm-btn');
    var resultEl          = findInApp('dc-result');

    /* Wizard panes & navigation */
    var panePhone         = findInApp('dc-pane-phone');
    var paneBundle        = findInApp('dc-pane-bundle');
    var paneConfirm       = findInApp('dc-pane-confirm');
    var paneResult        = findInApp('dc-pane-result');
    var stepperEl         = findInApp('dc-stepper');
    var contextPhone      = findInApp('dc-context-phone');
    var contextBundle     = findInApp('dc-context-bundle');
    var btnBackBundle     = findInApp('dc-btn-back-bundle');
    var btnBackConfirm    = findInApp('dc-btn-back-confirm');
    var btnRestart        = findInApp('dc-btn-restart');

    var requiredNodes = [
        countryBtn, countryFlag, countryDial, phoneEl,
        overlay, countrySearch, countryList, countryClose,
        loadingEl, feedbackEl,
        providerFilter, providerButtons, bundlesEl,
        confirmCard, feedbackConfirmEl, confirmBtn, resultEl,
        panePhone, paneBundle, paneConfirm, paneResult,
        stepperEl, btnBackBundle, btnBackConfirm, btnRestart,
    ];

    if (requiredNodes.some(function (n) { return !n; })) {
        console.warn('DingConnect: markup incompleto.');
        return;
    }

    function parseJsonAttr(name) {
        var raw = String(app.getAttribute(name) || '');
        if (!raw) return null;
        try { return JSON.parse(raw); } catch (e) { return null; }
    }

    var appCountries = parseJsonAttr('data-available-countries');
    var allCountries = Array.isArray(appCountries) && appCountries.length
        ? appCountries
        : (Array.isArray(DC_RECARGAS_DATA.countries) ? DC_RECARGAS_DATA.countries : []);
    var defaultCountryIso = String(app.getAttribute('data-default-country-iso') || '').toUpperCase();
    var allowedBundleIds = String(app.getAttribute('data-allowed-bundle-ids') || '')
        .split(',').map(function (id) { return id.trim(); }).filter(Boolean);
    var allowedBundleMap = {};
    allowedBundleIds.forEach(function (id) { allowedBundleMap[id] = true; });

    var autoTimer = null;
    var SEARCH_CACHE_TTL_MS = 10000;

    /* ===== State ===== */
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
        dataSource: '',
        allowedBundleMap: allowedBundleMap,
        wizardStep: 'phone',  // phone | bundle | confirm | result
    };

    /* ===== Wizard navigation ===== */
    var paneMap = {
        phone: panePhone,
        bundle: paneBundle,
        confirm: paneConfirm,
        result: paneResult,
    };

    var stepNumbers = { phone: 1, bundle: 2, confirm: 3 };

    function goToStep(newStep, direction) {
        var currentPane = paneMap[state.wizardStep];
        var nextPane = paneMap[newStep];
        if (!nextPane || newStep === state.wizardStep) return;

        // Remove animation classes from next pane in case they're stuck
        nextPane.classList.remove('is-entering-fwd', 'is-entering-back');

        // Hide current pane
        if (currentPane) currentPane.hidden = true;

        // Show next pane with animation
        nextPane.hidden = false;
        // Force reflow so animation triggers
        void nextPane.offsetWidth;
        var animClass = direction === 'back' ? 'is-entering-back' : 'is-entering-fwd';
        nextPane.classList.add(animClass);
        nextPane.addEventListener('animationend', function handler() {
            nextPane.removeEventListener('animationend', handler);
            nextPane.classList.remove(animClass);
        });

        state.wizardStep = newStep;
        updateStepper(newStep);

        // Scroll top of card into view smoothly
        app.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function updateStepper(step) {
        var currentNum = stepNumbers[step] || null;

        // Show/hide stepper on result step
        stepperEl.hidden = (step === 'result');

        // Update each step dot
        var stepEls = stepperEl.querySelectorAll('.dc-step');
        stepEls.forEach(function (el) {
            var n = parseInt(el.getAttribute('data-step'), 10);
            el.classList.remove('is-active', 'is-done');
            if (currentNum === null) return;
            if (n < currentNum) el.classList.add('is-done');
            else if (n === currentNum) el.classList.add('is-active');
        });

        // Update bars
        var bars = stepperEl.querySelectorAll('.dc-step-bar');
        bars.forEach(function (bar) {
            var barIdx = parseInt(bar.getAttribute('data-bar'), 10);
            bar.classList.toggle('is-done', currentNum !== null && barIdx < currentNum);
        });
    }

    /* ===== Helpers ===== */
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

    function setFeedbackConfirm(msg, type) {
        feedbackConfirmEl.className = 'dc-feedback' + (type ? ' ' + type : '');
        feedbackConfirmEl.textContent = msg || '';
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

    /* ===== Country picker ===== */
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

    /* ===== Phone helpers ===== */
    function normalizePhone() {
        if (!state.country) return '';
        var local = String(phoneEl.value || '').replace(/\D/g, '');
        if (!local || local.length < 5) return '';
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

    /* ===== API ===== */
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
        if (!response.ok) throw new Error((data && data.message) || 'Error en la solicitud.');
        return data;
    }

    /* ===== Search bundles ===== */
    async function searchBundles(opts) {
        opts = opts || {};

        if (!state.country) {
            if (!opts.silent) setFeedback('Selecciona un país primero.', 'warning');
            return;
        }

        var fullPhone = normalizePhone();
        if (!fullPhone) {
            if (!opts.silent) setFeedback('Ingresa un número móvil válido.', 'error');
            return;
        }

        var searchKey = state.country.iso + '|' + fullPhone;
        var withinCache = (Date.now() - state.lastSearchAt) < SEARCH_CACHE_TTL_MS;
        if (!opts.force && state.lastSearchKey === searchKey && withinCache) {
            // Already have results — advance directly if on phone step
            if (state.wizardStep === 'phone' && state.bundles.length > 0) {
                advanceToBundle();
            }
            return;
        }
        if (!opts.force && state.inFlightSearchKey === searchKey) return;

        state.fullPhone = fullPhone;
        state.inFlightSearchKey = searchKey;
        state.selected = null;
        resultEl.innerHTML = '';
        loadingEl.hidden = false;
        setFeedback('', '');

        try {
            var allowedParam = allowedBundleIds.length
                ? '&allowed_bundle_ids=' + encodeURIComponent(allowedBundleIds.join(','))
                : '';

            var res = await fetchJson(
                '/products?account_number=' + encodeURIComponent(fullPhone)
                + '&country_iso=' + encodeURIComponent(state.country.iso)
                + allowedParam
            );

            var items = res.result || [];
            state.bundles = Array.isArray(items) ? items : [];

            if (Object.keys(state.allowedBundleMap).length > 0) {
                state.bundles = state.bundles.filter(function (b) {
                    var id = String(b.BundleId || '').trim();
                    return id && !!state.allowedBundleMap[id];
                });
            }

            state.dataSource = res.source || 'unknown';
            state.selectedProvider = '';
            state.lastSearchKey = searchKey;
            state.lastSearchAt = Date.now();

            if (!res.ok) {
                setFeedback('⚠️ Sin conexión a DingConnect. Mostrando catálogo guardado.', 'warning');
            }

            populateProviderFilter();
            advanceToBundle();

        } catch (err) {
            state.bundles = [];
            setFeedback(err.message || 'No se pudo consultar DingConnect.', 'error');
        } finally {
            state.inFlightSearchKey = '';
            loadingEl.hidden = true;
        }
    }

    /* ===== Advance to bundle step ===== */
    function advanceToBundle() {
        if (state.bundles.length === 0) {
            setFeedback('No hay paquetes disponibles para este número.', 'warning');
            return;
        }

        // Update context strip in bundle pane
        if (contextPhone) {
            var flag = state.country ? isoToFlag(state.country.iso) : '';
            var dial = state.country ? '+' + state.country.dial : '';
            contextPhone.innerHTML = '<span class="dc-ctx-flag">' + flag + '</span>'
                + '<strong>' + escapeHtml(dial + ' ' + (phoneEl.value || '')) + '</strong>'
                + '&nbsp;· ' + escapeHtml(state.country ? state.country.name : '');
        }

        // Reset bundle pane state
        bundlesEl.innerHTML = '';
        bundlesEl.hidden = true;
        providerFilter.hidden = true;

        goToStep('bundle', 'forward');

        // Small delay so transition finishes before populating
        setTimeout(function () {
            showProviderStep();
        }, 50);
    }

    /* ===== Provider step ===== */
    function populateProviderFilter() {
        if (!providerButtons) return;
        var providers = [];
        var seen = {};
        state.bundles.forEach(function (b) {
            var name = getProviderLabel(b);
            if (name && !seen[name]) { seen[name] = true; providers.push(name); }
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
            btn.addEventListener('click', function () { selectProviderBtn(p); });
            providerButtons.appendChild(btn);
        });
    }

    function showProviderStep() {
        var providers = state._providers || [];
        if (providers.length === 0) {
            state.filteredBundles = state.bundles.slice();
            renderBundles();
            return;
        }
        if (providers.length === 1) {
            providerFilter.hidden = true;
            selectProviderBtn(providers[0]);
            return;
        }
        // Multiple providers — show picker first, bundles after
        providerFilter.hidden = false;
        bundlesEl.hidden = true;
    }

    function selectProviderBtn(provider) {
        state.selectedProvider = provider;
        if (providerButtons) {
            providerButtons.querySelectorAll('.dc-provider-btn').forEach(function (b) {
                b.classList.toggle('active', b.dataset.provider === provider);
            });
        }
        state.filteredBundles = state.bundles.filter(function (b) { return getProviderLabel(b) === provider; });
        state.selected = null;
        renderBundles();
    }

    /* ===== Render bundles ===== */
    function renderBundles() {
        bundlesEl.innerHTML = '';
        if (!state.filteredBundles.length) {
            bundlesEl.innerHTML = '<div class="dc-empty-state"><p>No hay paquetes disponibles para este número.</p></div>';
            bundlesEl.hidden = false;
            return;
        }

        var label = document.createElement('div');
        label.className = 'dc-bundles-label';
        var badge = '';
        if (state.dataSource === 'saved') badge = '<span class="dc-source-badge dc-source-saved">📋 Guardado</span>';
        else if (state.dataSource === 'dingconnect') badge = '<span class="dc-source-badge dc-source-dingconnect">🌐 En vivo</span>';
        else if (state.dataSource === 'fallback') badge = '<span class="dc-source-badge dc-source-fallback">⚠️ Respaldo</span>';
        label.innerHTML = state.filteredBundles.length + ' paquetes disponibles ' + badge;
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

            card.addEventListener('click', function () { selectBundle(bundle, card); });
            bundlesEl.appendChild(card);
        });

        bundlesEl.hidden = false;
    }

    /* ===== Select bundle → Confirm step ===== */
    function selectBundle(bundle, cardEl) {
        state.selected = bundle;

        // Visual selection
        bundlesEl.querySelectorAll('.dc-bundle-card').forEach(function (c) { c.classList.remove('selected'); });
        if (cardEl) cardEl.classList.add('selected');

        // Build context strip for confirm pane
        var providerLabel = getProviderLabel(bundle);
        var benefit = bundle.Description || '';
        var isUssd = benefit && /dial|\bUSSD\b|\*\d/i.test(benefit);

        if (contextBundle) {
            var flag = state.country ? isoToFlag(state.country.iso) : '';
            contextBundle.innerHTML = '<span class="dc-ctx-flag">' + flag + '</span>'
                + '<strong>' + escapeHtml(bundle.DefaultDisplayText || bundle.SkuCode) + '</strong>'
                + '&nbsp;· ' + escapeHtml(providerLabel);
        }

        // Build confirm card
        var countryName = state.country ? state.country.name : '';
        var dial = state.country ? '+' + state.country.dial : '';
        var phone = phoneEl.value || '';
        var price = Number(bundle.SendValue || 0).toFixed(2) + ' ' + escapeHtml(bundle.SendCurrencyIso || 'USD');

        confirmCard.innerHTML = ''
            + '<div class="dc-confirm-row">'
            +   '<span class="dc-confirm-row-label">Número</span>'
            +   '<span class="dc-confirm-row-value">' + escapeHtml(dial + ' ' + phone) + '<br><small style="font-weight:400;color:#64748b">' + escapeHtml(countryName) + '</small></span>'
            + '</div>'
            + '<div class="dc-confirm-row">'
            +   '<span class="dc-confirm-row-label">Paquete</span>'
            +   '<span class="dc-confirm-row-value">' + escapeHtml(bundle.DefaultDisplayText || bundle.SkuCode) + '</span>'
            + '</div>'
            + '<div class="dc-confirm-row">'
            +   '<span class="dc-confirm-row-label">Operador</span>'
            +   '<span class="dc-confirm-row-value">' + escapeHtml(providerLabel) + '</span>'
            + '</div>'
            + (benefit && !isUssd
                ? '<div class="dc-confirm-row"><span class="dc-confirm-row-label">Beneficios</span><span class="dc-confirm-row-value is-benefit">' + escapeHtml(benefit) + '</span></div>'
                : '')
            + '<div class="dc-confirm-row">'
            +   '<span class="dc-confirm-row-label">Costo</span>'
            +   '<span class="dc-confirm-row-value is-price">' + price + '</span>'
            + '</div>';

        // Update confirm button text
        if (DC_RECARGAS_DATA.woocommerce_active) {
            confirmBtn.textContent = 'Añadir al carrito';
        } else {
            confirmBtn.textContent = 'Confirmar recarga';
        }
        confirmBtn.disabled = false;
        setFeedbackConfirm('', '');

        goToStep('confirm', 'forward');
    }

    /* ===== Back navigation ===== */
    btnBackBundle.addEventListener('click', function () {
        goToStep('phone', 'back');
    });

    btnBackConfirm.addEventListener('click', function () {
        goToStep('bundle', 'back');
    });

    btnRestart.addEventListener('click', function () {
        // Reset state
        state.selected = null;
        state.bundles = [];
        state.filteredBundles = [];
        state.selectedProvider = '';
        state.fullPhone = '';
        state.lastSearchKey = '';
        state.lastSearchAt = 0;
        // Clear panes
        bundlesEl.innerHTML = '';
        bundlesEl.hidden = true;
        providerFilter.hidden = true;
        confirmCard.innerHTML = '';
        resultEl.innerHTML = '';
        setFeedback('', '');
        setFeedbackConfirm('', '');
        goToStep('phone', 'back');
        phoneEl.focus();
    });

    /* ===== Confirm action ===== */
    confirmBtn.addEventListener('click', async function () {
        if (!state.selected) return;
        confirmBtn.disabled = true;

        if (DC_RECARGAS_DATA.woocommerce_active) {
            await addToCart(state.selected);
        } else {
            await processDirectTransfer(state.selected);
        }
    });

    /* ===== WooCommerce: add to cart ===== */
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
        setFeedbackConfirm('Preparando tu recarga...', 'info');

        try {
            var res = await fetchJson('/add-to-cart', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (res.ok && res.redirect) {
                setFeedbackConfirm('Redirigiendo al checkout...', 'success');
                window.location.href = res.redirect;
            } else {
                setFeedbackConfirm(res.message || 'Error al añadir al carrito.', 'error');
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Añadir al carrito';
            }
        } catch (err) {
            setFeedbackConfirm(err.message || 'No se pudo añadir al carrito.', 'error');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Añadir al carrito';
        }
    }

    /* ===== Direct transfer ===== */
    async function processDirectTransfer(selected) {
        var payload = {
            account_number: state.fullPhone,
            sku_code: selected.SkuCode,
            send_value: Number(selected.SendValue || 0),
            send_currency_iso: selected.SendCurrencyIso || 'USD',
        };

        confirmBtn.textContent = 'Procesando...';
        setFeedbackConfirm('Enviando operación a DingConnect...', 'info');

        try {
            var transferRes = await fetchJson('/transfer', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            setFeedbackConfirm('', '');
            showFriendlyResult(transferRes.result || transferRes);
            goToStep('result', 'forward');
        } catch (err) {
            setFeedbackConfirm(err.message || 'No se pudo procesar la recarga.', 'error');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirmar recarga';
        }
    }

    /* ===== Result display ===== */
    function showFriendlyResult(result) {
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
            + '<div class="dc-result-row"><span>Número</span><span>' + escapeHtml(String(item.AccountNumber || state.fullPhone || 'N/A')) + '</span></div>';

        if (item.ReceiveValue) {
            html += '<div class="dc-result-row"><span>Recibe</span><span>' + escapeHtml(String(item.ReceiveCurrencyIso || '') + ' ' + Number(item.ReceiveValue).toFixed(2)) + '</span></div>';
        }
        html += '</div></div>';
        resultEl.innerHTML = html;
    }

    /* ===== Init ===== */
    var defaultCountry = allCountries.find(function (c) { return c.iso === 'CU'; }) || allCountries[0];
    if (defaultCountryIso) {
        defaultCountry = allCountries.find(function (c) { return c.iso === defaultCountryIso; }) || defaultCountry;
    }
    if (defaultCountry) selectCountry(defaultCountry);

    } // end initApp

    document.querySelectorAll('.dc-recargas-app').forEach(function (app) {
        initApp(app);
    });
})();
