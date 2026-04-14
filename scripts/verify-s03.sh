#!/usr/bin/env bash
# S03 Slice Verification Script

PASS=0
FAIL=0
BASE="retractation2026"
CTRL="$BASE/controllers/front/request.php"
MAIN="$BASE/retractation2026.php"
HOOK_TPL="$BASE/views/templates/hook/displayOrderDetail.tpl"
FORM_TPL="$BASE/views/templates/front/request.tpl"
CONF_TPL="$BASE/views/templates/front/confirmation.tpl"

check() {
  local desc="$1"
  local result="$2"
  if [ "$result" = "0" ]; then
    echo "  PASS: $desc"
    PASS=$((PASS + 1))
  else
    echo "  FAIL: $desc"
    FAIL=$((FAIL + 1))
  fi
}

echo "=== S03 Slice Verification ==="
echo ""

# --- File existence checks (5) ---
echo "[File existence]"
for f in "$CTRL" "$HOOK_TPL" "$FORM_TPL" "$CONF_TPL" "$BASE/controllers/front/index.php"; do
  if [ -f "$f" ]; then
    check "$f exists" "0"
  else
    check "$f exists" "1"
  fi
done

# --- Hook handler (3) ---
echo ""
echo "[Hook handler]"
if grep -q 'hookDisplayOrderDetail' "$MAIN" 2>/dev/null; then
  check "hookDisplayOrderDetail method in main module" "0"
else
  check "hookDisplayOrderDetail method in main module" "1"
fi

if grep -q 'RetractationEligibilityService' "$MAIN" 2>/dev/null; then
  check "Hook method references RetractationEligibilityService" "0"
else
  check "Hook method references RetractationEligibilityService" "1"
fi

if grep -q 'getEligibility' "$MAIN" 2>/dev/null; then
  check "Hook method references getEligibility" "0"
else
  check "Hook method references getEligibility" "1"
fi

# --- Front controller structure (4) ---
echo ""
echo "[Front controller structure]"
if grep -q 'Retractation2026RequestModuleFrontController' "$CTRL" 2>/dev/null; then
  check "Class name Retractation2026RequestModuleFrontController" "0"
else
  check "Class name Retractation2026RequestModuleFrontController" "1"
fi

if grep -q 'extends ModuleFrontController' "$CTRL" 2>/dev/null; then
  check "Extends ModuleFrontController" "0"
else
  check "Extends ModuleFrontController" "1"
fi

if grep -qE '\$this->auth\s*=\s*true|public\s+\$auth\s*=\s*true' "$CTRL" 2>/dev/null; then
  check "Sets auth = true" "0"
else
  check "Sets auth = true" "1"
fi

if grep -q '_PS_VERSION_' "$CTRL" 2>/dev/null; then
  check "Has _PS_VERSION_ guard" "0"
else
  check "Has _PS_VERSION_ guard" "1"
fi

# --- Security (5) ---
echo ""
echo "[Security]"
if grep -q 'id_customer' "$CTRL" 2>/dev/null; then
  check "Controller checks id_customer ownership" "0"
else
  check "Controller checks id_customer ownership" "1"
fi

if grep -q '(int)' "$CTRL" 2>/dev/null; then
  check "Controller uses (int) casting for id_order" "0"
else
  check "Controller uses (int) casting for id_order" "1"
fi

if grep -q 'pSQL' "$CTRL" 2>/dev/null; then
  check "Controller uses pSQL for string sanitization" "0"
else
  check "Controller uses pSQL for string sanitization" "1"
fi

if grep -q 'getToken' "$CTRL" 2>/dev/null; then
  check "Controller uses getToken for CSRF" "0"
else
  check "Controller uses getToken for CSRF" "1"
fi

if grep -q 'getRemoteAddr' "$CTRL" 2>/dev/null; then
  check "Controller uses getRemoteAddr for IP capture" "0"
else
  check "Controller uses getRemoteAddr for IP capture" "1"
fi

# --- SQL safety (2) ---
echo ""
echo "[SQL safety]"
if grep -q '_DB_PREFIX_' "$CTRL" 2>/dev/null; then
  check "Controller uses _DB_PREFIX_" "0"
else
  check "Controller uses _DB_PREFIX_" "1"
fi

HARDCODED_PS=0
if grep -qE "'ps_|\"ps_" "$CTRL" 2>/dev/null; then
  HARDCODED_PS=1
fi
check "No hardcoded ps_ table prefix in controller" "$HARDCODED_PS"

# --- Template content (4) ---
echo ""
echo "[Template content]"
if grep -q 'Renoncer au contrat ici' "$HOOK_TPL" 2>/dev/null; then
  check "Hook template contains 'Renoncer au contrat ici'" "0"
else
  check "Hook template contains 'Renoncer au contrat ici'" "1"
fi

if grep -q 'Confirmer' "$FORM_TPL" 2>/dev/null; then
  check "Form template contains 'Confirmer la rétractation'" "0"
else
  check "Form template contains 'Confirmer la rétractation'" "1"
fi

if grep -q 'token' "$FORM_TPL" 2>/dev/null; then
  check "Form template contains CSRF token field" "0"
else
  check "Form template contains CSRF token field" "1"
fi

if grep -qE 'retractation_date|retractation_time' "$CONF_TPL" 2>/dev/null; then
  check "Confirmation template references date/time display" "0"
else
  check "Confirmation template references date/time display" "1"
fi

# --- Duplicate prevention (1) ---
echo ""
echo "[Duplicate prevention]"
if grep -q 'id_retractation' "$CTRL" 2>/dev/null; then
  check "Controller checks for existing retractation before insert" "0"
else
  check "Controller checks for existing retractation before insert" "1"
fi

# --- Anti-patterns (2) ---
echo ""
echo "[Anti-patterns]"
DIRECT_SERVER=0
if grep -qE '\$_SERVER\[' "$CTRL" 2>/dev/null; then
  DIRECT_SERVER=1
fi
check "No direct \$_SERVER access in controller" "$DIRECT_SERVER"

DANGEROUS=0
if grep -qE 'serialize\(|eval\(' "$CTRL" "$MAIN" "$HOOK_TPL" "$FORM_TPL" "$CONF_TPL" 2>/dev/null; then
  DANGEROUS=1
fi
check "No serialize() or eval() in new PHP files" "$DANGEROUS"

# --- Summary ---
echo ""
TOTAL=$((PASS + FAIL))
echo "=== Results: $PASS/$TOTAL passed, $FAIL failed ==="

if [ "$FAIL" -gt 0 ]; then
  exit 1
fi
exit 0
