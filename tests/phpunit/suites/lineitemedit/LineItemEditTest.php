<?php

use Civi\Test\HookInterface;
use CRM_Itemmanager_ExtensionUtil as E;

/**
 * Form double for LineItemEdit — overrides assign() to capture template vars,
 * and exposes private methods via public wrappers.
 */
class CRM_Itemmanager_Test_LineItemEditFormDouble extends CRM_Itemmanager_Form_LineItemEdit {

  /** @var array Captured template assignments. */
  public array $assignments = [];

  /** @var array|null Submitted values to inject for postProcess testing. */
  public ?array $testSubmittedValues = NULL;

  public function assign($var, $value = NULL) {
    $this->assignments[$var] = $value;
  }

  public function getSubmittedValues(): array {
    if ($this->testSubmittedValues !== NULL) {
      return $this->testSubmittedValues;
    }
    return parent::getSubmittedValues();
  }

  public function publicAssignFormVariables(array $params = []): void {
    $this->assignFormVariables($params);
  }

  public function publicCountFutureLineItems(): int {
    $method = new \ReflectionMethod($this, 'countFutureLineItems');
    $method->setAccessible(true);
    return $method->invoke($this);
  }

  public function publicApplyToFutureContributions(array $params): void {
    $method = new \ReflectionMethod($this, 'applyToFutureContributions');
    $method->setAccessible(true);
    $method->invoke($this, $params);
  }

  public function getLineitemInfo(): array {
    return $this->_lineitemInfo;
  }

  public function getValues(): array {
    return $this->_values;
  }

  public function getIsQuickConfig(): bool {
    return $this->_isQuickConfig;
  }

  public function getPriceFieldInfo(): array {
    return $this->_priceFieldInfo;
  }
}

/**
 * Tests for LineItemEdit form.
 *
 * @group headless
 */
class CRM_Itemmanager_Test_LineItemEditTest extends CRM_Itemmanager_Test_NonTransactionalSeededTestCase implements HookInterface {

  /** @var int[] Contribution IDs created per test. */
  protected array $contributionIds = [];

  public function tearDown(): void {
    // Clean up contributions created per test (cascade deletes line items).
    $ids = array_values(array_unique(array_filter($this->contributionIds, fn($id) => (int) $id > 0)));
    foreach ($ids as $contribId) {
      try {
        \Civi\Api4\Contribution::delete(FALSE)
          ->addWhere('id', '=', $contribId)
          ->execute();
      }
      catch (\Exception $e) {
        // ignore
      }
    }
    // Defensive cleanup to avoid lingering locks.
    try {
      CRM_Core_DAO::executeQuery('UNLOCK TABLES');
      CRM_Core_DAO::executeQuery('SET autocommit=1');
    }
    catch (\Exception $e) {
      // ignore
    }

    parent::tearDown();
  }

  // ── Group B: formRule() ──────────────────────────────────────────────

  public function testFormRuleAcceptsWholeNumber(): void {
    $errors = CRM_Itemmanager_Form_LineItemEdit::formRule(['qty' => '3'], [], NULL);
    $this->assertEmpty($errors);
  }

  public function testFormRuleAcceptsWholeNumberWithWhitespace(): void {
    $errors = CRM_Itemmanager_Form_LineItemEdit::formRule(['qty' => ' 5 '], [], NULL);
    $this->assertEmpty($errors);
  }

  public function testFormRuleRejectsDecimal(): void {
    $errors = CRM_Itemmanager_Form_LineItemEdit::formRule(['qty' => '2.5'], [], NULL);
    $this->assertArrayHasKey('qty', $errors);
  }

  public function testFormRuleRejectsNegativeNumber(): void {
    $errors = CRM_Itemmanager_Form_LineItemEdit::formRule(['qty' => '-1'], [], NULL);
    $this->assertArrayHasKey('qty', $errors);
  }

  public function testFormRuleRejectsNonNumeric(): void {
    $errors = CRM_Itemmanager_Form_LineItemEdit::formRule(['qty' => 'abc'], [], NULL);
    $this->assertArrayHasKey('qty', $errors);
  }

  public function testFormRuleRejectsEmptyString(): void {
    $errors = CRM_Itemmanager_Form_LineItemEdit::formRule(['qty' => ''], [], NULL);
    $this->assertArrayHasKey('qty', $errors);
  }

  public function testFormRuleAcceptsZero(): void {
    $errors = CRM_Itemmanager_Form_LineItemEdit::formRule(['qty' => '0'], [], NULL);
    $this->assertEmpty($errors);
  }

  // ── Group H: Money precision and locale ──────────────────────────────

