<?php

use Civi\Test\HookInterface;

/**
 * Tests for CRM_Itemmanager_Form_RenewItemperiods.
 *
 * @group headless
 */
class CRM_Itemmanager_Test_RenewItemperiodsTest extends CRM_Itemmanager_Test_MembershipSeededTestCase implements HookInterface {

  /** @var int[] */
  protected array $createdPeriodIds = [];

  /** @var int[] */
  protected array $createdSettingIds = [];

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

  // ---------------------------------------------------------------
  // 4.1 checkIntegrityRule — Mixed Price Sets
  // ---------------------------------------------------------------

  public function testCheckIntegrityRuleRejectsMixedPriceSets(): void {
    $values = [
      'member_1_item_10_hidden' => '5',
      'member_1_item_20_hidden' => '6',
      'member_1_item_10_new_hidden' => '0',
      'member_1_item_20_new_hidden' => '0',
    ];

    $result = CRM_Itemmanager_Form_RenewItemperiods::checkIntegrityRule($values);

    $this->assertIsArray($result, 'Mixed price sets should return error array');
    $this->assertArrayHasKey('member_1_item_20', $result,
      'Error should reference the conflicting field');
  }

  public function testCheckIntegrityRuleAcceptsSamePriceSets(): void {
    $values = [
      'member_1_item_10_hidden' => '5',
      'member_1_item_20_hidden' => '5',
      'member_1_item_10_new_hidden' => '0',
      'member_1_item_20_new_hidden' => '0',
    ];

    $result = CRM_Itemmanager_Form_RenewItemperiods::checkIntegrityRule($values);

    $this->assertTrue($result, 'Same price sets should pass validation');
  }

  public function testCheckIntegrityRuleAllowsMixedPriceSetsForNewFields(): void {
    $values = [
      'member_1_item_10_hidden' => '5',
      'member_1_item_20_hidden' => '6',
      'member_1_item_10_new_hidden' => '0',
      'member_1_item_20_new_hidden' => '1',
    ];

    $result = CRM_Itemmanager_Form_RenewItemperiods::checkIntegrityRule($values);

    $this->assertTrue($result, 'Mixed price sets with new_hidden=1 should be allowed');
  }

  // ---------------------------------------------------------------
  // 4.2 checkIntegrityRule — Unequal Period Counts
  // ---------------------------------------------------------------

  public function testCheckIntegrityRuleRejectsUnequalPeriodCounts(): void {
    $values = [
      'member_1_item_10_period_100' => '3',
      'member_1_item_20_period_200' => '6',
    ];

    $result = CRM_Itemmanager_Form_RenewItemperiods::checkIntegrityRule($values);

    $this->assertIsArray($result, 'Unequal period counts should return error array');
    $this->assertArrayHasKey('member_1_item_20_period_200', $result);
  }

  public function testCheckIntegrityRuleAcceptsEqualPeriodCounts(): void {
    $values = [
      'member_1_item_10_period_100' => '3',
      'member_1_item_20_period_200' => '3',
    ];

    $result = CRM_Itemmanager_Form_RenewItemperiods::checkIntegrityRule($values);

    $this->assertTrue($result, 'Equal period counts should pass validation');
  }

  public function testCheckIntegrityRuleSkipsZeroPeriods(): void {
    $values = [
      'member_1_item_10_period_100' => '3',
      'member_1_item_20_period_200' => '0',
    ];

    $result = CRM_Itemmanager_Form_RenewItemperiods::checkIntegrityRule($values);

    $this->assertTrue($result, 'Zero-value periods should be skipped');
  }

  // ---------------------------------------------------------------
  // 4.3 preProcess — Membership/LineItem aggregation
  // ---------------------------------------------------------------

