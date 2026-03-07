---
name: api-migration
description: Migrate CiviCRM data access in this extension toward modern API usage. Analyzes API3/SQL calls, decides what to migrate, and applies conversions preserving error handling and return semantics.
argument-hint: [file-or-method]
allowed-tools: Read, Write, Edit, Grep, Glob, Bash
---

Migrate CiviCRM data access for: $ARGUMENTS

Analyze the target code first, then decide what makes sense to migrate.

## The Real Problem

This extension has three layers of data access mixed together:

1. **Raw SQL** (`CRM_Core_DAO::executeQuery`, `singleValueQuery`, `executeUnbufferedQuery`) — used for aggregations, multi-table joins, and bulk updates
2. **API3** (`civicrm_api3()` and legacy `civicrm_api()`) — used for all CiviCRM core entity access
3. **API4** (`\Civi\Api4\*`) — used only for the extension's own entities and in test code

The migration question is not "convert everything to API4" — it is "which conversions improve the code without breaking it?"

## Current State of This Extension

### Already on API4 (do not touch)
- `\Civi\Api4\ItemmanagerSettings` — extension's own entity
- `\Civi\Api4\ItemmanagerPeriods` — extension's own entity
- Test seed/cleanup code uses API4 for core entities (Contact, PriceSet, PriceField, etc.)

### Production code using API3 (~90 calls across these files)
- `CRM/Itemmanager/Util.php` — heaviest user: PriceFieldValue, PriceField, PriceSet, LineItem, Contribution, MembershipPayment, MembershipType, MembershipStatus, FinancialItem, FinancialAccount, EntityFinancialTrxn, Setting
- `CRM/Itemmanager/Page/UpdateItems.php` — Contribution, LineItem, PriceField, PriceFieldValue, ContributionRecur
- `CRM/Itemmanager/Page/Dashboard.php` — Contribution (getcount, getsingle)
- `CRM/Itemmanager/Form/ItemmanagerSetting.php` — PriceSet, PriceField, PriceFieldValue
- `CRM/Itemmanager/Form/RenewItemperiods.php` — Contribution, PriceFieldValue, PriceField, PriceSet
- `CRM/Itemmanager/Form/ItemmanagerOptions.php` — Setting (getfields, create, get)
- `CRM/Itemmanager/Form/LinkSepaWrapper.php` — PriceFieldValue, FinancialType
- `CRM/Itemmanager/Form/RepairMissingContribution.php` — Contribution
- `CRM/Itemmanager/Logic/RenewalPaymentPlanBase.php` — ContributionRecur, OptionValue, Contribution, ContributionSoft, CustomField, PriceFieldValue, Membership, MembershipType, LineItem
- `CRM/Itemmanager/Logic/RenewalSingleInstallmentPlan.php` — ContributionRecur, ContributionRecurLineItem, LineItem, Contribution
- `CRM/Itemmanager/Logic/RenewalMultipleInstallmentPlan.php` — ContributionRecur, Membership, OptionValue, LineItem, ContributionRecurLineItem
- `CRM/Itemmanager/Page/LinkSepaPaymentsStub.php` — Contribution, Payment

### Legacy API2-style calls (4 calls — migrate first)
- `CRM/Itemmanager/Page/UpdateItems.php:83` — `civicrm_api('Contact', 'getsingle', array('version' => 3, ...))`
- `CRM/Itemmanager/Page/UpdateItems.php:346` — same
- `CRM/Itemmanager/Form/RenewItemperiods.php:267` — same
- `CRM/Itemmanager/Form/LinkSepaWrapper.php:34` — same

### Raw SQL that should stay as SQL
- `Util::getTaxAmountTotalFromContributionID` — SUM aggregate on line items
- `Util::getAmountTotalFromContributionID` — SUM aggregate on line items
- `Util::getLastReceiveDateContribution` — MIN/MAX subquery
- `Util::getFirstReceiveDateContribution` — MIN/MAX subquery
- `Util::getLastMembershipsByContactId` — JOIN with GROUP BY subquery
- `Util::getSDDByContactId` — SEPA mandate queries (external extension table)
- `UpdateItems::prepareCreateForm` — multi-table JOIN across membership, contribution, line_item, price_field, price_set
- `UpdateItems::updateData` — direct UPDATE on contribution, line_item, financial_item, entity_financial_trxn tables (batch operations)
- `RenewalSingleInstallmentPlan` / `RenewalMultipleInstallmentPlan` — raw queries for recurring contribution logic

## Decision Matrix

