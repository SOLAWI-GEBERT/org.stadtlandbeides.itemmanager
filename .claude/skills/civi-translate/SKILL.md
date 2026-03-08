---
name: civi-translate
description: Extract translatable strings, translate to German (SOLAWI context), and compile .mo file.
argument-hint: "[--extract-only | --translate-only | --compile-only]"
allowed-tools: Read, Edit, Bash, Grep, Glob
user-invocable: true
---

Translate the CiviCRM extension to German. $ARGUMENTS

## Paths

- **civistrings binary**: `/Users/linus/Documents/0_Development/civistrings/bin/civistrings`
- **Project root**: `/Users/linus/Documents/0_Development/org.stadtlandbeides.itemmanager`
- **POT file**: `l10n/org.stadtlandbeides.itemmanager.pot`
- **PO file**: `l10n/de_DE/LC_MESSAGES/itemmanager.po`
- **MO file**: `l10n/de_DE/LC_MESSAGES/itemmanager.mo`

## Workflow

### Step 1 — Extract strings (skip if `--translate-only` or `--compile-only`)

Run from the project root:

```bash
/Users/linus/Documents/0_Development/civistrings/bin/civistrings -o l10n/org.stadtlandbeides.itemmanager.pot .
```

This scans all `.php`, `.tpl`, and `.js` files for `E::ts()`, `ts()` and `{ts}` calls and writes the POT file.

### Step 2 — Merge new strings into PO file (skip if `--translate-only` or `--compile-only`)

Merge the updated POT into the existing PO file to pick up new/removed strings:

```bash
msgmerge --update --no-fuzzy-matching l10n/de_DE/LC_MESSAGES/itemmanager.po l10n/org.stadtlandbeides.itemmanager.pot
```

If `msgmerge` is not available, manually add missing `msgid` entries from the POT file to the PO file.

### Step 3 — Translate (skip if `--extract-only` or `--compile-only`)

Read the PO file and translate all entries where `msgstr ""` (empty). Use the Edit tool to fill in translations.

#### Translation rules

1. **Language**: German (de_DE), formal ("Sie" form where applicable).
2. **Domain**: SOLAWI (Solidarische Landwirtschaft / Community Supported Agriculture). Use SOLAWI-specific terminology:
   - "Item" / "Line item" → "Posten" or "Anteil" (context-dependent)
   - "Membership" → "Mitgliedschaft" or "Ernteteiler" where it refers to a share
   - "Price set" → "Preisgruppe"
   - "Price field value" → "Preisfeld"
   - "Renew periods" → "Solawi Verlängerung"
   - "Contribution" → "Zuwendung"
   - "Payment" → "Bezahlung" or "Zahlung"
   - "Dashboard" → "Übersicht"
   - "Successor" → "Nachfolger"
   - "Novitiate" → "Neuzugang"
   - "Period" → "Zeitraum" or "Periode"
3. **UI length**: Keep translations short enough for buttons and table headers. Prefer concise terms.
4. **Placeholders**: Preserve `%1`, `%2`, `%s` etc. exactly as they appear in the msgid.
5. **Multiline strings**: Preserve the exact whitespace/newline structure of the msgid.
6. **Already translated**: Do NOT change entries that already have a non-empty msgstr (unless the user explicitly asks to review all).
7. **Technical strings**: Internal identifiers (entity names, API names) can be left untranslated if they are technical-only labels.

### Step 4 — Compile MO file (skip if `--extract-only` or `--translate-only`)

```bash
msgfmt -o l10n/de_DE/LC_MESSAGES/itemmanager.mo l10n/de_DE/LC_MESSAGES/itemmanager.po
```

### Step 5 — Report

Summarize:
- How many new strings were extracted
- How many strings were translated
- Any strings intentionally left untranslated (with reason)
