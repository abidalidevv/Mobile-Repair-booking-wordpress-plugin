# 🔧 EFIX — Mobile & Laptop Repair Booking Plugin for WordPress

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-8.0%20--%208.3%2B-777bb4.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](LICENSE)
[![Brands](https://img.shields.io/badge/Brands-18%20Active-orange.svg)](#supported-brands--devices)
[![Models](https://img.shields.io/badge/Models-525%20Devices-purple.svg)](#supported-brands--devices)
[![Suppliers](https://img.shields.io/badge/Wholesale%20Sync-LXCELL%20UAE-success.svg)](#supplier-integration--pricing-engine)

A modern, enterprise-ready WordPress plugin designed for mobile phone, tablet, and laptop repair shops. Features an interactive 4-step booking wizard, real-time smart cart, live wholesale spare-parts pricing with UAE suppliers (LXCELL), multi-tiered pricing, WhatsApp automation, and printable tax invoices.

---

## 🚀 Key Features

* **📱 Comprehensive Device Catalog (525 Models Across 18 Brands)**:
  - Covers devices from 2015 to 2026+ flagships.
  - Includes **Apple iPhone**, **Samsung Galaxy**, **Google Pixel**, **OnePlus**, **Huawei**, **Xiaomi / POCO**, **Oppo**, **iPad**, **MacBook**, **Vivo**, **Realme**, **Infinix**, **Tecno**, **Honor**, **Motorola**, **Nokia**, **Nothing / CMF**, and custom **Others**.
* **🖼️ 100% Self-Hosted Local Imagery**:
  - Every single model has a verified, high-resolution photo stored locally on disk in `Brands/`.
  - Zero external CDN hotlinking, zero third-party tracking, ultra-fast loading.
* **⚡ 6-Level Smart Pricing Cascade**:
  - **Level 1**: Administrator Manual Override Lock (Permanent until cleared).
  - **Level 2**: Verified Live Supplier Price (`Formula: (Part Cost + Labour) * (1 + Markup%)`).
  - **Level 3**: Smart 4-Tier Model Price (Economy, Mid-Range, Flagship, Premium/Foldables).
  - **Level 4**: Legacy Matrix Model Price.
  - **Level 5**: Global Default Repair Price.
  - **Level 6**: Master Seed Catalog Baseline.
* **🔄 Wholesale Supplier Feeds (LXCELL UAE)**:
  - Native Wholesale OpenXML XLSX and CSV file parsing (zero heavy external dependencies).
  - Automated 48-hour WP-Cron sync with error audit logging and concurrency locking.
* **🛡️ Server-Side Fraud Protection**:
  - Client-side submitted prices are discarded; totals, subtotals, and 5% VAT are re-calculated server-side at the moment of order placement.
  - Wholesale costs, supplier SKUs, and markup percentages are strictly hidden from customer view.
* **🧾 Printable Invoices & Calendar Integration**:
  - Instant branded PDF/print tax invoice with company headers, customer details, and VAT breakdown.
  - Google Calendar and Outlook appointment sync.
* **💬 WhatsApp & Multi-Currency**:
  - Instant automated customer notification messages via WhatsApp.
  - Native AED base currency with multi-currency conversion support.

---

## 📂 Project Directory Structure

The repository follows a clean, standardized WordPress plugin structure:

```text
repair-booking-form/
├── repair-booking-form.php        # Core plugin entry point & hook registrations
├── uninstall.php                  # Clean database removal on uninstall
├── brands_models_data.json        # Master seed catalog (18 brands, 525 models)
├── README.md                      # Primary project overview & quick start
├── admin/                         # WordPress admin pages & dashboards
│   ├── bookings.php               # Bookings management & invoice printer
│   ├── dashboard.php              # Analytics, KPIs, status cards
│   ├── models.php                 # Model catalog CRUD & tier assigner
│   └── repairs.php                # Repair services & labour fees editor
├── assets/                        # Compiled CSS and JavaScript
│   ├── css/                       # Frontend & admin stylesheets
│   └── js/                        # Multi-step wizard frontend logic
├── Brands/                        # 100% self-hosted device images & brand logos
│   ├── ipad_modals/               # iPad Pro, Air, Mini, 10th Gen renders
│   ├── macbook_modals/            # MacBook Pro & MacBook Air renders
│   ├── nothing_modals/            # Nothing & CMF phone photos
│   └── ...                        # Apple, Samsung, Xiaomi, Huawei, etc.
├── docs/                          # Comprehensive technical documentation
│   ├── brain.md                   # 🧠 SYSTEM BRAIN: Architecture, State Machine & Schemas
│   ├── documentation.md           # 📚 Complete User & Administrator Manual
│   ├── documentation.html         # 🌐 Interactive HTML Documentation for Admin UI
│   ├── developer-guide.md         # 🛠️ Developer Reference: Hooks, Filters & APIs
│   ├── changelog.md               # 📝 Full Release History & Changelog
│   └── archive/                   # Archived legacy documentation
├── includes/                      # Object-oriented core backend classes
│   ├── class-rbf-pricing.php      # 6-Level pricing cascade engine
│   ├── class-rbf-catalog.php      # Catalog sync, tier heuristics & image normalizer
│   ├── class-rbf-currency.php     # Multi-currency & VAT engine
│   ├── class-rbf-error-logger.php # PSR-compliant debug logging
│   ├── class-rbf-tracker.php      # Live repair status tracker
│   ├── class-rbf-whatsapp.php     # WhatsApp automation engine
│   ├── class-brands-models-manager.php # JSON catalog manager
│   └── suppliers/                 # Wholesaler & spare parts feeds
│       ├── interface-rbf-supplier.php
│       ├── class-rbf-supplier-manager.php
│       ├── class-rbf-supplier-sync-manager.php
│       └── class-rbf-supplier-lxcell.php
├── templates/                     # Frontend views
│   └── form.php                   # [repair_booking_form] shortcode view
└── tools/                         # CLI & Developer diagnostics
    ├── verify_production_readiness.php
    ├── verify_expanded_catalog.php
    ├── check-syntax.php
    └── debug-plugin.php
```

---

## ⚡ Quick Start & Installation

1. **Upload**: Copy the `repair-booking-form` folder into your WordPress installation at:  
   `/wp-content/plugins/repair-booking-form/`
2. **Activate**: Go to **WordPress Admin → Plugins → Installed Plugins** and click **Activate**.
3. **Embed Booking Form**: Create or edit any WordPress page and paste the shortcode:
   ```text
   [repair_booking_form]
   ```
4. **Configure Store & Pricing**:
   - Navigate to **Repair Booking → Settings** in your WordPress sidebar.
   - Set your store location, working hours, and WhatsApp contact number.
   - Configure global labour cost and markup percentage in **Supplier Settings**.

---

## 🎯 Shortcode Reference

| Shortcode | Description |
|---|---|
| `[repair_booking_form]` | Renders the complete 4-step customer repair booking wizard. |
| `[rbf_store_info]` | Displays store address, working hours, and contact details. |
| `[rbf_tracker]` | Live order tracking interface for customers using their Booking ID. |
| `[rbf_brands_models]` | Displays an interactive grid of supported device brands and models. |

---

## 🧠 System Architecture & Pricing Engine

The pricing engine guarantees that every device has an accurate price while protecting administrative overrides:

```
[ Customer Requests Price ]
           │
           ▼
[ 1. Is Manual Override Active? ] ──YES──► [ Return Admin Locked Price ]
           │ NO
           ▼
[ 2. Is Live Supplier Part In-Stock? ] ──YES──► [ (Cost + Labour) * (1 + Markup%) ]
           │ NO / Out-of-Stock
           ▼
[ 3. Smart 4-Tier Model Price ] ──YES──► [ Economy / Mid / Flagship / Premium Rate ]
           │ EMPTY
           ▼
[ 4. Legacy Matrix Price ]
           │ EMPTY
           ▼
[ 5. Global Default Repair Price ]
```

For complete technical specifications, database schema diagrams, and synchronization state machines, please read:  
👉 [**docs/brain.md**](docs/brain.md)

---

## 📚 Complete Documentation Suite

All detailed documentation is neatly organized inside the [`docs/`](docs/) directory:

* 🧠 [**System Brain & Architecture Spec**](docs/brain.md) — In-depth database schemas, pricing state machine, and security layers.
* 📚 [**Administrator & User Manual**](docs/documentation.md) — Step-by-step instructions for shop owners, technician management, and invoices.
* 🛠️ [**Developer Guide & API Reference**](docs/developer-guide.md) — WordPress action hooks, filters, AJAX endpoints, and custom supplier provider development.
* 📝 [**Changelog & Release Notes**](docs/changelog.md) — Detailed version history.

---

## 📄 License & Credits

* **Author:** Abid Ali
* **License:** GNU General Public License v2 or later
* **Tested Up To:** WordPress 6.7