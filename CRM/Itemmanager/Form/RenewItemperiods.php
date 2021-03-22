<?php

use CRM_Itemmanager_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Itemmanager_Form_RenewItemperiods extends CRM_Core_Form {

    public $_contact_id;
    public $_memberships;

    function __construct()
    {
        parent::__construct();
        $this->_memberships = array();
    }

    /**
     *
     * Get here the membership and price items
     *
     */
    public function preProcess()
    {
        $this->_contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');
        $this->assign('contact_id', $this->_contact_id);


        // Get the given memberships
        $member_array = CRM_Itemmanager_Util::getLastMemberShipsFullRecordByContactId($this->_contact_id);

        if($member_array['is_error'])
        {
            $this->processError("ERROR",E::ts('Retrieve memberships'),$member_array['error_message'],$this->_contact_id);
            return;
        }

        //Create a logical form reference
        foreach ($member_array['values'] As $membership)
        {
            //region Related Contributions
            //get the last record
            $contributions = array();
            foreach ($membership['payinfo'] As $contribution_link)
                $contributions[] = (int)$contribution_link['contribution_id'];

            $lastid = CRM_Itemmanager_Util::getLastReceiveDateContribution($contributions);
            if($lastid < 0)
            {
                $this->processError("ERROR",E::ts('Retrieve Contributions'),$member_array['error_message'],$this->_contact_id);
                return;
            }


            $firstid = CRM_Itemmanager_Util::getFirstReceiveDateContribution($contributions);
            if($firstid < 0)
            {
                $this->processError("ERROR",E::ts('Retrieve Contributions'),$member_array['error_message'],$this->_contact_id);
                return;
            }

            $last_contribution = civicrm_api3('Contribution', 'getsingle', array('id' => (int) $lastid));
            $last_date = CRM_Utils_Array::value('receive_date', $last_contribution);
            $first_contribution = civicrm_api3('Contribution', 'getsingle', array('id' => (int) $firstid));
            $first_date = CRM_Utils_Array::value('receive_date', $first_contribution);
            //endregion


            //get the line items to the last contribution
            $linerecords = CRM_Itemmanager_Util::getLineitemFullRecordByContributionId($lastid);
            if($linerecords['is_error'])
            {
                $this->processError("ERROR",E::ts('Retrieve line items'),$member_array['error_message'],$this->_contact_id);
                return;
            }


            $linelist = array();
            foreach ($linerecords As $lineitem)
            {
                //get the itemmanager records
                $choices = CRM_Itemmanager_Util::getChoicesOfPricefieldsByFieldID(
                    CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata']),$last_date);

                $linecollection = array(
                    'name' =>  CRM_Utils_Array::value('label', $lineitem['linedata']),
                    'price_field_value_id' => CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata']),
                    'price_field_id' => CRM_Utils_Array::value('id', $lineitem['fielddata']),
                    'last_qty' => CRM_Utils_Array::value('qty', $lineitem['linedata']),
                    'last_price_per_interval' => CRM_Utils_Money::format(
                        CRM_Utils_Array::value('unit_price', $lineitem['linedata']), NULL, NULL, TRUE),
                    'element_item_name' => 'member_'.$membership['memberdata']['id'].'_'.
                        'item_'.CRM_Utils_Array::value('id', $lineitem['fielddata']),
                    'element_quantity_name' => 'member_'.$membership['memberdata']['id'].'_'.
                        'item_'.CRM_Utils_Array::value('id', $lineitem['fielddata']).'_'.
                        'quantity_'.CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata']),
                    'element_period_name' => 'member_'.$membership['memberdata']['id'].'_'.
                        'item_'.CRM_Utils_Array::value('id', $lineitem['fielddata']).'_'.
                        'period_'.CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata']),
                    'choices' => $choices,
                    'new_active_on' => $choices['period_data'][0][max(
                        array_keys($choices['period_data'][0]))]['active_on'],
                    'new_expire_on' => $choices['period_data'][0][max(
                        array_keys($choices['period_data'][0]))]['expire_on'],
                    'new_interval_price' => $choices['period_data'][0][
                        max(array_keys($choices['period_data'][0]))]['interval_price'],
                    'new_period_start_on' => $choices['period_data'][0][max(
                        array_keys($choices['period_data']))]['period_start_on'],
                    'new_period_end_on' => $choices['period_data'][0][max(
                        array_keys($choices['period_data'][0]))]['period_end_on'],
                    'help_pre' => $choices['help_pre'][0],
                );
                $linelist[CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata'])] = $linecollection;
            }

            $form_collection = array(
                'name' => $membership['typeinfo']['name'],
                'member_id' => $membership['memberdata']['id'],
                'lascontribution_id' => $lastid,
                'start_date' => CRM_Utils_Date::customFormat(date_create($first_date)->format('Y-m-d'),
                    Civi::settings()->get('dateformatshortdate')),
                'last_date' => CRM_Utils_Date::customFormat(date_create($last_date)->format('Y-m-d'),
                    Civi::settings()->get('dateformatshortdate')),
                'line_items' => $linelist,


            );
            $this->_memberships[$membership['memberdata']['id']] = $form_collection;

        }

        $this->assign('memberships',$this->_memberships);
        Civi::resources()->addVars('RenewItemperiods', $this->_memberships);

        parent::preProcess();
    }//public function preProcess()

    /**
     * Create Form
     *
     * @throws CRM_Core_Exception
     */
    public function buildQuickForm() {

        $contact = civicrm_api('Contact', 'getsingle', array('version' => 3, 'id' => $this->_contact_id));
        $this->assign("display_name", $contact['display_name']);
        CRM_Utils_System::setTitle(E::ts('Renew periods for booked items for contact ').$contact['display_name']);

        //add itemselections
        foreach ($this->_memberships as $membership) {
          foreach ($membership['line_items'] as $line_item)
          {

            //Selection of the price item
            $this->add(
            'select',
                $line_item['element_item_name'],
                E::ts('Item'),
                $line_item['choices']['item_selection'], // list of options
                TRUE, // is required
                ['flex-grow'=> 1,
                    'onchange'=>"UpdateSettings(CRM.$, CRM._,".
                        $membership['member_id'].",".$line_item['price_field_id'].",".
                        $line_item['price_field_value_id'].",true)"],
            );
              //Selection of the period
            $this->add(
                'select',
                $line_item['element_period_name'],
                E::ts('Periods'),
                $line_item['choices']['period_selection'][0], // list of options
                TRUE, // is required
                ['onchange'=>"UpdateSettings(CRM.$, CRM._,".
                        $membership['member_id'].",".$line_item['price_field_id'].",".
                    $line_item['price_field_value_id'].",false)"],
            );

            $qtyattributes = array(
                'placeholder' => $line_item['last_qty'],
                'size'=>"5",
                'value' => $line_item['last_qty']
            );

            //Selection of the quantity item
            $this->add(
                'text',
                $line_item['element_quantity_name'],
                E::ts('Quantity'),
                $qtyattributes, // list of options
                TRUE, // is required
            );
          }


    }






    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->controller->exportValues($this->_name);
    foreach ($values as $value)
    {
        CRM_Core_Session::setStatus($value);
    }


//    try {
//
//
//        $multipleInstallmentRenewal = new CRM_Itemmanager_Logic_RenewalMultipleInstallmentPlan(132,
//            511,1,'2023-04-01');
//        $multipleInstallmentRenewal->addLineItemPrototype(6,1.0);
//
//        $multipleInstallmentRenewal->run();
//        }
//
//    catch (CRM_Core_Exception $e) {
//        CRM_Core_Session::setStatus($e->getMessage());
//    }

      try {

          $singleInstallmentRenewal = new CRM_Itemmanager_Logic_RenewalSingleInstallmentPlan(132,
              511,1,'2023-04-01');

          $singleInstallmentRenewal->addLineItemPrototype(6,1.0);

          $singleInstallmentRenewal->run();
      }

      catch (CRM_Core_Exception $e) {
          CRM_Core_Session::setStatus($e->getMessage());
      }

    parent::postProcess();

  }


  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }


    /**
     * report error data
     */
    protected function processError($status, $title, $message, $contact_id) {
        CRM_Core_Session::setStatus($status . "<br/>" . $message, ts('Error', array('domain' => 'org.stadtlandbeides.itemmanager')), 'error');
        $this->assign("error_title",   $title);
        $this->assign("error_message", $message);


        $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}&selectedChild=itemmanager");
        CRM_Utils_System::redirect($contact_url);

    }

    protected function processSuccess($message, $contact_id) {
        CRM_Core_Session::setStatus($message, ts('Success', array('domain' => 'org.stadtlandbeides.itemmanager')), 'success');

        $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}&selectedChild=itemmanager");
        CRM_Utils_System::redirect($contact_url);

    }

    protected function processInfo($message, $contact_id) {
        CRM_Core_Session::setStatus($message, ts('Info', array('domain' => 'org.stadtlandbeides.itemmanager')), 'info');

        $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}&selectedChild=itemmanager");
        CRM_Utils_System::redirect($contact_url);

    }

}
