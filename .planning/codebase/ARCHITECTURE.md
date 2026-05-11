# Architecture

**Analysis Date:** 2026-05-11

## Overview

`retractation2026` is a PrestaShop 8+ module that implements the French legal right of withdrawal (droit de rétractation) for e-commerce orders. It allows customers — including guests — to submit retractation requests online, and gives shop administrators a back-office dashboard to accept or reject those requests with automatic email notification.

The module follows the standard PrestaShop module architecture: a single entry-point class (`Retractation2026`) registers hooks and manages install/uninstall lifecycle. Business logic lives in a dedicated service class. Persistence is handled through direct `Db::getInstance()` calls for writes and a thin `ObjectModel` subclass for the schema definition.

## Domain Model

**Single entity: `Retractation`**

Defined in `retractation2026/classes/Retractation.php` as a PrestaShop `ObjectModel` subclass. Fields:

| Field | Type | Notes |
|-------|------|-------|
| `id_retractation` | INT UNSIGNED PK | Auto-increment |
| `id_order` | INT UNSIGNED | FK → `ps_orders` |
| `id_customer` | INT UNSIGNED | FK → `ps_customer` (0-able for guests) |
| `id_shop` | INT UNSIGNED | Multi-shop isolation |
| `reason` | TEXT | Optional, customer-provided |
| `status` | VARCHAR(32) | State machine: `pending` → `accepted` / `rejected` / `cancelled` |
| `retractation_date` | DATETIME | Moment the customer submitted |
| `deadline_date` | DATETIME | Computed eligibility deadline stored at submission |
| `deadline_source` | VARCHAR(32) | `delivered`, `shipped`, or `order` — which date was used |
| `ip_address` | VARCHAR(45) | Logged for legal traceability |
| `date_add` / `date_upd` | DATETIME | Standard PS timestamps |

**Status state machine:**
```
[not submitted]
       │
       ▼
   pending  ──accept──▶  accepted
       │
       └──reject──▶  rejected
       │
       └──(admin)──▶  cancelled
```
Only `pending` requests can be actioned by the admin. `cancelled` routes to the `rejected` email template.

## Request Flow

### Customer submits retractation (authenticated or guest)

1. Customer lands on `module-retractation2026-request` page (URL rewritten as `/retractation`).
   - Entry: `retractation2026/controllers/front/request.php` — `Retractation2026RequestModuleFrontController::initContent()`
2. **No `id_order` in URL** → show lookup form (`show_lookup = true` → `request.tpl`).
3. **Guest lookup (`submitLookup`)** → `lookupOrderByReference()` matches email + reference against `ps_orders` + `ps_customer`.
4. **Order found** → `RetractationEligibilityService::getEligibility()` is called.
   - `retractation2026/classes/RetractationEligibilityService.php`
   - Eligibility logic (in priority order):
     1. Check `RETRACTATION_ENABLED` config flag.
     2. Reject cancelled/refunded orders (`PS_OS_CANCELED`, `PS_OS_REFUND`).
     3. Find first `delivery` state date in `ps_order_history` → deadline = delivered_date + 14 days (no buffer).
     4. Else find first `shipped` state date → deadline = shipped_date + 14 + buffer_shipped days.
     5. Else fall back to `order.date_add` → deadline = order_date + 14 + buffer_order days.
   - `source` field records which path was taken (`delivered` / `shipped` / `order`).
5. **Eligibility confirmed, no existing request** → show pre-filled form (`request.tpl` with `show_lookup = false`).
6. **Customer submits form (`submitRetractation`)** → `postProcess()` runs:
   - CSRF token validated against `Tools::getToken(false)`.
   - `loadOrderAndVerifyAccess()` re-checks ownership (logged customer: `id_customer` match; guest: reference + email match).
   - Eligibility re-checked (double validation).
   - Duplicate check: no existing non-cancelled request for same order.
   - `Db::getInstance()->insert('retractation', $data)` — direct insert, not via ObjectModel save.
   - `Mail::Send()` sends `retractation_confirmation` template if `RETRACTATION_EMAIL_ENABLED`.
7. **On success** → `$retractationData` populated, `initContent()` redirects to `confirmation.tpl`.

### Hook: order detail page

`hookDisplayOrderDetail()` in `retractation2026/retractation2026.php`:
- Calls `RetractationEligibilityService::getEligibility()`.
- If eligible, renders `views/templates/hook/displayOrderDetail.tpl` with a CTA button linking to the request form.
- Passes `guest_email` and `order_reference` as URL params for guest orders.

## Admin Flow

