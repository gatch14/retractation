# Integrations

**Analysis Date:** 2026-05-11

## PrestaShop Hooks

All hooks are registered in `retractation2026/retractation2026.php` via `registerHook()` calls in `install()`.

| Hook | Handler Method | Purpose |
|------|---------------|---------|
| `displayOrderDetail` | `hookDisplayOrderDetail()` | Shows retractation button on customer order detail page when order is eligible |
| `displayCustomerAccount` | `hookDisplayCustomerAccount()` | Adds "My retractation requests" link to customer account page |
| `displayAdminOrderSide` | `hookDisplayAdminOrderSide()` | Shows retractation status panel in admin order sidebar |
| `actionOrderStatusPostUpdate` | (registered, no handler found in main file) | Reserved — hook registered but hook method not present in `retractation2026.php` v1.0.0 |
| `displayProductAdditionalInfo` | `hookDisplayProductAdditionalInfo()` | Displays legal withdrawal notice on product page |
| `displayShoppingCartFooter` | `hookDisplayShoppingCartFooter()` | Displays withdrawal rights notice in cart |
| `displayHeader` | (registered, no handler found in main file) | Reserved — hook registered but hook method not present in main file |

**Hook display templates:**
- `displayOrderDetail` → `retractation2026/views/templates/hook/displayOrderDetail.tpl`
- `displayCustomerAccount` → `retractation2026/views/templates/hook/displayCustomerAccount.tpl`
- `displayAdminOrderSide` → `retractation2026/views/templates/hook/admin_order_side.tpl`
- `displayProductAdditionalInfo` → `retractation2026/views/templates/hook/displayProductAdditionalInfo.tpl`
- `displayShoppingCartFooter` → `retractation2026/views/templates/hook/displayShoppingCartFooter.tpl`
- `displayFooter` → `retractation2026/views/templates/hook/displayFooter.tpl` (template exists; hook not explicitly registered — may be legacy)

## Email System

**Engine:** PrestaShop native `Mail::Send()` — no external mail service.

**Mail directory:** `retractation2026/mails/` (passed as 11th argument to `Mail::Send()`)

**Templates (HTML + TXT pairs):**

| Template name | Trigger | Languages |
|--------------|---------|-----------|
| `retractation_confirmation` | Customer submits retractation request (front controller `request.php`) | `fr/`, `en/` |
| `retractation_accepted` | Admin accepts a pending request (`AdminRetractationDashboardController::sendStatusEmail()`) | `fr/`, `en/` |
| `retractation_rejected` | Admin rejects a pending request OR sets status to `cancelled` (`sendStatusEmail()` maps `cancelled` → `rejected` template) | `fr/`, `en/` |

**Template variables injected:**
- `{firstname}`, `{lastname}` — customer name
- `{order_reference}` — PS order reference string
- `{retractation_date}`, `{retractation_time}` — formatted date/time
- `{reason}` — customer reason text (confirmation email only)
- `{shop_name}`, `{shop_url}` — store branding
- `{reject_reason}` — standard rejection message (accepted/rejected emails only)

**Email sending conditions:**
- Confirmation: gated by `Configuration::get('RETRACTATION_EMAIL_ENABLED')` (default: 1)
- Status updates: always sent when status changes to accepted/rejected/cancelled (no gate)

**Recipient:** Customer email address from `ps_customer` table.

## Admin Controllers

**Controller class:** `AdminRetractationDashboardController`
- File: `retractation2026/controllers/admin/AdminRetractationDashboardController.php`
- Extends: `ModuleAdminController`
- Table: `retractation`
- Identifier: `id_retractation`

**Admin Tab:**
- Class name: `AdminRetractationDashboard`
- Parent tab: `AdminParentOrders` (registered under Orders menu)
- Tab label: "Rétractations" (fr) / "Retractations" (other languages)
- Registered/removed automatically on install/uninstall

**Admin URL:**
- `$this->context->link->getAdminLink('AdminRetractationDashboard')`

**Features:**
- List view with sortable/filterable columns: ID, order reference, customer name, status, retractation date, deadline, source, created date
- CSV export enabled (`$this->allow_export = true`)
- Detail view via `renderView()` — shows full request info + order link
- Status actions: Accept / Reject buttons visible when status is `pending`
- Status update via GET param `statusretractation` + `new_status` (accepted, rejected, cancelled)
- Status badge rendering via `getStatusBadge()` callback (Bootstrap badge classes)

