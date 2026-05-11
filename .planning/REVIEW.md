---
phase: retractation2026-deep-review
reviewed: 2026-05-11T00:00:00Z
depth: deep
files_reviewed: 16
files_reviewed_list:
  - retractation2026/retractation2026.php
  - retractation2026/classes/Retractation.php
  - retractation2026/classes/RetractationEligibilityService.php
  - retractation2026/controllers/front/request.php
  - retractation2026/controllers/front/retractationlist.php
  - retractation2026/controllers/admin/AdminRetractationDashboardController.php
  - retractation2026/views/templates/front/request.tpl
  - retractation2026/views/templates/front/retractationlist.tpl
  - retractation2026/views/templates/front/confirmation.tpl
  - retractation2026/views/templates/hook/displayOrderDetail.tpl
  - retractation2026/views/templates/hook/displayCustomerAccount.tpl
  - retractation2026/views/templates/hook/admin_order_side.tpl
  - retractation2026/views/templates/hook/displayShoppingCartFooter.tpl
  - retractation2026/views/templates/hook/displayProductAdditionalInfo.tpl
  - retractation2026/sql/install.sql
  - retractation2026/sql/uninstall.sql
findings:
  critical: 9
  warning: 11
  info: 5
  total: 25
status: issues_found
---

# Code Review Report — retractation2026

**Reviewed:** 2026-05-11
**Depth:** deep
**Files Reviewed:** 16
**Status:** issues_found

## Summary

This PrestaShop 8/9 module implements the French droit de rétractation (14-day withdrawal period). The codebase is functional in its happy path but has multiple security vulnerabilities, two confirmed legal non-compliance issues under the Code de la consommation, a state-mutation bug that sends wrong emails, and several structural weaknesses. Issues span all layers: SQL, PHP controllers, Smarty templates, and admin actions. None of the critical issues require exotic exploit conditions — all are straightforward to trigger.

---

## Critical Issues

### CR-01: CSRF token is global session token, not form-specific nonce

**File:** `retractation2026/controllers/front/request.php:115`
**Issue:** `Tools::getToken(false)` returns the PS global front session token, which is the same value for every form in the session. Any page on the shop that the customer visits embeds this same token (e.g. the standard cart page uses it for AJAX calls). An attacker who lures the customer to a malicious page can read the token out of any PS-rendered page in a same-origin context and replay it against the retractation form. More critically, the token does not change between requests, so a CSRF attack via a pre-known token from a prior session observation remains valid. This is not a proper CSRF nonce.

**Fix:** Generate a form-specific, single-use nonce stored in the session:
```php
// In initContent(), when building the form:
$token = bin2hex(random_bytes(16));
$this->context->cookie->retractation_nonce = $token;

// In postProcess():
if (Tools::getValue('retractation_token') !== $this->context->cookie->retractation_nonce) {
    $this->errors[] = $this->trans('Invalid security token.', [], 'Modules.Retractation2026.Front');
    return;
}
unset($this->context->cookie->retractation_nonce); // consume nonce
```

---

### CR-02: `guest_email` leaked as plaintext GET parameter, no validation in loadOrderAndVerifyAccess

**File:** `retractation2026/retractation2026.php:284`, `retractation2026/controllers/front/request.php:70`
**Issue:** In `hookDisplayOrderDetail`, the customer's email address is appended as a plain GET parameter:
```php
$linkParams['guest_email'] = $customer->email;
```
This email lands in server access logs, browser history, HTTP `Referer` headers to third parties, and is visible to anyone who sees the URL. Additionally, `loadOrderAndVerifyAccess` at line 70 applies `pSQL()` to the `guest_email` value but never calls `Validate::isEmail()` on it before comparing against the database. An attacker can test whether arbitrary email addresses match orders by fuzzing the parameter.

**Fix:** Never pass the email in the URL. Instead, require the guest to prove their identity through the lookup form (`submitLookup`) before showing the retractation form. Remove `guest_email` from `$linkParams` entirely:
```php
// hookDisplayOrderDetail: for guests, link to the lookup page, not to the pre-filled form
$retractation_url = $this->context->link->getModuleLink('retractation2026', 'request');
// loadOrderAndVerifyAccess: add email validation
if (!Validate::isEmail($guestEmail)) {
    $this->errors[] = ...;
    return null;
}
```

