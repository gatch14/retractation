#!/usr/bin/env bash
# S01 Slice Verification Script

PASS=0
FAIL=0
BASE="retractation2026"
MAIN="$BASE/retractation2026.php"

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

echo "=== S01 Slice Verification ==="
echo ""

# --- File existence checks ---
echo "[File existence]"
for f in "$MAIN" "$BASE/sql/install.sql" "$BASE/sql/uninstall.sql" "$BASE/config.xml" "$BASE/logo.png"; do
  if test -f "$f"; then
    check "$f exists" "0"
  else
    check "$f exists" "1"
  fi
done

# --- Security index.php in all directories ---
echo ""
echo "[Security index.php files]"
MISSING_INDEX=0
for d in $(find "$BASE" -type d); do
  if [ ! -f "$d/index.php" ]; then
    echo "  MISSING: $d/index.php"
    MISSING_INDEX=1
  fi
done
check "All directories contain index.php" "$MISSING_INDEX"

# --- Main module content checks ---
echo ""
echo "[Module content checks]"

if grep -q '_PS_VERSION_' "$MAIN" 2>/dev/null; then
  check "Contains _PS_VERSION_ guard" "0"
else
  check "Contains _PS_VERSION_ guard" "1"
fi

if grep -q 'getContent' "$MAIN" 2>/dev/null; then
  check "Contains getContent" "0"
else
  check "Contains getContent" "1"
fi

if grep -q 'displayForm' "$MAIN" 2>/dev/null; then
  check "Contains displayForm" "0"
else
  check "Contains displayForm" "1"
fi

if grep -q 'submit_retractation2026' "$MAIN" 2>/dev/null; then
  check "Contains submit_retractation2026" "0"
else
  check "Contains submit_retractation2026" "1"
fi

HOOK_COUNT=$(grep -c 'registerHook' "$MAIN" 2>/dev/null || echo 0)
if [ "$HOOK_COUNT" -ge 6 ]; then
  check "registerHook count >= 6 (found $HOOK_COUNT)" "0"
else
  check "registerHook count >= 6 (found $HOOK_COUNT)" "1"
fi

CONFIG_COUNT=$(grep -c 'Configuration::updateValue' "$MAIN" 2>/dev/null || echo 0)
if [ "$CONFIG_COUNT" -ge 5 ]; then
  check "Configuration::updateValue count >= 5 (found $CONFIG_COUNT)" "0"
else
  check "Configuration::updateValue count >= 5 (found $CONFIG_COUNT)" "1"
fi

if grep -q '$this->l(' "$MAIN" 2>/dev/null; then
  check "No deprecated \$this->l() calls" "1"
else
  check "No deprecated \$this->l() calls" "0"
fi

SQL_HARDCODED=0
for sqlf in "$BASE/sql/install.sql" "$BASE/sql/uninstall.sql"; do
  if grep -q 'ps_' "$sqlf" 2>/dev/null; then
    SQL_HARDCODED=1
  fi
done
check "No hardcoded ps_ table prefix in SQL" "$SQL_HARDCODED"

# HelperForm check
if grep -q 'HelperForm\|new Helper' "$MAIN" 2>/dev/null; then
  check "Uses HelperForm" "0"
else
  check "Uses HelperForm" "1"
fi

CONFIG_GET_COUNT=$(grep -c 'Configuration::get' "$MAIN" 2>/dev/null || echo 0)
if [ "$CONFIG_GET_COUNT" -ge 5 ]; then
  check "Configuration::get count >= 5 (found $CONFIG_GET_COUNT)" "0"
else
  check "Configuration::get count >= 5 (found $CONFIG_GET_COUNT)" "1"
fi

# --- Summary ---
echo ""
TOTAL=$((PASS + FAIL))
echo "=== Results: $PASS/$TOTAL passed, $FAIL failed ==="

if [ "$FAIL" -gt 0 ]; then
  exit 1
fi
exit 0
