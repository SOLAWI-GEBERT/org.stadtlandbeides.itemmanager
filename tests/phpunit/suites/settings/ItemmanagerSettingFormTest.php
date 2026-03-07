<?php

use Civi\Test\HookInterface;

/**
 * Tests for ItemmanagerSetting form helpers and processing.
 *
 * The SuiteSeededTestCase seeds the database once for the whole test run and
 * reuses those IDs across tests to keep this class lightweight.
 *
 * @group headless
 */
class CRM_Itemmanager_Test_ItemmanagerSettingFormTest extends CRM_Itemmanager_Test_SuiteSeededTestCase implements HookInterface {

  /** @var array */
  protected array $itemmanagerPeriodIds = [];

  /** @var array */
  protected array $itemmanagerSettingIds = [];

  /** @var array */
  protected array $priceSetIds = [];

  /** @var array */
  protected array $priceFieldIds = [];

  /** @var array */
  protected array $priceFieldValueIds = [];

  public function setUp(): void {
    parent::setUp();

    $this->itemmanagerPeriodIds = [];
    $this->itemmanagerSettingIds = [];
    $this->priceSetIds = [];
    $this->priceFieldIds = [];
    $this->priceFieldValueIds = [];

    $priceSetId = $this->seedIds['price_set'][0] ?? NULL;
    $priceFieldValueId = $this->seedIds['price_field_value'][0] ?? NULL;

    civicrm_api3('Setting', 'create', [
      'itemmanager_show_hiden_periods' => 1,
    ]);

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

    $period = [];
    if ($priceSetId) {
      $period = $this->createPeriod((int) $priceSetId);
    }

    if ($priceFieldValueId && !empty($period['id'])) {
      $this->createSetting((int) $priceFieldValueId, (int) $period['id']);
    }
  }

  public function tearDown(): void {
    $settingIds = $this->filterIds($this->itemmanagerSettingIds);
    if (!empty($settingIds)) {
      \Civi\Api4\ItemmanagerSettings::delete(FALSE)
        ->addWhere('id', 'IN', $settingIds)
        ->execute();
    }

    $periodIds = $this->filterIds($this->itemmanagerPeriodIds);
    if (!empty($periodIds)) {
      \Civi\Api4\ItemmanagerPeriods::delete(FALSE)
        ->addWhere('id', 'IN', $periodIds)
        ->execute();
    }

    $priceFieldValueIds = $this->filterIds($this->priceFieldValueIds);
    if (!empty($priceFieldValueIds)) {
      \Civi\Api4\PriceFieldValue::delete(FALSE)
        ->addWhere('id', 'IN', $priceFieldValueIds)
        ->execute();
    }

    $priceFieldIds = $this->filterIds($this->priceFieldIds);
    if (!empty($priceFieldIds)) {
      \Civi\Api4\PriceField::delete(FALSE)
        ->addWhere('id', 'IN', $priceFieldIds)
        ->execute();
    }

    $priceSetIds = $this->filterIds($this->priceSetIds);
    if (!empty($priceSetIds)) {
      \Civi\Api4\PriceSet::delete(FALSE)
        ->addWhere('id', 'IN', $priceSetIds)
        ->execute();
    }

    parent::tearDown();
  }

  public function testGetIndexFromDurationKeyReturnsIndex(): void {
    $form = new CRM_Itemmanager_Form_ItemmanagerSetting();

    $durationOptions = $this->getPrivateProperty($form, '_duration_options');
    $durationKey = array_key_first($durationOptions);
    $expectedIndex = array_search($durationKey, array_keys($durationOptions), true);

    $index = $this->invokePrivateMethod($form, 'getIndexFromDurationKey', [$durationKey]);

    $this->assertSame($expectedIndex, $index);
  }

  public function testGetEmptySelectionReturnsDefaultEntry(): void {
    $form = new CRM_Itemmanager_Form_ItemmanagerSetting();

    $selection = $this->invokePrivateMethod($form, 'getEmptySelection');

    $this->assertArrayHasKey(0, $selection);
    $this->assertSame(ts('No Successor'), $selection[0]);
  }

