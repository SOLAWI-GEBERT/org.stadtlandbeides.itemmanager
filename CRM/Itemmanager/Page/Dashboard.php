<?php
/*
 * ------------------------------------------------------------+
 * | stadt, land, beides - CSA Support                                       |
 * | Copyright (C) 2022 Stadt, Land, Beides                               |
 * | Author: A. Gebert (webmaster -at- stadt-land-beides.de)  |
 * | https://stadt-land-beides.de/                                              |
 * +-------------------------------------------------------------+
 * | This program is released as free software under the          |
 * | Affero GPL license. You can redistribute it and/or               |
 * | modify it under the terms of this license which you            |
 * | can read by viewing the included agpl.txt or online             |
 * | at www.gnu.org/licenses/agpl.html. Removal of this            |
 * | copyright header is strictly prohibited without                    |
 * | written permission from the original author(s).                   |
 * +-------------------------------------------------------------
 */

use CRM_Itemmanager_ExtensionUtil as E;

class CRM_Itemmanager_Page_Dashboard extends CRM_Core_Page {

    //results for array1 (when it is in more, it is in array1 and not in array2. same for less)
    public static function compare_multi_Arrays($array1, $array2){

        foreach($array1 as $k => $v) {
            if(!array_key_exists($k,$array2))
                return False;

            if(!is_array($v) && !is_array($array2[$k]))
                if($v !== $array2[$k])
                    return False;

            if(is_array($v) && is_array($array2[$k]))
                if(!self::compare_multi_Arrays($v, $array2[$k]))
                    return False;
        }
        foreach($array2 as $k => $v)
            if(!array_key_exists($k,$array1))
                return False;

        return True;
    }

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Items Dashboard'));

    //Deklaration
      $base_list = array();
      $collect_list = array();
      $field_list = array();
      $group_sets = array();
      $group_dates = array();
      $old_set = -1;
      $old_field = -1;
      $old_date = "";
      $error = False;


      $this->assign('currentTime', date('Y-m-d H:i:s'));
      $this->_contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');
      $this->assign('contact_id', $this->_contact_id);





      // Get the given memberships
      $member_array = CRM_Itemmanager_Util::getLastMemberShipsFullRecordByContactId($this->_contact_id);

      if($member_array['is_error'])
      {
          $error = True;
          $this->assign('data_error',$error);
          $this->processError("ERROR",E::ts('Retrieve memberships'),$member_array['error_message']);
          return;
      }

      //get our itemsettings
      $item_settings = new CRM_Itemmanager_BAO_ItemmanagerSettings();

    //compound membership with lineitems
    foreach ($member_array['values'] As $membership)
    {
        //dig into details of a membership
        foreach ($membership['payinfo'] As $contribution_link)
        {

            //get the line items to the last contribution
            $linerecords = CRM_Itemmanager_Util::getLineitemFullRecordByContributionId((int)$contribution_link['contribution_id']);
            if($linerecords['is_error'])
            {
                $error = True;
                $this->assign('data_error',$error);
                $this->processError("ERROR",E::ts('Retrieve line items'),
                    $linerecords['error_message']);
                $this->processDetail($membership['typeinfo']['name'],
                    (int)$contribution_link['contribution_id']);
                return;
            }

            $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => (int)$contribution_link['contribution_id']));
            $contrib_date = CRM_Utils_Array::value('receive_date', $contribution);
            $line_timestamp = date_create($contrib_date);

