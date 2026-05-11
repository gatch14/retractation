# Directory Structure

**Analysis Date:** 2026-05-11

## Root Layout

```
retractation2026/               ← git repo root
├── retractation2026/           ← PrestaShop module package
│   ├── retractation2026.php    ← Module entry point (extends Module)
│   ├── config.xml              ← Module metadata
│   ├── logo.png                ← Module logo (displayed in BO module manager)
│   ├── .htaccess               ← Apache security (deny direct access)
│   ├── index.php               ← Security blank (deny direct access)
│   ├── classes/                ← Domain model & services
│   ├── controllers/            ← Front and admin controllers
│   ├── views/                  ← Smarty templates, CSS, JS
│   ├── mails/                  ← Email templates (FR + EN)
│   ├── sql/                    ← Install / uninstall SQL
│   └── translations/           ← XLF (new) + legacy fr.php
├── scripts/                    ← Node.js logo generation scripts (dev only)
├── package.json                ← Node.js dev tooling (sharp, ws)
└── .planning/                  ← GSD planning artifacts
```

## Module Entry Point — `retractation2026.php`

The main class `Retractation2026 extends Module` handles:

**Registration on install:**
- Runs `sql/install.sql` (creates `ps_retractation` table)
- Sets `Configuration` keys with defaults (delay: 14d, buffers: 7d/14d, enabled: 1, email: 1)
- Adds BO tab `AdminRetractationDashboard` under `AdminParentOrders`
- Registers meta page `module-retractation2026-request` (SEO URL: `/retractation`)
- Injects footer link "Droit de rétractation" into `ps_link_block_lang`
- Registers 7 hooks (see INTEGRATIONS.md)

**Config keys:**
| Key | Default | Description |
|-----|---------|-------------|
| `RETRACTATION_DELAY_DAYS` | 14 | Legal withdrawal period (days) |
| `RETRACTATION_BUFFER_SHIPPED` | 7 | Days added when only shipped date known |
| `RETRACTATION_BUFFER_ORDER` | 14 | Days added when only order date known |
| `RETRACTATION_ENABLED` | 1 | Toggle module on/off |
| `RETRACTATION_EMAIL_ENABLED` | 1 | Toggle confirmation email |

**Config form** (`getContent()`): rendered via `HelperForm`, 3 text inputs + 2 switches.

## Controllers

### Front Controllers (`controllers/front/`)

**`request.php`** — `Retractation2026RequestModuleFrontController`
- Route: `module-retractation2026-request` (URL: `/retractation`)
- Auth: `$auth = false` (accessible to guests)
- Two modes:
  - **Lookup mode** (guest, no `id_order` param): shows email + reference form
  - **Form mode** (logged-in or with valid `id_order`): shows pre-filled retractation form
- On POST: validates CSRF token, checks duplicate, inserts `ps_retractation` record, sends confirmation email
- Smarty vars: `retractation_form_data`, `retractation_deadline`, `show_lookup`, `errors`

**`retractationlist.php`** — `Retractation2026RetractationlistModuleFrontController`
- Route: `module-retractation2026-retractationlist`
- Auth: `$auth = true` (login required)
- Displays all retractation requests for the logged-in customer
- Smarty vars: `retractations`

### Admin Controller (`controllers/admin/`)

**`AdminRetractationDashboardController.php`** — `AdminRetractationDashboardController`
- Tab: appears under Orders menu in BO
- List view: ID, Order, Customer, Status, Retractation date, Deadline, Source, Created
- Detail view: full request info with Accept / Reject action buttons
- `processStatusUpdate()`: updates status, calls `sendStatusEmail()`
- `sendStatusEmail()`: dispatches `retractation_accepted` or `retractation_rejected` template
  - Note: `cancelled` status maps to `retractation_rejected` template
  - Uses `@Mail::Send()` (errors suppressed silently)

## Views & Templates (`views/templates/`)

