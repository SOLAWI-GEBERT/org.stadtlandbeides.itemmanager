<?php
use CRM_Itemmanager_ExtensionUtil as E;

class CRM_Itemmanager_Page_LinkSepaPaymentsStub extends CRM_Core_Page {

    public $_contact_id;
    public $_financial_id;
    public $_relation;
    private $_errormessages;
    private $_back_ward_search;
    private $_currency;
    private $_filteropen;
    private $_filterfuture;


    /**
     * CRM_Itemmanager_Form_LinkSepaPayments constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->_relation = array('contributions'=>array());
        $this->_errormessages = array();
        $this->_back_ward_search = array();
        $this->_currency = Civi::settings()->get('defaultCurrency');
        $this->_filteropen = false;
        $this->_filterfuture = false;
    }

    public function run() {



        $this->_contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');
        $this->_action = CRM_Utils_Request::retrieve('action', 'String', $this, FALSE, 'browse');
        $this->assign('contact_id', $this->_contact_id);

        $this->_financial_id = CRM_Utils_Request::retrieve('fid', 'Integer');
        $this->assign('financial_id', $this->_financial_id);

        //get our options
        $this->_filteropen = CRM_Utils_Request::retrieve('filteropen', 'Integer',$this,0) === 1;
        $this->_filterfuture = CRM_Utils_Request::retrieve('filterfuture', 'Integer',$this,0) === 1;

        // Get the given memberships
        $member_array = CRM_Itemmanager_Util::getLastMemberShipsFullRecordByContactId($this->_contact_id);

        if($member_array['is_error'])
        {
            $this->processError("ERROR",E::ts('Retrieve memberships'),$member_array['error_message']);
            return;
        }

        //get the SDD Data
        $SDD_transformed = $this->transformMandateData();
        $inserted_by_accident = array();

        //Create a logical form reference
        foreach ($member_array['values'] As $membership) {

            //get the last record
            foreach ($membership['payinfo'] as $contribution_link)
            {
                $contribution_id = (int)$contribution_link['contribution_id'];

                $current_contribution = civicrm_api3('Contribution', 'getsingle', array('id' => (int)$contribution_id));
                $current_contr_status = (int)CRM_Utils_Array::value('contribution_status_id', $current_contribution);

                //check here the filter options
                if($this->_filteropen && $current_contr_status === 1) continue;

                $contrib_fee_amount = CRM_Utils_Array::value('fee_amount', $current_contribution);
                $contrib_net_amount = CRM_Utils_Array::value('net_amount', $current_contribution);
                $contrib_net_fee_ratio = 0;
                //needed to make a asumption for part payments
                try {
                    if ($contrib_net_amount == 0) {
                        $contrib_net_fee_ratio = -1.0;
                        $this->_errormessages[] = 'Net amount is Zero ' .$contrib_net_amount;
                    }
                    else {$contrib_net_fee_ratio = $contrib_fee_amount / $contrib_net_amount;}

                } catch (DivisionByZeroError $e) {
                    $contrib_net_fee_ratio = -1.0;
                }

                if(!empty($this->_errormessages))
                {
                    $this->processError("ERROR",E::ts('Retrieve payinfo from memberships'),$this->_errormessages[0]);
                    return;
                }

                $contrib_date_raw = CRM_Utils_Array::value('receive_date', $current_contribution);
                $contrib_date = CRM_Utils_Date::customFormat(date_create( $contrib_date_raw)->format('Y-m-d'),
                    Civi::settings()->get('dateformatshortdate'));
                $reference_date = date_create( $contrib_date);
                $reference_month = $reference_date->format('Y-m');


                //check here date filter
                if($this->_filterfuture)
                {
                    $today = CRM_Utils_Date::getToday(NULL,'Y-m');
                    if($today < $reference_month) continue;
                }


                //get the line items of the contribution
                $linerecords = CRM_Itemmanager_Util::getLineitemFullRecordByContributionId(
                    $contribution_id,
                    $this->_financial_id);
                if ($linerecords['is_error']) {
                    $this->_errormessages[] = 'Could not get the line items for contribution ' .(int)$contribution_id;
                    continue;
                }

                if(count($linerecords) === 0) continue;

                $trxn = civicrm_api3('Payment','get',array('entity_id' => (int)$contribution_id));
                if ($trxn['is_error']) {
                    $this->_errormessages[] = 'Could not get the payments for the contribution ' .(int)$contribution_id;
                    continue;
                }

                $foundtrxfinance = false;
                $SDD_reference_trxn_id = array();

                //check here transaction relation
                $istrxn = !$trxn['is_error'] && count($trxn['values']) > 0;

                if ($istrxn) {
                    foreach ($trxn['values'] as $trx) {
                        $needle = 'SDD@';
                        $length = strlen($needle);
                        if (!substr($trx['trxn_id'], 0, $length) === $needle) continue;
                        $split_adds = explode("#finance#", $trx['trxn_id']);
                        if (count($split_adds) != 2) continue;

                        $split_ids = explode("#contribution#", $split_adds[1]);
                        if (count($split_ids) != 2) continue;

                        $finance_sdd_id = (int)$split_ids[0];
                        $sdd_contrib_id = (int)$split_ids[1];
                        if ($finance_sdd_id === $this->_financial_id) {
                            $foundtrxfinance = true;
                            $SDD_reference_trxn_id[] = $trx['trxn_id'];
                        }


                    }
                }


                foreach ($linerecords as $lineitem) {

                    $itemmanager = new CRM_Itemmanager_BAO_ItemmanagerSettings();
                    $price_field_value_id = CRM_Utils_Array::value('price_field_value_id', $lineitem['linedata']);
                    $valid = $itemmanager->get('price_field_value_id', $price_field_value_id );
                    if(!$valid)
                    {
                        $this->_errormessages[] = 'Could not get itemmanger data from ' .(int)$price_field_value_id;
                        continue;
                    }
                    $periodbase = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
                    $periodbase->get('id',$itemmanager->itemmanager_periods_id);

                    $reference_id = $foundtrxfinance ? $SDD_reference_trxn_id[0] :
                        CRM_Itemmanager_Util::getReferenceDate($reference_date,(int)$periodbase->period_type );


                    $price_set_name = CRM_Utils_Array::value('title',$lineitem['setdata']);
                    $financial_id = CRM_Utils_Array::value('financial_type_id', $lineitem['valuedata']);

                    $line_total = CRM_Utils_Array::value('line_total',$lineitem['linedata']);
                    $line_tax = CRM_Utils_Array::value('tax_amount',$lineitem['linedata']);

                    $this->_relation['valid'] = True;

                    //create entry just once per contribution
                    if(!array_key_exists($reference_id, $this->_relation['contributions'])) {
                        $this->_relation['contributions'][$reference_id] = array(
                            'reference_month' => $reference_month,
                            'reference_id' => $reference_id,
                            'element_link_name' => 'link_' . $this->_financial_id . '_' . $reference_id,
                            'related_contributions' => array(),
                            'related_sdds' => array(),
                            'related_total_display' => '-',
                            'related_total' => 0,
                            'is_trxn' => $istrxn,
                            'is_direct_trxn' => $foundtrxfinance ? 1 : 0,

                        );

                        //fill form backward search
                        $this->_back_ward_search[$this->_relation['contributions'][$reference_id]['element_link_name']] = array(
                            'entity' => 'link',
                            'element' => $this->_relation['contributions'][$reference_id]['element_link_name'],
                            'financial_id' => $financial_id,
                            'reference_month' => $reference_month,
                            'reference_id' => $reference_id,
                            'is_trxn' => $istrxn,
                            'is_direct_trxn' => $foundtrxfinance ? 1 : 0,
                        );

                        $sdd_base = &$this->_relation['contributions'][$reference_id]['related_sdds'];
                        //insert all related SDD data
                        foreach($SDD_transformed as $sdd_key => $sdd_value)
                        {

                            if($foundtrxfinance) {

                                //enter if direct link
                                foreach ($SDD_reference_trxn_id as $direct_trxn)
                                {
                                    if ($direct_trxn != $sdd_key) continue;
                                    $sdd_base[$sdd_key] = $sdd_value;

                                    $SDD_transformed[$sdd_key]['direct_linked'] = true;
                                    if ($SDD_transformed[$sdd_key]['date_linked']) {
                                        //remove SDD value if accidentally linked by date
                                        $inserted_by_accident[] = $sdd_value['reference_month'];
                                        $contrib_related_date = &$this->_relation['contributions']
                                        [$sdd_value['reference_month']]['related_sdds'];
                                        unset($contrib_related_date[$sdd_key]);

                                    }
                                }

                            }
                            else {


                                $reference_cmp = CRM_Itemmanager_Util::getReferenceDate($reference_date,
                                    (int)$periodbase->period_type );
                                $ssd_date_convert = date_create($sdd_value['sdd_contribution_raw']);
                                $sdd_reference_cmp = CRM_Itemmanager_Util::getReferenceDate($ssd_date_convert,
                                    (int)$periodbase->period_type );


                                //enter if reference_month ===
                                if($reference_cmp != $sdd_reference_cmp|| $SDD_transformed[$sdd_key]['direct_linked']) continue;

                                $sdd_base[$sdd_key] = $sdd_value;
                                $SDD_transformed[$sdd_key]['date_linked'] = true;


                            }

                        }

                    }

                    //we want to collect all items of the same month
                    $contrib_base = &$this->_relation['contributions'][$reference_id]['related_contributions'];

                    if(!array_key_exists($contribution_id, $contrib_base)) {
                        $contrib_base[$contribution_id] = array(
                            'contribution_id' => $contribution_id,
                            'contribution_date_raw' => $contrib_date_raw,
                            'contribution_date' => $contrib_date,
                            'item_label' => $price_set_name,
                            'element_cross_name' => 'contribution_' . $financial_id . '_' . $reference_id . '_' . $contribution_id,
                            'total' => 0.0,
                            'total_display' => '-',
                            'fee_amount' => $contrib_fee_amount,
                            'fee_net_ratio' => $contrib_net_fee_ratio,
                            'net_amount' => 0.0,
                            'line_count' => 0,
                            'is_trxn' => !$trxn['is_error'] && count($trxn['values']) > 0,
                            'is_direct_trxn' => $foundtrxfinance ? 1 : 0,
                            'statusclass' => $this->getcssClassforPaystatus($current_contr_status,
                                !$trxn['is_error'] && count($trxn['values']) > 0),

                        );

                        //fill form backward search
                        $this->_back_ward_search[$contrib_base[$contribution_id]['element_cross_name']] = array(
                            'entity' => 'contr_cross',
                            'element' => $contrib_base[$contribution_id]['element_cross_name'],
                            'financial_id' => $financial_id,
                            'contribution_id' => $contribution_id,
                            'contribution_date_raw' => $contrib_date_raw,
                            'is_trxn' => !$trxn['is_error'] && count($trxn['values']) > 0,
                            'is_direct_trxn' => $foundtrxfinance ? 1 : 0,
                            'reference_month' => $reference_month,
                            'reference_id' => $reference_id,
                            'cross_payment' => null,
                            'add_payment' => array(),
                        );

                    }

                    //sum up some parts

                    $contrib_entry = &$contrib_base[$contribution_id];
                    $contrib_entry['total'] += $line_total + $line_tax;
                    $contrib_entry['net_amount'] += $line_total;
                    $summary_display = CRM_Utils_Money::formatLocaleNumericRoundedForDefaultCurrency(
                        $contrib_entry['total']);
                    $contrib_entry['total_display'] = $summary_display.' '.$this->_currency;
                    $contrib_entry['line_count'] += 1;

                    //sum also all related
                    $this->_relation['contributions'][$reference_id]['related_total'] += $contrib_entry['total'];
                    $summary_related = CRM_Utils_Money::formatLocaleNumericRoundedForDefaultCurrency(
                        $this->_relation['contributions'][$reference_id]['related_total']);
                    $this->_relation['contributions'][$reference_id]['related_total_display'] =
                        $summary_related.' '.$this->_currency;

                    //add this value into reference
                    $reference = &$this->_back_ward_search[$contrib_entry['element_cross_name']];
                    $reference['cross_payment'] = $this->copyPaymentFragment($contrib_base[$contribution_id]);

                    $sdd_base = &$this->_relation['contributions'][$reference_id]['related_sdds'];
                    $add_payment = &$reference['add_payment'];
                    //insert all related SDD data
                    foreach($sdd_base as $sdd_key => $sdd_value)
                    {
                        $add_payment[$sdd_key] = $this->addPaymentbyLink($sdd_value, $contrib_entry,$sdd_value['sdd_id']);

                    }

                    //flag multilines
                    if(count($contrib_base ) > 1)
                        $this->_relation['contributions'][$reference_id]['multiline'] = True;

                }//foreach ($linerecords as $lineitem)

            }//foreach ($membership['payinfo'] as $contribution_link)

        }//foreach ($member_array['values'] As $membership)



        //add the rest
        $this->addMissingSDD($SDD_transformed);

        $this->assign('relation', $this->_relation);
        // export form elements
        CRM_Core_Resources::singleton()
            ->addScriptFile('org.stadtlandbeides.itemmanager', 'js/handlePayment.js', 999, 'html-header')
            ->addStyleFile('org.stadtlandbeides.itemmanager', 'css/sepaLink.css', 999, 'html-header');

        Civi::resources()->addVars('itemmanager_SEPA_backward_search'.$this->_financial_id, $this->_back_ward_search);


    parent::run();
    }


    /*
     * Simply add the non related SDD as standalone
     */
    private function addMissingSDD(&$SDD_transformed)
    {
        foreach ($SDD_transformed as $sdd_key => $sdd_value) {
            $reference_month = $sdd_value['reference_month'];

            if($SDD_transformed[$sdd_key]['date_linked'] || $SDD_transformed[$sdd_key]['direct_linked']) continue;
            //create entry just once per contribution
            if (!array_key_exists($reference_month, $this->_relation['contributions'])) {
                $this->_relation['contributions'][$reference_month] = array(
                    'reference_month' => $reference_month,
                    'reference_id' => $reference_month,
                    'related_contributions' => array(),
                    'related_sdds' => array(),
                    'element_link_name' => 'link_' . $this->_financial_id . '_' . $reference_month,
                    'related_total_display' => '-',
                    'related_total' => 0,
                    'is_trxn' => false,
                    'is_direct_trxn' => 0,

                );

                $sdd_base = &$this->_relation['contributions'][$reference_month]['related_sdds'];
                $sdd_base[$sdd_key] = $sdd_value;
            }
        }
        return;
    }