---

### CR-03: Admin status change actions use GET requests with no CSRF protection

**File:** `retractation2026/controllers/admin/AdminRetractationDashboardController.php:107-110`, `151`
**Issue:** Accept/Reject actions are plain `<a href="...">` links (GET requests). The URLs contain `&statusretractation&new_status=accepted`. `postProcess()` at line 151 acts on these GET parameters immediately without requiring a POST body or any admin-specific CSRF token beyond what `getAdminLink()` already embeds. While `getAdminLink()` does include a `token` parameter tied to the admin session, those tokens are exposed in rendered HTML of the admin view itself. Any XSS in the admin BO (including from user-supplied `reason` field — see CR-05) can trivially forge these URLs and auto-accept or auto-reject requests.

**Fix:** Replace anchor links with a POST form containing the built-in PS admin token:
```html
<form method="post" action="{$acceptUrl}">
  <input type="hidden" name="id_retractation" value="{$id}" />
  <input type="hidden" name="new_status" value="accepted" />
  <button type="submit" class="btn btn-success">Accept</button>
</form>
```

---

### CR-04: `cancelled` status sends a `retractation_rejected` email (wrong legal communication)

**File:** `retractation2026/controllers/admin/AdminRetractationDashboardController.php:211-215`
**Issue:** The `sendStatusEmail` method maps `cancelled` → `retractation_rejected` template:
```php
$emailStatus = ($status === 'cancelled') ? 'rejected' : $status;
$template = 'retractation_' . $emailStatus;
```
A cancellation (e.g., the customer changed their mind, or the request was found ineligible post-submission) is not a rejection by the merchant. Sending a "Your retractation has been rejected" email for a customer-initiated cancellation is legally incorrect — under L221-18 of the Code de la consommation, the customer retains their right of withdrawal if the 14-day period has not expired. Receiving a "rejected" email may mislead them into believing their right has been denied.

**Fix:** Either create a distinct `retractation_cancelled` email template, or suppress all email for the `cancelled` status:
```php
if (!in_array($status, ['accepted', 'rejected'])) {
    return; // no email for 'cancelled'
}
```

---

### CR-05: User-supplied `reason` injected into confirmation email without sanitisation

**File:** `retractation2026/controllers/front/request.php:173`
**Issue:** The `reason` field is passed directly from `Tools::getValue('reason')` into the email template vars without any HTML stripping or escaping:
```php
'{reason}' => Tools::getValue('reason'),
```
The `Retractation` ObjectModel declares `reason` as `TYPE_HTML` with `isCleanHtml` validation, but that validation only runs when using `ObjectModel::save()`. Here the data is inserted via `Db::getInstance()->insert()` directly (line 160) using `pSQL()`, which escapes SQL but does not strip HTML tags. As a result, the raw user input including HTML tags goes into the email. Many mail clients render HTML in plain-text parts; the HTML template also displays `{reason}` verbatim. This enables stored HTML/link injection in outbound emails.

**Fix:** Strip HTML before placing in the email and before storing:
```php
'{reason}' => htmlspecialchars(strip_tags(Tools::getValue('reason')), ENT_QUOTES, 'UTF-8'),
```

---

### CR-06: Eligibility does not exclude virtual/downloadable products (L221-28 legal gap)

**File:** `retractation2026/classes/RetractationEligibilityService.php` (entire file)
**Issue:** Article L221-28 of the Code de la consommation explicitly excludes digital content that has been executed with the consumer's prior agreement from the right of withdrawal. The `getEligibility()` method checks order status and dates but never inspects whether the order contains virtual products (`is_virtual = 1` on `order_detail`) or downloadable content. A shop selling digital goods will grant customers an unlawful right-of-withdrawal button on fully-executed digital orders.

