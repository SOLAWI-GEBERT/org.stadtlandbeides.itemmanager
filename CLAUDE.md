# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CiviCRM extension (`org.stadtlandbeides.itemmanager`) for managing membership line items in a CSA (Community Supported Agriculture) context. Built with civix scaffolding (format 24.09.1). Version 4.3.0, targeting CiviCRM 5.45+.

Three core features:
- **Update Items**: Synchronize line items when price field values change
- **Successor Price Sets**: Define successor relationships between price field values and periods
- **Renew Periods**: Renew memberships with payment plans based on successor definitions

Depends on the Membership Extras extension (`uk.co.compucorp.membershipextras`).

## Architecture

### Autoloading & Namespaces
- `Civi\` namespace: PSR-4 from `Civi/` directory (API4 entities)
- `CRM_` prefix: PSR-0 from project root (Pages, Forms, Logic, DAO, BAO, Util)
- Extension utility alias: `CRM_Itemmanager_ExtensionUtil as E`

### Custom Entities (schema/)
- **ItemmanagerPeriods** (`civicrm_itemmanager_periods`): Defines period configuration per price set — start date, period count, period type (0=day, 1=week, 2=month, 3=year), successor period, reverse flag
- **ItemmanagerSettings** (`civicrm_itemmanager_settings`): Links a price field value to a period and its successor item — includes ignore, extend, novitiate, bidding flags and period exception overrides

### Key Classes
- `CRM_Itemmanager_Util` — Static helpers for CiviCRM API3 lookups: memberships, contributions, line items, financial records, price set traversal, successor chain resolution
- `CRM_Itemmanager_Page_UpdateItems` — Compares current line items against current price field values; applies label, price, date, and financial record updates via direct SQL
- `CRM_Itemmanager_Page_Dashboard` — Contact tab showing aggregated line item data per membership
- `CRM_Itemmanager_Form_ItemmanagerSetting` — Admin form for configuring item successor relationships
- `CRM_Itemmanager_Logic_RenewalPaymentPlanBase` — Abstract base for renewal logic (single/multiple installment plans)

### API Layer
- API4 entities: `Civi\Api4\ItemmanagerSettings`, `Civi\Api4\ItemmanagerPeriods`
- Production code still uses API3 heavily (`civicrm_api3`) for core CiviCRM entities (Contribution, LineItem, MembershipPayment, PriceFieldValue, etc.)

### Templates
Smarty v2 templates in `templates/CRM/Itemmanager/` matching Page and Form classes.

### Database Migrations
Sequential SQL upgrade files in `sql/upgrade_*.sql` (4000 through 4300). Uses `CiviMix\Schema\Itemmanager\AutomaticUpgrader`.

## Testing

Tests require a running CiviCRM Standalone instance with buildkit. The bootstrap connects via `cv php:boot` and expects env vars `CIVICRM_SETTINGS`, `CIVICRM_BOOT`, `CIVICRM_DSN` (defaults configured for a specific buildkit setup in `phpunit.xml.dist`).

### Run all tests
```bash
phpunit -c phpunit.xml.dist
```

### Run a single suite
```bash
phpunit -c phpunit.xml.dist --testsuite Settings
```

### Run a single test file
```bash
phpunit -c phpunit.xml.dist tests/phpunit/suites/updateitems/UpdateItemsTest.php
```

### Run a single test method
```bash
phpunit -c phpunit.xml.dist --filter testUpdateDataUpdatesPersistedValuesAndFinancialRecords
```

### Test Base Classes (tests/phpunit/helper/CRM/Itemmanager/Test/)
- `SeededTestCase` — Headless CiviCRM with full fixture seed per test (organization, financial type, membership type, price set/field/values). IDs available via `$this->seedIds`.
- `SuiteSeededTestCase` — Seed once per suite for faster integration tests. Manually clean records created per test.
- `MembershipSeededTestCase` — Extends suite seed with individual contact and membership order fixture.

### Test Suites (tests/phpunit/suites/)
- `migration/` — Extension install, SQL upgrade idempotency, schema assertions
- `sanity/` — Basic extension health checks
- `settings/` — ItemmanagerSettings/Periods CRUD, form post-processing
- `util/` — Util helper methods with realistic contribution/line-item fixtures
- `updateitems/` — UpdateItems page logic with page doubles
- `dashboard/` — Dashboard page with template payload assertions
- `unit/` — Pure PHPUnit tests with no CiviCRM dependency

### Test Conventions
- Use page doubles that override `assign`, `processError`, `processSuccess`, `processInfo` instead of rendering templates
- Reference seeded IDs via `$this->seedIds` — never hardcode numeric IDs
- Clean up created records in `tearDown()` — filter to positive ints before API4 delete
- Use `assertSame` for exact checks; tolerance (`0.01`) for money/tax assertions
- Preserve/restore `$_REQUEST`/`$_GET`/`$_POST` with `try/finally`

## Branches

- `dev` — main integration branch (PR target)
- `codex_unittest` — test development branch

## Codex Agents (.agents/)

The repo includes OpenAI Codex agent skills:
- `unittest` — Generates/maintains PHPUnit tests (write-only, does not execute)
- `civicrm-api-migration` — Assists migrating API3 calls to API4
- `file-viewer` — Structured file analysis