### BO dashboard list

`retractation2026/controllers/admin/AdminRetractationDashboardController.php` extends `ModuleAdminController`.

- Tab registered under `AdminParentOrders` (visible as "Rétractations" in FR).
- `fields_list` drives the standard PS grid: ID, order reference, customer name, status badge, dates, deadline source.
- LEFT JOINs `ps_orders` and `ps_customer` to show denormalized data.
- Filtered by `id_shop` for multi-shop safety.
- Export enabled (`allow_export = true`).

### Viewing a request

`renderView()` builds raw HTML (no Smarty template — inline HTML string generation):
- Shows full detail table: order link, customer, email, status badge, dates, IP, reason.
- If status is `pending`, renders Accept and Reject buttons as anchor tags pointing to `statusretractation` action URLs.

### Accepting / Rejecting

`postProcess()` handles `statusretractation` GET action:
- Validates `new_status` against allowed values: `accepted`, `rejected`, `cancelled`.
- Runs `Db::getInstance()->update()` setting new status + `date_upd`.
- Calls `sendStatusEmail($idRetractation, $newStatus)`.
- Redirects back to the view page.

### Status email dispatch

`sendStatusEmail()`:
- `accepted` → sends `retractation_accepted` template.
- `rejected` → sends `retractation_rejected` template.
- `cancelled` → maps to `rejected` template (same wording).
- Email is sent using PS `Mail::Send()` with module mail directory override (`mails/`).
- Template vars: `{firstname}`, `{lastname}`, `{order_reference}`, `{retractation_date}`, `{retractation_time}`, `{shop_name}`, `{shop_url}`, `{reject_reason}`.

## Data Layer

**Table:** `ps_retractation` (single table, no joins needed for reads from the model).

**Indexes:**
- `idx_order` on `id_order`
- `idx_customer` on `id_customer`
- `idx_shop` on `id_shop`
- `idx_status` on `status`

**ORM approach:** Mixed.
- `Retractation extends ObjectModel` is used only for schema definition (`$definition`). No `->save()` or `->add()` calls exist in the codebase.
- All actual reads and writes use `Db::getInstance()` directly: `getValue()`, `getRow()`, `executeS()`, `insert()`, `update()`.
- `DbQuery` builder is used in `renderView()` for the detail query.

**SQL file handling:** `executeSqlFile()` in the main module class reads `sql/install.sql` or `sql/uninstall.sql`, replaces `PREFIX_` with `_DB_PREFIX_` and `ENGINE_TYPE` with `_MYSQL_ENGINE_`, then executes in one shot.

## Key Design Decisions

**Hook-driven UI surface.** The module injects UI into existing PrestaShop pages via 7 registered hooks rather than building standalone pages for every touch point:
- `displayOrderDetail` — CTA on order detail (front)
- `displayCustomerAccount` — link in customer account dashboard
- `displayAdminOrderSide` — sidebar card in BO order view
- `actionOrderStatusPostUpdate` — (registered but no handler implemented — reserved)
- `displayProductAdditionalInfo` — legal notice on product pages
- `displayShoppingCartFooter` — legal notice in cart
- `displayHeader` — (registered, no visible handler — likely reserved for CSS injection)

**Guest support without account.** `$auth = false` on the request controller. Guest access is verified by matching email + order reference at lookup time, then by passing `guest_email` + `order_reference` as hidden fields through the form.

**Deadline stored at submission.** `deadline_date` and `deadline_source` are written to the record at insert time, not recomputed on each view. This creates an audit trail of the eligibility calculation that was in effect when the customer submitted.

**Configurable deadline buffers.** Three `Configuration` values govern deadline calculation:
- `RETRACTATION_DELAY_DAYS` (default 14) — legal base delay
- `RETRACTATION_BUFFER_SHIPPED` (default 7) — extra days when only shipped date is known
- `RETRACTATION_BUFFER_ORDER` (default 14) — extra days when only order date is known

**No Smarty template for BO detail view.** `renderView()` generates raw HTML strings directly. This is a deliberate simplicity tradeoff but departs from PS conventions.

**CSRF protection on form.** `Tools::getToken(false)` is used as a hidden field `retractation_token`, validated in `postProcess()` before processing the submission.

**Multi-shop isolation.** All queries filter on `id_shop = Shop::getContextShopID()`. The `install()` method sets `Shop::CONTEXT_ALL` before SQL execution.

**Duplicate prevention.** Before inserting, the controller checks for any existing non-cancelled request on the same order. Only one active request per order is allowed.

---

*Architecture analysis: 2026-05-11*
