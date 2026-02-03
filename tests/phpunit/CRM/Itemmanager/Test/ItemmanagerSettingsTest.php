<?php

use Civi\Test\HookInterface;

/**
 * Skeleton for ItemmanagerSettings tests.
 *
 * @group headless
 */
class CRM_Itemmanager_Test_ItemmanagerSettingsTest extends CRM_Itemmanager_Test_SuiteSeededTestCase implements HookInterface {

  /** @var array */
  protected $itemmanagerSettingsIds = [];

  public function setUp(): void {
    parent::setUp();
    // TODO: create baseline ItemmanagerSettings records via API4/BAO.
  }

  public function tearDown(): void {
    // TODO: delete ItemmanagerSettings records created in setUp/test.
    parent::tearDown();
  }

}
