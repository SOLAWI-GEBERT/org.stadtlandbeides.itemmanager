<?php
use CRM_Itemmanager_ExtensionUtil as E;

class CRM_Itemmanager_Page_UpdateItems extends CRM_Core_Page {

    var $base_list = array();
    var $old_backid = 0;

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('Update Items'));

      //Deklaration
      $reset = CRM_Utils_Request::retrieve('reset','Integer');
      $doupdate = $_POST['items_update'];


      //Initialisierung
      if( isset($reset))
      {
          $base_list = array();
          $old_backid = 0;
      }

      if(!isset($doupdate))
      {
          $this->assign('currentTime', date('Y-m-d H:i:s'));
          $contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');
          $loaded_action = $_REQUEST['action'];
          if($old_backid != $contact_id)
          {
              $base_list = array();
          }


          $old_backid = $contact_id;
          $filter_harmonize = CRM_Utils_Request::retrieve('harm', 'Integer');
          $filter_sync = CRM_Utils_Request::retrieve('sync', 'Integer');
          CRM_Core_Session::setStatus($loaded_action,"DEBUG",'info');
          $this->assign("request",$_REQUEST);
          $this->assign("action",$loaded_action);
          if(isset($loaded_action) and $loaded_action == "update")
            $this->prepareCreateForm($contact_id,$filter_sync,$filter_harmonize);
          else
              $this->prepareCreateForm($contact_id,$filter_sync,$filter_harmonize);

      }
      else
      {

          parent::run();
          return;

      }

      //JUST TEST
      if (isset($_POST['items_update']))
      {

          $this->assign('post', $_POST);

          $message = "Ausgabe";
          if (isset($_POST['viewlist'])){
                foreach ($_POST['viewlist'] as $value)
                {
                    $message += "Wert:";
                    $message += $value."<br>";




           }
        }

          CRM_Core_Session::setStatus($message,"DEBUG",'info');

          parent::run();
          return;
      }






    parent::run();
  }

    function updatePreviewForm($contact_id,$filter_harmonize,$filter_sync,$base_list)
    {
        $this->assign('base_list', $base_list);
        $this->assign("contact_id", $contact_id);
        $this->assign("submit_url", CRM_Utils_System::url('civicrm/items/update'));
        $this->assign("filter_url", CRM_Utils_System::url('civicrm/items/update',"action=preview&cid=$contact_id"));
        $this->assign("filter_sync",$filter_sync);
        $this->assign("filter_harmonize",$filter_harmonize);

    }


    /**
     * Will prepare the form and look up all necessary data
     */
    function prepareCreateForm($contact_id,$filter_harmonize,$filter_sync)
    {

        // first, try to load contact
        $contact = civicrm_api('Contact', 'getsingle', array('version' => 3, 'id' => $contact_id));
        if (isset($contact['is_error']) && $contact['is_error']) {
            CRM_Core_Session::setStatus(sprintf(ts("Couldn't find contact #%s", array('domain' => 'org.stadtlandbeides.itemmanager')),
                $contact_id), ts('Error', array('domain' => 'org.stadtlandbeides.itemmanager')), 'error');
            $this->assign("display_name", "ERROR");
            return;
        }

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
            line_item.unit_price As item_price,
            line_item.line_total As item_total,
            line_item.tax_amount As item_tax,
            line_item.financial_type_id As item_ftype,
            price_field.id As field_id,
            price_field.is_active As item_active,
            price_field.active_on As item_startdate,
            price_field.expire_on As item_enddate,
            price_field.help_pre As item_help,
            price_field.label As field_label,
            price_field_value.amount As field_amount,  
            price_field_value.financial_type_id As field_finance_type,
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

               $periods = CRM_Itemmanager_BAO_ItemmanagerSettings::getFieldValue('CRM_Itemmanager_DAO_ItemmanagerSettings',
                    $line_items->field_id , 'periods','price_field_id',True);
               if(!isset($periods) or $periods == 0) $periods =1 ;

               $change_unit_price = $line_items ->field_amount / $periods;
               $tax = 1.0;
               if($this->isTaxEnabledInFinancialType($line_items->field_finance_type)) $tax = $this->getTaxRateInFinancialType($line_items->field_finance_type);
               $changed_total = $line_items->item_quantity * $change_unit_price;
               $changed_tax = $line_items->item_quantity * $change_unit_price * $tax/100.0;

                $base = array(
                    'line_id'  => $line_items-> line_id,
                    'set_id'        => $line_items->set_id,
                    'field_id'        => $line_items->field_id,
                    'member_name'   => $base_items->member_name,
                    'item_label'    => $line_items->item_label,
                    'item_quantity' => $line_items->item_quantity,
                    'item_price' => $line_items-> item_price,
                    'item_total' => $line_items-> item_total,
                    'item_tax' => $line_items-> item_tax,
                    'periods' => $periods,
                    'contrib_date'  => $line_timestamp,
                    'update_date' => 1,
                    'change_date' => $line_timestamp,
                    'update_label' => 1,
                    'change_label' => $line_items -> field_label,
                    'update_price' => 1,
                    'change_price' => $change_unit_price,
                    'change_total' => $changed_total,
                    'change_tax' => $changed_tax,
                );

                $base_list[] = $base;


            }

        }//while ($base_items->fetch())

        $this->assign('base_list',$base_list);
        $this->assign("date", date('Y-m-d'));
        $this->assign("start_date", date('Y-m-d'));
        $this->assign("contact_id", $contact_id);
        $this->assign("display_name", $contact['display_name']);
        $this->assign("submit_url", CRM_Utils_System::url('civicrm/items/update'));
        $this->assign("filter_url", CRM_Utils_System::url('civicrm/items/update',"action=preview&cid=$contact_id"));
        $this->assign("filter_sync",$filter_sync);
        $this->assign("filter_harmonize",$filter_harmonize);
    }


    /**
     * Check if there is tax value for selected financial type.
     * @param $financialTypeId
     * @return bool
     */
    private function isTaxEnabledInFinancialType($financialTypeId) {
        $taxRates = CRM_Core_PseudoConstant::getTaxRates();
        return (isset($taxRates[$financialTypeId])) ? TRUE : FALSE;
    }

    /**
     * get tax value for selected financial type.
     * @param $financialTypeId
     * @return Float
     */
    private function getTaxRateInFinancialType($financialTypeId) {
        $taxRates = CRM_Core_PseudoConstant::getTaxRates();
        return $taxRates[$financialTypeId];
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
            $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}&selectedChild=itemmanager");
            CRM_Utils_System::redirect($contact_url);
        }
    }

    protected function processInfo($status, $title, $message, $contact_id) {
        CRM_Core_Session::setStatus($status . "<br/>" . $message, ts('Info', array('domain' => 'org.stadtlandbeides.itemmanager')), 'info');
        $this->assign("error_title",   $title);
        $this->assign("error_message", $message);

        if (!$this->isPopup()) {
            $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}&selectedChild=contribute");
            CRM_Utils_System::redirect($contact_url);
        }
    }


}
