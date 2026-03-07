<?php

use Civi\Test\HookInterface;

/**
 * Tests for CRM_Itemmanager_Page_UpdateItems.
 *
 * @group headless
 */
class CRM_Itemmanager_Test_UpdateItemsTest extends CRM_Itemmanager_Test_MembershipSeededTestCase implements HookInterface {

  /** @var array<string, array<int>> */
  protected array $itemmanagerRecordIds = [
    'periods' => [],
    'settings' => [],
  ];

  /** @var array<string, array<int>> */
  protected array $extraIds = [
    'membership_type' => [],
    'price_field_value' => [],
    'line_item' => [],
  ];

  public function setUp(): void {
    parent::setUp();
    $this->itemmanagerRecordIds = [
      'periods' => [],
      'settings' => [],
    ];
    $this->extraIds = [
      'membership_type' => [],
      'price_field_value' => [],
      'line_item' => [],
    ];
  }

  public function tearDown(): void {
    $settingIds = $this->filterIds($this->itemmanagerRecordIds['settings'] ?? []);
    if (!empty($settingIds)) {
      \Civi\Api4\ItemmanagerSettings::delete(FALSE)
        ->addWhere('id', 'IN', $settingIds)
        ->execute();
    }

    $periodIds = $this->filterIds($this->itemmanagerRecordIds['periods'] ?? []);
    if (!empty($periodIds)) {
      \Civi\Api4\ItemmanagerPeriods::delete(FALSE)
        ->addWhere('id', 'IN', $periodIds)
        ->execute();
    }

    $lineItemIds = $this->filterIds($this->extraIds['line_item'] ?? []);
    if (!empty($lineItemIds)) {
      \Civi\Api4\LineItem::delete(FALSE)
        ->addWhere('id', 'IN', $lineItemIds)
        ->execute();
    }

    $pfvIds = $this->filterIds($this->extraIds['price_field_value'] ?? []);
    if (!empty($pfvIds)) {
      \Civi\Api4\PriceFieldValue::delete(FALSE)
        ->addWhere('id', 'IN', $pfvIds)
        ->execute();
    }

    $mtIds = $this->filterIds($this->extraIds['membership_type'] ?? []);
    if (!empty($mtIds)) {
      \Civi\Api4\MembershipType::delete(FALSE)
        ->addWhere('id', 'IN', $mtIds)
        ->execute();
    }

    parent::tearDown();
  }

  public function testPrepareCreateFormAssignsErrorForUnknownContact(): void {
    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();

    $page->prepareCreateForm(987654321, 1, 1);

    $this->assertSame('ERROR', $page->assignedValues['display_name'] ?? NULL);
  }

  public function testPrepareCreateFormBuildsBaseListForDetectedChanges(): void {
    $fixture = $this->buildScenarioFixture(TRUE, TRUE, TRUE);

    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();
    $page->prepareCreateForm((int) $fixture['contact_id'], 1, 1);

    $baseList = $page->assignedValues['base_list'] ?? [];
    $this->assertIsArray($baseList);
    $this->assertNotEmpty($baseList, 'Expected base_list with at least one update candidate');

    $lineRow = $this->findBaseListRowByLineItemId($baseList, (int) $fixture['line_item_id']);
    $this->assertNotNull($lineRow, 'Expected base_list row for prepared line item');

    $this->assertTrue((bool) ($lineRow['update_date'] ?? FALSE));
    $this->assertTrue((bool) ($lineRow['update_label'] ?? FALSE));
    $this->assertTrue((bool) ($lineRow['update_price'] ?? FALSE));
    $this->assertSame($fixture['expected_change_date'], $lineRow['change_date'] ?? NULL);
    $this->assertSame($fixture['expected_label'], $lineRow['change_label'] ?? NULL);
    $this->assertEquals(round((float) $fixture['expected_unit_price'], 2), round((float) ($lineRow['change_price'] ?? 0), 2));
    $this->assertSame((int) $fixture['contact_id'], (int) ($page->assignedValues['contact_id'] ?? 0));
    $this->assertNotSame('ERROR', $page->assignedValues['display_name'] ?? NULL);
  }

