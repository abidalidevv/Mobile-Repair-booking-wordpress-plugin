# 🔧 Repair Booking Form Plugin - Admin Documentation

**Version 1.1** | WordPress Plugin for Mobile Device Repair Bookings

## 📋 Table of Contents

1. [Plugin Overview](#plugin-overview)
2. [Installation & Setup](#installation--setup)
3. [Admin Pages & Features](#admin-pages--features)
4. [System Workflows](#system-workflows)
5. [Database Structure](#database-structure)
6. [Troubleshooting](#troubleshooting)

---

## 🎯 Plugin Overview

### Description
The Repair Booking Form Plugin is a comprehensive WordPress solution designed for mobile device repair businesses. It provides a multi-step booking form, admin management system, and automated pricing management for repair services.

### Key Features
- **Multi-step Booking Form**: Customer-friendly form with device selection and payment integration
- **Admin Management**: Comprehensive interface for managing brands, models, repairs, and bookings
- **Dynamic Pricing**: Advanced pricing system with brand-model combinations and admin overrides
- **Dashboard Analytics**: Real-time booking statistics and status management
- **Auto-synchronization**: Automatic data sync between JSON source and database tables
- **Professional UI**: Modern, responsive design with intuitive user experience

### System Requirements
- **WordPress**: 6.0 or higher
- **PHP**: 8.0 or higher
- **MySQL**: 5.7 or higher
- **Browser**: Modern browsers with JavaScript enabled

### File Structure
```
repair-booking-form/
├── repair-booking-form.php (Main plugin file)
├── error-logger.php (Error logging system)
├── admin/
│   ├── dashboard.php (Admin dashboard)
│   └── bookings.php (Bookings management)
├── assets/
│   ├── css/admin.css (Admin styles)
│   └── js/admin.js (Admin scripts)
├── includes/
│   └── class-brands-models-manager.php (JSON data manager)
└── templates/
    └── booking-form.php (Frontend form template)
```

---

## 🚀 Installation & Setup

### Plugin Installation
1. **Upload Plugin**: Upload the plugin folder to `/wp-content/plugins/` directory
2. **Activate Plugin**: Go to WordPress Admin → Plugins → Activate "Repair Booking Form"
3. **Database Setup**: Navigate to Repair Prices Management page and click "Setup Database Tables"

### Initial Configuration
> **Important**: The plugin requires initial database setup to function properly. This creates all necessary tables and populates them with default data.

### Database Tables Created

| Table Name | Purpose | Key Features |
|------------|---------|--------------|
| `rbf_bookings` | Stores customer booking information | Status tracking, payment info, customer details |
| `rbf_brands` | Device brands (iPhone, Samsung, etc.) | Active/inactive status, JSON sync |
| `rbf_models` | Device models linked to brands | Brand relationships, status management |
| `rbf_repairs` | Repair services and base prices | JSON source sync, pricing data |
| `rbf_pricing` | Brand-model-repair combinations | Admin overrides, dynamic pricing |
| `rbf_default_prices` | Base prices for repair services | Master pricing reference |

---

## ⚙️ Admin Pages & Features

### 1. Repair Services Management

**Purpose**: Manage the master list of repair services, brands, and models using JSON-based data management.

**Key Features**:
- **JSON Data Source**: Centralized data management
- **CRUD Operations**: Add, edit, delete brands, models, and repairs
- **Real-time Updates**: Changes reflect immediately across the system
- **Data Validation**: Ensures data integrity and consistency

**Workflow**:
1. Select entity type (Brand/Model/Repair)
2. Add new entry or edit existing
3. Save changes (auto-syncs with database)
4. Verify changes in other admin pages

### 2. Repair Prices Management

**Purpose**: Advanced pricing management with filtering, bulk updates, and admin price overrides.

**Advanced Filtering System**:
```
Filter Hierarchy: Brand → Model → Repair Type

Features:
• Cascading dropdowns
• Real-time table filtering
• Dynamic option population
• Filter status display
```

**Price Management Features**:
- **Global Price Updates**: Apply price to all repairs
- **Brand-specific Updates**: Update prices for specific brands
- **Model-specific Updates**: Update prices for specific models
- **Individual Price Editing**: Edit specific combination prices
- **Admin Override Protection**: Custom prices preserved during regeneration

**Admin Override System**:
> **Important**: When admins edit individual prices, these become "admin overrides" and are preserved during table regeneration.

**Workflow**:
1. Admin edits individual price in table
2. Price is marked as "admin-edited" in database
3. During regeneration, admin prices are preserved
4. System shows "ADMIN SET" vs "DEFAULT" status

**Filtered Brands for Combinations**:
- iPhone
- Samsung
- Google Pixel
- OnePlus

**Excluded Repairs (Not in Combinations)**:
- Software Support
- General Diagnosis
- Data Recovery
- Device Unlock Service

### 3. Repair Booking Dashboard

**Purpose**: Real-time overview of all bookings with status management and quick actions.

**Dashboard Components**:
- **Status Cards**: Pending, In Progress, Completed, Cancelled counts
- **Recent Bookings**: Latest booking information with status
- **Quick Actions**: Update status, view details, manage bookings
- **Real-time Updates**: Auto-refresh every 10 seconds

**Status Management**:

| Status | Description | Actions Available |
|--------|-------------|-------------------|
| Pending | New booking awaiting confirmation | Confirm, Cancel, Edit |
| In Progress | Repair work has begun | Update progress, Complete |
| Completed | Repair work finished | View details, Generate invoice |
| Cancelled | Booking cancelled by admin or customer | View details, Reactivate |

### 4. Bookings Management

**Purpose**: Detailed view and management of individual bookings with advanced filtering and bulk operations.

**Management Features**:
- **Detailed View**: Complete booking information
- **Status Updates**: Change booking status
- **Customer Communication**: Send notifications
- **Invoice Generation**: Create and send invoices
- **Bulk Operations**: Update multiple bookings

---

## 🔄 System Workflows & Logic

### 1. Data Synchronization Workflow

1. **JSON Source Management**: Admin updates repair services in JSON file via Repair Services Management page
2. **Database Sync**: System automatically syncs JSON data to database tables
3. **Pricing Table Regeneration**: Pricing combinations are regenerated based on updated data
4. **Admin Override Preservation**: Custom admin prices are preserved during regeneration

### 2. Booking Creation Workflow

1. **Customer Form Submission**: Customer fills out multi-step booking form
2. **Price Calculation**: System calculates price based on brand-model-repair combination
3. **Database Storage**: Booking information stored with "Pending" status
4. **Admin Notification**: New booking appears in dashboard and bookings management

### 3. Price Management Workflow

1. **Default Price Setting**: Base prices set in Repair Services Management
2. **Combination Generation**: System generates all brand-model-repair combinations
3. **Admin Customization**: Admins can override specific combination prices
4. **Customer Pricing**: Customers see final prices during booking

### 4. Table Regeneration Logic

```
Regeneration Process:

1. Sync JSON repairs to database
2. Get live data from master tables
3. Define excluded repairs (4 specific services)
4. Filter repairs for combinations
5. Preserve admin-edited prices
6. Clear existing pricing table
7. Insert default prices for all repairs
8. Generate combinations for filtered brands/models
9. Restore admin-edited prices
10. Update status and logging
```

### 5. Admin Override Priority System

> **Priority Order**: Admin-edited prices > Default repair prices > System fallback

**Workflow**:
1. **Price Edit**: Admin edits individual price in combinations table
2. **Database Marking**: Price marked with `is_admin_edited = 1` flag
3. **Preservation During Regeneration**: Admin prices backed up before table clearing
4. **Restoration**: Admin prices restored with original values

### 6. Auto-refresh & Synchronization

1. **Dashboard Auto-refresh**: Dashboard refreshes every 10 seconds automatically
2. **Status Change Detection**: AJAX calls trigger dashboard refresh on status changes
3. **Cross-page Sync**: Changes in one admin page reflect in others immediately

---

## 🗄️ Database Structure & Management

### Database Tables Overview

| Table | Purpose | Key Fields | Relationships |
|-------|---------|------------|---------------|
| `rbf_bookings` | Customer bookings | id, customer_name, brand, model, repair, status, total_amount | References brands, models, repairs |
| `rbf_brands` | Device brands | id, name, status, created_at | Has many models |
| `rbf_models` | Device models | id, brand_id, name, status, created_at | Belongs to brand, has many prices |
| `rbf_repairs` | Repair services | id, name, price, status, created_at | Has many prices |
| `rbf_pricing` | Price combinations | id, brand_id, model_id, repair_id, price, is_admin_edited | Links brands, models, and repairs |
| `rbf_default_prices` | Base prices | id, repair_id, price, created_at | References repairs |

### Key Database Fields

**Status Fields**:
- `booking.status`: pending, in_progress, completed, cancelled
- `brand.status`: active, inactive, deleted
- `model.status`: active, inactive, deleted
- `repair.status`: active, inactive, deleted

**Admin Override System**:
```
rbf_pricing.is_admin_edited:
• 0 = Default price (from repair service)
• 1 = Admin-edited price (preserved during regeneration)

Usage:
• Backup before TRUNCATE
• Restore after regeneration
• Visual indicators in admin interface
```

### Data Synchronization

**JSON to Database Sync**:
1. **Source of Truth**: JSON file is the master source for repair services
2. **Sync Function**: `sync_json_repairs_to_database()` reads JSON and updates DB
3. **Auto-trigger**: Sync runs on page load and before regeneration

**Sync Triggers**:
- Admin page load
- Table regeneration
- Database setup/fix operations
- Manual regeneration

### Database Maintenance

**Setup Operations**:
- **Setup Database**: Creates all tables and populates with initial data
- **Fix Database**: Repairs common issues and syncs data
- **Regenerate Combinations**: Rebuilds pricing tables with current data

**Performance Considerations**:
> **Important**: Large pricing tables may take time to regenerate. Admin overrides are always preserved.

---

## 🔧 Troubleshooting & Common Issues

### Common Issues & Solutions

#### 1. Dashboard Not Showing New Bookings

**Symptoms**: New bookings don't appear in dashboard counts

**Solutions**:
1. **Check Status Field**: Ensure new bookings have status = 'pending' (not '0.000000')
2. **Verify Database**: Check if bookings table exists and has correct structure
3. **Refresh Dashboard**: Use manual refresh button or wait for auto-refresh

#### 2. Prices Showing as 0.00

**Symptoms**: All prices display as 0.00 instead of actual values

**Solutions**:
1. **Check JSON Sync**: Verify JSON repairs are synced to database
2. **Regenerate Tables**: Use "Regenerate All Combinations" button
3. **Verify Repair Prices**: Check rbf_repairs table has correct price values

#### 3. Filter Not Working

**Symptoms**: Advanced filtering doesn't show expected results

**Solutions**:
1. **Check JavaScript Console**: Look for JavaScript errors in browser console
2. **Verify Data Attributes**: Ensure table rows have proper data-brand-id, data-model-id attributes
3. **Clear Browser Cache**: Refresh page or clear browser cache

#### 4. Admin Prices Not Preserved

**Symptoms**: Custom admin prices lost after regeneration

**Solutions**:
1. **Check is_admin_edited Column**: Verify rbf_pricing table has is_admin_edited column
2. **Check Regeneration Logs**: Look for error logs about price preservation
3. **Manual Database Check**: Verify admin prices exist in database before regeneration

### Debug Information

**Error Logging**:
```
Log File Location:
/wp-content/plugins/repair-booking-form/error-logger.php

Log Entries Include:
• Database operations
• Price preservation status
• Regeneration progress
• Error details
```

**Browser Console**:
- **JavaScript Errors**: Check browser console for script errors
- **AJAX Responses**: Monitor network tab for failed requests
- **DOM Issues**: Verify HTML structure and data attributes

**Database Queries**:
```sql
-- Check booking statuses
SELECT status, COUNT(*) FROM rbf_bookings GROUP BY status;

-- Verify admin overrides
SELECT COUNT(*) FROM rbf_pricing WHERE is_admin_edited = 1;

-- Check repair prices
SELECT name, price FROM rbf_repairs WHERE status != 'deleted';

-- Verify pricing combinations
SELECT COUNT(*) FROM rbf_pricing;
```

### Performance Optimization

**Large Dataset Handling**:
- **Batch Processing**: Large regenerations processed in batches
- **Progress Indicators**: Visual feedback during long operations
- **Memory Management**: Efficient data handling for large tables

**Recommended Settings**:
> **PHP Settings**:
> - memory_limit: 256M or higher
> - max_execution_time: 300 seconds
> - max_input_vars: 3000 or higher

---

## 📞 Support & Maintenance

### Regular Maintenance Tasks
1. **Monitor Error Logs**: Check error-logger.php for system issues
2. **Verify Data Sync**: Ensure JSON and database remain synchronized
3. **Check Admin Overrides**: Verify custom prices are preserved
4. **Performance Monitoring**: Monitor regeneration times for large datasets

### Best Practices
1. **Backup Before Major Changes**: Always backup database before bulk operations
2. **Test in Staging**: Test new configurations in staging environment first
3. **Monitor User Feedback**: Track admin user experience and optimize workflows
4. **Regular Updates**: Keep plugin and WordPress core updated

### Emergency Procedures
1. **Database Corruption**: Use "Fix Database" function
2. **Lost Admin Prices**: Check regeneration logs and restore from backup
3. **Performance Issues**: Increase PHP memory and execution time limits
4. **Data Loss**: Restore from latest backup and re-sync JSON data

---

**Documentation Version**: 1.1  
**Last Updated**: Current Date  
**Plugin Version**: 1.1  
**WordPress Compatibility**: 6.0+  
**PHP Compatibility**: 8.0+
