<?php

use CRM_Itemmanager_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Itemmanager_Form_LinkSepaPayments extends CRM_Core_Form {



    public $_contact_id;
    public $_relations;
    private $_errormessages;

    /**
     * CRM_Itemmanager_Form_LinkSepaPayments constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->_relations = array();
        $this->_errormessages = array();
    }

    /**
     * Get here the contribution relations needed for the form
     */
    public function preProcess()
    {
        $this->_contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');
        $this->assign('contact_id', $this->_contact_id);

        $itemmanager_price_fields = \Civi\Api4\ItemmanagerSettings::get()
            ->setCheckPermissions(TRUE)
            ->execute();

        $currency = Civi::settings()->get('defaultCurrency');

        //region Fetch Financial types as root object
        foreach ($itemmanager_price_fields as $item)
        {

            $price_id = CRM_Utils_Array::value('price_field_value_id', $item);
            $ignore = CRM_Utils_Array::value('ignore', $item);
            $novitiate = CRM_Utils_Array::value('novitiate', $item);
            //avoid general contribution amount etc.
            if($ignore && !$novitiate) continue;


            $price_origins  = civicrm_api3('PriceFieldValue', 'get',
                array('price_field_id' => (int)$price_id));
            if ($price_origins['is_error']) {
                $this->_errormessages[] = 'Could not get the price field ' .(int)$price_id;
                continue;
            }

            $price_origin = reset($price_origins['values']);

            if(!$price_origin)
                continue;

            $financial_id = CRM_Utils_Array::value('financial_type_id', $price_origin);

            //check further efforts first
            if(array_key_exists($financial_id, $this->_relations)) continue;

            $finance_type  = civicrm_api3('FinancialType', 'getsingle',
                array('id' => (int)$financial_id));
            if (!isset($finance_type)) {
                $this->_errormessages[] = 'Could not get the financial type ' .(int)$financial_id;
                continue;
            }

            $finance_name = CRM_Utils_Array::value('name', $finance_type);

            //basic dictionary with default settings
            $this->_relations[(int)$financial_id] = array(
                'financial_id' => $financial_id,
                'financial_name' => $finance_name,
                'contributions' => array(),
            );

        }
        //endregion

        // Get the given memberships
        $member_array = CRM_Itemmanager_Util::getLastMemberShipsFullRecordByContactId($this->_contact_id);

        if($member_array['is_error'])
        {
            $this->processError("ERROR",E::ts('Retrieve memberships'),$member_array['error_message']);
            return;
        }

        //Create a logical form reference
        foreach ($member_array['values'] As $membership) {
            //region Related Contributions
            //get the last record
            $contributions = array();
            foreach ($membership['payinfo'] as $contribution_link)
            {
                $contribution_id = (int)$contribution_link['contribution_id'];


                $current_contribution = civicrm_api3('Contribution', 'getsingle', array('id' => (int)$contribution_id));
                $contrib_date_raw = CRM_Utils_Array::value('receive_date', $current_contribution);
                $contrib_date = CRM_Utils_Date::customFormat(date_create( $contrib_date_raw)->format('Y-m-d'),
                    Civi::settings()->get('dateformatshortdate'));
                //endregion


                //get the line items to the last contribution
                $linerecords = CRM_Itemmanager_Util::getLineitemFullRecordByContributionId($contribution_id);
                if ($linerecords['is_error']) {
                    $this->_errormessages[] = 'Could not get the line items for contribution ' .(int)$contribution_id;
                    continue;
                }


                $linelist = array();
                foreach ($linerecords as $lineitem) {

                    $price_field_value_id = CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata']);
                    $price_set_name = CRM_Utils_Array::value('title',$lineitem['setdata']);
                    $financial_id = CRM_Utils_Array::value('financial_type_id', $lineitem['valuedata']);

                    $line_total = CRM_Utils_Array::value('line_total',$lineitem['linedata']);
                    $line_tax = CRM_Utils_Array::value('tax_amount',$lineitem['linedata']);


                    $relation = &$this->_relations[(int)$financial_id];

                    //create entry just once per contribution
                    if(!array_key_exists($contribution_id, $relation['contributions']))
                        $relation['contributions'][$contribution_id] = array(

                            'contribution_id' => $contribution_id,
                            'contribution_date' => $contrib_date,
                            'item_label' => $price_set_name,
                            'total' => 0.0,
                            'total_display' => '-',
                            'line_count' => 0,

                        );

                    //sum up some parts

                    $contrib_entry = &$relation['contributions'][$contribution_id];
                    $contrib_entry['total'] += $line_total + $line_tax;
                    $summary_display = CRM_Utils_Money::formatLocaleNumericRoundedForDefaultCurrency(
                        $contrib_entry['total']);
                    $contrib_entry['total_display'] = $summary_display.' '.$currency;
                    $contrib_entry['line_count'] += 1;



                }//foreach ($linerecords as $lineitem)

            }//foreach ($membership['payinfo'] as $contribution_link)

        }//foreach ($member_array['values'] As $membership)


        $this->assign('relations', $this->_relations);
    }

    public function buildQuickForm() {

    // add form elements
    $this->add(
      'select', // field type
      'favorite_color', // field name
      'Favorite Color', // field label
      $this->getColorOptions(), // list of options
      TRUE // is required
    );
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
    $values = $this->exportValues();
    $options = $this->getColorOptions();
    CRM_Core_Session::setStatus(E::ts('You picked color "%1"', array(
      1 => $options[$values['favorite_color']],
    )));
    parent::postProcess();
  }

  public function getColorOptions() {
    $options = array(
      '' => E::ts('- select -'),
      '#f00' => E::ts('Red'),
      '#0f0' => E::ts('Green'),
      '#00f' => E::ts('Blue'),
      '#f0f' => E::ts('Purple'),
    );
    foreach (array('1', '2', '3', '4', '5', '6', '7', '8', '9', 'a', 'b', 'c', 'd', 'e') as $f) {
      $options["#{$f}{$f}{$f}"] = E::ts('Grey (%1)', array(1 => $f));
    }
    return $options;
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