**Fix:** Add a product-type check in `getEligibility()`:
```php
$hasOnlyVirtual = (bool) Db::getInstance()->getValue(
    'SELECT MIN(od.is_virtual) FROM `' . _DB_PREFIX_ . 'order_detail` od
     WHERE od.id_order = ' . $idOrder
);
if ($hasOnlyVirtual) {
    return $this->ineligible('Order contains only virtual/downloadable products (L221-28)');
}
```

---

### CR-07: `reason` field stored as raw HTML but rendered in admin BO via PHP concatenation — stored XSS

**File:** `retractation2026/controllers/admin/AdminRetractationDashboardController.php:132`
**Issue:** The `reason` field at line 132 is escaped with `htmlspecialchars()` before being concatenated into the admin HTML view, which prevents direct XSS in this path. However, `nl2br()` is called first, and the value comes from `$row['reason']` which was stored via `pSQL()` (not HTML-stripped) at submission time. If the `reason` field in the DB contains HTML tags that were stored by bypassing the ObjectModel validation (which it does — see CR-05), they pass through `htmlspecialchars()` as escaped text, so this particular output is safe. **But**: the `reason` field is typed as `TYPE_HTML` in `Retractation.php` and could be stored by other means with valid HTML. More importantly, `reason` is also echoed unescaped in outbound emails (see CR-05). This is a defence-in-depth failure.

**Fix:** Enforce sanitization at insertion time (strip HTML at the controller level before calling `Db::insert()`), not only at display time.

---

### CR-08: Destructive `DROP TABLE` on uninstall with no data export or confirmation

**File:** `retractation2026/sql/uninstall.sql:1`
**Issue:**
```sql
DROP TABLE IF EXISTS `PREFIX_retractation`;
```
This permanently deletes all customer retractation records with no backup, no export, and no reversibility check. Retractation records have legal significance under L221-18 — they constitute proof that a consumer exercised their right of withdrawal within the 14-day window. French consumer law implicitly requires retention of such records for dispute resolution. Losing them on uninstall exposes the merchant to legal risk.

**Fix:** Replace destructive DROP with a rename or archive pattern. At minimum, rename the table instead of dropping it:
```sql
RENAME TABLE `PREFIX_retractation` TO `PREFIX_retractation_archived`;
```
Or add an explicit BO warning in `uninstall()` and offer a data export step before allowing the drop.

---

### CR-09: `sendStatusEmail` in admin controller has no `id_shop` filter — can leak data across shops

**File:** `retractation2026/controllers/admin/AdminRetractationDashboardController.php:187-193`
**Issue:** The SQL query inside `sendStatusEmail()` fetches retractation data using only `r.id_retractation` with no `id_shop` constraint:
```php
'WHERE r.id_retractation = ' . (int) $idRetractation
```
In a multi-shop context, an admin scoped to Shop A can trigger a status change on a `id_retractation` belonging to Shop B if they can guess the ID (auto-increment integers). The `postProcess()` update at line 162 does include `id_shop`, which means the update will silently fail (0 rows affected), and `sendStatusEmail()` is then called on the original row — potentially sending an email to a customer from another shop.

**Fix:** Add `AND r.id_shop = ' . (int) Shop::getContextShopID()` to the `sendStatusEmail` query:
```php
'WHERE r.id_retractation = ' . (int) $idRetractation . '
 AND r.id_shop = ' . (int) Shop::getContextShopID()
```
And only call `sendStatusEmail()` when `$result` confirms rows were actually updated (currently it does at line 164 — but the race between update and email fetch remains).

---

## Warnings

### WR-01: `@Mail::Send()` error suppression hides delivery failures

**File:** `retractation2026/controllers/admin/AdminRetractationDashboardController.php:217`
**Issue:** The `@` operator suppresses all PHP errors and warnings from `Mail::Send()`. If email delivery fails (bad template path, SMTP misconfiguration, missing mail file), the failure is silently swallowed. The admin sees "Status updated" but the customer never receives the accept/reject notification.

**Fix:** Remove `@` and check the return value:
```php
$sent = Mail::Send(...);
if (!$sent) {
    $this->warnings[] = $this->module->l('Email notification could not be sent.', ...);
}
```

---

