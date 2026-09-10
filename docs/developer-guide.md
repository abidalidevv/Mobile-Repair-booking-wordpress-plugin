# 🔧 EFIX Repair Booking Form Plugin - Developer Documentation

Comprehensive technical documentation for developers working with the EFIX Repair Booking Form plugin.

## 📋 **Table of Contents**

1. [Architecture Overview](#architecture-overview)
2. [File Structure](#file-structure)
3. [Database Schema](#database-schema)
4. [AJAX Endpoints](#ajax-endpoints)
5. [JavaScript API](#javascript-api)
6. [CSS Architecture](#css-architecture)
7. [Hooks & Filters](#hooks--filters)
8. [Customization Guide](#customization-guide)
9. [Troubleshooting](#troubleshooting)
10. [Performance Optimization](#performance-optimization)

---

## 🏗 **Architecture Overview**

### **Plugin Structure**
The plugin follows WordPress plugin architecture with a modular design:

```
Repair_Booking_Form (Main Class)
├── Admin Management
├── Frontend Form Handling
├── AJAX Handlers
├── Database Operations
└── Utility Functions
```

### **Core Components**
- **Main Class**: `Repair_Booking_Form` - Central plugin controller
- **Admin Pages**: Dashboard, Bookings, Payment Settings
- **Frontend Form**: Multi-step booking process with AJAX
- **Data Layer**: JSON-based device catalog and MySQL bookings storage

---

## 📁 **File Structure**

### **Root Directory**
```
repair-booking-form/
├── repair-booking-form.php          # Main plugin file
├── brands_models_data.json          # Device catalog data
├── README.md                        # User documentation
├── CHANGELOG.md                     # Version history
├── DEVELOPER.md                     # This file
└── uninstall.php                    # Cleanup on uninstall
```

### **Assets Directory**
```
assets/
├── css/
│   ├── admin.css                   # Admin panel styles
│   └── style.css                   # Frontend form styles
├── js/
│   └── main.js                     # Frontend JavaScript
└── images/                         # Plugin images and icons
```

### **Templates Directory**
```
templates/
└── form.php                        # Frontend form HTML template
```

---

## 🗄 **Database Schema**

### **Bookings Table**
```sql
CREATE TABLE `wp_rbf_bookings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `customer_name` varchar(255) NOT NULL,
    `customer_email` varchar(255) NOT NULL,
    `customer_phone` varchar(50) NOT NULL,
    `service_type` varchar(50) NOT NULL,
    `address` text,
    `street_building` varchar(255),
    `city` varchar(100),
    `emirate` varchar(100),
    `service_date` date,
    `service_time` time,
    `selected_brand` varchar(100) NOT NULL,
    `selected_model` varchar(100) NOT NULL,
    `cart_items` longtext NOT NULL,
    `subtotal` decimal(10,2) NOT NULL,
    `notes` text,
    `status` varchar(50) DEFAULT 'pending',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_customer_email` (`customer_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### **Data Types & Constraints**
- **Customer Data**: Name, email, phone with validation
- **Service Details**: Type, date, time, location
- **Device Info**: Brand and model selection
- **Cart Items**: JSON-encoded repair services
- **Timestamps**: Creation and update tracking

---

## 🔌 **AJAX Endpoints**

### **Frontend Endpoints**

#### **Get Models**
```php
add_action('wp_ajax_rbf_get_models', array($this, 'ajax_get_models'));
add_action('wp_ajax_nopriv_rbf_get_models', array($this, 'ajax_get_models'));
```
**Purpose**: Load device models for selected brand
**Data**: `brand`, `nonce`
**Response**: JSON array of models with images and descriptions

#### **Get Repairs**
```php
add_action('wp_ajax_rbf_get_repairs', array($this, 'ajax_get_repairs'));
add_action('wp_ajax_nopriv_rbf_get_repairs', array($this, 'ajax_get_repairs'));
```
**Purpose**: Load repair services for selected device
**Data**: `brand`, `model`, `nonce`
**Response**: JSON array of repair services with pricing

#### **Submit Booking**
```php
add_action('wp_ajax_rbf_submit_booking', array($this, 'ajax_submit_booking'));
add_action('wp_ajax_nopriv_rbf_submit_booking', array($this, 'ajax_submit_booking'));
```
**Purpose**: Process booking submission
**Data**: `booking_data` (JSON), `nonce`
**Response**: Success/error with booking ID

### **Admin Endpoints**

#### **Get Booking Details**
```php
add_action('wp_ajax_rbf_get_booking_details', array($this, 'ajax_get_booking_details'));
```
**Purpose**: Retrieve booking details for admin view
**Data**: `booking_id`, `nonce`
**Response**: HTML formatted booking details

#### **Get Invoice Data**
```php
add_action('wp_ajax_rbf_get_invoice_data', array($this, 'ajax_get_invoice_data'));
```
**Purpose**: Get raw booking data for invoice generation
**Data**: `booking_id`, `nonce`
**Response**: JSON object with all booking data

#### **Delete Booking**
```php
add_action('wp_ajax_rbf_delete_booking', array($this, 'ajax_delete_booking'));
```
**Purpose**: Remove booking from database
**Data**: `booking_id`, `nonce`
**Response**: Success/error confirmation

---

## 📱 **JavaScript API**

### **Global Functions**

#### **Navigation**
```javascript
// Navigate to specific step
window.rbfGoToStep(stepNumber);

// Example: Go to step 3
rbfGoToStep(3);
```

#### **Brand & Model Selection**
```javascript
// Select device brand
function selectBrand(brand) {
    window.selectedBrand = brand;
    // Triggers model loading
}

// Select device model
function selectModel(model) {
    window.selectedModel = model;
    // Triggers repair loading
}
```

#### **Cart Management**
```javascript
// Add item to cart
function addToCart(repairId, repairName, price, icon) {
    // Adds to window.cart array
    // Updates display automatically
}

// Remove item from cart
function removeFromCart(repairId) {
    // Removes from window.cart array
    // Updates display automatically
}

// Update cart display
function updateCartDisplay() {
    // Refreshes cart UI
    // Calculates totals with VAT
}
```

### **Event Handlers**

#### **Form Submission**
```javascript
$('#booking-form').on('submit', function(e) {
    e.preventDefault();
    // Form validation and submission
});
```

#### **Service Method Changes**
```javascript
$('input[name="service_type"]').on('change', function() {
    const selectedMethod = $(this).val();
    // Show/hide relevant sections
    // Update validation requirements
});
```

### **Data Persistence**
```javascript
// Save cart to localStorage
function saveCartToStorage() {
    const cartData = {
        cart: window.cart,
        cartTotal: window.cartTotal,
        selectedBrand: window.selectedBrand,
        selectedModel: window.selectedModel
    };
    localStorage.setItem('rbf_cart_data', JSON.stringify(cartData));
}

// Load cart from localStorage
function loadCartFromStorage() {
    const cartData = localStorage.getItem('rbf_cart_data');
    if (cartData) {
        const parsed = JSON.parse(cartData);
        // Restore cart state
    }
}
```

---

## 🎨 **CSS Architecture**

### **CSS Organization**

#### **Admin Styles (`admin.css`)**
```css
/* Page-specific classes */
.rbf-dashboard-page { /* Dashboard styles */ }
.rbf-bookings-page { /* Bookings management styles */ }
.rbf-payment-page { /* Payment settings styles */ }

/* Component styles */
.rbf-status-card { /* Status indicator cards */ }
.rbf-modal { /* Modal dialogs */ }
.rbf-toast { /* Notification toasts */ }
```

#### **Frontend Styles (`style.css`)**
```css
/* Form structure */
.rbf-container { /* Main container */ }
.rbf-step { /* Individual form steps */ }
.rbf-progress { /* Progress indicators */ }

/* Interactive elements */
.rbf-brand-card { /* Brand selection cards */ }
.rbf-model-card { /* Model selection cards */ }
.rbf-repair-item { /* Repair service items */ }
.rbf-cart-item { /* Cart display items */ }
```

### **Responsive Design**
```css
/* Mobile-first approach */
.rbf-container {
    max-width: 100%;
    padding: 15px;
}

/* Tablet breakpoint */
@media (min-width: 768px) {
    .rbf-container {
        max-width: 768px;
        padding: 30px;
    }
}

/* Desktop breakpoint */
@media (min-width: 1024px) {
    .rbf-container {
        max-width: 1200px;
        padding: 40px;
    }
}
```

### **CSS Custom Properties**
```css
:root {
    --rbf-primary: #017c36;
    --rbf-secondary: #30ab58;
    --rbf-accent: #6f42c1;
    --rbf-background: #f8f9fa;
    --rbf-border: #e9ecef;
    --rbf-text: #23282d;
    --rbf-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
```

---

## 🔗 **Hooks & Filters**

### **Available Actions**

#### **Before/After Booking**
```php
// Before booking submission
do_action('rbf_before_booking_submit', $booking_data);

// After successful booking
do_action('rbf_after_booking_success', $booking_id, $booking_data);

// After booking deletion
do_action('rbf_after_booking_deletion', $booking_id);
```

#### **Admin Page Rendering**
```php
// Before admin page content
do_action('rbf_before_admin_page', $page_name);

// After admin page content
do_action('rbf_after_admin_page', $page_name);
```

### **Available Filters**

#### **Data Modification**
```php
// Filter booking data before save
$booking_data = apply_filters('rbf_booking_data_before_save', $booking_data);

// Filter repair services
$repair_services = apply_filters('rbf_repair_services', $repair_services, $brand, $model);

// Filter device models
$models = apply_filters('rbf_device_models', $models, $brand);
```

#### **Display Customization**
```php
// Filter admin page title
$page_title = apply_filters('rbf_admin_page_title', $page_title, $page_name);

// Filter form validation messages
$validation_message = apply_filters('rbf_validation_message', $message, $field_name);
```

---

## 🛠 **Customization Guide**

### **Adding New Brands/Models**

#### **Modify JSON Data**
```json
{
  "brands": {
    "NewBrand": {
      "icon": "/images/newbrand-icon.svg",
      "models": [
        {
          "name": "Model X",
          "description": "New model description",
          "image": "/images/modelx.jpg"
        }
      ]
    }
  }
}
```

#### **Add Custom Logic**
```php
// In repair-booking-form.php
public function get_models_by_brand($brand) {
    // Load from JSON
    $models = $this->load_models_from_json($brand);
    
    // Apply custom filters
    $models = apply_filters('rbf_custom_models', $models, $brand);
    
    return $models;
}
```

### **Custom Repair Services**

#### **Add New Service Types**
```php
public function get_repairs_by_model($model) {
    $repairs = [
        'custom_repair' => [
            'id' => 'custom_repair',
            'name' => 'Custom Repair',
            'price' => 299.00,
            'duration' => '2 hours',
            'icon' => '/images/custom-repair.svg',
            'description' => 'Custom repair service'
        ]
    ];
    
    return apply_filters('rbf_custom_repairs', $repairs, $model);
}
```

### **Custom Validation**

#### **Add Field Validation**
```javascript
// In main.js
function validateCustomField(fieldValue) {
    // Custom validation logic
    if (!fieldValue || fieldValue.length < 3) {
        return 'Field must be at least 3 characters long';
    }
    return true;
}

// Integrate with form validation
function validateBookingForm() {
    // ... existing validation ...
    
    // Custom validation
    const customField = $('#custom-field').val();
    const customValidation = validateCustomField(customField);
    if (customValidation !== true) {
        alert(customValidation);
        return false;
    }
}
```

---

## 🐛 **Troubleshooting**

### **Common Issues**

#### **AJAX Errors**
```javascript
// Check nonce verification
console.log('Nonce:', rbf_ajax.nonce);

// Verify AJAX URL
console.log('AJAX URL:', rbf_ajax.ajax_url);

// Check user permissions
if (!rbf_ajax.user_can_manage) {
    console.error('User lacks required permissions');
}
```

#### **CSS Conflicts**
```css
/* Force plugin styles with !important */
.rbf-modal {
    z-index: 100000 !important;
    display: none !important;
}

/* Override conflicting themes */
.rbf-container * {
    box-sizing: border-box !important;
}
```

#### **JavaScript Errors**
```javascript
// Enable debug mode
window.rbfDebug = true;

// Safe execution wrapper
function safeExecute(fn, context = 'Unknown') {
    try {
        if (window.rbfDebug) console.log(`Running: ${context}`);
        return fn();
    } catch (error) {
        console.error(`Error in ${context}:`, error);
        return false;
    }
}
```

### **Debug Mode**
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Plugin-specific debug
define('RBF_DEBUG', true);
```

---

## ⚡ **Performance Optimization**

### **Database Optimization**

#### **Indexing Strategy**
```sql
-- Add indexes for common queries
ALTER TABLE wp_rbf_bookings ADD INDEX idx_status_created (status, created_at);
ALTER TABLE wp_rbf_bookings ADD INDEX idx_customer_email_status (customer_email, status);
```

#### **Query Optimization**
```php
// Use prepared statements
$stmt = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}rbf_bookings WHERE status = %s AND created_at >= %s",
    $status,
    $date
);

// Limit results
$bookings = $wpdb->get_results($stmt . " LIMIT 50");
```

### **Frontend Optimization**

#### **JavaScript Performance**
```javascript
// Debounce expensive operations
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Debounced cart update
const debouncedCartUpdate = debounce(updateCartDisplay, 300);
```

#### **CSS Performance**
```css
/* Use transform instead of position changes */
.rbf-card:hover {
    transform: translateY(-2px);
    /* Instead of: margin-top: -2px; */
}

/* Optimize animations */
.rbf-animation {
    will-change: transform;
    transform: translateZ(0); /* Hardware acceleration */
}
```

### **Caching Strategies**

#### **Local Storage**
```javascript
// Cache device data
function cacheDeviceData(brand, models) {
    const cacheKey = `rbf_models_${brand}`;
    const cacheData = {
        data: models,
        timestamp: Date.now(),
        expiry: 24 * 60 * 60 * 1000 // 24 hours
    };
    localStorage.setItem(cacheKey, JSON.stringify(cacheData));
}

// Retrieve cached data
function getCachedDeviceData(brand) {
    const cacheKey = `rbf_models_${brand}`;
    const cached = localStorage.getItem(cacheKey);
    if (cached) {
        const cacheData = JSON.parse(cached);
        if (Date.now() - cacheData.timestamp < cacheData.expiry) {
            return cacheData.data;
        }
    }
    return null;
}
```

---

## 📚 **Additional Resources**

### **WordPress Development**
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WordPress AJAX API](https://codex.wordpress.org/AJAX_in_Plugins)
- [WordPress Database API](https://codex.wordpress.org/Class_Reference/wpdb)

### **Frontend Development**
- [jQuery Documentation](https://api.jquery.com/)
- [CSS Grid Layout](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Grid_Layout)
- [Flexbox Guide](https://css-tricks.com/snippets/css/a-guide-to-flexbox/)

### **Performance & Security**
- [WordPress Security](https://wordpress.org/support/article/hardening-wordpress/)
- [Web Performance Best Practices](https://web.dev/performance/)
- [OWASP Security Guidelines](https://owasp.org/www-project-top-ten/)

---

## 📞 **Developer Support**

- **Technical Questions**: developer@efix.ae
- **Bug Reports**: Include error logs and reproduction steps
- **Feature Requests**: Detailed use case descriptions
- **Code Reviews**: Submit pull requests for review

---

*This documentation is maintained by the EFIX Repair Services development team. Last updated: January 2025*

### **Receipt Printing & Frontend JS API**

#### `window.rbfPrintReceipt()`
Opens a dedicated, clean, isolated print dialog for the confirmed repair booking.
- **Mechanism**: Dynamically populates a hidden `<iframe>` with an isolated receipt HTML document and triggers `print()` directly on that frame.
- **Data Source**: Pulls from `state.completedBooking` (persisted on successful booking AJAX response) with automatic DOM fallback.
- **Output Structure**: Company header, green checkmark, Tracking ID box, Customer & Device details box, Itemized services table, Subtotal, 5% VAT, Grand Total, and Warranty badges.

#### `renderRepairIconHtml(item)`
Normalizes icon paths and generates appropriate HTML:
- Resolves relative file paths (e.g. `Brands/repair_Icons/broken.png` or `assets/images/repairs/screen-replacement.png`) with `rbfData.plugin_url`.
- Automatically maps repair names to authentic icons via keyword matching (screen, battery, charging port, camera, speaker, mic, back glass, frame, water damage, software, diagnostics, buttons).
- Implements `onerror` graceful fallback to prevent broken image displays.

#### Backend Helper: `RBF_Catalog::normalize_repair_icon($icon, $name)`
PHP server-side counterpart that sanitizes and returns clean relative asset paths for all repair services.

#### Backend Helper: `RBF_WhatsApp::send_booking_notification($booking)` & `get_direct_whatsapp_url($booking)`
Handles automated UltraMsg API dispatch and free `https://wa.me/` direct chat link generation without fatal errors.
