# Tech Stack

**Analysis Date:** 2026-05-11

## Languages & Runtimes

**Primary:**
- PHP — module core logic, controllers, classes, templates (all `.php` files)
  - No minimum PHP version declared in code; PrestaShop 8.x requires PHP 8.0+
- Smarty — view layer via `.tpl` files rendered by PrestaShop's template engine

**Secondary:**
- JavaScript (ES module, Node.js) — logo/banner generation scripts at project root only
  - `scripts/generate-logo.js`, `scripts/generate-banner.js`, `scripts/generate-banner-v2.js`, `scripts/process-logo.js`
  - These are dev tooling scripts, not part of the deployed module

**HTML:**
- Static email templates (`.html` + `.txt` pairs) in `retractation2026/mails/`

## Frameworks & Platforms

**Core Platform:**
- PrestaShop 8.0.0 – 9.99.99
  - Declared in `retractation2026/retractation2026.php`: `$this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.99.99']`
  - Module class extends `Module` (PrestaShop core)
  - Admin controller extends `ModuleAdminController`
  - Front controllers extend `ModuleFrontController`
  - ORM: `ObjectModel` (PrestaShop built-in, used by `Retractation` class)
  - DB access: `Db::getInstance()` with raw SQL + `DbQuery` builder
  - Settings: `Configuration::get()` / `Configuration::updateValue()`

**Templating:**
- Smarty — PrestaShop's native template engine
  - Templates in `retractation2026/views/templates/`
  - Assignment via `$this->context->smarty->assign([])`

**Bootstrap:**
- `$this->bootstrap = true` — module config page uses PrestaShop Bootstrap-based HelperForm

## Key Dependencies

**PrestaShop Core Classes Used:**
- `Module` — base module class
- `ModuleAdminController` — admin dashboard controller base
- `ModuleFrontController` — front office controller base
- `ObjectModel` — ORM base for `Retractation` model
- `Db` / `DbQuery` — database access
- `Configuration` — persistent key/value store
- `Tab` — admin menu tab registration
- `Meta` — SEO meta entry for front controller page
- `Mail` — PrestaShop email sending (used in `request.php` and `AdminRetractationDashboardController.php`)
- `Language` — multi-language iteration on install
- `Tools` — utility helpers (form values, CSRF token, remote addr, redirect)
- `Validate` — input validation (email, object loading)
- `Shop` — multi-shop context
- `Order`, `Customer`, `Context`, `Link` — standard PS objects

**No Composer / No External PHP Packages** — pure PrestaShop module, no vendor directory.

## Build & Tooling

**Node.js Scripts (dev only, not deployed):**
- `package.json` at project root (`C:/dev/gsd/microsaas/retractation2026/package.json`)
- Package name: `retractation2026`, version `1.0.0`, type `module`
- Dependencies:
  - `sharp ^0.34.5` — image processing (logo/banner generation)
  - `ws ^0.8.20.0` — WebSocket client (ComfyUI local API communication)
- Scripts: `npm run test` is a stub only (no real tests)
- These scripts are **not part of the PrestaShop module ZIP** — they are for asset generation only

**No build step for the module itself** — plain PHP, no transpilation, no asset pipeline.

**No Composer** — no `composer.json` found.

## Database

**Tables Created on Install:**

`PREFIX_retractation` (maps to `ps_retractation` by default):

| Column | Type | Notes |
|--------|------|-------|
| `id_retractation` | INT UNSIGNED AUTO_INCREMENT | Primary key |
| `id_order` | INT UNSIGNED NOT NULL | FK to `ps_orders` (not enforced) |
| `id_customer` | INT UNSIGNED NOT NULL | FK to `ps_customer` (not enforced) |
| `id_shop` | INT UNSIGNED NOT NULL DEFAULT 1 | Multi-shop support |
| `reason` | TEXT | Optional customer-provided reason |
| `status` | VARCHAR(32) NOT NULL DEFAULT 'pending' | Enum: pending, accepted, rejected, cancelled |
| `retractation_date` | DATETIME NOT NULL | When the customer submitted |
| `deadline_date` | DATETIME NOT NULL | Computed eligibility deadline |
| `deadline_source` | VARCHAR(32) NOT NULL DEFAULT 'order' | Values: delivered, shipped, order |
| `ip_address` | VARCHAR(45) | IPv4 or IPv6 |
| `date_add` | DATETIME NOT NULL | PrestaShop convention |
| `date_upd` | DATETIME NOT NULL | PrestaShop convention |

**Indexes:** `idx_order`, `idx_customer`, `idx_shop`, `idx_status`

**Engine:** Configurable via `ENGINE_TYPE` placeholder (resolved to `InnoDB` or `MyISAM` at install time).

**Install/Uninstall SQL:**
- `retractation2026/sql/install.sql` — `CREATE TABLE IF NOT EXISTS`
- `retractation2026/sql/uninstall.sql` — `DROP TABLE IF EXISTS`
- SQL executed via `executeSqlFile()` in main module class — replaces `PREFIX_` with `_DB_PREFIX_` and `ENGINE_TYPE` with `_MYSQL_ENGINE_`

**SQL Pattern Used in Controllers:**
- Raw SQL strings with `pSQL()` escaping for user input
- `Db::getInstance()->getValue()` for single values
- `Db::getInstance()->getRow()` for single rows
- `Db::getInstance()->executeS()` for result sets
- `Db::getInstance()->insert()` / `update()` for writes
- `DbQuery` builder used in admin controller's `renderView()`

---

*Stack analysis: 2026-05-11*