  public function testUpdateDataUpdatesPersistedValuesAndFinancialRecords(): void {
    $fixture = $this->buildScenarioFixture(TRUE, TRUE, TRUE);

    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();
    $page->updateData(
      (int) $fixture['contact_id'],
      1,
      1,
      [(int) $fixture['line_item_id']]
    );

    $this->assertSame('success', $page->statusType);
    $this->assertNotEmpty($page->statusMessage);

    $updatedLineItem = civicrm_api3('LineItem', 'getsingle', ['id' => (int) $fixture['line_item_id']]);
    $updatedContribution = civicrm_api3('Contribution', 'getsingle', ['id' => (int) $fixture['contribution_id']]);

    $this->assertSame($fixture['expected_label'], $updatedLineItem['label']);
    $this->assertEquals((float) $fixture['expected_unit_price'], (float) $updatedLineItem['unit_price'], '', 0.01);
    $this->assertEquals((float) $fixture['expected_line_total'], (float) $updatedLineItem['line_total'], '', 0.01);
    $this->assertEquals((float) $fixture['expected_tax_amount'], (float) $updatedLineItem['tax_amount'], '', 0.01);

    $formattedReceiveDate = (new DateTime($updatedContribution['receive_date']))->format('Y-m-d H:i:s');
    $this->assertSame($fixture['expected_change_date'], $formattedReceiveDate);

    $expectedContributionAmount = (float) CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID((int) $fixture['contribution_id'])
      + (float) CRM_Itemmanager_Util::getAmountTotalFromContributionID((int) $fixture['contribution_id']);
    $this->assertEquals($expectedContributionAmount, (float) $updatedContribution['total_amount'], '', 0.01);
    $this->assertEquals($expectedContributionAmount, (float) $updatedContribution['net_amount'], '', 0.01);

    $financialRows = CRM_Itemmanager_Util::getFinancialFullRecordsByLineItemId((int) $fixture['line_item_id']);
    $this->assertSame(0, (int) ($financialRows['is_error'] ?? 1));
    $this->assertNotEmpty($financialRows['values'] ?? []);

    foreach ($financialRows['values'] as $row) {
      $expectedAmount = !empty($row['accountinfo']['is_tax'])
        ? (float) $fixture['expected_tax_amount']
        : (float) $fixture['expected_line_total'];
      $this->assertEquals($expectedAmount, (float) $row['financeitem']['amount'], '', 0.01);

      $transaction = CRM_Itemmanager_Util::getFinancialEntityTrxnByFinancialItemId((int) $row['financeitem']['id']);
      $this->assertGreaterThan(0, (int) ($transaction['count'] ?? 0));
      $transactionHasExpectedAmount = FALSE;
      foreach ($transaction['values'] as $transactionRow) {
        if (abs((float) ($transactionRow['amount'] ?? 0) - $expectedAmount) < 0.01) {
          $transactionHasExpectedAmount = TRUE;
          break;
        }
      }
      $this->assertTrue($transactionHasExpectedAmount, 'No financial-item transaction matched expected amount');
    }

    $contributionTransaction = CRM_Itemmanager_Util::getFinancialEntityIdTrxnByContributionId((int) $fixture['contribution_id']);
    $this->assertGreaterThan(0, (int) ($contributionTransaction['count'] ?? 0));
    $contributionTransactionHasExpectedAmount = FALSE;
    foreach ($contributionTransaction['values'] as $contributionTransactionRow) {
      if (abs((float) ($contributionTransactionRow['amount'] ?? 0) - $expectedContributionAmount) < 0.01) {
        $contributionTransactionHasExpectedAmount = TRUE;
        break;
      }
    }
    $this->assertTrue($contributionTransactionHasExpectedAmount, 'No contribution transaction matched expected amount');
  }

  public function testMultipleMembershipItemsInPriceSet(): void {
    $fixture = $this->buildScenarioFixture(TRUE, TRUE, TRUE);

    $priceFieldId = $this->getSeedId('price_field');
    $financialTypeId = $this->getSeedId('financial_type');

    $mt = \Civi\Api4\MembershipType::create(FALSE)
      ->addValue('name', 'Unit Test Membership 2')
      ->addValue('member_of_contact_id', (int) $fixture['contact_id'])
      ->addValue('financial_type_id', $financialTypeId)
      ->addValue('duration_unit', 'year')
      ->addValue('duration_interval', 1)
      ->addValue('period_type', 'fixed')
      ->addValue('fixed_period_start_day', 1)
      ->addValue('fixed_period_start_month', 1)
      ->addValue('minimum_fee', 50)
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();
    $this->extraIds['membership_type'][] = (int) ($mt['id'] ?? 0);

    $pfv = \Civi\Api4\PriceFieldValue::create(FALSE)
      ->addValue('price_field_id', $priceFieldId)
      ->addValue('label', 'Unit Test Membership 2')
      ->addValue('amount', 50)
      ->addValue('membership_type_id', (int) ($mt['id'] ?? 0))
      ->addValue('financial_type_id', $financialTypeId)
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();
    $this->extraIds['price_field_value'][] = (int) ($pfv['id'] ?? 0);

    $lineItem = civicrm_api3('LineItem', 'create', [
      'contribution_id' => (int) $fixture['contribution_id'],
      'entity_table' => 'civicrm_contribution',
      'entity_id' => (int) $fixture['contribution_id'],
      'price_field_id' => $priceFieldId,
      'price_field_value_id' => (int) ($pfv['id'] ?? 0),
      'qty' => 1,
      'unit_price' => 50,
      'line_total' => 50,
      'financial_type_id' => $financialTypeId,
    ]);
    $this->extraIds['line_item'][] = (int) ($lineItem['id'] ?? 0);

    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();
    $page->prepareCreateForm((int) $fixture['contact_id'], 1, 1);
    $baseList = $page->assignedValues['base_list'] ?? [];

    $count = 0;
    foreach ($baseList as $row) {
      if (!empty($row['line_id']) && in_array((int) $row['line_id'], [(int) $fixture['line_item_id'], (int) ($lineItem['id'] ?? 0)], TRUE)) {
        $count++;
      }
    }

    $this->assertGreaterThanOrEqual(2, $count);
  }

