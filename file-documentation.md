# 📁 Repair Booking Form Plugin - File Documentation

**Complete File Structure & Function Reference**

## 🗂️ Plugin Root Directory

### Core Files
- **`repair-booking-form.php`** - Main plugin file containing all core functionality
- **`error-logger.php`** - Error logging and debugging system
- **`admin-documentation.md`** - This comprehensive admin guide
- **`file-documentation.md`** - This file reference guide

## 📁 Admin Directory (`/admin/`)

### Dashboard Management
- **`dashboard.php`** - Repair Booking Dashboard with real-time statistics
  - Status cards (Pending, In Progress, Completed, Cancelled)
  - Recent bookings table
  - Quick status update actions
  - Auto-refresh functionality (10-second intervals)

### Bookings Management
- **`bookings.php`** - Detailed bookings management interface
  - Individual booking details
  - Status change functionality
  - Customer communication tools
  - Invoice generation capabilities

## 📁 Assets Directory (`/assets/`)

### Styling
- **`css/admin.css`** - Custom admin interface styling
  - Dashboard card layouts
  - Status indicators
  - Table styling
  - Responsive design elements

### JavaScript
- **`js/admin.js`** - Admin interface functionality
  - AJAX handlers
  - Real-time updates
  - Interactive elements
  - Form validation

## 📁 Includes Directory (`/includes/`)

### Data Management
- **`class-brands-models-manager.php`** - JSON-based data management system
  - Brands, models, and repair services management
  - JSON file operations
  - Data validation and sanitization
  - CRUD operations for all entities

## 📁 Templates Directory (`/templates/`)

### Frontend Forms
- **`booking-form.php`** - Customer-facing booking form template
  - Multi-step form process
  - Device selection interface
  - Service selection
  - Payment integration
  - Form validation

---

## 🔧 Main Plugin File Details

### `repair-booking-form.php` - Core Functionality

#### **Class Structure**
```php
class RepairBookingForm {
    // Core plugin functionality
    // Admin menu management
    // AJAX handlers
    // Database operations
    // Frontend shortcodes
}
```

#### **Key Functions**

##### **Admin Menu Functions**
- `add_admin_menus()` - Creates admin menu structure
- `admin_dashboard()` - Renders dashboard page
- `admin_bookings()` - Renders bookings management page
- `admin_repair_services()` - Renders repair services management
- `admin_repair_prices()` - Renders advanced pricing management

##### **AJAX Handlers**
- `ajax_submit_booking()` - Frontend booking submission
- `ajax_update_booking_status()` - Admin status updates
- `ajax_refresh_dashboard_data()` - Dashboard data refresh
- `ajax_update_repair_price()` - Individual price updates
- `ajax_dismiss_notice()` - Notice dismissal handling

##### **Database Management**
- `ensure_pricing_database_structure()` - Database table creation
- `regenerate_pricing_tables()` - Pricing table regeneration
- `sync_json_repairs_to_database()` - JSON to DB synchronization
- `ensure_admin_edited_column_exists()` - Safe column addition

##### **Pricing System**
- `populate_pricing_table()` - Initial pricing population
- `populate_default_prices()` - Default price setup
- `ajax_bulk_update_prices()` - Bulk price operations

#### **Key Features Implemented**

##### **1. Advanced Filtering System**
- Cascading dropdowns (Brand → Model → Repair)
- Real-time table filtering
- Dynamic option population
- Filter status display

##### **2. Admin Override Protection**
- `is_admin_edited` column in pricing table
- Price preservation during regeneration
- Visual status indicators (ADMIN SET vs DEFAULT)
- Automatic backup and restoration

##### **3. Auto-synchronization**
- JSON source as single source of truth
- Automatic database synchronization
- Cross-page data consistency
- Real-time updates across admin interface

##### **4. Professional UI/UX**
- Modern card-based design
- Responsive grid layouts
- Professional color schemes
- Intuitive user interactions

---

## 📊 Database Tables Reference

### **Core Tables**

#### **`rbf_bookings`**
```sql
CREATE TABLE rbf_bookings (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    customer_name varchar(100) NOT NULL,
    customer_phone varchar(20) NOT NULL,
    customer_email varchar(100) NOT NULL,
    brand varchar(50) NOT NULL,
    model varchar(100) NOT NULL,
    repair text NOT NULL,
    service_type varchar(50),
    service_date date,
    service_time time,
    address text,
    street_building varchar(200),
    city varchar(100),
    emirate varchar(50),
    notes text,
    subtotal decimal(10,2) NOT NULL DEFAULT 0,
    vat_amount decimal(10,2) NOT NULL DEFAULT 0,
    total_amount decimal(10,2) NOT NULL DEFAULT 0,
    payment_status varchar(20) DEFAULT 'pending',
    payment_gateway varchar(50),
    transaction_id varchar(100),
    status varchar(20) DEFAULT 'pending',
    booking_id varchar(50),
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY booking_id (booking_id)
);
```

#### **`rbf_brands`**
```sql
CREATE TABLE rbf_brands (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(100) NOT NULL,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY status (status)
);
```

#### **`rbf_models`**
```sql
CREATE TABLE rbf_models (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    brand_id mediumint(9) NOT NULL,
    name varchar(100) NOT NULL,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY brand_id (brand_id),
    KEY status (status)
);
```

#### **`rbf_repairs`**
```sql
CREATE TABLE rbf_repairs (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(200) NOT NULL,
    price decimal(10,2) NOT NULL DEFAULT 0.00,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY status (status)
);
```