  public function testGetPeriodSelectionReturnsOtherPeriods(): void {
    $priceSetId = (int) ($this->seedIds['price_set'][0] ?? 0);
    $financialTypeId = (int) ($this->seedIds['financial_type'][0] ?? 0);

    $this->assertNotSame(0, $priceSetId, 'Missing seeded price set.');

    $priceSet = civicrm_api3('PriceSet', 'getsingle', ['id' => $priceSetId]);

    $otherPriceSet = \Civi\Api4\PriceSet::create(FALSE)
      ->addValue('name', 'unit_test_priceset_successor')
      ->addValue('title', 'Unit Test Successor PriceSet')
      ->addValue('extends', 'Membership')
      ->addValue('is_active', TRUE)
      ->addValue('financial_type_id', $financialTypeId)
      ->execute()
      ->first();
    if (!empty($otherPriceSet['id'])) {
      $this->priceSetIds[] = (int) $otherPriceSet['id'];
    }

    $currentPeriod = $this->createPeriod($priceSetId);
    $otherPeriod = $this->createPeriod((int) $otherPriceSet['id']);

    $form = new CRM_Itemmanager_Form_ItemmanagerSetting();

    $selection = $this->invokePrivateMethod($form, 'getPeriodSelection', [$currentPeriod, $priceSet]);

    $this->assertArrayHasKey(0, $selection);
    $this->assertArrayHasKey((int) $otherPeriod['id'], $selection);
    $this->assertSame('Unit Test Successor PriceSet', $selection[(int) $otherPeriod['id']]);
  }

  public function testGetItemSelectionReturnsSuccessorFieldOptions(): void {
    $priceSetId = (int) ($this->seedIds['price_set'][0] ?? 0);
    $financialTypeId = (int) ($this->seedIds['financial_type'][0] ?? 0);
    $membershipTypeId = (int) ($this->seedIds['membership_type'][0] ?? 0);

    $priceFieldId = (int) ($this->seedIds['price_field'][0] ?? 0);
    $priceFieldValueId = (int) ($this->seedIds['price_field_value'][0] ?? 0);

    $this->assertNotSame(0, $priceSetId, 'Missing seeded price set.');
    $this->assertNotSame(0, $priceFieldValueId, 'Missing seeded price field value.');

    $priceSet = civicrm_api3('PriceSet', 'getsingle', ['id' => $priceSetId]);
    $priceField = civicrm_api3('PriceField', 'getsingle', ['id' => $priceFieldId]);
    $priceFieldValue = civicrm_api3('PriceFieldValue', 'getsingle', ['id' => $priceFieldValueId]);

    $successorPriceSet = \Civi\Api4\PriceSet::create(FALSE)
      ->addValue('name', 'unit_test_priceset_item_successor')
      ->addValue('title', 'Unit Test Item Successor')
      ->addValue('extends', 'Membership')
      ->addValue('is_active', TRUE)
      ->addValue('financial_type_id', $financialTypeId)
      ->execute()
      ->first();
    if (!empty($successorPriceSet['id'])) {
      $this->priceSetIds[] = (int) $successorPriceSet['id'];
    }

    $successorPriceField = \Civi\Api4\PriceField::create(FALSE)
      ->addValue('price_set_id', $successorPriceSet['id'])
      ->addValue('name', 'membership_type')
      ->addValue('label', 'Membership Type')
      ->addValue('html_type', 'CheckBox')
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();
    if (!empty($successorPriceField['id'])) {
      $this->priceFieldIds[] = (int) $successorPriceField['id'];
    }

    $successorPriceFieldValue = \Civi\Api4\PriceFieldValue::create(FALSE)
      ->addValue('price_field_id', $successorPriceField['id'])
      ->addValue('label', 'Unit Test Membership')
      ->addValue('amount', 100)
      ->addValue('membership_type_id', $membershipTypeId)
      ->addValue('financial_type_id', $financialTypeId)
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();
    if (!empty($successorPriceFieldValue['id'])) {
      $this->priceFieldValueIds[] = (int) $successorPriceFieldValue['id'];
    }

    $successorPeriod = $this->createPeriod((int) $successorPriceSet['id']);

    $successorSetting = $this->createSetting(
      (int) $successorPriceFieldValue['id'],
      (int) $successorPeriod['id']
    );

    $currentPeriod = $this->createPeriod($priceSetId, [
      'itemmanager_period_successor_id' => (int) $successorPeriod['id'],
    ]);

    $form = new CRM_Itemmanager_Form_ItemmanagerSetting();

    $selection = $this->invokePrivateMethod(
      $form,
      'getItemSelection',
      [$priceSet, $priceField, $priceFieldValue, $currentPeriod]
    );

    $expectedLabel = '(Unit Test Item Successor) Unit Test Membership';
    $this->assertArrayHasKey(0, $selection);
    $this->assertArrayHasKey((int) $successorSetting['id'], $selection);
    $this->assertSame($expectedLabel, $selection[(int) $successorSetting['id']]);
  }

