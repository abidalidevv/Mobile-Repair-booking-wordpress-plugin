# 📚 EFIX Repair Booking Plugin — Complete User & Administrator Manual

> **Plugin Name:** Repair Booking Form (EFIX)  
> **WordPress Compatibility:** 6.0 – 6.7+  
> **PHP Compatibility:** 8.0 – 8.3+  
> **Author:** Abid Ali  

---

## Table of Contents
1. [Introduction & Overview](#1-introduction--overview)
2. [Installation & Setup](#2-installation--setup)
3. [Shortcodes Reference](#3-shortcodes-reference)
4. [Customer Booking Journey (Frontend)](#4-customer-booking-journey-frontend)
5. [Admin Management Guide](#5-admin-management-guide)
   - [Dashboard & Analytics](#51-dashboard--analytics)
   - [Managing Bookings & Invoices](#52-managing-bookings--invoices)
   - [Device Catalog & Smart Tiers](#53-device-catalog--smart-tiers)
   - [Repair Services & Labour Cost](#54-repair-services--labour-cost)
   - [Repair Prices & Manual Overrides](#55-repair-prices--manual-overrides)
   - [Supplier Integration & Wholesale Feeds](#56-supplier-integration--wholesale-feeds)
   - [Multi-Currency & VAT Settings](#57-multi-currency--vat-settings)
   - [WhatsApp Automation](#58-whatsapp-automation)
6. [Wholesale Feed Import Guide (CSV / XLSX)](#6-wholesale-feed-import-guide-csv--xlsx)
7. [Troubleshooting & FAQ](#7-troubleshooting--faq)

---

## 1. Introduction & Overview

The **EFIX Repair Booking Form** is a full-featured, turnkey solution for device repair shops, electronics service centers, and mobile technicians. It combines an intuitive customer booking wizard with an enterprise-grade backend for parts pricing, supplier automation, invoicing, and WhatsApp notifications.

### Key Capabilities
- **18 Brands & 525 Models**: Complete coverage from 2015 classics to 2026+ flagships (Apple, Samsung, Google Pixel, OnePlus, Huawei, Xiaomi, Oppo, iPad, MacBook, Vivo, Realme, Infinix, Tecno, Honor, Motorola, Nokia, Nothing, and custom models).
- **100% Local Self-Hosted Assets**: 525 verified device photos and brand badges stored locally on disk—zero external hotlinks, zero CDN dependencies, lightning fast load times.
- **Dynamic 6-Tier Pricing Engine**: Supports live verified supplier prices, smart device tiers, admin manual price locks, and global fallbacks.
- **Supplier Integration**: Native Wholesale XLSX and CSV feed processing with LXCELL UAE parts integration, automated 48-hour WP-Cron sync, and concurrency locking.
- **Smart Cart System**: Real-time multi-repair selection, duration estimates, 5% UAE VAT calculation, and currency conversion.
- **Automated Customer Communication**: Instant WhatsApp booking confirmation alerts and printable branded invoices.

---

## 2. Installation & Setup

### Requirements
* WordPress 6.0 or higher
* PHP 8.0 or higher (PHP 8.1 / 8.2 / 8.3 fully supported)
* MySQL 5.7+ or MariaDB 10.3+
* PHP `zip` and `simplexml` extensions enabled (standard on all WordPress hosts)

### Installation Steps
1. Download or clone the plugin folder into `/wp-content/plugins/repair-booking-form/`.
2. Navigate to **WordPress Admin → Plugins → Installed Plugins**.
3. Locate **Repair Booking Form** and click **Activate**.
4. Upon activation, the plugin automatically creates the required 10 database tables and registers the default options.
5. Create a new page (e.g., "Book a Repair"), paste the shortcode `[repair_booking_form]`, and publish.

---

## 3. Shortcodes Reference

| Shortcode | Purpose | Example / Attributes |
|---|---|---|
| `[repair_booking_form]` | Embeds the complete 4-step repair booking wizard. | `[repair_booking_form]` |
| `[rbf_store_info]` | Displays store address, working hours, and contact details. | `[rbf_store_info show_address="true" show_hours="true"]` |
| `[rbf_tracker]` | Customer live repair tracking page by Booking ID. | `[rbf_tracker]` |
| `[rbf_brands_models]` | Showcases supported device brands and models grid. | `[rbf_brands_models]` |

---

## 4. Customer Booking Journey (Frontend)

The frontend wizard guides customers through an intuitive, mobile-optimized 4-step workflow:

1. **Step 1 — Brand Selection**:
   - Customers choose from visual brand cards with crisp logos.
   - Includes Apple, Samsung, Google Pixel, iPad, MacBook, Nothing, and custom "Others".
2. **Step 2 — Model Selection**:
   - Loads genuine device photos with instant client-side search/filter.
   - Full model names with generation identifiers.
3. **Step 3 — Repair Service Selection (Smart Cart)**:
   - Browse repairs (Screen Replacement, Battery, Charging Port, Camera, Water Damage, Back Glass, etc.).
   - Displays real-time estimated turnaround time and dynamic prices.
   - Customers can add multiple repairs to their cart.
4. **Step 4 — Customer Information & Booking**:
   - Select Service Mode: **Store Visit**, **Free Pickup & Delivery**, or **Onsite Repair**.
   - Pick preferred date and time slot.
   - Enter contact details with international phone number validation.
   - View itemized invoice breakdown (Subtotal, 5% VAT, Total Amount).
   - Instant confirmation with short Booking ID (`EFIX-XXXXXX-XXX`), Print Invoice button, and Google/Outlook Calendar sync.

---

## 5. Admin Management Guide

### 5.1 Dashboard & Analytics
- **Location**: `Repair Booking → Dashboard`
- **Features**: Live metrics on total revenue, active repairs, completed jobs, and top requested brands and models.

### 5.2 Managing Bookings & Invoices
- **Location**: `Repair Booking → Bookings`
- **Features**:
  - Filter bookings by status: `Pending`, `Confirmed`, `In Progress`, `Completed`, `Cancelled`.
  - Edit customer notes, device IMEI/Serial, or technician assignment.
  - **Print Invoice**: Generates a professional branded tax invoice complete with VAT breakdown, company header, and customer terms.

### 5.3 Device Catalog & Smart Tiers
- **Location**: `Repair Booking → Models`
- **Features**:
  - Browse all 525 models across 18 brands.
  - Device Tier Heuristics:
    - **Tier 1 (Economy)**: Budget models (multiplier: `0.70x`).
    - **Tier 2 (Mid-Range)**: Standard flagships (multiplier: `1.00x`).
    - **Tier 3 (Flagship)**: Pro, Ultra, Plus series (multiplier: `1.35x`).
    - **Tier 4 (Premium/Foldable)**: Fold, Flip, MacBook series (multiplier: `1.65x`).
  - Single and bulk tier assignment tools.

### 5.4 Repair Services & Labour Cost
- **Location**: `Repair Booking → Repairs`
- **Features**:
  - Add or customize repair types, icons, descriptions, and durations.
  - Configure **Labour Cost** independently per repair service (default: `80.00 AED`).

### 5.5 Repair Prices & Manual Overrides
- **Location**: `Repair Booking → Repair Prices`
- **Features**:
  - View the complete matrix of Model + Repair Type prices.
  - **Manual Override Lock**: When you edit any price in this table, the row is marked `is_manual_override = 1`. Automated supplier sync will **never** overwrite your custom price.
  - **Revert Override**: Clear the manual price to instantly restore the dynamic supplier formula.

### 5.6 Supplier Integration & Wholesale Feeds
- **Location**: `Repair Booking → Supplier Settings`
- **Features**:
  - **Active Provider**: Select provider (e.g., LXCELL UAE).
  - **Global Markup %**: Configurable markup added on top of cost + labour (default: `13%`).
  - **Default Labour Fee**: Global labour fallback.
  - **Sync Schedule**: Automated 48-hour cron interval.
  - **Sync Now**: On-demand manual sync button with real-time feedback and concurrency locking.
  - **Audit Logs**: Full history of sync duration, parts updated, and items needing review.

### 5.7 Multi-Currency & VAT Settings
- **Location**: `Repair Booking → Settings → Currency & VAT`
- **Features**:
  - Base currency: **AED** (United Arab Emirates Dirham).
  - Multi-currency display support (USD, EUR, GBP, SAR).
  - Configurable VAT rate (default: `5.0%`).

### 5.8 WhatsApp Automation
- **Location**: `Repair Booking → Settings → WhatsApp`
- **Features**:
  - Automated WhatsApp message generation upon booking confirmation.
  - Customizable template tags: `{customer_name}`, `{booking_id}`, `{device}`, `{total_amount}`, `{appointment_time}`.

---

## 6. Wholesale Feed Import Guide (CSV / XLSX)

You can import wholesale spare-parts catalog files directly through the admin panel:

### Supported Formats
* **CSV Files**: UTF-8 comma-separated text.
* **XLSX Files**: Microsoft Excel OpenXML format (parsed natively without third-party extensions).

### Expected Column Names
The importer automatically recognizes any standard column headers:

| Field | Recognized Column Names |
|---|---|
| **SKU** | `SKU`, `Product Code`, `Item #`, `Part Number`, `MPN` |
| **Product Name** | `Product Name`, `Title`, `Item Description`, `Description` |
| **Model** | `Model`, `Device Model`, `Compatible Model`, `Device` |
| **Repair Type** | `Repair Type`, `Part Type`, `Category`, `Component` |
| **Cost Price** | `Cost`, `Cost Price`, `Wholesale Price`, `Price (AED)`, `AED` |
| **Stock** | `Stock`, `Stock Status`, `Availability`, `Qty` |

---

## 7. Troubleshooting & FAQ

### Q: Why is a model's price not updating after running Supplier Sync?
**A:** Check if that model + repair has a **Manual Override** active in `Repair Booking → Repair Prices`. Manual overrides take highest priority and are locked to protect custom administrator pricing. To allow supplier pricing to take effect, click **Remove Override**.

### Q: What happens if a supplier part is Out of Stock?
**A:** The system automatically falls back to Level 3 (Smart Tier Pricing). Customers can still complete their booking without experiencing an error or seeing a 0.00 AED price.

### Q: Can customers or competitors see our wholesale spare part costs?
**A:** **Never.** Wholesale costs, labour fees, markup percentages, and supplier names are strictly stripped from all public AJAX endpoints and HTML responses.

### Q: Where are error logs stored?
**A:** Debug logs are handled by `RBF_Error_Logger` and written to `/wp-content/uploads/rbf-logs/debug-errors.log` protected by server access rules.