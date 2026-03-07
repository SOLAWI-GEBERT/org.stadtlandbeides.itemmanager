<?php

use Civi\Test\HookInterface;

/**
 * Tests for CRM_Itemmanager_Util helpers.
 *
 * @group headless
 */
class CRM_Itemmanager_Test_ItemmanagerUtilTest extends CRM_Itemmanager_Test_MembershipSeededTestCase implements HookInterface {

  public function testGetReferenceDateFormats(): void {
    $reference = new DateTime('2025-04-10');

    $this->assertEquals('2025-04-10', CRM_Itemmanager_Util::getReferenceDate($reference, 0));
    $this->assertEquals('2025-15', CRM_Itemmanager_Util::getReferenceDate($reference, 1));
    $this->assertEquals('2025-04', CRM_Itemmanager_Util::getReferenceDate($reference, 2));
    $this->assertEquals('2025', CRM_Itemmanager_Util::getReferenceDate($reference, 3));
    $this->assertEquals('2025-04-10', CRM_Itemmanager_Util::getReferenceDate($reference, 99));
  }

  public function testContributionTotalsAggregateLineItems(): void {
    $contactId = $this->getSeedId('member_contact');
    $contribution = $this->createContributionForContact($contactId, '2025-07-01', 200.0);

    $this->createLineItem($contribution['id'], 150.0, 15.0);
    $this->createLineItem($contribution['id'], 50.0, 5.0);

    $this->assertEquals(20.0, CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID($contribution['id']));
    $this->assertEquals(200.0, CRM_Itemmanager_Util::getAmountTotalFromContributionID($contribution['id']));
  }

  public function testReceiveDateHelpersReturnExtremes(): void {
    $contactId = $this->getSeedId('member_contact');
    $dates = ['2025-06-01', '2025-06-03', '2025-06-02'];
    $ids = [];

    foreach ($dates as $date) {
      $ids[] = $this->createContributionForContact($contactId, $date, 10.0)['id'];
    }

    $this->assertSame($ids[1], CRM_Itemmanager_Util::getLastReceiveDateContribution($ids));
    $this->assertSame($ids[0], CRM_Itemmanager_Util::getFirstReceiveDateContribution($ids));
  }

  public function testMembershipTypeAndPaymentsHelpers(): void {
    $membershipTypeId = $this->getMembershipTypeId();
    $type = CRM_Itemmanager_Util::getMembershipTypeById($membershipTypeId);
    $first = $type['values'] ? reset($type['values']) : NULL;
    $this->assertEquals($membershipTypeId, $first['id'] ?? NULL);

    $contactId = $this->getSeedId('member_contact');
    $membership = \Civi\Api4\Membership::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('membership_type_id', '=', $membershipTypeId)
      ->execute()
      ->first();

    if (empty($membership['id'])) {
      $membership = civicrm_api3('Membership', 'create', [
        'contact_id' => $contactId,
        'membership_type_id' => $membershipTypeId,
        'status_id' => 'Current',
        'start_date' => date('Y-m-d'),
      ]);
      $membership = ['id' => $membership['id'] ?? NULL];
    }

    $this->assertNotEmpty($membership['id']);

    $contribution = $this->createContributionForContact($contactId, '2025-03-01', 120.0);
    $payment = civicrm_api3('MembershipPayment', 'create', [
      'membership_id' => $membership['id'],
      'contribution_id' => $contribution['id'],
      'amount' => 120.0,
      'payment_date' => '2025-03-02',
    ]);

    $this->assertArrayHasKey('id', $payment);

    $result = CRM_Itemmanager_Util::getMemberShipPaymentByMembershipId($membership['id']);
    $this->assertEquals(0, $result['is_error']);
    $this->assertArrayHasKey($payment['id'], $result['values']);
  }