### WR-02: Duplicate eligibility check + duplicate `existing` query — three copies in request.php

**File:** `retractation2026/controllers/front/request.php:125-131`, `226-245`, `277-297`
**Issue:** The eligibility check and the "existing request" guard appear verbatim three times in `initContent()` and `postProcess()`. Any change to the eligibility logic (e.g., adding the L221-28 virtual product check from CR-06) must be applied in all three places. This is a maintenance trap that has already caused inconsistency — the three code paths are not identical (different error handling, different Smarty assigns).

**Fix:** Extract into a private method:
```php
private function checkOrderEligibility(Order $order): bool {
    $service = new RetractationEligibilityService();
    $eligibility = $service->getEligibility((int) $order->id);
    if (!$eligibility['eligible']) {
        $this->errors[] = $this->trans('This order is not eligible for retractation.', [], 'Modules.Retractation2026.Front');
        return false;
    }
    $existing = Db::getInstance()->getValue(
        'SELECT id_retractation FROM `' . _DB_PREFIX_ . 'retractation`
         WHERE id_order = ' . (int) $order->id . ' AND status != \'cancelled\''
    );
    if ($existing) {
        $this->errors[] = $this->trans('A retractation request already exists for this order.', [], 'Modules.Retractation2026.Front');
        return false;
    }
    return true;
}
```

---

### WR-03: `require_once` inside hook methods instead of module autoloader

**File:** `retractation2026/retractation2026.php:270`, `310`; `retractation2026/controllers/front/request.php:125`, `226`, `277`
**Issue:** `RetractationEligibilityService` is loaded via `require_once` inside individual hook methods and controller branches. In PS8/9, the module should declare its classes in `composer.json` autoload or use `$this->autoloadClasses()`. The current approach is fragile (path must be correct relative to `_PS_MODULE_DIR_`), fails elegantly only because `require_once` deduplicates, and is not the documented PS8 pattern.

**Fix:** Add a `composer.json` with PSR-4 autoload pointing to the `classes/` directory, or register classes in the module's `autoload.php`. Remove all inline `require_once` calls from hooks and controllers.

---

### WR-04: Hardcoded `d/m/Y` date format in emails and confirmation page — wrong for EN locale

**File:** `retractation2026/controllers/front/request.php:171`, `194`; `retractation2026/controllers/admin/AdminRetractationDashboardController.php:202`
**Issue:**
```php
'{retractation_date}' => date('d/m/Y', strtotime($now)),
'retractation_date' => date('d/m/Y', strtotime($now)),
```
Day/Month/Year format is correct for French customers but incorrect for English-locale shops (or any non-FR locale). An English customer receives a confirmation email with `11/05/2026` which they will read as November 5th, not May 11th. This is both a UX bug and can affect legal proof of the withdrawal date.

**Fix:** Use a locale-aware format or store the ISO date and let the template format it:
```php
'{retractation_date}' => Tools::displayDate($now, null, false),
```
Alternatively store `Y-m-d` in the template var and let the mail template handle locale formatting.

---

### WR-05: `actionOrderStatusPostUpdate` hook registered but never implemented

**File:** `retractation2026/retractation2026.php:34`, `98`
**Issue:** The `actionOrderStatusPostUpdate` hook is registered in both the `HOOKS` constant and in `install()`, but there is no `hookActionOrderStatusPostUpdate()` method anywhere in `retractation2026.php` or in any included class. PS will call this hook on every order status change and find no handler — wasted hook registration with potential future confusion about what this hook was meant to do (auto-cancel retractation when order is cancelled? auto-accept on refund?).

**Fix:** Either implement the hook handler or remove it from `HOOKS` and the `registerHook()` call in `install()`.

---

### WR-06: No admin notification email on new retractation submission

**File:** `retractation2026/controllers/front/request.php:166-191`
**Issue:** When a customer submits a retractation request, only the customer receives a confirmation email. The admin/merchant is not notified. In a real shop, the merchant needs to process the return and arrange a refund within 14 days (L221-24). A missed pending request discovered late could result in a legal deadline violation.

