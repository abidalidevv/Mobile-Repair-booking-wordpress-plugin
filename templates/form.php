<?php
/**
 * Frontend Repair Booking Form Template
 * Enterprise Edition v2.0.0
 * Fully Scoped for Theme Compatibility (Astra, Divi, Elementor, Hello, etc.)
 */
if (!defined('ABSPATH')) {
    exit;
}

// Get Currency Manager instance
$currency_mgr = class_exists('RBF_Currency') ? RBF_Currency::get_instance() : null;
$currencies = $currency_mgr ? $currency_mgr->get_currencies_data() : [
    'AED' => ['name' => 'UAE Dirham', 'symbol' => 'AED', 'rate' => 1.0],
    'SAR' => ['name' => 'Saudi Riyal', 'symbol' => 'SAR', 'rate' => 1.02],
    'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'rate' => 0.27],
];
$default_currency = $currency_mgr ? $currency_mgr->get_default_currency() : 'AED';
$vat_rate = floatval(get_option('rbf_vat_rate', 5));

// Get Brands Data
if (!class_exists('RBF_Brands_Models_Manager')) {
    require_once RBF_PLUGIN_PATH . 'includes/class-brands-models-manager.php';
}
$brands_manager = new RBF_Brands_Models_Manager();
$brands = $brands_manager->get_brands();

// Store settings
$store_address = get_option('rbf_store_address', 'Office 101, Business Bay');
$store_city = get_option('rbf_store_city', 'Dubai');
$store_emirate = get_option('rbf_store_emirate', 'Dubai');
$store_phone = get_option('rbf_store_phone', '+971 50 123 4567');
$store_whatsapp = get_option('rbf_business_whatsapp', get_option('rbf_store_whatsapp', '+971501234567'));
$store_email = get_option('rbf_store_email', get_option('admin_email'));
$store_hours = get_option('rbf_store_working_hours', 'Daily: 9:00 AM - 10:00 PM');
?>

