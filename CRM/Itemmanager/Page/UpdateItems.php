<?php
use CRM_Itemmanager_ExtensionUtil as E;

class CRM_Itemmanager_Page_UpdateItems extends CRM_Core_Page {

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('UpdateItems'));

      //Deklaration
      $base_list = array();
      $status ='open';
      $collect_list = array();
      $field_list = array();
      $group_sets = array();
      $group_dates = array();
      $old_set = -1;
      $old_field = -1;
      $old_date = "";


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
            line_item.id As line_id,
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

              $base = array(
                  'line_id'  => $line_items-> line_id,
                  'set_id'        => $line_items->set_id,
                  'field_id'        => $line_items->field_id,
                  'member_name'   => $base_items->member_name,
                  'item_label'    => $line_items->item_label,
                  'item_quantity' => $line_items->item_quantity,
                  'contrib_date'  => $line_timestamp,
              );

              $base_list[] = $base;


          }

      }//while ($base_items->fetch())

      $this->assign('base_list',$base_list);
      $this->assign("status", $status);
      $this->prepareCreateForm($_REQUEST['cid']);
    parent::run();
  }


    /**
     * Will prepare the form and look up all necessary data
     */
    function prepareCreateForm($contact_id)
    {
        // load financial types

        $this->assign("date", date('Y-m-d'));
        $this->assign("start_date", date('Y-m-d'));


        // first, try to load contact
        $contact = civicrm_api('Contact', 'getsingle', array('version' => 3, 'id' => $contact_id));
        if (isset($contact['is_error']) && $contact['is_error']) {
            CRM_Core_Session::setStatus(sprintf(ts("Couldn't find contact #%s", array('domain' => 'org.stadtlandbeides.itemmanager')), $cid), ts('Error', array('domain' => 'org.stadtlandbeides.itemmanager')), 'error');
            $this->assign("display_name", "ERROR");
            return;
        }

        $this->assign("contact_id", $contact_id);
        $this->assign("display_name", $contact['display_name']);

        // all seems to be ok.
        $this->assign("submit_url", CRM_Utils_System::url('civicrm/items/update'));
    }

    /**
     * test if this page is called as a popup
     */
    protected function isPopup() {
        return CRM_Utils_Array::value('snippet', $_REQUEST);
    }

    /**
     * report error data
     */
    protected function processError($status, $title, $message, $contact_id) {
        CRM_Core_Session::setStatus($status . "<br/>" . $message, ts('Error', array('domain' => 'org.stadtlandbeides.itemmanager')), 'error');
        $this->assign("error_title",   $title);
        $this->assign("error_message", $message);

        if (!$this->isPopup()) {
            $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}&selectedChild=contribute");
            CRM_Utils_System::redirect($contact_url);
        }
    }


}