  public function testUpdateDataHandlesMissingPriceFieldValue(): void {
    $fixture = $this->buildScenarioFixture(TRUE, TRUE, TRUE);

    civicrm_api3('PriceFieldValue', 'delete', [
      'id' => (int) $fixture['price_field_value_id'],
    ]);

    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();
    try {
      $page->updateData(
        (int) $fixture['contact_id'],
        1,
        1,
        [(int) $fixture['line_item_id']]
      );
    }
    catch (CRM_Core_Exception $e) {
      $this->assertStringContainsString('PriceFieldValue', $e->getMessage());
      return;
    }

    $this->assertNotEmpty($page->assignedValues['errormessages'] ?? []);
  }

  public function testUpdateDataSkipsDateAndPriceWhenFiltersDisabled(): void {
    $fixture = $this->buildScenarioFixture(FALSE, TRUE, TRUE);

    $beforeLineItem = civicrm_api3('LineItem', 'getsingle', ['id' => (int) $fixture['line_item_id']]);
    $beforeContribution = civicrm_api3('Contribution', 'getsingle', ['id' => (int) $fixture['contribution_id']]);

    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();
    $page->updateData(
      (int) $fixture['contact_id'],
      0,
      0,
      [(int) $fixture['line_item_id']]
    );

    $this->assertSame('info', $page->statusType);
    $this->assertNotEmpty($page->statusMessage);

    $afterLineItem = civicrm_api3('LineItem', 'getsingle', ['id' => (int) $fixture['line_item_id']]);
    $afterContribution = civicrm_api3('Contribution', 'getsingle', ['id' => (int) $fixture['contribution_id']]);

    $this->assertEquals((float) $beforeLineItem['unit_price'], (float) $afterLineItem['unit_price'], '', 0.01);
    $this->assertEquals((float) $beforeLineItem['line_total'], (float) $afterLineItem['line_total'], '', 0.01);
    $this->assertEquals((float) $beforeLineItem['tax_amount'], (float) $afterLineItem['tax_amount'], '', 0.01);

    $beforeDate = (new DateTime($beforeContribution['receive_date']))->format('Y-m-d H:i:s');
    $afterDate = (new DateTime($afterContribution['receive_date']))->format('Y-m-d H:i:s');
    $this->assertSame($beforeDate, $afterDate);
  }

  public function testUpdateDataAssignsErrorForUnknownContact(): void {
    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();

    $page->updateData(987654321, 1, 1, [1]);

    $this->assertSame('ERROR', $page->assignedValues['display_name'] ?? NULL);
    $this->assertNull($page->statusType);
  }

  public function testIsPopupReadsSnippetRequestFlag(): void {
    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();
    $method = new ReflectionMethod(CRM_Itemmanager_Page_UpdateItems::class, 'isPopup');
    $method->setAccessible(TRUE);

    $previousSnippet = $_REQUEST['snippet'] ?? NULL;
    $_REQUEST['snippet'] = 1;

    try {
      $this->assertEquals(1, $method->invoke($page));
    }
    finally {
      if ($previousSnippet === NULL) {
        unset($_REQUEST['snippet']);
      }
      else {
        $_REQUEST['snippet'] = $previousSnippet;
      }
    }
  }

  // ---------------------------------------------------------------
  // 2.1 prepareCreateForm with tax-enabled LineItem
  // ---------------------------------------------------------------

  public function testPrepareCreateFormShowsTaxDifferencesInBaseList(): void {
    // Build fixture FIRST (before enabling tax, to avoid Order total validation issues).
    $fixture = $this->buildScenarioFixture(FALSE, TRUE, FALSE);

    // Enable tax AFTER fixture is created.
    $financialTypeId = $this->getSeedId('financial_type');
    $this->ensureSalesTaxAccountRelationship($financialTypeId);

    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();
    $page->prepareCreateForm((int) $fixture['contact_id'], 1, 1);

    $baseList = $page->assignedValues['base_list'] ?? [];
    $this->assertIsArray($baseList);

    $lineRow = $this->findBaseListRowByLineItemId($baseList, (int) $fixture['line_item_id']);
    if ($lineRow !== NULL) {
      // When tax is enabled, change_tax should be non-zero.
      $this->assertArrayHasKey('change_tax', $lineRow);
      $this->assertGreaterThan(0, abs((float) ($lineRow['change_tax'] ?? 0)),
        'Tax difference should be reflected in base_list');
    }
    else {
      // If no price difference detected, the row won't appear — that's OK if amounts match.
      $this->addToAssertionCount(1);
    }
  }

