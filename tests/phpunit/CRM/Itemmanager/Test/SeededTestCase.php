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

    if (!empty($this->seedIds['financial_type'])) {
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