| Situation | Action |
|---|---|
| `civicrm_api()` with `'version' => 3` | Always migrate to `civicrm_api3()` at minimum, API4 if entity available |
| `civicrm_api3()` for entity with API4 support | Migrate if: simple get/create/delete, no complex param chaining |
| `civicrm_api3()` with `getfields`, `getvalue`, `getcount` | Check API4 equivalent carefully; `getvalue` has no direct API4 match |
| `civicrm_api3('ContributionRecurLineItem', ...)` | Keep as API3 — this entity may not have full API4 support |
| `civicrm_api3('OptionValue', 'getvalue', ...)` | Keep as API3 or use `\Civi::settings()` if it's a setting |
| Raw SQL with SUM/MIN/MAX/GROUP BY | Keep as raw SQL |
| Raw SQL doing direct UPDATE on multiple tables | Keep as raw SQL — API overhead and transaction semantics differ |
| Raw SQL querying SEPA extension tables | Keep as raw SQL — external extension |

## Migration Patterns

### Legacy `civicrm_api()` to `civicrm_api3()`
```php
// Before
$contact = civicrm_api('Contact', 'getsingle', array('version' => 3, 'id' => $id));
// After
$contact = civicrm_api3('Contact', 'getsingle', ['id' => $id]);
```

### API3 get to API4
```php
// Before
$result = civicrm_api3('PriceFieldValue', 'getsingle', ['id' => $id]);
// After
$result = \Civi\Api4\PriceFieldValue::get(FALSE)
  ->addWhere('id', '=', $id)
  ->execute()->single();
```

### API3 get with params to API4
```php
// Before
$result = civicrm_api3('LineItem', 'get', [
  'contribution_id' => $contributionId,
  'options' => ['limit' => 100, 'sort' => 'id DESC'],
]);
foreach ($result['values'] as $item) { ... }
// After
$result = \Civi\Api4\LineItem::get(FALSE)
  ->addWhere('contribution_id', '=', $contributionId)
  ->setLimit(100)
  ->addOrderBy('id', 'DESC')
  ->execute();
foreach ($result as $item) { ... }
```

### API3 getcount to API4
```php
// Before
$count = civicrm_api3('Contribution', 'getcount', ['id' => $id]);
// After
$count = \Civi\Api4\Contribution::get(FALSE)
  ->addWhere('id', '=', $id)
  ->selectRowCount()
  ->execute()
  ->countMatched();
```

### API3 create to API4
```php
// Before (API3 create with id = update)
$result = civicrm_api3('ContributionRecur', 'create', [
  'id' => $id, 'amount' => $amount, 'contact_id' => $contactId,
]);
// After
\Civi\Api4\ContributionRecur::update(FALSE)
  ->addWhere('id', '=', $id)
  ->addValue('amount', $amount)
  ->execute();
```

### Error handling migration
```php
// Before (API3)
try {
  $result = civicrm_api3('Entity', 'getsingle', $params);
} catch (CiviCRM_API3_Exception $e) {
  $errorMessage = $e->getMessage();
}
// After (API4)
try {
  $result = \Civi\Api4\Entity::get(FALSE)
    ->addWhere(...)->execute()->single();
} catch (\CRM_Core_Exception $e) {
  $errorMessage = $e->getMessage();
}
```

## Key API4 Differences

- `addWhere('field', 'operator', 'value')` instead of flat param arrays
- Returns `Result` object (iterable); use `->first()`, `->single()`, `->column('field')`, `->countMatched()`
- `FALSE` first param = `setCheckPermissions(FALSE)`
- Exception class: `\CRM_Core_Exception` (not `CiviCRM_API3_Exception`)
- No `['values']` wrapper — iterate `$result` directly
- `create` vs `update` are separate actions (API3 merges them via presence of `id`)
- `options.limit` to `->setLimit()`, `options.sort` to `->addOrderBy()`
- `return` param to `->addSelect('field1', 'field2')`

## Available Python Scripts

Helper scripts in `.agents/skills/civicrm-api-migration/scripts/`:

```bash
# Entity access report
python3 .agents/skills/civicrm-api-migration/scripts/build_entity_access_report.py \
  --core /Users/linus/Documents/0_Development/civicrm-core --format tsv --stdout

# SQL-to-API hint
python3 .agents/skills/civicrm-api-migration/scripts/sql_to_api_hint.py \
  --core /Users/linus/Documents/0_Development/civicrm-core --sql "SELECT ..."

# Batch API3-to-API4 conversion
python3 .agents/skills/civicrm-api-migration/scripts/upgrade_api3_to_api4.py \
  --core /Users/linus/Documents/0_Development/civicrm-core --input file.php --in-place
```

## Workflow

1. Read the target file to understand context around each API call.
2. Classify each call: migrate to API4, keep as API3, or keep as raw SQL.
3. For migrations: preserve error handling semantics and return value usage.
4. API3 `create` with `id` becomes API4 `update`. API3 `create` without `id` becomes API4 `create`.
5. Mark anything uncertain with `// TODO: verify API4 migration` comments.
6. Suggest running relevant test suite after migration.
