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

        //Later we compound all line items belonging to the contribution
        $item_query = "
        SELECT
            line_item.label As item_label,
            line_item.qty As item_quantity,
            price_field.id As field_id,
            price_field.is_active As item_active,
            price_field.active_on As item_startdate,
            price_field.expire_on As item_enddate,
            price_field.help_pre As item_help,
            contribution.receive_date As contrib_date,
            price_set.id As set_id,
            price_set.name As set_name,
            price_set.is_active As set_active,
            price_set.help_pre As set_help
        FROM civicrm_line_item line_item
             LEFT JOIN civicrm_price_field_value price_field_value ON line_item.price_field_value_id = price_field_value.id
             LEFT JOIN civicrm_price_field price_field ON line_item.price_field_id = price_field.id
             LEFT JOIN civicrm_contribution as contribution ON line_item.contribution_id = contribution.id
             LEFT JOIN civicrm_price_set price_set ON price_field.price_set_id = price_set.id
        WHERE line_item.contribution_id = %1 
        ORDER BY contribution.receipt_date DESC 

     ";



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
                $linecollection = array(
                    'name' =>  CRM_Utils_Array::value('label', $lineitem['linedata']),
                    'last_qty' => CRM_Utils_Array::value('qty', $lineitem['linedata']),
                    'last_price_per_interval' => CRM_Utils_Money::format(
                        CRM_Utils_Array::value('unit_price', $lineitem['linedata']), NULL, NULL, TRUE),
                    'element_item_name' => 'member_'.$membership['memberdata']['id'].'_'.
                        'item_'.CRM_Utils_Array::value('id', $lineitem['fielddata']),
                    'element_quantity_name' => 'member_'.$membership['memberdata']['id'].'_'.
                        'item_'.CRM_Utils_Array::value('id', $lineitem['fielddata']).'_'.
                        'quantity_'.CRM_Utils_Array::value('id', $lineitem['linedata']),
                );
                $linelist[] = $linecollection;
            }

            $form_collection = array(
                'name' => $membership['typeinfo']['name'],
                'start_date' => CRM_Utils_Date::customFormat(date_create($first_date)->format('Y-m-d'),
                    Civi::settings()->get('dateformatshortdate')),
                'last_date' => CRM_Utils_Date::customFormat(date_create($last_date)->format('Y-m-d'),
                    Civi::settings()->get('dateformatshortdate')),
                'line_items' => $linelist,


            );
            $this->assign('linerecord', $linerecords);
            $this->_memberships[] = $form_collection;

        }


        $this->assign('memberrecord', $member_array['values']);
        $this->assign('memberships',$this->_memberships);

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
                $this->getColorOptions(), // list of options
                TRUE, // is required
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