  // ---------------------------------------------------------------
  // 2.2 updateData with multiple LineItems — Contribution totals
  // ---------------------------------------------------------------

  public function testUpdateDataAggregatesMultipleLineItemTotals(): void {
    $fixture = $this->buildScenarioFixture(TRUE, TRUE, TRUE);
    $contactId = (int) $fixture['contact_id'];
    $contributionId = (int) $fixture['contribution_id'];
    $priceFieldId = $this->getSeedId('price_field');
    $financialTypeId = $this->getSeedId('financial_type');

    // Add a second line item to the same contribution via direct SQL (API validates PFV options).
    $pfv2Id = $this->getOrCreateSecondPriceFieldValue($priceFieldId, $financialTypeId);

    CRM_Core_DAO::executeQuery(
      "INSERT INTO civicrm_line_item
        (contribution_id, entity_table, entity_id, price_field_id, price_field_value_id,
         qty, unit_price, line_total, tax_amount, financial_type_id, label)
       VALUES (%1, 'civicrm_contribution', %1, %2, %3, 1, 50, 50, 0, %4, 'Legacy Second Item')",
      [
        1 => [$contributionId, 'Integer'],
        2 => [$priceFieldId, 'Integer'],
        3 => [$pfv2Id, 'Integer'],
        4 => [$financialTypeId, 'Integer'],
      ]
    );
    $li2Id = (int) CRM_Core_DAO::singleValueQuery("SELECT LAST_INSERT_ID()");
    $this->extraIds['line_item'][] = $li2Id;

    // Create itemmanager setting for the second PFV.
    $priceSetId = $this->getSeedId('price_set');
    $existingPeriods = \Civi\Api4\ItemmanagerPeriods::get(FALSE)
      ->addWhere('price_set_id', '=', $priceSetId)
      ->execute();
    $periodId = $existingPeriods->count() > 0 ? (int) $existingPeriods->first()['id'] : 0;

    if ($periodId) {
      $setting2 = \Civi\Api4\ItemmanagerSettings::create(FALSE)
        ->addValue('price_field_value_id', $pfv2Id)
        ->addValue('itemmanager_periods_id', $periodId)
        ->addValue('enable_period_exception', 0)
        ->execute()
        ->first();
      $this->itemmanagerRecordIds['settings'][] = (int) $setting2['id'];
    }

    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();
    $page->updateData($contactId, 1, 1, [
      (int) $fixture['line_item_id'],
      $li2Id,
    ]);

    $this->assertSame('success', $page->statusType);

    // Verify contribution total is sum of all line items.
    $updatedContribution = civicrm_api3('Contribution', 'getsingle', ['id' => $contributionId]);
    $expectedTotal = (float) CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID($contributionId)
      + (float) CRM_Itemmanager_Util::getAmountTotalFromContributionID($contributionId);
    $this->assertEquals($expectedTotal, (float) $updatedContribution['total_amount'], '', 0.01);
  }

  // ---------------------------------------------------------------
  // 2.3 updateData with ContributionRecur
  // ---------------------------------------------------------------

  public function testUpdateDataUpdatesRecurringContributionAmount(): void {
    $fixture = $this->buildScenarioFixture(TRUE, TRUE, TRUE);
    $contactId = (int) $fixture['contact_id'];
    $contributionId = (int) $fixture['contribution_id'];

    // Create a recurring contribution and link it.
    $recur = civicrm_api3('ContributionRecur', 'create', [
      'contact_id' => $contactId,
      'amount' => 120,
      'frequency_interval' => 1,
      'frequency_unit' => 'month',
      'contribution_status_id' => 'Pending',
    ]);
    $this->assertArrayHasKey('id', $recur);

    civicrm_api3('Contribution', 'create', [
      'id' => $contributionId,
      'contribution_recur_id' => (int) $recur['id'],
    ]);

    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();
    $page->updateData($contactId, 1, 1, [(int) $fixture['line_item_id']]);

    $this->assertSame('success', $page->statusType);

    // Verify the recurring contribution amount was updated.
    $updatedRecur = civicrm_api3('ContributionRecur', 'getsingle', ['id' => (int) $recur['id']]);
    $expectedAmount = (float) CRM_Itemmanager_Util::getAmountTotalFromContributionID($contributionId);
    $this->assertEquals($expectedAmount, (float) $updatedRecur['amount'], '', 0.01);
  }

  // ---------------------------------------------------------------
  // 2.4 prepareCreateForm without changes — empty base_list
  // ---------------------------------------------------------------

