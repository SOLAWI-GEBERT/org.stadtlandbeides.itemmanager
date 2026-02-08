<?php

use Civi\Test\HookInterface;

/**
 * Test sync job in ItemmanagerSetting form.
 *
 * @group headless
 */
class CRM_Itemmanager_Test_ItemmanagerSettingSyncTest extends CRM_Itemmanager_Test_SuiteSeededTestCase implements HookInterface {

  public function testSyncCreatesPeriodsAndSettings(): void {
    $priceSetId = $this->seedIds['price_set'][0] ?? NULL;
    $priceFieldValueId = $this->seedIds['price_field_value'][0] ?? NULL;

    // Ensure clean state for this test.
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

    $form = new CRM_Itemmanager_Form_ItemmanagerSetting();

    $method = new \ReflectionMethod($form, 'syncItemmanager');
    $method->setAccessible(true);
    $method->invoke($form);

    $period = \Civi\Api4\ItemmanagerPeriods::get(FALSE)
      ->addWhere('price_set_id', '=', $priceSetId)
      ->execute()
      ->first();
    $this->assertNotEmpty($period['id'] ?? NULL, 'Sync did not create ItemmanagerPeriods');

    $setting = \Civi\Api4\ItemmanagerSettings::get(FALSE)
      ->addWhere('price_field_value_id', '=', $priceFieldValueId)
      ->execute()
      ->first();
    $this->assertNotEmpty($setting['id'] ?? NULL, 'Sync did not create ItemmanagerSettings');
  }

}
