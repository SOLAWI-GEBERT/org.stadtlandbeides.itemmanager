<?php

use Civi\Test\HookInterface;

/**
 * Test sync job in ItemmanagerSetting form.
 *
 * @group headless
 */
class CRM_Itemmanager_Test_ItemmanagerSettingSyncTest extends CRM_Itemmanager_Test_SuiteSeededTestCase implements HookInterface {

  /** @var int[] */
  protected array $createdSettingIds = [];

  /** @var int[] */
  protected array $createdPeriodIds = [];

  public function tearDown(): void {
    $settingIds = array_values(array_filter(array_map('intval', $this->createdSettingIds)));
    if (!empty($settingIds)) {
      \Civi\Api4\ItemmanagerSettings::delete(FALSE)
        ->addWhere('id', 'IN', $settingIds)
        ->execute();
    }
    $periodIds = array_values(array_filter(array_map('intval', $this->createdPeriodIds)));
    if (!empty($periodIds)) {
      \Civi\Api4\ItemmanagerPeriods::delete(FALSE)
        ->addWhere('id', 'IN', $periodIds)
        ->execute();
    }
    parent::tearDown();
  }

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

  public function testSyncRemovesOrphanedSettingsWhenPriceFieldValueDeleted(): void {
    $priceSetId = $this->seedIds['price_set'][0] ?? NULL;
    $priceFieldId = $this->seedIds['price_field'][0] ?? NULL;
    $financialTypeId = $this->seedIds['financial_type'][0] ?? NULL;

    $this->assertNotEmpty($priceSetId);

    // Clean existing itemmanager data.
    \Civi\Api4\ItemmanagerPeriods::delete(FALSE)
      ->addWhere('price_set_id', '=', $priceSetId)
      ->execute();

    // Create a temporary PFV that we will delete.
    $tempPfv = \Civi\Api4\PriceFieldValue::create(FALSE)
      ->addValue('price_field_id', $priceFieldId)
      ->addValue('label', 'Orphan Test Item')
      ->addValue('amount', 10)
      ->addValue('financial_type_id', $financialTypeId)
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();
    $tempPfvId = (int) $tempPfv['id'];

    // Create period + setting for this PFV.
    $period = \Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', $priceSetId)
      ->execute()
      ->first();
    $this->createdPeriodIds[] = (int) $period['id'];

    $setting = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->addValue('price_field_value_id', $tempPfvId)
      ->addValue('itemmanager_periods_id', (int) $period['id'])
      ->execute()
      ->first();
    $settingId = (int) $setting['id'];
    $this->createdSettingIds[] = $settingId;

    // Now delete the PFV to make the setting orphaned.
    \Civi\Api4\PriceFieldValue::delete(FALSE)
      ->addWhere('id', '=', $tempPfvId)
      ->execute();

    // Run sync.
    $form = new CRM_Itemmanager_Form_ItemmanagerSetting();
    $method = new \ReflectionMethod($form, 'syncItemmanager');
    $method->setAccessible(true);
    $method->invoke($form);

    // The orphaned setting should have been removed by sync.
    $remaining = \Civi\Api4\ItemmanagerSettings::get(FALSE)
      ->addWhere('price_field_value_id', '=', $tempPfvId)
      ->execute();

    $this->assertSame(0, $remaining->count(), 'Orphaned setting should be removed after sync');
  }

}