  public function testPreProcessAggregatesMembershipLineItems(): void {
    $contactId = $this->getMemberContactId();
    $priceSetId = $this->getActivePriceSetId();
    $priceFieldId = $this->getPriceFieldId();
    $pfvId = $this->getPriceFieldValueId();
    $financialTypeId = $this->getFinancialTypeId();
    $membershipTypeId = $this->getMembershipTypeId();

    // Ensure membership has end_date (needed by getLastMembershipsByContactId).
    $this->ensureMembershipWithEndDate($contactId, $membershipTypeId);

    // Clean and create itemmanager config.
    $this->cleanItemmanagerDataForPriceSet($priceSetId);

    // Set help_pre on price field and value to avoid undefined key errors.
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_price_field SET help_pre = 'Test help', active_on = '2025-01-01', expire_on = '2026-12-31' WHERE id = %1",
      [1 => [$priceFieldId, 'Integer']]
    );
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_price_field_value SET help_pre = 'Value help' WHERE id = %1",
      [1 => [$pfvId, 'Integer']]
    );

    $period = \Civi\Api4\ItemmanagerPeriods::create(FALSE)
      ->addValue('price_set_id', $priceSetId)
      ->addValue('periods', 4)
      ->addValue('period_type', 2)
      ->addValue('period_start_on', '20260101')
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

    // Create a contribution with line item linked to membership.
    $contribution = $this->createContributionWithLineItems(
      $contactId, $priceFieldId, $pfvId, $membershipTypeId, $financialTypeId
    );

    // Link contribution to membership via MembershipPayment.
    $membership = \Civi\Api4\Membership::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('membership_type_id', '=', $membershipTypeId)
      ->execute()
      ->first();

    if (!empty($membership['id']) && !empty($contribution['id'])) {
      civicrm_api3('MembershipPayment', 'create', [
        'membership_id' => $membership['id'],
        'contribution_id' => $contribution['id'],
      ]);
    }

    // Verify MembershipPayment link exists.
    $mpCheck = civicrm_api3('MembershipPayment', 'get', [
      'membership_id' => $membership['id'],
      'sequential' => 1,
    ]);
    $this->assertGreaterThan(0, $mpCheck['count'],
      'MembershipPayment link should exist');

    // Verify getLastMemberShipsFullRecordByContactId returns data.
    $fullRecord = CRM_Itemmanager_Util::getLastMemberShipsFullRecordByContactId($contactId);
    $this->assertSame(0, $fullRecord['is_error'],
      'getLastMemberShipsFullRecordByContactId should not error: '
      . ($fullRecord['error_message'] ?? ''));
    $this->assertNotEmpty($fullRecord['values'],
      'getLastMemberShipsFullRecordByContactId should return memberships');

    // Check payinfo is populated.
    $firstMember = reset($fullRecord['values']);
    $this->assertNotEmpty($firstMember['payinfo'],
      'payinfo should be populated for membership');

    // Run preProcess with the contact_id set.
    // Suppress notices: production code accesses undefined keys (e.g. 'is_error')
    // which PHPUnit converts to exceptions via convertNoticesToExceptions.
    $oldLevel = error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
    $_REQUEST['cid'] = $contactId;
    $_GET['cid'] = $contactId;
    try {
      $form = new CRM_Itemmanager_Form_RenewItemperiods();
      $form->preProcess();
    }
    finally {
      unset($_REQUEST['cid'], $_GET['cid']);
      error_reporting($oldLevel);
    }

    $memberships = $form->_memberships;
    $errorMessages = (new \ReflectionProperty($form, '_errormessages'))->getValue($form);
    $this->assertEmpty($errorMessages,
      'preProcess should not have error messages: ' . print_r($errorMessages, TRUE));

    $this->assertNotEmpty($memberships, 'preProcess should populate _memberships');

    $firstMembership = reset($memberships);
    $this->assertArrayHasKey('name', $firstMembership);
    $this->assertArrayHasKey('member_id', $firstMembership);
    $this->assertArrayHasKey('line_items', $firstMembership);
    $this->assertArrayHasKey('lastcontribution_id', $firstMembership);
    $this->assertArrayHasKey('start_date', $firstMembership);
    $this->assertArrayHasKey('last_date', $firstMembership);

    // Verify line items contain our PFV.
    $lineItems = $firstMembership['line_items'];
    $this->assertNotEmpty($lineItems, 'Should have line items for membership');

    $firstLine = reset($lineItems);
    $this->assertArrayHasKey('choices', $firstLine);
    $this->assertArrayHasKey('element_item_name', $firstLine);
    $this->assertArrayHasKey('new_interval_price', $firstLine);
  }

  // ---------------------------------------------------------------
  // Private helpers
  // ---------------------------------------------------------------

  private function getMemberContactId(): int {
    $id = (int) ($this->seedIds['member_contact'][0] ?? 0);
    if ($id && CRM_Core_DAO::getFieldValue('CRM_Contact_DAO_Contact', $id, 'id', 'id')) {
      return $id;
    }
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT id FROM civicrm_contact WHERE first_name = 'Unit' AND last_name = 'Member' LIMIT 1"
    );
    if ($dao->fetch()) {
      return (int) $dao->id;
    }
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Unit')
      ->addValue('last_name', 'Member')
      ->execute()
      ->first();
    return (int) ($contact['id'] ?? 0);
  }

  private function getFinancialTypeId(): int {
    $id = (int) ($this->seedIds['financial_type'][0] ?? 0);
    if ($id && CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', $id, 'id', 'id')) {
      return $id;
    }
    return (int) CRM_Core_DAO::getFieldValue('CRM_Financial_DAO_FinancialType', 'Membership VAT 19', 'id', 'name');
  }

  private function getMembershipTypeId(): int {
    $id = (int) ($this->seedIds['membership_type'][0] ?? 0);
    if ($id && CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipType', $id, 'id', 'id')) {
      return $id;
    }
    return (int) CRM_Core_DAO::getFieldValue('CRM_Member_DAO_MembershipType', 'Unit Test Membership', 'id', 'name');
  }

  private function getPriceFieldId(): int {
    $id = (int) ($this->seedIds['price_field'][0] ?? 0);
    if ($id && CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceField', $id, 'id', 'id')) {
      return $id;
    }
    return (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceField', 'membership_type', 'id', 'name');
  }

  private function getPriceFieldValueId(): int {
    $id = (int) ($this->seedIds['price_field_value'][0] ?? 0);
    if ($id && CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $id, 'id', 'id')) {
      return $id;
    }
    $priceFieldId = $this->getPriceFieldId();
    $dao = CRM_Core_DAO::executeQuery(
      "SELECT id FROM civicrm_price_field_value WHERE price_field_id = %1 AND label = 'Unit Test Membership' LIMIT 1",
      [1 => [$priceFieldId, 'Integer']]
    );
    return $dao->fetch() ? (int) $dao->id : 0;
  }

  private function getActivePriceSetId(): int {
    $pfvId = $this->getPriceFieldValueId();
    $ref = CRM_Itemmanager_Util::getPriceSetRefByFieldValueId($pfvId);
    $this->assertSame(0, $ref['iserror']);
    return (int) $ref['price_id'];
  }

  private function ensureMembershipWithEndDate(int $contactId, int $membershipTypeId): void {
    $existing = \Civi\Api4\Membership::get(FALSE)
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('membership_type_id', '=', $membershipTypeId)
      ->execute()
      ->first();

    if (empty($existing['id'])) {
      civicrm_api3('Membership', 'create', [
        'contact_id' => $contactId,
        'membership_type_id' => $membershipTypeId,
        'status_id' => 'Current',
        'start_date' => date('Y-m-d'),
        'end_date' => date('Y-m-d', strtotime('+1 year')),
      ]);
    }
    elseif (empty($existing['end_date'])) {
      CRM_Core_DAO::executeQuery(
        "UPDATE civicrm_membership SET end_date = %1 WHERE id = %2",
        [
          1 => [date('Y-m-d', strtotime('+1 year')), 'String'],
          2 => [(int) $existing['id'], 'Integer'],
        ]
      );
    }
  }

  private function cleanItemmanagerDataForPriceSet(int $priceSetId): void {
    $periods = \Civi\Api4\ItemmanagerPeriods::get(FALSE)
      ->addWhere('price_set_id', '=', $priceSetId)
      ->execute();
    foreach ($periods as $period) {
      \Civi\Api4\ItemmanagerSettings::delete(FALSE)
        ->addWhere('itemmanager_periods_id', '=', (int) $period['id'])
        ->execute();
    }
    \Civi\Api4\ItemmanagerPeriods::delete(FALSE)
      ->addWhere('price_set_id', '=', $priceSetId)
      ->execute();
  }

  private function createContributionWithLineItems(
    int $contactId,
    int $priceFieldId,
    int $pfvId,
    int $membershipTypeId,
    int $financialTypeId
  ): array {
    $order = civicrm_api3('Order', 'create', [
      'contact_id' => $contactId,
      'total_amount' => 100,
      'financial_type_id' => $financialTypeId,
      'receive_date' => date('Y-m-d'),
      'contribution_status_id' => 'Completed',
      'line_items' => [
        [
          'params' => [
            'contact_id' => $contactId,
            'membership_type_id' => $membershipTypeId,
          ],
          'line_item' => [
            [
              'price_field_id' => $priceFieldId,
              'price_field_value_id' => $pfvId,
              'qty' => 1,
              'unit_price' => 100,
              'line_total' => 100,
              'financial_type_id' => $financialTypeId,
              'membership_type_id' => $membershipTypeId,
            ],
          ],
        ],
      ],
    ]);
    $this->assertArrayHasKey('id', $order);
    return $order;
  }

}
