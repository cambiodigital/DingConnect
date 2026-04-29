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
    var providerStatusEl  = findInApp('dc-provider-status');
    var dynamicFieldsEl   = findInApp('dc-dynamic-fields');
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
        packageStage, packageSelect, packageCard, providerStatusEl, dynamicFieldsEl,
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
    var landingKey = String(app.getAttribute('data-landing-key') || '').trim();
    var featuredBundleId = String(app.getAttribute('data-featured-bundle-id') || '').trim();
    var allowedBundleIds = String(app.getAttribute('data-allowed-bundle-ids') || '')
        .split(',').map(function (id) { return id.trim(); }).filter(Boolean);
    var allowedBundleMap = {};
    allowedBundleIds.forEach(function (id) { allowedBundleMap[id] = true; });

    var autoTimer = null;
    var estimateTimer = null;
    var SEARCH_CACHE_TTL_MS = 10000;
    var PROVIDER_STATUS_CACHE_TTL_MS = 300000;

    /* ===== State ===== */
    var state = {
        country: null,
        bundles: [],
        visibleBundles: [],
        selected: null,
        featuredBundleId: featuredBundleId,
        fullPhone: '',
        lastSearchKey: '',
        lastSearchAt: 0,
        inFlightSearchKey: '',
        dataSource: '',
        allowedBundleMap: allowedBundleMap,
        providerStatusCache: {},
        selectedSettings: {},
        selectedBillRef: '',
        selectedBillOptions: [],
        selectedEstimate: null,
        selectedEstimateError: '',
        selectedSendValue: 0,
        wizardStep: 'phone',  // phone | confirm | result
    };

    function setAllowedBundles(bundleIds) {
        var nextIds = Array.isArray(bundleIds) ? bundleIds.map(function (id) {
            return String(id || '').trim();
        }).filter(Boolean) : [];

        allowedBundleIds = nextIds;
        allowedBundleMap = {};
        nextIds.forEach(function (id) {
            allowedBundleMap[id] = true;
        });
        state.allowedBundleMap = allowedBundleMap;
    }

    function applyLandingRuntimeConfig(config) {
        if (!config || typeof config !== 'object') {
            return;
        }

        if (Array.isArray(config.bundle_ids)) {
            setAllowedBundles(config.bundle_ids);
        }

        var runtimeFeatured = String(config.featured_bundle_id || '').trim();
        state.featuredBundleId = runtimeFeatured;

        var runtimeCountryIso = String(config.country_iso || '').trim().toUpperCase();
        if (runtimeCountryIso) {
            defaultCountryIso = runtimeCountryIso;
        }
    }

    async function refreshLandingRuntimeConfig() {
        if (!landingKey) {
            return null;
        }

        try {
            var response = await fetchJson('/landing-config?landing_key=' + encodeURIComponent(landingKey), {
                cache: 'no-store'
            });
            if (response && response.ok && response.result) {
                applyLandingRuntimeConfig(response.result);
                return response.result;
            }
        } catch (err) {
            // Mantener configuración estática del shortcode si falla el refresco runtime.
        }

        return null;
    }

    var landingConfigPromise = refreshLandingRuntimeConfig();

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
        state.visibleBundles = [];
        state.selectedSettings = {};
        state.selectedBillRef = '';
        state.selectedBillOptions = [];
        state.selectedEstimate = null;
        state.selectedSendValue = 0;
        if (clearBundles) {
            state.bundles = [];
        }
        packageSelect.innerHTML = '';
        packageCard.innerHTML = '';
        providerStatusEl.innerHTML = '';
        providerStatusEl.hidden = true;
        dynamicFieldsEl.innerHTML = '';
        dynamicFieldsEl.hidden = true;
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

    function getCurrentSendValue(bundle) {
        var fallback = Number((bundle && bundle.SendValue) || 0);
        var current = Number(state.selectedSendValue || 0);
        return current > 0 ? current : fallback;
    }

    function getCurrentReceiveValue(bundle) {
        if (state.selectedEstimate && Number(state.selectedEstimate.ReceiveValue || 0) > 0) {
            return {
                amount: Number(state.selectedEstimate.ReceiveValue || 0),
                currency: String(state.selectedEstimate.ReceiveCurrencyIso || bundle.ReceiveCurrencyIso || ''),
                excludingTax: Number(state.selectedEstimate.ReceiveValueExcludingTax || 0),
            };
        }

        var amount = Number((bundle && bundle.ReceiveValue) || 0);
        var excludingTax = Number((bundle && bundle.ReceiveValueExcludingTax) || 0);

        // En bundles guardados (precio comercial), evitar mostrar valores fiscales heredados
        // que vienen en otra escala/moneda y no son consistentes con el precio al público.
        if (state.dataSource === 'saved') {
            excludingTax = amount;
        }

        return {
            amount: amount,
            currency: String((bundle && bundle.ReceiveCurrencyIso) || ''),
            excludingTax: excludingTax,
        };
    }

    function getDisplayPrice(bundle) {
        var receive = getCurrentReceiveValue(bundle);
        if (state.dataSource === 'saved' && Number(receive.amount || 0) > 0) {
            return {
                amount: Number(receive.amount || 0),
                currency: String(receive.currency || ''),
            };
        }

        return {
            amount: Number(getCurrentSendValue(bundle) || 0),
            currency: String((bundle && bundle.SendCurrencyIso) || ''),
        };
    }

    function getBundleBenefitText(bundle) {
        if (!bundle || typeof bundle !== 'object') {
            return '';
        }

        var description = String(bundle.Description || '').trim();
        if (description) {
            return description;
        }

        var benefits = Array.isArray(bundle.Benefits)
            ? bundle.Benefits.map(function (entry) {
                return String(entry || '').trim();
            }).filter(function (entry) {
                return entry !== '';
            })
            : [];

        if (benefits.length > 0) {
            return benefits.join(' · ');
        }

        return String(bundle.DefaultDisplayText || bundle.SkuCode || '').trim();
    }

    function getMandatorySettingDefinitions(bundle) {
        return Array.isArray(bundle && bundle.SettingDefinitions)
            ? bundle.SettingDefinitions.filter(function (definition) {
                return definition && definition.IsMandatory;
            })
            : [];
    }

    function isRangeBundle(bundle) {
        return !!(bundle && bundle.IsRange);
    }

    function syncSettingsFromInputs() {
        state.selectedSettings = {};

        if (!dynamicFieldsEl) return;

        dynamicFieldsEl.querySelectorAll('[data-setting-name]').forEach(function (input) {
            var name = String(input.getAttribute('data-setting-name') || '').trim();
            if (!name) return;
            state.selectedSettings[name] = String(input.value || '').trim();
        });
    }

    function normalizeErrorCodes(errorCodes) {
        return (Array.isArray(errorCodes) ? errorCodes : []).map(function (entry) {
            if (entry && typeof entry === 'object') {
                return {
                    Code: String(entry.Code || '').trim(),
                    Context: String(entry.Context || '').trim(),
                    Message: String(entry.Message || '').trim(),
                };
            }

            return {
                Code: String(entry || '').trim(),
                Context: '',
                Message: '',
            };
        }).filter(function (entry) {
            return entry.Code !== '' || entry.Message !== '';
        });
    }

    function getResultErrorMessage(item, fallback) {
        if (!item || typeof item !== 'object') {
            return fallback;
        }

        var errorCodes = normalizeErrorCodes(item.ErrorCodes);
        var resultCode = Number(item.ResultCode || 0);
        if (!resultCode && !errorCodes.length) {
            return '';
        }

        var firstError = errorCodes[0] || null;
        if (!firstError) {
            return fallback;
        }

        var mapped = {
            InsufficientBalance: 'No hay saldo suficiente en DingConnect para completar esta operación.',
            AccountNumberInvalid: 'El número no es válido para este producto.',
            RechargeNotAllowed: 'La recarga no está permitida para esta cuenta o producto.',
            ProviderError: 'El proveedor rechazó temporalmente la solicitud. Intenta de nuevo en unos minutos.',
            RateLimited: 'El proveedor limitó temporalmente la consulta. Espera unos segundos e inténtalo de nuevo.',
            LookupBillsFailed: 'No se pudo consultar la factura con los datos actuales.',
        };

        if (mapped[firstError.Code]) {
            return mapped[firstError.Code];
        }

        return firstError.Message || firstError.Context || fallback;
    }

    function clearBillSelection(bundle, reasonMessage) {
        state.selectedBillRef = '';
        state.selectedBillOptions = [];
        if (bundle && bundle.LookupBillsRequired) {
            state.selectedEstimate = null;
            state.selectedEstimateError = '';
        }

        if (!dynamicFieldsEl) return;

        var billSelect = dynamicFieldsEl.querySelector('#dc-bill-select');
        var lookupFeedback = dynamicFieldsEl.querySelector('#dc-bill-feedback');
        if (billSelect) {
            billSelect.innerHTML = '';
            billSelect.hidden = true;
        }
        if (lookupFeedback && reasonMessage) {
            lookupFeedback.textContent = reasonMessage;
        }
    }

    function getSettingsPayload(bundle) {
        syncSettingsFromInputs();

        return Object.keys(state.selectedSettings).filter(function (name) {
            return String(state.selectedSettings[name] || '').trim() !== '';
        }).map(function (name) {
            return {
                Name: name,
                Value: String(state.selectedSettings[name] || '').trim(),
            };
        });
    }

    function renderProviderStatus(info) {
        if (!providerStatusEl) return;

        if (!info || !info.ProviderCode) {
            providerStatusEl.hidden = true;
            providerStatusEl.innerHTML = '';
            return;
        }

        var isUp = info.IsProcessingTransfers !== false;
        providerStatusEl.hidden = false;
        providerStatusEl.className = 'dc-provider-status ' + (isUp ? 'is-ok' : 'is-down');
        providerStatusEl.innerHTML = ''
            + '<strong>' + (isUp ? 'Proveedor disponible' : 'Proveedor temporalmente no disponible') + '</strong>'
            + (info.Message ? '<span>' + escapeHtml(String(info.Message)) + '</span>' : '');
    }

    async function ensureProviderStatus(bundle, surface) {
        if (!bundle || !bundle.ProviderCode) return true;

        var providerCode = String(bundle.ProviderCode);
        if (Object.prototype.hasOwnProperty.call(state.providerStatusCache, providerCode)) {
            var cachedEntry = state.providerStatusCache[providerCode];
            var cacheAge = Date.now() - Number(cachedEntry && cachedEntry.ts ? cachedEntry.ts : 0);
            if (cacheAge <= PROVIDER_STATUS_CACHE_TTL_MS) {
                var cached = cachedEntry.info || null;
                renderProviderStatus(cached);
                if (cached && cached.IsProcessingTransfers === false) {
                    if (surface === 'confirm') setFeedbackConfirm('El proveedor está temporalmente no disponible. Prueba más tarde.', 'warning');
                    else setFeedback('El proveedor está temporalmente no disponible. Prueba más tarde.', 'warning');
                    return false;
                }
                return true;
            }

            delete state.providerStatusCache[providerCode];
        }

        renderProviderStatus({ ProviderCode: providerCode, IsProcessingTransfers: true, Message: 'Consultando disponibilidad del proveedor...' });

        try {
            var response = await fetchJson('/provider-status?provider_code=' + encodeURIComponent(providerCode));
            var info = Array.isArray(response.result) && response.result.length ? response.result[0] : {
                ProviderCode: providerCode,
                IsProcessingTransfers: true,
                Message: '',
            };
            state.providerStatusCache[providerCode] = {
                info: info,
                ts: Date.now(),
            };
            renderProviderStatus(info);

            if (info.IsProcessingTransfers === false) {
                if (surface === 'confirm') setFeedbackConfirm('El proveedor está temporalmente no disponible. Prueba más tarde.', 'warning');
                else setFeedback('El proveedor está temporalmente no disponible. Prueba más tarde.', 'warning');
                return false;
            }
            return true;
        } catch (error) {
            renderProviderStatus(null);
            return true;
        }
    }

    function validateSelectedBundle(bundle) {
        if (!bundle) return '';

        var requiredSettings = getMandatorySettingDefinitions(bundle);

        if (bundle.LookupBillsRequired && !state.selectedBillRef) {
            return 'Selecciona la factura o importe consultado antes de continuar.';
        }

        if (requiredSettings.length > 0) {
            var settingMap = {};
            getSettingsPayload(bundle).forEach(function (setting) {
                settingMap[String(setting.Name)] = String(setting.Value || '').trim();
            });

            var missing = requiredSettings.find(function (definition) {
                return !String(settingMap[String(definition.Name)] || '').trim();
            });

            if (missing) {
                return 'Completa el dato requerido: ' + String(missing.Description || missing.Name) + '.';
            }
        }

        if (isRangeBundle(bundle)) {
            var currentSendValue = getCurrentSendValue(bundle);
            var min = Number(bundle.MinimumSendValue || bundle.SendValue || 0);
            var max = Number(bundle.MaximumSendValue || bundle.SendValue || 0);

            if (currentSendValue <= 0) {
                return 'Indica un importe válido para este producto.';
            }

            if (min > 0 && currentSendValue < min) {
                return 'El importe está por debajo del mínimo permitido para este producto.';
            }

            if (max > 0 && currentSendValue > max) {
                return 'El importe supera el máximo permitido para este producto.';
            }
        }

        var regexSource = String(bundle.ValidationRegex || '').trim();
        if (!regexSource) return '';

        var fullDigits = String(state.fullPhone || normalizePhone() || '').replace(/\D/g, '');
        var localDigits = String(phoneEl.value || '').replace(/\D/g, '');
        var candidates = [];

        if (fullDigits) candidates.push(fullDigits);
        if (localDigits && candidates.indexOf(localDigits) === -1) candidates.push(localDigits);

        try {
            var pattern = new RegExp(regexSource);
            var matches = candidates.some(function (candidate) {
                return pattern.test(candidate);
            });

            if (!matches) {
                return 'El número no cumple el formato requerido por este proveedor.';
            }
        } catch (error) {
            console.warn('DingConnect: ValidationRegex no compatible en este navegador.', regexSource, error);
        }

        return '';
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
        var raw = await response.text();
        var data = null;
        try {
            data = raw ? JSON.parse(raw) : null;
        } catch (e) {
            data = null;
        }

        if (!data && !response.ok) {
            throw new Error('El servidor devolvio una respuesta invalida (HTTP ' + response.status + ').');
        }

        if (!response.ok) throw new Error((data && data.message) || 'Error en la solicitud.');
        return data;
    }

    function renderEstimateSummary(bundle) {
        var estimateEl = dynamicFieldsEl ? dynamicFieldsEl.querySelector('#dc-range-estimate') : null;
        if (!estimateEl || !bundle) return;

        estimateEl.classList.remove('is-loading');

        if (!state.selectedEstimate) {
            if (state.selectedEstimateError) {
                estimateEl.innerHTML = '<span class="dc-estimate-error">' + escapeHtml(state.selectedEstimateError) + '</span>';
                return;
            }
            estimateEl.innerHTML = '<span>Ingresa un importe para calcular cuánto recibirá el destinatario.</span>';
            return;
        }

        var receiveValue = Number(state.selectedEstimate.ReceiveValue || 0);
        var receiveCurrency = String(state.selectedEstimate.ReceiveCurrencyIso || bundle.ReceiveCurrencyIso || '');
        var excludingTax = Number(state.selectedEstimate.ReceiveValueExcludingTax || 0);
        var fee = Number(state.selectedEstimate.CustomerFee || 0);
        var feeCurrency = String(state.selectedEstimate.SendCurrencyIso || bundle.SendCurrencyIso || '');
        var taxName = String(state.selectedEstimate.TaxName || 'Impuestos');
        var taxMode = String(state.selectedEstimate.TaxCalculation || '');
        var taxAmount = Math.max(0, receiveValue - excludingTax);

        estimateEl.innerHTML = ''
            + '<strong>' + escapeHtml(formatMoney(receiveValue, receiveCurrency)) + '</strong>'
            + '<span>recibirá el destinatario</span>'
            + (excludingTax > 0 ? '<small>Sin impuestos: ' + escapeHtml(formatMoney(excludingTax, receiveCurrency)) + '</small>' : '')
            + (fee > 0 ? '<small>Tarifa cliente: ' + escapeHtml(formatMoney(fee, feeCurrency)) + '</small>' : '')
            + (taxAmount > 0 ? '<small>' + escapeHtml(taxName + (taxMode ? ' (' + taxMode + ')' : '')) + ': ' + escapeHtml(formatMoney(taxAmount, receiveCurrency)) + '</small>' : '');
    }

    async function requestEstimate(bundle, sendValue) {
        if (!bundle || !isRangeBundle(bundle) || !sendValue || sendValue <= 0) {
            state.selectedEstimate = null;
            state.selectedEstimateError = '';
            renderEstimateSummary(bundle);
            return;
        }

        var estimateEl = dynamicFieldsEl ? dynamicFieldsEl.querySelector('#dc-range-estimate') : null;
        if (estimateEl) {
            estimateEl.classList.add('is-loading');
            estimateEl.innerHTML = '<span>Calculando estimación...</span>';
        }

        try {
            var response = await fetchJson('/estimate-prices', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sku_code: bundle.SkuCode,
                    send_value: sendValue,
                    send_currency_iso: bundle.SendCurrencyIso || '',
                }),
            });

            var estimateItem = Array.isArray(response.result) && response.result.length
                ? response.result[0]
                : null;
            var estimateError = getResultErrorMessage(estimateItem, 'No se pudo calcular la estimación para este importe.');

            if (estimateError) {
                state.selectedEstimate = null;
                state.selectedEstimateError = estimateError;
            } else {
                state.selectedEstimate = estimateItem;
                state.selectedEstimateError = '';
            }
        } catch (error) {
            state.selectedEstimate = null;
            state.selectedEstimateError = 'No se pudo estimar el importe. Puedes intentarlo de nuevo.';
        }

        renderEstimateSummary(bundle);
    }

    async function lookupBillsForBundle(bundle) {
        if (!bundle || !bundle.LookupBillsRequired) return;

        var lookupBtn = dynamicFieldsEl.querySelector('#dc-lookup-bills-btn');
        var lookupFeedback = dynamicFieldsEl.querySelector('#dc-bill-feedback');
        var billSelect = dynamicFieldsEl.querySelector('#dc-bill-select');
        var validationError = validateSelectedBundle(Object.assign({}, bundle, { LookupBillsRequired: false }));

        if (validationError) {
            lookupFeedback.textContent = validationError;
            return;
        }

        lookupBtn.disabled = true;
        lookupFeedback.textContent = 'Consultando facturas o importes disponibles...';
        clearBillSelection(bundle, 'Consultando facturas o importes disponibles...');

        try {
            var response = await fetchJson('/lookup-bills', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    sku_code: bundle.SkuCode,
                    account_number: state.fullPhone,
                    settings: getSettingsPayload(bundle),
                }),
            });

            state.selectedBillOptions = Array.isArray(response.result) ? response.result : [];
            billSelect.innerHTML = '';

            var lookupError = '';
            if (state.selectedBillOptions.length) {
                lookupError = getResultErrorMessage(state.selectedBillOptions[0], 'No se pudo consultar la factura para este producto.');
            }

            if (lookupError) {
                state.selectedBillRef = '';
                billSelect.hidden = true;
                lookupFeedback.textContent = lookupError;
                state.selectedEstimate = null;
                state.selectedEstimateError = lookupError;
                renderEstimateSummary(bundle);
                return;
            }

            if (!state.selectedBillOptions.length) {
                state.selectedBillRef = '';
                billSelect.hidden = true;
                lookupFeedback.textContent = 'No se encontraron facturas o importes disponibles para este producto. Verifica los datos y vuelve a consultar.';
                return;
            }

            state.selectedBillOptions.forEach(function (bill, index) {
                var option = document.createElement('option');
                option.value = String(index);
                option.textContent = String(bill.BillRef || 'Factura') + ' · ' + formatMoney(bill.SendValue || 0, bill.SendCurrencyIso || bundle.SendCurrencyIso || '');
                billSelect.appendChild(option);
            });

            billSelect.hidden = false;
            billSelect.value = '0';

            var firstBill = state.selectedBillOptions[0];
            state.selectedBillRef = String(firstBill.BillRef || '');
            state.selectedSendValue = Number(firstBill.SendValue || bundle.SendValue || 0);
            state.selectedEstimate = {
                SendValue: Number(firstBill.SendValue || 0),
                SendCurrencyIso: String(firstBill.SendCurrencyIso || bundle.SendCurrencyIso || ''),
                ReceiveValue: Number(firstBill.ReceiveValue || 0),
                ReceiveCurrencyIso: String(firstBill.ReceiveCurrencyIso || bundle.ReceiveCurrencyIso || ''),
                ReceiveValueExcludingTax: Number(firstBill.ReceiveValueExcludingTax || 0),
                CustomerFee: Number(firstBill.CustomerFee || 0),
                TaxName: String(firstBill.TaxName || ''),
                TaxCalculation: String(firstBill.TaxCalculation || ''),
            };
            state.selectedEstimateError = '';

            lookupFeedback.textContent = 'Selecciona la factura o el importe que deseas pagar.';
            renderEstimateSummary(bundle);
        } catch (error) {
            lookupFeedback.textContent = error.message || 'No se pudo consultar la factura.';
            state.selectedEstimate = null;
            state.selectedEstimateError = lookupFeedback.textContent;
            renderEstimateSummary(bundle);
        } finally {
            lookupBtn.disabled = false;
        }
    }

    function renderDynamicFields(bundle) {
        if (!dynamicFieldsEl) return;

        state.selectedSettings = {};
        state.selectedBillRef = '';
        state.selectedBillOptions = [];
        state.selectedEstimate = null;
        state.selectedEstimateError = '';
        state.selectedSendValue = Number((bundle && bundle.SendValue) || 0);

        if (!bundle) {
            dynamicFieldsEl.hidden = true;
            dynamicFieldsEl.innerHTML = '';
            return;
        }

        var settingDefinitions = Array.isArray(bundle.SettingDefinitions) ? bundle.SettingDefinitions : [];
        var hasDynamicFields = isRangeBundle(bundle) || settingDefinitions.length > 0 || bundle.LookupBillsRequired;

        if (!hasDynamicFields) {
            dynamicFieldsEl.hidden = true;
            dynamicFieldsEl.innerHTML = '';
            return;
        }

        dynamicFieldsEl.hidden = false;

        var html = '';

        if (isRangeBundle(bundle)) {
            var min = Number(bundle.MinimumSendValue || bundle.SendValue || 0);
            var max = Number(bundle.MaximumSendValue || bundle.SendValue || 0);
            html += ''
                + '<div class="dc-dynamic-block">'
                +   '<label class="dc-dynamic-label" for="dc-range-amount">Importe a enviar</label>'
                +   '<input id="dc-range-amount" class="dc-dynamic-input" type="number" min="' + escapeHtml(String(min)) + '" max="' + escapeHtml(String(max)) + '" step="0.01" value="' + escapeHtml(String(getCurrentSendValue(bundle))) + '">'
                +   '<div class="dc-dynamic-hint">Rango permitido: ' + escapeHtml(formatMoney(min, bundle.SendCurrencyIso || '')) + ' a ' + escapeHtml(formatMoney(max, bundle.SendCurrencyIso || '')) + '</div>'
                +   '<div id="dc-range-estimate" class="dc-range-estimate"></div>'
                + '</div>';
        }

        if (settingDefinitions.length > 0) {
            html += '<div class="dc-dynamic-block"><div class="dc-dynamic-section-title">Datos requeridos por el proveedor</div>';
            settingDefinitions.forEach(function (definition) {
                var label = String(definition.Description || definition.Name || 'Dato adicional');
                html += ''
                    + '<label class="dc-dynamic-label" for="dc-setting-' + escapeHtml(String(definition.Name)) + '">' + escapeHtml(label) + (definition.IsMandatory ? ' *' : '') + '</label>'
                    + '<input id="dc-setting-' + escapeHtml(String(definition.Name)) + '" class="dc-dynamic-input" type="text" data-setting-name="' + escapeHtml(String(definition.Name)) + '" placeholder="' + escapeHtml(label) + '">';
            });
            html += '<div class="dc-dynamic-hint">Usaremos estos datos exactamente como los exige DingConnect para el producto seleccionado.</div></div>';
        }

        if (bundle.LookupBillsRequired) {
            html += ''
                + '<div class="dc-dynamic-block">'
                +   '<div class="dc-dynamic-section-title">Consulta de factura</div>'
                +   '<button type="button" id="dc-lookup-bills-btn" class="dc-secondary-btn">Consultar factura o importe</button>'
                +   '<select id="dc-bill-select" class="dc-dynamic-select" hidden></select>'
                +   '<div id="dc-bill-feedback" class="dc-dynamic-hint">Consulta los importes disponibles antes de continuar.</div>'
                + '</div>';
        }

        dynamicFieldsEl.innerHTML = html;

        if (isRangeBundle(bundle)) {
            var rangeInput = dynamicFieldsEl.querySelector('#dc-range-amount');
            renderEstimateSummary(bundle);
            rangeInput.addEventListener('input', function () {
                var value = Number(rangeInput.value || 0);
                state.selectedSendValue = value;
                state.selectedEstimate = null;
                state.selectedEstimateError = '';
                clearBillSelection(bundle, 'Si cambias el importe, debes consultar la factura nuevamente.');
                renderEstimateSummary(bundle);
                if (estimateTimer) clearTimeout(estimateTimer);
                estimateTimer = setTimeout(function () {
                    requestEstimate(bundle, value);
                }, 350);
            });

            requestEstimate(bundle, getCurrentSendValue(bundle));
        }

        dynamicFieldsEl.querySelectorAll('[data-setting-name]').forEach(function (input) {
            input.addEventListener('input', function () {
                syncSettingsFromInputs();
                clearBillSelection(bundle, 'Cambiaste datos requeridos. Consulta la factura otra vez para continuar.');
            });
        });

        if (bundle.LookupBillsRequired) {
            var lookupBtn = dynamicFieldsEl.querySelector('#dc-lookup-bills-btn');
            var billSelect = dynamicFieldsEl.querySelector('#dc-bill-select');

            lookupBtn.addEventListener('click', function () {
                lookupBillsForBundle(bundle);
            });

            billSelect.addEventListener('change', function () {
                var selectedBill = state.selectedBillOptions[parseInt(billSelect.value, 10)] || null;
                if (!selectedBill) {
                    state.selectedBillRef = '';
                    return;
                }

                state.selectedBillRef = String(selectedBill.BillRef || '');
                state.selectedSendValue = Number(selectedBill.SendValue || bundle.SendValue || 0);
                state.selectedEstimate = {
                    SendValue: Number(selectedBill.SendValue || 0),
                    SendCurrencyIso: String(selectedBill.SendCurrencyIso || bundle.SendCurrencyIso || ''),
                    ReceiveValue: Number(selectedBill.ReceiveValue || 0),
                    ReceiveCurrencyIso: String(selectedBill.ReceiveCurrencyIso || bundle.ReceiveCurrencyIso || ''),
                    ReceiveValueExcludingTax: Number(selectedBill.ReceiveValueExcludingTax || 0),
                    CustomerFee: Number(selectedBill.CustomerFee || 0),
                    TaxName: String(selectedBill.TaxName || ''),
                    TaxCalculation: String(selectedBill.TaxCalculation || ''),
                };
                state.selectedEstimateError = '';
                renderEstimateSummary(bundle);
            });
        }
    }

    /* ===== Search bundles ===== */
    async function searchBundles(opts) {
        opts = opts || {};

        if (landingConfigPromise) {
            await landingConfigPromise;
            landingConfigPromise = null;
        }

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
        var displayPrice = getDisplayPrice(bundle);
        var amount = formatMoney(displayPrice.amount || 0, displayPrice.currency || 'USD');

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
        state.visibleBundles = bundles.slice();
        packageSelect.innerHTML = '';

        if (!bundles.length) {
            resetPackageStage(false);
            return;
        }

        if (bundles.length > 1) {
            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'Selecciona un paquete...';
            packageSelect.appendChild(placeholder);
        }

        bundles.forEach(function (bundle, index) {
            var option = document.createElement('option');
            option.value = String(index);
            option.textContent = getBundleOptionLabel(bundle);
            packageSelect.appendChild(option);
        });

        if (bundles.length === 1) {
            state.selected = bundles[0];
            packageSelect.value = '0';
            renderPackageCard(state.selected);
            btnContinueConfirm.disabled = false;
            return;
        }

        if (!state.selected || bundles.indexOf(state.selected) === -1) {
            state.selected = null;
        }

        if (state.selected) {
            packageSelect.value = String(bundles.indexOf(state.selected));
            renderPackageCard(state.selected);
        } else {
            packageSelect.value = '';
            renderPackageCard(null);
        }

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

    function getFlowKind(bundle, resultItem, receiptParams) {
        var productType = String((resultItem && resultItem.ProductType) || (bundle && bundle.ProductType) || '').toLowerCase();
        var redemptionMechanism = String((resultItem && resultItem.RedemptionMechanism) || (bundle && bundle.RedemptionMechanism) || '').toLowerCase();
        var additionalInformation = String((bundle && bundle.AdditionalInformation) || '').toLowerCase();
        var hasPin = !!getReceiptParamValue(receiptParams, 'pin');

        if (hasPin || redemptionMechanism === 'readreceipt' || /(voucher|pin|gift|digital)/.test(productType)) {
            return 'voucher';
        }

        if ((bundle && bundle.LookupBillsRequired) || /(electric|bill|utility|power)/.test(productType)) {
            return 'electricity';
        }

        if (/(dth|satellite|tv)/.test(productType) || /(dth|satellite|dish|television)/.test(additionalInformation)) {
            return 'dth';
        }

        if (isRangeBundle(bundle)) {
            return 'range';
        }

        return 'mobile';
    }

    function getFlowCopy(flowKind, stateKey) {
        var copyMap = {
            voucher: {
                success: {
                    title: 'Voucher listo para usar',
                    summary: 'Guarda el codigo y las instrucciones entregadas por el proveedor antes de cerrar esta pantalla.',
                    receiptTitle: 'Codigo e instrucciones de canje',
                    paramsTitle: 'Datos del voucher',
                    nextTitle: 'Que hacer ahora',
                    nextBody: 'Comparte el codigo solo con el destinatario final y conserva esta referencia para soporte.',
                    confirmLabel: 'Entrega',
                    confirmValue: 'Recibiras un codigo o PIN junto con las instrucciones de canje al finalizar.',
                },
                pending: {
                    title: 'Voucher en validacion',
                    summary: 'El proveedor recibio la solicitud, pero el codigo final todavia no esta confirmado.',
                    receiptTitle: 'Instrucciones preliminares',
                    paramsTitle: 'Datos preliminares del voucher',
                    nextTitle: 'Siguiente paso',
                    nextBody: 'No repitas la compra mientras el estado siga pendiente. Espera la confirmacion final o revisa el pedido en WooCommerce.',
                    confirmLabel: 'Entrega',
                    confirmValue: 'Si el proveedor responde en diferido, el codigo aparecera cuando el estado deje de estar pendiente.',
                },
                error: {
                    title: 'Voucher no confirmado',
                    summary: 'No hay un codigo final confirmado todavia para este voucher.',
                    receiptTitle: 'Detalle recibido del proveedor',
                    paramsTitle: 'Datos tecnicos del voucher',
                    nextTitle: 'Revision sugerida',
                    nextBody: 'Verifica la referencia, conserva el pedido y revisa el mensaje tecnico antes de reintentar.',
                    confirmLabel: 'Entrega',
                    confirmValue: 'El resultado final depende de la respuesta del proveedor y del receipt devuelto.',
                },
            },
            electricity: {
                success: {
                    title: 'Pago del servicio registrado',
                    summary: 'Conserva la referencia y confirma con el titular del servicio que la factura o saldo quedo actualizado.',
                    receiptTitle: 'Comprobante o instrucciones del servicio',
                    paramsTitle: 'Datos del servicio',
                    nextTitle: 'Que hacer ahora',
                    nextBody: 'No vuelvas a pagar la misma factura hasta verificar el resultado con la referencia mostrada abajo.',
                    confirmLabel: 'Servicio',
                    confirmValue: 'Se aplicara sobre la factura consultada y el proveedor puede devolver datos adicionales del servicio.',
                },
                pending: {
                    title: 'Pago del servicio en validacion',
                    summary: 'El proveedor recibio la solicitud del servicio, pero aun no cerro el estado final.',
                    receiptTitle: 'Comprobante preliminar',
                    paramsTitle: 'Datos preliminares del servicio',
                    nextTitle: 'Siguiente paso',
                    nextBody: 'No repitas el pago mientras el estado siga Submitted, Pending o Processing. Espera conciliacion o soporte.',
                    confirmLabel: 'Servicio',
                    confirmValue: 'La factura consultada queda asociada a esta operacion y puede tardar en reflejarse.',
                },
                error: {
                    title: 'Pago del servicio no confirmado',
                    summary: 'La operacion no quedo cerrada con estado exitoso para este servicio.',
                    receiptTitle: 'Respuesta del proveedor',
                    paramsTitle: 'Datos tecnicos del servicio',
                    nextTitle: 'Revision sugerida',
                    nextBody: 'Valida la factura consultada, la referencia y el estado antes de decidir un nuevo intento.',
                    confirmLabel: 'Servicio',
                    confirmValue: 'El proveedor puede responder con informacion adicional incluso si el estado sigue no terminal.',
                },
            },
            dth: {
                success: {
                    title: 'Recarga DTH registrada',
                    summary: 'La recarga del servicio de TV quedo enviada con la referencia operativa correspondiente.',
                    receiptTitle: 'Detalle del servicio DTH',
                    paramsTitle: 'Datos entregados por el proveedor',
                    nextTitle: 'Que hacer ahora',
                    nextBody: 'Si necesitas soporte, comparte la referencia Ding y el numero de abonado usado en esta compra.',
                    confirmLabel: 'Servicio',
                    confirmValue: 'Verifica el numero de abonado y el operador DTH antes de confirmar la recarga.',
                },
                pending: {
                    title: 'Recarga DTH en validacion',
                    summary: 'La solicitud ya salio al proveedor DTH, pero todavia no hay cierre terminal.',
                    receiptTitle: 'Detalle preliminar del servicio DTH',
                    paramsTitle: 'Datos preliminares del proveedor',
                    nextTitle: 'Siguiente paso',
                    nextBody: 'No repitas la recarga mientras el estado siga pendiente. Espera confirmacion final o conciliacion manual.',
                    confirmLabel: 'Servicio',
                    confirmValue: 'Algunos proveedores DTH validan el abonado antes de cerrar la operacion.',
                },
                error: {
                    title: 'Recarga DTH no confirmada',
                    summary: 'El proveedor DTH no devolvio un cierre exitoso para esta operacion.',
                    receiptTitle: 'Respuesta del servicio DTH',
                    paramsTitle: 'Datos tecnicos del proveedor',
                    nextTitle: 'Revision sugerida',
                    nextBody: 'Revisa operador, cuenta y referencia antes de generar una nueva solicitud.',
                    confirmLabel: 'Servicio',
                    confirmValue: 'La confirmacion final depende de la validacion del operador DTH y del estado devuelto.',
                },
            },
            range: {
                success: {
                    title: 'Recarga movil confirmada',
                    summary: 'El importe final aceptado por el proveedor quedo registrado con la estimacion aplicada a esta operacion.',
                    receiptTitle: 'Detalle final de la recarga',
                    paramsTitle: 'Datos entregados por el proveedor',
                    nextTitle: 'Que hacer ahora',
                    nextBody: 'Conserva la referencia y el importe final recibido por si necesitas soporte o conciliacion.',
                    confirmLabel: 'Importe',
                    confirmValue: 'El proveedor confirmara el valor final sobre el rango solicitado y puede ajustar impuestos o receive value.',
                },
                pending: {
                    title: 'Recarga movil en validacion',
                    summary: 'La solicitud de rango quedo enviada, pero el proveedor aun no devolvio el cierre final.',
                    receiptTitle: 'Detalle preliminar de la recarga',
                    paramsTitle: 'Datos preliminares del proveedor',
                    nextTitle: 'Siguiente paso',
                    nextBody: 'No repitas la compra mientras el estado siga pendiente. Espera la conciliacion final porque el importe ya fue enviado al proveedor.',
                    confirmLabel: 'Importe',
                    confirmValue: 'El valor estimado puede quedar pendiente hasta que DingConnect cierre la operacion con el proveedor.',
                },
                error: {
                    title: 'Recarga movil no confirmada',
                    summary: 'No hay un cierre exitoso para el importe solicitado en este producto de rango.',
                    receiptTitle: 'Respuesta del proveedor',
                    paramsTitle: 'Datos tecnicos de la recarga',
                    nextTitle: 'Revision sugerida',
                    nextBody: 'Revisa referencia, importe solicitado y mensaje tecnico antes de decidir un nuevo intento.',
                    confirmLabel: 'Importe',
                    confirmValue: 'La confirmacion final depende del calculo real del proveedor y del estado devuelto.',
                },
            },
            mobile: {
                success: {
                    title: 'Recarga procesada',
                    summary: 'La recarga fue aceptada por el proveedor y la referencia quedo registrada.',
                    receiptTitle: 'Detalle de la recarga',
                    paramsTitle: 'Datos entregados por el proveedor',
                    nextTitle: 'Que hacer ahora',
                    nextBody: 'Conserva esta referencia si necesitas soporte o revisar la operacion mas tarde.',
                    confirmLabel: 'Entrega',
                    confirmValue: 'El proveedor devolvera el detalle final de la recarga cuando la operacion termine.',
                },
                pending: {
                    title: 'Recarga en validacion',
                    summary: 'La operacion sigue pendiente en el proveedor y aun no debe tratarse como cerrada.',
                    receiptTitle: 'Detalle preliminar de la recarga',
                    paramsTitle: 'Datos preliminares del proveedor',
                    nextTitle: 'Siguiente paso',
                    nextBody: 'No repitas la recarga mientras el estado siga Submitted, Pending o Processing. Espera el cierre final.',
                    confirmLabel: 'Entrega',
                    confirmValue: 'Si el proveedor responde en diferido, DingConnect cerrara la operacion cuando reciba el estado final.',
                },
                error: {
                    title: 'Recarga no confirmada',
                    summary: 'La respuesta no quedo en estado exitoso para esta recarga.',
                    receiptTitle: 'Respuesta del proveedor',
                    paramsTitle: 'Datos tecnicos de la recarga',
                    nextTitle: 'Revision sugerida',
                    nextBody: 'Revisa referencia, estado y mensaje tecnico antes de volver a intentarlo.',
                    confirmLabel: 'Entrega',
                    confirmValue: 'El proveedor puede devolver informacion tecnica adicional aunque la operacion no cierre en exito.',
                },
            },
        };

        var kindCopy = copyMap[flowKind] || copyMap.mobile;
        return kindCopy[stateKey] || kindCopy.pending;
    }

    function getReceiptParamValue(receiptParams, expectedKey) {
        var expected = String(expectedKey || '').toLowerCase();
        var value = '';

        Object.keys(receiptParams || {}).some(function (key) {
            if (String(key).toLowerCase() !== expected) {
                return false;
            }

            value = String(receiptParams[key] || '').trim();
            return value !== '';
        });

        return value;
    }

    function renderPackageCard(bundle) {
        if (!bundle) {
            packageCard.innerHTML = '';
            renderProviderStatus(null);
            renderDynamicFields(null);
            btnContinueConfirm.disabled = true;
            return;
        }

        var providerLabel = getProviderLabel(bundle);
        var benefit = String(bundle.Description || bundle.DefaultDisplayText || bundle.SkuCode || 'Paquete disponible');
        var countryIso = String(bundle.CountryIso || (state.country ? state.country.iso : '') || '').toUpperCase();
        var displayPrice = getDisplayPrice(bundle);
        var amount = formatMoney(displayPrice.amount || 0, displayPrice.currency || 'USD');
        var rangeHint = isRangeBundle(bundle)
            ? '<div class="dc-package-range-hint">Rango: ' + escapeHtml(formatMoney(bundle.MinimumSendValue || 0, bundle.SendCurrencyIso || '')) + ' a ' + escapeHtml(formatMoney(bundle.MaximumSendValue || 0, bundle.SendCurrencyIso || '')) + '</div>'
            : '';
        var cachedProviderStatus = bundle.ProviderCode && Object.prototype.hasOwnProperty.call(state.providerStatusCache, String(bundle.ProviderCode))
            ? state.providerStatusCache[String(bundle.ProviderCode)]
            : null;
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
            +     rangeHint
            +   '</div>'
            +   '<div class="dc-package-price-block">'
            +     '<span class="dc-package-price-label">Precio al público</span>'
            +     '<strong>' + escapeHtml(amount) + '</strong>'
            +   '</div>'
            +   '<div class="dc-package-iso-chip">' + escapeHtml(countryIso || 'N/A') + '</div>'
            + '</div>'
            + '<div class="dc-package-card-meta">'
            +   '<span class="dc-package-meta-label">Operador</span>'
            +   '<span class="dc-package-meta-value">' + escapeHtml(providerLabel) + '</span>'
            + '</div>';

        renderProviderStatus(cachedProviderStatus);
        renderDynamicFields(bundle);
        btnContinueConfirm.disabled = false;
    }

    function buildConfirmStep(bundle) {
        var providerLabel = getProviderLabel(bundle);
        var benefit = String(bundle.Description || bundle.DefaultDisplayText || bundle.SkuCode || 'Paquete disponible');
        var countryName = state.country ? state.country.name : '';
        var countryIso = String(bundle.CountryIso || (state.country ? state.country.iso : '') || '').toUpperCase();
        var dial = state.country ? '+' + state.country.dial : '';
        var phone = phoneEl.value || '';
        var currentSendValue = getCurrentSendValue(bundle);
        var currentReceive = getCurrentReceiveValue(bundle);
        var displayPrice = getDisplayPrice(bundle);
        var price = formatMoney(displayPrice.amount || 0, displayPrice.currency || 'USD');
        var featuredClass = isFeaturedBundle(bundle) ? ' is-featured' : '';
        var featuredBadge = isFeaturedBundle(bundle)
            ? '<span class="dc-featured-badge">⭐ Paquete destacado</span>'
            : '';
        var settingsPayload = getSettingsPayload(bundle);
        var flowKind = getFlowKind(bundle, null, {});
        var confirmCopy = getFlowCopy(flowKind, 'pending');

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

        if (isRangeBundle(bundle)) {
            confirmCard.innerHTML += ''
                + '<div class="dc-confirm-row">'
                +   '<span class="dc-confirm-row-label">Importe solicitado</span>'
                +   '<span class="dc-confirm-row-value is-price">' + escapeHtml(price) + '</span>'
                + '</div>';
        }

        if (currentReceive.amount > 0) {
            confirmCard.innerHTML += ''
                + '<div class="dc-confirm-row">'
                +   '<span class="dc-confirm-row-label">Recibe estimado</span>'
                +   '<span class="dc-confirm-row-value is-price">' + escapeHtml(formatMoney(currentReceive.amount, currentReceive.currency || '')) + '</span>'
                + '</div>';
        }

        if (currentReceive.excludingTax > 0 && currentReceive.excludingTax !== currentReceive.amount) {
            confirmCard.innerHTML += ''
                + '<div class="dc-confirm-row">'
                +   '<span class="dc-confirm-row-label">Recibe sin impuestos</span>'
                +   '<span class="dc-confirm-row-value is-price">' + escapeHtml(formatMoney(currentReceive.excludingTax, currentReceive.currency || '')) + '</span>'
                + '</div>';
        }

        if (state.selectedBillRef) {
            confirmCard.innerHTML += ''
                + '<div class="dc-confirm-row">'
                +   '<span class="dc-confirm-row-label">Factura</span>'
                +   '<span class="dc-confirm-row-value">' + escapeHtml(state.selectedBillRef) + '</span>'
                + '</div>';
        }

        settingsPayload.forEach(function (setting) {
            confirmCard.innerHTML += ''
                + '<div class="dc-confirm-row">'
                +   '<span class="dc-confirm-row-label">' + escapeHtml(setting.Name) + '</span>'
                +   '<span class="dc-confirm-row-value">' + escapeHtml(setting.Value) + '</span>'
                + '</div>';
        });

        if (bundle.CustomerCareNumber) {
            confirmCard.innerHTML += ''
                + '<div class="dc-confirm-row">'
            +   '<span class="dc-confirm-row-label">Soporte proveedor</span>'
            +   '<span class="dc-confirm-row-value">' + escapeHtml(String(bundle.CustomerCareNumber)) + '</span>'
                + '</div>';
        }

        if (confirmCopy.confirmValue) {
            confirmCard.innerHTML += ''
                + '<div class="dc-confirm-row">'
            +   '<span class="dc-confirm-row-label">' + escapeHtml(confirmCopy.confirmLabel) + '</span>'
            +   '<span class="dc-confirm-row-value is-benefit">' + escapeHtml(confirmCopy.confirmValue) + '</span>'
                + '</div>';
        }

        if (String(bundle.RedemptionMechanism || '') === 'ReadAdditionalInformation' && bundle.AdditionalInformation) {
            confirmCard.innerHTML += ''
            + '<div class="dc-confirm-row">'
            +   '<span class="dc-confirm-row-label">Instrucciones</span>'
            +   '<span class="dc-confirm-row-value is-benefit">' + escapeHtml(String(bundle.AdditionalInformation)) + '</span>'
            + '</div>';
        }

        if (DC_RECARGAS_DATA.woocommerce_active) {
            confirmBtn.textContent = 'Proceder al pago';
        } else {
            confirmBtn.textContent = 'Confirmar recarga';
        }
        confirmBtn.disabled = false;
        setFeedbackConfirm('', '');
    }

    packageSelect.addEventListener('change', function () {
        var selectedIndex = parseInt(packageSelect.value, 10);
        state.selected = isNaN(selectedIndex) ? null : (state.visibleBundles[selectedIndex] || null);
        renderPackageCard(state.selected);
    });

    btnContinueConfirm.addEventListener('click', async function () {
        if (!state.selected) return;
        var providerAvailable = await ensureProviderStatus(state.selected, 'package');
        if (!providerAvailable) {
            btnContinueConfirm.disabled = false;
            return;
        }

        var validationError = validateSelectedBundle(state.selected);
        if (validationError) {
            setFeedback(validationError, 'warning');
            return;
        }

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
        var providerAvailable = await ensureProviderStatus(state.selected, 'confirm');
        if (!providerAvailable) {
            confirmBtn.disabled = false;
            return;
        }

        var validationError = validateSelectedBundle(state.selected);
        if (validationError) {
            setFeedbackConfirm(validationError, 'warning');
            return;
        }

        confirmBtn.disabled = true;

        if (DC_RECARGAS_DATA.woocommerce_active) {
            await addToCart(state.selected);
        } else {
            await processDirectTransfer(state.selected);
        }
    });

    /* ===== WooCommerce: add to cart ===== */
    async function addToCart(selected) {
        var displayPrice = getDisplayPrice(selected);
        var sendValue = Number(getCurrentSendValue(selected) || 0);
        var receiveValue = Number((selected && selected.ReceiveValue) || 0);
        var checkoutPublicPrice = receiveValue > 0 ? receiveValue : Number(displayPrice.amount || 0);
        if (checkoutPublicPrice <= 0) {
            checkoutPublicPrice = sendValue;
        }
        var payload = {
            account_number: state.fullPhone,
            country_iso: state.country.iso,
            sku_code: selected.SkuCode,
            send_value: sendValue,
            send_currency_iso: selected.SendCurrencyIso || 'EUR',
            public_price: checkoutPublicPrice,
            public_price_currency: String(displayPrice.currency || selected.SendCurrencyIso || 'EUR'),
            provider_name: getProviderLabel(selected),
            bundle_label: selected.DefaultDisplayText || selected.SkuCode,
            bundle_benefit: getBundleBenefitText(selected),
            bundle_id: String((selected && selected.BundleId) || ''),
            product_type: String(selected.ProductType || ''),
            redemption_mechanism: String(selected.RedemptionMechanism || ''),
            lookup_bills_required: !!selected.LookupBillsRequired,
            customer_care_number: String(selected.CustomerCareNumber || ''),
            is_range: !!selected.IsRange,
            settings: getSettingsPayload(selected),
            bill_ref: state.selectedBillRef || '',
        };

        confirmBtn.textContent = 'Procesando pago...';
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
                setFeedbackConfirm(res.message || 'Error al procesar el pago.', 'error');
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Proceder al pago';
            }
        } catch (err) {
            setFeedbackConfirm(err.message || 'No se pudo procesar el pago.', 'error');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Proceder al pago';
        }
    }

    /* ===== Direct transfer ===== */
    async function processDirectTransfer(selected) {
        var payload = {
            account_number: state.fullPhone,
            country_iso: state.country && state.country.iso ? state.country.iso : '',
            sku_code: selected.SkuCode,
            send_value: Number(getCurrentSendValue(selected) || 0),
            send_currency_iso: selected.SendCurrencyIso || 'USD',
            settings: getSettingsPayload(selected),
            bill_ref: state.selectedBillRef || '',
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
        var processingState = item.ProcessingState || record.ProcessingState || item.Status || 'Pendiente';
        var transferRef = transferId.TransferRef || transferId.DistributorRef
            || result.TransferRef || result.DistributorRef || 'N/A';
        var status = item.Status || processingState || '';
        var receiptText = String(record.ReceiptText || item.ReceiptText || '');
        var receiptParams = record.ReceiptParams || item.ReceiptParams || {};
        var extraInformation = String((state.selected && state.selected.AdditionalInformation) || '');
        var isSuccess = /approved|completed|success|ok/i.test(String(status));
        var isPending = /submitted|pending|processing|queued|inprogress/i.test(String(processingState || status));
        var stateKey = isSuccess ? 'success' : (isPending ? 'pending' : 'error');
        var flowKind = getFlowKind(state.selected, item, receiptParams);
        var flowCopy = getFlowCopy(flowKind, stateKey);
        var providerLabel = getProviderLabel(state.selected || item || {});
        var providerRef = getReceiptParamValue(receiptParams, 'providerRef');
        var pinValue = getReceiptParamValue(receiptParams, 'pin');
        var supportPhone = String((state.selected && state.selected.CustomerCareNumber) || '');
        var billRef = String(state.selectedBillRef || item.BillRef || record.BillRef || '');
        var receiptParamsHtml = '';

        Object.keys(receiptParams || {}).forEach(function (key) {
            if (String(key).toLowerCase() === 'pin' || String(key).toLowerCase() === 'providerref') {
                return;
            }

            receiptParamsHtml += ''
                + '<div class="dc-result-param">'
                +   '<span class="dc-result-param-key">' + escapeHtml(String(key)) + '</span>'
                +   '<span class="dc-result-param-value">' + escapeHtml(String(receiptParams[key])) + '</span>'
                + '</div>';
        });

        var icon = isSuccess ? '✓' : (isPending ? '⏳' : '!');
        var html = '<div class="dc-result-card">'
            + '<div class="dc-result-header">'
            + '<span class="dc-result-icon">' + icon + '</span>'
            + '<strong>' + escapeHtml(flowCopy.title) + '</strong>'
            + '</div><div class="dc-result-body">'
            + '<div class="dc-result-receipt">'
            +   '<div class="dc-result-receipt-title">Resumen</div>'
            +   '<div class="dc-result-receipt-text">' + escapeHtml(flowCopy.summary) + '</div>'
            + '</div>'
            + '<div class="dc-result-row"><span>Referencia</span><span>' + escapeHtml(String(transferRef)) + '</span></div>'
            + '<div class="dc-result-row"><span>Estado</span><span>' + escapeHtml(String(status || 'Pendiente')) + '</span></div>'
            + '<div class="dc-result-row"><span>Numero</span><span>' + escapeHtml(String(item.AccountNumber || state.fullPhone || 'N/A')) + '</span></div>';

        if (providerLabel && providerLabel !== 'Operador') {
            html += '<div class="dc-result-row"><span>Proveedor</span><span>' + escapeHtml(providerLabel) + '</span></div>';
        }

        if (item.ReceiveValue) {
            html += '<div class="dc-result-row"><span>Recibe</span><span>' + escapeHtml(String(item.ReceiveCurrencyIso || '') + ' ' + Number(item.ReceiveValue).toFixed(2)) + '</span></div>';
        }

        if (Number(item.ReceiveValueExcludingTax || 0) > 0 && Number(item.ReceiveValueExcludingTax || 0) !== Number(item.ReceiveValue || 0)) {
            html += '<div class="dc-result-row"><span>Recibe sin impuestos</span><span>' + escapeHtml(String(item.ReceiveCurrencyIso || '') + ' ' + Number(item.ReceiveValueExcludingTax).toFixed(2)) + '</span></div>';
        }

        if (billRef) {
            html += '<div class="dc-result-row"><span>Factura</span><span>' + escapeHtml(billRef) + '</span></div>';
        }

        if (pinValue) {
            html += '<div class="dc-result-row"><span>PIN</span><span>' + escapeHtml(pinValue) + '</span></div>';
        }

        if (providerRef) {
            html += '<div class="dc-result-row"><span>Ref. proveedor</span><span>' + escapeHtml(providerRef) + '</span></div>';
        }

        if (supportPhone) {
            html += '<div class="dc-result-row"><span>Soporte proveedor</span><span>' + escapeHtml(supportPhone) + '</span></div>';
        }

        if (receiptText) {
            html += ''
                + '<div class="dc-result-receipt">'
                +   '<div class="dc-result-receipt-title">' + escapeHtml(flowCopy.receiptTitle) + '</div>'
                +   '<div class="dc-result-receipt-text">' + escapeHtml(receiptText).replace(/\n/g, '<br>') + '</div>'
                + '</div>';
        } else if (String((state.selected && state.selected.RedemptionMechanism) || '') === 'ReadAdditionalInformation' && extraInformation) {
            html += ''
                + '<div class="dc-result-receipt">'
                +   '<div class="dc-result-receipt-title">' + escapeHtml(flowCopy.receiptTitle) + '</div>'
                +   '<div class="dc-result-receipt-text">' + escapeHtml(extraInformation).replace(/\n/g, '<br>') + '</div>'
                + '</div>';
        }

        if (receiptParamsHtml) {
            html += ''
                + '<div class="dc-result-receipt">'
                +   '<div class="dc-result-receipt-title">' + escapeHtml(flowCopy.paramsTitle) + '</div>'
                +   '<div class="dc-result-receipt-params">' + receiptParamsHtml + '</div>'
                + '</div>';
        }

        if (processingState && processingState !== status) {
            html += '<div class="dc-result-row"><span>Procesamiento</span><span>' + escapeHtml(String(processingState)) + '</span></div>';
        }

        if (flowCopy.nextBody) {
            html += ''
                + '<div class="dc-result-receipt">'
                +   '<div class="dc-result-receipt-title">' + escapeHtml(flowCopy.nextTitle) + '</div>'
                +   '<div class="dc-result-receipt-text">' + escapeHtml(flowCopy.nextBody) + '</div>'
                + '</div>';
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
