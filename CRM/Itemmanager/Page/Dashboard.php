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

    // Example: Assign a variable for use in a template
    $this->assign('currentTime', date('Y-m-d H:i:s'));
    $contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');
    $this->assign('contact_id', $contact_id);




    $base_list = array();
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
            price_field.is_active As item_active,
            price_field.active_on As item_startdate,
            price_field.expire_on As item_enddate,
            price_field.help_pre As item_help,
            price_set.name As set_name,
            price_set.is_active As set_active,
            price_set.help_pre As set_help
        FROM civicrm_line_item line_item
             LEFT JOIN civicrm_price_field_value price_field_value ON line_item.price_field_value_id = price_field_value.id
             LEFT JOIN civicrm_price_field price_field ON line_item.price_field_id = price_field.id
             LEFT JOIN civicrm_price_set price_set ON price_field.price_set_id = price_set.id
        WHERE line_item.contribution_id = %1
     ";


    $base_items = CRM_Core_DAO::executeQuery($base_query,
          array( 1 => array($contact_id, 'Integer')));

    //compound both queries together
    while ($base_items->fetch()) {
        $line_items = CRM_Core_DAO::executeQuery($item_query,
            array( 1 => array($base_items->contrib_id, 'Integer')));

        while ($line_items->fetch()) {

            $base = array(
                'member_name' => $base_items->member_name,
                'item_label'  => $line_items->item_label,
                'item_quantity' => $line_items->item_quantity,
            );

            $base_list[] = $base;

        }



    }
    $this->assign('item_bases', $base_list);

    parent::run();
  }

}