    /*
     * Give us just a transformed version of the SDD data
     */
    private function transformMandateData()
    {
        // Get the given memberships
        $SDD_transform = array();

        $sdd_array = CRM_Itemmanager_Util::getSDDFullRecordByContactId($this->_contact_id, $this->_financial_id);

        if($sdd_array['is_error'])
        {
            $this->processError("ERROR",E::ts('Retrieve SEPA mandate'),$sdd_array['error_message']);
            return $SDD_transform;
        }

        foreach ($sdd_array['values'] As $sddmandate) {
            foreach ($sddmandate['payinfo'] as $sdd_contribution) {

                //head data
                $sdd_contribution_id = (int)CRM_Utils_Array::value('id', $sdd_contribution);
                $sdd_mandate = CRM_Utils_Array::value('reference', $sddmandate['sdddata']);
                $trxnid = $this->createMandateTrxnId($sdd_mandate, $sdd_contribution_id);

                $sdd_contrib_date_raw = CRM_Utils_Array::value('receive_date', $sdd_contribution);
                $sdd_contrib_date = CRM_Utils_Date::customFormat(date_create($sdd_contrib_date_raw)->format('Y-m-d'),
                    Civi::settings()->get('dateformatshortdate'));
                $reference_date = date_create($sdd_contrib_date);
                $reference_month = $reference_date->format('Y-m');

                //check here date filter
                if($this->_filterfuture)
                {
                    $today = CRM_Utils_Date::getToday(NULL,'Y-m');
                    if($today < $reference_month) continue;
                }

                $summary_display = CRM_Utils_Money::formatLocaleNumericRoundedForDefaultCurrency(
                    CRM_Utils_Array::value('total_amount', $sdd_contribution));
                $SDD_transform[$trxnid] = array(
                    'date_linked' => false,
                    'direct_linked' => false,
                    'sdd_id' => $sdd_contribution_id,
                    'reference_month' => $reference_month,
                    'sdd_contribution_date' => $sdd_contrib_date,
                    'sdd_contribution_raw' => $sdd_contrib_date_raw,
                    'element_cross_name' => 'mandate_' . $this->_financial_id . '_' . $reference_month . '_' . $sdd_contribution_id,
                    'sdd_mandate_id' => CRM_Utils_Array::value('id', $sddmandate['sdddata']),
                    'sdd_mandate' => CRM_Utils_Array::value('reference', $sddmandate['sdddata']),
                    'payment_instrument_id' => CRM_Utils_Array::value('payment_instrument_id', $sdd_contribution),
                    'sdd_source' => CRM_Utils_Array::value('source', $sddmandate['sdddata']),
                    'sdd_total' => CRM_Utils_Array::value('total_amount', $sdd_contribution),
                    'sdd_fee_amount' => CRM_Utils_Array::value('fee_amount', $sdd_contribution),
                    'sdd_net_amount' => CRM_Utils_Array::value('net_amount', $sdd_contribution),
                    'sdd_total_display' => $summary_display . ' ' . $this->_currency,
                    'statusclass' => $this->getcssClassforPaystatus(
                        (int)CRM_Utils_Array::value('contribution_status_id', $sdd_contribution), true),
                );


                //fill form backward search
                $this->_back_ward_search[$SDD_transform[$trxnid]['element_cross_name']] = array(
                    'entity' => 'sdd_cross',
                    'element' => $SDD_transform[$trxnid]['element_cross_name'],
                    'financial_id' => $this->_financial_id,
                    'sdd_id' => $sdd_contribution_id,
                    'reference_month' => $reference_month,
                    'add_payment' => $this->addPaymentFragment($SDD_transform[$trxnid],$sdd_contribution_id),

                );
            }
        }

        return $SDD_transform;
    }