**Fix:** After sending the customer confirmation, also send an internal notification:
```php
Mail::Send(
    (int) $this->context->language->id,
    'retractation_admin_notification',
    'New retractation request — Order ' . $order->reference,
    $templateVars,
    Configuration::get('PS_SHOP_EMAIL'),
    Configuration::get('PS_SHOP_NAME'),
    ...
);
```

---

### WR-07: `installFooterLink()` directly mutates `link_block_lang` JSON (fragile coupling to ps_linklist schema)

**File:** `retractation2026/retractation2026.php:142-190`
**Issue:** This method reaches into the `ps_link_block_lang` table and mutates its `custom_content` JSON column directly. This is a private internal data format of the `ps_linklist` module that is not part of any public API. Any update to `ps_linklist` that changes the JSON schema (column rename, format change, multi-shop scope change) will silently break or corrupt the footer data. The `uninstallFooterLink()` method scans all rows across all languages without shop filtering (line 196-199), meaning it can corrupt footer links for shops other than the one being uninstalled.

**Fix:** Use the `ps_linklist` module's public hooks or its native admin interface. At minimum, add `AND id_shop = ...` to the uninstall query and document the coupling risk prominently.

---

### WR-08: `pSQL()` double-applied to `$email` and `$reference` before interpolation into SQL string

**File:** `retractation2026/controllers/front/request.php:20-37`
**Issue:**
```php
$email = pSQL(trim(Tools::getValue('lookup_email')));
$reference = pSQL(trim(Tools::getValue('lookup_reference')));
// then later:
WHERE o.reference = \'' . pSQL($reference) . '\'
AND c.email = \'' . pSQL($email) . '\''
```
`pSQL()` is called on the values at lines 20-21, then called again at lines 36-37. Double-escaping can corrupt valid input containing characters like `\` or `'`. For example, a reference like `A\'B` becomes `A\\'B` after the first `pSQL()`, then `A\\\\'B` after the second. The result is a SQL mismatch that fails to find real orders.

**Fix:** Apply `pSQL()` only once, at the interpolation point:
```php
$email = trim(Tools::getValue('lookup_email'));
$reference = trim(Tools::getValue('lookup_reference'));
// ...
WHERE o.reference = \'' . pSQL($reference) . '\'
AND c.email = \'' . pSQL($email) . '\''
```

---

### WR-09: `install()` does not roll back on partial failure

**File:** `retractation2026/retractation2026.php:65-102`
**Issue:** The `install()` method creates the SQL table, registers configuration values, creates the Tab, installs the Meta, and installs the footer link before calling `parent::install()`. If any step after `executeSqlFile()` fails (e.g., `$tab->add()` fails at line 87 and returns `false`), the method returns `false` but the DB table and configuration values already written are left in place. Re-attempting installation may hit "table already exists" or duplicate config scenarios.

**Fix:** Wrap the entire install sequence in a transaction, or implement compensating uninstall calls on failure:
```php
if (!$tab->add()) {
    $this->executeSqlFile('uninstall');
    Configuration::deleteByName('RETRACTATION_DELAY_DAYS');
    // ... clean up all previously written state
    return false;
}
```

---

### WR-10: `executeSqlFile()` runs the entire SQL file as a single `execute()` call — multi-statement injection risk

**File:** `retractation2026/retractation2026.php:428-441`
**Issue:** `Db::getInstance()->execute($sql)` is called with the full contents of `install.sql`. PrestaShop's `Db::execute()` uses `mysqli_multi_query` or PDO's `exec()` depending on the driver, which executes multiple statements. This is necessary for the SQL file, but the code does no error checking on individual statements. If `install.sql` is ever modified to include multiple statements, a failure in the middle leaves the DB in a partial state with no indication of which statement failed.

**Fix:** Split the SQL file on `;` and execute each statement individually, checking for errors:
```php
foreach (array_filter(explode(';', $sql)) as $statement) {
    if (!Db::getInstance()->execute(trim($statement))) {
        return false;
    }
}
```

---

### WR-11: No unique constraint on `(id_order, status)` in the database — race condition allows duplicate pending requests