  public function testRoundMoneyHandlesDivisionResults(): void {
    // 100/3 = 33.333333... should round to 33.3333
    $result = CRM_Itemmanager_Util::roundMoney(100 / 3);
    $this->assertSame(33.3333, $result);
  }

  public function testMoneyEqualsComparesAtFourDecimalPrecision(): void {
    // 33.33331 and 33.33334 both round to 33.3333
    $this->assertTrue(CRM_Itemmanager_Util::moneyEquals(33.33331, 33.33334));
    // 33.3333 and 33.3334 should differ
    $this->assertFalse(CRM_Itemmanager_Util::moneyEquals(33.3333, 33.3334));
  }

  public function testToMachineMoneyFormatsWithFourDecimals(): void {
    $result = CRM_Itemmanager_Util::toMachineMoney(8.3333);
    $this->assertSame('8.3333', $result);
  }

  public function testToMachineMoneyFromDivision(): void {
    // 100/12 = 8.33333... → should produce "8.3333"
    $result = CRM_Itemmanager_Util::toMachineMoney(100 / 12);
    $this->assertSame('8.3333', $result);
  }

  public function testRoundMoneyPreservesExactValues(): void {
    $this->assertSame(100.0, CRM_Itemmanager_Util::roundMoney(100));
    $this->assertSame(12.5, CRM_Itemmanager_Util::roundMoney(12.50));
    $this->assertSame(0.0, CRM_Itemmanager_Util::roundMoney(0));
  }

  // ── Group A: assignFormVariables() ───────────────────────────────────

  public function testAssignFormVariablesLoadsLineItemData(): void {
    $lineItemId = $this->getSeededLineItemId();
    $this->assertGreaterThan(0, $lineItemId, 'Seeded line item must exist.');

    $form = $this->createFormDouble($lineItemId);

    $values = $form->getValues();
    $this->assertArrayHasKey('label', $values);
    $this->assertArrayHasKey('qty', $values);
    $this->assertArrayHasKey('unit_price', $values);
    $this->assertArrayHasKey('financial_type_id', $values);
    $this->assertArrayHasKey('currency', $values);
    $this->assertIsInt($values['qty']);
  }

  public function testAssignFormVariablesSetsTaxAmountToZeroWhenNull(): void {
    $lineItemId = $this->getSeededLineItemId();
    $form = $this->createFormDouble($lineItemId);

    $info = $form->getLineitemInfo();
    // tax_amount should be a numeric value (0 if null)
    $this->assertNotNull($info['tax_amount']);
    $this->assertIsNumeric($info['tax_amount']);
  }

  public function testAssignFormVariablesDetectsQuickConfigFalse(): void {
    $lineItemId = $this->getSeededLineItemId();
    $form = $this->createFormDouble($lineItemId);

    // Our seeded price set is not quick config
    $this->assertFalse($form->getIsQuickConfig());
  }

  public function testAssignFormVariablesLoadsHelpTexts(): void {
    $lineItemId = $this->getSeededLineItemId();

    // Add help text to our price set
    $priceSetId = $this->seedIds['price_set'][0];
    \Civi\Api4\PriceSet::update(FALSE)
      ->addWhere('id', '=', $priceSetId)
      ->addValue('help_pre', 'Test help pre')
      ->execute();

    $form = $this->createFormDouble($lineItemId);

    $helpTexts = $form->assignments['helpTexts'] ?? [];
    $this->assertContains('Test help pre', $helpTexts);

    // Restore
    \Civi\Api4\PriceSet::update(FALSE)
      ->addWhere('id', '=', $priceSetId)
      ->addValue('help_pre', '')
      ->execute();
  }

  // ── Group A (continued): assignFormVariables() ─────────────────────

  public function testAssignFormVariablesDetectsQuickConfigTrueWhenNoPriceFieldId(): void {
    $lineItemId = $this->getSeededLineItemId();

    // Remove price_field_id temporarily to trigger quick config path
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_line_item SET price_field_id = NULL WHERE id = %1',
      [1 => [$lineItemId, 'Integer']]
    );

    $form = $this->createFormDouble($lineItemId);
    $this->assertTrue($form->getIsQuickConfig());

