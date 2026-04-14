#!/bin/bash
set -e
PASS=0
FAIL=0
check() {
  if eval "$1" > /dev/null 2>&1; then
    echo "  PASS: $2"
    PASS=$((PASS+1))
  else
    echo "  FAIL: $2"
    FAIL=$((FAIL+1))
  fi
}

echo "=== S04 Verification: Email Confirmation ==="

echo ""
echo "--- File existence ---"
check "test -f retractation2026/mails/fr/retractation_confirmation.html" "FR HTML template exists"
check "test -f retractation2026/mails/fr/retractation_confirmation.txt"  "FR TXT template exists"
check "test -f retractation2026/mails/en/retractation_confirmation.html" "EN HTML template exists"
check "test -f retractation2026/mails/en/retractation_confirmation.txt"  "EN TXT template exists"
check "test -f retractation2026/mails/index.php"    "mails/index.php exists"
check "test -f retractation2026/mails/fr/index.php"  "mails/fr/index.php exists"
check "test -f retractation2026/mails/en/index.php"  "mails/en/index.php exists"

echo ""
echo "--- Mail::Send integration ---"
check "grep -q 'Mail::Send' retractation2026/controllers/front/request.php" "Mail::Send present in request.php"
check "grep -q 'RETRACTATION_EMAIL_ENABLED' retractation2026/controllers/front/request.php" "RETRACTATION_EMAIL_ENABLED config gate present"

echo ""
echo "--- Template variables (FR HTML) ---"
check "grep -q '{firstname}' retractation2026/mails/fr/retractation_confirmation.html"        "{firstname} in FR HTML"
check "grep -q '{lastname}' retractation2026/mails/fr/retractation_confirmation.html"         "{lastname} in FR HTML"
check "grep -q '{order_reference}' retractation2026/mails/fr/retractation_confirmation.html"  "{order_reference} in FR HTML"
check "grep -q '{retractation_date}' retractation2026/mails/fr/retractation_confirmation.html" "{retractation_date} in FR HTML"
check "grep -q '{retractation_time}' retractation2026/mails/fr/retractation_confirmation.html" "{retractation_time} in FR HTML"
check "grep -q '{reason}' retractation2026/mails/fr/retractation_confirmation.html"            "{reason} in FR HTML"

echo ""
echo "--- Template variables (FR TXT) ---"
check "grep -q '{firstname}' retractation2026/mails/fr/retractation_confirmation.txt"        "{firstname} in FR TXT"
check "grep -q '{retractation_date}' retractation2026/mails/fr/retractation_confirmation.txt" "{retractation_date} in FR TXT"
check "grep -q '{retractation_time}' retractation2026/mails/fr/retractation_confirmation.txt" "{retractation_time} in FR TXT"

echo ""
echo "--- No early return after Mail::Send ---"
CONTROLLER="retractation2026/controllers/front/request.php"
MAIL_LINE=$(grep -n 'Mail::Send' "$CONTROLLER" | head -1 | cut -d: -f1)
RETRACTDATA_LINE=$(grep -n 'retractationData' "$CONTROLLER" | head -1 | cut -d: -f1)
if [ -n "$MAIL_LINE" ] && [ -n "$RETRACTDATA_LINE" ]; then
  BETWEEN=$(sed -n "${MAIL_LINE},${RETRACTDATA_LINE}p" "$CONTROLLER" | grep -c 'return;' || true)
  if [ "$BETWEEN" -eq 0 ]; then
    echo "  PASS: No early return between Mail::Send and retractationData"
    PASS=$((PASS+1))
  else
    echo "  FAIL: Found return statement between Mail::Send and retractationData"
    FAIL=$((FAIL+1))
  fi
else
  echo "  FAIL: Could not locate Mail::Send or retractationData lines"
  FAIL=$((FAIL+1))
fi

echo ""
echo "--- {var} syntax (not {\$var}) ---"
if grep -q '{\$' retractation2026/mails/fr/retractation_confirmation.html; then
  echo "  FAIL: Found {\$var} syntax in FR HTML (should be {var})"
  FAIL=$((FAIL+1))
else
  echo "  PASS: Correct {var} syntax in FR HTML"
  PASS=$((PASS+1))
fi

echo ""
echo "--- No hardcoded email addresses in templates ---"
if grep -qiE '[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}' retractation2026/mails/fr/retractation_confirmation.html retractation2026/mails/en/retractation_confirmation.html; then
  echo "  FAIL: Found hardcoded email address in templates"
  FAIL=$((FAIL+1))
else
  echo "  PASS: No hardcoded email addresses"
  PASS=$((PASS+1))
fi

echo ""
echo "=== Results: $PASS passed, $FAIL failed ==="
if [ "$FAIL" -gt 0 ]; then
  exit 1
fi
echo "All checks passed."
exit 0
