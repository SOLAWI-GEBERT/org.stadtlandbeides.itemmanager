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
  }

  /**
   * Cleanup seeded data.
   */
  protected function cleanupSeeds(): void {
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
