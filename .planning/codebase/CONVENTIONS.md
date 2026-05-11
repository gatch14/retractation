# Code Conventions

**Analysis Date:** 2026-05-11

## PHP Style

**Standard:** PSR-12 (braces on same line for control structures, separate line for class/method openings).

**Indentation:** 4 spaces. No tabs.

**Naming:**
- Classes: `PascalCase` — `Retractation`, `RetractationEligibilityService`, `AdminRetractationDashboardController`
- Methods: `camelCase` — `getEligibility()`, `computeResult()`, `installFooterLink()`
- Private methods: `camelCase` with `private` keyword — `ineligible()`, `executeSqlFile()`
- Properties: `camelCase` for class fields, `snake_case` for ObjectModel public fields (`$id_order`, `$date_add`)
- Local variables: `camelCase` — `$idOrder`, `$delayDays`, `$bufferShipped`
- Constants: `SCREAMING_SNAKE_CASE` — `RETRACTATION_DELAY_DAYS`, `CONFIG_KEYS`

**Type hints:** Used on method signatures where possible. Return types declared on service methods:
```php
public function getEligibility(int $idOrder): array
private function getStateDate(int $idOrder, string $stateColumn): ?string
private function computeResult(string $referenceDate, int $delayDays, int $bufferDays, string $source): array
```

**Casting:** Always explicit before DB write or comparison — `(int)`, `(bool)`, `pSQL()`:
```php
$idOrder = (int) Tools::getValue('id_order');
$newStatus = pSQL(Tools::getValue('new_status'));
Configuration::updateValue('RETRACTATION_ENABLED', (bool) Tools::getValue('RETRACTATION_ENABLED'));
```

**File header:** Every PHP file (including stub `index.php` files) opens with:
```php
<?php
/**
 * @author    GSD
 * @copyright GSD
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}
```

**No closing `?>` tag** in any PHP file.

## PrestaShop Conventions

**Module class:** Extends `Module`, named exactly as the module directory (`Retractation2026`).
- `$this->name` matches directory name: `'retractation2026'`
- `$this->tab` set to `'legal_compliance'`
- `$this->bootstrap = true` always set

**ObjectModel extension:** `Retractation extends ObjectModel` with static `$definition` array. Primary key follows pattern `id_<tablename>`. Fields use PrestaShop type constants (`self::TYPE_INT`, `self::TYPE_STRING`, `self::TYPE_HTML`, `self::TYPE_DATE`).

**Admin controller:** Extends `ModuleAdminController`. Class name pattern: `Admin{Feature}Controller` — `AdminRetractationDashboardController`.
- Tab registration: `class_name` is controller name without `Controller` suffix: `'AdminRetractationDashboard'`
- Translation in admin controllers uses `$this->module->l('...', 'AdminRetractationDashboardController')` (legacy `l()` method with file context, not `trans()`)

**Front controller:** Extends `ModuleFrontController`. Class name pattern: `{ModuleClass}{ControllerName}ModuleFrontController` — `Retractation2026RequestModuleFrontController`, `Retractation2026RetractationlistModuleFrontController`.
- Translation in front controllers uses `$this->trans('...', [], 'Modules.Retractation2026.Front')` (Symfony-style `trans()`)

**Hook registration:** Done in `install()` via chained `&&` on `registerHook()` calls. Hook names are listed in a `const HOOKS` array at the top of the module class.

**Hook method naming:** `hook{HookName}(array $params): string` — e.g., `hookDisplayOrderDetail(array $params): string`.

**Config keys:** Stored as `SCREAMING_SNAKE_CASE` strings starting with the module prefix (`RETRACTATION_`). Declared in `const CONFIG_KEYS` and `const CONFIG_DEFAULTS` arrays in the module class.

**SQL execution:** SQL files in `sql/install.sql` and `sql/uninstall.sql`, loaded via private `executeSqlFile($filename)` method. Placeholder `PREFIX_` replaced with `_DB_PREFIX_`, `ENGINE_TYPE` replaced with `_MYSQL_ENGINE_`.

**Classes loaded via `require_once`:** Service classes not autoloaded by PrestaShop are loaded with:
```php
require_once dirname(__FILE__) . '/classes/RetractationEligibilityService.php';
// or from controllers:
require_once _PS_MODULE_DIR_ . 'retractation2026/classes/RetractationEligibilityService.php';
```

**Output escaping:** HTML output always escaped with `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')` when built manually. In templates, always `|escape:'html':'UTF-8'` or `|escape:'htmlall':'UTF-8'`.

**Email sending:** Via `Mail::Send()` (static call). Template vars use `{placeholder}` notation (curly braces, lowercase). Email templates live in `mails/fr/` and `mails/en/`. The `@` error-suppression prefix (`@Mail::Send`) is used in the admin controller but not in the front controller — this is inconsistent.

## Smarty Templates