    // Restore
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_line_item SET price_field_id = %1 WHERE id = %2',
      [
        1 => [$this->seedIds['price_field'][0], 'Integer'],
        2 => [$lineItemId, 'Integer'],
      ]
    );
  }

  public function testAssignFormVariablesSkipsHelpTextsWhenNoPriceFieldId(): void {
    $lineItemId = $this->getSeededLineItemId();

    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_line_item SET price_field_id = NULL WHERE id = %1',
      [1 => [$lineItemId, 'Integer']]
    );

    $form = $this->createFormDouble($lineItemId);
    $this->assertArrayNotHasKey('helpTexts', $form->assignments);
    $this->assertEmpty($form->getPriceFieldInfo());

    // Restore
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_line_item SET price_field_id = %1 WHERE id = %2',
      [
        1 => [$this->seedIds['price_field'][0], 'Integer'],
        2 => [$lineItemId, 'Integer'],
      ]
    );
  }

  // ── Group C: countFutureLineItems() ──────────────────────────────────

  public function testCountFutureLineItemsReturnsZeroWhenNoPriceFieldValueId(): void {
    $lineItemId = $this->getSeededLineItemId();

    // Remove price_field_value_id temporarily
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_line_item SET price_field_value_id = NULL WHERE id = %1',
      [1 => [$lineItemId, 'Integer']]
    );

    $form = $this->createFormDouble($lineItemId);
    $count = $form->publicCountFutureLineItems();

    $this->assertSame(0, $count);

    // Restore
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_line_item SET price_field_value_id = %1 WHERE id = %2',
      [
        1 => [$this->seedIds['price_field_value'][0], 'Integer'],
        2 => [$lineItemId, 'Integer'],
      ]
    );
  }

  public function testCountFutureLineItemsReturnsZeroWhenNoFutureItems(): void {
    $lineItemId = $this->getSeededLineItemId();
    $form = $this->createFormDouble($lineItemId);

    // With only the seeded order, there should be no future items
    $count = $form->publicCountFutureLineItems();
    $this->assertSame(0, $count);
  }

  public function testCountFutureLineItemsCountsCorrectlyWithFutureOrders(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_LineItem', $lineItemId, 'contribution_id');

    // Get the receive_date of the seeded contribution
    $receiveDate = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribId, 'receive_date');
    $futureDate1 = date('Y-m-d', strtotime($receiveDate . ' +30 days'));
    $futureDate2 = date('Y-m-d', strtotime($receiveDate . ' +60 days'));
    $futureDate3 = date('Y-m-d', strtotime($receiveDate . ' +90 days'));

    $this->createFutureOrder($futureDate1);
    $this->createFutureOrder($futureDate2);
    $this->createFutureOrder($futureDate3);

    $form = $this->createFormDouble($lineItemId);
    $count = $form->publicCountFutureLineItems();

    $this->assertSame(3, $count);
  }

  public function testCountFutureLineItemsExcludesCurrentItem(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_LineItem', $lineItemId, 'contribution_id');

    $receiveDate = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribId, 'receive_date');
    // Create an order AFTER the seeded one — it should be counted
    $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' +1 day')));

    $form = $this->createFormDouble($lineItemId);
    $count = $form->publicCountFutureLineItems();

    // Future order: counted. Current item: excluded.
    $this->assertSame(1, $count);
  }

  public function testCountFutureLineItemsExcludesPastContributions(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = (int) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_LineItem', $lineItemId, 'contribution_id');
    $receiveDate = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribId, 'receive_date');

    // Create one future and one past order
    $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' +30 days')));
    $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' -30 days')));

    $form = $this->createFormDouble($lineItemId);
    $count = $form->publicCountFutureLineItems();

    // Only the future order should be counted, not the past one
    $this->assertSame(1, $count);
  }

  // ── Group F: updateEntityRecord() ────────────────────────────────────

  public function testUpdateEntityRecordRunsWithoutErrorForPositiveQty(): void {
    $lineItemId = $this->getSeededLineItemId();
    $lineItem = civicrm_api3('LineItem', 'getsingle', ['id' => $lineItemId]);

    $form = $this->createFormDouble($lineItemId);
    $lineItem['qty'] = 2;

    // Call updateEntityRecord — should not throw
    $method = new \ReflectionMethod($form, 'updateEntityRecord');
    $method->setAccessible(true);
    $method->invoke($form, $lineItem);

    // Verify membership still exists and is not cancelled
    $dao = CRM_Core_DAO::executeQuery(
      'SELECT status_id FROM civicrm_membership WHERE id = %1',
      [1 => [(int) $lineItem['entity_id'], 'Integer']]
    );
    $dao->fetch();

    $cancelledStatusId = CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'status_id', 'Cancelled');
    $this->assertNotSame((int) $cancelledStatusId, (int) $dao->status_id);
  }

  public function testUpdateEntityRecordCancelsMembershipWhenQtyZero(): void {
    $lineItemId = $this->getSeededLineItemId();
    $lineItem = civicrm_api3('LineItem', 'getsingle', ['id' => $lineItemId]);

    $form = $this->createFormDouble($lineItemId);
    $lineItem['qty'] = 0;

    $method = new \ReflectionMethod($form, 'updateEntityRecord');
    $method->setAccessible(true);
    $method->invoke($form, $lineItem);

    // Use direct SQL to check status — the production code uses checkPermissions=TRUE
    $dao = CRM_Core_DAO::executeQuery(
      'SELECT status_id, is_override FROM civicrm_membership WHERE id = %1',
      [1 => [(int) $lineItem['entity_id'], 'Integer']]
    );
    $dao->fetch();

    $cancelledStatusId = CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'status_id', 'Cancelled');
    $this->assertSame((int) $cancelledStatusId, (int) $dao->status_id);
    $this->assertTrue((bool) $dao->is_override);

    // Restore membership
    $currentStatusId = CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'status_id', 'Current');
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_membership SET status_id = %1, is_override = 0 WHERE id = %2',
      [
        1 => [(int) $currentStatusId, 'Integer'],
        2 => [(int) $lineItem['entity_id'], 'Integer'],
      ]
    );
  }

  public function testUpdateEntityRecordSetsMembershipTypeFromPriceFieldValue(): void {
    $lineItemId = $this->getSeededLineItemId();
    $lineItem = civicrm_api3('LineItem', 'getsingle', ['id' => $lineItemId]);

    $form = $this->createFormDouble($lineItemId);

    $method = new \ReflectionMethod($form, 'updateEntityRecord');
    $method->setAccessible(true);
    $method->invoke($form, $lineItem);

    // The membership_type_id from the PFV should be applied
    $pfvMembershipTypeId = CRM_Core_DAO::getFieldValue(
      'CRM_Price_DAO_PriceFieldValue',
      $lineItem['price_field_value_id'],
      'membership_type_id'
    );

    if (!empty($pfvMembershipTypeId)) {
      $membership = \Civi\Api4\Membership::get(FALSE)
        ->addWhere('id', '=', $lineItem['entity_id'])
        ->addSelect('membership_type_id')
        ->execute()
        ->first();
      $this->assertSame((int) $pfvMembershipTypeId, (int) $membership['membership_type_id']);
    }
    else {
      // If PFV has no membership_type_id, test still passes — nothing to assert
      $this->addToAssertionCount(1);
    }
  }

  public function testUpdateEntityRecordDoesNotCancelMembershipWhenQtyPositive(): void {
    $lineItemId = $this->getSeededLineItemId();
    $lineItem = civicrm_api3('LineItem', 'getsingle', ['id' => $lineItemId]);

    $form = $this->createFormDouble($lineItemId);
    $lineItem['qty'] = 1;

    $method = new \ReflectionMethod($form, 'updateEntityRecord');
    $method->setAccessible(true);
    $method->invoke($form, $lineItem);

    // With qty > 0, the membership should NOT be cancelled
    $dao = CRM_Core_DAO::executeQuery(
      'SELECT status_id FROM civicrm_membership WHERE id = %1',
      [1 => [(int) $lineItem['entity_id'], 'Integer']]
    );
    $dao->fetch();

    $cancelledStatusId = CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'status_id', 'Cancelled');
    $this->assertNotSame((int) $cancelledStatusId, (int) $dao->status_id);
  }

  // ── Group D: postProcess() ───────────────────────────────────────────

  public function testPostProcessUpdatesLineItemFields(): void {
    $lineItemId = $this->getSeededLineItemId();

    $form = $this->createFormDouble($lineItemId);
    $form->testSubmittedValues = [
      'label' => 'Updated Label',
      'financial_type_id' => $this->seedIds['financial_type'][0],
      'qty' => '2',
      'unit_price' => CRM_Itemmanager_Util::formatLocaleMoney(50),
      'line_total' => CRM_Itemmanager_Util::formatLocaleMoney(100),
      'tax_amount' => CRM_Itemmanager_Util::formatLocaleMoney(0),
    ];

    $form->postProcess();

    $updatedItem = civicrm_api3('LineItem', 'getsingle', ['id' => $lineItemId]);
    $this->assertSame('Updated Label', $updatedItem['label']);
    $this->assertEquals(2, (int) $updatedItem['qty']);
    $this->assertEquals(50.0, (float) $updatedItem['unit_price'], '', 0.01);

    // Restore
    $this->restoreSeededLineItem($lineItemId);
  }

  public function testPostProcessUpdatesContributionTotal(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = $this->seedIds['order'][0];

    $form = $this->createFormDouble($lineItemId);
    $form->testSubmittedValues = [
      'label' => 'Test Item',
      'financial_type_id' => $this->seedIds['financial_type'][0],
      'qty' => '1',
      'unit_price' => CRM_Itemmanager_Util::formatLocaleMoney(200),
      'line_total' => CRM_Itemmanager_Util::formatLocaleMoney(200),
      'tax_amount' => CRM_Itemmanager_Util::formatLocaleMoney(0),
    ];

    $form->postProcess();

    $contrib = \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('id', '=', $contribId)
      ->addSelect('total_amount')
      ->execute()
      ->first();

    $this->assertEquals(200.0, (float) $contrib['total_amount'], '', 0.01);

    // Restore
    $this->restoreSeededLineItem($lineItemId);
  }

  public function testPostProcessSetsTaxAmountToZeroWhenTaxDisabled(): void {
    $lineItemId = $this->getSeededLineItemId();

    $form = $this->createFormDouble($lineItemId);
    $form->testSubmittedValues = [
      'label' => 'Tax Test',
      'financial_type_id' => $this->seedIds['financial_type'][0],
      'qty' => '1',
      'unit_price' => CRM_Itemmanager_Util::formatLocaleMoney(100),
      'line_total' => CRM_Itemmanager_Util::formatLocaleMoney(100),
      'tax_amount' => CRM_Itemmanager_Util::formatLocaleMoney(19),
    ];

    $form->postProcess();

    // Since our seeded financial type has no tax, tax_amount should be 0
    $updatedItem = civicrm_api3('LineItem', 'getsingle', ['id' => $lineItemId]);
    $this->assertEquals(0.0, (float) $updatedItem['tax_amount'], '', 0.01);

    $this->restoreSeededLineItem($lineItemId);
  }

  public function testPostProcessCancelsMembershipWhenQtyZero(): void {
    $lineItemId = $this->getSeededLineItemId();

    $form = $this->createFormDouble($lineItemId);
    $form->testSubmittedValues = [
      'label' => 'Cancel Test',
      'financial_type_id' => $this->seedIds['financial_type'][0],
      'qty' => '0',
      'unit_price' => CRM_Itemmanager_Util::formatLocaleMoney(100),
      'line_total' => CRM_Itemmanager_Util::formatLocaleMoney(0),
      'tax_amount' => CRM_Itemmanager_Util::formatLocaleMoney(0),
    ];

    $form->postProcess();

    $lineItem = civicrm_api3('LineItem', 'getsingle', ['id' => $lineItemId]);

    // Use direct SQL — production code uses checkPermissions=TRUE
    $dao = CRM_Core_DAO::executeQuery(
      'SELECT status_id, is_override FROM civicrm_membership WHERE id = %1',
      [1 => [(int) $lineItem['entity_id'], 'Integer']]
    );
    $dao->fetch();

    $cancelledStatusId = CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'status_id', 'Cancelled');
    $this->assertSame((int) $cancelledStatusId, (int) $dao->status_id);
    $this->assertTrue((bool) $dao->is_override);

    // Restore membership and line item
    $currentStatusId = CRM_Core_PseudoConstant::getKey('CRM_Member_BAO_Membership', 'status_id', 'Current');
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_membership SET status_id = %1, is_override = 0 WHERE id = %2',
      [
        1 => [(int) $currentStatusId, 'Integer'],
        2 => [(int) $lineItem['entity_id'], 'Integer'],
      ]
    );
    $this->restoreSeededLineItem($lineItemId);
  }

  public function testPostProcessWithApplyFutureUpdatesFutureLineItems(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = $this->seedIds['order'][0];
    $receiveDate = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribId, 'receive_date');

    $futureContribId = $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' +30 days')));

    $form = $this->createFormDouble($lineItemId);
    $form->testSubmittedValues = [
      'label' => 'Synced Label',
      'financial_type_id' => $this->seedIds['financial_type'][0],
      'qty' => '3',
      'unit_price' => CRM_Itemmanager_Util::formatLocaleMoney(25),
      'line_total' => CRM_Itemmanager_Util::formatLocaleMoney(75),
      'tax_amount' => CRM_Itemmanager_Util::formatLocaleMoney(0),
      'apply_future' => 1,
    ];

    $form->postProcess();

    // Check the future line item was updated
    $futureLineItem = $this->getLineItemByContributionId($futureContribId);
    $this->assertSame('Synced Label', $futureLineItem['label']);
    $this->assertEquals(3, (int) $futureLineItem['qty']);
    $this->assertEquals(25.0, (float) $futureLineItem['unit_price'], '', 0.01);

    // Restore
    $this->restoreSeededLineItem($lineItemId);
  }

  public function testPostProcessWithoutApplyFutureDoesNotUpdateFutureItems(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = $this->seedIds['order'][0];
    $receiveDate = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribId, 'receive_date');

    $futureContribId = $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' +30 days')));

    $futureItemBefore = $this->getLineItemByContributionId($futureContribId);

    $form = $this->createFormDouble($lineItemId);
    $form->testSubmittedValues = [
      'label' => 'Changed Label Only Current',
      'financial_type_id' => $this->seedIds['financial_type'][0],
      'qty' => '1',
      'unit_price' => CRM_Itemmanager_Util::formatLocaleMoney(100),
      'line_total' => CRM_Itemmanager_Util::formatLocaleMoney(100),
      'tax_amount' => CRM_Itemmanager_Util::formatLocaleMoney(0),
    ];

    $form->postProcess();

    $futureItemAfter = $this->getLineItemByContributionId($futureContribId);
    $this->assertSame($futureItemBefore['label'], $futureItemAfter['label']);

    // Restore
    $this->restoreSeededLineItem($lineItemId);
  }

  // ── Group E: applyToFutureContributions() ───────────────────────────

  public function testApplyToFutureContributionsUpdatesLabelAndPrice(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = $this->seedIds['order'][0];
    $receiveDate = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribId, 'receive_date');

    $futureContribId = $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' +30 days')));

    $form = $this->createFormDouble($lineItemId);
    $params = [
      'id' => $lineItemId,
      'label' => 'Future Updated',
      'qty' => 2,
      'unit_price' => CRM_Itemmanager_Util::toMachineMoney(50),
      'line_total' => CRM_Itemmanager_Util::toMachineMoney(100),
      'tax_amount' => CRM_Itemmanager_Util::toMachineMoney(0),
      'financial_type_id' => $this->seedIds['financial_type'][0],
    ];

    $form->publicApplyToFutureContributions($params);

    $futureItem = $this->getLineItemByContributionId($futureContribId);
    $this->assertSame('Future Updated', $futureItem['label']);
    $this->assertEquals(2, (int) $futureItem['qty']);
    $this->assertEquals(50.0, (float) $futureItem['unit_price'], '', 0.01);
    $this->assertEquals(100.0, (float) $futureItem['line_total'], '', 0.01);
  }

  public function testApplyToFutureContributionsUpdatesContributionTotal(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = $this->seedIds['order'][0];
    $receiveDate = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribId, 'receive_date');

    $futureContribId = $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' +30 days')));

    $form = $this->createFormDouble($lineItemId);
    $params = [
      'id' => $lineItemId,
      'label' => 'Total Test',
      'qty' => 1,
      'unit_price' => CRM_Itemmanager_Util::toMachineMoney(200),
      'line_total' => CRM_Itemmanager_Util::toMachineMoney(200),
      'tax_amount' => CRM_Itemmanager_Util::toMachineMoney(0),
      'financial_type_id' => $this->seedIds['financial_type'][0],
    ];

    $form->publicApplyToFutureContributions($params);

    $contrib = \Civi\Api4\Contribution::get(FALSE)
      ->addWhere('id', '=', $futureContribId)
      ->addSelect('total_amount')
      ->execute()
      ->first();

    $this->assertEquals(200.0, (float) $contrib['total_amount'], '', 0.01);
  }

  public function testApplyToFutureContributionsDoesNotAffectPastItems(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = $this->seedIds['order'][0];
    $receiveDate = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribId, 'receive_date');

    $pastContribId = $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' -60 days')));
    $pastItemBefore = $this->getLineItemByContributionId($pastContribId);

    $form = $this->createFormDouble($lineItemId);
    $params = [
      'id' => $lineItemId,
      'label' => 'Should Not Propagate',
      'qty' => 5,
      'unit_price' => CRM_Itemmanager_Util::toMachineMoney(999),
      'line_total' => CRM_Itemmanager_Util::toMachineMoney(4995),
      'tax_amount' => CRM_Itemmanager_Util::toMachineMoney(0),
      'financial_type_id' => $this->seedIds['financial_type'][0],
    ];

    $form->publicApplyToFutureContributions($params);

    $pastItemAfter = $this->getLineItemByContributionId($pastContribId);
    $this->assertSame($pastItemBefore['label'], $pastItemAfter['label']);
    $this->assertEquals((float) $pastItemBefore['unit_price'], (float) $pastItemAfter['unit_price'], '', 0.01);
  }

  public function testApplyToFutureContributionsSkipsWhenNoPriceFieldValueId(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = $this->seedIds['order'][0];
    $receiveDate = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribId, 'receive_date');

    $futureContribId = $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' +30 days')));
    $futureItemBefore = $this->getLineItemByContributionId($futureContribId);

    // Remove price_field_value_id from the seeded line item
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_line_item SET price_field_value_id = NULL WHERE id = %1',
      [1 => [$lineItemId, 'Integer']]
    );

    $form = $this->createFormDouble($lineItemId);
    $params = [
      'id' => $lineItemId,
      'label' => 'No PFV',
      'qty' => 1,
      'unit_price' => CRM_Itemmanager_Util::toMachineMoney(100),
      'line_total' => CRM_Itemmanager_Util::toMachineMoney(100),
      'tax_amount' => CRM_Itemmanager_Util::toMachineMoney(0),
      'financial_type_id' => $this->seedIds['financial_type'][0],
    ];

    $form->publicApplyToFutureContributions($params);

    $futureItemAfter = $this->getLineItemByContributionId($futureContribId);
    $this->assertSame($futureItemBefore['label'], $futureItemAfter['label']);

    // Restore
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_line_item SET price_field_value_id = %1 WHERE id = %2',
      [
        1 => [$this->seedIds['price_field_value'][0], 'Integer'],
        2 => [$lineItemId, 'Integer'],
      ]
    );
  }

  public function testApplyToFutureContributionsUpdatesMultipleOrders(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = $this->seedIds['order'][0];
    $receiveDate = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribId, 'receive_date');

    $futureId1 = $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' +30 days')));
    $futureId2 = $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' +60 days')));

    $form = $this->createFormDouble($lineItemId);
    $params = [
      'id' => $lineItemId,
      'label' => 'Batch Update',
      'qty' => 3,
      'unit_price' => CRM_Itemmanager_Util::toMachineMoney(10),
      'line_total' => CRM_Itemmanager_Util::toMachineMoney(30),
      'tax_amount' => CRM_Itemmanager_Util::toMachineMoney(0),
      'financial_type_id' => $this->seedIds['financial_type'][0],
    ];

    $form->publicApplyToFutureContributions($params);

    $item1 = $this->getLineItemByContributionId($futureId1);
    $item2 = $this->getLineItemByContributionId($futureId2);

    $this->assertSame('Batch Update', $item1['label']);
    $this->assertSame('Batch Update', $item2['label']);
    $this->assertEquals(3, (int) $item1['qty']);
    $this->assertEquals(3, (int) $item2['qty']);
  }

  // ── Group G: buildQuickForm() ─────────────────────────────────────────

  public function testBuildQuickFormAddsExpectedFields(): void {
    $lineItemId = $this->getSeededLineItemId();
    $form = $this->createFormDouble($lineItemId);
    $this->initFormController($form);

    $form->buildQuickForm();

    // Verify key form elements exist
    $this->assertTrue($form->elementExists('label'));
    $this->assertTrue($form->elementExists('qty'));
    $this->assertTrue($form->elementExists('unit_price'));
    $this->assertTrue($form->elementExists('line_total'));
    $this->assertTrue($form->elementExists('financial_type_id'));
  }

  public function testBuildQuickFormAssignsFutureCount(): void {
    $lineItemId = $this->getSeededLineItemId();
    $form = $this->createFormDouble($lineItemId);
    $this->initFormController($form);

    $form->buildQuickForm();

    $this->assertArrayHasKey('futureCount', $form->assignments);
    $this->assertIsInt($form->assignments['futureCount']);
  }

  public function testBuildQuickFormAddsApplyFutureCheckboxWhenFutureItemsExist(): void {
    $lineItemId = $this->getSeededLineItemId();
    $contribId = $this->seedIds['order'][0];
    $receiveDate = CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $contribId, 'receive_date');

    $this->createFutureOrder(date('Y-m-d', strtotime($receiveDate . ' +30 days')));

    $form = $this->createFormDouble($lineItemId);
    $this->initFormController($form);

    $form->buildQuickForm();

    $this->assertGreaterThan(0, $form->assignments['futureCount']);
    $this->assertTrue($form->elementExists('apply_future'));
  }

  // ── Group H (continued): Money precision and locale ────────────────

  public function testAssignFormVariablesFormatsMoneyFieldsWithLocale(): void {
    $lineItemId = $this->getSeededLineItemId();
    $form = $this->createFormDouble($lineItemId);

    $values = $form->getValues();
    // unit_price and line_total are always present as locale-formatted strings
    foreach (['unit_price', 'line_total'] as $field) {
      $this->assertArrayHasKey($field, $values);
      $this->assertIsString($values[$field], "$field should be a locale-formatted string");
    }
    // Verify the value matches formatLocaleMoney output
    $rawUnitPrice = abs(civicrm_api3('LineItem', 'getvalue', [
      'id' => $lineItemId,
      'return' => 'unit_price',
    ]));
    $expected = CRM_Itemmanager_Util::formatLocaleMoney($rawUnitPrice);
    $this->assertSame($expected, $values['unit_price']);
  }

  public function testRoundMoneyHandlesFourDecimalUnitPrice(): void {
    // Simulate a price like 33.3333 (100/3 rounded to 4 decimals)
    $unitPrice = CRM_Itemmanager_Util::roundMoney(100 / 3);
    $this->assertSame(33.3333, $unitPrice);

    // toMachineMoney should preserve all 4 decimals
    $machine = CRM_Itemmanager_Util::toMachineMoney($unitPrice);
    $this->assertSame('33.3333', $machine);

    // formatLocaleMoney should also handle it
    $formatted = CRM_Itemmanager_Util::formatLocaleMoney($unitPrice);
    $this->assertNotEmpty($formatted);
  }

  // ── Helper Methods ───────────────────────────────────────────────────

  private function getSeededLineItemId(): int {
    $contribId = (int) ($this->seedIds['order'][0] ?? 0);
    if ($contribId <= 0) {
      $contribId = $this->createFutureOrder(date('Y-m-d'));
    }

    $lineItem = civicrm_api3('LineItem', 'getsingle', [
      'contribution_id' => $contribId,
      'options' => ['limit' => 1],
    ]);
    return (int) $lineItem['id'];
  }

  private function getMemberContactId(): int {
    $contactId = (int) ($this->seedIds['member_contact'][0] ?? 0);
    if ($contactId > 0) {
      return $contactId;
    }
    $contact = \Civi\Api4\Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Unit')
      ->addValue('last_name', 'Member')
      ->execute()
      ->first();
    $contactId = (int) ($contact['id'] ?? 0);
    $this->seedIds['member_contact'][0] = $contactId;
    return $contactId;
  }

  private function createFormDouble(int $lineItemId): CRM_Itemmanager_Test_LineItemEditFormDouble {
    $form = new CRM_Itemmanager_Test_LineItemEditFormDouble();
    $form->_id = $lineItemId;
    $form->publicAssignFormVariables();
    return $form;
  }

  private function createFutureOrder(string $receiveDate): int {
    $contactId = $this->getMemberContactId();
    $priceFieldId = $this->seedIds['price_field'][0] ?? 0;
    $priceFieldValueId = $this->seedIds['price_field_value'][0] ?? 0;
    $financialTypeId = $this->seedIds['financial_type'][0] ?? 0;
    $membershipTypeId = $this->seedIds['membership_type'][0] ?? 0;

    if (!$priceFieldId || !$priceFieldValueId || !$financialTypeId || !$membershipTypeId) {
      throw new \RuntimeException('Missing seed IDs for future order');
    }

    $order = civicrm_api3('Order', 'create', [
      'contact_id' => $contactId,
      'total_amount' => 100,
      'financial_type_id' => $financialTypeId,
      'receive_date' => $receiveDate,
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
              'unit_price' => 100,
              'line_total' => 100,
              'financial_type_id' => $financialTypeId,
              'membership_type_id' => $membershipTypeId,
            ],
          ],
        ],
      ],
    ]);

    $contribId = (int) $order['id'];
    $this->contributionIds[] = $contribId;
    return $contribId;
  }

  private function getLineItemByContributionId(int $contribId): array {
    return civicrm_api3('LineItem', 'getsingle', [
      'contribution_id' => $contribId,
      'options' => ['limit' => 1],
    ]);
  }

  private function createControllerStub(array $formValues): object {
    return new class($formValues) {
      private array $values;

      public function __construct(array $values) {
        $this->values = $values;
      }

      public function exportValues($name): array {
        return $this->values;
      }
    };
  }

  private function restoreSeededLineItem(int $lineItemId): void {
    $priceFieldValueId = $this->seedIds['price_field_value'][0];
    $financialTypeId = $this->seedIds['financial_type'][0];
    $contribId = $this->seedIds['order'][0];

    $pfv = civicrm_api3('PriceFieldValue', 'getsingle', ['id' => $priceFieldValueId]);

    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_line_item SET label = %1, qty = 1, unit_price = %2, line_total = %2, tax_amount = 0, financial_type_id = %3 WHERE id = %4",
      [
        1 => [$pfv['label'], 'String'],
        2 => [(float) $pfv['amount'], 'Float'],
        3 => [$financialTypeId, 'Integer'],
        4 => [$lineItemId, 'Integer'],
      ]
    );

    // Restore contribution total
    $lineTotal = CRM_Price_BAO_LineItem::getLineTotal($contribId);
    $taxTotal = CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID($contribId);
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_contribution SET total_amount = %1, net_amount = %1 WHERE id = %2",
      [
        1 => [$lineTotal + $taxTotal, 'Float'],
        2 => [$contribId, 'Integer'],
      ]
    );
  }

  private function initFormController(CRM_Itemmanager_Test_LineItemEditFormDouble $form): void {
    $form->controller = new CRM_Core_Controller_Simple(
      'CRM_Itemmanager_Form_LineItemEdit',
      'Line Item Edit'
    );
  }

  private function filterIds(array $ids): array {
    return array_values(array_filter(array_unique($ids), fn($id) => (int) $id > 0));
  }
}