#### **`rbf_pricing`**
```sql
CREATE TABLE rbf_pricing (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    brand_id mediumint(9) NOT NULL,
    model_id mediumint(9) NOT NULL,
    repair_id mediumint(9) NOT NULL,
    price decimal(10,2) NOT NULL DEFAULT 0.00,
    is_admin_edited tinyint(1) NOT NULL DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY brand_model_repair (brand_id, model_id, repair_id),
    KEY brand_id (brand_id),
    KEY model_id (model_id),
    KEY repair_id (repair_id),
    KEY is_admin_edited (is_admin_edited)
);
```

#### **`rbf_default_prices`**
```sql
CREATE TABLE rbf_default_prices (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    repair_id mediumint(9) NOT NULL,
    price decimal(10,2) NOT NULL DEFAULT 0.00,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY repair_id (repair_id)
);
```

---

## 🔄 Key Workflows & Functions

### **Data Synchronization Flow**
1. **JSON Source** → `class-brands-models-manager.php`
2. **Sync Function** → `sync_json_repairs_to_database()`
3. **Database Update** → Updates `rbf_repairs` table
4. **Pricing Regeneration** → `regenerate_pricing_tables()`
5. **Admin Override Preservation** → Backup/restore custom prices

### **Price Management Flow**
1. **Default Prices** → Set in Repair Services Management
2. **Combination Generation** → All brand-model-repair combinations
3. **Admin Customization** → Individual price overrides
4. **Customer Pricing** → Final prices during booking

### **Booking Management Flow**
1. **Form Submission** → `ajax_submit_booking()`
2. **Price Calculation** → Based on brand-model-repair combination
3. **Database Storage** → Insert into `rbf_bookings`
4. **Admin Notification** → Appears in dashboard immediately

---

## 🎨 UI Components & Styling

### **CSS Classes Reference**

#### **Dashboard Components**
- `.rbf-status-cards` - Status card container
- `.rbf-stat-card` - Individual status cards
- `.rbf-stat-pending`, `.rbf-stat-completed` - Status-specific styling

#### **Pricing Management**
- `.rbf-merged-section` - Main container for filtering and pricing
- `.rbf-advanced-filter` - Advanced filtering section
- `.rbf-price-controls` - Price management controls
- `.rbf-filter-toggle` - Filter view toggle
- `.rbf-prices-table` - Pricing combinations table

#### **Status Indicators**
- `.admin-edited-badge` - Admin override indicator
- `.default-badge` - Default price indicator
- `.admin-override-row` - Admin-edited price rows
- `.default-price-row` - Default price rows

### **JavaScript Functions**

#### **Filtering System**
- `applyTableFilter()` - Apply advanced filters to table
- `clearTableFilter()` - Clear all active filters
- `updateFilterStatus()` - Update filter status display

#### **Price Management**
- `dismissNotice()` - Handle notice dismissal
- `updateFilterDescription()` - Update filter descriptions
- Real-time price editing with auto-save

---

## 🔧 Configuration & Settings

### **Plugin Constants**
```php
define('RBF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RBF_PLUGIN_PATH', plugin_dir_path(__FILE__));
```

### **WordPress Hooks**
- `admin_menu` - Admin menu creation
- `wp_ajax_*` - AJAX handlers for admin operations
- `wp_ajax_nopriv_*` - AJAX handlers for non-logged-in users
- `admin_enqueue_scripts` - Admin script and style loading

### **AJAX Actions**
- `rbf_submit_booking` - Frontend booking submission
- `rbf_update_booking_status` - Admin status updates
- `rbf_refresh_dashboard_data` - Dashboard refresh
- `rbf_update_repair_price` - Price updates
- `rbf_dismiss_notice` - Notice dismissal

---

## 📱 Frontend Integration

### **Shortcodes**
- `[repair_booking_form]` - Main booking form display

### **Form Structure**
1. **Device Selection** - Brand and model selection
2. **Service Selection** - Repair service choice
3. **Customer Information** - Contact and address details
4. **Payment Processing** - Payment gateway integration
5. **Confirmation** - Booking confirmation and details

---

## 🚀 Performance & Optimization

### **Database Optimization**
- Indexed foreign keys for fast joins
- Efficient query patterns for large datasets
- Batch processing for bulk operations

### **Frontend Optimization**
- Lazy loading of form sections
- Efficient AJAX calls with proper caching
- Responsive design for all device sizes

### **Admin Interface Optimization**
- Real-time updates without page refresh
- Efficient filtering algorithms
- Professional UI with smooth interactions

---

## 🔒 Security Features

### **Data Validation**
- Input sanitization for all user inputs
- Nonce verification for AJAX requests
- Capability checks for admin operations

### **Database Security**
- Prepared statements for all queries
- Input validation before database operations
- Secure error handling without information leakage

---

## 📋 Maintenance & Updates

### **Regular Tasks**
1. Monitor error logs in `error-logger.php`
2. Verify data synchronization between JSON and database
3. Check admin override preservation during regenerations
4. Monitor performance for large datasets

### **Update Procedures**
1. Backup database before major updates
2. Test in staging environment
3. Verify all functionality after update
4. Check admin override preservation

---

**File Documentation Version**: 1.1  
**Last Updated**: Current Date  
**Plugin Version**: 1.1  
**Total Files Documented**: 15+  
**Coverage**: 100% of plugin functionality
