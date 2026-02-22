---
name: unittest
description: Create and update PHPUnit tests for the org.stadtlandbeides.itemmanager CiviCRM extension. Use when work involves adding or refactoring tests in tests/phpunit, choosing the correct seeded headless base class, extending API3/API4 integration coverage, maintaining deterministic fixture setup/cleanup, or writing test doubles for pages/forms without executing the test suite.
---

# Unittest

Create and maintain PHPUnit coverage with the repository's existing CiviCRM headless setup.
Write and update test code only. Do not execute tests.

## Repository Baseline

- PHPUnit config: `phpunit.xml.dist`
- Bootstrap: `tests/phpunit/helper/bootstrap.php`
- Helper base classes: `tests/phpunit/helper/CRM/Itemmanager/Test/*.php`
- Suites:
  - `tests/phpunit/suites/migration`
  - `tests/phpunit/suites/sanity`
  - `tests/phpunit/suites/settings`
  - `tests/phpunit/suites/util`
  - `tests/phpunit/suites/updateitems`
  - `tests/phpunit/suites/dashboard`
  - `tests/phpunit/suites/unit`

## Follow This Workflow

1. Map changed production code to an existing suite or create a new test in the closest existing suite directory.
2. Select the right base class (see matrix below).
3. Reuse seeded IDs through `$this->seedIds`; never hardcode numeric IDs.
4. Add minimal fixture setup in the test class and targeted cleanup in `tearDown()`.
5. Assert both behavior and persisted state for DB-affecting code.
6. Cover at least one error/edge path for changed logic.
7. Suggest manual `phpunit` commands for the user if helpful, but do not run them.

## Select Base Class Correctly

| Need | Base class | Notes |
|---|---|---|
| Pure isolated PHP logic | `\PHPUnit\Framework\TestCase` | Use for fast unit tests with no Civi env dependency. |
| Headless Civi + extension data seed | `CRM_Itemmanager_Test_SeededTestCase` | Fresh seed per test. Use when each test needs strong isolation. |
| Headless Civi + shared seed across suite | `CRM_Itemmanager_Test_SuiteSeededTestCase` | Seed once, reuse IDs, faster integration suites. |
| Suite seed plus membership/order fixture | `CRM_Itemmanager_Test_MembershipSeededTestCase` | Adds individual contact and membership order fixture. |

For headless tests, also implement `Civi\Test\HookInterface` and keep class name prefix `CRM_Itemmanager_Test_`.

## Understand The Seed Topology

`CRM_Itemmanager_Test_SeededTestCase` seeds these records and stores IDs in `$this->seedIds`:

- `organization`: contact type Organization (`Unit Test Org`)
- `financial_type`: `Membership VAT 19`
- `membership_type`: `Unit Test Membership`
- `financial_account`: `Unit Test VAT Account`
- `entity_financial_account`: link financial type to tax account relationship
- `price_set`: `unit_test_priceset`
- `price_field`: membership and optional fee fields
- `price_field_value`: membership and optional fee values
- `extension_installed`: marker for extension install/uninstall handling

`CRM_Itemmanager_Test_MembershipSeededTestCase` adds:

- `member_contact`: individual `Unit Member`
- `order`: API3 Order used to create membership-related line items

## Respect Lifecycle Semantics

- `SeededTestCase` installs extension and seeds in `setUp()`, then cleans in `tearDown()`.
- `SuiteSeededTestCase` seeds once and skips per-test cleanup; cleanup runs at shutdown.
- When using suite-seeded bases, explicitly clean records created by individual tests (`ItemmanagerPeriods`, `ItemmanagerSettings`, extra contacts, temporary price sets/fields/values).
- Use idempotent cleanup in `setUp()` for scoped records to avoid cross-test leakage.
- Preserve and restore global state (`$_REQUEST`, `$_GET`, `$_POST`) with `try/finally`.

## Suite-Specific Patterns To Reuse

### `migration`

- Reinstall extension before migration assertions.
- Execute all `sql/upgrade_*.sql` files in natural sort order.
- Re-run migrations to assert idempotency.
- Assert table existence and minimal required columns.

### `settings`

- Start from seeded `price_set` and `price_field_value`.
- Delete existing `ItemmanagerPeriods`/`ItemmanagerSettings` for those IDs before creating test records.
- For form internals, use reflection helpers (`ReflectionMethod`, `ReflectionProperty`) to test private helpers safely.
- For `postProcess`, inject a lightweight controller double exposing `exportValues`.