  public function testPreProcessBuildsItemSettings(): void {
    $periodId = $this->itemmanagerPeriodIds[0] ?? NULL;
    $this->assertNotEmpty($periodId, 'Missing Itemmanager period for preProcess test.');
    $form = new CRM_Itemmanager_Form_ItemmanagerSetting();
    $form->preProcess();

    $itemSettings = $this->getPrivateProperty($form, '_itemSettings');

    $this->assertArrayHasKey((int) $periodId, $itemSettings);
    $this->assertSame((int) $periodId, $itemSettings[(int) $periodId]['periods_id']);
  }

  public function testPostProcessUpdatesPeriodsAndSettings(): void {
    $periodId = $this->itemmanagerPeriodIds[0] ?? NULL;
    $settingId = $this->itemmanagerSettingIds[0] ?? NULL;
    $this->assertNotEmpty($periodId, 'Missing Itemmanager period for postProcess test.');
    $this->assertNotEmpty($settingId, 'Missing Itemmanager setting for postProcess test.');
    $form = new CRM_Itemmanager_Form_ItemmanagerSetting();
    $form->preProcess();

    $itemSettings = $this->getPrivateProperty($form, '_itemSettings');
    $currentPeriod = $itemSettings[(int) $periodId];
    $currentField = reset($currentPeriod['fields']);

    $durationOptions = $this->getPrivateProperty($form, '_duration_options');
    $durationKey = array_key_first($durationOptions);

    $formValues = [
      $currentPeriod['element_period_periods'] => 2,
      $currentPeriod['element_period_type'] => $durationKey,
      $currentPeriod['element_period_start_on'] => '2024-02-01',
      $currentPeriod['element_period_hide'] => 1,
      $currentPeriod['element_period_reverse'] => 1,
      $currentPeriod['element_period_successor'] => 0,
      $currentField['element_period_field_successor'] => 0,
      $currentField['element_period_field_ignore'] => 1,
      $currentField['element_period_field_extend'] => 1,
      $currentField['element_period_field_novitiate'] => 1,
      $currentField['element_period_field_bidding'] => 1,
      $currentField['element_enable_period_exception'] => 1,
      $currentField['element_exception_periods'] => 3,
    ];

    $form->controller = new class($formValues) {
      private array $values;

      public function __construct(array $values) {
        $this->values = $values;
      }

      public function exportValues($name): array {
        return $this->values;
      }
    };

    $form->postProcess();

    $updatedPeriod = \Civi\Api4\ItemmanagerPeriods::get(FALSE)
      ->addWhere('id', '=', (int) $periodId)
      ->execute()
      ->first();

    $this->assertSame(2, (int) $updatedPeriod['periods']);
    $this->assertSame(1, (int) $updatedPeriod['hide']);
    $this->assertSame(1, (int) $updatedPeriod['reverse']);

    $updatedSetting = \Civi\Api4\ItemmanagerSettings::get(FALSE)
      ->addWhere('id', '=', (int) $settingId)
      ->execute()
      ->first();

    $this->assertSame(1, (int) $updatedSetting['ignore']);
    $this->assertSame(1, (int) $updatedSetting['extend']);
    $this->assertSame(1, (int) $updatedSetting['novitiate']);
    $this->assertSame(1, (int) $updatedSetting['bidding']);
    $this->assertSame(1, (int) $updatedSetting['enable_period_exception']);
    $this->assertSame(3, (int) $updatedSetting['exception_periods']);
  }

