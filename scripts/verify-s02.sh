#!/usr/bin/env bash
# S02 Slice Verification Script

PASS=0
FAIL=0
BASE="retractation2026"
SERVICE="$BASE/classes/RetractationEligibilityService.php"

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

echo "=== S02 Slice Verification ==="
echo ""

# --- File existence checks ---
echo "[File existence]"
if [ -f "$SERVICE" ]; then
  check "$SERVICE exists" "0"
else
  check "$SERVICE exists" "1"
fi

if [ -f "$BASE/classes/index.php" ]; then
  check "$BASE/classes/index.php exists" "0"
else
  check "$BASE/classes/index.php exists" "1"
fi

# --- Security guard ---
echo ""
echo "[Security]"
if grep -q '_PS_VERSION_' "$SERVICE" 2>/dev/null; then
  check "Contains _PS_VERSION_ guard" "0"
else
  check "Contains _PS_VERSION_ guard" "1"
fi

# --- Method signature ---
echo ""
echo "[Method signature]"
if grep -q 'function getEligibility' "$SERVICE" 2>/dev/null; then
  check "Contains getEligibility method" "0"
else
  check "Contains getEligibility method" "1"
fi

# --- Return keys ---
echo ""
echo "[Return keys]"
for key in eligible deadline source reference_date reason; do
  if grep -q "'$key'" "$SERVICE" 2>/dev/null; then
    check "References return key '$key'" "0"
  else
    check "References return key '$key'" "1"
  fi
done

# --- Configuration keys ---
echo ""
echo "[Configuration keys]"
for cfg in RETRACTATION_DELAY_DAYS RETRACTATION_BUFFER_SHIPPED RETRACTATION_BUFFER_ORDER RETRACTATION_ENABLED; do
  if grep -q "$cfg" "$SERVICE" 2>/dev/null; then
    check "Reads config $cfg" "0"
  else
    check "Reads config $cfg" "1"
  fi
done

# --- SQL safety ---
echo ""
echo "[SQL safety]"
if grep -q '(int)' "$SERVICE" 2>/dev/null; then
  check "Uses (int) casting in SQL context" "0"
else
  check "Uses (int) casting in SQL context" "1"
fi

if grep -q '_DB_PREFIX_' "$SERVICE" 2>/dev/null; then
  check "Uses _DB_PREFIX_" "0"
else
  check "Uses _DB_PREFIX_" "1"
fi

SQL_HARDCODED=0
if grep -qE "'ps_|\"ps_" "$SERVICE" 2>/dev/null; then
  SQL_HARDCODED=1
fi
check "No hardcoded ps_ table prefix" "$SQL_HARDCODED"

# --- Cascade checks ---
echo ""
echo "[Cascade logic]"
if grep -q 'order_history' "$SERVICE" 2>/dev/null; then
  check "Queries order_history table" "0"
else
  check "Queries order_history table" "1"
fi

if grep -q 'delivery' "$SERVICE" 2>/dev/null; then
  check "Uses delivery column from order_state" "0"
else
  check "Uses delivery column from order_state" "1"
fi

if grep -q 'shipped' "$SERVICE" 2>/dev/null; then
  check "Uses shipped column from order_state" "0"
else
  check "Uses shipped column from order_state" "1"
fi

# --- Anti-pattern checks ---
echo ""
echo "[Anti-patterns]"
HARDCODED_STATE=0
if grep -qE 'PS_OS_DELIVERED|PS_OS_SHIPPING' "$SERVICE" 2>/dev/null; then
  HARDCODED_STATE=1
fi
check "No hardcoded PS_OS_DELIVERED/PS_OS_SHIPPING lookups" "$HARDCODED_STATE"

DANGEROUS_CALLS=0
if grep -qE 'serialize\(|eval\(' "$SERVICE" 2>/dev/null; then
  DANGEROUS_CALLS=1
fi
check "No serialize() or eval() calls" "$DANGEROUS_CALLS"

# --- Date handling ---
echo ""
echo "[Date handling]"
if grep -q 'DateTimeImmutable' "$SERVICE" 2>/dev/null; then
  check "Uses DateTimeImmutable" "0"
else
  check "Uses DateTimeImmutable" "1"
fi

STRTOTIME=0
if grep -q 'strtotime' "$SERVICE" 2>/dev/null; then
  STRTOTIME=1
fi
check "Does not use strtotime" "$STRTOTIME"

# --- Summary ---
echo ""
TOTAL=$((PASS + FAIL))
echo "=== Results: $PASS/$TOTAL passed, $FAIL failed ==="

if [ "$FAIL" -gt 0 ]; then
  exit 1
fi
exit 0