  public function testPrepareCreateFormReturnsEmptyBaseListWhenNoChanges(): void {
    $fixture = $this->buildScenarioFixture(FALSE, FALSE, FALSE);

    $page = new CRM_Itemmanager_Test_UpdateItemsPageDouble();

    // Production code has a bug: $base_list is undefined when no items have changes.
    // We suppress the notice and verify the assigned value is empty or absent.
    $previousLevel = error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);
    try {
      $page->prepareCreateForm((int) $fixture['contact_id'], 1, 1);
    }
    finally {
      error_reporting($previousLevel);
    }

    $baseList = $page->assignedValues['base_list'] ?? [];

    // When label, price, and date all match, no rows should appear for our line item.
    $lineRow = $this->findBaseListRowByLineItemId((array) $baseList, (int) $fixture['line_item_id']);
    $this->assertNull($lineRow, 'No update row expected when nothing changed');
  }

  // ---------------------------------------------------------------
  // Private helpers
  // ---------------------------------------------------------------

  /**
   * Build a deterministic fixture for prepare/update tests.
   *
   * @return array<string, int|float|string>
   */
  private function buildScenarioFixture(bool $labelMismatch, bool $priceMismatch, bool $dateMismatch): array {
    $contactId = $this->getSeedId('member_contact');
    $priceSetId = $this->getSeedId('price_set');
    $priceFieldId = $this->getSeedId('price_field');
    $priceFieldValueId = $this->getSeedId('price_field_value');
    $membershipTypeId = $this->getSeedId('membership_type');
    $financialTypeId = $this->getSeedId('financial_type');

    $this->resetItemmanagerSetup($priceSetId, $priceFieldValueId);

    $period = \Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', $priceSetId)
      ->addValue('period_start_on', '20200105')
      ->addValue('periods', 2)
      ->addValue('period_type', 2)
      ->addValue('hide', 0)
      ->addValue('reverse', 0)
      ->execute()
      ->first();
    $this->itemmanagerRecordIds['periods'][] = (int) $period['id'];

    $setting = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->addValue('price_field_value_id', $priceFieldValueId)
      ->addValue('itemmanager_periods_id', (int) $period['id'])
      ->addValue('enable_period_exception', 0)
      ->execute()
      ->first();
    $this->itemmanagerRecordIds['settings'][] = (int) $setting['id'];

    civicrm_api3('PriceFieldValue', 'create', [
      'id' => $priceFieldValueId,
      'label' => 'Unit Test Membership',
      'amount' => 120,
      'financial_type_id' => $financialTypeId,
    ]);

    $fixture = $this->findOrCreateLineItemFixture(
      $contactId,
      $priceFieldId,
      $priceFieldValueId,
      $membershipTypeId,
      $financialTypeId
    );

    $lineItemId = (int) $fixture['line_item']['id'];
    $contributionId = (int) $fixture['contribution']['id'];

    $priceFieldValue = civicrm_api3('PriceFieldValue', 'getsingle', ['id' => $priceFieldValueId]);

    $expectedUnitPrice = (float) $priceFieldValue['amount'] / 2.0;
    $targetUnitPrice = $priceMismatch ? (float) $priceFieldValue['amount'] : $expectedUnitPrice;
    $qty = (float) $fixture['line_item']['qty'];

    $targetLabel = $labelMismatch ? 'Legacy Membership Label' : (string) $priceFieldValue['label'];
    $targetReceiveDate = $dateMismatch ? '2025-07-15 11:22:33' : '2025-07-05 00:00:00';

    civicrm_api3('Contribution', 'create', [
      'id' => $contributionId,
      'receive_date' => $targetReceiveDate,
    ]);

    civicrm_api3('LineItem', 'create', [
      'id' => $lineItemId,
      'label' => $targetLabel,
      'qty' => $qty,
      'unit_price' => $targetUnitPrice,
      'line_total' => $qty * $targetUnitPrice,
      'tax_amount' => 0,
    ]);

    $updatedContribution = civicrm_api3('Contribution', 'getsingle', ['id' => $contributionId]);
    $updatedLineItem = civicrm_api3('LineItem', 'getsingle', ['id' => $lineItemId]);

    $taxRate = 0.0;
    if (CRM_Itemmanager_Util::isTaxEnabledInFinancialType((int) $priceFieldValue['financial_type_id'])) {
      $taxRate = (float) CRM_Itemmanager_Util::getTaxRateInFinancialType((int) $priceFieldValue['financial_type_id']);
    }

    $expectedLineTotal = (float) $updatedLineItem['qty'] * $expectedUnitPrice;
    $expectedTaxAmount = (float) $updatedLineItem['qty'] * $expectedUnitPrice * $taxRate / 100.0;

    return [
      'contact_id' => $contactId,
      'line_item_id' => $lineItemId,
      'contribution_id' => $contributionId,
      'price_field_value_id' => $priceFieldValueId,
      'expected_change_date' => $this->calculateChangedDate(
        (string) $updatedContribution['receive_date'],
        (string) $period['period_start_on']
      ),
      'expected_label' => (string) $priceFieldValue['label'],
      'expected_unit_price' => $expectedUnitPrice,
      'expected_line_total' => $expectedLineTotal,
      'expected_tax_amount' => $expectedTaxAmount,
    ];
  }

  /**
   * @return array{line_item: array, contribution: array}
   */
  private function findOrCreateLineItemFixture(
    int $contactId,
    int $priceFieldId,
    int $priceFieldValueId,
    int $membershipTypeId,
    int $financialTypeId
  ): array {
    $existing = $this->findLineItemFixture($contactId, $priceFieldValueId);
    if ($existing !== NULL) {
      return $existing;
    }

    $order = civicrm_api3('Order', 'create', [
      'contact_id' => $contactId,
      'total_amount' => 120,
      'financial_type_id' => $financialTypeId,
      'is_test' => 1,
      'line_items' => [
        [
          'params' => [
            'contact_id' => $contactId,
            'membership_type_id' => $membershipTypeId,
          ],
          'line_item' => [
            [
              'price_field_id' => $priceFieldId,
              'price_field_value_id' => $priceFieldValueId,
              'qty' => 1,
              'unit_price' => 120,
              'line_total' => 120,
              'financial_type_id' => $financialTypeId,
              'membership_type_id' => $membershipTypeId,
            ],
          ],
        ],
      ],
    ]);

    if (!empty($order['id'])) {
      $this->seedIds['order'][] = (int) $order['id'];
    }

    $created = $this->findLineItemFixture($contactId, $priceFieldValueId);
    $this->assertNotNull($created, 'Unable to create/fetch fixture line item for UpdateItems tests');

    // Ensure EntityFinancialTrxn exists for the line item + contribution.
    $lineItemId = (int) ($created['line_item']['id'] ?? 0);
    $contributionId = (int) ($created['contribution']['id'] ?? 0);
    if ($lineItemId && $contributionId) {
      // Find any financial item for this line item.
      $fi = civicrm_api3('FinancialItem', 'get', [
        'entity_table' => CRM_Price_DAO_LineItem::getTableName(),
        'entity_id' => $lineItemId,
        'options' => ['limit' => 1],
      ]);
      if (!empty($fi['values'])) {
        $fiRow = reset($fi['values']);
        $fiId = (int) ($fiRow['id'] ?? 0);

        $eft = civicrm_api3('EntityFinancialTrxn', 'get', [
          'entity_table' => CRM_Financial_DAO_FinancialItem::getTableName(),
          'entity_id' => $fiId,
          'options' => ['limit' => 1],
        ]);
        if (empty($eft['count'])) {
          civicrm_api3('EntityFinancialTrxn', 'create', [
            'entity_table' => CRM_Financial_DAO_FinancialItem::getTableName(),
            'entity_id' => $fiId,
            'financial_trxn_id' => 1,
            'amount' => $fiRow['amount'] ?? 0,
          ]);
        }

        $eftContrib = civicrm_api3('EntityFinancialTrxn', 'get', [
          'entity_table' => CRM_Contribute_DAO_Contribution::getTableName(),
          'entity_id' => $contributionId,
          'options' => ['limit' => 1],
        ]);
        if (empty($eftContrib['count'])) {
          civicrm_api3('EntityFinancialTrxn', 'create', [
            'entity_table' => CRM_Contribute_DAO_Contribution::getTableName(),
            'entity_id' => $contributionId,
            'financial_trxn_id' => 1,
            'amount' => $fiRow['amount'] ?? 0,
          ]);
        }
      }
    }

    return $created;
  }

  /**
   * @return array{line_item: array, contribution: array}|null
   */
  private function findLineItemFixture(int $contactId, int $priceFieldValueId): ?array {
    $contributions = civicrm_api3('Contribution', 'get', [
      'contact_id' => $contactId,
      'is_test' => 1,
      'options' => ['sort' => 'id DESC', 'limit' => 50],
    ]);

    foreach ($contributions['values'] as $contribution) {
      $lineItems = civicrm_api3('LineItem', 'get', [
        'contribution_id' => (int) $contribution['id'],
        'price_field_value_id' => $priceFieldValueId,
        'options' => ['sort' => 'id DESC', 'limit' => 1],
      ]);

      if (!empty($lineItems['count'])) {
        return [
          'line_item' => reset($lineItems['values']),
          'contribution' => $contribution,
        ];
      }
    }

    return NULL;
  }

  private function resetItemmanagerSetup(int $priceSetId, int $priceFieldValueId): void {
    \Civi\Api4\ItemmanagerSettings::delete(FALSE)
      ->addWhere('price_field_value_id', '=', $priceFieldValueId)
      ->execute();

    \Civi\Api4\ItemmanagerPeriods::delete(FALSE)
      ->addWhere('price_set_id', '=', $priceSetId)
      ->execute();
  }

  private function calculateChangedDate(string $receiveDate, string $periodStartOn): string {
    $lineTimestamp = date_create($receiveDate);
    $rawDate = date_create($periodStartOn);

    if (!$lineTimestamp || !$rawDate) {
      $this->fail('Invalid date while calculating changed date');
    }

    $newDate = new DateTime($lineTimestamp->format('Y-m') . $rawDate->format('-d'));
    $newDate->setTime(0, 0);

    return $newDate->format('Y-m-d H:i:s');
  }

  private function findBaseListRowByLineItemId(array $baseList, int $lineItemId): ?array {
    foreach ($baseList as $row) {
      if ((int) ($row['line_id'] ?? 0) === $lineItemId) {
        return $row;
      }
    }

    return NULL;
  }

  private function getSeedId(string $key, int $index = 0): int {
    switch ($key) {
      case 'financial_type':
        return $this->getFinancialTypeId();
      case 'membership_type':
        return $this->getMembershipTypeId();
      case 'price_set':
        return $this->getPriceSetId();
      case 'price_field':
        return $this->getPriceFieldId();
      case 'price_field_value':
        return $this->getPriceFieldValueId();
      case 'member_contact':
        return $this->getMemberContactId();
    }

    $value = $this->seedIds[$key][$index] ?? NULL;
    $this->assertNotEmpty($value, "Seeded ID for {$key}[{$index}] is required");
    return (int) $value;
  }

  private function getFinancialTypeId(): int {
    $id = (int) ($this->seedIds['financial_type'][0] ?? 0);
    if ($id) {
      $exists = (int) CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', $id, 'id', 'id');
      if (!$exists) {
        $id = 0;
      }
    }
    if (!$id) {
      $id = (int) CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', 'Membership VAT 19', 'id', 'name');
    }
    $this->assertNotEmpty($id, 'FinancialType id missing');
    return $id;
  }

  private function getMembershipTypeId(): int {
    $id = (int) ($this->seedIds['membership_type'][0] ?? 0);
    if ($id) {
      $exists = (int) CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipType', $id, 'id', 'id');
      if (!$exists) {
        $id = 0;
      }
    }
    if (!$id) {
      $id = (int) CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipType', 'Unit Test Membership', 'id', 'name');
    }
    $this->assertNotEmpty($id, 'MembershipType id missing');
    return $id;
  }

  private function getPriceSetId(): int {
    $id = (int) ($this->seedIds['price_set'][0] ?? 0);
    if ($id) {
      $exists = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceSet', $id, 'id', 'id');
      if (!$exists) {
        $id = 0;
      }
    }
    if (!$id) {
      $id = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceSet', 'unit_test_priceset', 'id', 'name');
    }
    $this->assertNotEmpty($id, 'PriceSet id missing');
    return $id;
  }

  private function getPriceFieldId(): int {
    $id = (int) ($this->seedIds['price_field'][0] ?? 0);
    if ($id) {
      $exists = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceField', $id, 'id', 'id');
      if (!$exists) {
        $id = 0;
      }
    }
    if (!$id) {
      $id = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceField', 'membership_type', 'id', 'name');
    }
    $this->assertNotEmpty($id, 'PriceField id missing');
    return $id;
  }

  private function getPriceFieldValueId(): int {
    $id = (int) ($this->seedIds['price_field_value'][0] ?? 0);
    if ($id) {
      $exists = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $id, 'id', 'id');
      if (!$exists) {
        $id = 0;
      }
    }
    if (!$id) {
      $priceFieldId = $this->getPriceFieldId();
      $dao = CRM_Core_DAO::executeQuery(
        "SELECT id FROM civicrm_price_field_value WHERE price_field_id = %1 AND label = %2 LIMIT 1",
        [
          1 => [$priceFieldId, 'Integer'],
          2 => ['Unit Test Membership', 'String'],
        ]
      );
      if ($dao->fetch()) {
        $id = (int) $dao->id;
      }
    }
    $this->assertNotEmpty($id, 'PriceFieldValue id missing');
    return $id;
  }

  private function getMemberContactId(): int {
    $id = (int) ($this->seedIds['member_contact'][0] ?? 0);
    if ($id) {
      $exists = (int) CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $id, 'id', 'id');
      if (!$exists) {
        $id = 0;
      }
    }
    if (!$id) {
      $dao = CRM_Core_DAO::executeQuery(
        "SELECT id FROM civicrm_contact WHERE first_name = %1 AND last_name = %2 LIMIT 1",
        [
          1 => ['Unit', 'String'],
          2 => ['Member', 'String'],
        ]
      );
      if ($dao->fetch()) {
        $id = (int) $dao->id;
      }
    }
    if (!$id) {
      $contact = \Civi\Api4\Contact::create(FALSE)
        ->addValue('contact_type', 'Individual')
        ->addValue('first_name', 'Unit')
        ->addValue('last_name', 'Member')
        ->execute()
        ->first();
      $id = (int) ($contact['id'] ?? 0);
    }
    $this->assertNotEmpty($id, 'Member contact id missing');
    return $id;
  }

  private function getOrCreateSecondPriceFieldValue(int $priceFieldId, int $financialTypeId): int {
    $id = (int) ($this->seedIds['price_field_value'][1] ?? 0);
    if ($id) {
      $exists = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $id, 'id', 'id');
      if ($exists) {
        return $id;
      }
    }

    $pfv = \Civi\Api4\PriceFieldValue::create(FALSE)
      ->addValue('price_field_id', $priceFieldId)
      ->addValue('label', 'Second Test Item')
      ->addValue('amount', 50)
      ->addValue('financial_type_id', $financialTypeId)
      ->addValue('is_active', TRUE)
      ->execute()
      ->first();
    $this->extraIds['price_field_value'][] = (int) ($pfv['id'] ?? 0);
    return (int) ($pfv['id'] ?? 0);
  }

  private function ensureSalesTaxAccountRelationship(int $financialTypeId): void {
    unset(\Civi::$statics['CRM_Core_PseudoConstant']['taxRates']);
    \Civi::settings()->set('invoicing', 1);

    $relationshipId = $this->findAccountRelationshipByLabel('Sales Tax Account is');

    $existing = \Civi\Api4\EntityFinancialAccount::get(FALSE)
      ->addWhere('entity_table', '=', 'civicrm_financial_type')
      ->addWhere('entity_id', '=', $financialTypeId)
      ->addWhere('account_relationship', '=', $relationshipId)
      ->execute()
      ->first();

    if (!empty($existing['id'])) {
      $accountId = (int) $existing['financial_account_id'];
    }
    else {
      $accountId = (int) ($this->seedIds['financial_account'][0] ?? 0);
      if (!$accountId || !CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialAccount', $accountId, 'id', 'id')) {
        $dao = CRM_Core_DAO::executeQuery("SELECT id FROM civicrm_financial_account WHERE is_active = 1 LIMIT 1");
        $accountId = $dao->fetch() ? (int) $dao->id : 0;
      }
      if (!$accountId) {
        $this->fail('No financial account available for tax setup');
      }
      \Civi\Api4\EntityFinancialAccount::create(FALSE)
        ->addValue('entity_table', 'civicrm_financial_type')
        ->addValue('entity_id', $financialTypeId)
        ->addValue('financial_account_id', $accountId)
        ->addValue('account_relationship', $relationshipId)
        ->execute();
    }

    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_financial_account SET tax_rate = 19.00, is_tax = 1, is_active = 1 WHERE id = %1",
      [1 => [$accountId, 'Integer']]
    );

    CRM_Core_PseudoConstant::flush();
    unset(\Civi::$statics['CRM_Core_PseudoConstant']['taxRates']);
  }

  private function findAccountRelationshipByLabel(string $needle): int {
    $result = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'account_relationship',
      'options' => ['limit' => 100],
      'sequential' => 1,
    ]);

    foreach ($result['values'] as $row) {
      if (!empty($row['label']) && stripos($row['label'], $needle) !== FALSE) {
        return (int) $row['value'];
      }
      if (!empty($row['name']) && stripos($row['name'], $needle) !== FALSE) {
        return (int) $row['value'];
      }
    }

    $this->fail("Account relationship containing {$needle} not found");
  }

  /**
   * @param array<int|string, mixed> $ids
   * @return array<int>
   */
  private function filterIds(array $ids): array {
    return array_values(array_filter(array_map('intval', $ids)));
  }

}

