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

    $priceSetId = $this->seedIds['price_set'][0] ?? NULL;
    $priceFieldValueId = $this->seedIds['price_field_value'][0] ?? NULL;

    // Idempotent cleanup for this test scope.
    if ($priceSetId) {
      \Civi\Api4\ItemmanagerPeriods::delete(FALSE)
        ->addWhere('price_set_id', '=', $priceSetId)
        ->execute();
    }
    if ($priceFieldValueId) {
      \Civi\Api4\ItemmanagerSettings::delete(FALSE)
        ->addWhere('price_field_value_id', '=', $priceFieldValueId)
        ->execute();
    }

    $period = \Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', $priceSetId)
      ->execute()
      ->first();

    if (!empty($period['id'])) {
      $this->itemmanagerSettingsIds['period'][] = $period['id'];
    }

    $setting = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->addValue('price_field_value_id', $priceFieldValueId)
      ->addValue('itemmanager_periods_id', $period['id'] ?? NULL)
      ->execute()
      ->first();

    if (!empty($setting['id'])) {
      $this->itemmanagerSettingsIds['setting'][] = $setting['id'];
    }
  }

  public function tearDown(): void {
    if (!empty($this->itemmanagerSettingsIds['setting'])) {
      \Civi\Api4\ItemmanagerSettings::delete(FALSE)
        ->addWhere('id', 'IN', $this->itemmanagerSettingsIds['setting'])
        ->execute();
    }

    if (!empty($this->itemmanagerSettingsIds['period'])) {
      \Civi\Api4\ItemmanagerPeriods::delete(FALSE)
        ->addWhere('id', 'IN', $this->itemmanagerSettingsIds['period'])
        ->execute();
    }

    parent::tearDown();
  }

  public function testApi4Crud(): void {
    $settingId = $this->itemmanagerSettingsIds['setting'][0] ?? NULL;
    $this->assertNotEmpty($settingId, 'ItemmanagerSettings not created');

    $setting = \Civi\Api4\ItemmanagerSettings::get(FALSE)
      ->addWhere('id', '=', $settingId)
      ->execute()
      ->first();

    $this->assertEquals($settingId, $setting['id'] ?? NULL);

    // Update a flag to verify write access.
    \Civi\Api4\ItemmanagerSettings::update(FALSE)
      ->addValue('ignore', 1)
      ->addWhere('id', '=', $settingId)
      ->execute();

    $setting2 = \Civi\Api4\ItemmanagerSettings::get(FALSE)
      ->addWhere('id', '=', $settingId)
      ->execute()
      ->first();

    $this->assertEquals(1, (int) ($setting2['ignore'] ?? 0));

    // DB check via API4 get (periods/settings exist).
    $periodId = $this->itemmanagerSettingsIds['period'][0] ?? NULL;
    $period = \Civi\Api4\ItemmanagerPeriods::get(FALSE)
      ->addWhere('id', '=', $periodId)
      ->execute()
      ->first();
    $this->assertEquals($periodId, $period['id'] ?? NULL);

    $setting3 = \Civi\Api4\ItemmanagerSettings::get(FALSE)
      ->addWhere('id', '=', $settingId)
      ->execute()
      ->first();
    $this->assertEquals($settingId, $setting3['id'] ?? NULL);
  }

}
