#!/usr/bin/env bash
# S06 Slice Verification Script

PASS=0
FAIL=0
BASE="retractation2026"
MODULE="$BASE/retractation2026.php"
CONTROLLER="$BASE/controllers/front/retractationlist.php"
TPL_CART="$BASE/views/templates/hook/displayShoppingCartFooter.tpl"
TPL_PRODUCT="$BASE/views/templates/hook/displayProductAdditionalInfo.tpl"
TPL_ACCOUNT="$BASE/views/templates/hook/displayCustomerAccount.tpl"
TPL_LIST="$BASE/views/templates/front/retractationlist.tpl"

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

echo "=== S06 Slice Verification ==="
echo ""

# --- File existence (5 checks) ---
echo "[File existence]"
for f in "$TPL_CART" "$TPL_PRODUCT" "$TPL_ACCOUNT" "$CONTROLLER" "$TPL_LIST"; do
  if [ -f "$f" ]; then
    check "File exists: $f" "0"
  else
    check "File exists: $f" "1"
  fi
done

# --- Hook handlers (3 checks) ---
echo ""
echo "[Hook handlers]"
for hook in hookDisplayCustomerAccount hookDisplayShoppingCartFooter hookDisplayProductAdditionalInfo; do
  if grep -q "$hook" "$MODULE" 2>/dev/null; then
    check "$hook method exists in module" "0"
  else
    check "$hook method exists in module" "1"
  fi
done

# --- Hook registration (2 checks) ---
echo ""
echo "[Hook registration]"
if grep -q "'displayShoppingCartFooter'" "$MODULE" 2>/dev/null; then
  check "displayShoppingCartFooter in HOOKS constant" "0"
else
  check "displayShoppingCartFooter in HOOKS constant" "1"
fi

if grep -q "registerHook('displayShoppingCartFooter')" "$MODULE" 2>/dev/null; then
  check "registerHook displayShoppingCartFooter in install()" "0"
else
  check "registerHook displayShoppingCartFooter in install()" "1"
fi

# --- Controller structure (4 checks) ---
echo ""
echo "[Controller structure]"
if grep -q 'Retractationlist' "$CONTROLLER" 2>/dev/null; then
  check "Class name contains Retractationlist" "0"
else
  check "Class name contains Retractationlist" "1"
fi

if grep -q 'extends ModuleFrontController' "$CONTROLLER" 2>/dev/null; then
  check "Extends ModuleFrontController" "0"
else
  check "Extends ModuleFrontController" "1"
fi

if grep -q 'auth = true' "$CONTROLLER" 2>/dev/null; then
  check "auth = true set" "0"
else
  check "auth = true set" "1"
fi

if grep -q '_PS_VERSION_' "$CONTROLLER" 2>/dev/null; then
  check "Controller has _PS_VERSION_ guard" "0"
else
  check "Controller has _PS_VERSION_ guard" "1"
fi

# --- Multistore (2 checks) ---
echo ""
echo "[Multistore]"
if grep -q 'id_shop' "$CONTROLLER" 2>/dev/null; then
  check "id_shop filter in retractationlist controller" "0"
else
  check "id_shop filter in retractationlist controller" "1"
fi

if grep -qE 'context->shop->id|getContextShopID' "$CONTROLLER" 2>/dev/null; then
  check "Shop context usage in controller" "0"
else
  check "Shop context usage in controller" "1"
fi

# --- i18n (4 checks) ---
echo ""
echo "[i18n]"
for tpl in "$TPL_CART" "$TPL_PRODUCT" "$TPL_ACCOUNT" "$TPL_LIST"; do
  if grep -q "d='Modules.Retractation2026.Front'" "$tpl" 2>/dev/null; then
    check "i18n domain in $(basename "$tpl")" "0"
  else
    check "i18n domain in $(basename "$tpl")" "1"
  fi
done

# --- Security (4 checks) ---
echo ""
echo "[Security]"
if grep -q '(int)' "$CONTROLLER" 2>/dev/null; then
  check "(int) casting in controller" "0"
else
  check "(int) casting in controller" "1"
fi

if grep -q '_DB_PREFIX_' "$CONTROLLER" 2>/dev/null; then
  check "_DB_PREFIX_ usage in controller" "0"
else
  check "_DB_PREFIX_ usage in controller" "1"
fi

HARDCODED_PS=0
for f in "$TPL_CART" "$TPL_PRODUCT" "$TPL_ACCOUNT" "$TPL_LIST" "$CONTROLLER"; do
  if grep -qE "'ps_|\"ps_" "$f" 2>/dev/null; then
    HARDCODED_PS=1
  fi
done
check "No hardcoded ps_ prefix in new files" "$HARDCODED_PS"

if grep -q '_PS_VERSION_' "$CONTROLLER" 2>/dev/null; then
  check "_PS_VERSION_ guard in controller" "0"
else
  check "_PS_VERSION_ guard in controller" "1"
fi

# --- Anti-patterns (2 checks) ---
echo ""
echo "[Anti-patterns]"
DANGEROUS=0
for f in "$TPL_CART" "$TPL_PRODUCT" "$TPL_ACCOUNT" "$TPL_LIST" "$CONTROLLER"; do
  if grep -qE 'serialize\(|eval\(' "$f" 2>/dev/null; then
    DANGEROUS=1
  fi
done
check "No serialize() or eval() in new files" "$DANGEROUS"

SERVER_ACCESS=0
for f in "$TPL_CART" "$TPL_PRODUCT" "$TPL_ACCOUNT" "$TPL_LIST" "$CONTROLLER"; do
  if grep -q '$_SERVER' "$f" 2>/dev/null; then
    SERVER_ACCESS=1
  fi
done
check "No direct \$_SERVER access in new files" "$SERVER_ACCESS"

# --- Summary ---
echo ""
TOTAL=$((PASS + FAIL))
echo "=== Results: $PASS/$TOTAL passed, $FAIL failed ==="

if [ "$FAIL" -gt 0 ]; then
  exit 1
fi
exit 0