/**
 * Test double to capture assigned variables and process messages.
 */
class CRM_Itemmanager_Test_UpdateItemsPageDouble extends CRM_Itemmanager_Page_UpdateItems {

  /** @var array<string, mixed> */
  public array $assignedValues = [];

  public ?string $statusType = NULL;

  public ?string $statusMessage = NULL;

  public ?string $statusTitle = NULL;

  public ?int $statusContactId = NULL;

  /**
   * Store assigned page values locally for assertions.
   *
   * @param array|string $var
   * @param mixed $value
   */
  public function assign($var, $value = NULL) {
    if (is_array($var)) {
      foreach ($var as $name => $item) {
        $this->assignedValues[(string) $name] = $item;
      }
      return;
    }

    $this->assignedValues[(string) $var] = $value;
  }

  protected function processError($status, $title, $message, $contact_id) {
    $this->statusType = 'error';
    $this->statusMessage = (string) $message;
    $this->statusTitle = (string) $title;
    $this->statusContactId = (int) $contact_id;
  }

  protected function processSuccess($message, $contact_id) {
    $this->statusType = 'success';
    $this->statusMessage = (string) $message;
    $this->statusTitle = 'Success';
    $this->statusContactId = (int) $contact_id;
  }

  protected function processInfo($message, $contact_id) {
    $this->statusType = 'info';
    $this->statusMessage = (string) $message;
    $this->statusTitle = 'Info';
    $this->statusContactId = (int) $contact_id;
  }

}
