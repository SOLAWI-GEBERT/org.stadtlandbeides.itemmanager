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
            $this->prepareCreateForm($contact_id,$filter_harmonize,$filter_sync);
          else
              $this->prepareCreateForm($contact_id,$filter_harmonize,$filter_sync);

          parent::run();

      }
      else
      {

          $filter_sync = $_POST['filter_sync'];
          $filter_harmonize = $_POST['filter_harmonize'];
          $contact_id = $_POST['contact_id'];
          $check_list = $_POST['viewlist'];
          $this->assign("update_done",  1);
          if(isset($check_list))
            $this->updateData($contact_id,$filter_harmonize,$filter_sync,$check_list);
          parent::run();
      }

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
            line_item.price_field_value_id as field_value_id,
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
                $change_timestamp = $line_items->contrib_date;
                $periods = 1;

                try {

                    $manager_item = new CRM_Itemmanager_BAO_ItemmanagerSettings();
                    $valid=$manager_item->get('price_field_value_id',$line_items->field_value_id);

                    $period_item = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
                    $valid=$period_item->get('id',$manager_item->itemmanager_periods_id);


                    $periods = $period_item->periods;
                    if (!$valid or $periods == 0) $periods = 1;

                    $change_timestamp = $period_item->period_start_on;
                    if (!$valid) $change_timestamp = $line_items->contrib_date;


                } catch (\Civi\API\Exception\UnauthorizedException $e) {
                } catch (API_Exception $e) {

                } catch (CRM_Core_Exception $e) {
                }

                //extract start date from month
                $raw_date = date_create($change_timestamp);
                $new_date = new DateTime($line_timestamp->format('Y-m') . $raw_date->format('-d'));
                $new_date->setTime(0,0);

                $changed_date = $new_date->format('Y-m-d H:i:s');


               $change_unit_price = $line_items ->field_amount / $periods;
               $tax = 1.0;
               if(CRM_Itemmanager_Util::isTaxEnabledInFinancialType($line_items->field_finance_type))
                   $tax = CRM_Itemmanager_Util::getTaxRateInFinancialType($line_items->field_finance_type);
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


    /**
     * Updates all related tables according to the line item
     *
     * @param $contact_id
     * @param $filter_harmonize
     * @param $filter_sync
     * @param $selected_items
     * @throws CRM_Core_Exception
     * @throws CiviCRM_API3_Exception
     */
    function updateData($contact_id,$filter_harmonize,$filter_sync,$selected_items)
    {

        //Deklaration
        $error = False;
        $error_msg = "";
        $contribution_table = CRM_Contribute_DAO_Contribution::getTableName();
        $line_item_table = CRM_Price_DAO_LineItem::getTableName();
        $financial_item_table = CRM_Financial_DAO_FinancialItem::getTableName();
        $financial_transaktion_table = CRM_Financial_DAO_EntityFinancialTrxn::getTableName();

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

        $update_financial_item ="
                UPDATE
                    ". $financial_item_table ."
                SET amount = %1
                WHERE id = %2
                ";
        $update_contribution_query = "
                UPDATE
                    ". $contribution_table ."
                SET total_amount = %1,
                    net_amount = %1
                WHERE id = %2
        
        ";

        $update_lineitem_query = "
                UPDATE
                    ". $line_item_table ."
                SET unit_price = %1,
                    line_total = %2,
                    tax_amount = %3
                WHERE id = %4
        
        ";

        $update_financial_transaktion_query = "

                UPDATE
                    ". $financial_transaktion_table ."
                SET amount = %1
                    
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
            $update_price = False;
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

            //get itemmanager added informations

            $manager_item = new CRM_Itemmanager_BAO_ItemmanagerSettings();
            $valid=$manager_item->get('price_field_value_id',(int) $lineitemInfo['price_field_value_id']);

            $period_item = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
            $valid = $period_item->get('id',$manager_item->itemmanager_periods_id);


            $periods = (int)$period_item->periods;
            if (!$valid or $periods == 0) $periods = 1;

            $change_timestamp = $period_item->period_start_on;
            if (!$valid)  $change_timestamp = $contributionInfo['receive_date'];

            //extract start date from month
            $raw_date = date_create($change_timestamp);
            $new_date = new DateTime($line_timestamp->format('Y-m') . $raw_date->format('-d'));
            $new_date->setTime(0,0);

            $changed_date = $new_date->format('Y-m-d H:i:s');



            $change_unit_price = $priceFieldValueInfo['amount'] / $periods;
            $tax = 0.0;
            if(CRM_Itemmanager_Util::isTaxEnabledInFinancialType((int) $priceFieldValueInfo['financial_type_id']))
                $tax = CRM_Itemmanager_Util::getTaxRateInFinancialType((int) $priceFieldValueInfo['financial_type_id']);
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
                $update_price = True;

                $lineitemInfo['unit_price'] = CRM_Utils_Money::format($change_unit_price, NULL, NULL, TRUE);
                $lineitemInfo['line_total'] = CRM_Utils_Money::format($changed_total, NULL, NULL, TRUE);
                $lineitemInfo['tax_amount'] = CRM_Utils_Money::format($changed_tax, NULL, NULL, TRUE);

                //update lineitem
                try{
                    $finalquery = CRM_Core_DAO::composeQuery($update_lineitem_query,
                        array( 1 => array($lineitemInfo['unit_price'], 'Float'),
                            2 => array($lineitemInfo['line_total'], 'Float'),
                            3 => array($lineitemInfo['tax_amount'], 'Float'),
                            4 => array((int) $line_item, 'Integer')));
                    $this->assign("finalquery", $finalquery);
                    CRM_Core_DAO::executeUnbufferedQuery($finalquery);

                }
                catch(exception $e){

                    $error_msg .= E::ts("Error for updating line items") . $line_item . "<br/>";
                }


                //get financial relations
                $financeitems = CRM_Itemmanager_Util::getFinancialFullRecordsByLineItemId((int) $line_item);
                if($financeitems['is_error'])
                {
                    $this->processError("ERROR",E::ts('Retrieve financial infos'),$financeitems['error_message'],$contact_id);
                    return;
                }

                //Update financial items
                foreach ($financeitems['values'] As $financeitem)
                {
                    try {
                        //decide tax or not
                        $financeitem['financeitem']['amount'] = $financeitem['accountinfo']['is_tax'] ?
                            CRM_Utils_Money::format($changed_tax, NULL, NULL, TRUE) : CRM_Utils_Money::format($changed_total, NULL, NULL, TRUE);

                        $finalquery = CRM_Core_DAO::composeQuery($update_financial_item,
                            array(1 => array($financeitem['financeitem']['amount'], 'Float'),
                                2 => array((int)$financeitem['financeitem']['id'], 'Integer')));

                        CRM_Core_DAO::executeUnbufferedQuery($finalquery);

                        //update related transaktion
                        $transaction = CRM_Itemmanager_Util::getFinancialEntityTrxnByFinancialItemId((int)$financeitem['financeitem']['id']);
                        $finalquery = CRM_Core_DAO::composeQuery($update_financial_transaktion_query,
                            array(1 => array($financeitem['financeitem']['amount'], 'Float'),
                                2 => array((int)$transaction['id'], 'Integer')));

                        CRM_Core_DAO::executeUnbufferedQuery($finalquery);

                    }
                    catch(exception $e){

                        $error_msg .= E::ts("Error for updating financeitem") . $financeitem['financeitem']['id'] . "<br/>";
                    }
                }

                //Update contribution
                $tax_total = CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID((int) $lineitemInfo['contribution_id']);
                $total = CRM_Itemmanager_Util::getAmountTotalFromContributionID((int) $lineitemInfo['contribution_id']);

                if(isset($contributionInfo['contribution_recur_id']))
                    civicrm_api3('ContributionRecur', 'create', [
                        'id' => (int)$contributionInfo['contribution_recur_id'],
                        'amount' => $this->$total,
                    ]);

                try{
                    $finalquery = CRM_Core_DAO::composeQuery($update_contribution_query,
                        array( 1 => array($tax_total + $total, 'Float'),
                            2 => array((int) $lineitemInfo['contribution_id'], 'Integer')));
                    $this->assign("finalquery", $finalquery);
                    CRM_Core_DAO::executeUnbufferedQuery($finalquery);

                    //update related transaktion
                    $transaction = CRM_Itemmanager_Util::getFinancialEntityIdTrxnByContributionId((int) $lineitemInfo['contribution_id']);
                    $finalquery = CRM_Core_DAO::composeQuery($update_financial_transaktion_query,
                        array(1 => array($tax_total + $total, 'Float'),
                            2 => array((int)$transaction['id'], 'Integer')));

                    CRM_Core_DAO::executeUnbufferedQuery($finalquery);

                }
                catch(exception $e){

                    $error_msg .= E::ts("Error for updating contribution") . $lineitemInfo['contribution_id'] . "<br/>";
                }


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

        if($update_label or $update_contribution or $update_price)
            $this->processSuccess(E::ts('Items updated'),$contact_id);
        else
            $this->processInfo(E::ts('Nothing to be done'),$contact_id);



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

        $this->assign("destroy",  True);
        $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}&selectedChild=itemmanager");
        CRM_Utils_System::redirect($contact_url);

    }

    protected function processSuccess($message, $contact_id) {
        CRM_Core_Session::setStatus($message, ts('Success', array('domain' => 'org.stadtlandbeides.itemmanager')), 'success');
        $this->assign("destroy",  True);
        $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}&selectedChild=itemmanager");


    }

    protected function processInfo($message, $contact_id) {
        CRM_Core_Session::setStatus($message, ts('Info', array('domain' => 'org.stadtlandbeides.itemmanager')), 'info');

        $contact_url = CRM_Utils_System::url('civicrm/contact/view', "reset=1&cid={$contact_id}&selectedChild=itemmanager");
        CRM_Utils_System::redirect($contact_url);

    }


}