    /**
     *  Creates the set of the payment information
     *
     * @param $base_contribution
     */
    private function addPaymentbyLink($mandate, $rel_contribution,$sdd_contribution_id)
    {

        if((float) $mandate['sdd_total'] < (float)$rel_contribution['total'])
        {
            $total = (float) $mandate['sdd_total'];
            $net = (float)$mandate['sdd_net_amount'];
            $fee = (float) $mandate['sdd_fee_amount'];
        }
        else
        {
            $total = (float)$rel_contribution['total'];
            $net = (float)$rel_contribution['net_amount'];
            $fee = (float)$rel_contribution['fee_net_ratio'] * $net;
        }

        //has to be filled up for the payment
        $pay_param = array(
            //'check_number',
            //'payment_processor_id',
            'fee_amount'=>  $fee,
            'total_amount' => $total,
            'contribution_id' => (int) $rel_contribution['contribution_id'],
            'net_amount' => $net,
            //'card_type_id',
            //'pan_truncation',
            'trxn_result_code'=>2,
            'payment_instrument_id'=> (int) $mandate['payment_instrument_id'],
            'trxn_id' => $this->createMandateTrxnId($mandate['sdd_mandate'],$sdd_contribution_id),
            'trxn_date' => $mandate['sdd_contribution_raw'],
            'contribution_date_copy' => $rel_contribution['contribution_date_raw'],
            'is_send_contribution_notification' => false,
            //'order_reference' => ,

        );

        return $pay_param;
    }