### Hook templates (`views/templates/hook/`)
| Template | Hook | Smarty vars |
|----------|------|-------------|
| `displayOrderDetail.tpl` | `displayOrderDetail` | `retractation_eligible`, `retractation_deadline`, `retractation_url` |
| `displayCustomerAccount.tpl` | `displayCustomerAccount` | `retractation_list_url` |
| `admin_order_side.tpl` | `displayAdminOrderSide` | `retractation_request`, `retractation_eligibility`, `retractation_module_link` |
| `displayShoppingCartFooter.tpl` | `displayShoppingCartFooter` | _(none)_ |
| `displayProductAdditionalInfo.tpl` | `displayProductAdditionalInfo` | _(none)_ |
| `displayFooter.tpl` | `displayFooter` | _(none — link injected via DB, not this template)_ |

### Front templates (`views/templates/front/`)
| Template | Controller | Purpose |
|----------|------------|---------|
| `request.tpl` | `request.php` | Retractation form (lookup or pre-filled) |
| `retractationlist.tpl` | `retractationlist.php` | Customer's retractation history |
| `confirmation.tpl` | `request.php` (redirect) | Submission success page |

## Classes (`classes/`)

**`Retractation.php`** — `Retractation extends ObjectModel`
- ORM mapping for `ps_retractation` table
- Fields: `id_order`, `id_customer`, `id_shop`, `reason`, `status`, `retractation_date`, `deadline_date`, `deadline_source`, `ip_address`, `date_add`, `date_upd`
- Status values: `pending`, `accepted`, `rejected`, `cancelled`

**`RetractationEligibilityService.php`** — `RetractationEligibilityService`
- Pure stateless service (no constructor dependencies)
- `getEligibility(int $idOrder): array` — returns `['eligible' => bool, 'deadline' => string, 'source' => string]`
- Deadline calculation logic:
  1. Prefer delivery date if order has `delivered` status
  2. Fall back to shipped date + `RETRACTATION_BUFFER_SHIPPED`
  3. Fall back to order date + `RETRACTATION_BUFFER_ORDER`
- Returns `eligible = false` if order is cancelled, refunded, or past deadline

## Mails (`mails/`)

```
mails/
├── en/
│   ├── retractation_confirmation.html/.txt   ← Sent on form submission
│   ├── retractation_accepted.html/.txt        ← Sent on BO Accept
│   └── retractation_rejected.html/.txt        ← Sent on BO Reject/Cancel
├── fr/
│   └── (same 3 templates × 2 formats)
└── index.php                                  ← Security blank
```

Template variables: `{firstname}`, `{lastname}`, `{order_reference}`, `{retractation_date}`, `{retractation_time}`, `{shop_name}`, `{shop_url}`

## SQL Schema (`sql/`)

**Table: `ps_retractation`**
| Column | Type | Notes |
|--------|------|-------|
| `id_retractation` | INT UNSIGNED PK AUTO_INCREMENT | |
| `id_order` | INT UNSIGNED | FK to `ps_orders` |
| `id_customer` | INT UNSIGNED | FK to `ps_customer` |
| `id_shop` | INT UNSIGNED DEFAULT 1 | Multi-shop isolation |
| `reason` | TEXT | Customer-provided reason |
| `status` | VARCHAR(32) DEFAULT 'pending' | pending/accepted/rejected/cancelled |
| `retractation_date` | DATETIME | When retractation was submitted |
| `deadline_date` | DATETIME | Legal deadline calculated at submission |
| `deadline_source` | VARCHAR(32) DEFAULT 'order' | delivered/shipped/order |
| `ip_address` | VARCHAR(45) | IPv4 or IPv6 at submission |
| `date_add` | DATETIME | Record creation timestamp |
| `date_upd` | DATETIME | Last update timestamp |

Indexes: `idx_order`, `idx_customer`, `idx_shop`, `idx_status`

**`uninstall.sql`:** `DROP TABLE IF EXISTS ps_retractation`

## Where to Add New Code

| Task | Location |
|------|----------|
| New business rule for eligibility | `classes/RetractationEligibilityService.php` |
| New database field | `sql/install.sql` + `classes/Retractation.php::$definition` |
| New hook | `retractation2026.php::install()` + new `hookXxx()` method + template |
| New BO action | `controllers/admin/AdminRetractationDashboardController.php` |
| New email trigger | `mails/fr/` + `mails/en/` + dispatch call in controller |
| New front page | New `controllers/front/xxx.php` + `views/templates/front/xxx.tpl` |
