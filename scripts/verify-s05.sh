#!/usr/bin/env bash
# S05 Slice Verification Script

PASS=0
FAIL=0
BASE="retractation2026"
CONTROLLER="$BASE/controllers/admin/AdminRetractationDashboardController.php"
MODULE="$BASE/retractation2026.php"
TEMPLATE="$BASE/views/templates/hook/admin_order_side.tpl"

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

echo "=== S05 Slice Verification ==="
echo ""

# --- File existence checks ---
echo "[File existence]"
if [ -f "$CONTROLLER" ]; then
  check "Controller file exists" "0"
else
  check "Controller file exists" "1"
fi

if [ -f "$TEMPLATE" ]; then
  check "Admin order side template exists" "0"
else
  check "Admin order side template exists" "1"
fi

# --- Controller structure checks ---
echo ""
echo "[Controller structure]"
if grep -q 'extends ModuleAdminController' "$CONTROLLER" 2>/dev/null; then
  check "Extends ModuleAdminController" "0"
else
  check "Extends ModuleAdminController" "1"
fi

if grep -q "table = 'retractation'" "$CONTROLLER" 2>/dev/null; then
  check "Table set to retractation" "0"
else
  check "Table set to retractation" "1"
fi

if grep -q 'fields_list' "$CONTROLLER" 2>/dev/null; then
  check "fields_list defined" "0"
else
  check "fields_list defined" "1"
fi

if grep -q '_select' "$CONTROLLER" 2>/dev/null; then
  check "_select with join query" "0"
else
  check "_select with join query" "1"
fi

if grep -q '_join' "$CONTROLLER" 2>/dev/null; then
  check "_join with orders/customer tables" "0"
else
  check "_join with orders/customer tables" "1"
fi

if grep -qE '_defaultOrderBy|_defaultOrderWay' "$CONTROLLER" 2>/dev/null; then
  check "Default order defined" "0"
else
  check "Default order defined" "1"
fi

if grep -q 'getContextShopID' "$CONTROLLER" 2>/dev/null; then
  check "Multi-shop filter with getContextShopID" "0"
else
  check "Multi-shop filter with getContextShopID" "1"
fi

# --- Module file checks ---
echo ""
echo "[Module hooks and Tab]"
if grep -q 'hookDisplayAdminOrderSide' "$MODULE" 2>/dev/null; then
  check "hookDisplayAdminOrderSide method exists" "0"
else
  check "hookDisplayAdminOrderSide method exists" "1"
fi

if grep -q 'AdminRetractationDashboard' "$MODULE" 2>/dev/null; then
  check "Tab class AdminRetractationDashboard in install" "0"
else
  check "Tab class AdminRetractationDashboard in install" "1"
fi

if grep -q 'getIdFromClassName' "$MODULE" 2>/dev/null; then
  check "Tab::getIdFromClassName in uninstall" "0"
else
  check "Tab::getIdFromClassName in uninstall" "1"
fi

if grep -q 'getEligibility' "$MODULE" 2>/dev/null; then
  check "getEligibility called from hook" "0"
else
  check "getEligibility called from hook" "1"
fi

# --- Template checks ---
echo ""
echo "[Template]"
if grep -qE '\{l s=|\{\$' "$TEMPLATE" 2>/dev/null; then
  check "Template uses Smarty syntax" "0"
else
  check "Template uses Smarty syntax" "1"
fi

if grep -q 'retractation_request' "$TEMPLATE" 2>/dev/null; then
  check "Template uses retractation_request variable" "0"
else
  check "Template uses retractation_request variable" "1"
fi

if grep -q 'retractation_eligibility' "$TEMPLATE" 2>/dev/null; then
  check "Template uses retractation_eligibility variable" "0"
else
  check "Template uses retractation_eligibility variable" "1"
fi

# --- Security checks ---
echo ""
echo "[Security]"
if grep -q '_PS_VERSION_' "$CONTROLLER" 2>/dev/null; then
  check "Controller has _PS_VERSION_ guard" "0"
else
  check "Controller has _PS_VERSION_ guard" "1"
fi

if grep -q '(int)' "$CONTROLLER" 2>/dev/null; then
  check "Controller uses (int) casting" "0"
else
  check "Controller uses (int) casting" "1"
fi

if grep -q '(int)' "$MODULE" 2>/dev/null; then
  check "Module uses (int) casting in hook" "0"
else
  check "Module uses (int) casting in hook" "1"
fi

if grep -q '_DB_PREFIX_' "$CONTROLLER" 2>/dev/null; then
  check "Controller uses _DB_PREFIX_" "0"
else
  check "Controller uses _DB_PREFIX_" "1"
fi

if grep -q '_DB_PREFIX_' "$MODULE" 2>/dev/null; then
  check "Module uses _DB_PREFIX_" "0"
else
  check "Module uses _DB_PREFIX_" "1"
fi

HARDCODED_PS=0
if grep -qE "'ps_|\"ps_" "$CONTROLLER" 2>/dev/null; then
  HARDCODED_PS=1
fi
if grep -qE "'ps_|\"ps_" "$MODULE" 2>/dev/null; then
  HARDCODED_PS=1
fi
check "No hardcoded ps_ table prefix" "$HARDCODED_PS"

# --- Anti-pattern checks ---
echo ""
echo "[Anti-patterns]"
DANGEROUS=0
if grep -qE 'serialize\(|eval\(' "$CONTROLLER" 2>/dev/null; then
  DANGEROUS=1
fi
if grep -qE 'serialize\(|eval\(' "$MODULE" 2>/dev/null; then
  DANGEROUS=1
fi
check "No serialize() or eval() calls" "$DANGEROUS"

# --- Summary ---
echo ""
TOTAL=$((PASS + FAIL))
echo "=== Results: $PASS/$TOTAL passed, $FAIL failed ==="

if [ "$FAIL" -gt 0 ]; then
  exit 1
fi
exit 0
