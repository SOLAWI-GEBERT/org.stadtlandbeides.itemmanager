<?php
/*
 * ------------------------------------------------------------+
 * | stadt, land, beides - CSA Support                                       |
 * | Copyright (C) 2021 Stadt, Land, Beides                               |
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

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Items Dashboard'));

    //Deklaration
      $base_list = array();
      $collect_list = array();
      $field_list = array();
      $group_sets = array();
      $group_dates = array();
      $old_set = 0;
      $old_date = "";

    // Example: Assign a variable for use in a template
    $this->assign('currentTime', date('Y-m-d H:i:s'));
    $contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');
    $this->assign('contact_id', $contact_id);





    //Here we just collect the memberships
    $base_query = "
        SELECT
            member_type.name AS member_name,
            contribution.id AS contrib_id,
            membership.start_date as member_start,
            membership.end_date as member_end,
            membership.status_id as member_status
        FROM civicrm_membership membership
         LEFT JOIN civicrm_membership_payment member_pay ON member_pay.membership_id = membership.id
         LEFT JOIN civicrm_contribution as contribution ON contribution.contact_id = %1 and member_pay.contribution_id = contribution.id
         LEFT JOIN civicrm_membership_type member_type ON member_type.id = membership.membership_type_id
        WHERE membership.contact_id = %1
                ";

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
        ORDER BY contribution.receipt_date ASC

     ";


    $base_items = CRM_Core_DAO::executeQuery($base_query,
          array( 1 => array($contact_id, 'Integer')));

    //compound both queries together
    while ($base_items->fetch()) {
        $line_items = CRM_Core_DAO::executeQuery($item_query,
            array( 1 => array($base_items->contrib_id, 'Integer')));

        while ($line_items->fetch()) {

            $line_timestamp = date_create($line_items->contrib_date);
            $line_date = $line_timestamp->format('Y-M');
            $newdate = $line_date != $old_date;
            $old_date = $line_date;

            $base = array(
                'set_id'        => $line_items->set_id,
                'field_id'        => $line_items->field_id,
                'member_name'   => $base_items->member_name,
                'item_label'    => $line_items->item_label,
                'item_quantity' => $line_items->item_quantity,
                'contrib_date'  => $line_timestamp,
            );

            $base_list[] = $base;
            $collect_list[] = $base;

            if($newdate)
            {
                $date_item = array(
                    'group_date' => $line_date,
                    'group_data' => $collect_list,
                );

                $collect_list = array();
                $group_dates[] = $date_item;
            }
        }



    }//while ($base_items->fetch())

      $test = array();
    //dig into Date Groups and find different sets
    foreach ($group_dates As $date_set)
    {

        foreach ($date_set['group_data'] as $group_data)
        {
            $test[] = $group_data;
            //Trigger new sets
            $set = $group_data['set_id'];
            $new_set = $set != $old_set;
            $old_set = $set;

            $field = array(
                'id' => $group_data['field_id'],
                'quantity' => $group_data['item_quantity'],
            );

            $field_list[] = $field;

            if($new_set)
            {
                $current_set = array(
                    'set' => $set,
                    'fields' => $field_list,
                );

                $field_list = array();
                $group_sets[] = $current_set;
            }




        }

    }

    $this->assign('group_bases',$group_dates);
    $this->assign('item_bases', $base_list);
    $this->assign('test_items', $group_sets);

    parent::run();
  }

}
