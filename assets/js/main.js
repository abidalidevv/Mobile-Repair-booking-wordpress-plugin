/**
 * eFix Repair Booking Form - Enterprise Engine v2.0.0
 * Multi-Currency, Live Rates, Real-Time Filtering, WhatsApp Direct Integration
 */

(function($) {
    'use strict';

    // Global data fallback
    window.rbfData = window.rbfData || window.rbf_ajax || {};

    // Application State
    const state = {
        currentStep: 1,
        selectedBrand: '',
        selectedModel: '',
        selectedModelImage: '',
        currentCurrency: (typeof rbfData !== 'undefined' && rbfData.default_currency) ? rbfData.default_currency : 'AED',
        vatRate: (typeof rbfData !== 'undefined' && rbfData.vat_rate) ? parseFloat(rbfData.vat_rate) : 5,
        rates: (typeof rbfData !== 'undefined' && rbfData.rates) ? rbfData.rates : { AED: 1.0, SAR: 1.02, USD: 0.27 },
        currencies: (typeof rbfData !== 'undefined' && rbfData.currencies) ? rbfData.currencies : {},
        activeCategory: 'all',
        cart: [], // Array of repair items
        allModels: [],
        allRepairs: []
    };

    // Initialize Application
    $(document).ready(function() {
        initCurrencySwitcher();
        initNavigation();
        initBrandSelection();
        initModelSelection();
        initRepairSelection();
        initServiceTypeToggle();
        initBookingForm();
        initSearchFilters();
    });

    /**
     * Currency Helper Functions
     */
    function getCurrencySymbol(code) {
        if (state.currencies && state.currencies[code] && state.currencies[code].symbol) {
            return state.currencies[code].symbol;
        }
        return code;
    }

    function convertAedToCurrent(aedAmount) {
        const rate = state.rates[state.currentCurrency] || 1.0;
        return parseFloat(aedAmount) * rate;
    }

    function formatPrice(aedAmount) {
        if (!aedAmount || parseFloat(aedAmount) <= 0) {
            return 'Quote on Inspection';
        }
        const converted = convertAedToCurrent(aedAmount);
        const symbol = getCurrencySymbol(state.currentCurrency);
        return `${symbol} ${converted.toFixed(2)}`;
    }

    function initCurrencySwitcher() {
        const $select = $('#rbf-currency-select');
        if ($select.length) {
            $select.val(state.currentCurrency);
            $select.on('change', function() {
                state.currentCurrency = $(this).val();
                $('#cart-currency-badge, #step4-currency-badge').text(state.currentCurrency);
                renderRepairs();
                updateCartDisplay();
            });
        }
    }

    /**
     * Navigation & Stepper
     */
    window.rbfGoToStep = function(stepNumber) {
        if (stepNumber < 1 || stepNumber > 4) return;
        
        // Validation when proceeding forward
        if (stepNumber > 1 && !state.selectedBrand) {
            alert('Please select a device brand first.');
            return;
        }
        if (stepNumber > 2 && !state.selectedModel) {
            alert('Please choose your device model first.');
            return;
        }
        if (stepNumber > 3 && state.cart.length === 0) {
            alert('Please select at least one repair service to proceed.');
            return;
        }

        state.currentStep = stepNumber;

        // Update step containers
        $('.rbf-step').removeClass('active');
        $(`.rbf-step-${stepNumber}`).addClass('active');

        // Update progress bar
        $('.rbf-progress-step').removeClass('active completed');
        for (let i = 1; i < stepNumber; i++) {
            $(`.rbf-progress-step[data-step="${i}"]`).addClass('completed');
        }
        $(`.rbf-progress-step[data-step="${stepNumber}"]`).addClass('active');

        // Scroll into view
        const $wrapper = $('#efix-repair-form-wrapper');
        if ($wrapper.length) {
            $('html, body').animate({
                scrollTop: $wrapper.offset().top - 40
            }, 300);
        }

        if (stepNumber === 4) {
            updateStep4Summary();
        }
    };

    function initNavigation() {
        $('.rbf-progress-step').on('click', function() {
            const targetStep = parseInt($(this).data('step'), 10);
            if (targetStep < state.currentStep) {
                window.rbfGoToStep(targetStep);
            }
        });
    }

    /**
     * Search Filters
     */
    function initSearchFilters() {
        // Brand Search
        $('#rbf-brand-search').on('input', function() {
            const query = $(this).val().toLowerCase().trim();
            $('.rbf-brand-card').each(function() {
                const brand = $(this).data('brand').toString().toLowerCase();
                if (brand.includes(query)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Model Search
        $('#rbf-model-search').on('input', function() {
            const query = $(this).val().toLowerCase().trim();
            $('.rbf-model-card').each(function() {
                const model = $(this).data('model').toString().toLowerCase();
                if (model.includes(query)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
    }

    /**
     * Step 1: Brand Selection
     */
    function initBrandSelection() {
        $(document).on('click', '.rbf-brand-card', function() {
            const brand = $(this).data('brand');
            state.selectedBrand = brand;
            state.selectedModel = '';
            state.cart = [];
            
            $('.rbf-brand-card').removeClass('active');
            $(this).addClass('active');

            $('#selected-brand').text(brand);
            
            // Check for 'Others'
            if (brand === 'Others') {
                $('#model-grid').hide();
                $('#rbf-model-search').closest('.rbf-search-box-wrapper').hide();
                $('#custom-model-inputs').show();
                window.rbfGoToStep(2);
            } else {
                $('#custom-model-inputs').hide();
                $('#rbf-model-search').closest('.rbf-search-box-wrapper').show();
                $('#model-grid').show();
                fetchModelsForBrand(brand);
            }
        });

        // Confirm custom model button
        $('#confirm-custom-model').on('click', function() {
            const customBrand = $('#custom-brand').val().trim();
            const customModel = $('#custom-model').val().trim();
            if (!customBrand || !customModel) {
                alert('Please enter both device brand and model name.');
                return;
            }
            state.selectedBrand = customBrand;
            state.selectedModel = customModel;
            state.selectedModelImage = '';
            
            $('#selected-model').text(`${customBrand} ${customModel}`);
            fetchRepairsForModel(customBrand, customModel);
        });
    }

    function fetchModelsForBrand(brand) {
        showLoading(true);
        $.ajax({
            url: rbfData.ajax_url,
            type: 'POST',
            data: {
                action: 'rbf_get_models',
                brand: brand,
                nonce: rbfData.nonce
            },
            success: function(res) {
                showLoading(false);
                if (res.success && res.data) {
                    state.allModels = res.data;
                    renderModels(res.data);
                    window.rbfGoToStep(2);
                } else {
                    alert('Could not load models for this brand.');
                }
            },
            error: function() {
                showLoading(false);
                alert('Connection error while fetching models. Please try again.');
            }
        });
    }

    function renderModels(models) {
        const $grid = $('#model-grid');
        $grid.empty();

        if (!models || models.length === 0) {
            $grid.html('<p style="text-align: center; grid-column: 1/-1; padding: 30px; color: #64748b;">No pre-configured models found for this brand. Please specify custom model.</p>');
            return;
        }

        models.forEach(function(m) {
            const imgSrc = m.image ? m.image : (rbfData.plugin_url + 'Brands/other_brand.jpg');
            const cardHtml = `
                <div class="rbf-model-card" data-model="${m.name}" data-image="${imgSrc}" tabindex="0" role="button">
                    <div class="rbf-model-image">
                        <img src="${imgSrc}" alt="${m.name}" loading="lazy" />
                    </div>
                    <div class="rbf-model-name">${m.name}</div>
                    <div class="rbf-model-desc">${m.description || 'Authentic Parts Available'}</div>
                </div>
            `;
            $grid.append(cardHtml);
        });
    }

    /**
     * Step 2: Model Selection
     */
    function initModelSelection() {
        $(document).on('click', '.rbf-model-card', function() {
            const modelName = $(this).data('model');
            const modelImg = $(this).data('image');

            state.selectedModel = modelName;
            state.selectedModelImage = modelImg;
            state.cart = [];

            $('.rbf-model-card').removeClass('active');
            $(this).addClass('active');

            $('#selected-model').text(modelName);

            // Update device preview in sidebar
            if (modelImg) {
                $('#selected-device-image, #selected-device-image-step4').attr('src', modelImg);
                $('#selected-device-name, #selected-device-name-step4').text(state.selectedBrand);
                $('#selected-device-model, #selected-device-model-step4').text(modelName);
                $('#device-display, #device-display-step4').show();
            }

            fetchRepairsForModel(state.selectedBrand, modelName);
        });
    }

    function fetchRepairsForModel(brand, model) {
        showLoading(true);
        $.ajax({
            url: rbfData.ajax_url,
            type: 'POST',
            data: {
                action: 'rbf_get_repairs',
                brand: brand,
                model: model,
                nonce: rbfData.nonce
            },
            success: function(res) {
                showLoading(false);
                if (res.success && res.data) {
                    state.allRepairs = res.data;
                    renderRepairs();
                    updateCartDisplay();
                    window.rbfGoToStep(3);
                } else {
                    alert('Could not load repair services.');
                }
            },
            error: function() {
                showLoading(false);
                alert('Connection error while fetching repair prices.');
            }
        });
    }

    /**
     * Step 3: Repair Services & Cart
     */
    function initRepairSelection() {
        // Category Filters
        $('.rbf-cat-btn').on('click', function() {
            $('.rbf-cat-btn').removeClass('active');
            $(this).addClass('active');
            state.activeCategory = $(this).data('category');
            renderRepairs();
        });

        // Toggle repair item selection
        $(document).on('click', '.rbf-repair-item', function() {
            const serviceId = $(this).data('id');
            toggleRepairInCart(serviceId);
        });
    }

    function categorizeService(name) {
        const lower = name.toLowerCase();
        if (lower.includes('screen') || lower.includes('display') || lower.includes('glass') || lower.includes('lcd') || lower.includes('oled')) return 'screen';
        if (lower.includes('battery') || lower.includes('charging') || lower.includes('port') || lower.includes('power')) return 'battery';
        if (lower.includes('camera') || lower.includes('lens')) return 'camera';
        if (lower.includes('back') || lower.includes('housing') || lower.includes('frame') || lower.includes('body')) return 'backglass';
        if (lower.includes('motherboard') || lower.includes('chip') || lower.includes('logic') || lower.includes('ic') || lower.includes('water')) return 'motherboard';
        return 'other';
    }

    function renderRepairs() {
        const $list = $('#repair-list');
        $list.empty();

        if (!state.allRepairs || state.allRepairs.length === 0) {
            $list.html('<p style="padding: 20px; color: #64748b;">No repair services found.</p>');
            return;
        }

        const filtered = state.allRepairs.filter(function(r) {
            if (state.activeCategory === 'all') return true;
            return categorizeService(r.name) === state.activeCategory;
        });

        if (filtered.length === 0) {
            $list.html('<p style="padding: 20px; color: #64748b;">No services found in this category for this model.</p>');
            return;
        }

        filtered.forEach(function(item) {
            const isSelected = state.cart.some(c => c.id === item.id);
            const formattedPrice = formatPrice(item.price);
            const icon = item.icon || '🛠️';
            
            const itemHtml = `
                <div class="rbf-repair-item ${isSelected ? 'selected' : ''}" data-id="${item.id}" tabindex="0" role="button">
                    <div class="rbf-repair-left">
                        <div class="rbf-repair-icon">${icon}</div>
                        <div class="rbf-repair-info">
                            <h4>${item.name}</h4>
                            <p>${item.description || 'OEM grade replacement part with warranty'}</p>
                            <span class="rbf-repair-duration">⏱️ ${item.duration || '01-02 Hours'}</span>
                        </div>
                    </div>
                    <div class="rbf-repair-right">
                        <div class="rbf-repair-price">${formattedPrice}</div>
                        <div class="rbf-repair-checkbox">${isSelected ? '✓' : ''}</div>
                    </div>
                </div>
            `;
            $list.append(itemHtml);
        });
    }

    function toggleRepairInCart(serviceId) {
        const existingIdx = state.cart.findIndex(c => c.id === serviceId);
        if (existingIdx >= 0) {
            state.cart.splice(existingIdx, 1);
        } else {
            const service = state.allRepairs.find(r => r.id === serviceId);
            if (service) {
                state.cart.push(service);
            }
        }
        renderRepairs();
        updateCartDisplay();
    }

    window.clearCart = function() {
        state.cart = [];
        renderRepairs();
        updateCartDisplay();
    };

    function updateCartDisplay() {
        const $itemsContainer = $('#cart-items, #cart-items-step4');
        $itemsContainer.empty();

        if (state.cart.length === 0) {
            $itemsContainer.html('<div class="rbf-cart-empty">No repair services selected yet. Click any service on the left to add.</div>');
            $('#cart-subtotal, #cart-subtotal-step4').text(formatPrice(0));
            $('#cart-vat, #cart-vat-step4').text(formatPrice(0));
            $('#cart-total-amount, #cart-total-amount-step4').text(formatPrice(0));
            return;
        }

        let subtotalAed = 0;
        state.cart.forEach(function(item) {
            const price = parseFloat(item.price) || 0;
            subtotalAed += price;

            const rowHtml = `
                <div class="rbf-cart-item">
                    <span class="rbf-cart-item-name">${item.name}</span>
                    <span class="rbf-cart-item-price">${formatPrice(price)}</span>
                </div>
            `;
            $itemsContainer.append(rowHtml);
        });

        const vatAed = subtotalAed * (state.vatRate / 100.0);
        const totalAed = subtotalAed + vatAed;

        $('#cart-subtotal, #cart-subtotal-step4').text(formatPrice(subtotalAed));
        $('#cart-vat, #cart-vat-step4').text(formatPrice(vatAed));
        $('#cart-total-amount, #cart-total-amount-step4').text(formatPrice(totalAed));
    }

    function updateStep4Summary() {
        $('#step4-currency-badge').text(state.currentCurrency);
        updateCartDisplay();
    }

    /**
     * Step 4: Service Type Selection
     */
    function initServiceTypeToggle() {
        $('input[name="service_type"]').on('change', function() {
            const val = $(this).val();
            $('.rbf-radio-label').removeClass('active');
            $(this).closest('.rbf-radio-label').addClass('active');

            if (val === 'pickup' || val === 'onsite') {
                $('#address-section').slideDown(200);
                $('#datetime-section').slideDown(200);
                $('#store-visit-section').slideUp(200);
                $('#address, #street-building').prop('required', true);
            } else if (val === 'store_visit') {
                $('#address-section').slideUp(200);
                $('#datetime-section').slideDown(200);
                $('#store-visit-section').slideDown(200);
                $('#address, #street-building').prop('required', false);
            } else if (val === 'delivery') {
                $('#address-section').slideUp(200);
                $('#datetime-section').slideUp(200);
                $('#store-visit-section').slideDown(200);
                $('#address, #street-building').prop('required', false);
            }
        });
    }

    /**
     * Step 4: Form Submission
     */
    function initBookingForm() {
        $('#booking-form').on('submit', function(e) {
            e.preventDefault();

            if (state.cart.length === 0) {
                alert('Please select at least one repair service first.');
                window.rbfGoToStep(3);
                return;
            }

            const name = $('#customer-name').val().trim();
            const email = $('#customer-email').val().trim();
            const countryCode = $('#country-code').val() || '+971';
            const rawPhone = $('#customer-phone').val().trim();
            const fullPhone = `${countryCode} ${rawPhone}`;

            if (!name || !email || !rawPhone) {
                alert('Please fill in your name, email, and phone number.');
                return;
            }

            let subtotalAed = 0;
            state.cart.forEach(item => {
                subtotalAed += (parseFloat(item.price) || 0);
            });
            const vatAed = subtotalAed * (state.vatRate / 100.0);
            const totalAed = subtotalAed + vatAed;

            const payload = {
                action: 'rbf_submit_booking',
                nonce: rbfData.nonce,
                name: name,
                email: email,
                phone: fullPhone,
                selected_brand: state.selectedBrand,
                selected_model: state.selectedModel,
                imei: $('#device-imei').val().trim(),
                service_type: $('input[name="service_type"]:checked').val() || 'pickup',
                address: $('#address').val() || '',
                street_building: $('#street-building').val() || '',
                city: $('#emirate').val() || 'Dubai',
                emirate: $('#emirate').val() || 'Dubai',
                service_date: $('#service-date').val() || '',
                service_time: $('#service-time').val() || '',
                notes: $('#customer-notes').val() || '',
                currency: state.currentCurrency,
                subtotal: subtotalAed,
                vat_amount: vatAed,
                total_amount: totalAed,
                cart_items: state.cart
            };

            showLoading(true);
            $('#rbf-submit-btn').prop('disabled', true).text('Processing Booking...');

            $.ajax({
                url: rbfData.ajax_url,
                type: 'POST',
                data: payload,
                success: function(res) {
                    showLoading(false);
                    $('#rbf-submit-btn').prop('disabled', false).text('🔒 Confirm & Book Repair');

                    if (res.success && res.data) {
                        const bookingId = res.data.booking_id;
                        $('#booking-id').text(bookingId);

                        // WhatsApp Chat Button Link
                        let waUrl = res.data.whatsapp_url;
                        if (!waUrl) {
                            const bizNumber = (rbfData.business_whatsapp || '+971501234567').replace(/[^0-9]/g, '');
                            const msg = encodeURIComponent(`Hi eFix, I just booked a repair!\nBooking ID: ${bookingId}\nDevice: ${state.selectedBrand} ${state.selectedModel}\nTotal: ${formatPrice(subtotalAed)}`);
                            waUrl = `https://wa.me/${bizNumber}?text=${msg}`;
                        }
                        $('#rbf-whatsapp-chat-btn').attr('href', waUrl);

                        // Confirmation details table
                        const detailsHtml = `
                            <div class="rbf-booking-id-card" style="text-align: left; margin-top: 20px;">
                                <p><strong>Customer:</strong> ${name}</p>
                                <p><strong>Phone:</strong> ${fullPhone}</p>
                                <p><strong>Device:</strong> ${state.selectedBrand} - ${state.selectedModel}</p>
                                <p><strong>Repairs:</strong> ${state.cart.map(c => c.name).join(', ')}</p>
                                <p><strong>Total Cost:</strong> ${formatPrice(subtotalAed)} (VAT incl.)</p>
                            </div>
                        `;
                        $('#confirmation-details').html(detailsHtml);

                        // Hide all steps, show success
                        $('.rbf-step').hide();
                        $('.rbf-step-success').fadeIn(300);
                        $('.rbf-progress-bar').hide();

                    } else {
                        alert(res.data || 'Failed to submit repair booking. Please try again.');
                    }
                },
                error: function(err) {
                    showLoading(false);
                    $('#rbf-submit-btn').prop('disabled', false).text('🔒 Confirm & Book Repair');
                    alert('Network error while processing booking. Please check your connection.');
                }
            });
        });
    }

    function showLoading(show) {
        if (show) {
            $('#rbf-loading').fadeIn(150);
        } else {
            $('#rbf-loading').fadeOut(150);
        }
    }

})(jQuery);
