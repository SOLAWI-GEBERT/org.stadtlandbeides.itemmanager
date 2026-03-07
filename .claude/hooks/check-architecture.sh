#!/usr/bin/env bash
# Hook: Architecture and PHP 8 code style checks for CiviCRM extension code.
#
# Receives PostToolUse JSON on stdin (tool_name, tool_input, tool_output).
# Prints warnings/suggestions to stdout — Claude sees these as feedback.

set -euo pipefail

# Parse file path from stdin JSON
INPUT=$(cat)
FILE_PATH=$(echo "$INPUT" | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('tool_input',{}).get('file_path',''))" 2>/dev/null || true)

# Only check PHP and TPL files
[[ "$FILE_PATH" == *.php || "$FILE_PATH" == *.tpl ]] || exit 0
[[ -f "$FILE_PATH" ]] || exit 0

WARNINGS=""
BASENAME=$(basename "$FILE_PATH")

warn() {
  WARNINGS="${WARNINGS}WARNING: $1\n"
}

info() {
  WARNINGS="${WARNINGS}INFO: $1\n"
}

# Helper: count occurrences (uses m{} delimiter to allow / in patterns)
count_matches() {
  perl -nle "print for m{$1}g" "$FILE_PATH" 2>/dev/null | wc -l | tr -d ' '
}

# Helper: find line numbers (uses m{} delimiter to allow / in patterns)
line_numbers() {
  perl -nle "print \$. if m{$1}" "$FILE_PATH" 2>/dev/null | head -5 | tr '\n' ',' | sed 's/,$//'
}

# =============================================================================
# SMARTY TEMPLATE CHECKS (for .tpl files)
# =============================================================================

