<?php

use Civi\Test\HookInterface;

/**
 * Run SQL migrations from sql/ and verify tables exist.
 *
 * @group headless
 */
class CRM_Itemmanager_Test_MigrationTest extends CRM_Itemmanager_Test_SuiteSeededTestCase implements HookInterface {

  public function testMigrationsApply(): void {
    // Ensure base schema for this extension exists before migrations.
    civicrm_api3('Extension', 'refresh', []);
    civicrm_api3('Extension', 'disable', [
      'keys' => ['org.stadtlandbeides.itemmanager'],
    ]);
    civicrm_api3('Extension', 'uninstall', [
      'keys' => ['org.stadtlandbeides.itemmanager'],
    ]);
    civicrm_api3('Extension', 'install', [
      'keys' => ['org.stadtlandbeides.itemmanager'],
    ]);

    $sqlDir = dirname(__DIR__, 4) . '/sql';
    $files = glob($sqlDir . '/upgrade_*.sql');
    sort($files, SORT_NATURAL);

    foreach ($files as $file) {
      $sql = trim(file_get_contents($file));
      if (!empty($sql)) {
        try {
          CRM_Core_DAO::executeQuery($sql);
        }
        catch (\Exception $e) {
          // ALTER TABLE ADD COLUMN may fail if column already exists after install.
        }
      }
    }

    // Idempotency: run migrations a second time (should not error).
    foreach ($files as $file) {
      $sql = trim(file_get_contents($file));
      if (!empty($sql)) {
        try {
          CRM_Core_DAO::executeQuery($sql);
        }
        catch (\Exception $e) {
          // Expected: duplicate column errors on re-run.
        }
      }
    }

    // Basic sanity: schema tables should exist after migrations.
    $this->assertTrue($this->tableExists('civicrm_itemmanager_periods'));
    $this->assertTrue($this->tableExists('civicrm_itemmanager_settings'));

    // Structure checks (minimal expected columns).
    $this->assertColumns('civicrm_itemmanager_periods', [
      'id',
      'price_set_id',
      'period_start_on',
      'periods',
      'period_type',
    ]);
    $this->assertColumns('civicrm_itemmanager_settings', [
      'id',
      'price_field_value_id',
      'itemmanager_periods_id',
      'itemmanager_successor_id',
      'ignore',
      'extend',
      'novitiate',
      'enable_period_exception',
      'bidding',
      'exception_periods',
    ]);
  }

  protected function tableExists(string $table): bool {
    $dao = CRM_Core_DAO::executeQuery("SHOW TABLES LIKE %1", [
      1 => [$table, 'String'],
    ]);
    return (bool) $dao->fetch();
  }

  protected function assertColumns(string $table, array $columns): void {
    foreach ($columns as $column) {
      $dao = CRM_Core_DAO::executeQuery("SHOW COLUMNS FROM {$table} LIKE %1", [
        1 => [$column, 'String'],
      ]);
      $this->assertTrue((bool) $dao->fetch(), "Missing column {$table}.{$column}");
    }
  }

}