            foreach ($linerecords As $lineitem) {


                try {

                    $valid=$item_settings->get('price_field_value_id',
                        CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata']));

                    //if(!$valid) continue;

                    //$choices = CRM_Itemmanager_Util::getChoicesOfPricefieldsByFieldID(
                    //    CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata']),$last_date);


                    $line_date = $line_timestamp->format('Y-M');
                    $newdate = $line_date != $old_date;
                    $old_date = $line_date;
                    $base = array(
                        'set_id' => CRM_Utils_Array::value('id', $lineitem['setdata']),
                        'field_id' => CRM_Utils_Array::value('id', $lineitem['fielddata']),
                        'member_name' => $membership['typeinfo']['name'],
                        'item_label' => CRM_Utils_Array::value('label', $lineitem['linedata']),
                        'item_quantity' => CRM_Utils_Array::value('qty', $lineitem['linedata']),
                        'contrib_date' => $line_timestamp,
                      //  'field_choices' => $choices['field_value_selection'],
                      //  'set_choices' => $choices['price_set_selection'],
                    );
                    $base_list[] = $base;
                    $collect_list[] = $base;
                    if ($newdate) {
                        $date_item = array(
                            'group_date' => $line_date,
                            'group_data' => $collect_list,
                        );

                        $collect_list = array();
                        $group_dates[] = $date_item;
                    }
                } catch (Exception $e) {

                    $error = True;
                    $this->assign('data_error',$error);
                    $this->processError("ERROR",E::ts('Combine line items'),
                        $e->getMessage());
                    $this->processDetail($membership['typeinfo']['name'],
                        (int)$contribution_link['contribution_id'],
                        CRM_Utils_Array::value('label', $lineitem['linedata']));
                    return;
                }

            }//foreach ($linerecords As $lineitem)
        }


    }//foreach ($member_array['values'] As $membership)


      $current_sets = array();
      $current_index = 0;
      $group_sets = array();
      $_group_sets = array();
      $current_datelist = array();

    //dig into Date Groups and find different sets
    foreach ($group_dates As $date_set)
    {

        foreach ($date_set['group_data'] as $group_data)
        {

            try {
                $current_datelist[] = $group_data['contrib_date'];
                if (!array_key_exists($group_data['set_id'], $_group_sets))
                    $_group_sets[$group_data['set_id']] = array();
                $_group_set = &$_group_sets[$group_data['set_id']];
                if (!array_key_exists($group_data['field_id'], $_group_set)) {
                    $_details = array(
                        'item_quantity' => (string)$group_data['item_quantity'],
                        'item_label' => (string)$group_data['item_label'],
                        'member_name' => (string)$group_data['member_name'],
                    );
                    $_group_set[$group_data['field_id']] = $_details;

                }
            } catch (Exception $e) {

                $error = True;
                $this->assign('data_error',$error);
                $this->processError("ERROR",E::ts('Find different sets'),
                    $e->getMessage());
                $this->processDetail((string)$group_data['member_name'],
                    $group_data['contrib_date'],
                    (string)$group_data['item_label']);
                return;
            }


        }//foreach ($date_set['group_data'] as $group_data)

        try {
            $diffresult = self::compare_multi_Arrays($current_sets, $_group_sets);
            $current_sets = $_group_sets;
            if (!$diffresult) {
                if (!array_key_exists($current_index, $group_sets)) {
                    $details = array();
                    foreach ($_group_sets as $set)
                        foreach ($set as $fields)
                            $details[] = $fields;

                    $group_sets[$current_index]['list'] = $details;
                    $group_sets[$current_index]['raw'] = $_group_sets;
                    $group_sets[$current_index]['date_max'] = max($current_datelist)->format('Y-M');
                    $group_sets[$current_index]['date_min'] = min($current_datelist)->format('Y-M');
                }

                $current_index += 1;
                $_group_sets = array();
                $current_datelist = array();
            }
        } catch (Exception $e) {

            $error = True;
            $this->assign('data_error',$error);
            $this->processError("ERROR",E::ts('Split datasets'),
                $e->getMessage());
            return;

        }

    }//foreach ($group_dates As $date_set)

    $this->assign('group_sets',$group_sets);
    $this->assign("group_refresh", CRM_Utils_System::url('civicrm/items/tab', "reset=1&force=1&cid={$this->_contact_id}"));
    $this->assign('data_error',$error);

    parent::run();
  }


    /**
     * report error data
     */
    protected function processError($status, $title, $message) {
        CRM_Core_Session::setStatus($status . "<br/>" . $message, ts('Error', array('domain' => 'org.stadtlandbeides.itemmanager')), 'error');
        $this->assign("error_title",   $title);
        $this->assign("error_message", $message);


    }

    //some details
    protected function processDetail($membership, $contribution, $lineitem = null) {
        $this->assign("detail_member",   $membership);
        $this->assign("detail_contribution", $contribution);
        $this->assign("detail_lineitem", $lineitem);


    }

}