### `util`

- Use membership-seeded base class.
- Build realistic contribution/line-item fixtures with API3 where API4 coverage is incomplete (`Order`, `LineItem`, `MembershipPayment`).
- Assert monetary fields with tolerance (`assertEquals(..., '', 0.01)`).
- Add fallback lookups by business keys (for example by name/label) when seeded IDs might become stale.

### `updateitems`

- Use a page double overriding `assign`, `processError`, `processSuccess`, `processInfo`.
- Build deterministic fixtures in helper methods, then assert both displayed change candidates and persisted updates.
- Validate financial side effects (`FinancialItem`, `EntityFinancialTrxn`) in addition to line-item/contribution changes.
- Include negative tests (unknown contact, deleted price field value).

### `dashboard`

- Use a page double and assert assigned template payload shape.
- Add helper to ensure required itemmanager records exist for seeded field values.
- Verify both "data exists" and "no memberships" flows.

### `unit`

- Use plain PHPUnit test case only for truly isolated logic.
- Keep these tests independent from Civi bootstrap assumptions.

## Assertion Rules

- Prefer `assertSame` for exact scalar and type-sensitive checks.
- For money/tax calculations, use tolerance (`0.01`) instead of exact float equality.
- Assert presence before deep field checks (`assertNotEmpty`, `assertArrayHasKey`).
- For date assertions, normalize format explicitly (`Y-m-d H:i:s`) before compare.
- After updates, assert by re-fetching from API/DB instead of trusting in-memory values.

## Determinism And Cleanup Checklist

- Never assume auto-increment IDs.
- Never rely on implicit execution order between tests.
- Track created IDs in class arrays and delete them in `tearDown()`.
- Filter cleanup arrays to positive ints before API4 delete (`array_map('intval')`, `array_filter`).
- Keep setup idempotent: remove conflicting records for the same business keys before create.

## Templates

### 1) Headless integration test with suite seed

```php
<?php

use Civi\Test\HookInterface;

class CRM_Itemmanager_Test_NewFeatureTest extends CRM_Itemmanager_Test_SuiteSeededTestCase implements HookInterface {

  /** @var array<int> */
  protected array $createdIds = [];

  public function tearDown(): void {
    $ids = array_values(array_filter(array_map('intval', $this->createdIds)));
    if (!empty($ids)) {
      \Civi\Api4\ItemmanagerSettings::delete(FALSE)
        ->addWhere('id', 'IN', $ids)
        ->execute();
    }
    parent::tearDown();
  }

  public function testCreatesExpectedSetting(): void {
    $priceFieldValueId = (int) ($this->seedIds['price_field_value'][0] ?? 0);
    $periodId = (int) (\Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', (int) $this->seedIds['price_set'][0])
      ->execute()
      ->first()['id'] ?? 0);

    $created = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->addValue('price_field_value_id', $priceFieldValueId)
      ->addValue('itemmanager_periods_id', $periodId)
      ->execute()
      ->first();

    $this->assertGreaterThan(0, (int) ($created['id'] ?? 0));
    $this->createdIds[] = (int) $created['id'];
  }
}
```

### 2) Page test-double pattern

```php
class CRM_Itemmanager_Test_PageDouble extends CRM_Itemmanager_Page_Dashboard {
  public array $assignedValues = [];

  public function assign($var, $value = NULL) {
    if (is_array($var)) {
      foreach ($var as $k => $v) {
        $this->assignedValues[(string) $k] = $v;
      }
      return;
    }
    $this->assignedValues[(string) $var] = $value;
  }
}
```

### 3) Reflection helper pattern for private form methods

```php
private function invokePrivateMethod(object $object, string $method, array $args = []) {
  $ref = new \ReflectionMethod($object, $method);
  $ref->setAccessible(true);
  return $ref->invokeArgs($object, $args);
}
```

## Manual Run Hints (Do Not Execute)

- `phpunit -c phpunit.xml.dist`
- `phpunit -c phpunit.xml.dist tests/phpunit/suites/settings/ItemmanagerSettingFormTest.php`
- `phpunit -c phpunit.xml.dist --filter testUpdateDataUpdatesPersistedValuesAndFinancialRecords`