**File:** `retractation2026/sql/install.sql`
**Issue:** The duplicate-request guard at `request.php:134-141` is a SELECT-then-INSERT pattern with no transaction and no DB-level uniqueness constraint. Under concurrent load (customer double-clicks submit, or two browser tabs both reach the form), two requests can both pass the SELECT check before either INSERT completes, resulting in two `pending` records for the same order. The only protection is the application-level check, which is not atomic.

**Fix:** Add a unique index and handle the duplicate-key error gracefully:
```sql
ALTER TABLE `PREFIX_retractation`
  ADD UNIQUE KEY `uq_order_active` (`id_order`, `id_shop`);
```
Then catch the duplicate-key exception in the controller instead of relying on the SELECT guard alone.

---

## Info

### IN-01: `{$order_reference}` output without `|escape` filter in two templates

**File:** `retractation2026/views/templates/front/confirmation.tpl:14`, `retractation2026/views/templates/front/request.tpl:83`
**Issue:**
```smarty
{$order_reference}                   {* confirmation.tpl:14 *}
value="{$order_reference}" readonly  {* request.tpl:83 *}
```
Order references from PS are alphanumeric and safe in practice, but outputting any template variable without an escape filter is a coding standard violation in PS8 Smarty templates. If the reference format ever changes (e.g., custom reference generators), this becomes an XSS vector.

**Fix:**
```smarty
{$order_reference|escape:'htmlall':'UTF-8'}
```

---

### IN-02: Legacy `$this->module->l()` instead of PS8 `trans()` throughout admin controller

**File:** `retractation2026/controllers/admin/AdminRetractationDashboardController.php` (all `l()` calls)
**Issue:** The admin controller uses `$this->module->l(...)` (the PS 1.6/1.7 pattern) throughout. PS8 modules should use `$this->trans('string', [], 'domain')` or the Symfony translation system. The old `l()` method is deprecated and may be removed in a future PS version.

**Fix:** Replace all `$this->module->l('...', 'AdminRetractationDashboardController')` with `$this->trans('...', [], 'Modules.Retractation2026.Admin')`.

---

### IN-03: `displayProductAdditionalInfo` claims all products are withdrawal-eligible with no product-type check

**File:** `retractation2026/views/templates/hook/displayProductAdditionalInfo.tpl:1-6`
**Issue:** The template unconditionally states "This product is eligible for the legal right of withdrawal." This is displayed on every product page regardless of product type. Virtual/downloadable products excluded under L221-28 will display a false eligibility claim to customers.

**Fix:** Pass a `$retractation_product_eligible` variable from `hookDisplayProductAdditionalInfo()` that checks `$params['product']['is_virtual']` before rendering the notice.

---

### IN-04: `Retractation` ObjectModel `id_customer` and `id_shop` fields are not `required`

**File:** `retractation2026/classes/Retractation.php:32-33`
**Issue:** Both `id_customer` and `id_shop` are optional (no `'required' => true`) in the ObjectModel definition. This means `ObjectModel::save()` would accept a record with `id_customer = 0` or `id_shop = 0` without error. The controller manually sets these, but the ORM provides no safety net.

**Fix:**
```php
'id_customer' => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
'id_shop'     => ['type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true],
```

---

### IN-05: `retractation_date` displayed raw (ISO format) in front-office list — poor UX

**File:** `retractation2026/views/templates/front/retractationlist.tpl:37-38`
**Issue:**
```smarty
<td>{$retractation.retractation_date|escape:'html':'UTF-8'}</td>
<td>{$retractation.deadline_date|escape:'html':'UTF-8'}</td>
```
These output the raw `Y-m-d H:i:s` database values. The customer sees `2026-05-11 14:32:07` instead of a locale-appropriate formatted date. The `date_format` modifier is available in Smarty and used elsewhere in the module.

**Fix:**
```smarty
<td>{$retractation.retractation_date|date_format:'%d/%m/%Y'}</td>
```
Or, better, pass pre-formatted dates from the controller using locale-aware formatting.

---

_Reviewed: 2026-05-11_
_Reviewer: Claude (gsd-code-reviewer)_
_Depth: deep_
