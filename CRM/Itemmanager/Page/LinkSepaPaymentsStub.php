<?php
use CRM_Itemmanager_ExtensionUtil as E;

class CRM_Itemmanager_Page_LinkSepaPaymentsStub extends CRM_Core_Page {

    public $_contact_id;
    public $_financial_id;
    public $_relation;
    private $_errormessages;
    private $_back_ward_search;


    /**
     * CRM_Itemmanager_Form_LinkSepaPayments constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->_relation = array('contributions'=>array());
        $this->_errormessages = array();
        $this->_back_ward_search = array();
    }

    public function run() {
      $this->_contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');
      $this->assign('contact_id', $this->_contact_id);

        $this->_financial_id = CRM_Utils_Request::retrieve('fid', 'Integer');
        $this->assign('financial_id', $this->_financial_id);

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

                    $this->_relation['valid'] = True;

                    //create entry just once per contribution
                    if(!array_key_exists($reference_month, $this->_relation['contributions'])) {
                        $this->_relation['contributions'][$reference_month] = array(
                            'reference_month' => $reference_month,
                            'element_link_name' => 'link_' . $financial_id . '_' . $reference_month,
                            'related_contributions' => array(),
                            'related_total_display' => '-',
                            'related_total' => 0,
                            'is_trxn' => $istrxn,
                            'is_direct_trxn' => $foundtrxfinance,

                        );

                        //fill form backward search
                        $this->_back_ward_search[$this->_relation['contributions'][$reference_month]['element_link_name']] = array(
                            'entity' => 'link',
                            'element' => $this->_relation['contributions'][$reference_month]['element_link_name'],
                            'financial_id' => $financial_id,
                            'reference_month' => $reference_month,
                            'is_trxn' => $istrxn,
                            'is_direct_trxn' => $foundtrxfinance,
                        );

                    }



                    //we want to collect all items of the same month
                    $contrib_base = &$this->_relation['contributions'][$reference_month]['related_contributions'];

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
                    $this->_relation['contributions'][$reference_month]['related_total'] += $contrib_entry['total'];
                    $summary_related = CRM_Utils_Money::formatLocaleNumericRoundedForDefaultCurrency(
                        $this->_relation['contributions'][$reference_month]['related_total']);
                    $this->_relation['contributions'][$reference_month]['related_total_display'] =
                        $summary_related.' '.$currency;

                    //flag multilines
                    if(count($contrib_base ) > 1)
                        $this->_relation['contributions'][$reference_month]['multiline'] = True;

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

                //don't relate foreign SDD contributions
                if($financial_id != $this->_financial_id) continue;

                $relation_found = False;
                foreach ($this->_relation['contributions'] as $contribution_base)
                    foreach ($contribution_base['related_contributions'] as $contribution)
                    {

                        if($contribution_base['reference_month'] == $reference_month)
                        {
                            $summary_display = CRM_Utils_Money::formatLocaleNumericRoundedForDefaultCurrency(
                                CRM_Utils_Array::value('total_amount', $sdd_contribution));
                            $contrib = &$this->_relation['contributions'][$reference_month];
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
                    if(!array_key_exists($reference_month, $this->_relation['contributions'])) {
                        $this->_relation['contributions'][$reference_month] = array(
                            'reference_month' => $reference_month,
                            'related_contributions' => array(),
                            'element_link_name' => 'link_'.$financial_id.'_'. $reference_month,

                        );

                        //fill form backward search
                        $this->_back_ward_search[$this->_relation['contributions'][$reference_month]['element_link_name']] = array(
                            'entity' => 'link',
                            'element' => $this->_relation['contributions'][$reference_month]['element_link_name'],
                            'financial_id' => $financial_id,
                            'sdd_id' => $sdd_contribution_id,
                            'reference_month' => $reference_month,
                        );


                    }

                    $sdd_contrib_base = &$this->_relation['contributions'][$reference_month];
                    $sdd_contrib_rel = &$this->_relation['contributions'][$reference_month]['related_contributions'];

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



        $this->assign('relation', $this->_relation);
        $this->setVar('backward_search', $this->_back_ward_search);
        // export form elements
        CRM_Core_Resources::singleton()
           // ->addScriptFile('org.stadtlandbeides.itemmanager', 'js/expandAccordion.js')
            ->addStyleFile('org.stadtlandbeides.itemmanager', 'css/sepaLink.css');


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

    protected function processSuccess($message) {
        CRM_Core_Session::setStatus($message, ts('Success', array('domain' => 'org.stadtlandbeides.itemmanager')), 'success');



    }

    protected function processInfo($message) {
        CRM_Core_Session::setStatus($message, ts('Info', array('domain' => 'org.stadtlandbeides.itemmanager')), 'info');



    }


}
