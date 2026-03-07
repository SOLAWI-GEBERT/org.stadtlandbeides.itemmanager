#!/usr/bin/env bash
# Hook: Verify that CiviCRM API entities, CRM_ classes, and SQL tables used in
# changed PHP files actually exist in civicrm-core or the membershipextras
# extension checkout.
#
# Receives PostToolUse JSON on stdin (tool_name, tool_input, tool_output).
# Prints warnings to stdout — Claude sees these as feedback.

set -euo pipefail

CORE="/Users/linus/Documents/0_Development/civicrm-core"
MEMEXT="/Users/linus/Documents/0_Development/uk.co.compucorp.membershipextras"

# Parse file path from stdin JSON
INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('tool_input',{}).get('file_path',''))" 2>/dev/null || true)

# Only check PHP files
[[ "$FILE_PATH" == *.php ]] || exit 0
[[ -f "$FILE_PATH" ]] || exit 0

# Skip test files — they may reference mocks or fixtures
[[ "$FILE_PATH" != */tests/* ]] || exit 0

WARNINGS=""

# Use perl instead of grep -P (macOS grep lacks PCRE support)
extract() {
  perl -nle "print for /$1/g" "$FILE_PATH" 2>/dev/null | sort -u
}

# Check if an API entity exists in core or membershipextras
entity_exists() {
  local entity="$1"
  # Check civicrm-core
  [[ -f "$CORE/Civi/Api4/${entity}.php" ]] && return 0
  [[ -f "$CORE/api/v3/${entity}.php" ]] && return 0
  # Check membershipextras
  [[ -f "$MEMEXT/api/v3/${entity}.php" ]] && return 0
  return 1
}

# Check if a CRM_ class exists in core or membershipextras
class_exists() {
  local class="$1"
  local class_path="${class//_//}.php"
  [[ -f "$CORE/$class_path" ]] && return 0
  [[ -f "$MEMEXT/$class_path" ]] && return 0
  return 1
}

# Check if a SQL table exists in core or membershipextras schema
table_exists() {
  local table="$1"
  # civicrm-core schema (new style)
  grep -rql "'${table}'" "$CORE/schema/" >/dev/null 2>&1 && return 0
  # civicrm-core schema (xml style)
  grep -rql "name=\"${table}\"" "$CORE/xml/schema/" >/dev/null 2>&1 && return 0
  # membershipextras (check sql/ and xml/)
  grep -rql "'${table}'" "$MEMEXT/sql/" >/dev/null 2>&1 && return 0
  grep -rql "name=\"${table}\"" "$MEMEXT/xml/" >/dev/null 2>&1 && return 0
  # membershipextras DAO file referencing the table
  grep -rql "'${table}'" "$MEMEXT/CRM/" >/dev/null 2>&1 && return 0
  return 1
}

# --- Check 1: civicrm_api3('Entity', ...) calls ---
while IFS= read -r entity; do
  [[ -z "$entity" ]] && continue
  case "$entity" in
    setting|Setting) continue ;;
    Extension) continue ;;
  esac
  if entity_exists "$entity"; then
    continue
  fi
  WARNINGS="${WARNINGS}WARNING: civicrm_api3('${entity}', ...) — entity not found in civicrm-core or membershipextras\n"
done < <(extract "civicrm_api3\(\s*['\"]([A-Za-z0-9_]+)")

# --- Check 2: Legacy civicrm_api('Entity', ...) calls ---
while IFS= read -r entity; do
  [[ -z "$entity" ]] && continue
  if ! entity_exists "$entity"; then
    WARNINGS="${WARNINGS}WARNING: civicrm_api('${entity}', ...) — legacy API call, entity not found in civicrm-core or membershipextras\n"
  fi
done < <(extract "civicrm_api\(\s*['\"]([A-Za-z0-9_]+)")

# --- Check 3: \Civi\Api4\Entity:: references ---
while IFS= read -r entity; do
  [[ -z "$entity" ]] && continue
  # Skip our own extension entities
  case "$entity" in
    ItemmanagerSettings|ItemmanagerPeriods) continue ;;
  esac
  if [[ ! -f "$CORE/Civi/Api4/${entity}.php" ]]; then
    WARNINGS="${WARNINGS}WARNING: \\Civi\\Api4\\${entity} — API4 entity not found in civicrm-core\n"
  fi
done < <(extract '\\Civi\\Api4\\([A-Za-z0-9_]+)::')

# --- Check 4: CRM_ class references ---
while IFS= read -r class; do
  [[ -z "$class" ]] && continue
  # Skip our own extension classes
  [[ "$class" == CRM_Itemmanager_* ]] && continue
  # Skip SEPA extension (separate dependency, not checked out as working copy)
  [[ "$class" == CRM_Sepa_* ]] && continue
  if ! class_exists "$class"; then
    WARNINGS="${WARNINGS}WARNING: ${class} — class not found in civicrm-core or membershipextras\n"
  fi
done < <(extract '\b(CRM_[A-Za-z0-9_]+)::')

# --- Check 5: SQL table references ---
while IFS= read -r table; do
  [[ -z "$table" ]] && continue
  # Skip our own extension tables
  [[ "$table" == civicrm_itemmanager_* ]] && continue
  # Skip SEPA tables (separate dependency)
  [[ "$table" == civicrm_sdd_* ]] && continue
  [[ "$table" == civicrm_sepa_* ]] && continue
  # Skip dynamic custom field tables (created at runtime)
  [[ "$table" == civicrm_value_* ]] && continue
  # Skip false positives from function names
  [[ "$table" == civicrm_api3 ]] && continue
  [[ "$table" == civicrm_api ]] && continue
  if ! table_exists "$table"; then
    WARNINGS="${WARNINGS}INFO: SQL table '${table}' — not found in civicrm-core or membershipextras schema\n"
  fi
done < <(extract '\b(civicrm_[a-z0-9_]+)\b')

# Output
if [[ -n "$WARNINGS" ]]; then
  echo "=== civicrm compatibility check for $(basename "$FILE_PATH") ==="
  echo -e "$WARNINGS"
  echo "Checked: civicrm-core + membershipextras"
fi

exit 0