if [[ "$FILE_PATH" == *.tpl ]]; then

  # --- S1: {php} tags (removed in Smarty 3) ---
  if grep -qE '\{php\}|\{/php\}' "$FILE_PATH" 2>/dev/null; then
    LINES=$(grep -nE '\{php\}|\{/php\}' "$FILE_PATH" 2>/dev/null | head -5 | cut -d: -f1 | tr '\n' ',' | sed 's/,$//')
    warn "Smarty3: {php} Tags sind in Smarty 3+ entfernt (Zeilen: $LINES). PHP-Logik in die Page/Form-Klasse verschieben."
  fi

  # --- S2: {include_php} (removed in Smarty 3) ---
  if grep -qE '\{include_php\b' "$FILE_PATH" 2>/dev/null; then
    LINES=$(grep -nE '\{include_php\b' "$FILE_PATH" 2>/dev/null | head -5 | cut -d: -f1 | tr '\n' ',' | sed 's/,$//')
    warn "Smarty3: {include_php} ist in Smarty 3+ entfernt (Zeilen: $LINES). PHP-Logik in die Klasse verschieben."
  fi

  # --- S3: HTML <if> tags instead of Smarty {if} ---
  if grep -qE '<if\s+\{|<\/if>' "$FILE_PATH" 2>/dev/null; then
    LINES=$(grep -nE '<if\s+\{|<\/if>' "$FILE_PATH" 2>/dev/null | head -5 | cut -d: -f1 | tr '\n' ',' | sed 's/,$//')
    warn "Smarty: HTML <if> statt Smarty {if} gefunden (Zeilen: $LINES). Verwende {if \$var}...{/if}."
  fi

  # --- S4: Backtick variable interpolation in attributes (deprecated Smarty 3+) ---
  if grep -qE '`\$[a-zA-Z_]' "$FILE_PATH" 2>/dev/null; then
    LINES=$(line_numbers '`\$[a-zA-Z_]')
    warn "Smarty3: Backtick-Variablen-Interpolation (Zeilen: $LINES). In Smarty 3+ verwende {crmURL ... q=\"param=\"|cat:\$var} oder baue die URL in PHP."
  fi

  # --- S5: @count modifier (legacy Smarty 2 syntax) ---
  if grep -qE '\|@count' "$FILE_PATH" 2>/dev/null; then
    LINES=$(line_numbers '\|@count')
    info "Smarty3: Legacy |@count Modifier (Zeilen: $LINES). In Smarty 3+ verwende |count oder count(\$array) in {if}."
  fi

  # --- S6: Other @ modifier prefix (Smarty 2 array-apply syntax) ---
  if grep -qE '\|@[a-z]+' "$FILE_PATH" 2>/dev/null; then
    MATCHES=$(perl -nle 'print "$.: $&" if /\|@[a-z]+/' "$FILE_PATH" 2>/dev/null | grep -v '@count' | head -5 || true)
    if [[ -n "$MATCHES" ]]; then
      info "Smarty3: Legacy |@modifier Syntax (Array-Apply) gefunden. In Smarty 3+ |modifier direkt verwenden:\n$MATCHES"
    fi
  fi

  # --- S7: {$smarty.request/get/post} (security risk + Smarty 3 stricter) ---
  if grep -qE '\{\$smarty\.(request|get|post|cookies)\.' "$FILE_PATH" 2>/dev/null; then
    LINES=$(grep -nE '\{\$smarty\.(request|get|post|cookies)\.' "$FILE_PATH" 2>/dev/null | head -5 | cut -d: -f1 | tr '\n' ',' | sed 's/,$//')
    warn "Smarty: Direkter Superglobal-Zugriff via \$smarty.request/get/post (Zeilen: $LINES). Werte in PHP via CRM_Utils_Request holen und per assign() übergeben."
  fi

  # --- S8: JavaScript/CSS curly braces without {literal} ---
  # Check for <script> or <style> blocks that are NOT wrapped in {literal}
  UNPROTECTED_JS=$(perl -e '
    my $in_literal = 0;
    my $in_script = 0;
    my $in_style = 0;
    my @issues;
    while (<STDIN>) {
      my $ln = $.;
      $in_literal = 1 if /\{literal\}/;
      $in_literal = 0 if /\{\/literal\}/;
      $in_script = 1 if /<script/i && !$in_literal;
      $in_script = 0 if /<\/script/i;
      $in_style = 1 if /<style/i && !$in_literal;
      $in_style = 0 if /<\/style/i;
      if (($in_script || $in_style) && !$in_literal && /[{}]/ && !/\{[\/$a-zA-Z*]/ && !/^\s*\{literal/) {
        push @issues, "  Zeile $ln: JS/CSS Klammern ohne {literal}";
      }
    }
    print join("\n", @issues[0..4]) . "\n" if @issues;
  ' < "$FILE_PATH" 2>/dev/null)
  if [[ -n "$UNPROTECTED_JS" ]]; then
    warn "Smarty: JavaScript/CSS-Blöcke mit geschweiften Klammern ohne {literal}...{/literal} gefunden. Smarty interpretiert {} als Template-Tags:\n$UNPROTECTED_JS"
  fi

  # --- S9: Deprecated {insert} function (removed in Smarty 3.1+) ---
  if grep -qE '\{insert\s' "$FILE_PATH" 2>/dev/null; then
    LINES=$(grep -nE '\{insert\s' "$FILE_PATH" 2>/dev/null | head -5 | cut -d: -f1 | tr '\n' ',' | sed 's/,$//')
    warn "Smarty3: {insert} ist in Smarty 3.1+ deprecated/entfernt (Zeilen: $LINES). Verwende {call} oder eine Plugin-Funktion."
  fi

  # --- S10: $this-> in templates (Smarty 2 only) ---
  if grep -qE '\{\$this->' "$FILE_PATH" 2>/dev/null; then
    LINES=$(grep -nE '\{\$this->' "$FILE_PATH" 2>/dev/null | head -5 | cut -d: -f1 | tr '\n' ',' | sed 's/,$//')
    warn "Smarty3: \$this-> Zugriff im Template (Zeilen: $LINES). Nur in Smarty 2 verfügbar — Werte per assign() übergeben."
  fi

  # --- S11: ts() without domain in extension templates ---
  if grep -qE '\{ts\}|\{ts ' "$FILE_PATH" 2>/dev/null; then
    if ! grep -qE '\{ts\s+domain=' "$FILE_PATH" 2>/dev/null; then
      # Only warn if there are ts calls and NONE have domain
      TS_COUNT=$(grep -cE '\{ts\}|\{ts ' "$FILE_PATH" 2>/dev/null || echo "0")
      TS_DOMAIN=$(grep -cE '\{ts\s+domain=' "$FILE_PATH" 2>/dev/null || echo "0")
      TS_COUNT=$(echo "$TS_COUNT" | tr -d '[:space:]')
      TS_DOMAIN=$(echo "$TS_DOMAIN" | tr -d '[:space:]')
      if [[ "$TS_COUNT" -gt 0 && "$TS_DOMAIN" -eq 0 ]]; then
        warn "CiviCRM: {ts} ohne domain= Attribut gefunden ($TS_COUNT Stellen). In Extension-Templates immer {ts domain=\"org.stadtlandbeides.itemmanager\"}...{/ts} verwenden."
      fi
    fi
  fi

  # --- S12: Smarty 2 {math} function (deprecated in Smarty 3) ---
  if grep -qE '\{math\s' "$FILE_PATH" 2>/dev/null; then
    LINES=$(grep -nE '\{math\s' "$FILE_PATH" 2>/dev/null | head -5 | cut -d: -f1 | tr '\n' ',' | sed 's/,$//')
    info "Smarty3: {math} ist in Smarty 3+ deprecated (Zeilen: $LINES). Berechnungen in PHP durchführen und per assign() übergeben."
  fi

  # --- S13: Unquoted string arguments in modifiers ---
  UNQUOTED=$(perl -nle '
    while (/\|(\w+):([A-Za-z]\w*)/g) {
      my ($mod, $arg) = ($1, $2);
      next if $arg =~ /^(true|false|null|and|or|not|eq|ne|gt|lt|ge|le)$/i;
      next if $arg =~ /^\$/;
      print "$.: |$mod:$arg — Argument nicht gequoted";
    }
  ' "$FILE_PATH" 2>/dev/null | head -5)
  if [[ -n "$UNQUOTED" ]]; then
    info "Smarty3: Ungequotete String-Argumente in Modifiern (in Smarty 3 strenger):\n$UNQUOTED"
  fi

  # --- S14: {eval} usage (security risk, discouraged in Smarty 3+) ---
  if grep -qE '\{eval\s' "$FILE_PATH" 2>/dev/null; then
    LINES=$(grep -nE '\{eval\s' "$FILE_PATH" 2>/dev/null | head -5 | cut -d: -f1 | tr '\n' ',' | sed 's/,$//')
    warn "Smarty: {eval} ist ein Sicherheitsrisiko und in Smarty 3+ eingeschränkt (Zeilen: $LINES)."
  fi

  # --- S15: Smarty v2 mixin compatibility reminder ---
  # Hard check: verify extension declares smarty-v2 mixin
  EXT_ROOT=$(echo "$FILE_PATH" | sed 's|/templates/.*||')
  if [[ -f "$EXT_ROOT/info.xml" ]]; then
    if ! grep -q 'smarty-v2' "$EXT_ROOT/info.xml" 2>/dev/null; then
      warn "CiviCRM: Extension deklariert kein smarty-v2 Mixin in info.xml. Templates werden als Smarty 3+ interpretiert — Kompatibilität prüfen!"
    fi
  fi

  # Output for TPL files
  if [[ -n "$WARNINGS" ]]; then
    echo "=== smarty template check for $BASENAME ==="
    echo -e "$WARNINGS"
    echo "Hinweis: Extension nutzt smarty-v2@1.0.3 Mixin. Bei Migration auf Smarty 3+ alle o.g. Punkte beheben."
  fi

  exit 0
fi

# =============================================================================
# PHP-ONLY CHECKS BELOW
# =============================================================================

# =============================================================================
# SECTION 1: PHP 8 CODE STYLE
# =============================================================================

# --- 1a: Functions/methods missing return type declarations ---
# Match "function name(...) {" without ": type" before the brace
MISSING_RETURN=$(perl -nle '
  if (/^\s*(public|protected|private|static|\s)*(function\s+\w+\s*\([^)]*\))\s*\{/ && !/:\s*\S+\s*\{/) {
    print "$.: $&";
  }
' "$FILE_PATH" 2>/dev/null | head -8)
if [[ -n "$MISSING_RETURN" ]]; then
  warn "PHP8: Methoden ohne Return-Type-Declaration gefunden. Bitte Rückgabetypen ergänzen (void, int, string, array, bool, mixed, etc.):\n$MISSING_RETURN"
fi

# --- 1b: Untyped properties (class properties without type declaration) ---
UNTYPED_PROPS=$(perl -nle '
  if (/^\s*(public|protected|private)\s+(?!static\s+function|function)\s*\$\w+/ && !/^\s*(public|protected|private)\s+(static\s+)?(int|float|string|bool|array|object|mixed|null|\?|\\\\)\s/) {
    print "$.: $&";
  }
' "$FILE_PATH" 2>/dev/null | head -5)
if [[ -n "$UNTYPED_PROPS" ]]; then
  info "PHP8: Properties ohne Type-Declaration gefunden. Typed properties bevorzugen:\n$UNTYPED_PROPS"
fi

# --- 1c: Old-style type casts with spaces ---
if grep -qE '\(\s+(int|string|float|bool|array|object)\s+\)' "$FILE_PATH" 2>/dev/null; then
  LINES=$(line_numbers '\(\s+(int|string|float|bool|array|object)\s+\)')
  warn "PHP8: Type-Casts mit Leerzeichen gefunden (Zeilen: $LINES). Nutze (int), (string) etc. ohne Leerzeichen."
fi

# --- 1d: Deprecated ${var} string interpolation (deprecated in PHP 8.2) ---
if grep -qE '\$\{[a-zA-Z_]' "$FILE_PATH" 2>/dev/null; then
  LINES=$(line_numbers '\$\{[a-zA-Z_]')
  warn "PHP8.2: Veraltete \${var} String-Interpolation gefunden (Zeilen: $LINES). Nutze {\$var} stattdessen."
fi

# --- 1e: Use of create_function (removed in PHP 8.0) ---
if grep -q 'create_function\s*(' "$FILE_PATH" 2>/dev/null; then
  warn "PHP8: create_function() ist seit PHP 8.0 entfernt. Verwende anonyme Funktionen (Closures)."
fi

# --- 1f: Use of each() (removed in PHP 8.0) ---
if grep -qE '\beach\s*\(' "$FILE_PATH" 2>/dev/null; then
  warn "PHP8: each() ist seit PHP 8.0 entfernt. Verwende foreach stattdessen."
fi

# --- 1g: Nullable type with explicit null union (redundant) ---
if grep -qE '\?\w+\s*\|\s*null|\bnull\s*\|\s*\?\w+' "$FILE_PATH" 2>/dev/null; then
  LINES=$(line_numbers '\?\w+\s*\|\s*null|\bnull\s*\|\s*\?\w+')
  info "PHP8: Redundante ?Type|null Syntax gefunden (Zeilen: $LINES). Nutze entweder ?Type oder Type|null."
fi

# =============================================================================
# SECTION 2: ARCHITECTURE — GENERAL PRINCIPLES
# =============================================================================

# --- 2a: File too large (SRP indicator) ---
LINE_COUNT=$(wc -l < "$FILE_PATH" | tr -d ' ')
if [[ "$LINE_COUNT" -gt 500 ]]; then
  warn "Architektur: Datei hat $LINE_COUNT Zeilen. Dateien >500 Zeilen deuten auf Verletzung des Single-Responsibility-Prinzips hin."
fi

# --- 2b: Too many methods in a class ---
METHOD_COUNT=$(count_matches '^\s*(public|protected|private)\s+(static\s+)?function\s')
if [[ "$METHOD_COUNT" -gt 20 ]]; then
  warn "Architektur: Klasse hat $METHOD_COUNT Methoden. Klassen mit >20 Methoden sollten aufgeteilt werden (SRP)."
fi

# --- 2c: Method too long ---
# Simple heuristic: find function starts and check gap to next function
LONG_METHODS=$(perl -e '
  my @lines = <STDIN>;
  my @funcs;
  for my $i (0..$#lines) {
    if ($lines[$i] =~ /^\s*(public|protected|private|static|\s)*(function\s+(\w+))\s*\(/) {
      push @funcs, [$i+1, $3];
    }
  }
  for my $j (0..$#funcs) {
    my $start = $funcs[$j][0];
    my $end = $j < $#funcs ? $funcs[$j+1][0] - 1 : scalar(@lines);
    my $len = $end - $start;
    if ($len > 80) {
      print "  $funcs[$j][1]() ab Zeile $start ($len Zeilen)\n";
    }
  }
' < "$FILE_PATH" 2>/dev/null)
if [[ -n "$LONG_METHODS" ]]; then
  warn "Architektur: Sehr lange Methoden gefunden (>80 Zeilen). In kleinere Methoden aufteilen:\n$LONG_METHODS"
fi

# --- 2d: Use of global keyword ---
if grep -qE '^\s*global\s+\$' "$FILE_PATH" 2>/dev/null; then
  LINES=$(line_numbers '^\s*global\s+\$')
  warn "Architektur: 'global' Keyword gefunden (Zeilen: $LINES). Verwende Dependency Injection oder Parameter stattdessen."
fi

# --- 2e: exit/die statements (except in CLI scripts) ---
if grep -qE '\b(exit|die)\s*\(' "$FILE_PATH" 2>/dev/null; then
  # Allow in test bootstrap files
  if [[ "$FILE_PATH" != *bootstrap* && "$FILE_PATH" != *bin/* ]]; then
    LINES=$(line_numbers '\b(exit|die)\s*\(')
    warn "Architektur: exit()/die() gefunden (Zeilen: $LINES). In CiviCRM Exceptions werfen statt exit/die."
  fi
fi

# --- 2f: Hardcoded numeric IDs ---
if perl -nle 'print if /(?:contact_id|membership_id|contribution_id|financial_type_id|membership_type_id|price_set_id|price_field_id)\s*(?:=>|=)\s*\d+/' "$FILE_PATH" 2>/dev/null | grep -qv 'tests/'; then
  # Skip test files
  if [[ "$FILE_PATH" != *tests/* && "$FILE_PATH" != *Test.php ]]; then
    LINES=$(line_numbers '(?:contact_id|membership_id|contribution_id|financial_type_id|membership_type_id|price_set_id|price_field_id)\s*(?:=>|=)\s*\d+')
    warn "Architektur: Hardcodierte Entity-IDs gefunden (Zeilen: $LINES). IDs dynamisch über API ermitteln."
  fi
fi

# --- 2g: echo/print in non-CLI classes ---
if [[ "$FILE_PATH" != *tests/* && "$FILE_PATH" != *bin/* && "$FILE_PATH" != *CLI* ]]; then
  if grep -qE '^\s*(echo|print)\s' "$FILE_PATH" 2>/dev/null; then
    LINES=$(line_numbers '^\s*(echo|print)\s')
    warn "Architektur: echo/print in Klasse gefunden (Zeilen: $LINES). In CiviCRM Templates (Smarty) oder CRM_Core_Session::setStatus() nutzen."
  fi
fi

# --- 2h: Deeply nested code (>4 levels) ---
DEEP_NESTING=$(perl -nle '
  my $indent = 0;
  $indent++ while /\{/g;
  $indent-- while /\}/g;
  BEGIN { $depth = 0; }
  $depth += $indent;
  if ($depth > 5) {
    print "  Zeile $.: Verschachtelungstiefe $depth";
  }
' "$FILE_PATH" 2>/dev/null | head -5)
if [[ -n "$DEEP_NESTING" ]]; then
  info "Architektur: Tiefe Verschachtelung gefunden. Early returns oder Methoden-Extraktion erwägen:\n$DEEP_NESTING"
fi

# =============================================================================
# SECTION 3: CIVICRM-SPEZIFISCHE PRÜFUNGEN
# =============================================================================

# --- 3a: Direct $_GET/$_POST/$_REQUEST instead of CRM_Utils_Request ---
if [[ "$FILE_PATH" != *tests/* && "$FILE_PATH" != *Test.php ]]; then
  if grep -qE '\$_(GET|POST|REQUEST)\s*\[' "$FILE_PATH" 2>/dev/null; then
    LINES=$(line_numbers '\$_(GET|POST|REQUEST)\s*\[')
    warn "CiviCRM: Direkter Zugriff auf \$_GET/\$_POST/\$_REQUEST (Zeilen: $LINES). Verwende CRM_Utils_Request::retrieve() oder CRM_Utils_Request::retrieveValue()."
  fi
fi

# --- 3b: ts() instead of E::ts() for extension translations ---
if grep -qE "(?<![A-Za-z:>])ts\s*\(" "$FILE_PATH" 2>/dev/null; then
  # Exclude lines that already use E::ts or self::ts or $this->ts
  BARE_TS=$(perl -nle 'print "$.: $_" if /(?<![A-Za-z:>\$])ts\s*\(/ && !/E::ts/ && !/self::ts/ && !/\$this->ts/ && !/function\s+ts/' "$FILE_PATH" 2>/dev/null | head -5)
  if [[ -n "$BARE_TS" ]]; then
    warn "CiviCRM: Bare ts() statt E::ts() gefunden. In Extensions immer E::ts() für korrekte Übersetzungsdomäne verwenden:\n$BARE_TS"
  fi
fi

# --- 3c: Direct SQL in Page/Form classes (should be in BAO/Util) ---
if [[ "$FILE_PATH" == *Page* || "$FILE_PATH" == *Form* ]]; then
  if grep -qE 'CRM_Core_DAO::executeQuery|CRM_Core_DAO::singleValueQuery|\bSELECT\b.*\bFROM\b|\bUPDATE\b.*\bSET\b|\bINSERT\s+INTO\b|\bDELETE\s+FROM\b' "$FILE_PATH" 2>/dev/null; then
    SQL_COUNT=$(count_matches 'CRM_Core_DAO::executeQuery|CRM_Core_DAO::singleValueQuery')
    if [[ "$SQL_COUNT" -gt 3 ]]; then
      info "CiviCRM: $SQL_COUNT direkte SQL-Aufrufe in Page/Form-Klasse. SQL-Logik in BAO/Util-Klassen auslagern (Separation of Concerns)."
    fi
  fi
fi

# --- 3d: Missing ExtensionUtil import ---
if [[ "$FILE_PATH" != *tests/* && "$FILE_PATH" == *CRM/Itemmanager/* ]]; then
  if ! grep -q 'CRM_Itemmanager_ExtensionUtil' "$FILE_PATH" 2>/dev/null; then
    if grep -qE "E::" "$FILE_PATH" 2>/dev/null; then
      warn "CiviCRM: E:: wird verwendet, aber 'use CRM_Itemmanager_ExtensionUtil as E' fehlt."
    fi
  fi
fi

# --- 3e: Deprecated CiviCRM patterns ---
# CRM_Core_Error::fatal is deprecated
if grep -q 'CRM_Core_Error::fatal' "$FILE_PATH" 2>/dev/null; then
  LINES=$(line_numbers 'CRM_Core_Error::fatal')
  warn "CiviCRM: CRM_Core_Error::fatal() ist deprecated (Zeilen: $LINES). Verwende throw new CRM_Core_Exception() stattdessen."
fi

# CRM_Core_DAO::$_nullObject / CRM_Core_DAO::$_nullArray are deprecated
if grep -qE 'CRM_Core_DAO::\$_null(Object|Array)' "$FILE_PATH" 2>/dev/null; then
  LINES=$(line_numbers 'CRM_Core_DAO::\$_null(Object|Array)')
  warn "CiviCRM: CRM_Core_DAO::\$_nullObject/\$_nullArray sind deprecated (Zeilen: $LINES)."
fi

# --- 3f: Mixing API3 and API4 for same entity in one file ---
API3_ENTITIES=$(perl -nle 'print $1 if /civicrm_api3\(\s*['\''"]([A-Za-z]+)/' "$FILE_PATH" 2>/dev/null | sort -u)
API4_ENTITIES=$(perl -nle 'print $1 if /\\Civi\\Api4\\([A-Za-z]+)::/' "$FILE_PATH" 2>/dev/null | sort -u)
if [[ -n "$API3_ENTITIES" && -n "$API4_ENTITIES" ]]; then
  MIXED=$(comm -12 <(echo "$API3_ENTITIES") <(echo "$API4_ENTITIES") 2>/dev/null)
  if [[ -n "$MIXED" ]]; then
    info "CiviCRM: Gleiche Entities werden sowohl über API3 als auch API4 angesprochen: $MIXED. Konsistent eine API-Version pro Entity verwenden."
  fi
fi

# --- 3g: Direct table manipulation instead of API/BAO ---
if [[ "$FILE_PATH" != *DAO* && "$FILE_PATH" != *Upgrader* && "$FILE_PATH" != *tests/* ]]; then
  DIRECT_INSERT=$(grep -cE "INSERT\s+INTO\s+civicrm_" "$FILE_PATH" 2>/dev/null || echo "0")
  DIRECT_DELETE=$(grep -cE "DELETE\s+FROM\s+civicrm_" "$FILE_PATH" 2>/dev/null || echo "0")
  DIRECT_INSERT=$(echo "$DIRECT_INSERT" | tr -d '[:space:]')
  DIRECT_DELETE=$(echo "$DIRECT_DELETE" | tr -d '[:space:]')
  TOTAL=$((DIRECT_INSERT + DIRECT_DELETE))
  if [[ "$TOTAL" -gt 2 ]]; then
    info "CiviCRM: $TOTAL direkte INSERT/DELETE auf civicrm_* Tabellen. Bevorzuge API oder BAO-Methoden für korrekte Hook-Ausführung und Logging."
  fi
fi

# --- 3h: Accessing Smarty singleton directly ---
if grep -qE 'CRM_Core_Smarty::singleton\(\)|\\Civi::service\(.smarty.\)' "$FILE_PATH" 2>/dev/null; then
  if [[ "$FILE_PATH" == *Page* || "$FILE_PATH" == *Form* ]]; then
    LINES=$(line_numbers 'CRM_Core_Smarty::singleton|\\Civi::service\(.smarty.\)')
    info "CiviCRM: Direkter Smarty-Singleton-Zugriff (Zeilen: $LINES). In Pages/Forms \$this->assign() verwenden."
  fi
fi

# --- 3i: Catch-all Exception without specific type ---
if grep -qE 'catch\s*\(\s*\\?Exception\s' "$FILE_PATH" 2>/dev/null; then
  LINES=$(line_numbers 'catch\s*\(\s*\\?Exception\s')
  info "Architektur: Generische Exception gefangen (Zeilen: $LINES). Spezifischere Exception-Typen (CRM_Core_Exception, API_Exception, etc.) bevorzugen."
fi

# --- 3j: Suppressed errors with @ operator ---
AT_COUNT=$(count_matches '@\$|@\\\\|@[a-zA-Z_]+\(')
if [[ "$AT_COUNT" -gt 2 ]]; then
  info "PHP: $AT_COUNT Error-Suppression-Operatoren (@) gefunden. Fehler explizit behandeln statt unterdrücken."
fi

# =============================================================================
# OUTPUT
# =============================================================================

if [[ -n "$WARNINGS" ]]; then
  echo "=== architecture & code style check for $BASENAME ==="
  echo -e "$WARNINGS"
fi

exit 0
