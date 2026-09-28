# Retractation 2026 - Module Documentation

## Overview

**Retractation 2026** is a PrestaShop module that automates the management of the consumer right of withdrawal (droit de retractation) in compliance with French law (Ordonnance n 2026-2). It provides a complete workflow for customers to exercise their 14-day withdrawal right, with configurable deadline calculation, pre-filled withdrawal forms, confirmation emails, and a dedicated admin dashboard.

### Key Features

- Automated eligibility calculation with configurable 14-day withdrawal period
- Cascade date priority: Delivered date > Shipped date > Order date, with configurable buffer days for each fallback
- Pre-filled retractation request form for customers
- Confirmation email sent automatically upon withdrawal request
- Retractation history accessible in the customer account area (Mon Compte)
- Admin dashboard under Orders menu with list, filter, and sort capabilities
- Order side panel in admin showing retractation status or eligibility
- Precontractual withdrawal notices on product pages and shopping cart
- Full multistore support

## Installation

1. Download the `retractation2026.zip` file from the PrestaShop Addons marketplace
2. In your PrestaShop Back Office, navigate to **Modules > Module Manager**
3. Click **Upload a module** and select the ZIP file
4. The module installs automatically and registers all required hooks
5. Navigate to **Modules > Module Manager**, find "Retractation 2026", and click **Configure**

No manual database changes are required. The module creates its `retractation` table automatically on install and removes it cleanly on uninstall.

## Configuration

Access the configuration page via **Back Office > Modules > Module Manager > Retractation 2026 > Configure**.

| Setting | Description | Default |
|---------|-------------|---------|
| Delai legal de retractation (jours) | Number of calendar days for the legal withdrawal period | 14 |
| Buffer expedition (jours) | Extra days added when only the shipped date is known (no delivery date) | 7 |
| Buffer commande (jours) | Extra days added when only the order date is known (no shipped/delivery date) | 14 |
| Module actif | Enable or disable the module globally | Yes |
| Envoyer un email de confirmation | Send a confirmation email to the customer upon withdrawal request | Yes |

### Configuration Keys

- `RETRACTATION_DELAY_DAYS` - Legal withdrawal period in days
- `RETRACTATION_BUFFER_SHIPPED` - Buffer days for shipped-only orders
- `RETRACTATION_BUFFER_ORDER` - Buffer days for order-date-only fallback
- `RETRACTATION_ENABLED` - Module activation toggle
- `RETRACTATION_EMAIL_ENABLED` - Email notification toggle

## Usage - Customer Side

### Requesting a Withdrawal

1. The customer navigates to their order detail page
2. If the order is eligible for withdrawal, a **retractation button** is displayed (via `displayOrderDetail` hook)
3. Clicking the button opens a pre-filled retractation form with order details
4. The customer submits the form to confirm their withdrawal
5. A confirmation page displays the withdrawal date and time
6. If email is enabled, the customer receives a confirmation email

### Retractation History

Customers can view all their retractation requests from their account area (**Mon Compte > Mes retractations**), accessible via the `displayCustomerAccount` hook.

### Precontractual Notices

The module displays withdrawal right notices on:
- Product pages (via `displayProductAdditionalInfo` hook)
- Shopping cart footer (via `displayShoppingCartFooter` hook)

These notices inform customers of their withdrawal rights before purchase.

## Usage - Merchant Side

### Admin Dashboard

A dedicated **Retractations** tab is added under the **Orders** menu in the Back Office. The dashboard provides:

- List of all retractation requests with date, order reference, customer name, and status
- Filtering and sorting capabilities
- Direct link to the associated order

### Order Side Panel

When viewing an individual order in the Back Office, the right side panel displays:
- The current retractation request (if one exists) with status and date
- Eligibility status if no request has been made yet
- Link to the retractation dashboard

This is provided via the `displayAdminOrderSide` hook.

## Multistore

The module fully supports PrestaShop's multistore feature:

- Configuration values are independent per shop
- Retractation data is filtered by shop ID (`id_shop` column)
- The admin dashboard shows only retractation requests for the current shop context
- Each shop can have different delay and buffer settings

## Compatibility

| Requirement | Version |
|-------------|---------|
| PrestaShop | 8.0.0 to 9.99.99 |
| PHP | 7.4+ (as required by PrestaShop 8) |

### Registered Hooks

| Hook | Purpose |
|------|---------|
| `displayOrderDetail` | Show withdrawal button on order detail |
| `displayCustomerAccount` | Add retractation history link in customer account |
| `displayAdminOrderSide` | Show retractation panel in admin order view |
| `displayProductAdditionalInfo` | Precontractual notice on product page |
| `displayShoppingCartFooter` | Precontractual notice in cart |

## Legal Context

This module implements the consumer right of withdrawal as defined by **Ordonnance n 2026-2**, which reinforces Articles L.221-18 to L.221-28 of the French Consumer Code (Code de la consommation).

Key legal points:
- Consumers have a **14-day withdrawal period** from the date of receipt of goods
- The merchant must inform the consumer of this right before purchase (precontractual information)
- The consumer can exercise this right without justification and without penalty
- The module calculates the deadline using a cascade: delivery date (preferred), shipped date + buffer, or order date + buffer

This module assists merchants in complying with these requirements but does **not constitute legal advice**. Merchants should consult a legal professional to ensure full compliance.

## Support

For support, feature requests, or bug reports:
- Visit the module page on [PrestaShop Addons](https://addons.prestashop.com)
- Contact the developer at the email provided on the Addons listing

## License

This module is licensed under the Academic Free License version 3.0 (AFL-3.0).
