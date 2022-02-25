<?php

use CRM_Itemmanager_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Itemmanager_Form_LinkSepaPayments extends CRM_Core_Form {

    public $_contact_id;
    public $_relations;
    private $_errormessages;
    private $_back_ward_search;

    /**
     * CRM_Itemmanager_Form_LinkSepaPayments constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->_relations = array();
        $this->_errormessages = array();
        $this->_back_ward_search = array();
    }

    /**
     * Get here the contribution relations needed for the form
     */
    public function preProcess()
    {
        $this->_contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');

        $contact = civicrm_api('Contact', 'getsingle', array('version' => 3, 'id' => $this->_contact_id));
        if (isset($contact['is_error']) && $contact['is_error']) {
            CRM_Core_Session::setStatus(sprintf(ts("Couldn't find contact #%s", array('domain' => 'org.stadtlandbeides.itemmanager')),
                $this->_contact_id), ts('Error', array('domain' => 'org.stadtlandbeides.itemmanager')), 'error');
            $this->assign("display_name", "ERROR");
            return;
        }

        CRM_Utils_System::setTitle(E::ts('Payments relation for').' '.CRM_Utils_Array::value('display_name', $contact));

        $this->assign('contact_id', $this->_contact_id);

        $itemmanager_price_fields = \Civi\Api4\ItemmanagerSettings::get()
            ->setCheckPermissions(TRUE)
            ->execute();

        $currency = Civi::settings()->get('defaultCurrency');

        //region Fetch Financial types as root object
        foreach ($itemmanager_price_fields as $item)
        {

            $price_id = CRM_Utils_Array::value('price_field_value_id', $item);
            $ignore = CRM_Utils_Array::value('ignore', $item);
            $novitiate = CRM_Utils_Array::value('novitiate', $item);
            //avoid general contribution amount etc.
            if($ignore && !$novitiate) continue;

            $price_origins  = civicrm_api3('PriceFieldValue', 'get',
                array('price_field_id' => (int)$price_id));
            if ($price_origins['is_error']) {
                $this->_errormessages[] = 'Could not get the price field ' .(int)$price_id;
                continue;
            }

            $price_origin = reset($price_origins['values']);

            if(!$price_origin)
                continue;

            $financial_id = CRM_Utils_Array::value('financial_type_id', $price_origin);

            //check further efforts first
            if(array_key_exists($financial_id, $this->_relations)) continue;

            $finance_type  = civicrm_api3('FinancialType', 'getsingle',
                array('id' => (int)$financial_id));
            if (!isset($finance_type)) {
                $this->_errormessages[] = 'Could not get the financial type ' .(int)$financial_id;
                continue;
            }

            $finance_name = CRM_Utils_Array::value('name', $finance_type);

            //basic dictionary with default settings
            $this->_relations[(int)$financial_id] = array(
                'financial_id' => $financial_id,
                'financial_name' => $finance_name,
                'element_link_name' => 'grouplink_'.$financial_id,
                'contributions' => array(),
            );

            //fill form backward search
            $this->_back_ward_search[$this->_relations[(int)$financial_id]['element_link_name']] = array(
                'entity' => 'financial',
                'financial_id' => $financial_id,
            );

        }
        //endregion

        // Get the given memberships
        $member_array = CRM_Itemmanager_Util::getLastMemberShipsFullRecordByContactId($this->_contact_id);

        if($member_array['is_error'])
        {
            $this->processError("ERROR",E::ts('Retrieve memberships'),$member_array['error_message']);
            return;
        }

        //Create a logical form reference
        foreach ($member_array['values'] As $membership) {

            //get the last record
            $contributions = array();
            foreach ($membership['payinfo'] as $contribution_link)
            {
                $contribution_id = (int)$contribution_link['contribution_id'];

                $current_contribution = civicrm_api3('Contribution', 'getsingle', array('id' => (int)$contribution_id));
                $contrib_fee_amount = CRM_Utils_Array::value('fee_amount', $current_contribution);
                $contrib_net_amount = CRM_Utils_Array::value('net_amount', $current_contribution);

                //needed to make a asumption for part payments
                $contrib_net_fee_ratio = $contrib_fee_amount / $contrib_net_amount;
                $contrib_date_raw = CRM_Utils_Array::value('receive_date', $current_contribution);
                $contrib_date = CRM_Utils_Date::customFormat(date_create( $contrib_date_raw)->format('Y-m-d'),
                    Civi::settings()->get('dateformatshortdate'));
                $reference_date = date_create( $contrib_date);
                $reference_month = $reference_date->format('Y-m');

                $trxn = civicrm_api3('Payment','get',array('entity_id' => (int)$contribution_id));
                if ($trxn['is_error']) {
                    $this->_errormessages[] = 'Could not get the payments for the contribution ' .(int)$contribution_id;
                    continue;
                }

               //get the line items of the contribution
                $linerecords = CRM_Itemmanager_Util::getLineitemFullRecordByContributionId($contribution_id);
                if ($linerecords['is_error']) {
                    $this->_errormessages[] = 'Could not get the line items for contribution ' .(int)$contribution_id;
                    continue;
                }

                $foundtrxfinance = false;
                foreach ($linerecords as $lineitem) {

                    $price_field_value_id = CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata']);
                    $price_set_name = CRM_Utils_Array::value('title',$lineitem['setdata']);
                    $financial_id = CRM_Utils_Array::value('financial_type_id', $lineitem['valuedata']);

                    $line_total = CRM_Utils_Array::value('line_total',$lineitem['linedata']);
                    $line_tax = CRM_Utils_Array::value('tax_amount',$lineitem['linedata']);

                    //check here transaction relation
                    $istrxn = !$trxn['is_error'] && count($trxn['values']) > 0;


                    if ($istrxn) {
                        foreach ($trxn['values'] as $trx) {
                            $needle = 'SDD@';
                            $length = strlen($needle);
                            if (!substr($trx['trxn_id'], 0, $length) === $needle) continue;
                            $split = explode("#finance#", $trx['trxn_id']);
                            if (count($split) != 2) continue;
                            $finance_sdd_id = (int)$split[1];
                            if ($finance_sdd_id == (int)$financial_id) {
                                $foundtrxfinance = true;
                            }
                        }
                    }


                    //if($line_total == 0.0) continue;

                    $relation = &$this->_relations[(int)$financial_id];
                    $relation['valid'] = True;

                    //create entry just once per contribution
                    if(!array_key_exists($reference_month, $relation['contributions'])) {
                        $relation['contributions'][$reference_month] = array(
                            'reference_month' => $reference_month,
                            'element_link_name' => 'link_' . $financial_id . '_' . $reference_month,
                            'related_contributions' => array(),
                            'related_total_display' => '-',
                            'related_total' => 0,
                            'is_trxn' => $istrxn,
                            'is_direct_trxn' => $foundtrxfinance,

                        );

                        //fill form backward search
                        $this->_back_ward_search[$relation['contributions'][$reference_month]['element_link_name']] = array(
                            'entity' => 'link',
                            'element' => $relation['contributions'][$reference_month]['element_link_name'],
                            'financial_id' => $financial_id,
                            'reference_month' => $reference_month,
                            'is_trxn' => $istrxn,
                            'is_direct_trxn' => $foundtrxfinance,
                        );

                    }



                    //we want to collect all items of the same month
                    $contrib_base = &$relation['contributions'][$reference_month]['related_contributions'];

                    if(!array_key_exists($contribution_id, $contrib_base)) {
                        $contrib_base[$contribution_id] = array(
                            'contribution_id' => $contribution_id,
                            'contribution_date_raw' => $contrib_date_raw,
                            'contribution_date' => $contrib_date,
                            'item_label' => $price_set_name,
                            'element_cross_name' => 'contribution_' . $financial_id . '_' . $reference_month . '_' . $contribution_id,
                            'total' => 0.0,
                            'total_display' => '-',
                            'fee_amount' => $contrib_fee_amount,
                            'fee_net_ratio' => $contrib_net_fee_ratio,
                            'net_amount' => 0.0,
                            'line_count' => 0,
                            'is_trxn' => !$trxn['is_error'] && count($trxn['values']) > 0,
                            'is_direct_trxn' => $foundtrxfinance,

                        );

                        //fill form backward search
                        $this->_back_ward_search[$contrib_base[$contribution_id]['element_cross_name']] = array(
                            'entity' => 'contr_cross',
                            'element' => $contrib_base[$contribution_id]['element_cross_name'],
                            'financial_id' => $financial_id,
                            'contribution_id' => $contribution_id,
                            'reference_month' => $reference_month,
                        );


                    }

                    //sum up some parts

                    $contrib_entry = &$contrib_base[$contribution_id];
                    $contrib_entry['total'] += $line_total + $line_tax;
                    $contrib_entry['net_amount'] += $line_total;
                    $summary_display = CRM_Utils_Money::formatLocaleNumericRoundedForDefaultCurrency(
                        $contrib_entry['total']);
                    $contrib_entry['total_display'] = $summary_display.' '.$currency;
                    $contrib_entry['line_count'] += 1;

                    //sum also all related
                    $relation['contributions'][$reference_month]['related_total'] += $contrib_entry['total'];
                    $summary_related = CRM_Utils_Money::formatLocaleNumericRoundedForDefaultCurrency(
                        $relation['contributions'][$reference_month]['related_total']);
                    $relation['contributions'][$reference_month]['related_total_display'] =
                        $summary_related.' '.$currency;

                    //flag multilines
                    if(count($contrib_base ) > 1)
                        $relation['contributions'][$reference_month]['multiline'] = True;

                }//foreach ($linerecords as $lineitem)

            }//foreach ($membership['payinfo'] as $contribution_link)

        }//foreach ($member_array['values'] As $membership)


        // Get the given memberships
        $sdd_array = CRM_Itemmanager_Util::getSDDFullRecordByContactId($this->_contact_id);

        if($sdd_array['is_error'])
        {
            $this->processError("ERROR",E::ts('Retrieve SEPA mandate'),$sdd_array['error_message']);
            return;
        }

        foreach ($sdd_array['values'] As $sddmandate) {
            $index = 0;
            foreach ($sddmandate['payinfo'] as $sdd_contribution) {

                $sdd_contribution_id = (int)CRM_Utils_Array::value('id', $sdd_contribution);

                $sdd_contrib_date_raw = CRM_Utils_Array::value('receive_date', $sdd_contribution);
                $sdd_contrib_date = CRM_Utils_Date::customFormat(date_create($sdd_contrib_date_raw)->format('Y-m-d'),
                    Civi::settings()->get('dateformatshortdate'));
                $reference_date = date_create($sdd_contrib_date);
                $reference_month = $reference_date->format('Y-m');

                $financial_id = CRM_Utils_Array::value('financial_type_id', $sdd_contribution);

                //just in case there is a new financial type
                if(!array_key_exists($financial_id, $this->_relations))
                {
                    $finance_type  = civicrm_api3('FinancialType', 'getsingle',
                        array('id' => (int)$financial_id));
                    if (!isset($finance_type)) {
                        $this->_errormessages[] = 'Could not get the financial type ' .(int)$financial_id;
                        continue;
                    }

                    $finance_name = CRM_Utils_Array::value('name', $finance_type);

                    //basic dictionary with default settings
                    $this->_relations[(int)$financial_id] = array(
                        'financial_id' => $financial_id,
                        'financial_name' => $finance_name,
                        'element_link_name' => 'grouplink_'.$financial_id,
                        'contributions' => array(),
                    );


                    //fill form backward search
                    $this->_back_ward_search[$this->_relations[(int)$financial_id]['element_link_name']] = array(
                        'entity' => 'financial',
                        'element' => $this->_relations[(int)$financial_id]['element_link_name'],
                        'financial_id' => $financial_id,
                    );

                }

                $relation = &$this->_relations[(int)$financial_id];
                $relation_found = False;
                foreach ($relation['contributions'] as $contribution_base)
                    foreach ($contribution_base['related_contributions'] as $contribution)
                    {

                        if($contribution_base['reference_month'] == $reference_month)
                        {
                            $summary_display = CRM_Utils_Money::formatLocaleNumericRoundedForDefaultCurrency(
                                CRM_Utils_Array::value('total_amount', $sdd_contribution));
                            $contrib = &$relation['contributions'][$reference_month];
                            $contrib['sdd'] = array(
                                'sdd_id' => $sdd_contribution_id,
                                'sdd_contribution_date' => $sdd_contrib_date,
                                'sdd_contribution_raw' => $sdd_contrib_date_raw,
                                'element_cross_name' => 'mandate_'.$financial_id.'_'.$reference_month.'_'.$sdd_contribution_id,
                                'sdd_mandate_id' => CRM_Utils_Array::value('id', $sddmandate['sdddata']),
                                'sdd_mandate' => CRM_Utils_Array::value('reference', $sddmandate['sdddata']),
                                'payment_instrument_id' => CRM_Utils_Array::value('payment_instrument_id', $sdd_contribution),
                                'sdd_source' => CRM_Utils_Array::value('source', $sddmandate['sdddata']),
                                'sdd_total' => CRM_Utils_Array::value('total_amount', $sdd_contribution),
                                'sdd_fee_amount' => CRM_Utils_Array::value('fee_amount', $sdd_contribution),
                                'sdd_net_amount' => CRM_Utils_Array::value('net_amount', $sdd_contribution),
                                'sdd_total_display' => $summary_display.' '.$currency,
                            );


                            //fill form backward search
                            $this->_back_ward_search[$contrib['sdd']['element_cross_name']] = array(
                                'entity' => 'sdd_cross',
                                'element' => $contrib['sdd']['element_cross_name'],
                                'financial_id' => $financial_id,
                                'sdd_id' => $sdd_contribution_id,
                                'reference_month' => $reference_month,
                            );

                            $relation_found = True;
                            break;

                        }


                    }//foreach ($relation['contributions'] as $contribution)

                //alonesome contribution
                if(!$relation_found)
                {
                    $summary_display = CRM_Utils_Money::formatLocaleNumericRoundedForDefaultCurrency(
                        CRM_Utils_Array::value('total_amount', $sdd_contribution));

                    //create entry just once per contribution
                    if(!array_key_exists($reference_month, $relation['contributions'])) {
                        $relation['contributions'][$reference_month] = array(
                            'reference_month' => $reference_month,
                            'related_contributions' => array(),
                            'element_link_name' => 'link_'.$financial_id.'_'. $reference_month,

                        );

                        //fill form backward search
                        $this->_back_ward_search[$relation['contributions'][$reference_month]['element_link_name']] = array(
                            'entity' => 'link',
                            'element' => $relation['contributions'][$reference_month]['element_link_name'],
                            'financial_id' => $financial_id,
                            'sdd_id' => $sdd_contribution_id,
                            'reference_month' => $reference_month,
                        );


                    }

                    $sdd_contrib_base = &$relation['contributions'][$reference_month];
                    $sdd_contrib_rel = &$relation['contributions'][$reference_month]['related_contributions'];

                    $sdd_contrib_base['sdd'] = array(
                        'sdd_id' => $sdd_contribution_id,
                        'sdd_contribution_date' => $sdd_contrib_date,
                        'sdd_contribution_raw' => $sdd_contrib_date_raw,
                        'element_cross_name' => 'mandate_'.$financial_id.'_'.$reference_month.'_'.$sdd_contribution_id,
                        'sdd_mandate_id' => CRM_Utils_Array::value('id', $sddmandate['sdddata']),
                        'sdd_mandate' => CRM_Utils_Array::value('reference', $sddmandate['sdddata']),
                        'payment_instrument_id' => CRM_Utils_Array::value('payment_instrument_id', $sdd_contribution),
                        'sdd_source' => CRM_Utils_Array::value('source', $sddmandate['sdddata']),
                        'sdd_total' => CRM_Utils_Array::value('total_amount', $sdd_contribution),
                        'sdd_fee_amount' => CRM_Utils_Array::value('fee_amount', $sdd_contribution),
                        'sdd_net_amount' => CRM_Utils_Array::value('net_amount', $sdd_contribution),
                        'sdd_total_display' => $summary_display.' '.$currency,
                        );

                    //fill form backward search
                    $this->_back_ward_search[$sdd_contrib_base['sdd']['element_cross_name']] = array(
                        'entity' => 'sdd_cross',
                        'element' => $sdd_contrib_base['sdd']['element_cross_name'],
                        'financial_id' => $financial_id,
                        'sdd_id' => $sdd_contribution_id,
                        'reference_month' => $reference_month,
                    );

                    if(!array_key_exists($sdd_contribution_id, $sdd_contrib_rel)) {
                        $sdd_contrib_rel[$sdd_contribution_id] = array(

                            'contribution_id' => 0,
                            'element_cross_name' => 'empty_'.$financial_id.'_'. $reference_month . '_' . $sdd_contribution_id,
                            'contribution_date' => '-',
                            'contribution_date_raw' => '-',
                            'item_label' => '-',
                            'total' => 0.0,
                            'total_display' => '-',
                            'fee_amount' => 0.0,
                            'fee_net_ratio' => 1.0,
                            'net_amount' => 0.0,
                            'line_count' => 0,
                            'is_trxn' => 0,
                            'is_direct_trxn' => 0,
                            'empty' => True,

                        );

                        //fill form backward search
                        $this->_back_ward_search[$sdd_contrib_rel[$sdd_contribution_id]['element_cross_name']] = array(
                            'entity' => 'empty',
                            'element' => $sdd_contrib_rel[$sdd_contribution_id]['element_cross_name'],
                                'financial_id' => $financial_id,
                            'reference_month' => $reference_month,
                        );

                    }
                }
            }

        }//foreach ($sdd_array['values'] As $sddmandate)



        $this->assign('relations', $this->_relations);
    }

    /**
     * Create Form
     *
     * @throws CRM_Core_Exception
     */

    public function buildQuickForm() {

        foreach ($this->_relations as $relation) {

            $this->addElement(
                'advcheckbox',
                $relation['element_link_name'],
                ts('Relation'),
                null,
                array(
                    'id'=> $relation['financial_id'],
                    'class' => 'cm-toggle',
                ),
            );


            foreach ($relation['contributions'] as $basecontribution) {


                $chk = $this->addElement(
                    'advcheckbox',
                    $basecontribution['element_link_name'],
                    null,
                    null,
                    array(
                        'id'=> $basecontribution['reference_month'],
                        'class' => 'cm-toggle',
                    ),
                );

                $chk->setValue($basecontribution['is_direct_trxn']);


                foreach ($basecontribution['related_contributions'] as $contribution)
                {
                    $this->addElement(
                        'advcheckbox',
                        $contribution['element_cross_name'],
                        ts('Crosslink'),
                        null,
                        array(
                            'id'=> $contribution['contribution_id'],
                        ),
                    );

                }//foreach ($basecontribution['related_contributions'] as $contribution)

                $related_mandate = $basecontribution['sdd'];



                $this->addElement(
                    'advcheckbox',
                    $related_mandate['element_cross_name'],
                    ts('Crosslink'),
                    null,
                    array(
                        'id'=> $related_mandate['sdd_id'],
                        )
                );

            }//foreach ($relation['contributions'] as $basecontribution)

        }//foreach ($this->_relations as $relation)



        $this->addButtons(array(
          array(
            'type' => 'submit',
            'name' => E::ts('Submit'),
            'isDefault' => TRUE,
          ),
            array(
                'type' => 'cancel',
                'name' => ts('Cancel'),
            ),
        ));

        // export form elements
        $this->assign('elementNames', $this->getRenderableElementNames());
        $this->assign('errormessages',$this->_errormessages);

        parent::buildQuickForm();
      }

  public function postProcess() {
    $formvalues = $this->exportValues();

    foreach (array_keys($formvalues) as $key)
    {
       if(array_key_exists($key, $this->_back_ward_search))
       {
           $back_reference = $this->_back_ward_search[$key];
           $value = $formvalues[$key];

           switch ($back_reference['entity'])
           {
               case 'empty':
                   break;

               case 'sdd_cross':
                   break;

               case 'link':
                   if($value == 1 && !$back_reference['is_direct_trxn'])
                        $this->createPaymentbyLink($back_reference);
                   elseif ($value == 0 && $back_reference['is_direct_trxn'])
                        $this->deletePaymentbyLink($back_reference);
                   break;

               case 'contr_cross':
                   break;

               case 'financial':
                   break;

       }

       }
    }
    parent::postProcess();
  }

    /**
     *  Creates the related payment based on the link button
     *
     * @param $reference (array) back reference array item
     */
  private function createPaymentbyLink($reference)
  {
        $financial_id = $reference['financial_id'];
        $reference_month = $reference['reference_month'];

        $relation = &$this->_relations[$financial_id];
        $contrib_base = &$relation['contributions'][$reference_month];
        $contrib_rel = &$relation['contributions'][$reference_month]['related_contributions'];
        $mandate = $contrib_base['sdd'];

        foreach ($contrib_rel as $contribution)
        {
            if((float) $mandate['sdd_total'] < (float)$contribution['total'])
            {
                $total = (float) $mandate['sdd_total'];
                $net = (float)$mandate['sdd_net_amount'];
                $fee = (float) $mandate['sdd_fee_amount'];
            }
            else
            {
                $total = (float)$contribution['total'];
                $net = (float)$contribution['net_amount'];
                $fee = (float)$contribution['fee_net_ratio'] * $net;
            }


            //has to be filled up for the payment
            $pay_param = array(
                //'check_number',
                //'payment_processor_id',
                'fee_amount'=>  $fee,
                'total_amount' => $total,
                'contribution_id' => (int) $contribution['contribution_id'],
                'net_amount' => $net,
                //'card_type_id',
                //'pan_truncation',
                'trxn_result_code'=>2,
                'payment_instrument_id'=> (int) $mandate['payment_instrument_id'],
                'trxn_id' => 'SDD@'.$mandate['sdd_mandate'].'#finance#'.$financial_id,
                'trxn_date' => $mandate['sdd_contribution_raw'],
                'contribution_date_copy' => $contribution['contribution_date_raw'],
                //'order_reference' => ,



            );

            $this->createPayment($pay_param,
                E::ts('Add SEPA payment relation').
                                    ' '.$mandate['sdd_mandate'].' from '.$mandate['sdd_contribution_date']);

        }

      return;
  }


    /**
     *  Delete the related payment based on the link button
     *
     * @param $reference (array) back reference array item
     */
    private function deletePaymentbyLink($reference)
    {
        $financial_id = $reference['financial_id'];
        $reference_month = $reference['reference_month'];

        $relation = &$this->_relations[$financial_id];
        $contrib_base = &$relation['contributions'][$reference_month];
        $contrib_rel = &$relation['contributions'][$reference_month]['related_contributions'];
        $mandate = $contrib_base['sdd'];

        foreach ($contrib_rel as $contribution)
        {
            //skip unrelated payments
            if(!$contribution['is_trxn']) continue;

            //has to be filled up for the payment
            $pay_param = array(
                'contribution_id' => (int) $contribution['contribution_id'],
                'financial_id' => $financial_id,
            );

            $this->deletePayment($pay_param,
                E::ts('Delete SEPA payment relation').
                ' '.$mandate['sdd_mandate'].' from '.$mandate['sdd_contribution_date']);

        }

        return;
    }



    /**
     *  Wrapper for the API Payment call to serve some adds
     *
     * @param $params Payment see API3
     * @param $sucessmessage
     * @throws CiviCRM_API3_Exception
     */
  private function createPayment(array $params, $sucessmessage){

      $transaction = new CRM_Core_Transaction();
      try {

          //$param['is_email_receipt'] = false;

          $trxn = civicrm_api3('Payment', 'create', $params);

          $update_param = array(
              'id' => (int)$params['contribution_id'],
              'receive_date' =>  $params['contribution_date_copy'],
          );



          if($trxn['is_error'])
          {
              $this->processError("ERROR",E::ts('Add SEPA payment relation'),$trxn['error_message']);
              $transaction->rollback();

          }
          else
          {

              //restore origin date
              $transaction->commit();
              $update = civicrm_api3('Contribution', 'create', $update_param);

              if($update['is_error'])
              {
                  $this->processError("ERROR",E::ts('Update status contribution'),$update['error_message']);
                  $transaction->rollback();

              }
              else
                $this->processSuccess($sucessmessage.' transaction '.$trxn['id']);
          }


      } catch (CRM_Core_Exception $e) {
          $transaction->rollback();
      }



  }

    /**
     *  Wrapper for the API Payment delete call to serve some adds
     *
     * @param $params Payment see API3
     * @param $sucessmessage
     * @throws CiviCRM_API3_Exception
     */
    private function deletePayment(array $params, $sucessmessage){

        $transaction = new CRM_Core_Transaction();
        try {

            $contribution_id = $params['contribution_id'];

            $trxn = civicrm_api3('Payment','get',array('entity_id' => (int)$contribution_id));
            if ($trxn['is_error']) {
                $this->processError("ERROR",
                    E::ts('Could not get the payments for the contribution ' .(int)$contribution_id),
                    $trxn['error_message']);
                $transaction->rollback();
            }
            else
            {

                foreach ($trxn['values'] as $trx )
                {
                    //check our stuff
                    $needle = 'SDD@';
                    $length = strlen( $needle );
                    if (!substr( $trx['trxn_id'], 0, $length ) === $needle) continue;
                    $split = explode("#finance#", $trx['trxn_id']);
                    if(count($split) != 2 ) continue;

                    $finance_sdd_id = (int)$split[1];

                    if($finance_sdd_id != (int)$params['financial_id']) continue;

                    $trxd = civicrm_api3('Payment', 'delete', array('id' => (int)$trx['id']));

                    if($trxd['is_error'])
                    {
                        $this->processError("ERROR",E::ts('Delete SEPA payment relation'),$trxd['error_message']);
                        $transaction->rollback();
                        break;

                    }
                    else {

                        // it is not everythin allowed here
//                        $update_param = array(
//                            'id' => (int)$params['contribution_id'],
//                            'contribution_status_id' =>  3,
//                        );
//
//                        //restore origin date
//                        $update = civicrm_api3('Contribution', 'create', $update_param);
//
//                        if($update['is_error'])
//                        {
//                            $this->processError("ERROR",E::ts('Update status contribution'),$update['error_message']);
//                            $transaction->rollback();
//
//                        }
//                        else
//                        {
//                            $this->processSuccess($sucessmessage . ' transaction ' . $trx['id']);
//                        }


                        $this->processSuccess($sucessmessage . ' transaction ' . $trx['id']);

                    }
                }

            }


        } catch (CRM_Core_Exception $e) {
            $transaction->rollback();
        }

        $transaction->commit();

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
    protected function processError($status, $title, $message) {
        CRM_Core_Session::setStatus($status . "<br/>" . $message, ts('Error', array('domain' => 'org.stadtlandbeides.itemmanager')), 'error');
        $this->assign("error_title",   $title);
        $this->assign("error_message", $message);


    }

    protected function processSuccess($message) {
        CRM_Core_Session::setStatus($message, ts('Success', array('domain' => 'org.stadtlandbeides.itemmanager')), 'success');



    }

    protected function processInfo($message) {
        CRM_Core_Session::setStatus($message, ts('Info', array('domain' => 'org.stadtlandbeides.itemmanager')), 'info');



    }

}
