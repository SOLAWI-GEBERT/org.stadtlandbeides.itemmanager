#!/usr/bin/env bash
set -euo pipefail

FILTER="${1:-}"
if [[ -z "$FILTER" ]]; then
  echo "Usage: $0 <filter-regex>"
  exit 1
fi

export PATH=/root/.composer/vendor/bin:$PATH

OUTPUT=$(phpunit -c phpunit.xml.dist --filter "$FILTER" tests/phpunit/suites/lineitemedit/LineItemEditTest.php 2>&1 || true)

if echo "$OUTPUT" | grep -q "OK ("; then
  echo "OK"
elif echo "$OUTPUT" | grep -q "FAILURES!\|ERRORS!\|There was\|There were"; then
  echo "FAIL"
  echo "$OUTPUT" | grep -E "FAILURES!|ERRORS!|There was|There were|^[0-9]+\) |^Time:" || true
else
  echo "UNKNOWN"
  echo "$OUTPUT" | tail -n 5
fi
