---
name: unittest
description: Create and update PHPUnit tests for the org.stadtlandbeides.itemmanager CiviCRM extension. Use when work involves adding new tests, adapting seeded headless fixtures, or extending coverage in tests/phpunit without executing the test suite.
---

# Unittest

## Overview

Create and maintain PHPUnit coverage for this extension with the existing CiviCRM headless test setup. Restrict this skill to writing and updating tests only.

## Workflow

1. Locate the changed production code and map it to existing tests in `tests/phpunit`.
2. Choose the correct base style:
   - Use plain `\PHPUnit\Framework\TestCase` for isolated logic.
   - Use `CRM_Itemmanager_Test_SuiteSeededTestCase` plus `HookInterface` for headless/API4 integration behavior.
3. Place each test in the matching suite folder under `tests/phpunit/suites/<suite-name>`.
4. Keep shared seed/setup/teardown code only in `tests/phpunit/helper`.
5. Keep class names with the `CRM_Itemmanager_Test_` prefix for extension integration tests.
6. Reuse seeded data through `$this->seedIds`, and keep setup/cleanup idempotent.
7. Assert both returned values and persistent state changes where relevant (for example API4 create/update/delete effects).
8. Provide optional `phpunit` command suggestions for manual execution by the user, but do not execute them.

## Execution Constraint

- Do not run tests inside this skill.
- Assume the environment requires external infrastructure that is not available to Codex.
- If needed, list commands for manual execution by the user:
  - `phpunit -c phpunit.xml.dist`
  - `phpunit -c phpunit.xml.dist tests/phpunit/suites/settings/ItemmanagerSettingsTest.php`
  - `phpunit -c phpunit.xml.dist --filter testApi4Crud`

## Repository Anchors

- Bootstrap entrypoint: `tests/phpunit/bootstrap.php`
- PHPUnit configuration: `phpunit.xml.dist`
- Suite directories: `tests/phpunit/suites/*`
- Seeded base classes:
  - `tests/phpunit/helper/SeededTestCase.php`
  - `tests/phpunit/helper/SuiteSeededTestCase.php`

## Quality Gates

- Keep tests deterministic and independent across runs.
- Cover both expected success and relevant negative/edge behavior.
- Prefer assertions close to extension behavior (API4 output and persisted records).
- Avoid fragile assumptions like hardcoded auto-increment IDs.
- Never execute PHPUnit commands in this skill; limit output to test code and manual run hints.