**Configuration page:**
- Accessible via PrestaShop Modules page → Configure
- Method: `getContent()` in `retractation2026/retractation2026.php`
- Form rendered with `HelperForm`
- Configurable settings:
  - `RETRACTATION_DELAY_DAYS` (default: 14)
  - `RETRACTATION_BUFFER_SHIPPED` (default: 7)
  - `RETRACTATION_BUFFER_ORDER` (default: 14)
  - `RETRACTATION_ENABLED` (default: 1)
  - `RETRACTATION_EMAIL_ENABLED` (default: 1)

## Front Controllers

**Controller 1 — Retractation Request Form:**
- Class: `Retractation2026RequestModuleFrontController`
- File: `retractation2026/controllers/front/request.php`
- Route: `module-retractation2026-request` (URL rewrite: `/retractation`)
- Auth required: `$this->auth = false` — accessible by guests and logged-in customers
- Templates:
  - `retractation2026/views/templates/front/request.tpl` — form display
  - `retractation2026/views/templates/front/confirmation.tpl` — post-submission success

**Access modes supported:**
1. Logged-in customer with `id_order` param — direct link from order detail hook
2. Guest with `id_order` + `guest_email` + `order_reference` params — link from order email
3. Anonymous lookup via `submitLookup` form — enter email + order reference to find order

**Form actions:**
- `submitLookup` — finds order by email + reference, shows form if eligible
- `submitRetractation` — submits the retractation (CSRF token validated via `Tools::getToken(false)`)

**Eligibility logic:**
- Delegates to `RetractationEligibilityService::getEligibility()` (`retractation2026/classes/RetractationEligibilityService.php`)
- Checks: module enabled, order not cancelled/refunded, deadline not expired
- Deadline computed from: delivery date > shipped date > order date, with configurable buffers

**Controller 2 — Customer Retractation History:**
- Class: `Retractation2026RetractationlistModuleFrontController`
- File: `retractation2026/controllers/front/retractationlist.php`
- Route: `module-retractation2026-retractationlist`
- Auth required: `$this->auth = true` (redirects to `my-account` if not logged in)
- Template: `retractation2026/views/templates/front/retractationlist.tpl`
- Scope: lists all retractation records for the current customer + current shop

## Footer Link Integration

**Auto-installed on module install** via `installFooterLink()` in `retractation2026/retractation2026.php`.

- Finds the most recently positioned link block attached to the `displayFooter` hook
- Injects a custom link to the `module-retractation2026-request` URL into `ps_link_block_lang.custom_content` (JSON array)
- Link label: "Droit de rétractation" (fr) / "Right of withdrawal" (other)
- Removed on uninstall via `uninstallFooterLink()` — filters out any link with "retractation" in the URL

**Dependency:** Requires the `ps_linklist` module (or equivalent) to be installed and have at least one link block on `displayFooter`. If no block exists, link is silently not added.

## Translation System

**Two parallel translation systems (PrestaShop legacy + Symfony XLF):**

**System 1 — Symfony XLF (PS 8.x native):**
- File: `retractation2026/translations/fr-FR/ModulesRetractation2026Front.xlf`
- Domain: `Modules.Retractation2026.Front`
- Covers: all front-office strings
- Source language: `en-US`, target language: `fr-FR`
- Used via `$this->trans('...', [], 'Modules.Retractation2026.Front')` in front controllers

**System 2 — Legacy PHP array (PS 1.7 compat):**
- File: `retractation2026/translations/fr.php`
- Format: `$_MODULE['<{retractation2026}prestashop>controller_hash'] = 'FR string';`
- Covers: all admin controller strings + all front controller strings (complete duplication)
- Used via `$this->module->l('...', 'ControllerClassName')` in admin controller

**Translation domains used:**
- `Modules.Retractation2026.Admin` — admin-facing strings (module name, config page labels)
- `Modules.Retractation2026.Front` — customer-facing strings (form labels, error messages, confirmations)

**Supported languages:**
- French (`fr`) — full translations in XLF and legacy PHP
- English — source strings only (no EN XLF file; EN is the default fallback)

---

*Integration audit: 2026-05-11*