  public function testMembershipPaymentStatusChangeChain(): void {
    $contactId = $this->getSeedId('member_contact');
    $membershipTypeId = $this->getMembershipTypeId();

    $membership = civicrm_api3('Membership', 'create', [
      'contact_id' => $contactId,
      'membership_type_id' => $membershipTypeId,
      'status_id' => 'Pending',
      'start_date' => date('Y-m-d'),
    ]);
    $this->assertArrayHasKey('id', $membership);

    $contribution = $this->createContributionForContact($contactId, date('Y-m-d'), 80.0);
    $payment = civicrm_api3('MembershipPayment', 'create', [
      'membership_id' => $membership['id'],
      'contribution_id' => $contribution['id'],
      'amount' => 80.0,
      'payment_date' => date('Y-m-d'),
    ]);
    $this->assertArrayHasKey('id', $payment);

    $result = CRM_Itemmanager_Util::getMemberShipPaymentByMembershipId($membership['id']);
    $this->assertEquals(0, $result['is_error']);
    $this->assertArrayHasKey($payment['id'], $result['values']);

    civicrm_api3('Membership', 'create', [
      'id' => $membership['id'],
      'status_id' => 'Current',
    ]);

    $membershipCheck = civicrm_api3('Membership', 'getsingle', ['id' => $membership['id']]);
    $status = civicrm_api3('MembershipStatus', 'getsingle', ['name' => 'Current']);
    $this->assertSame((int) $status['id'], (int) ($membershipCheck['status_id'] ?? 0));
  }

  public function testFinancialAccountLookupUsesSalesTaxRelationship(): void {
    $financialTypeId = $this->getFinancialTypeId();
    $financialAccountId = $this->getSeedId('financial_account');
    $relationshipId = $this->findAccountRelationshipByLabel('Sales Tax Account is');

    \Civi\Api4\EntityFinancialAccount::delete(FALSE)
      ->addWhere('entity_table', '=', 'civicrm_financial_type')
      ->addWhere('entity_id', '=', $financialTypeId)
      ->addWhere('account_relationship', '=', $relationshipId)
      ->execute();

    \Civi\Api4\EntityFinancialAccount::create(FALSE)
      ->addValue('entity_table', 'civicrm_financial_type')
      ->addValue('entity_id', $financialTypeId)
      ->addValue('financial_account_id', $financialAccountId)
      ->addValue('account_relationship', $relationshipId)
      ->execute()
      ->first();

    $this->assertEquals($financialAccountId, CRM_Itemmanager_Util::getFinancialAccountId($financialTypeId));
  }

  private function getSeedId(string $key, int $index = 0): int {
    $value = $this->seedIds[$key][$index] ?? NULL;
    $this->assertNotEmpty($value, "Seeded ID for {$key}[{$index}] is required");
    return (int) $value;
  }

