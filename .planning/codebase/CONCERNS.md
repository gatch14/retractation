# Concerns & Tech Debt

**Analysis Date:** 2026-05-11

## Security Risks

**CSRF token is too broad:**
- `request.php` validates against `Tools::getToken(false)` — the global session token rather than a form-specific nonce. Any page on the site that leaks the token can be used to replay the retractation form.

**Guest email exposed in GET parameters:**
- `hookDisplayOrderDetail` builds a URL containing `guest_email=customer@example.com` as a plain query parameter. This leaks the email in browser history, server logs, and referrer headers.

**Missing email validation for guest path:**
- `request.php` compares `$guestEmail` against the order's customer email without first running `Validate::isEmail()`. Malformed input reaches the comparison directly.

**Smarty XSS — inconsistent escaping:**
- `{$order_reference}` is output without `|escape:'html'` in `request.tpl`, while all other variables in the same template use `|escape`. Order references from a malicious source could inject HTML.

**Admin action authorization:**
- Accept/Reject actions in the admin controller rely on the implicit PrestaShop token check inherited from `AdminController`. There is no explicit assertion in `postProcess()` — correctness depends on PS internals not changing.

**Silent email failure in BO:**
- `AdminRetractationDashboardController::sendStatusEmail()` calls `@Mail::Send()` with error suppression. Failed status notification emails are silently lost — the admin has no indication of delivery failure.

## Code Quality Issues

**Duplicated eligibility logic:**
- The eligibility check + duplicate-request guard is copy-pasted in at least 3 places in `request.php`. Should be consolidated into a single call to `RetractationEligibilityService`.

**Hardcoded date format `d/m/Y`:**
- Date formatting in email dispatch uses the French format `d/m/Y` for both FR and EN templates. English-speaking customers receive a date in the wrong regional format.

**Admin view built by string concatenation:**
- The BO detail panel is rendered via raw PHP string concatenation rather than a Smarty template. This makes it hard to maintain and increases XSS surface area.

**Legacy translation API in admin controller:**
- `AdminRetractationDashboardController` uses `$this->module->l()` throughout instead of the PrestaShop 8 standard `$this->trans()` / `$this->module->trans()`. Mixed approaches across the codebase.

**`require_once` inside hook methods:**
- `hookDisplayOrderDetail` and `hookDisplayAdminOrderSide` call `require_once dirname(__FILE__) . '/classes/RetractationEligibilityService.php'` inline. This should be loaded via autoloader or at the top of the class.

## PrestaShop Compatibility

**No deprecation audit done:** Module targets PS 8.0–9.99. No check has been run for deprecated APIs (`AdminController::$currentIndex`, `Tools::getToken()`, `HelperForm`, `Db::getInstance()`) that are progressively removed in PS 9.x.

**`Db::getInstance()` raw queries:** Several places bypass the ORM and use raw `Db::getInstance()->execute()` / `getRow()`. These are not type-safe and will need updating if PS moves away from this pattern.

**Bootstrap flag:** `$this->bootstrap = true` is set, which assumes the back-office uses Bootstrap 4. PS 9 may ship with Bootstrap 5 — untested.

## Business Logic Gaps

**Virtual/downloadable products not excluded:**
- French law (L221-28 Code de la consommation) exempts digital goods from the right of withdrawal. The `RetractationEligibilityService` has no check for `is_virtual` on order products. A customer could legally submit a retractation for a digital purchase.

**`cancelled` status sends "rejected" email:**
- When an admin sets a request to `cancelled`, the `sendStatusEmail()` logic dispatches the `retractation_rejected` template. This is legally and semantically misleading — cancellation and rejection are distinct states.

**No admin notification on new request:**
- There is no email or BO notification sent to the shop admin when a customer submits a new retractation request. Admins must proactively check the dashboard.

**No rate limiting:**
- The front form has no rate limiting or captcha. A bot could flood the `ps_retractation` table with dummy requests.

**No deduplication across cancelled requests:**
- The duplicate-request guard checks for any existing row for the order. If an admin cancels a request, the customer cannot re-submit even if they still have legal standing.

**Multi-shop: partial isolation:**
- `id_shop` is stored and filtered in the admin list, but several raw queries in hook methods omit the `id_shop` condition. In a multi-shop setup, requests from other shops may bleed through.

## Missing Features / TODOs

- No admin notification email when new retractation submitted
- No export (CSV/PDF) of retractation records
- No pagination on the customer-facing `retractationlist` if a customer has many requests
- No cron/cleanup job for old records
- `getContent()` config page has no validation — negative values for delay days are accepted silently

## Localization Gaps

**Translation domain split:**
- Front templates use `{l s='...' mod='retractation2026'}` (resolved via `translations/fr.php`)
- Front controllers use `$this->trans('...', [], 'Modules.Retractation2026.Front')` (resolved via XLF)
- These two sources are maintained independently with no tooling to detect drift or missing keys.

**English email templates:** The `en/` email templates exist but have not been verified for natural English phrasing (commit history shows only FR was corrected in the QA recette cycle).

**Missing admin translation:** `Modules.Retractation2026.Admin` domain strings are defined inline in PHP with no corresponding XLF — not extractable by PrestaShop's translation tooling.

## Infrastructure Concerns

**Destructive uninstall:**
- `uninstall.sql` runs `DROP TABLE IF EXISTS ps_retractation`. All retractation history is permanently deleted with no export prompt or backup step.

**`installFooterLink()` fragile dependency:**
- The footer link is injected directly into `ps_link_block_lang` JSON. This depends on the `ps_linklist` module being installed and having at least one block attached to `displayFooter`. If that block doesn't exist, the link is silently not created with no warning.

**Uncommitted changes in git:**
- `AdminRetractationDashboardController.php`, `logo.png`, and all 4 accept/reject email templates show as modified in working tree but not staged. The current ZIP package (`chore: update ZIP package`) may not reflect these latest changes.

**No Composer autoloading:**
- Classes are loaded via `require_once` inside methods. Adding new classes requires manual `require_once` calls throughout — error-prone and not IDE-friendly.
