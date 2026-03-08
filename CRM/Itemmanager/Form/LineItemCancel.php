<?php

use CRM_Itemmanager_ExtensionUtil as E;

/**
 * Form controller for cancelling a single line item.
 *
 * Integrated from biz.jmaconsulting.lineitemedit (CRM_Lineitemedit_Form_Cancel).
 */
class CRM_Itemmanager_Form_LineItemCancel extends CRM_Core_Form {

  public $_lineitemInfo = NULL;

  protected $_multipleLineItem;

  public $_prevContributionID = NULL;

  public $_id;

  public function preProcess() {
    $this->_id = CRM_Utils_Request::retrieve('id', 'Positive', $this);
    $this->assignFormVariables();
  }

  public function assignFormVariables() {
    $this->_lineitemInfo = civicrm_api3('LineItem', 'getsingle', array('id' => $this->_id));

    $this->_prevContributionID = $this->_lineitemInfo['contribution_id'];

    $count = civicrm_api3('LineItem', 'getcount', array(
      'contribution_id' => $this->_prevContributionID,
      'line_total' => array('>' => 0),
    ));

    $this->_multipleLineItem = ($count > 1) ? TRUE : FALSE;
  }

  public function buildQuickForm() {
    $this->assign('message', E::ts('WARNING: Cancelling this line item will affect the related contribution and update the associated financial transactions. The quantity and total price will be set to 0 for this line item. Do you want to continue?'));

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Cancel Item'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Close'),
      ),
    ));

    parent::buildQuickForm();
  }

  public function postProcess() {
    CRM_Itemmanager_Util_LineItemEditor::cancelEntity($this->_lineitemInfo['entity_id'], $this->_lineitemInfo['entity_table']);

    civicrm_api3('LineItem', 'create', array(
      'id' => $this->_id,
      'qty' => 0,
      'participant_count' => 0,
      'line_total' => 0.00,
      'tax_amount' => 0.00,
    ));

    $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($this->_prevContributionID);
    $taxAmount = CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID($this->_prevContributionID);

    CRM_Itemmanager_Util_LineItemEditor::recordAdjustedAmt(
      $updatedAmount,
      $this->_prevContributionID,
      $taxAmount,
      FALSE
    );

    CRM_Itemmanager_Util_LineItemEditor::insertFinancialItemOnEdit(
      $this->_id,
      $this->_lineitemInfo
    );
    if ($this->_lineitemInfo['entity_table'] == 'civicrm_membership') {
      $contactId = CRM_Core_DAO::getFieldValue('CRM_Contribute_BAO_Contribution',
        $this->_lineitemInfo['contribution_id'],
        'contact_id'
      );
      $this->ajaxResponse['updateTabs']['#tab_member'] = CRM_Contact_BAO_Contact::getCountComponent('membership', $contactId);
    }

    parent::postProcess();
  }

}
