<?php

use CRM_Itemmanager_ExtensionUtil as E;

require_once 'CRM/Core/Form.php';

/**
 * Form controller for editing a single line item.
 *
 * Integrated from biz.jmaconsulting.lineitemedit (CRM_Lineitemedit_Form_Edit).
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Itemmanager_Form_LineItemEdit extends CRM_Core_Form {

  public $_id;

  public $_values;

  public $_isQuickConfig = FALSE;

  public $_priceFieldInfo = [];

  protected $_lineitemInfo;

  protected $submittableMoneyFields = ['unit_price', 'line_total', 'tax_amount'];

  public function preProcess() {
    $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this);
    $this->assignFormVariables();
  }

  private function isTaxEnabledInFinancialType($financialTypeId) {
    return CRM_Itemmanager_Util::isTaxEnabledInFinancialType($financialTypeId);
  }

  public function assignFormVariables($params = []) {

    $this->_lineitemInfo = civicrm_api3('lineItem', 'getsingle', array('id' => $this->_id));
    $this->_lineitemInfo['tax_amount'] = $this->_lineitemInfo['tax_amount'] ?? 0.00;
    foreach (CRM_Itemmanager_Util_LineItemEditor::getLineitemFieldNames() as $attribute) {
      if (in_array($attribute, $this->submittableMoneyFields)) {
        $this->_values[$attribute] = CRM_Itemmanager_Util::formatLocaleMoney(abs($this->_lineitemInfo[$attribute]));
      }
      elseif ($attribute === 'qty') {
        $this->_values[$attribute] = (int) abs($this->_lineitemInfo[$attribute]);
      }
      else {
        $this->_values[$attribute] = $this->_lineitemInfo[$attribute] ?? 0;
      }
    }
    $this->_values['currency'] = CRM_Core_DAO::getFieldValue(
      'CRM_Financial_DAO_Currency',
      CRM_Core_DAO::getFieldValue('CRM_Contribute_DAO_Contribution', $this->_lineitemInfo['contribution_id'], 'currency'),
      'symbol',
      'name'
    );

    $this->_isQuickConfig = empty($this->_lineitemInfo['price_field_id']);

    if (!empty($this->_lineitemInfo['price_field_id'])) {
      $this->_priceFieldInfo = civicrm_api3('PriceField', 'getsingle', array('id' => $this->_lineitemInfo['price_field_id']));
      $this->_isQuickConfig = (bool) CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceSet', $this->_priceFieldInfo['price_set_id'], 'is_quick_config');

      $helpTexts = [];
      $priceSet = civicrm_api3('PriceSet', 'getsingle', [
        'id' => $this->_priceFieldInfo['price_set_id'],
        'return' => ['title', 'help_pre', 'help_post'],
      ]);
      if (!empty($priceSet['help_pre'])) {
        $helpTexts[] = $priceSet['help_pre'];
      }
      if (!empty($priceSet['help_post'])) {
        $helpTexts[] = $priceSet['help_post'];
      }
      if (!empty($this->_priceFieldInfo['help_pre'])) {
        $helpTexts[] = $this->_priceFieldInfo['help_pre'];
      }
      if (!empty($this->_priceFieldInfo['help_post'])) {
        $helpTexts[] = $this->_priceFieldInfo['help_post'];
      }
      $this->assign('helpTexts', $helpTexts);
    }
  }

  public function setDefaultValues() {
    return $this->_values;
  }

  public function buildQuickForm() {
    $fieldNames = array_keys($this->_values);
    $this->assign('currency', $this->_values['currency']);
    foreach ($fieldNames as $fieldName) {
      $required = TRUE;
      if ($fieldName == 'line_total') {
        $this->add('text', 'line_total', E::ts('Total amount'), array(
          'size' => 6,
          'maxlength' => 14,
          'readonly' => TRUE)
        );
        continue;
      }
      elseif ($fieldName == 'currency') {
        continue;
      }
      $properties = array(
        'entity' => 'LineItem',
        'name' => $fieldName,
        'context' => 'edit',
        'action' => 'create',
      );
      if ($fieldName == 'financial_type_id') {
        CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes($financialTypes);
        $properties['options'] = $financialTypes;
      }
      elseif ($fieldName == 'tax_amount') {
        $properties['readonly'] = TRUE;
        $required = FALSE;
      }

      $ele = $this->addField($fieldName, $properties, $required);
    }
    $this->assign('fieldNames', $fieldNames);

    $this->assign('taxRates', json_encode(CRM_Core_PseudoConstant::getTaxRates()));

    $this->assign('isTaxEnabled', $this->isTaxEnabledInFinancialType($this->_values['financial_type_id']));

    // Count future contributions with the same price_field_value_id
    $futureCount = $this->countFutureLineItems();
    $this->assign('futureCount', $futureCount);
    if ($futureCount > 0) {
      $this->add('checkbox', 'apply_future', E::ts('Apply to %1 future contributions', [1 => $futureCount]));
    }

    $this->addFormRule(array(__CLASS__, 'formRule'), $this);

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Close'),
      ),
    ));

    parent::buildQuickForm();
  }

  public static function formRule($fields, $files, $self) {
    $errors = array();

    if (!ctype_digit(trim($fields['qty']))) {
      $errors['qty'] = E::ts('Please enter a whole number for quantity');
    }

    return $errors;
  }

  public function postProcess() {
    $values = $this->getSubmittedValues();
    $values['line_total'] = CRM_Itemmanager_Util::toMachineMoney($values['line_total']);
    $balanceAmount = ($values['line_total'] - $this->_lineitemInfo['line_total']);
    $contactId = CRM_Core_DAO::getFieldValue('CRM_Contribute_BAO_Contribution',
      $this->_lineitemInfo['contribution_id'],
      'contact_id'
    );

    if (!$this->isTaxEnabledInFinancialType($values['financial_type_id'])) {
      $values['tax_amount'] = 0.00;
    }
    $qty = (int) $values['qty'];
    $params = array(
      'id' => $this->_id,
      'financial_type_id' => $values['financial_type_id'],
      'label' => $values['label'],
      'qty' => $qty,
      'unit_price' => CRM_Itemmanager_Util::toMachineMoney($values['unit_price']),
      'line_total' => $values['line_total'],
      'tax_amount' => CRM_Itemmanager_Util::toMachineMoney($values['tax_amount'] ?? 0.00),
    );

    $lineItem = CRM_Price_BAO_LineItem::create($params);
    $lineItem = civicrm_api3('LineItem', 'getsingle', ['id' => $this->_id]);

    $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($this->_lineitemInfo['contribution_id']);
    $taxAmount = CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID($this->_lineitemInfo['contribution_id']);
    CRM_Itemmanager_Util_LineItemEditor::recordAdjustedAmt(
      $updatedAmount,
      $this->_lineitemInfo['contribution_id'],
      $taxAmount,
      FALSE
    );

    CRM_Itemmanager_Util_LineItemEditor::insertFinancialItemOnEdit(
      $this->_id,
      $this->_lineitemInfo
    );

    // Apply to future contributions if checkbox was checked
    if (!empty($values['apply_future'])) {
      $this->applyToFutureContributions($params);
    }

    if (in_array($this->_lineitemInfo['entity_table'], ['civicrm_membership', 'civicrm_participant']) && !empty($lineItem['entity_id'])) {
      $this->updateEntityRecord($this->_lineitemInfo);
      $entityTab = ($this->_lineitemInfo['entity_table'] == 'civicrm_membership') ? 'member' : 'participant';
      $this->ajaxResponse['updateTabs']['#tab_' . $entityTab] = CRM_Contact_BAO_Contact::getCountComponent(str_replace('civicrm_', '', $this->_lineitemInfo['entity_table']), $contactId);
    }
    parent::postProcess();
  }

  /**
   * Count line items with the same price_field_value_id
   * across contributions from the current one onwards.
   */
  private function countFutureLineItems() {
    if (empty($this->_lineitemInfo['price_field_value_id'])) {
      return 0;
    }
    $receiveDate = CRM_Core_DAO::getFieldValue(
      'CRM_Contribute_DAO_Contribution',
      $this->_lineitemInfo['contribution_id'],
      'receive_date'
    );
    $query = "
      SELECT COUNT(DISTINCT li.id)
      FROM civicrm_line_item li
      INNER JOIN civicrm_contribution c ON li.contribution_id = c.id
      WHERE li.price_field_value_id = %1
      AND li.id != %2
      AND c.receive_date >= %3
    ";
    return (int) CRM_Core_DAO::singleValueQuery($query, [
      1 => [(int) $this->_lineitemInfo['price_field_value_id'], 'Integer'],
      2 => [(int) $this->_id, 'Integer'],
      3 => [$receiveDate, 'String'],
    ]);
  }

  /**
   * Apply the same edit (label, unit_price, qty, financial_type, tax)
   * to all line items with the same price_field_value_id
   * from the current contribution onwards.
   */
  private function applyToFutureContributions($params) {
    if (empty($this->_lineitemInfo['price_field_value_id'])) {
      return;
    }

    $receiveDate = CRM_Core_DAO::getFieldValue(
      'CRM_Contribute_DAO_Contribution',
      $this->_lineitemInfo['contribution_id'],
      'receive_date'
    );
    $query = "
      SELECT li.id AS line_id, li.contribution_id
      FROM civicrm_line_item li
      INNER JOIN civicrm_contribution c ON li.contribution_id = c.id
      WHERE li.price_field_value_id = %1
      AND li.id != %2
      AND c.receive_date >= %3
    ";
    $dao = CRM_Core_DAO::executeQuery($query, [
      1 => [(int) $this->_lineitemInfo['price_field_value_id'], 'Integer'],
      2 => [(int) $this->_id, 'Integer'],
      3 => [$receiveDate, 'String'],
    ]);

    $line_item_table = CRM_Price_DAO_LineItem::getTableName();
    $contribution_table = CRM_Contribute_DAO_Contribution::getTableName();
    $financial_item_table = CRM_Financial_DAO_FinancialItem::getTableName();
    $financial_trxn_table = CRM_Financial_DAO_EntityFinancialTrxn::getTableName();

    while ($dao->fetch()) {
      $lineId = (int) $dao->line_id;
      $contribId = (int) $dao->contribution_id;

      // Update line item
      CRM_Core_DAO::executeQuery(
        "UPDATE $line_item_table
         SET label = %1, qty = %2, unit_price = %3, line_total = %4,
             tax_amount = %5, financial_type_id = %6
         WHERE id = %7",
        [
          1 => [$params['label'], 'String'],
          2 => [$params['qty'], 'Integer'],
          3 => [$params['unit_price'], 'Float'],
          4 => [$params['line_total'], 'Float'],
          5 => [$params['tax_amount'], 'Float'],
          6 => [$params['financial_type_id'], 'Integer'],
          7 => [$lineId, 'Integer'],
        ]
      );

      // Update financial records
      $financeitems = CRM_Itemmanager_Util::getFinancialFullRecordsByLineItemId($lineId);
      if (!$financeitems['is_error']) {
        foreach ($financeitems['values'] as $fi) {
          $amount = $fi['accountinfo']['is_tax']
            ? (float) $params['tax_amount']
            : (float) $params['line_total'];

          CRM_Core_DAO::executeQuery(
            "UPDATE $financial_item_table SET amount = %1 WHERE id = %2",
            [1 => [$amount, 'Float'], 2 => [(int) $fi['financeitem']['id'], 'Integer']]
          );

          $trxn = CRM_Itemmanager_Util::getFinancialEntityTrxnByFinancialItemId(
            (int) $fi['financeitem']['id']
          );
          if (!empty($trxn['id'])) {
            CRM_Core_DAO::executeQuery(
              "UPDATE $financial_trxn_table SET amount = %1 WHERE id = %2",
              [1 => [$amount, 'Float'], 2 => [(int) $trxn['id'], 'Integer']]
            );
          }
        }
      }

      // Update contribution total
      $taxTotal = CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID($contribId);
      $lineTotal = CRM_Itemmanager_Util::getAmountTotalFromContributionID($contribId);
      CRM_Core_DAO::executeQuery(
        "UPDATE $contribution_table SET total_amount = %1, net_amount = %1 WHERE id = %2",
        [1 => [$taxTotal + $lineTotal, 'Float'], 2 => [$contribId, 'Integer']]
      );

      $contribTrxn = CRM_Itemmanager_Util::getFinancialEntityIdTrxnByContributionId($contribId);
      if (!empty($contribTrxn['id'])) {
        CRM_Core_DAO::executeQuery(
          "UPDATE $financial_trxn_table SET amount = %1 WHERE id = %2",
          [1 => [$taxTotal + $lineTotal, 'Float'], 2 => [(int) $contribTrxn['id'], 'Integer']]
        );
      }
    }
  }

  protected function updateEntityRecord($lineItem) {
    $params = ['id' => $lineItem['entity_id']];
    if (($lineItem['entity_table'] == 'civicrm_membership')) {
      $membership = \Civi\Api4\Membership::update(TRUE)
        ->addWhere('id', '=', $lineItem['entity_id']);

      if (!empty($lineItem['price_field_value_id'])) {
        $memberNumTerms = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $lineItem['price_field_value_id'], 'membership_num_terms');
        $membershipTypeId = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $lineItem['price_field_value_id'], 'membership_type_id');
        $memberNumTerms = empty($memberNumTerms) ? 1 : $memberNumTerms;
        $memberNumTerms = $lineItem['qty'] * $memberNumTerms;
        $membership->addValue('num_terms', $memberNumTerms);
        if (!empty($membershipTypeId)) {
          $membership->addValue('membership_type_id', $membershipTypeId);
        }
      }
      if ($lineItem['qty'] == 0) {
        $membership->addValue('status_id:name', 'Cancelled');
        $membership->addValue('is_override', TRUE);
      }
      else {
        $membership->addValue('skipStatusCal', FALSE);
      }

      $membership->execute();
    }
    else {
      $line = array();
      $lineTotal = 0;
      $getUpdatedLineItems = CRM_Utils_SQL_Select::from('civicrm_line_item')
                              ->where([
                                "entity_table = '!et'",
                                "entity_id = #eid",
                                "qty > 0",
                              ])
                              ->param('!et', $lineItem['entity_table'])
                              ->param('#eid', $lineItem['entity_id'])
                              ->execute()
                              ->fetchAll();
      foreach ($getUpdatedLineItems as $updatedLineItem) {
        $line[] = $updatedLineItem['label'] . ' - ' . (float) $updatedLineItem['qty'];
        $lineTotal += $updatedLineItem['line_total'] + (float) $updatedLineItem['tax_amount'];
      }
      $params['fee_level'] = implode(', ', $line);
      $params['fee_amount'] = $lineTotal;
      civicrm_api3('Participant', 'create', $params);

      CRM_Event_BAO_Participant::addActivityForSelection($lineItem['entity_id'], 'Change Registration');
    }
  }

}
