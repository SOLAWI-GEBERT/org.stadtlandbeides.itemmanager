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
    $base_query = "
        SELECT
            line_item.label AS item_label,
            member_type.name AS member_name
            FROM civicrm_line_item line_item
                LEFT JOIN civicrm_price_field_value price_field_value ON line_item.price_field_value_id = price_field_value.id
                LEFT JOIN civicrm_price_field price_field ON line_item.price_field_id = price_field.id
                LEFT JOIN civicrm_membership membership ON line_item.entity_id = membership.id AND membership.contact_id = %1
                LEFT JOIN civicrm_membership_type member_type ON member_type.id = membership.membership_type_id
                LEFT JOIN civicrm_membership_payment member_pay ON member_pay.membership_id = membership.id
                LEFT JOIN civicrm_contact civicrm_contact ON membership.contact_id = civicrm_contact.id AND civicrm_contact.id = %1
                LEFT JOIN civicrm_contribution as contribution ON line_item.contribution_id = contribution.id and contribution.contact_id = %1 and member_pay.contribution_id = contribution.id 
                ";


      /*SELECT
      *
      FROM civicrm_membership membership
                LEFT JOIN civicrm_line_item line_item ON line_item.entity_id = membership.id AND line_item.entity_table = 'civicrm_membership'
                LEFT JOIN civicrm_contact civicrm_contact ON membership.contact_id = civicrm_contact.id AND civicrm_contact.id = 3
                LEFT JOIN civicrm_contribution as contribution ON line_item.contribution_id = contribution.id
                LEFT JOIN civicrm_price_field_value price_field_value ON line_item.price_field_value_id = price_field_value.id
                LEFT JOIN civicrm_price_field price_field ON line_item.price_field_id = price_field.id
                WHERE membership.contact_id = 3 and civicrm_membership
ORDER BY `civicrm_contact`.`sort_name`  DESC

      SELECT
            *
            FROM civicrm_membership membership
            LEFT JOIN civicrm_contact civicrm_contact ON membership.contact_id = civicrm_contact.id AND civicrm_contact.id = 3
            LEFT JOIN civicrm_contribution as contribution ON contribution.
            WHERE membership.contact_id = 3


      */


    $base_items = CRM_Core_DAO::executeQuery($base_query,
          array( 1 => array($contact_id, 'Integer')));

    while ($base_items->fetch()) {
      $base = array(
          'item_label'  => $base_items->item_label,
          'member_name' => $base_items->member_name,
      );

      $base_list[] = $base;

    }
    $this->assign('item_bases', $base_list);

    parent::run();
  }

}
