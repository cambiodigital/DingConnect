(function () {
    if (typeof DC_WIZARD_DATA === 'undefined') {
        return;
    }

    var wizardNodes = document.querySelectorAll('[data-dc-wizard="1"]');
    if (!wizardNodes.length) {
        return;
    }

    var STEPS = ['category', 'country', 'operator', 'product', 'review'];

    function safeParseJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (e) {
            return fallback;
        }
    }

    function request(path, options) {
        var base = String(DC_WIZARD_DATA.restBase || '').replace(/\/$/, '');
        var url = base + path;
        var opts = options || {};
        opts.headers = Object.assign({}, opts.headers || {}, {
            'Content-Type': 'application/json',
            'X-WP-Nonce': DC_WIZARD_DATA.nonce || '',
        });

        return fetch(url, opts).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || payload.ok === false) {
                    var message = payload && payload.message ? payload.message : DC_WIZARD_DATA.texts.error;
                    throw new Error(message);
                }

                return payload;
            });
        });
    }

    function toCountryOptions(countries, selectedIso) {
        var options = ['<option value="">Selecciona país...</option>'];

        (countries || []).forEach(function (country) {
            var iso = String(country.iso || '').toUpperCase();
            var name = String(country.name || iso);
            var selected = selectedIso && selectedIso === iso ? ' selected' : '';
            options.push('<option value="' + iso + '"' + selected + '>' + name + ' (' + iso + ')</option>');
        });

        return options.join('');
    }

    function mountWizard(root, idx) {
        var panel = root.querySelector('.dc-wizard-panel');
        var feedback = root.querySelector('[data-dc-wizard-feedback]');
        var btnBack = root.querySelector('[data-dc-wizard-back]');
        var btnNext = root.querySelector('[data-dc-wizard-next]');
        var stepItems = root.querySelectorAll('.dc-wizard-steps [data-step]');

        if (!panel || !feedback || !btnBack || !btnNext || !stepItems.length) {
            return;
        }

        var initialState = safeParseJson(panel.getAttribute('data-dc-wizard-state') || '{}', {});
        var entryMode = String(root.getAttribute('data-entry-mode') || 'number_first');
        var presetCountry = String(root.getAttribute('data-country-iso') || '').toUpperCase();
        var presetCategory = String(root.getAttribute('data-category') || '');
        var fixedPrefix = String(root.getAttribute('data-fixed-prefix') || '').replace(/\D+/g, '');
        var storageKey = 'dc_wizard_session_' + idx + '_' + (presetCountry || 'global') + '_' + (presetCategory || 'all');

        var state = {
            session_id: '',
            current_step: initialState.current_step || 'category',
            category: initialState.category || presetCategory || '',
            country_iso: initialState.country_iso || presetCountry || '',
            operator: initialState.operator || '',
            product: initialState.product || null,
            account_number: initialState.account_number || '',
            offers: [],
            loading: false,
        };

        function setFeedback(message, type) {
            feedback.className = 'dc-feedback' + (type ? ' ' + type : '');
            feedback.textContent = message || '';
        }

        function normalizePhone(raw) {
            var phone = String(raw || '').replace(/\D+/g, '');
            if (fixedPrefix && phone && phone.indexOf(fixedPrefix) !== 0) {
                phone = fixedPrefix + phone;
            }
            return phone;
        }

        function saveSession() {
            var payload = {
                session_id: state.session_id,
                state: {
                    current_step: state.current_step,
                    category: state.category,
                    country_iso: state.country_iso,
                    operator: state.operator,
                    product: state.product,
                    account_number: state.account_number,
                },
                context: {
                    entry_mode: entryMode,
                    fixed_prefix: fixedPrefix,
                },
            };

            return request('/wizard/session', {
                method: 'POST',
                body: JSON.stringify(payload),
            }).then(function (res) {
                state.session_id = String(res.result.session_id || state.session_id);
                try {
                    localStorage.setItem(storageKey, state.session_id);
                } catch (e) {
                    /* ignore storage errors */
                }
            });
        }

        function recoverSession() {
            var existingId = '';
            try {
                existingId = String(localStorage.getItem(storageKey) || '');
            } catch (e) {
                existingId = '';
            }

            if (!existingId) {
                return Promise.resolve();
            }

            return request('/wizard/session/' + encodeURIComponent(existingId), {
                method: 'GET',
            }).then(function (res) {
                if (!res.result || !res.result.state) {
                    return;
                }

                state.session_id = String(res.result.session_id || existingId);
                state.current_step = String(res.result.state.current_step || state.current_step);
                state.category = String(res.result.state.category || state.category);
                state.country_iso = String(res.result.state.country_iso || state.country_iso);
                state.operator = String(res.result.state.operator || state.operator);
                state.product = res.result.state.product || state.product;
                state.account_number = String(res.result.state.account_number || state.account_number);
            }).catch(function () {
                try {
                    localStorage.removeItem(storageKey);
                } catch (e) {
                    /* ignore storage errors */
                }
            });
        }

        function fetchOffers() {
            var query = [];
            query.push('entry_mode=' + encodeURIComponent(entryMode));
            if (state.account_number) {
                query.push('account_number=' + encodeURIComponent(state.account_number));
            }
            if (state.country_iso) {
                query.push('country_iso=' + encodeURIComponent(state.country_iso));
            }
            if (state.category) {
                query.push('category=' + encodeURIComponent(state.category));
            }
            if (fixedPrefix) {
                query.push('fixed_prefix=' + encodeURIComponent(fixedPrefix));
            }

            state.loading = true;
            render();

            return request('/wizard/offers?' + query.join('&'), {
                method: 'GET',
            }).then(function (res) {
                state.offers = (res.result && res.result.offers) ? res.result.offers : [];
                if (!state.country_iso && res.result && res.result.country_iso) {
                    state.country_iso = String(res.result.country_iso || '').toUpperCase();
                }
                state.loading = false;
                if (!state.offers.length) {
                    setFeedback(DC_WIZARD_DATA.texts.empty, 'warning');
                } else {
                    setFeedback('', '');
                }
                return res.result || {};
            }).catch(function (err) {
                state.loading = false;
                state.offers = [];
                setFeedback(err.message || DC_WIZARD_DATA.texts.error, 'error');
                return Promise.reject(err);
            });
        }

        function selectedProductSummary() {
            if (!state.product) {
                return '';
            }

            return [
                '<li><strong>Categoría:</strong> ' + (state.category || 'N/A') + '</li>',
                '<li><strong>País:</strong> ' + (state.country_iso || 'N/A') + '</li>',
                '<li><strong>Operador:</strong> ' + (state.operator || 'N/A') + '</li>',
                '<li><strong>Producto:</strong> ' + (state.product.label || state.product.sku_code || 'N/A') + '</li>',
                '<li><strong>Importe:</strong> ' + Number(state.product.send_value || 0).toFixed(2) + ' ' + (state.product.send_currency_iso || '') + '</li>',
                '<li><strong>Número:</strong> ' + (state.account_number || 'N/A') + '</li>',
            ].join('');
        }

        function markSteps() {
            stepItems.forEach(function (item) {
                var step = item.getAttribute('data-step');
                var stepIndex = STEPS.indexOf(step);
                var currentIndex = STEPS.indexOf(state.current_step);
                item.classList.toggle('is-current', stepIndex === currentIndex);
                item.classList.toggle('is-done', stepIndex < currentIndex);
            });
        }

        function renderCategoryStep() {
            var lockedText = presetCategory ? '<p class="dc-wizard-note">Categoría predefinida para esta landing.</p>' : '';
            var recargasActive = state.category === 'recargas' ? ' is-active' : '';
            var giftActive = state.category === 'gift_cards' ? ' is-active' : '';
            var disabledAttr = presetCategory ? ' disabled' : '';

            panel.innerHTML = '' +
                '<section class="dc-wizard-step">' +
                '<h3>Selecciona categoría</h3>' +
                '<div class="dc-wizard-grid">' +
                '<button type="button" class="dc-choice-btn' + recargasActive + '" data-choice-category="recargas"' + disabledAttr + '>Recargas</button>' +
                '<button type="button" class="dc-choice-btn' + giftActive + '" data-choice-category="gift_cards"' + disabledAttr + '>Gift Cards</button>' +
                '</div>' +
                lockedText +
                '</section>';
        }

        function renderCountryStep() {
            var countryLocked = entryMode === 'country_fixed' && !!presetCountry;
            var countrySelect = toCountryOptions(DC_WIZARD_DATA.countries || [], state.country_iso);
            var countryDisabled = countryLocked ? ' disabled' : '';
            var phoneValue = state.account_number ? state.account_number : '';
            var countryLabel = entryMode === 'number_first' ? 'País (opcional, lo detectamos automático)' : 'País';

            panel.innerHTML = '' +
                '<section class="dc-wizard-step">' +
                '<h3>Datos del destinatario</h3>' +
                '<label class="dc-field-label">' + countryLabel + '</label>' +
                '<select class="dc-field-input" data-field-country' + countryDisabled + '>' + countrySelect + '</select>' +
                '<label class="dc-field-label">Número móvil</label>' +
                '<input type="tel" class="dc-field-input" data-field-phone value="' + phoneValue + '" placeholder="Ingresa número" />' +
                (entryMode === 'number_first' ? '<p class="dc-wizard-note">Si no eliges país, el wizard intentará detectarlo con el número.</p>' : '') +
                (fixedPrefix ? '<p class="dc-wizard-note">Prefijo fijo aplicado: +' + fixedPrefix + '</p>' : '') +
                '</section>';
        }

        function renderOperatorStep() {
            var operators = {};
            (state.offers || []).forEach(function (offer) {
                if (!offer || !offer.provider_name) {
                    return;
                }
                operators[offer.provider_name] = true;
            });

            var names = Object.keys(operators).sort();
            var html = ['<section class="dc-wizard-step"><h3>Elige operador</h3><div class="dc-wizard-grid">'];

            if (!names.length) {
                if (entryMode === 'number_first' && !state.country_iso) {
                    html.push('<p class="dc-wizard-note">No pudimos detectar el país automáticamente. Usá el botón <strong>Atrás</strong> y seleccioná el país manualmente.</p>');
                } else {
                    html.push('<p class="dc-wizard-note">No hay operadores disponibles para este destino. Probá con otro país.</p>');
                }
            } else {
                names.forEach(function (name) {
                    var activeClass = state.operator === name ? ' is-active' : '';
                    html.push('<button type="button" class="dc-choice-btn' + activeClass + '" data-choice-operator="' + name + '">' + name + '</button>');
                });
            }

            html.push('</div></section>');
            panel.innerHTML = html.join('');
        }

        function renderProductStep() {
            var offers = (state.offers || []).filter(function (offer) {
                return state.operator && offer.provider_name === state.operator;
            });

            var html = ['<section class="dc-wizard-step"><h3>Selecciona producto</h3><div class="dc-product-list">'];

            if (!offers.length) {
                html.push('<p class="dc-wizard-note">No hay productos para el operador seleccionado.</p>');
            } else {
                offers.forEach(function (offer, pos) {
                    var selected = state.product && state.product.sku_code === offer.sku_code ? ' is-selected' : '';
                    html.push('' +
                        '<button type="button" class="dc-product-item' + selected + '" data-choice-product="' + pos + '">' +
                        '<span class="dc-product-title">' + (offer.label || offer.sku_code) + '</span>' +
                        '<span class="dc-product-desc">' + (offer.description || '') + '</span>' +
                        '<span class="dc-product-price">' + Number(offer.send_value || 0).toFixed(2) + ' ' + (offer.send_currency_iso || '') + '</span>' +
                        '</button>');
                });
            }

            html.push('</div></section>');
            panel.innerHTML = html.join('');
        }

        function renderReviewStep() {
            panel.innerHTML = '' +
                '<section class="dc-wizard-step">' +
                '<h3>Revisa tu selección</h3>' +
                '<ul class="dc-review-list">' + selectedProductSummary() + '</ul>' +
                '<p class="dc-wizard-note">El siguiente paso real de checkout se conecta en la fase de pago del proyecto.</p>' +
                '</section>';
        }

        function renderLoading() {
            panel.innerHTML = '<section class="dc-wizard-step"><p class="dc-wizard-note">' + DC_WIZARD_DATA.texts.loading + '</p></section>';
        }

        function render() {
            markSteps();

            if (state.loading) {
                renderLoading();
            } else if (state.current_step === 'category') {
                renderCategoryStep();
            } else if (state.current_step === 'country') {
                renderCountryStep();
            } else if (state.current_step === 'operator') {
                renderOperatorStep();
            } else if (state.current_step === 'product') {
                renderProductStep();
            } else {
                renderReviewStep();
            }

            btnBack.hidden = STEPS.indexOf(state.current_step) <= 0;
            btnNext.textContent = state.current_step === 'review' ? DC_WIZARD_DATA.texts.continueCheckout : DC_WIZARD_DATA.texts.next;
        }

        function nextStep() {
            var currentIndex = STEPS.indexOf(state.current_step);
            if (currentIndex === -1) {
                state.current_step = 'category';
                render();
                return;
            }

            if (state.current_step === 'category' && !state.category) {
                setFeedback('Selecciona una categoría para continuar.', 'error');
                return;
            }

            if (state.current_step === 'country') {
                var phoneNode = panel.querySelector('[data-field-phone]');
                var countryNode = panel.querySelector('[data-field-country]');
                var requiresCountry = entryMode === 'country_fixed';

                if (countryNode) {
                    state.country_iso = String(countryNode.value || state.country_iso || '').toUpperCase();
                }
                if (phoneNode) {
                    state.account_number = normalizePhone(phoneNode.value);
                }

                if (requiresCountry && !state.country_iso) {
                    setFeedback('Debes seleccionar un país.', 'error');
                    return;
                }

                if (!state.account_number || state.account_number.length < 8) {
                    setFeedback('Ingresa un número válido para continuar.', 'error');
                    return;
                }

                fetchOffers().then(function () {
                    state.current_step = 'operator';
                    saveSession();
                    render();
                });
                return;
            }

            if (state.current_step === 'operator' && !state.operator) {
                setFeedback('Selecciona un operador para continuar.', 'error');
                return;
            }

            if (state.current_step === 'product' && !state.product) {
                setFeedback('Selecciona un producto para continuar.', 'error');
                return;
            }

            if (state.current_step === 'review') {
                if (!state.product || !state.account_number) {
                    setFeedback('Faltan datos para continuar al checkout.', 'error');
                    return;
                }
                setFeedback('Agregando al carrito...', 'info');
                btnNext.disabled = true;
                request('/add-to-cart', {
                    method: 'POST',
                    body: JSON.stringify({
                        account_number: state.account_number,
                        country_iso: state.country_iso,
                        sku_code: state.product.sku_code || '',
                        send_value: state.product.send_value || 0,
                        send_currency_iso: state.product.send_currency_iso || 'EUR',
                        provider_name: state.operator || '',
                        bundle_label: state.product.label || state.product.sku_code || '',
                    }),
                }).then(function (res) {
                    setFeedback('', '');
                    window.location.href = res.redirect || DC_WIZARD_DATA.checkoutUrl || '/checkout/';
                }).catch(function (err) {
                    setFeedback(err.message || DC_WIZARD_DATA.texts.error, 'error');
                    btnNext.disabled = false;
                });
                return;
            }

            state.current_step = STEPS[currentIndex + 1] || 'review';
            saveSession();
            render();
        }

        function prevStep() {
            var currentIndex = STEPS.indexOf(state.current_step);
            if (currentIndex <= 0) {
                return;
            }

            state.current_step = STEPS[currentIndex - 1];
            saveSession();
            render();
        }

        panel.addEventListener('click', function (evt) {
            var target = evt.target;
            if (!target) {
                return;
            }

            var categoryNode = target.closest('[data-choice-category]');
            if (categoryNode) {
                state.category = String(categoryNode.getAttribute('data-choice-category') || '');
                setFeedback('', '');
                saveSession();
                render();
                return;
            }

            var operatorNode = target.closest('[data-choice-operator]');
            if (operatorNode) {
                state.operator = String(operatorNode.getAttribute('data-choice-operator') || '');
                state.product = null;
                setFeedback('', '');
                saveSession();
                render();
                return;
            }

            var productNode = target.closest('[data-choice-product]');
            if (productNode) {
                var pos = Number(productNode.getAttribute('data-choice-product') || -1);
                var offers = (state.offers || []).filter(function (offer) {
                    return state.operator && offer.provider_name === state.operator;
                });
                if (pos >= 0 && pos < offers.length) {
                    state.product = offers[pos];
                    setFeedback('', '');
                    saveSession();
                    render();
                }
            }
        });

        btnNext.addEventListener('click', nextStep);
        btnBack.addEventListener('click', prevStep);

        recoverSession().finally(function () {
            if (presetCategory && !state.category) {
                state.category = presetCategory;
            }
            if (presetCountry && !state.country_iso) {
                state.country_iso = presetCountry;
            }

            saveSession().finally(function () {
                render();
            });
        });
    }

    Array.prototype.forEach.call(wizardNodes, function (node, idx) {
        mountWizard(node, idx + 1);
    });
})();
