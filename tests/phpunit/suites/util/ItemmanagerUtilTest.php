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

}