**Inheritance:** All front page templates use `{extends file='page.tpl'}` with `{block name='page_title'}` and `{block name='page_content'}` blocks.

**Hook templates:** Do not extend a layout — they are partial fragments only (no `{extends}`).

**Variable naming in templates:** `snake_case` prefixed with `retractation_` for module-specific vars — `$retractation_deadline`, `$retractation_request`, `$retractation_eligible`, `$retractation_token`, `$retractation_module_link`.

**CSS class patterns:** Bootstrap 4 utility classes (`card`, `card-body`, `card-header`, `form-group row`, `col-md-4`, `col-md-8`, `btn btn-primary`, `badge badge-warning`). Module-specific wrapper classes are `kebab-case` prefixed with `retractation-` — `retractation-form-container`, `retractation-list`.

**Status badge pattern:** Status values (`pending`, `accepted`, `rejected`, `cancelled`) always mapped to Bootstrap badge classes via `{if}/{elseif}` blocks in templates, or via `getStatusBadge()` callback in the admin list.

**Escaping:** All user-sourced variables escaped with `|escape:'html':'UTF-8'`. Date formatting uses `|date_format:'%d/%m/%Y %H:%M'`.

**Translation in templates:** Legacy `{l s='...' mod='retractation2026'}` syntax used throughout all templates (not the Symfony `{t}` tag). The `mod` attribute is always `retractation2026`.

## Translation Keys

**Two parallel systems coexist:**

1. **XLF (Symfony/PS 8 modern format):** `translations/fr-FR/ModulesRetractation2026Front.xlf`
   - Domain: `Modules.Retractation2026.Front` (dot-separated, PascalCase segments)
   - `resname` attribute holds a human-readable English string (the translation source key)
   - `id` attribute is a hex UUID — not human-readable
   - Used by front controllers via `$this->trans('...', [], 'Modules.Retractation2026.Front')`

2. **Legacy PHP array format:** `translations/fr.php`
   - Keys pattern: `<{retractation2026}prestashop>{context}_{md5hash}`
   - Context is the lowercase controller/template filename (e.g., `adminretractationdashboardcontroller`, `request`, `retractationlist`, `confirmation`)
   - Values are French translations
   - Used by templates via `{l s='...' mod='retractation2026'}` and by admin controller via `$this->module->l('...', 'ControllerClassName')`

**Admin domain:** `Modules.Retractation2026.Admin` — used in the module class `__construct()` via `$this->trans()`.

## File Naming

**Module main file:** `retractation2026.php` — matches directory name exactly.

**Classes:** `PascalCase.php` — `Retractation.php`, `RetractationEligibilityService.php`.

**Admin controllers:** `Admin{Feature}Controller.php` — `AdminRetractationDashboardController.php`. Directory: `controllers/admin/`.

**Front controllers:** `{featurename}.php` (lowercase, matches the controller URL slug) — `request.php`, `retractationlist.php`. Directory: `controllers/front/`.

**Templates:** `{featurename}.tpl` for front pages, `{hookName}.tpl` for hook partials (kebab-case where needed) — `request.tpl`, `confirmation.tpl`, `retractationlist.tpl`, `admin_order_side.tpl`, `displayOrderDetail.tpl`.

**Mail templates:** `retractation_{type}.html` / `retractation_{type}.txt` — `retractation_confirmation.html`, `retractation_accepted.html`, `retractation_rejected.html`.

**SQL files:** `install.sql`, `uninstall.sql` in `sql/` directory.

**Security stubs:** Every directory contains a one-line `index.php` that exits silently, blocking directory listing.

## SQL Conventions

**Table prefix:** Placeholder `PREFIX_` in SQL files, replaced at runtime with `_DB_PREFIX_`. In PHP queries, always concatenated as `` '`' . _DB_PREFIX_ . 'tablename`' ``.

**Table naming:** `snake_case`, no module prefix — `retractation` (not `retractation2026_retractation`).

**Column naming:** `snake_case`, prefixed with `id_` for foreign keys — `id_order`, `id_customer`, `id_shop`.

**Backtick quoting:** All table and column names backtick-quoted in raw SQL strings.

**Engine placeholder:** `ENGINE=ENGINE_TYPE` in DDL, replaced with `_MYSQL_ENGINE_` at install time.

**Charset:** `DEFAULT CHARSET=utf8mb4` on all tables.

**Indexes:** Named `idx_{column}` — `idx_order`, `idx_customer`, `idx_shop`, `idx_status`.

**Query building:** Mix of raw SQL strings and `DbQuery` object builder (`$sql = new DbQuery(); $sql->select(...)->from(...)`). Both patterns are used — prefer `DbQuery` for complex joins in admin views, raw strings in service/front layer.

**Input protection:** All values used in SQL wrapped with `pSQL()` for strings, `(int)` for integers, `bqSQL()` for column names used in queries dynamically.