  public function testBuildQuickFormCreatesExpectedElements(): void {
    $form = new CRM_Itemmanager_Form_ItemmanagerSetting();
    $form->preProcess();
    $form->buildQuickForm();

    $periodId = $this->itemmanagerPeriodIds[0] ?? NULL;
    $this->assertNotEmpty($periodId);

    $itemSettings = $this->getPrivateProperty($form, '_itemSettings');
    $period = $itemSettings[(int) $periodId];

    // Verify core period elements exist.
    $this->assertTrue($form->elementExists($period['element_period_periods']),
      'Periods text element should exist');
    $this->assertTrue($form->elementExists($period['element_period_type']),
      'Duration type select should exist');
    $this->assertTrue($form->elementExists($period['element_period_hide']),
      'Hide checkbox should exist');
    $this->assertTrue($form->elementExists($period['element_period_reverse']),
      'Reverse checkbox should exist');
    $this->assertTrue($form->elementExists($period['element_period_successor']),
      'Successor select should exist');

    // Verify field-level elements exist.
    $field = reset($period['fields']);
    $this->assertTrue($form->elementExists($field['element_period_field_ignore']),
      'Ignore checkbox should exist');
    $this->assertTrue($form->elementExists($field['element_period_field_extend']),
      'Extend checkbox should exist');
    $this->assertTrue($form->elementExists($field['element_period_field_novitiate']),
      'Novitiate checkbox should exist');
    $this->assertTrue($form->elementExists($field['element_period_field_bidding']),
      'Bidding checkbox should exist');
    $this->assertTrue($form->elementExists($field['element_enable_period_exception']),
      'Period exception checkbox should exist');
  }

  public function testGetRenderableElementNamesFiltersUnlabeledElements(): void {
    $form = new CRM_Itemmanager_Form_ItemmanagerSetting();

    $form->add('text', 'renderable', 'Renderable');
    $form->add('text', 'hidden', '');

    $names = $form->getRenderableElementNames();

    $this->assertSame(['renderable'], $names);
  }

  private function createPeriod(int $priceSetId, array $overrides = []): array {
    $values = array_merge([
      'price_set_id' => $priceSetId,
      'period_start_on' => '20240101',
      'periods' => 1,
      'period_type' => 1,
      'hide' => FALSE,
      'reverse' => FALSE,
      'itemmanager_period_successor_id' => 0,
    ], $overrides);

    $period = \Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->setCheckPermissions(FALSE)
      ->addValue('price_set_id', $values['price_set_id'])
      ->addValue('period_start_on', $values['period_start_on'])
      ->addValue('periods', $values['periods'])
      ->addValue('period_type', $values['period_type'])
      ->addValue('hide', $values['hide'])
      ->addValue('reverse', $values['reverse'])
      ->addValue('itemmanager_period_successor_id', $values['itemmanager_period_successor_id'])
      ->execute()
      ->first();
    if (!empty($period['id'])) {
      $this->itemmanagerPeriodIds[] = (int) $period['id'];
    }
    return $period;
  }

  private function createSetting(int $priceFieldValueId, int $periodId): array {
    $setting = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->setCheckPermissions(FALSE)
      ->addValue('price_field_value_id', $priceFieldValueId)
      ->addValue('itemmanager_periods_id', $periodId)
      ->addValue('ignore', FALSE)
      ->addValue('extend', FALSE)
      ->addValue('novitiate', FALSE)
      ->addValue('bidding', FALSE)
      ->addValue('enable_period_exception', FALSE)
      ->addValue('exception_periods', 0)
      ->execute()
      ->first();
    if (!empty($setting['id'])) {
      $this->itemmanagerSettingIds[] = (int) $setting['id'];
    }
    return $setting;
  }

  private function getPrivateProperty(object $object, string $propertyName) {
    $property = new \ReflectionProperty($object, $propertyName);
    $property->setAccessible(true);
    return $property->getValue($object);
  }

  private function invokePrivateMethod(object $object, string $method, array $args = []) {
    $methodRef = new \ReflectionMethod($object, $method);
    $methodRef->setAccessible(true);
    return $methodRef->invokeArgs($object, $args);
  }

  private function filterIds(array $ids): array {
    $ids = array_values(array_unique($ids));
    return array_values(array_filter($ids, static function ($id) {
      return !empty($id) && (int) $id > 0;
    }));
  }
}