  private function getMembershipTypeId(): int {
    $id = $this->getSeedId('membership_type');
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

  private function getFinancialTypeId(): int {
    $id = $this->getSeedId('financial_type');
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

  private function getPriceFieldId(): int {
    $id = $this->getSeedId('price_field');
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
    $id = $this->getSeedId('price_field_value');
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

  private function createContributionForContact(int $contactId, string $receiveDate, float $totalAmount): array {
    $params = [
      'contact_id' => $contactId,
      'total_amount' => $totalAmount,
      'receive_date' => $receiveDate,
      'contribution_status_id' => 1,
      'financial_type_id' => $this->getFinancialTypeId(),
      'is_test' => 1,
      'skipLineItem' => 1,
    ];

    $result = civicrm_api3('Contribution', 'create', $params);
    $this->assertArrayHasKey('id', $result, 'Contribution creation failed');

    // Ensure no pre-existing line items for this contribution.
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_line_item WHERE contribution_id = %1', [
      1 => [$result['id'], 'Integer'],
    ]);

    return $result;
  }

  private function createLineItem(int $contributionId, float $lineTotal, float $taxAmount): array {
    $params = [
      'contribution_id' => $contributionId,
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $contributionId,
      'qty' => 1,
      'line_total' => $lineTotal,
      'unit_price' => $lineTotal,
      'financial_type_id' => $this->getFinancialTypeId(),
      'price_field_id' => $this->getPriceFieldId(),
      'price_field_value_id' => $this->getPriceFieldValueId(),
      'tax_amount' => $taxAmount,
    ];

    $result = civicrm_api3('LineItem', 'create', $params);
    $this->assertArrayHasKey('id', $result, 'Line item creation failed');

    // Ensure tax_amount is set (some APIs ignore input tax_amount).
    CRM_Core_DAO::executeQuery('UPDATE civicrm_line_item SET tax_amount = %1 WHERE id = %2', [
      1 => [$taxAmount, 'Float'],
      2 => [$result['id'], 'Integer'],
    ]);

    return $result;
  }

  // ---------------------------------------------------------------
  // 1.1 Tax helpers
  // ---------------------------------------------------------------

  public function testIsTaxEnabledInFinancialTypeReturnsTrue(): void {
    $financialTypeId = $this->getFinancialTypeId();
    $this->assertTrue(CRM_Itemmanager_Util::isTaxEnabledInFinancialType($financialTypeId));
  }

  public function testIsTaxEnabledInFinancialTypeReturnsFalseForNonTaxable(): void {
    $nonTaxFt = \Civi\Api4\FinancialType::get(FALSE)
      ->addWhere('name', '=', 'Donation')
      ->execute()
      ->first();

    if (empty($nonTaxFt['id'])) {
      $this->markTestSkipped('No non-taxable financial type "Donation" available');
    }

    $this->assertFalse(CRM_Itemmanager_Util::isTaxEnabledInFinancialType($nonTaxFt['id']));
  }

  public function testGetTaxRateInFinancialTypeReturnsRate(): void {
    $financialTypeId = $this->getFinancialTypeId();
    $rate = CRM_Itemmanager_Util::getTaxRateInFinancialType($financialTypeId);
    $this->assertIsNumeric($rate);
  }

  // ---------------------------------------------------------------
  // 1.2 getFinancialItemsByLineItemId
  // ---------------------------------------------------------------

  public function testGetFinancialItemsByLineItemIdReturnsItems(): void {
    $orderId = $this->getSeedId('order');

    $lineItems = civicrm_api3('LineItem', 'get', [
      'contribution_id' => $orderId,
      'options' => ['limit' => 1],
    ]);

    $this->assertGreaterThan(0, $lineItems['count'], 'Order should have line items');

    $lineItemId = (int) reset($lineItems['values'])['id'];
    $result = CRM_Itemmanager_Util::getFinancialItemsByLineItemId($lineItemId);

    $this->assertArrayHasKey('is_error', $result);
    $this->assertSame(0, (int) $result['is_error']);
  }

  // ---------------------------------------------------------------
  // 1.3 Financial transaction lookups
  // ---------------------------------------------------------------

  public function testGetFinancialEntityIdTrxnByContributionIdReturnsTransactions(): void {
    $orderId = $this->getSeedId('order');
    $result = CRM_Itemmanager_Util::getFinancialEntityIdTrxnByContributionId($orderId);

    $this->assertArrayHasKey('is_error', $result);
    $this->assertSame(0, (int) $result['is_error']);
    $this->assertGreaterThan(0, $result['count'], 'Order should have financial transactions');
  }

  public function testGetFinancialEntityTrxnByFinancialItemIdReturnsData(): void {
    $orderId = $this->getSeedId('order');

    $lineItems = civicrm_api3('LineItem', 'get', [
      'contribution_id' => $orderId,
      'options' => ['limit' => 1],
    ]);
    $lineItemId = (int) reset($lineItems['values'])['id'];

    $financialItems = CRM_Itemmanager_Util::getFinancialItemsByLineItemId($lineItemId);
    if (empty($financialItems['values'])) {
      $this->markTestSkipped('No financial items for order line item');
    }

    $firstFi = reset($financialItems['values']);
    $result = CRM_Itemmanager_Util::getFinancialEntityTrxnByFinancialItemId((int) $firstFi['id']);

    $this->assertArrayHasKey('is_error', $result);
    $this->assertSame(0, (int) $result['is_error']);
  }

  // ---------------------------------------------------------------
  // 1.4 getFinancialFullRecordsByLineItemId
  // ---------------------------------------------------------------

  public function testGetFinancialFullRecordsByLineItemIdChainsData(): void {
    $orderId = $this->getSeedId('order');

    $lineItems = civicrm_api3('LineItem', 'get', [
      'contribution_id' => $orderId,
      'options' => ['limit' => 1],
    ]);
    $lineItemId = (int) reset($lineItems['values'])['id'];

    $result = CRM_Itemmanager_Util::getFinancialFullRecordsByLineItemId($lineItemId);

    $this->assertArrayHasKey('is_error', $result);
    $this->assertSame(0, (int) $result['is_error']);
    $this->assertArrayHasKey('values', $result);

    if (!empty($result['values'])) {
      $first = $result['values'][0];
      $this->assertArrayHasKey('financeitem', $first);
      $this->assertArrayHasKey('accountinfo', $first);
    }
  }

  // ---------------------------------------------------------------
  // 1.5 getLineitemFullRecordByContributionId
  // ---------------------------------------------------------------

  public function testGetLineitemFullRecordByContributionIdReturnsNestedStructure(): void {
    $orderId = $this->getSeedId('order');
    $result = CRM_Itemmanager_Util::getLineitemFullRecordByContributionId($orderId);

    $this->assertIsArray($result);
    $this->assertNotEmpty($result, 'Order should have line item records');

    $first = $result[0];
    $this->assertArrayHasKey('linedata', $first);
    $this->assertArrayHasKey('fielddata', $first);
    $this->assertArrayHasKey('valuedata', $first);
    $this->assertArrayHasKey('setdata', $first);

    $this->assertSame(
      (int) $this->seedIds['price_set'][0],
      (int) $first['setdata']['id']
    );
  }

  public function testGetLineitemFullRecordByContributionIdWithFinancialFilter(): void {
    $orderId = $this->getSeedId('order');
    $financialTypeId = $this->getFinancialTypeId();

    $result = CRM_Itemmanager_Util::getLineitemFullRecordByContributionId($orderId, $financialTypeId);
    $this->assertIsArray($result);
    $this->assertNotEmpty($result);

    foreach ($result as $item) {
      $this->assertSame(
        $financialTypeId,
        (int) $item['linedata']['financial_type_id']
      );
    }
  }

  // ---------------------------------------------------------------
  // 1.6 getPriceSetRefByFieldValueId
  // ---------------------------------------------------------------

  public function testGetPriceSetRefByFieldValueIdTraversesChain(): void {
    $pfvId = $this->getPriceFieldValueId();
    $result = CRM_Itemmanager_Util::getPriceSetRefByFieldValueId($pfvId);

    $this->assertSame(0, $result['iserror']);
    $this->assertSame(
      (int) $this->seedIds['price_set'][0],
      (int) $result['price_id']
    );
  }

  public function testGetPriceSetRefByFieldValueIdReturnsErrorForInvalidId(): void {
    $result = CRM_Itemmanager_Util::getPriceSetRefByFieldValueId(999999);
    $this->assertSame(1, $result['iserror']);
  }

  // ---------------------------------------------------------------
  // 1.7 getLastMembershipsByContactId
  // ---------------------------------------------------------------

  public function testGetLastMembershipsByContactIdReturnsMembership(): void {
    $contactId = $this->getSeedId('member_contact');
    $result = CRM_Itemmanager_Util::getLastMembershipsByContactId($contactId);

    $this->assertSame(0, $result['is_error']);
    $this->assertNotEmpty($result['values']);
    $this->assertSame($contactId, (int) $result['values'][0]['contact_id']);
  }

  public function testGetLastMembershipsByContactIdReturnsEmptyForUnknownContact(): void {
    $result = CRM_Itemmanager_Util::getLastMembershipsByContactId(999999);

    $this->assertSame(0, $result['is_error']);
    $this->assertEmpty($result['values']);
  }

  // ---------------------------------------------------------------
  // 1.8 getLastMemberShipsFullRecordByContactId
  // ---------------------------------------------------------------

  public function testGetLastMemberShipsFullRecordByContactIdReturnsAggregated(): void {
    $contactId = $this->getSeedId('member_contact');
    $result = CRM_Itemmanager_Util::getLastMemberShipsFullRecordByContactId($contactId);

    $this->assertSame(0, $result['is_error']);
    $this->assertNotEmpty($result['values']);

    $first = $result['values'][0];
    $this->assertArrayHasKey('memberdata', $first);
    $this->assertArrayHasKey('typeinfo', $first);
    $this->assertArrayHasKey('payinfo', $first);
    $this->assertArrayHasKey('status', $first);
    $this->assertArrayHasKey('member_active', $first);
  }

  public function testGetLastMemberShipsFullRecordByContactIdReturnsEmptyForUnknown(): void {
    $result = CRM_Itemmanager_Util::getLastMemberShipsFullRecordByContactId(999999);

    $this->assertSame(0, $result['is_error']);
    $this->assertEmpty($result['values']);
  }

  // ---------------------------------------------------------------
  // 1.9 getLastPricefieldSuccessor
  // ---------------------------------------------------------------

  public function testGetLastPricefieldSuccessorReturnsCurrentWhenNoSettings(): void {
    $pfvId = $this->getPriceFieldValueId();
    $result = CRM_Itemmanager_Util::getLastPricefieldSuccessor($pfvId);
    $this->assertSame($pfvId, (int) $result);
  }

  public function testGetLastPricefieldSuccessorResolvesChain(): void {
    $pfvId = $this->getPriceFieldValueId();
    $priceSetId = (int) $this->seedIds['price_set'][0];

    $period = \Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', $priceSetId)
      ->addValue('periods', 12)
      ->addValue('period_type', 2)
      ->addValue('period_start_on', '2026-01-01')
      ->execute()
      ->first();
    $this->createdPeriodIds[] = (int) $period['id'];

    $settingA = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->addValue('price_field_value_id', $pfvId)
      ->addValue('itemmanager_periods_id', (int) $period['id'])
      ->addValue('itemmanager_successor_id', 0)
      ->addValue('ignore', FALSE)
      ->execute()
      ->first();
    $this->createdSettingIds[] = (int) $settingA['id'];

    // Without successor, should return same pfvId.
    $result = CRM_Itemmanager_Util::getLastPricefieldSuccessor($pfvId);
    $this->assertSame($pfvId, (int) $result);

    // Now add a second PFV and link as successor.
    $pfvId2 = (int) ($this->seedIds['price_field_value'][1] ?? 0);
    if (!$pfvId2) {
      $this->markTestSkipped('Need second price_field_value in seed');
    }

    $settingB = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->addValue('price_field_value_id', $pfvId2)
      ->addValue('itemmanager_periods_id', (int) $period['id'])
      ->addValue('itemmanager_successor_id', 0)
      ->addValue('ignore', FALSE)
      ->execute()
      ->first();
    $this->createdSettingIds[] = (int) $settingB['id'];

    // Link A → B.
    \Civi\Api4\ItemmanagerSettings::update(FALSE)
      ->addWhere('id', '=', $settingA['id'])
      ->addValue('itemmanager_successor_id', (int) $settingB['id'])
      ->execute();

    $result = CRM_Itemmanager_Util::getLastPricefieldSuccessor($pfvId);
    $this->assertSame($pfvId2, (int) $result);
  }

  // ---------------------------------------------------------------
  // 1.10 getSuccessorItemsettingsByPriceId
  // ---------------------------------------------------------------

  public function testGetSuccessorItemsettingsByPriceIdReturnsEmptyWithoutConfig(): void {
    $priceSetId = (int) $this->seedIds['price_set'][0];
    $result = CRM_Itemmanager_Util::getSuccessorItemsettingsByPriceId($priceSetId);

    $this->assertIsArray($result);
    $this->assertCount(2, $result);
    $this->assertEmpty($result[0]);
    $this->assertEmpty($result[1]);
  }

  public function testGetSuccessorItemsettingsByPriceIdReturnsSuccessorSettings(): void {
    $priceSetId = (int) $this->seedIds['price_set'][0];
    $pfvId = $this->getPriceFieldValueId();

    // Create two periods: current → successor.
    $periodSuccessor = \Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', $priceSetId)
      ->addValue('periods', 12)
      ->addValue('period_type', 2)
      ->addValue('period_start_on', '2027-01-01')
      ->execute()
      ->first();
    $this->createdPeriodIds[] = (int) $periodSuccessor['id'];

    $periodCurrent = \Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', $priceSetId)
      ->addValue('periods', 12)
      ->addValue('period_type', 2)
      ->addValue('period_start_on', '2026-01-01')
      ->addValue('itemmanager_period_successor_id', (int) $periodSuccessor['id'])
      ->execute()
      ->first();
    $this->createdPeriodIds[] = (int) $periodCurrent['id'];

    // Create a setting in the successor period (not ignored, not novitiate).
    $setting = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->addValue('price_field_value_id', $pfvId)
      ->addValue('itemmanager_periods_id', (int) $periodSuccessor['id'])
      ->addValue('ignore', FALSE)
      ->addValue('novitiate', FALSE)
      ->execute()
      ->first();
    $this->createdSettingIds[] = (int) $setting['id'];

    $result = CRM_Itemmanager_Util::getSuccessorItemsettingsByPriceId($priceSetId);

    $this->assertCount(2, $result);
    $this->assertNotEmpty($result[0], 'Should return successor period BAO');
    $this->assertNotEmpty($result[1], 'Should return successor settings array');
  }

  // ---------------------------------------------------------------
  // 1.11 getChoicesOfPricefieldsByFieldID
  // ---------------------------------------------------------------

  public function testGetChoicesOfPricefieldsByFieldIDWithoutSettingsReturnsEmpty(): void {
    $pfvId = $this->getPriceFieldValueId();
    $result = CRM_Itemmanager_Util::getChoicesOfPricefieldsByFieldID($pfvId, '2026-12-31');

    $this->assertArrayHasKey('item_selection', $result);
    // Without ItemmanagerSettings, should get an error/empty choice.
    $this->assertArrayHasKey(0, $result['item_selection']);
  }

  public function testGetChoicesOfPricefieldsByFieldIDWithSettingsReturnsPeriodData(): void {
    $priceSetId = (int) $this->seedIds['price_set'][0];
    $pfvId = $this->getPriceFieldValueId();
    $pfId = $this->getPriceFieldId();

    // Ensure active_on and expire_on are set on price field.
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_price_field SET active_on = '2026-01-01', expire_on = '2026-12-31' WHERE id = %1",
      [1 => [$pfId, 'Integer']]
    );

    $period = \Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', $priceSetId)
      ->addValue('periods', 4)
      ->addValue('period_type', 2)
      ->addValue('period_start_on', '2026-01-01')
      ->addValue('reverse', FALSE)
      ->execute()
      ->first();
    $this->createdPeriodIds[] = (int) $period['id'];

    $setting = \Civi\Api4\ItemmanagerSettings::create(FALSE)
      ->addValue('price_field_value_id', $pfvId)
      ->addValue('itemmanager_periods_id', (int) $period['id'])
      ->addValue('itemmanager_successor_id', 0)
      ->addValue('ignore', FALSE)
      ->execute()
      ->first();
    $this->createdSettingIds[] = (int) $setting['id'];

    $result = CRM_Itemmanager_Util::getChoicesOfPricefieldsByFieldID($pfvId, '2025-12-31');

    $this->assertArrayHasKey('item_selection', $result);
    $this->assertArrayHasKey('period_data', $result);
    $this->assertArrayHasKey('period_selection', $result);
    $this->assertArrayHasKey('field_value_selection', $result);
    $this->assertArrayHasKey('itemmanager_selection', $result);

    // Without successor → index 0 is self, index 1 is empty choice.
    $this->assertSame($pfvId, (int) $result['field_value_selection'][0]);
    $this->assertNull($result['field_value_selection'][1]);

    // Period data should have entries for period count (4 down to 1) + zero entry.
    $this->assertNotEmpty($result['period_data'][0]);
    $firstPeriod = reset($result['period_data'][0]);
    $this->assertArrayHasKey('period_iso_start_on', $firstPeriod);
    $this->assertArrayHasKey('period_start_on', $firstPeriod);
    $this->assertArrayHasKey('period_end_on', $firstPeriod);
    $this->assertArrayHasKey('interval_price', $firstPeriod);
  }

  // ---------------------------------------------------------------
  // 1.12 getFinancialAccountInfosByAccountId
  // ---------------------------------------------------------------

  public function testGetFinancialAccountInfosByAccountIdReturnsData(): void {
    $accountId = $this->getSeedId('financial_account');
    $result = CRM_Itemmanager_Util::getFinancialAccountInfosByAccountId($accountId);

    $this->assertArrayHasKey('id', $result);
    $this->assertSame($accountId, (int) $result['id']);
    $this->assertSame('Unit Test VAT Account', $result['name']);
  }

  public function testGetFinancialAccountInfosByAccountIdReturnsErrorForInvalidId(): void {
    $result = CRM_Itemmanager_Util::getFinancialAccountInfosByAccountId(999999);
    $this->assertSame(1, (int) ($result['is_error'] ?? 0));
  }

  // ---------------------------------------------------------------
  // Helper: tracking created IDs for cleanup
  // ---------------------------------------------------------------

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

    $this->createdSettingIds = [];
    $this->createdPeriodIds = [];

    parent::tearDown();
  }

}