<div id="rbf-repair-form-wrapper" class="rbf-scope">
    <div id="repair-booking-form" class="rbf-container">
        
        <!-- Header Topbar with Currency Switcher & Trust Badges -->
        <div class="rbf-top-bar">
            <div class="rbf-brand-header-badge">
                <span class="rbf-badge-pulse"></span>
                <span class="rbf-badge-text">⚡ Instant Device Repair Quote</span>
            </div>
            
            <div class="rbf-top-controls">
                <div class="rbf-currency-switcher-wrap">
                    <label for="rbf-currency-select" class="rbf-currency-label">Currency:</label>
                    <select id="rbf-currency-select" class="rbf-currency-select" aria-label="Select Currency">
                        <?php foreach ($currencies as $code => $cur): ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($default_currency, $code); ?>>
                                <?php echo esc_html($code . ' (' . $cur['symbol'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Progress Stepper -->
        <div class="rbf-progress-bar">
            <div class="rbf-progress-step active" data-step="1">
                <div class="rbf-step-number">1</div>
                <div class="rbf-step-label">Select Brand</div>
            </div>
            <div class="rbf-progress-step" data-step="2">
                <div class="rbf-step-number">2</div>
                <div class="rbf-step-label">Choose Model</div>
            </div>
            <div class="rbf-progress-step" data-step="3">
                <div class="rbf-step-number">3</div>
                <div class="rbf-step-label">Select Repairs</div>
            </div>
            <div class="rbf-progress-step" data-step="4">
                <div class="rbf-step-number">4</div>
                <div class="rbf-step-label">Book Service</div>
            </div>
        </div>

        <!-- Step 1: Brand Selection -->
        <div class="rbf-step rbf-step-1 active">
            <div class="rbf-step-header">
                <h2>Select Your Device Brand</h2>
                <p>Choose the manufacturer of your mobile or tablet device</p>
                <div class="rbf-search-box-wrapper">
                    <input type="text" id="rbf-brand-search" class="rbf-search-input" placeholder="🔍 Search brand (Apple, Samsung, Huawei, Xiaomi...)" />
                </div>
            </div>
            
            <div class="rbf-brand-grid" id="rbf-brand-grid">
                <?php foreach ($brands as $brand): 
                    $model_count = !empty($brand['models']) ? count($brand['models']) : 0;
                ?>
                <div class="rbf-brand-card" data-brand="<?php echo esc_attr($brand['name']); ?>" tabindex="0" role="button" aria-label="<?php echo esc_attr($brand['name']); ?>">
                    <div class="rbf-brand-image">
                        <img decoding="async" src="<?php echo esc_url(RBF_PLUGIN_URL . $brand['logo'] . '?v=' . (defined('RBF_VERSION') ? RBF_VERSION : '2.0.6')); ?>" alt="<?php echo esc_attr($brand['name']); ?>">
                    </div>
                    <div class="rbf-brand-name"><?php echo esc_html($brand['name']); ?></div>
                    <div class="rbf-brand-badge"><?php echo $model_count > 0 ? esc_html($model_count . ' Models') : 'Custom'; ?></div>
                    <div class="rbf-brand-desc"><?php echo esc_html($brand['description']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Step 2: Model Selection -->
        <div class="rbf-step rbf-step-2">
            <div class="rbf-step-header">
                <h2>Choose Your <span id="selected-brand" class="rbf-highlight-text">Device</span> Model</h2>
                <p>Select your exact series or model to load authentic replacement parts</p>
                
                <div class="rbf-search-box-wrapper">
                    <input type="text" id="rbf-model-search" class="rbf-search-input" placeholder="🔍 Search model name (e.g., iPhone 16 Pro Max, S24 Ultra, Mate 60, Pura 70)..." />
                </div>
            </div>
            
            <div class="rbf-step-nav-header">
                <button type="button" class="rbf-btn-back" onclick="rbfGoToStep(1)">
                    ← Change Brand
                </button>
            </div>
            
            <!-- Model Selection Grid -->
            <div class="rbf-model-grid" id="model-grid">
                <!-- Dynamically populated via AJAX/JS -->
            </div>
            
            <!-- Custom input fields for 'Others' brand -->
            <div class="rbf-custom-model-inputs" id="custom-model-inputs" style="display: none;">
                <div class="rbf-custom-box">
                    <h3>Specify Your Device</h3>
                    <p>Can't find your exact model in the list? Enter it manually below and we will inspect and quote it for you.</p>
                    <div class="rbf-form-group">
                        <label for="custom-brand">Device Brand *</label>
                        <input type="text" id="custom-brand" name="custom_brand" placeholder="e.g., Nothing Phone, Vivo, Asus ROG, Motorola">
                    </div>
                    <div class="rbf-form-group">
                        <label for="custom-model">Device Model Name *</label>
                        <input type="text" id="custom-model" name="custom_model" placeholder="e.g., Phone (2), X100 Pro, ROG 8 Pro">
                    </div>
                    <div class="rbf-form-group">
                        <button type="button" id="confirm-custom-model" class="rbf-btn-primary">Continue with this Device →</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Repair Selection -->
        <div class="rbf-step rbf-step-3">
            <div class="rbf-step-header">
                <h2>Select Required Repairs</h2>
                <p>Choose one or more repair services needed for your <span id="selected-model" class="rbf-highlight-text">device</span></p>
            </div>
            
            <div class="rbf-step-nav-header">
                <button type="button" class="rbf-btn-back" onclick="rbfGoToStep(2)">
                    ← Change Model
                </button>
            </div>

            <!-- Repair Category Filter Tabs -->
            <div class="rbf-category-filters">
                <button type="button" class="rbf-cat-btn active" data-category="all">All Services</button>
                <button type="button" class="rbf-cat-btn" data-category="screen">📱 Screen & Glass</button>
                <button type="button" class="rbf-cat-btn" data-category="battery">🔋 Battery & Charging</button>
                <button type="button" class="rbf-cat-btn" data-category="camera">📷 Camera & Lens</button>
                <button type="button" class="rbf-cat-btn" data-category="backglass">✨ Back Glass & Frame</button>
                <button type="button" class="rbf-cat-btn" data-category="motherboard">⚡ Motherboard & Chip</button>
                <button type="button" class="rbf-cat-btn" data-category="other">🛠️ Others / Water</button>
            </div>
            
            <div class="rbf-repair-container">
                <!-- Repair Items List -->
                <div class="rbf-repair-list" id="repair-list">
                    <!-- Populated dynamically via AJAX -->
                </div>
                
                <!-- Sticky Cart Sidebar -->
                <div class="rbf-cart-sidebar">
                    <!-- Device Image Card -->
                    <div class="rbf-device-display" id="device-display" style="display: none;">
                        <div class="rbf-device-card">
                            <div class="rbf-device-image">
                                <img id="selected-device-image" src="" alt="Selected Device">
                            </div>
                            <div class="rbf-device-info">
                                <h4 id="selected-device-name">Device Name</h4>
                                <p id="selected-device-model">Model</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="rbf-cart">
                        <div class="rbf-cart-header">
                            <h3>🧾 Repair Summary</h3>
                            <span class="rbf-currency-badge" id="cart-currency-badge"><?php echo esc_html($default_currency); ?></span>
                        </div>
                        
                        <div class="rbf-cart-items" id="cart-items">
                            <div class="rbf-cart-empty">No repair services selected yet. Click any service on the left to add.</div>
                        </div>
                        
                        <div class="rbf-cart-total" id="cart-total">
                            <div class="rbf-total-line">
                                <span>Subtotal:</span>
                                <span id="cart-subtotal">AED 0.00</span>
                            </div>
                            <div class="rbf-total-line">
                                <span>VAT (<?php echo esc_html($vat_rate); ?>%):</span>
                                <span id="cart-vat">AED 0.00</span>
                            </div>
                            <div class="rbf-total-line rbf-total-main">
                                <span>Estimated Total:</span>
                                <span id="cart-total-amount">AED 0.00</span>
                            </div>
                        </div>
                        
                        <div class="rbf-cart-actions">
                            <button type="button" class="rbf-btn-primary rbf-btn-proceed" onclick="rbfGoToStep(4)">
                                Proceed to Booking →
                            </button>
                            <button type="button" class="rbf-btn-secondary rbf-btn-clear-cart" onclick="clearCart()">
                                Clear Selection
                            </button>
                        </div>
                        
                        <div class="rbf-cart-guarantees">
                            <div class="rbf-guarantee-item">🛡️ <strong>Up to 12 Months Warranty</strong></div>
                            <div class="rbf-guarantee-item">⚡ <strong>No Fix, No Fee Policy</strong></div>
                            <div class="rbf-guarantee-item">🔒 <strong>100% Data Privacy Safe</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Customer Details & Booking Form -->
        <div class="rbf-step rbf-step-4">
            <div class="rbf-step-header">
                <h2>Complete Your Repair Booking</h2>
                <p>Provide contact details and choose your preferred service pickup/visit method</p>
            </div>
            
            <div class="rbf-step-nav-header">
                <button type="button" class="rbf-btn-back" onclick="rbfGoToStep(3)">
                    ← Back to Repairs
                </button>
            </div>
            
            <div class="rbf-booking-container">
                <!-- Left Form Section -->
                <div class="rbf-booking-main">
                    <div class="rbf-booking-form">
                        <form id="booking-form" novalidate>
                            
                            <div class="rbf-form-section-title">👤 Customer Information</div>
                            
                            <div class="rbf-form-row">
                                <div class="rbf-form-group">
                                    <label for="customer-name">Full Name *</label>
                                    <input type="text" id="customer-name" name="name" placeholder="e.g., Mohammed Al-Mansoor" required>
                                </div>
                                
                                <div class="rbf-form-group">
                                    <label for="customer-email">Email Address *</label>
                                    <input type="email" id="customer-email" name="email" placeholder="e.g., client@example.com" required>
                                </div>
                            </div>
                            
                            <div class="rbf-form-row">
                                <div class="rbf-form-group">
                                    <label for="customer-phone">WhatsApp / Mobile Number *</label>
                                    <div class="rbf-phone-input-wrap">
                                        <select id="country-code" class="rbf-country-select">
                                            <option value="+971" selected>🇦🇪 +971 (UAE)</option>
                                            <option value="+966">🇸🇦 +966 (KSA)</option>
                                            <option value="+968">🇴🇲 +968 (Oman)</option>
                                            <option value="+973">🇧🇭 +973 (Bahrain)</option>
                                            <option value="+965">🇰🇼 +965 (Kuwait)</option>
                                            <option value="+974">🇶🇦 +974 (Qatar)</option>
                                            <option value="+1">🇺🇸 +1 (US/CA)</option>
                                            <option value="+44">🇬🇧 +44 (UK)</option>
                                            <option value="+92">🇵🇰 +92 (PK)</option>
                                            <option value="+91">🇮🇳 +91 (IN)</option>
                                        </select>
                                        <input type="tel" id="customer-phone" name="phone" placeholder="50 123 4567" required>
                                    </div>
                                    <small class="rbf-field-hint">We send instant repair tracking updates to this WhatsApp number.</small>
                                </div>

                                <div class="rbf-form-group">
                                    <label for="device-imei">Device IMEI / Serial Number (Optional)</label>
                                    <input type="text" id="device-imei" name="imei" maxlength="30" placeholder="Dial *#06# to get 15-digit IMEI">
                                    <small class="rbf-field-hint">Helps ensure 100% exact part matching and warranty tracking.</small>
                                </div>
                            </div>
                            
                            <!-- Service Method Selection -->
                            <div class="rbf-form-group">
                                <label class="rbf-form-label">Choose Service Method *</label>
                                <div class="rbf-radio-group">
                                    <label class="rbf-radio-label active">
                                        <input type="radio" name="service_type" value="pickup" checked required>
                                        <span class="rbf-radio-custom"></span>
                                        <strong>🚚 Free Doorstep Pickup</strong>
                                        <small>We pick up & return your device</small>
                                    </label>
                                    <label class="rbf-radio-label">
                                        <input type="radio" name="service_type" value="onsite" required>
                                        <span class="rbf-radio-custom"></span>
                                        <strong>🏠 Onsite Repair Van</strong>
                                        <small>Technician repairs at your doorstep</small>
                                    </label>
                                    <label class="rbf-radio-label">
                                        <input type="radio" name="service_type" value="store_visit" required>
                                        <span class="rbf-radio-custom"></span>
                                        <strong>🏪 Visit Our Service Center</strong>
                                        <small>Walk into our flagship store</small>
                                    </label>
                                    <label class="rbf-radio-label">
                                        <input type="radio" name="service_type" value="delivery" required>
                                        <span class="rbf-radio-custom"></span>
                                        <strong>📦 Courier Send-In</strong>
                                        <small>Ship your device via courier</small>
                                    </label>
                                </div>
                            </div>

                            <!-- Address Section (for Pickup & Onsite) -->
                            <div id="address-section" class="rbf-form-section">
                                <div class="rbf-form-section-title">📍 Service / Pickup Address</div>
                                <div class="rbf-form-group">
                                    <label for="address">Street / Building / Villa *</label>
                                    <input type="text" id="address" name="address" placeholder="e.g., Apartment 402, Marina Crown Tower" required>
                                </div>
                                <div class="rbf-form-row">
                                    <div class="rbf-form-group">
                                        <label for="street-building">Area / Neighborhood *</label>
                                        <input type="text" id="street-building" name="street_building" placeholder="e.g., Dubai Marina, Downtown, Al Barsha" required>
                                    </div>
                                    <div class="rbf-form-group">
                                        <label for="emirate">Emirate / City *</label>
                                        <select id="emirate" name="emirate" required>
                                            <option value="Dubai" selected>Dubai</option>
                                            <option value="Abu Dhabi">Abu Dhabi</option>
                                            <option value="Sharjah">Sharjah</option>
                                            <option value="Ajman">Ajman</option>
                                            <option value="Ras Al Khaimah">Ras Al Khaimah</option>
                                            <option value="Fujairah">Fujairah</option>
                                            <option value="Umm Al Quwain">Umm Al Quwain</option>
                                            <option value="Riyadh">Riyadh (KSA)</option>
                                            <option value="Jeddah">Jeddah (KSA)</option>
                                            <option value="Other">Other Region</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Date & Time Section -->
                            <div id="datetime-section" class="rbf-form-section">
                                <div class="rbf-form-section-title">📅 Preferred Time Slot</div>
                                <div class="rbf-form-row">
                                    <div class="rbf-form-group">
                                        <label for="service-date">Date *</label>
                                        <input type="date" id="service-date" name="service_date" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="rbf-form-group" id="time-field-group">
                                        <label for="service-time">Preferred Time Window *</label>
                                        <select id="service-time" name="service_time" required>
                                            <option value="">Select Time Window</option>
                                            <option value="09:00 - 12:00">🌅 Morning (09:00 AM - 12:00 PM)</option>
                                            <option value="12:00 - 15:00">☀️ Afternoon (12:00 PM - 03:00 PM)</option>
                                            <option value="15:00 - 18:00">🌆 Evening (03:00 PM - 06:00 PM)</option>
                                            <option value="18:00 - 21:00">🌙 Night (06:00 PM - 09:00 PM)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Store Visit Info (shown when store_visit is selected) -->
                            <div id="store-visit-section" class="rbf-form-section" style="display: none;">
                                <div class="rbf-form-section-title">🏪 Store Location Details</div>
                                <div class="rbf-store-info-box">
                                    <p><strong>📍 Address:</strong> <?php echo esc_html($store_address . ', ' . $store_city . ', ' . $store_emirate); ?></p>
                                    <p><strong>📞 Phone:</strong> <a href="tel:<?php echo esc_attr($store_phone); ?>"><?php echo esc_html($store_phone); ?></a></p>
                                    <p><strong>💬 WhatsApp:</strong> <a href="https://wa.me/<?php echo esc_attr(str_replace(['+', ' ', '-'], '', $store_whatsapp)); ?>"><?php echo esc_html($store_whatsapp); ?></a></p>
                                    <p><strong>⏰ Working Hours:</strong> <?php echo esc_html($store_hours); ?></p>
                                </div>
                            </div>

                            <div class="rbf-form-group">
                                <label for="customer-notes">Device Passcode / Notes for Technician (Optional)</label>
                                <textarea id="customer-notes" name="notes" rows="2" placeholder="e.g., Screen flickers when touched, passcode is 1234 (or state if device is wiped)"></textarea>
                            </div>

                            <div class="rbf-whatsapp-optin">
                                <label class="rbf-checkbox-label">
                                    <input type="checkbox" id="wa-optin" name="wa_optin" checked>
                                    <span>📲 Send instant confirmation & live technician updates on WhatsApp</span>
                                </label>
                            </div>
                            
                            <button type="submit" class="rbf-btn-primary rbf-btn-book" id="rbf-submit-btn">
                                🔒 Confirm & Book Repair
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Right Summary Sidebar -->
                <div class="rbf-booking-sidebar">
                    <!-- Selected Device Card -->
                    <div class="rbf-device-display" id="device-display-step4">
                        <div class="rbf-device-card">
                            <div class="rbf-device-image">
                                <img id="selected-device-image-step4" src="" alt="Selected Device">
                            </div>
                            <div class="rbf-device-info">
                                <h4 id="selected-device-name-step4">Device Name</h4>
                                <p id="selected-device-model-step4">Model</p>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Summary -->
                    <div class="rbf-cart">
                        <div class="rbf-cart-header">
                            <h3>🧾 Booking Summary</h3>
                            <span class="rbf-currency-badge" id="step4-currency-badge"><?php echo esc_html($default_currency); ?></span>
                        </div>
                        
                        <div class="rbf-cart-items" id="cart-items-step4">
                            <div class="rbf-cart-empty">No items selected</div>
                        </div>
                        
                        <div class="rbf-cart-total" id="cart-total-step4">
                            <div class="rbf-total-line">
                                <span>Subtotal:</span>
                                <span id="cart-subtotal-step4">AED 0.00</span>
                            </div>
                            <div class="rbf-total-line">
                                <span>VAT (<?php echo esc_html($vat_rate); ?>%):</span>
                                <span id="cart-vat-step4">AED 0.00</span>
                            </div>
                            <div class="rbf-total-line rbf-total-main">
                                <span>Total Amount:</span>
                                <span id="cart-total-amount-step4">AED 0.00</span>
                            </div>
                        </div>
                        
                        <div class="rbf-payment-note">
                            <p><strong>💡 Pay After Repair:</strong> No upfront payment is required now. You pay securely by Cash or Card only once your device is completely fixed and tested!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Message Step -->
        <div class="rbf-step rbf-step-success" style="display: none;">
            <div class="rbf-success-content">
                <div class="rbf-success-icon-wrap">
                    <div class="rbf-success-icon">✓</div>
                </div>
                <h2>🎉 Repair Booking Confirmed!</h2>
                <p>Thank you! Your repair request has been registered in our system. A technician has been assigned to your device.</p>
                
                <div class="rbf-booking-id-card">
                    <span class="rbf-booking-id-label">YOUR TRACKING ID</span>
                    <span class="rbf-booking-id-val" id="booking-id">RBF-XXXXXX</span>
                </div>

                <div class="rbf-success-actions">
                    <a href="#" id="rbf-whatsapp-chat-btn" target="_blank" class="rbf-btn-wa-direct">
                        💬 Chat with Technician on WhatsApp
                    </a>
                    <button type="button" class="rbf-btn-secondary rbf-print-receipt" onclick="rbfPrintReceipt()">
                        🧾 Print Invoice / Receipt
                    </button>
                    <button type="button" class="rbf-btn-secondary" onclick="location.reload()">
                        Book Another Device
                    </button>
                </div>

                <div class="rbf-confirmation-details" id="confirmation-details">
                    <!-- Dynamic booking summary rendered here -->
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="rbf-loading" id="rbf-loading" style="display: none;">
            <div class="rbf-spinner"></div>
            <div class="rbf-loading-text">Securing your booking...</div>
        </div>
    </div>
</div>
