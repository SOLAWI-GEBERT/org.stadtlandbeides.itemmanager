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
          $this->assign("request",$_REQUEST);
          $this->assign("action",$loaded_action);
          if(isset($loaded_action) and $loaded_action == "update")
            $this->prepareCreateForm($contact_id,$filter_sync,$filter_harmonize);
          else
              $this->prepareCreateForm($contact_id,$filter_sync,$filter_harmonize);

      }
      else
      {

          $filter_sync = $_POST['filter_sync'];
          $filter_harmonize = $_POST['filter_harmonize'];
          $contact_id = $_POST['contact_id'];
          $check_list = $_POST['viewlist'];
          if(isset($check_list))
            $this->updateData($contact_id,$filter_harmonize,$filter_sync,$check_list);
          parent::run();

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
            price_field_value.label As field_label,
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
                $line_date = $line_timestamp->format('Y-m-d H:i:s');


               $periods = CRM_Itemmanager_BAO_ItemmanagerSettings::getFieldValue('CRM_Itemmanager_DAO_ItemmanagerSettings',
                    $line_items->field_id , 'periods','price_field_id',True);
               if(!isset($periods) or $periods == 0) $periods =1 ;

                $change_timestamp = CRM_Itemmanager_BAO_ItemmanagerSettings::getFieldValue('CRM_Itemmanager_DAO_ItemmanagerSettings',
                    $line_items->field_id , 'period_start_on','price_field_id',True);
                if(!isset($change_timestamp)) $change_timestamp = $line_items->contrib_date;

                //extract start date from month
                $raw_date = date_create($change_timestamp);
                $new_date = new DateTime($line_timestamp->format('Y-m') . $raw_date->format('-d'));
                $new_date->setTime(0,0);

                $changed_date = $new_date->format('Y-m-d H:i:s');


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
                    'item_price' => round($line_items-> item_price, 2),
                    'item_total' => round($line_items-> item_total,2),
                    'item_tax' => round($line_items-> item_tax,2),
                    'periods' => $periods,
                    'contrib_date'  => $line_date,
                    'update_date' => $line_date != $changed_date and $filter_harmonize == 1,
                    'change_date' => $changed_date,
                    'update_label' => $line_items->item_label != $line_items -> field_label,
                    'change_label' => $line_items -> field_label,
                    'update_price' => round($line_items-> item_price, 2) != round($change_unit_price, 2)
                                            and $filter_sync == 1,
                    'change_price' => round($change_unit_price,2),
                    'change_total' => round($changed_total,2),
                    'change_tax' => round($changed_tax,2),
                );

                //just pickup items to be updated
                if($base['update_date'] or $base['update_label'] or $base['update_price']) $base_list[] = $base;


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


    function updateData($contact_id,$filter_harmonize,$filter_sync,$selected_items)
    {

        //Deklaration
        $error = False;
        $error_msg = "";
        $contribution_table = CRM_Contribute_DAO_Contribution::getTableName();
        $line_item_table = CRM_Price_DAO_LineItem::getTableName();
        $update_date_query = "
                UPDATE
                    ". $contribution_table ."
                SET receive_date = %1
                WHERE id = %2
                ";

        $update_label_query = "
                UPDATE
                    ". $line_item_table ."
                SET label = %1
                WHERE id = %2
                ";

        // first, try to load contact
        $contact = civicrm_api('Contact', 'getsingle', array('version' => 3, 'id' => $contact_id));
        if (isset($contact['is_error']) && $contact['is_error']) {
            CRM_Core_Session::setStatus(sprintf(ts("Couldn't find contact #%s", array('domain' => 'org.stadtlandbeides.itemmanager')),
                $contact_id), ts('Error', array('domain' => 'org.stadtlandbeides.itemmanager')), 'error');
            $this->assign("display_name", "ERROR");
            return;
        }

        foreach ($selected_items As $line_item)
        {
            $update_contribution = False;
            $update_label = False;
            //get all nested data
            $lineitemInfo = civicrm_api3('lineItem', 'getsingle', array('id' => (int) $line_item));
            if(!isset($lineitemInfo)) continue;
            $priceFieldInfo = civicrm_api3('PriceField', 'getsingle', array('id' => (int) $lineitemInfo['price_field_id']));
            if(!isset($priceFieldInfo)) continue;
            $priceFieldValueInfo = civicrm_api3('PriceFieldValue', 'getsingle', array('id' => (int) $lineitemInfo['price_field_value_id']));
            if(!isset($priceFieldValueInfo)) continue;
            $contributionInfo = civicrm_api3('Contribution', 'getsingle', array('id' => (int) $lineitemInfo['contribution_id']));
            if(!isset($contributionInfo)) continue;

            //update the data
            $line_timestamp = date_create($contributionInfo['receive_date']);
            $line_date = $line_timestamp->format('Y-m-d H:i:s');


            $periods = CRM_Itemmanager_BAO_ItemmanagerSettings::getFieldValue('CRM_Itemmanager_DAO_ItemmanagerSettings',
                (int) $lineitemInfo['price_field_id'] , 'periods','price_field_id',True);
            if(!isset($periods) or $periods == 0) $periods =1 ;

            $change_timestamp = CRM_Itemmanager_BAO_ItemmanagerSettings::getFieldValue('CRM_Itemmanager_DAO_ItemmanagerSettings',
                (int) $lineitemInfo['price_field_id'] , 'period_start_on','price_field_id',True);
            if(!isset($change_timestamp)) $change_timestamp = $contributionInfo['receive_date'];

            //extract start date from month
            $raw_date = date_create($change_timestamp);
            $new_date = new DateTime($line_timestamp->format('Y-m') . $raw_date->format('-d'));
            $new_date->setTime(0,0);

            $changed_date = $new_date->format('Y-m-d H:i:s');


            $change_unit_price = $priceFieldValueInfo['amount'] / $periods;
            $tax = 1.0;
            if($this->isTaxEnabledInFinancialType((int) $priceFieldValueInfo['financial_type_id'])) $tax = $this->getTaxRateInFinancialType((int) $priceFieldValueInfo['financial_type_id']);
            $changed_total = $lineitemInfo['qty'] * $change_unit_price;
            $changed_tax = $lineitemInfo['qty'] * $change_unit_price * $tax/100.0;


            if($line_date != $changed_date and $filter_harmonize == 1)
            {
                $contributionInfo['receive_date'] =  $changed_date;
                $update_contribution = True;
            }
            //change label
            if($lineitemInfo['label'] != $priceFieldValueInfo['label'])
            {
                $update_label = True;
                $lineitemInfo['label'] = $priceFieldValueInfo['label'];
            }


            //change price data
            if(round($lineitemInfo['unit_price'], 2) != round($change_unit_price, 2)
                and $filter_sync == 1)
            {
                $lineitemInfo['unit_price'] = round($change_unit_price,2);
                $lineitemInfo['line_total'] = round($changed_total,2);
                $lineitemInfo['tax_amount'] = round($changed_tax,2);
            }


            //Update Contribution
            if($update_contribution)
            {
                try{
                    $finalquery = CRM_Core_DAO::composeQuery($update_date_query,
                        array( 1 => array($contributionInfo['receive_date'], 'String'),
                            2 => array((int) $lineitemInfo['contribution_id'], 'Integer')));
                    $this->assign("finalquery", $finalquery);
                    CRM_Core_DAO::executeUnbufferedQuery($finalquery);

                }
                catch(exception $e){

                    $error_msg .= E::ts("Error for updating contribution") . $lineitemInfo['contribution_id'] . "<br/>";
                }

            }

            if($update_label)
            {
                try{
                    $finalquery = CRM_Core_DAO::composeQuery($update_label_query,
                        array( 1 => array($lineitemInfo['label'], 'String'),
                            2 => array((int) (int) $line_item, 'Integer')));
                    $this->assign("finalquery", $finalquery);
                    CRM_Core_DAO::executeUnbufferedQuery($finalquery);

                }
                catch(exception $e){

                    $error_msg .= E::ts("Error for label for ") . $line_item . "<br/>";
                }
            }

        }

        if($error)
        {
            $this->processError("ERROR",E::ts('Update Items'),$error_msg,$contact_id);
            return;
        }


        $this->processSuccess(E::ts('Items updated'),$contact_id);


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

    protected function processSuccess($message, $contact_id) {
        CRM_Core_Session::setStatus($message, ts('Success', array('domain' => 'org.stadtlandbeides.itemmanager')), 'success');
        if (!$this->isPopup()) {
            $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}&selectedChild=contribute");
            CRM_Utils_System::redirect($contact_url);
        }
    }


}
