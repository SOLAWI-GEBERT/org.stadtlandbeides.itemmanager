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
      $member_list = array();
      $error = False;
      $max_time = max(ini_get('max_execution_time'),30);

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


    //compound membership with lineitems
    foreach ($member_array['values'] As $membership)
    {

        $field_data = array();
        $member_count = count($member_array['values']);
        $max_time_member = 0.75 * $max_time / $member_count;
        $time_start = microtime(true);
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

                    $max_field_id = CRM_Itemmanager_Util::getLastPricefieldSuccessor(
                        CRM_Utils_Array::value('id', $lineitem['valuedata']));

                    $line_date = $line_timestamp->format('Y-M');
                    $field_id = CRM_Utils_Array::value('id', $lineitem['fielddata']);
                    $item_quantity = CRM_Utils_Array::value('qty', $lineitem['linedata']);

                    //new stuff
                    if (!array_key_exists($max_field_id, $field_data))
                        $field_data[$max_field_id] = array();
                    $_field = &$field_data[$max_field_id];
                    if (!array_key_exists($item_quantity, $_field)) {
                        $_details = array(
                            'item_quantity' => (string)$item_quantity,
                            'item_label' => CRM_Utils_Array::value('label', $lineitem['linedata']),
                            'item_dates' => array(),
                            'min' => null,
                            'max' => null,
                        );
                        $_field[$item_quantity] = $_details;
                    }
                    $_dates = &$_field[$item_quantity]['item_dates'];
                    $_dates[] = $line_date;
                    $_field[$item_quantity]['min'] = min($_dates);
                    $_field[$item_quantity]['max'] = max($_dates);


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

                $time_end = microtime(true);
                $execution_time = ($time_end - $time_start);
                if($execution_time > $max_time_member)
                    break;

            }//foreach ($linerecords As $lineitem)

            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start);
            if($execution_time > $max_time_member)
                break;

        }

        $member_list[] = array(
            'field_data' => $field_data,
            'member_name' => $membership['typeinfo']['name'],
        );


    }//foreach ($member_array['values'] As $membership)



      $this->assign('member_list',$member_list);
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
