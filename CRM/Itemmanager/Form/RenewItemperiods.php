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
            $this->processError("ERROR",E::ts('Retrieve memberships'),$member_array['error_message']);
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
                $this->processError("ERROR",E::ts('Retrieve Contributions'),$member_array['error_message']);
                return;
            }


            $firstid = CRM_Itemmanager_Util::getFirstReceiveDateContribution($contributions);
            if($firstid < 0)
            {
                $this->processError("ERROR",E::ts('Retrieve Contributions'),$member_array['error_message']);
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
                $this->processError("ERROR",E::ts('Retrieve line items'),$member_array['error_message']);
                return;
            }


            $linelist = array();
            foreach ($linerecords As $lineitem)
            {
                //get the itemmanager records
                $choices = CRM_Itemmanager_Util::getChoicesOfPricefieldsByFieldID(
                    CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata']),$last_date);

                $linecollection = array(
                    'name' =>  '(' .CRM_Utils_Array::value('id', $lineitem['setdata']) .') '.CRM_Utils_Array::value('label', $lineitem['linedata']).' '.
                        CRM_Utils_Array::value('title',$lineitem['setdata']),
                    'price_field_value_id' => CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata']),
                    'price_field_id' => CRM_Utils_Array::value('id', $lineitem['fielddata']),
                    'price_set_id' => CRM_Utils_Array::value('id', $lineitem['setdata']),
                    'last_qty' => CRM_Utils_Array::value('qty', $lineitem['linedata']),
                    'last_price_per_interval' => CRM_Utils_Money::format(
                        CRM_Utils_Array::value('unit_price', $lineitem['linedata']), NULL, NULL, TRUE),
                    'element_item_name' => 'member_'.$membership['memberdata']['id'].'_'.
                        'item_'.CRM_Utils_Array::value('id', $lineitem['fielddata']),
                    'element_hidden_name' => 'member_'.$membership['memberdata']['id'].'_'.
                        'item_'.CRM_Utils_Array::value('id', $lineitem['fielddata']).'_hidden',
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
                'member_id' => (int)$membership['memberdata']['id'],
                'lastcontribution_id' => $lastid,
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


            $this->addElement(
                'hidden',
                $line_item['element_hidden_name'],
                $line_item['price_set_id'],
                array('id'=> $line_item['element_hidden_name'],)
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
    $formvalues = $this->controller->exportValues($this->_name);

    foreach ($this->_memberships as $membership)
    {
        $item_prototypes = array();
        $startdate ="";
        $periods = 0;
        foreach ($membership['line_items'] as $line_item)
        {

            $choices = $line_item['choices'];
            $quantity = $line_item['last_qty'];
            $periods = $choices['period_selection'][0][max(
                array_keys($choices['period_selection'][0]))];
            $item_id = 0;
            $fieldValueId = $choices['field_value_selection'][0];
            $startdate = $choices['period_data'][0][max(
                array_keys($choices['period_data']))]['period_iso_start_on'];
            $manager_id = $choices['itemmanager_selection'][0];

            if(isset($formvalues[$line_item['element_item_name']]))
            {
                $item_id = (int)$formvalues[$line_item['element_item_name']];
                $id = $choices['field_value_selection'][$item_id];
                //break here if the empty is chosen
                if(!isset($id))
                    continue;

                $fieldValueId = $id;
                $startdate = $choices['period_data'][$item_id][max(
                    array_keys($choices['period_data']))]['period_iso_start_on'];
                $manager_id = $choices['itemmanager_selection'][$item_id];

            }

            if(isset($formvalues[$line_item['element_quantity_name']]))
            {
                $quantity = (float)$formvalues[$line_item['element_quantity_name']];
            }

            if(isset($formvalues[$line_item['element_period_name']]))
            {
                $periods = (int)$formvalues[$line_item['element_period_name']];
                $startdate = $choices['period_data'][$item_id][$periods]['period_iso_start_on'];
            }

            if($quantity == 0.0 or $periods == 0)
                continue;

            $item_prototypes[] = array(
                'manager_id'=> (int)$manager_id,
                 'quantity' => $quantity,
            );
        }


        if(count($item_prototypes)>0)
        {
            try {

                if ($periods == 1) {
                    //Single Installments
                    $singleInstallmentRenewal = new CRM_Itemmanager_Logic_RenewalSingleInstallmentPlan($membership['member_id'],
                        $membership['lastcontribution_id'], $periods, $startdate);

                    foreach ($item_prototypes as $prototype)
                    {
                        $singleInstallmentRenewal->addLineItemPrototype($prototype['manager_id'], $prototype['quantity']);
                    }

                    $singleInstallmentRenewal->run();


                } else {

                    //Multiple Installments
                    $multipleInstallmentRenewal = new CRM_Itemmanager_Logic_RenewalMultipleInstallmentPlan($membership['member_id'],
                        $membership['lastcontribution_id'],$periods, $startdate);

                    foreach ($item_prototypes as $prototype) {
                        $multipleInstallmentRenewal->addLineItemPrototype($prototype['manager_id'], $prototype['quantity']);
                    }

                   $multipleInstallmentRenewal->run();

                }

                $this->processSuccess("Membership ".$membership['name']." has been updated.");

            }
        catch (CRM_Core_Exception $e) {
            $this->processError("Membership ".$membership['name']." has been failed.",$e->getMessage(),
                "Update Membership");
        }

        }

    }//foreach ($this->_memberships as $membership)




    parent::postProcess();

  }


    /**
     * If your form requires special validation, add one or more callbacks here
     */
    public function addRules() {
        $this->addFormRule(array('CRM_Itemmanager_Form_RenewItemperiods', 'checkIntegrityRule'));
    }

    /**
     * We validate, that the pricefields for one membership are not mixed
     */
    public static function checkIntegrityRule($values) {
        $errors = array();
        $keys = array_keys($values);
        $items = array();
        $periods = array();
        foreach ($keys as $key) {
            if (strpos($key, 'member_') === 0) {
                $split = explode('_',$key);
                if(count($split)==5 and $split[4] == 'hidden')
                {
                    if($values[$key] == "") continue;

                    //Decide here, if pricesets are equal
                    if(!isset($items[$split[1]]))
                    {
                        $items[$split[1]] = $values[$key];
                    }
                    else
                    {
                        //check if all pricesets are equal
                        if($items[$split[1]] != $values[$key])
                            $errors[$split[0].'_'.$split[1].'_'.$split[2].'_'.$split[3]] =
                                '</br>'.ts('The price field has been chosen from a different price set!');
                    }


                }//if(count($split)==5 and $split[4] == 'hidden')


                if(count($split)==6 and $split[4] == 'period')
                {
                    if(($values[$key])==0) continue;

                    //Decide here, if pricesets are equal
                    if(!isset($periods [$split[1]]))
                    {
                        $periods [$split[1]] = $values[$key];
                    }
                    else
                    {
                        //check if all pricesets are equal
                        if($periods [$split[1]] != $values[$key])
                            $errors[$key] =
                                '</br>'.ts('The period count for all items has to be equal');
                    }


                }//if(count($split)==5 and $split[4] == 'hidden')


            }
        }

        return empty($errors) ? TRUE : $errors;
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
    protected function processError($status, $title, $message) {
        CRM_Core_Session::setStatus($status . "<br/>" . $message, ts('Error', array('domain' => 'org.stadtlandbeides.itemmanager')), 'error');
        $this->assign("error_title",   $title);
        $this->assign("error_message", $message);


    }

    protected function processSuccess($message) {
        CRM_Core_Session::setStatus($message, ts('Success', array('domain' => 'org.stadtlandbeides.itemmanager')), 'success');



    }

    protected function processInfo($message) {
        CRM_Core_Session::setStatus($message, ts('Info', array('domain' => 'org.stadtlandbeides.itemmanager')), 'info');



    }

}