    /*
     * Just create the trxn id for purpose
     */
    private function createMandateTrxnId($mandate,$sdd_contribution_id)
    {
        return 'SDD@'.$mandate.'#finance#'.
            $this->_financial_id.'#contribution#'.$sdd_contribution_id;
    }
    /**
     *  Creates the set of the payment information
     *
     */
    private function addPaymentFragment($mandate, $sdd_contribution_id)
    {

        //has to be filled up for the payment
        $pay_param = array(
            //'check_number',
            //'payment_processor_id',
            'fee_amount'=>  (float) $mandate['sdd_fee_amount'],
            'total_amount' => (float) $mandate['sdd_total'],
            'contribution_id' => 0,
            'net_amount' => (float)$mandate['sdd_net_amount'],
            //'card_type_id',
            //'pan_truncation',
            'trxn_result_code'=>2,
            'payment_instrument_id'=> (int) $mandate['payment_instrument_id'],
            'trxn_id' => $this->createMandateTrxnId($mandate['sdd_mandate'], $sdd_contribution_id),
            'trxn_date' => $mandate['sdd_contribution_raw'],
            'contribution_date_copy' => null,
            'is_send_contribution_notification' => false,
            //'order_reference' => ,

        );

        return $pay_param;
    }

    private function copyPaymentFragment($contribution)
    {

        $pay_param = array(

            'total' => $contribution['total'],
            'fee_amount' => $contribution['fee_amount'],
            'fee_net_ratio' => $contribution['fee_net_ratio'],
            'net_amount' => $contribution['net_amount'],
            'contribution_date_raw' => $contribution['contribution_date_raw'],
        );

        return $pay_param;
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


    protected function getcssClassforPaystatus($status, $istrxn)
    {
        switch($status)
        {
            case 3:
                return 'fa-times-circle';

            case 5:
                return 'fa-spinner';

                case 6:
            case 2:
                return 'fa-circle-o';

            case 8:
                return 'fa-adjust';

            case 1:
                if(!$istrxn) return 'fa-flash';
                return 'fa-check-circle';

            case 4:
            case 11:
                return 'fa-flash';

            case 9:
            case 10:
                return 'fa-arrow-circle-left';


        }

        return 'fa-flash';

    }

    protected function getcssClassforSDDstatus($status)
    {
        switch($status)
        {
            case 'SENT':
                return 'fa-times-circle';

            case 'COMPLETE':
                return 'fa-spinner';

            case 'OOF':
            case 'FIRST':
                return 'fa-circle-o';

            case 8:
                return 'fa-adjust';

            case 1:
                return 'fa-check-circle';

            case 4:
            case 11:
                return 'fa-flash';

            case 9:
            case 10:
                return 'fa-arrow-circle-left';


        }

        return 'fa-flash';

    }


}
