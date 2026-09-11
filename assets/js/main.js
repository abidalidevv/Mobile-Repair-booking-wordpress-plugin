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

    function renderRepairIconHtml(item) {
        let icon = (item.icon || '').trim();
        const fallbackEmoji = '🛠️';

        // Auto-match repair name to authentic Brand repair icons if missing, empty, or generic
        if (!icon || icon === '🛠️' || icon === '⚙️') {
            const nameLower = (item.name || '').toLowerCase();
            if (nameLower.includes('screen') || nameLower.includes('display') || nameLower.includes('glass') || nameLower.includes('lcd')) {
                icon = 'Brands/repair_Icons/broken.png';
            } else if (nameLower.includes('battery')) {
                icon = 'Brands/repair_Icons/battry.png';
            } else if (nameLower.includes('port') || nameLower.includes('charging')) {
                icon = 'Brands/repair_Icons/port_issue.png';
            } else if (nameLower.includes('camera') || nameLower.includes('lens')) {
                icon = 'Brands/repair_Icons/camera.png';
            } else if (nameLower.includes('speaker') || nameLower.includes('audio') || nameLower.includes('sound')) {
                icon = 'Brands/repair_Icons/speaker.png';
            } else if (nameLower.includes('microphone') || nameLower.includes('mic')) {
                icon = 'Brands/repair_Icons/connectivity.png';
            } else if (nameLower.includes('back glass') || nameLower.includes('back cover')) {
                icon = 'Brands/repair_Icons/back_damaged.png';
            } else if (nameLower.includes('frame') || nameLower.includes('housing') || nameLower.includes('body')) {
                icon = 'Brands/repair_Icons/frame_damaged.png';
            } else if (nameLower.includes('water') || nameLower.includes('liquid')) {
                icon = 'Brands/repair_Icons/waterdaamage.png';
            } else if (nameLower.includes('software') || nameLower.includes('slow') || nameLower.includes('os')) {
                icon = 'Brands/repair_Icons/slow.png';
            } else if (nameLower.includes('diagnos') || nameLower.includes('checkup') || nameLower.includes('inspect')) {
                icon = 'Brands/repair_Icons/Checkup.png';
            } else if (nameLower.includes('lock') || nameLower.includes('unlock')) {
                icon = 'Brands/repair_Icons/locked.png';
            } else if (nameLower.includes('data') || nameLower.includes('recovery')) {
                icon = 'Brands/repair_Icons/data_recovery.png';
            } else if (nameLower.includes('power') || nameLower.includes('button')) {
                icon = 'Brands/repair_Icons/power.png';
            } else if (nameLower.includes('motherboard') || nameLower.includes('chip') || nameLower.includes('ic')) {
                icon = 'Brands/repair_Icons/hardware.png';
            } else {
                icon = 'Brands/repair_Icons/hardware.png';
            }
        }

        // If it's a file path or URL
        if (icon.match(/\.(png|jpg|jpeg|webp|svg)(\?.*)?$/i) || icon.includes('/')) {
            let fullUrl = icon;
            if (!icon.startsWith('http://') && !icon.startsWith('https://') && !icon.startsWith('//')) {
                fullUrl = (rbfData.plugin_url || '') + icon.replace(/^\//, '');
            }
            return `<img src="${fullUrl}" class="rbf-repair-img-icon" alt="${item.name}" loading="lazy" onerror="this.onerror=null; this.parentElement.innerHTML='🛠️';">`;
        }

        // Fallback to emoji or text icon
        return `<span class="rbf-repair-emoji-icon">${icon || fallbackEmoji}</span>`;
    }

    function renderRepairs() {
        const $list = $('#repair-list');
        $list.empty();

        if (!state.allRepairs || state.allRepairs.length === 0) {
            $list.html('<p style="padding: 20px; color: #64748b; grid-column: 1 / -1; text-align: center;">No repair services found.</p>');
            return;
        }

        const filtered = state.allRepairs.filter(function(r) {
            if (state.activeCategory === 'all') return true;
            return categorizeService(r.name) === state.activeCategory;
        });

        if (filtered.length === 0) {
            $list.html('<p style="padding: 20px; color: #64748b; grid-column: 1 / -1; text-align: center;">No services found in this category for this model.</p>');
            return;
        }

        filtered.forEach(function(item) {
            const isSelected = state.cart.some(c => c.id === item.id);
            const formattedPrice = formatPrice(item.price);
            const iconHtml = renderRepairIconHtml(item);
            
            const itemHtml = `
                <div class="rbf-repair-item ${isSelected ? 'selected' : ''}" data-id="${item.id}" tabindex="0" role="button" aria-pressed="${isSelected}">
                    <div class="rbf-repair-checkbox ${isSelected ? 'checked' : ''}">
                        ${isSelected ? '✓' : ''}
                    </div>
                    <div class="rbf-repair-icon">
                        ${iconHtml}
                    </div>
                    <div class="rbf-repair-info">
                        <h4 title="${item.name}">${item.name}</h4>
                        <p>${item.description || 'OEM grade replacement part with warranty'}</p>
                    </div>
                    <div class="rbf-repair-footer">
                        <span class="rbf-repair-duration">⏱️ ${item.duration || '01-02 Hours'}</span>
                        <div class="rbf-repair-price">${formattedPrice}</div>
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

                        // Save completed booking state for print receipt
                        const serviceTypeLabel = $('input[name="service_type"]:checked').closest('.rbf-radio-label').find('strong').text().trim() || 'Pickup & Delivery';
                        state.completedBooking = {
                            bookingId: bookingId,
                            name: name,
                            phone: fullPhone,
                            email: email,
                            brand: state.selectedBrand,
                            model: state.selectedModel,
                            imei: $('#device-imei').val().trim(),
                            serviceType: serviceTypeLabel,
                            serviceDate: $('#service-date').val() || '',
                            serviceTime: $('#service-time option:selected').text() || '',
                            address: [$('#address').val(), $('#street-building').val(), $('#emirate').val()].filter(Boolean).join(', '),
                            repairs: state.cart.slice(),
                            subtotal: subtotalAed,
                            vat: vatAed,
                            total: totalAed,
                            formattedSubtotal: formatPrice(subtotalAed),
                            formattedVat: formatPrice(vatAed),
                            formattedTotal: formatPrice(totalAed),
                            dateFormatted: new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
                        };

                        // Confirmation details table
                        const detailsHtml = `
                            <div class="rbf-booking-id-card" style="text-align: left; margin-top: 20px;">
                                <p><strong>Customer:</strong> ${name}</p>
                                <p><strong>Phone:</strong> ${fullPhone}</p>
                                <p><strong>Device:</strong> ${state.selectedBrand} - ${state.selectedModel}</p>
                                <p><strong>Repairs:</strong> ${state.cart.map(c => c.name).join(', ')}</p>
                                <p><strong>Total Cost:</strong> ${formatPrice(totalAed)} (VAT incl.)</p>
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
                error: function(xhr, status, error) {
                    showLoading(false);
                    $('#rbf-submit-btn').prop('disabled', false).text('🔒 Confirm & Book Repair');
                    let errMsg = 'Network error while processing booking. Please check your connection.';
                    if (xhr.responseJSON && xhr.responseJSON.data) {
                        errMsg = xhr.responseJSON.data;
                    } else if (xhr.responseText) {
                        try {
                            const parsed = JSON.parse(xhr.responseText);
                            if (parsed.data) errMsg = parsed.data;
                        } catch(e) {
                            console.error('RBF Server Response:', xhr.responseText);
                        }
                    }
                    alert(errMsg);
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

    /**
     * Dedicated Print Receipt Function
     * Prints clean confirmation receipt WITHOUT website header/menus/footer
     */
        window.rbfPrintReceipt = function() {
        const b = state.completedBooking || {
            bookingId: $('#booking-id').text().trim() || 'eFIX-BOOKING',
            name: $('#customer-name').val() || 'Customer',
            phone: ($('#country-code').val() || '+971') + ' ' + ($('#customer-phone').val() || ''),
            email: $('#customer-email').val() || '',
            brand: state.selectedBrand || 'Device',
            model: state.selectedModel || '',
            imei: $('#device-imei').val() || '',
            serviceType: $('input[name="service_type"]:checked').closest('.rbf-radio-label').find('strong').text().trim() || 'Service Center',
            serviceDate: $('#service-date').val() || '',
            serviceTime: $('#service-time option:selected').text() || '',
            address: [$('#address').val(), $('#street-building').val(), $('#emirate').val()].filter(Boolean).join(', '),
            repairs: state.cart.slice(),
            formattedSubtotal: $('#cart-subtotal').text() || 'AED 0.00',
            formattedVat: $('#cart-vat').text() || 'AED 0.00',
            formattedTotal: $('#cart-total-amount').text() || 'AED 0.00',
            dateFormatted: new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
        };

        const siteName = rbfData.site_name || 'eFix Repair Services';
        const sitePhone = rbfData.site_phone || '+971 50 123 4567';
        const siteAddress = rbfData.site_address || 'Dubai, United Arab Emirates';
        const vatNumber = rbfData.vat_number || 'VAT No: 123456789012345';

        let repairsRowsHtml = '';
        if (b.repairs && b.repairs.length > 0) {
            b.repairs.forEach(function(r) {
                repairsRowsHtml += `
                    <tr>
                        <td style="padding: 5px 8px; border-bottom: 1px solid #e2e8f0;">
                            <strong>${r.name}</strong>
                        </td>
                        <td style="padding: 5px 8px; border-bottom: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 10px;">
                            ${r.duration || '01-02 Hours'}
                        </td>
                        <td style="padding: 5px 8px; border-bottom: 1px solid #e2e8f0; text-align: right; font-weight: 700; color: #017c36;">
                            ${formatPrice(r.price)}
                        </td>
                    </tr>
                `;
            });
        } else {
            repairsRowsHtml = `
                <tr>
                    <td style="padding: 5px 8px; border-bottom: 1px solid #e2e8f0;">
                        <strong>Device Repair Services</strong>
                    </td>
                    <td style="padding: 5px 8px; border-bottom: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 10px;">
                        01-02 Hours
                    </td>
                    <td style="padding: 5px 8px; border-bottom: 1px solid #e2e8f0; text-align: right; font-weight: 700; color: #017c36;">
                        ${b.formattedTotal}
                    </td>
                </tr>
            `;
        }

        const receiptHtml = `<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Repair Receipt - ${b.bookingId}</title>
    <style>
        @page { 
            size: A4 portrait; 
            margin: 8mm 10mm; 
        }
        * { 
            box-sizing: border-box; 
            -webkit-print-color-adjust: exact !important; 
            print-color-adjust: exact !important; 
        }
        html, body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; 
            color: #0f172a; 
            background: #ffffff; 
            margin: 0; 
            padding: 0; 
            font-size: 11px; 
            line-height: 1.35; 
        }
        .receipt-box { 
            max-width: 650px; 
            margin: 0 auto; 
            border: 1px solid #cbd5e1; 
            border-radius: 8px; 
            padding: 12px 18px; 
            background: #ffffff;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
        }
        .receipt-header { 
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #017c36; 
            padding-bottom: 6px; 
            margin-bottom: 8px; 
        }
        .store-brand { 
            font-size: 18px; 
            font-weight: 800; 
            color: #017c36; 
            margin: 0 0 2px; 
            letter-spacing: -0.3px;
        }
        .store-meta { 
            font-size: 9.5px; 
            color: #64748b; 
            margin: 1px 0; 
        }
        .header-right {
            text-align: right;
        }

        .status-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 6px 12px;
            margin-bottom: 8px;
        }
        .status-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .status-check { 
            width: 24px; 
            height: 24px; 
            border-radius: 50%; 
            background: #017c36; 
            color: #ffffff; 
            font-size: 13px; 
            font-weight: bold; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            flex-shrink: 0;
        }
        .confirmed-title { 
            font-size: 12.5px; 
            font-weight: 800; 
            color: #065f46; 
            margin: 0; 
        }
        .confirmed-subtitle { 
            font-size: 9.5px; 
            color: #047857; 
            margin: 1px 0 0; 
        }
        .tracking-card { 
            background: #ffffff;
            border: 1.5px dashed #017c36; 
            border-radius: 6px; 
            padding: 4px 10px; 
            text-align: right; 
        }
        .tracking-label { 
            font-size: 8.5px; 
            font-weight: 700; 
            color: #64748b; 
            letter-spacing: 1px; 
            text-transform: uppercase; 
            display: block;
        }
        .tracking-val { 
            font-size: 15px; 
            font-weight: 800; 
            color: #017c36; 
            letter-spacing: 0.5px; 
        }

        .details-grid { 
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px 14px;
            background: #f8fafc; 
            border: 1px solid #e2e8f0; 
            border-radius: 6px; 
            padding: 8px 12px; 
            margin-bottom: 8px; 
        }
        .detail-row { 
            display: flex; 
            font-size: 11px; 
            line-height: 1.35; 
        }
        .detail-lbl { 
            font-weight: 700; 
            color: #475569; 
            width: 76px; 
            flex-shrink: 0; 
        }
        .detail-val { 
            color: #0f172a; 
            font-weight: 500; 
            word-break: break-word; 
        }

        .table-wrap { 
            margin-bottom: 6px; 
        }
        .table-wrap table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        .table-wrap th { 
            background: #f1f5f9; 
            color: #334155; 
            font-size: 10.5px; 
            font-weight: 700; 
            text-align: left; 
            padding: 5px 8px; 
            border-bottom: 1.5px solid #cbd5e1; 
        }
        .table-wrap td {
            padding: 5px 8px; 
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
            line-height: 1.3;
        }

        .totals-section { 
            display: flex;
            justify-content: flex-end;
            margin-bottom: 8px;
        }
        .totals-box { 
            width: 230px;
            background: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 10px;
        }
        .total-row { 
            display: flex; 
            justify-content: space-between; 
            font-size: 10.5px; 
            color: #475569; 
            margin-bottom: 2px; 
        }
        .total-grand { 
            font-size: 13px; 
            font-weight: 800; 
            color: #017c36; 
            padding-top: 4px; 
            border-top: 1px dashed #017c36; 
            margin-top: 3px; 
        }

        .guarantee-pills { 
            display: flex; 
            justify-content: space-around; 
            background: #f8fafc; 
            border: 1px solid #e2e8f0; 
            border-radius: 6px; 
            padding: 5px 8px; 
            margin-bottom: 8px; 
            font-size: 10px; 
            font-weight: 600; 
            color: #334155; 
        }
        .receipt-footer { 
            text-align: center; 
            border-top: 1px solid #e2e8f0; 
            padding-top: 6px; 
            color: #64748b; 
            font-size: 9.5px; 
            line-height: 1.3; 
        }
    </style>
</head>
<body>
    <div class="receipt-box">
        <div class="receipt-header">
            <div>
                <div class="store-brand">${siteName}</div>
                <p class="store-meta">${siteAddress} • Tel: ${sitePhone}</p>
            </div>
            <div class="header-right">
                <p class="store-meta"><strong>Date:</strong> ${b.dateFormatted}</p>
                <p class="store-meta">${vatNumber}</p>
            </div>
        </div>

        <div class="status-banner">
            <div class="status-left">
                <span class="status-check">✓</span>
                <div>
                    <div class="confirmed-title">Repair Booking Confirmed</div>
                    <div class="confirmed-subtitle">Technician assigned & repair slot reserved</div>
                </div>
            </div>
            <div class="tracking-card">
                <span class="tracking-label">TRACKING ID</span>
                <span class="tracking-val">${b.bookingId}</span>
            </div>
        </div>

        <div class="details-grid">
            <div class="detail-row"><span class="detail-lbl">Customer:</span><span class="detail-val">${b.name}</span></div>
            <div class="detail-row"><span class="detail-lbl">Device:</span><span class="detail-val">${b.brand} - ${b.model}</span></div>
            <div class="detail-row"><span class="detail-lbl">Phone:</span><span class="detail-val">${b.phone}</span></div>
            <div class="detail-row"><span class="detail-lbl">Service:</span><span class="detail-val">${b.serviceType}</span></div>
            ${b.email ? `<div class="detail-row"><span class="detail-lbl">Email:</span><span class="detail-val">${b.email}</span></div>` : ''}
            ${b.serviceDate ? `<div class="detail-row"><span class="detail-lbl">Slot:</span><span class="detail-val">${b.serviceDate} ${b.serviceTime}</span></div>` : ''}
            ${b.imei ? `<div class="detail-row"><span class="detail-lbl">IMEI/SN:</span><span class="detail-val">${b.imei}</span></div>` : ''}
            ${b.address ? `<div class="detail-row" style="grid-column: 1 / -1;"><span class="detail-lbl">Address:</span><span class="detail-val">${b.address}</span></div>` : ''}
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Repair Service / Item</th>
                        <th style="text-align: center; width: 110px;">Est. Time</th>
                        <th style="text-align: right; width: 110px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    ${repairsRowsHtml}
                </tbody>
            </table>
        </div>

        <div class="totals-section">
            <div class="totals-box">
                <div class="total-row"><span>Subtotal:</span><span>${b.formattedSubtotal}</span></div>
                <div class="total-row"><span>VAT (5%):</span><span>${b.formattedVat}</span></div>
                <div class="total-row total-grand"><span>Total Amount:</span><span>${b.formattedTotal}</span></div>
            </div>
        </div>

        <div class="guarantee-pills">
            <span>🛡️ Up to 12M Warranty</span>
            <span>⚡ No Fix, No Fee</span>
            <span>🔒 100% Data Safe</span>
            <span>⭐ Genuine Quality Parts</span>
        </div>

        <div class="receipt-footer">
            <p style="margin: 2px 0;"><strong>Thank you for trusting ${siteName}!</strong></p>
            <p style="margin: 2px 0;">Keep this receipt or your tracking ID <strong>#${b.bookingId}</strong> for device handover and collection.</p>
        </div>
    </div>
</body>
</html>`;

        // Render in hidden iframe for 100% clean isolation from website menus and headers
        let printIframe = document.getElementById('rbf-receipt-print-iframe');
        if (!printIframe) {
            printIframe = document.createElement('iframe');
            printIframe.id = 'rbf-receipt-print-iframe';
            printIframe.style.position = 'fixed';
            printIframe.style.right = '0';
            printIframe.style.bottom = '0';
            printIframe.style.width = '0';
            printIframe.style.height = '0';
            printIframe.style.border = '0';
            document.body.appendChild(printIframe);
        }

        try {
            const frameDoc = printIframe.contentWindow.document;
            frameDoc.open();
            frameDoc.write(receiptHtml);
            frameDoc.close();

            setTimeout(function() {
                try {
                    printIframe.contentWindow.focus();
                    printIframe.contentWindow.print();
                } catch(e) {
                    const win = window.open('', '_blank');
                    win.document.write(receiptHtml);
                    win.document.close();
                    win.focus();
                    setTimeout(() => win.print(), 350);
                }
            }, 300);
        } catch(err) {
            const win = window.open('', '_blank');
            win.document.write(receiptHtml);
            win.document.close();
            win.focus();
            setTimeout(() => win.print(), 350);
        }
    };

})(jQuery);

