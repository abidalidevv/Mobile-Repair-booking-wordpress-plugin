# Repair Booking Form — WordPress Plugin

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org)
[![Version](https://img.shields.io/badge/Version-2.0.6-brightgreen.svg)](#)
[![PHP](https://img.shields.io/badge/PHP-8.0%20--%208.3%2B-777bb4.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v2%2B-green.svg)](LICENSE)

A production-ready WordPress plugin for any **mobile phone, tablet, and laptop repair shop**. Drop in the shortcode, configure your pricing and store details, and your customers get a full interactive repair booking experience.

---

## What It Does

Customers walk through a clean 4-step wizard:

1. Pick device (brand + model with photos)
2. Choose repairs (smart-priced cards with icons, estimates, prices)
3. Select service type (Store Visit / Onsite / Pickup & Delivery)
4. Confirm — VAT-inclusive total, WhatsApp notification, printable receipt

Pricing, VAT, and supplier cost are calculated server-side. Customers never see your margins.

---

## Key Features

- **525 Models, 19 Brands** — Apple, Samsung, Google Pixel, OnePlus, Huawei, Xiaomi/POCO, Oppo, Vivo, Realme, Infinix, Tecno, Honor, Motorola, Nokia, Nothing/CMF, iPad, MacBook
- **6-Level Smart Pricing Cascade** — Admin override → Live supplier → 4-tier model → Legacy matrix → Global default
- **Wholesale Supplier Feed (LXCELL UAE)** — Native XLSX/CSV parsing, auto 48hr WP-Cron sync
- **Server-Side Fraud Protection** — Client prices discarded; totals re-calculated at order time
- **Printable Invoices** — Clean branded receipt isolated from site navigation
- **WhatsApp Automation** — Instant wa.me booking summary to customer
- **Admin Bookings Dashboard** — Search, filter, status updates, invoice print, delete
- **Zero-FOUC Pricing Engine** — No layout flash on the repair prices page
- **Modern Modal System** — Frosted-glass backdrop, 44px controls, animated toasts
- **4-Column Repair Cards** — Step 3 grid with icons, duration pills, price badges

---

## Installation

1. Upload 
epair-booking-form/ to /wp-content/plugins/
2. Activate via **WordPress Admin → Plugins**
3. Add shortcode to any page: [repair_booking_form]
4. Configure under **Repair Booking → Settings**

---

## Shortcodes

| Shortcode | Description |
|---|---|
| [repair_booking_form] | 4-step customer booking wizard |
| [rbf_store_info] | Store address, hours, contact |
| [rbf_tracker] | Live tracking by Booking ID |
| [rbf_brands_models] | Brand & model grid |

---

## Pricing Engine

`
Admin Override → Live Supplier Cost → 4-Tier Model Price → Legacy Matrix → Global Default
`

Full details in the docs.

---

## Documentation

Complete manual (architecture, DB schema, AJAX API, hooks, FAQ):

👉 docs/documentation.html — open in any browser

---

## Requirements

| | Minimum |
|---|---|
| WordPress | 6.0+ |
| PHP | 8.0+ |
| MySQL | 5.7+ |

---

## License

- **Author:** Abid Ali
- **License:** GPL v2 or later
- **Tested up to:** WordPress 6.7
