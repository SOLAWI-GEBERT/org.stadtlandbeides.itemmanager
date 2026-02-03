<?php

use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * Base class for tests with seeded data.
 */
abstract class CRM_Itemmanager_Test_SeededTestCase extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  /** @var array */
  protected $seedIds = [];

  /**
   * Setup used when HeadlessInterface is implemented.
   */
  public function setUpHeadless(): CiviEnvBuilder {
    $extRoot = dirname(__DIR__, 4);
    return \Civi\Test::headless()
      ->installMe($extRoot)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    $this->seedIds = [];

    // Ensure extension is installed in headless DB.
    civicrm_api3('Extension', 'refresh', []);
    civicrm_api3('Extension', 'install', [
      'keys' => [\CRM_Itemmanager_ExtensionUtil::LONG_NAME],
    ]);

    $this->seedDatabase();
  }

  public function tearDown(): void {
    $this->cleanupSeeds();
    parent::tearDown();
  }

  /**
   * Seed minimal data for tests.
   */
  protected function seedDatabase(): void {
    // Seed: create an organization.
    $org = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Organization')
      ->addValue('organization_name', 'Unit Test Org')
      ->execute()
      ->first();

    if (!empty($org['id'])) {
      $this->seedIds['organization'][] = $org['id'];
    }

    // Cleanup any leftovers (idempotent seeds).
    $existingFt = \Civi\Api4\FinancialType::get(FALSE)
      ->addWhere('name', '=', 'Membership VAT 19')
      ->setSelect(['id'])
      ->execute()
      ->first();

    if (!empty($existingFt['id'])) {
      $ftId = (int) $existingFt['id'];
      // Remove dependent price fields/values referencing this financial type (if column exists).
      $this->deleteByFinancialTypeIfColumn('civicrm_price_field_value', $ftId);
      $this->deleteByFinancialTypeIfColumn('civicrm_price_field', $ftId);
      $this->deleteByFinancialTypeIfColumn('civicrm_price_set', $ftId);

      \Civi\Api4\MembershipType::delete(FALSE)
        ->addWhere('financial_type_id', '=', $ftId)
        ->execute();

      \Civi\Api4\EntityFinancialAccount::delete(FALSE)
        ->addWhere('entity_table', '=', 'civicrm_financial_type')
        ->addWhere('entity_id', '=', $ftId)
        ->execute();

      \Civi\Api4\FinancialType::delete(FALSE)
        ->addWhere('id', '=', $ftId)
        ->execute();
    }

    \Civi\Api4\FinancialAccount::delete(FALSE)
      ->addWhere('name', '=', 'Unit Test VAT Account')
      ->execute();

    // Seed: create financial type (Membership VAT 19).
    $finType = \Civi\Api4\FinancialType::create(FALSE)
      ->addValue('name', 'Membership VAT 19')
      ->addValue('is_taxable', TRUE)
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();

    if (!empty($finType['id'])) {
      $this->seedIds['financial_type'][] = $finType['id'];
    }

    // Seed: create membership type.
    $memType = \Civi\Api4\MembershipType::create(FALSE)
      ->addValue('name', 'Unit Test Membership')
      ->addValue('member_of_contact_id', $org['id'] ?? NULL)
      ->addValue('financial_type_id', $finType['id'] ?? NULL)
      ->addValue('duration_unit', 'year')
      ->addValue('duration_interval', 1)
      ->addValue('period_type', 'fixed')
      ->addValue('minimum_fee', 100)
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();

    if (!empty($memType['id'])) {
      $this->seedIds['membership_type'][] = $memType['id'];
    }

    // Seed: create financial account (type id = 7).
    $finAcc = \Civi\Api4\FinancialAccount::create(FALSE)
      ->addValue('name', 'Unit Test VAT Account')
      ->addValue('financial_account_type_id', 7)
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();

    if (!empty($finAcc['id'])) {
      $this->seedIds['financial_account'][] = $finAcc['id'];
    }

    // Seed: link via EntityFinancialAccount (account_relationship = Tax).
    $accountRelValue = $this->findAccountRelationshipValue('Tax');

    $efa = \Civi\Api4\EntityFinancialAccount::create(FALSE)
      ->addValue('entity_table', 'civicrm_financial_type')
      ->addValue('entity_id', $finType['id'] ?? NULL)
      ->addValue('financial_account_id', $finAcc['id'] ?? NULL)
      ->addValue('account_relationship', $accountRelValue)
      ->execute()
      ->first();

    if (!empty($efa['id'])) {
      $this->seedIds['entity_financial_account'][] = $efa['id'];
    }

    // Seed: price set.
    $priceSet = \Civi\Api4\PriceSet::create(FALSE)
      ->addValue('name', 'unit_test_priceset')
      ->addValue('title', 'Unit Test PriceSet')
      ->addValue('extends', 'Membership')
      ->addValue('is_active', TRUE)
      ->addValue('financial_type_id', $finType['id'] ?? NULL)
      ->execute()
      ->first();

    if (!empty($priceSet['id'])) {
      $this->seedIds['price_set'][] = $priceSet['id'];
    }

    // Seed: price field for membership type (checkbox).
    $pfMembership = \Civi\Api4\PriceField::create(FALSE)
      ->addValue('price_set_id', $priceSet['id'] ?? NULL)
      ->addValue('name', 'membership_type')
      ->addValue('label', 'Membership Type')
      ->addValue('html_type', 'CheckBox')
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();

    if (!empty($pfMembership['id'])) {
      $this->seedIds['price_field'][] = $pfMembership['id'];
    }

    $pfvMembership = \Civi\Api4\PriceFieldValue::create(FALSE)
      ->addValue('price_field_id', $pfMembership['id'] ?? NULL)
      ->addValue('label', 'Unit Test Membership')
      ->addValue('amount', 100)
      ->addValue('membership_type_id', $memType['id'] ?? NULL)
      ->addValue('financial_type_id', $finType['id'] ?? NULL)
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();

    if (!empty($pfvMembership['id'])) {
      $this->seedIds['price_field_value'][] = $pfvMembership['id'];
    }

    // Seed: optional fee with tax (checkbox).
    $pfOptional = \Civi\Api4\PriceField::create(FALSE)
      ->addValue('price_set_id', $priceSet['id'] ?? NULL)
      ->addValue('name', 'optional_fee')
      ->addValue('label', 'Optional Fee (Taxable)')
      ->addValue('html_type', 'CheckBox')
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();

    if (!empty($pfOptional['id'])) {
      $this->seedIds['price_field'][] = $pfOptional['id'];
    }

    $pfvOptional = \Civi\Api4\PriceFieldValue::create(FALSE)
      ->addValue('price_field_id', $pfOptional['id'] ?? NULL)
      ->addValue('label', 'Optional Fee')
      ->addValue('amount', 10)
      ->addValue('financial_type_id', $finType['id'] ?? NULL)
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();

    if (!empty($pfvOptional['id'])) {
      $this->seedIds['price_field_value'][] = $pfvOptional['id'];
    }
  }

  /**
   * Delete rows by financial_type_id if the column exists.
   */
  protected function deleteByFinancialTypeIfColumn(string $table, int $ftId): void {
    if (!$this->columnExists($table, 'financial_type_id')) {
      return;
    }
    CRM_Core_DAO::executeQuery("DELETE FROM {$table} WHERE financial_type_id = %1", [
      1 => [$ftId, 'Integer'],
    ]);
  }

  /**
   * Check if a column exists in current DB.
   */
  protected function columnExists(string $table, string $column): bool {
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT COUNT(*) AS cnt
       FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = %1
         AND COLUMN_NAME = %2",
      [
        1 => [$table, 'String'],
        2 => [$column, 'String'],
      ]
    );
    if ($dao->fetch()) {
      return ((int) $dao->cnt) > 0;
    }
    return FALSE;
  }

  /**
   * Find option value for account_relationship (e.g. Tax).
   */
  protected function findAccountRelationshipValue(string $labelOrName): int {
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT ov.value FROM civicrm_option_value ov
       JOIN civicrm_option_group og ON og.id = ov.option_group_id
       WHERE og.name = 'account_relationship'
         AND (ov.name = %1 OR ov.label = %1)
       LIMIT 1",
      [1 => [$labelOrName, 'String']]
    );
    if ($dao->fetch()) {
      return (int) $dao->value;
    }

    // Fallback: any value that contains the label.
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT ov.value FROM civicrm_option_value ov
       JOIN civicrm_option_group og ON og.id = ov.option_group_id
       WHERE og.name = 'account_relationship'
         AND (ov.name LIKE %1 OR ov.label LIKE %1)
       LIMIT 1",
      [1 => ['%' . $labelOrName . '%', 'String']]
    );
    if ($dao->fetch()) {
      return (int) $dao->value;
    }

    throw new \RuntimeException("account_relationship option not found: {$labelOrName}");
  }

  /**
   * Cleanup seeded data.
   */
  protected function cleanupSeeds(): void {
    if (!empty($this->seedIds['price_field_value'])) {
      \Civi\Api4\PriceFieldValue::delete(FALSE)
        ->addWhere('id', 'IN', $this->seedIds['price_field_value'])
        ->execute();
    }

    if (!empty($this->seedIds['price_field'])) {
      \Civi\Api4\PriceField::delete(FALSE)
        ->addWhere('id', 'IN', $this->seedIds['price_field'])
        ->execute();
    }

    if (!empty($this->seedIds['price_set'])) {
      \Civi\Api4\PriceSet::delete(FALSE)
        ->addWhere('id', 'IN', $this->seedIds['price_set'])
        ->execute();
    }

    if (!empty($this->seedIds['entity_financial_account'])) {
      \Civi\Api4\EntityFinancialAccount::delete(FALSE)
        ->addWhere('id', 'IN', $this->seedIds['entity_financial_account'])
        ->execute();
    }

    if (!empty($this->seedIds['financial_account'])) {
      \Civi\Api4\FinancialAccount::delete(FALSE)
        ->addWhere('id', 'IN', $this->seedIds['financial_account'])
        ->execute();
    }

    if (!empty($this->seedIds['membership_type'])) {
      \Civi\Api4\MembershipType::delete(FALSE)
        ->addWhere('id', 'IN', $this->seedIds['membership_type'])
        ->execute();
    }

    if (!empty($this->seedIds['financial_type'])) {
      foreach ($this->seedIds['financial_type'] as $ftId) {
        $ftId = (int) $ftId;
        $this->deleteByFinancialTypeIfColumn('civicrm_price_field_value', $ftId);
        $this->deleteByFinancialTypeIfColumn('civicrm_price_field', $ftId);
        $this->deleteByFinancialTypeIfColumn('civicrm_price_set', $ftId);
      }

      \Civi\Api4\FinancialType::delete(FALSE)
        ->addWhere('id', 'IN', $this->seedIds['financial_type'])
        ->execute();
    }

    if (!empty($this->seedIds['organization'])) {
      \Civi\Api4\Contact::delete(FALSE)
        ->addWhere('id', 'IN', $this->seedIds['organization'])
        ->execute();
    }

    // Drop extension tables if they were created.
    CRM_Core_DAO::executeQuery('SET FOREIGN_KEY_CHECKS=0');
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS civicrm_itemmanager_periods');
    CRM_Core_DAO::executeQuery('DROP TABLE IF EXISTS civicrm_itemmanager_settings');
    CRM_Core_DAO::executeQuery('SET FOREIGN_KEY_CHECKS=1');

    $this->seedIds = [];
  }

}
