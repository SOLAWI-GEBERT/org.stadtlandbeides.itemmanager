---
name: unittest
description: Create or update PHPUnit tests for changed or specified production code in this CiviCRM extension. Write and update test code only — do not execute tests.
argument-hint: [file-or-class]
allowed-tools: Read, Write, Edit, Grep, Glob, Bash
---

Create or update PHPUnit tests for: $ARGUMENTS

## Repository Baseline

- PHPUnit config: `phpunit.xml.dist`
- Bootstrap: `tests/phpunit/helper/bootstrap.php`
- Helper base classes: `tests/phpunit/helper/CRM/Itemmanager/Test/*.php`
- Suites: `tests/phpunit/suites/{migration,sanity,settings,util,updateitems,dashboard,unit}`

## Workflow

1. Read the production code that needs test coverage.
2. Map it to an existing suite or create a new test in the closest existing suite directory.
3. Select the right base class (see matrix below).
4. Reuse seeded IDs through `$this->seedIds` — never hardcode numeric IDs.
5. Add minimal fixture setup in the test class and targeted cleanup in `tearDown()`.
6. Assert both behavior and persisted state for DB-affecting code.
7. Cover at least one error/edge path for changed logic.
8. Suggest manual `phpunit` commands the user can run.

## Base Class Selection

| Need | Base class | Notes |
|---|---|---|
| Pure isolated PHP logic | `\PHPUnit\Framework\TestCase` | Fast unit tests, no Civi dependency. |
| Headless Civi + extension data seed | `CRM_Itemmanager_Test_SeededTestCase` | Fresh seed per test. Strong isolation. |
| Headless Civi + shared seed across suite | `CRM_Itemmanager_Test_SuiteSeededTestCase` | Seed once, reuse IDs, faster. |
| Suite seed + membership/order fixture | `CRM_Itemmanager_Test_MembershipSeededTestCase` | Adds contact and membership order. |

For headless tests, also implement `Civi\Test\HookInterface` and keep class name prefix `CRM_Itemmanager_Test_`.

## Seed Topology

`SeededTestCase` seeds and stores IDs in `$this->seedIds`:
- `organization`, `financial_type`, `membership_type`, `financial_account`
- `entity_financial_account`, `price_set`, `price_field`, `price_field_value`
- `extension_installed`

`MembershipSeededTestCase` adds:
- `member_contact` (individual), `order` (API3 Order with line items)

## Lifecycle

- `SeededTestCase`: installs extension and seeds in `setUp()`, cleans in `tearDown()`.
- `SuiteSeededTestCase`: seeds once, skips per-test cleanup; cleanup at shutdown.
- When using suite-seeded bases, explicitly clean records created by individual tests.
- Preserve/restore `$_REQUEST`/`$_GET`/`$_POST` with `try/finally`.

## Suite-Specific Patterns

### migration
- Reinstall extension before migration assertions.
- Execute `sql/upgrade_*.sql` files in natural sort order.
- Re-run to assert idempotency. Assert table existence and columns.

### settings
- Start from seeded `price_set` and `price_field_value`.
- Delete existing `ItemmanagerPeriods`/`ItemmanagerSettings` before creating test records.
- Use reflection helpers for private methods. Inject controller double for `postProcess`.

### util
- Use `MembershipSeededTestCase`.
- Build contribution/line-item fixtures with API3.
- Assert monetary fields with tolerance (`0.01`).

### updateitems
- Use page double overriding `assign`, `processError`, `processSuccess`, `processInfo`.
- Build deterministic fixtures, assert displayed candidates and persisted updates.
- Validate financial side effects (`FinancialItem`, `EntityFinancialTrxn`).

### dashboard
- Use page double, assert assigned template payload shape.
- Verify both "data exists" and "no memberships" flows.

### unit
- Plain `\PHPUnit\Framework\TestCase` only. No Civi bootstrap.

## Assertion Rules

- `assertSame` for exact scalar checks.
- Tolerance (`0.01`) for money/tax.
- `assertNotEmpty` / `assertArrayHasKey` before deep checks.
- Normalize dates to `Y-m-d H:i:s` before compare.
- Re-fetch from API/DB after updates instead of trusting in-memory values.

## Cleanup Rules

- Never assume auto-increment IDs.
- Track created IDs in arrays, delete in `tearDown()`.
- Filter to positive ints before API4 delete: `array_values(array_filter(array_map('intval', ...)))`.
- Idempotent setup: remove conflicting records before create.

## Templates

### Headless integration test with suite seed
```php
<?php
use Civi\Test\HookInterface;

class CRM_Itemmanager_Test_NewFeatureTest extends CRM_Itemmanager_Test_SuiteSeededTestCase implements HookInterface {
  protected array $createdIds = [];

  public function tearDown(): void {
    $ids = array_values(array_filter(array_map('intval', $this->createdIds)));
    if (!empty($ids)) {
      \Civi\Api4\ItemmanagerSettings::delete(FALSE)
        ->addWhere('id', 'IN', $ids)->execute();
    }
    parent::tearDown();
  }

  public function testCreatesExpectedSetting(): void {
    $priceFieldValueId = (int) ($this->seedIds['price_field_value'][0] ?? 0);
    $periodId = (int) (\Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', (int) $this->seedIds['price_set'][0])
      ->execute()->first()['id'] ?? 0);

    $created = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->addValue('price_field_value_id', $priceFieldValueId)
      ->addValue('itemmanager_periods_id', $periodId)
      ->execute()->first();

    $this->assertGreaterThan(0, (int) ($created['id'] ?? 0));
    $this->createdIds[] = (int) $created['id'];
  }
}
```

### Page test-double pattern
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

### Reflection helper for private methods
```php
private function invokePrivateMethod(object $object, string $method, array $args = []) {
  $ref = new \ReflectionMethod($object, $method);
  $ref->setAccessible(true);
  return $ref->invokeArgs($object, $args);
}
```
