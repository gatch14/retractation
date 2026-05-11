# Testing

**Analysis Date:** 2026-05-11

## Test Coverage

**Automated tests: none.** The repository contains no PHPUnit, Codeception, Pest, or any other test framework configuration. There are no `tests/`, `spec/`, or `__tests__/` directories. The root `package.json` has a `test` script that outputs `"Error: no test specified"` and exits with code 1 — confirming no JS test tooling is configured either.

No `.phpunit.xml`, `phpunit.xml.dist`, `codeception.yml`, or `phpstan.neon` files exist.

## Manual Testing Approach

Based on git history and module structure, the following flows constitute the QA surface:

**Install/Uninstall flow:**
- Install module via PrestaShop back-office module manager
- Verify `ps_retractation` table created with correct schema (`sql/install.sql`)
- Verify BO tab "Rétractations" appears under Orders menu
- Verify footer link "Droit de rétractation" injected into `ps_link_block_lang`
- Uninstall: verify table dropped, tab removed, footer link removed, config keys deleted

**Configuration page (`getContent()`):**
- Navigate to module config in BO
- Modify `RETRACTATION_DELAY_DAYS`, `RETRACTATION_BUFFER_SHIPPED`, `RETRACTATION_BUFFER_ORDER`
- Toggle `RETRACTATION_ENABLED` and `RETRACTATION_EMAIL_ENABLED` switches
- Save and verify values persisted

**Front-office request form — logged-in customer path:**
- Log in as a customer with at least one eligible order (within retractation window)
- Navigate to order detail page — verify "Renoncer au contrat ici" link appears
- Follow link to `module-retractation2026-request` page
- Verify customer name, email, order reference pre-filled (read-only)
- Verify deadline date displayed
- Submit form — verify redirect to `confirmation.tpl` with correct order reference and date
- Verify confirmation email sent (if `RETRACTATION_EMAIL_ENABLED = 1`)
- Verify record inserted in `ps_retractation` with `status = 'pending'`

**Front-office request form — guest/non-logged path:**
- Access `module-retractation2026-request` without being logged in
- Verify lookup form shown (`show_lookup = true`)
- Enter email + order reference — verify order details loaded
- Submit retractation — verify same confirmation flow as above

**Eligibility guard cases to test manually:**
- Order with `status = cancelled` or `refunded` → not eligible, no form shown
- Order past the `RETRACTATION_DELAY_DAYS + buffer` window → not eligible
- Duplicate submission on same order → error "A retractation request already exists"
- CSRF token tampered → error "Invalid security token"

**Back-office dashboard (`AdminRetractationDashboardController`):**
- Navigate to Orders > Rétractations
- Verify list loads with columns: ID, Order, Customer, Status, Retractation date, Deadline, Source, Created
- Filter by status dropdown
- Click View on a pending request — verify detail panel with Accept/Reject buttons
- Click Accept → verify status updated to `accepted`, email sent, redirect back to detail
- Click Reject → verify status updated to `rejected`, email sent
- Verify `cancelled` status routes to `retractation_rejected` email template

**Hook display testing:**
- `displayOrderDetail`: eligible order shows deadline + link; ineligible shows nothing
- `displayCustomerAccount`: "Mes demandes de rétractation" link appears on account page
- `displayAdminOrderSide`: panel on BO order page shows status badge or eligibility
- `displayShoppingCartFooter`: legal retractation notice shown in cart
- `displayProductAdditionalInfo`: notice shown on product page

**Email templates (FR + EN):**
- `retractation_confirmation.html/.txt` — sent on form submission
- `retractation_accepted.html/.txt` — sent on BO Accept action
- `retractation_rejected.html/.txt` — sent on BO Reject or Cancel action
- Template vars to verify: `{firstname}`, `{lastname}`, `{order_reference}`, `{retractation_date}`, `{retractation_time}`, `{shop_name}`, `{shop_url}`

## Known Test Gaps

**No automated coverage for:**
- `RetractationEligibilityService::getEligibility()` — the core business logic (deadline calculation, delivered/shipped/order source selection) is entirely untested. This is the highest-risk gap.
- CSRF token validation in front controller `postProcess()`
- Duplicate retractation guard
- Guest access path (email + reference lookup)
- `AdminRetractationDashboardController::sendStatusEmail()` — `cancelled` status silently maps to `rejected` email template; this mapping is not documented or tested
- SQL injection protection paths (`pSQL`, `bqSQL` usage)
- Multi-shop isolation (`id_shop` filtering on all queries)
- `installFooterLink()` / `uninstallFooterLink()` — link block manipulation logic has conditional edge cases not validated

**Translation inconsistency gap:** Front templates use legacy `{l s='...' mod='retractation2026'}` syntax but front controllers use `$this->trans('...', [], 'Modules.Retractation2026.Front')`. The XLF file covers the `trans()` domain; the `fr.php` legacy file covers the `{l}` tag domain. These are maintained separately with no cross-validation tooling — drift between them is possible and not caught by any check.

## QA Artifacts

**Git history evidence of QA fixes (from commit messages):**

- `fix: recette QA — traductions FR, emails accept/reject, BO amélioré, formulaire UX` (commit `6c266a4`)
  - FR translations corrected
  - Accept/reject email templates fixed
  - BO admin view improved
  - Form UX changes

- `fix: support guest retractation, footer link, CSRF token, SQL LIMIT` (commit `76006fe`)
  - Guest retractation flow added post-initial release
  - Footer link injection bug fixed
  - CSRF token validation added
  - SQL query had missing `LIMIT` clause (now fixed)

- `chore: update ZIP package with latest QA fixes` (commit `685d4da`)
  - Distribution ZIP rebuilt after QA cycle

**Recette test file:** No dedicated QA checklist or recette document found in the repository. The commit messages describe a manual QA cycle conducted before the ZIP packaging step, but no artifact is committed.

**Known remaining issue from code inspection:** `@Mail::Send()` in `AdminRetractationDashboardController::sendStatusEmail()` uses the `@` error suppression operator. Email delivery failures are silently swallowed — no error is surfaced to the admin. The front controller's `Mail::Send()` call does not suppress errors. This asymmetry means failed status emails in the BO will go unnoticed.
