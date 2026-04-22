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
    var packageStage      = findInApp('dc-package-stage');
    var productTypeFilter = findInApp('dc-product-type-filter');
    var packageSelect     = findInApp('dc-package-select');
    var packageCard       = findInApp('dc-package-card');
    var confirmCard       = findInApp('dc-confirm-card');
    var feedbackConfirmEl = findInApp('dc-feedback-confirm');
    var btnContinueConfirm = findInApp('dc-btn-continue-confirm');
    var confirmBtn        = findInApp('dc-confirm-btn');
    var resultEl          = findInApp('dc-result');

    /* Wizard panes & navigation */
    var panePhone         = findInApp('dc-pane-phone');
    var paneConfirm       = findInApp('dc-pane-confirm');
    var paneResult        = findInApp('dc-pane-result');
    var stepperEl         = findInApp('dc-stepper');
    var contextPhone      = findInApp('dc-context-phone');
    var contextBundle     = findInApp('dc-context-bundle');
    var btnBackConfirm    = findInApp('dc-btn-back-confirm');
    var btnRestart        = findInApp('dc-btn-restart');

    var requiredNodes = [
        countryBtn, countryFlag, countryDial, phoneEl,
        overlay, countrySearch, countryList, countryClose,
        loadingEl, feedbackEl,
        packageStage, packageSelect, packageCard,
        confirmCard, feedbackConfirmEl, btnContinueConfirm, confirmBtn, resultEl,
        panePhone, paneConfirm, paneResult,
        btnBackConfirm, btnRestart,
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
    var featuredBundleId = String(app.getAttribute('data-featured-bundle-id') || '').trim();
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
        selected: null,
        featuredBundleId: featuredBundleId,
        fullPhone: '',
        lastSearchKey: '',
        lastSearchAt: 0,
        inFlightSearchKey: '',
        dataSource: '',
        allowedBundleMap: allowedBundleMap,
        wizardStep: 'phone',  // phone | confirm | result
    };

    /* ===== Wizard navigation ===== */
    var paneMap = {
        phone: panePhone,
        confirm: paneConfirm,
        result: paneResult,
    };

    var stepNumbers = { phone: 1, confirm: 2 };

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
        if (!stepperEl) return;

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

    function resetPackageStage(clearBundles) {
        state.selected = null;
        if (clearBundles) {
            state.bundles = [];
        }
        packageSelect.innerHTML = '';
        packageCard.innerHTML = '';
        packageStage.hidden = true;
        confirmCard.innerHTML = '';
        setFeedbackConfirm('', '');
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function getProviderLabel(bundle) {
        return String((bundle && (bundle.ProviderName || bundle.ProviderCode)) || 'Operador');
    }

    function getBundleId(bundle) {
        return String((bundle && bundle.BundleId) || '').trim();
    }

    function isFeaturedBundle(bundle) {
        var featuredId = String(state.featuredBundleId || '').trim();
        if (!featuredId) return false;
        return getBundleId(bundle) === featuredId;
    }

    function formatMoney(amount, currency) {
        return Number(amount || 0).toFixed(2) + ' ' + String(currency || 'USD');
    }

    /* ===== Country picker ===== */
    function selectCountry(c) {
        state.country = c;
        countryFlag.textContent = isoToFlag(c.iso);
        countryDial.textContent = '+' + c.dial;
        resetPackageStage(true);
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

    phoneEl.addEventListener('input', function () {
        resetPackageStage(true);
        scheduleAutoSearch();
    });

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
        if (!opts.force && state.lastSearchKey === searchKey && withinCache && state.bundles.length > 0) {
            // Already have results — rehydrate package stage without another request
            if (state.wizardStep === 'phone' && state.bundles.length > 0) {
                showPackageStage();
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

                var bundleOrderMap = {};
                allowedBundleIds.forEach(function (bundleId, index) {
                    bundleOrderMap[String(bundleId)] = index;
                });

                state.bundles.sort(function (left, right) {
                    var leftId = getBundleId(left);
                    var rightId = getBundleId(right);
                    var leftOrder = Object.prototype.hasOwnProperty.call(bundleOrderMap, leftId) ? bundleOrderMap[leftId] : 99999;
                    var rightOrder = Object.prototype.hasOwnProperty.call(bundleOrderMap, rightId) ? bundleOrderMap[rightId] : 99999;
                    if (leftOrder !== rightOrder) return leftOrder - rightOrder;
                    return getBundleOptionLabel(left).localeCompare(getBundleOptionLabel(right));
                });
            }

            state.dataSource = res.source || 'unknown';
            state.lastSearchKey = searchKey;
            state.lastSearchAt = Date.now();

            if (!res.ok) {
                setFeedback('⚠️ Sin conexión a DingConnect. Mostrando catálogo guardado.', 'warning');
            }

            showPackageStage();

        } catch (err) {
            state.bundles = [];
            resetPackageStage(true);
            setFeedback(err.message || 'No se pudo consultar DingConnect.', 'error');
        } finally {
            state.inFlightSearchKey = '';
            loadingEl.hidden = true;
        }
    }

    /* ===== Package stage ===== */
    function showPackageStage() {
        if (state.bundles.length === 0) {
            resetPackageStage(false);
            setFeedback('No hay paquetes disponibles para este número.', 'warning');
            return;
        }

        if (contextPhone) {
            var flag = state.country ? isoToFlag(state.country.iso) : '';
            var dial = state.country ? '+' + state.country.dial : '';
            contextPhone.innerHTML = '<span class="dc-ctx-flag">' + flag + '</span>'
                + '<strong>' + escapeHtml(dial + ' ' + (phoneEl.value || '')) + '</strong>'
                + '&nbsp;· ' + escapeHtml(state.country ? state.country.name : '');
        }

        setFeedback('', '');
        renderProductTypeFilter();
        packageStage.hidden = false;
        populatePackageSelect();
    }

    function getBundleOptionLabel(bundle) {
        var parts = [];
        var title = String(bundle.DefaultDisplayText || bundle.SkuCode || 'Paquete disponible');
        var provider = getProviderLabel(bundle);
        var amount = formatMoney(bundle.SendValue || 0, bundle.SendCurrencyIso || 'USD');

        if (isFeaturedBundle(bundle)) {
            parts.push('⭐ Destacado');
        }
        parts.push(title);
        if (provider) parts.push(provider);
        parts.push(amount);

        return parts.join(' · ');
    }

    function populatePackageSelect(bundlesOverride) {
        var bundles = Array.isArray(bundlesOverride) ? bundlesOverride : state.bundles;
        packageSelect.innerHTML = '';

        if (!bundles.length) {
            resetPackageStage(false);
            return;
        }

        bundles.forEach(function (bundle, index) {
            var option = document.createElement('option');
            option.value = String(index);
            option.textContent = getBundleOptionLabel(bundle);
            packageSelect.appendChild(option);
        });

        if (!state.selected || bundles.indexOf(state.selected) === -1) {
            var featured = bundles.find(function (bundle) { return isFeaturedBundle(bundle); });
            state.selected = featured || bundles[0];
        }

        packageSelect.value = String(bundles.indexOf(state.selected));
        renderPackageCard(state.selected);
        btnContinueConfirm.disabled = !state.selected;
    }

    function renderProductTypeFilter() {
        if (!productTypeFilter) return;

        var types = [];
        state.bundles.forEach(function (bundle) {
            var type = String(bundle.ProductType || '').trim();
            if (type !== '' && types.indexOf(type) === -1) {
                types.push(type);
            }
        });
        types.sort();

        if (types.length < 2) {
            productTypeFilter.hidden = true;
            productTypeFilter.innerHTML = '';
            return;
        }

        productTypeFilter.innerHTML = '';
        var allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = 'Todos los tipos';
        productTypeFilter.appendChild(allOption);

        types.forEach(function (type) {
            var option = document.createElement('option');
            option.value = type;
            option.textContent = type;
            productTypeFilter.appendChild(option);
        });

        productTypeFilter.hidden = false;

        productTypeFilter.onchange = function () {
            var selected = productTypeFilter.value;
            var filtered = selected === ''
                ? state.bundles
                : state.bundles.filter(function (bundle) {
                    return String(bundle.ProductType || '').trim() === selected;
                });
            state.selected = null;
            populatePackageSelect(filtered);
        };
    }

    function renderPackageCard(bundle) {
        if (!bundle) {
            packageCard.innerHTML = '';
            btnContinueConfirm.disabled = true;
            return;
        }

        var providerLabel = getProviderLabel(bundle);
        var benefit = String(bundle.Description || bundle.DefaultDisplayText || bundle.SkuCode || 'Paquete disponible');
        var countryIso = String(bundle.CountryIso || (state.country ? state.country.iso : '') || '').toUpperCase();
        var amount = formatMoney(bundle.SendValue || 0, bundle.SendCurrencyIso || 'USD');
        var featuredClass = isFeaturedBundle(bundle) ? ' is-featured' : '';
        var featuredBadge = isFeaturedBundle(bundle)
            ? '<span class="dc-featured-badge">⭐ Paquete destacado</span>'
            : '';

        packageCard.innerHTML = ''
            + '<div class="dc-package-card-head' + featuredClass + '">'
            +   '<div class="dc-package-copy">'
            +     '<div class="dc-package-copy-label">Beneficios recibidos</div>'
            +     featuredBadge
            +     '<div class="dc-package-copy-title">' + escapeHtml(bundle.DefaultDisplayText || bundle.SkuCode || 'Paquete') + '</div>'
            +     '<div class="dc-package-copy-description">' + escapeHtml(benefit) + '</div>'
            +   '</div>'
            +   '<div class="dc-package-price-block">'
            +     '<span class="dc-package-price-label">Monto</span>'
            +     '<strong>' + escapeHtml(amount) + '</strong>'
            +   '</div>'
            +   '<div class="dc-package-iso-chip">' + escapeHtml(countryIso || 'N/A') + '</div>'
            + '</div>'
            + '<div class="dc-package-card-meta">'
            +   '<span class="dc-package-meta-label">Operador</span>'
            +   '<span class="dc-package-meta-value">' + escapeHtml(providerLabel) + '</span>'
            + '</div>';

        btnContinueConfirm.disabled = false;
    }

    function buildConfirmStep(bundle) {
        var providerLabel = getProviderLabel(bundle);
        var benefit = String(bundle.Description || bundle.DefaultDisplayText || bundle.SkuCode || 'Paquete disponible');
        var countryName = state.country ? state.country.name : '';
        var countryIso = String(bundle.CountryIso || (state.country ? state.country.iso : '') || '').toUpperCase();
        var dial = state.country ? '+' + state.country.dial : '';
        var phone = phoneEl.value || '';
        var price = formatMoney(bundle.SendValue || 0, bundle.SendCurrencyIso || 'USD');
        var featuredClass = isFeaturedBundle(bundle) ? ' is-featured' : '';
        var featuredBadge = isFeaturedBundle(bundle)
            ? '<span class="dc-featured-badge">⭐ Paquete destacado</span>'
            : '';

        if (contextBundle) {
            var flag = state.country ? isoToFlag(state.country.iso) : '';
            contextBundle.innerHTML = '<span class="dc-ctx-flag">' + flag + '</span>'
                + '<strong>' + escapeHtml(dial + ' ' + phone) + '</strong>'
                + '&nbsp;· ' + escapeHtml(providerLabel);
        }

        confirmCard.innerHTML = ''
            + '<div class="dc-confirm-hero' + featuredClass + '">'
            +   '<div class="dc-confirm-hero-copy">'
            +     '<div class="dc-confirm-kicker">Beneficios recibidos</div>'
            +     featuredBadge
            +     '<div class="dc-confirm-title">' + escapeHtml(bundle.DefaultDisplayText || bundle.SkuCode || 'Paquete') + '</div>'
            +     '<div class="dc-confirm-benefit">' + escapeHtml(benefit) + '</div>'
            +   '</div>'
            +   '<div class="dc-confirm-hero-side">'
            +     '<span class="dc-confirm-iso">' + escapeHtml(countryIso || 'N/A') + '</span>'
            +     '<strong class="dc-confirm-amount">' + escapeHtml(price) + '</strong>'
            +   '</div>'
            + '</div>'
            + '<div class="dc-confirm-row">'
            +   '<span class="dc-confirm-row-label">Operador</span>'
            +   '<span class="dc-confirm-row-value">' + escapeHtml(providerLabel) + '</span>'
            + '</div>'
            + '<div class="dc-confirm-row">'
            +   '<span class="dc-confirm-row-label">Número</span>'
            +   '<span class="dc-confirm-row-value">' + escapeHtml(dial + ' ' + phone) + '</span>'
            + '</div>'
            + '<div class="dc-confirm-row">'
            +   '<span class="dc-confirm-row-label">País</span>'
            +   '<span class="dc-confirm-row-value">' + escapeHtml(countryName + (countryIso ? ' (' + countryIso + ')' : '')) + '</span>'
            + '</div>';

        if (DC_RECARGAS_DATA.woocommerce_active) {
            confirmBtn.textContent = 'Añadir al carrito';
        } else {
            confirmBtn.textContent = 'Confirmar recarga';
        }
        confirmBtn.disabled = false;
        setFeedbackConfirm('', '');
    }

    packageSelect.addEventListener('change', function () {
        var selectedIndex = parseInt(packageSelect.value, 10);
        state.selected = state.bundles[selectedIndex] || null;
        renderPackageCard(state.selected);
    });

    btnContinueConfirm.addEventListener('click', function () {
        if (!state.selected) return;
        buildConfirmStep(state.selected);
        goToStep('confirm', 'forward');
    });

    btnBackConfirm.addEventListener('click', function () {
        goToStep('phone', 'back');
        if (!packageStage.hidden) {
            packageSelect.focus();
        }
    });

    btnRestart.addEventListener('click', function () {
        state.fullPhone = '';
        state.lastSearchKey = '';
        state.lastSearchAt = 0;
        resetPackageStage(true);
        resultEl.innerHTML = '';
        setFeedback('', '');
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
