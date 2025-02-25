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

class CRM_Itemmanager_Util
{


    /**
     *  Just get our settings with API
     * @param $settingName
     * @return mixed
     * @throws CiviCRM_API3_Exception
     */
    public static function getSetting($settingName) {
        $result = civicrm_api3('setting', 'get', array(
            'return' => [$settingName],
            'sequential' => 1,
        ));
        $settingValue = $result['values'][0][$settingName];

        return $settingValue;
    }


    /**
     *  Returns a date format according to the period type
     *
     * @param DateTime $date
     * @param int $period_unit period type (0 day, 1 week, 2 month, 3 year )
     * @return string
     */
    public static function getReferenceDate(DateTime $date,int $period_unit)
    {
        switch($period_unit)
        {
            case 0:
                return $date->format('Y-m-d');
            case 1:
                return $date->format('Y-W');
            case 2:
                return $date->format('Y-m');
            case 3:
                return $date->format('Y');
        }

        return $date->format('Y-m-d');

    }

    /**
     * Check if there is tax value for selected financial type.
     * @param $financialTypeId
     * @return bool
     */
    public static function isTaxEnabledInFinancialType($financialTypeId) {
        $taxRates = CRM_Core_PseudoConstant::getTaxRates();
        return (isset($taxRates[$financialTypeId])) ? TRUE : FALSE;
    }

    /**
     * get tax value for selected financial type.
     * @param $financialTypeId
     * @return Float
     */
    public static function getTaxRateInFinancialType($financialTypeId) {
        $taxRates = CRM_Core_PseudoConstant::getTaxRates();
        return $taxRates[$financialTypeId];
    }


    /**
     * Function used to return total tax amount of a contribution, calculated from associated line item records
     *
     * @param int $contributionID
     *
     * @return money
     *       total tax amount in money format
     */
    public static function getTaxAmountTotalFromContributionID($contributionID):float {
        $taxAmount = CRM_Core_DAO::singleValueQuery("SELECT SUM(COALESCE(tax_amount,0)) FROM civicrm_line_item WHERE contribution_id = $contributionID AND qty > 0 ");
        return floatval($taxAmount);
    }



    /**
     * Function used to return total amount of a contribution, calculated from associated line item records
     *
     * @param int $contributionID
     *
     * @return money
     *       total tax amount in money format
     */
    public static function getAmountTotalFromContributionID($contributionID):float {
        $taxAmount = CRM_Core_DAO::singleValueQuery("SELECT SUM(COALESCE(line_total,0)) FROM civicrm_line_item WHERE contribution_id = $contributionID AND qty > 0 ");
        return floatval($taxAmount);
    }

    /**
     * Get financial account id has 'Sales Tax Account is' account relationship with financial type.
     *
     * @param int $financialTypeId
     *
     * @return int
     *   Financial Account Id
     */
    public static function getFinancialAccountId($financialTypeId) {
        $accountRel = key(CRM_Core_PseudoConstant::accountOptionValues('account_relationship', NULL, " AND v.name LIKE 'Sales Tax Account is' "));
        $searchParams = array(
            'entity_table' => 'civicrm_financial_type',
            'entity_id' => $financialTypeId,
            'account_relationship' => $accountRel,
        );
        $result = array();
        CRM_Financial_BAO_FinancialTypeAccount::retrieve($searchParams, $result);
        return $result['financial_account_id'];
    }

    /**
     * Get financial items from entity LineItem
     *
     * @param $lineItemId
     *
     * @result array
     *      Financial Items
     */
    public static function getFinancialItemsByLineItemId($lineItemId)
    {
        $searchParams = array(
            'entity_table' => CRM_Price_DAO_LineItem::getTableName(),
            'entity_id' => $lineItemId,
        );

        try{
            $result = civicrm_api3('FinancialItem', 'get', $searchParams);
        }
        catch (CiviCRM_API3_Exception $e) {
            // Handle error here.
            $errorMessage = $e->getMessage();
            $errorCode = $e->getErrorCode();
            $errorData = $e->getExtraParams();
            return [
                'is_error' => 1,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
                'error_data' => $errorData,
            ];
        }

        return $result;
    }


    /**
     * Returns the last contribution regarding the receive date from the given set of id's
     *
     * @param $contributionArray
     * @return id
     *
     */
    public static function getLastReceiveDateContribution($contributionArray) {

        $contribution_table = CRM_Contribute_DAO_Contribution::getTableName();
        $inset= implode(',',$contributionArray);


        try {
            $id = CRM_Core_DAO::singleValueQuery("
                    SELECT id from " . $contribution_table . " 
                    WHERE id IN (" . $inset . ") and receive_date = 
                    (SELECT max(receive_date) FROM " . $contribution_table . "
                     WHERE id IN(" . $inset . ") )");
            return (int)$id;
        } catch (Exception $e) {
            return -1;
        }


    }

    /**
     * Returns the first contribution regarding the receive date from the given set of id's
     *
     * @param $contributionArray
     * @return id
     *
     */
    public static function getFirstReceiveDateContribution($contributionArray) {

        $contribution_table = CRM_Contribute_DAO_Contribution::getTableName();
        $inset= implode(',',$contributionArray);


        try {
            $id = CRM_Core_DAO::singleValueQuery("
                    SELECT id from " . $contribution_table . " 
                    WHERE id IN (" . $inset . ") and receive_date = 
                    (SELECT min(receive_date) FROM " . $contribution_table . "
                     WHERE id IN(" . $inset . ") )");
            return (int)$id;
        } catch (Exception $e) {
            return -1;
        }


    }


    /**
     * Fetch the membertype data given by id
     *
     * @param $typeId
     * @return array
     */
    public static function getMembershipTypeById($typeId) {
        $params = [
            'id' => $typeId,
        ];

        try{
            $result = civicrm_api3('MembershipType', 'get', $params);
        }
        catch (CiviCRM_API3_Exception $e) {
            // Handle error here.
            $errorMessage = $e->getMessage();
            $errorCode = $e->getErrorCode();
            $errorData = $e->getExtraParams();
            return [
                'is_error' => 1,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
                'error_data' => $errorData,
            ];
        }

        return $result;
    }


    /**
     *  Gets the membership payment connection
     *
     *
     * @return array
     */
    public static function getMemberShipPaymentByMembershipId($membership_id,$payfilter=null) {
        $params = [
        'membership_id' => $membership_id,
        'options' => array(
            'limit' => 100,
            'sort' => "id DESC",
        ),
        ];

        if($payfilter)
            foreach ($payfilter as $filter_key => $filter_value)
                $params[$filter_key] = $filter_value;

        try{
        $result = civicrm_api3('MembershipPayment', 'get', $params);
        }
        catch (CiviCRM_API3_Exception $e) {
            // Handle error here.
            $errorMessage = $e->getMessage();
            $errorCode = $e->getErrorCode();
            return [
                'is_error' => 1,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
            ];
        }

        return $result;
    }


    /**
     *  Get the full record of the line item regarding to priceset
     *
     * @param $contribution_id
     * @return array
     */
    public static function getLineitemFullRecordByContributionId($contribution_id, $financial_id = null)
    {

        $params = [
            'contribution_id' => $contribution_id,
            'options' => array(
                'limit' => 100,
                'sort' => "price_field_value_id DESC",
            ),
        ];

        if($financial_id)
            $params['financial_type_id'] = $financial_id;

        $linearray = array();

        try{
            $lineitems = civicrm_api3('LineItem', 'get', $params);

            if( $lineitems['is_error']) return  $lineitems;

            foreach ($lineitems['values'] As $lineitem)
            {
                $pricefieldvalue = civicrm_api3('PriceFieldValue', 'getsingle', array('id' => (int) $lineitem['price_field_value_id']));
                $pricefield = civicrm_api3('PriceField', 'getsingle', array('id' => (int) $lineitem['price_field_id']));
                $priceset = civicrm_api3('PriceSet', 'getsingle', array('id' => (int) $pricefield['price_set_id']));

                $itemcollection = array(
                    'linedata' => $lineitem,
                    'fielddata' => $pricefield,
                    'valuedata' => $pricefieldvalue,
                    'setdata' => $priceset,
                );

                $linearray[] = $itemcollection;

            }

        }
        catch (CiviCRM_API3_Exception $e) {
            // Handle error here.
            $errorMessage = $e->getMessage();
            $errorCode = $e->getErrorCode();
            $errorData = $e->getExtraParams();
            return [
                'is_error' => 1,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
                'error_data' => $errorData,
            ];
        }

        return $linearray;
    }


    /**
     *  Get the memberships regarding the last membership_type_id
     *
     * @param $contactid
     * @return array
     */
    public static function getLastMembershipsByContactId($contactid) {

        $membertable = CRM_Member_DAO_Membership::getTableName();

        $last_query = "
            SELECT * FROM " . $membertable . "  t1
            JOIN (
            SELECT
                max(end_date) as last, membership_type_id
            FROM " . $membertable . " 
            WHERE contact_id = %1 group by membership_type_id) t2 ON t1.membership_type_id = t2.membership_type_id and t2.last = t1.end_date
            WHERE t1.contact_id = %1 
        ";


        try{
            $last_items = CRM_Core_DAO::executeQuery($last_query,
                array( 1 => array($contactid, 'Integer')));
            $result = $last_items->fetchAll();
            return [
                'is_error' => isset($result) ? 0 : 1,
                'values' => $result,
            ];

        }

    catch (Exception $e) {
            // Handle error here.
            $errorMessage = $e->getMessage();
            $errorCode = $e->getErrorCode();
            $errorData = $e->getExtraParams();
            return [
                'is_error' => 1,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
                'error_data' => $errorData,
            ];
        }


    }

    /**
     *  Get the SEPA mandates by contact id
     *
     * @param $contactid
     * @return array
     */
    public static function getSDDByContactId($contactid) {

        $mandatetable = CRM_Sepa_DAO_SEPAMandate::getTableName();

        $ooff_query = "
          SELECT *
          FROM " . $mandatetable . " t1
          WHERE t1.contact_id = %1
            AND t1.type = 'OOFF'
            AND t1.entity_table = 'civicrm_contribution'";

        $rcur_query = "
          SELECT *
          FROM " . $mandatetable . " t1
          WHERE t1.contact_id = %1
            AND t1.type = 'RCUR'
            AND t1.entity_table = 'civicrm_contribution_recur'
          ORDER BY t1.id DESC;";


        try{
            $ooff_items = CRM_Core_DAO::executeQuery($ooff_query,
                array( 1 => array($contactid, 'Integer')));

            $result_ooff = $ooff_items->fetchAll();

            $rcur_items = CRM_Core_DAO::executeQuery($rcur_query,
                array( 1 => array($contactid, 'Integer')));

            $rcur_ooff = $rcur_items->fetchAll();

            return [
                'is_error' => isset($result_ooff) && isset($rcur_ooff) ? 0 : 1,
                'ooff_values' => $result_ooff,
                'rcur_values' => $rcur_ooff,
            ];

        }

        catch (Exception $e) {
            // Handle error here.
            $errorMessage = $e->getMessage();
            $errorCode = $e->getErrorCode();
            $errorData = $e->getExtraParams();
            return [
                'is_error' => 1,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
                'error_data' => $errorData,
            ];
        }


    }


    /**
     *
     * Fetch the membership by the contact id
     *
     * @param $contactId
     * @return array Returns the membership together with type and payment
     */
    public static function getSDDFullRecordByContactId($contactId, $financial_id=null)
    {

        $sddfullarray = array();


        try{
            $sddarray = self::getSDDByContactId($contactId);

            if($sddarray['is_error']) return $sddarray;
            foreach ($sddarray['ooff_values'] As $sdd)
            {
                $id = $sdd['entity_id'];

                $param = array(
                    'id' => (int) $id,
                );

                if($financial_id)
                   $param['financial_type_id'] = $financial_id;

                $paydata = array();

                $contribution = civicrm_api3('Contribution', 'get', $param);

                if($contribution['is_error']) return $sddarray;
                $counter = count($contribution['values']);
                if($counter)
                    $paydata[$id] = reset($contribution['values']);

                $collection = array(
                    'sdddata' => $sdd,
                    'payinfo' => $paydata,
                );

                $sddfullarray[] = $collection;

            }

            foreach ($sddarray['rcur_values'] As $sdd)
            {
                $id = $sdd['entity_id'];

                $param = array(
                    'contribution_recur_id' => (int) $id,
                );

                if($financial_id)
                    $param['financial_type_id'] = $financial_id;

                $contributions = civicrm_api3('Contribution', 'get', $param);

                if($contributions['is_error']) return $sddarray;

                $paydata = array();
                foreach ($contributions['values'] as $contribution)
                    $paydata[(int)$contribution['id']] = $contribution;


                $collection = array(
                    'sdddata' => $sdd,
                    'payinfo' => $paydata,
                );

                $sddfullarray[] = $collection;

            }

            return [
                'is_error' => isset($sddarray) ? 0 : 1,
                'values' => $sddfullarray,
            ];


        }
        catch (CiviCRM_API3_Exception $e) {
            // Handle error here.
            $errorMessage = $e->getMessage();
            $errorCode = $e->getErrorCode();
            $errorData = $e->getExtraParams();
            return [
                'is_error' => 1,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
                'error_data' => $errorData,
            ];
        }


    }

    /**
     *
     * Fetch the membership by the contact id
     *
     * @param $contactId
     * @return array Returns the membership together with type and payment
     */
    public static function getLastMemberShipsFullRecordByContactId($contactId, $payfilter=null)
    {

        $memberarray = array();

        try{
            $memberdata = self::getLastMembershipsByContactId($contactId);

            if($memberdata['is_error']) return $memberdata;
            foreach ($memberdata['values'] As $memberitem)
            {
                $typedata = self::getMembershipTypeById($memberitem['membership_type_id']);
                if($typedata['is_error']) return $typedata;
                $paydata = self::getMemberShipPaymentByMembershipId($memberitem['id'],$payfilter);
                if($paydata['is_error']) return $paydata;

                $statusparams = [
                    'id' => $memberitem['status_id'],
                ];

                $statusdata = civicrm_api3('MembershipStatus', 'getsingle', $statusparams);

                $membercollection = array(
                    'memberdata' => $memberitem,
                    'typeinfo' => reset($typedata['values']),
                    'payinfo' => $paydata['values'],
                    'status' => $statusdata['label'],
                    'member_active' => $statusdata['is_current_member'],
                );

                $memberarray[] = $membercollection;

            }


            return [
                'is_error' => isset($memberarray) ? 0 : 1,
                'values' => $memberarray,
            ];


        }
        catch (CiviCRM_API3_Exception $e) {
            // Handle error here.
            $errorMessage = $e->getMessage();
            $errorCode = $e->getErrorCode();
            return [
                'is_error' => 1,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
            ];
        }


    }



    /**
     *  Returns the Entity Transaktion for a financial item entity
     *
     * @param $financialItemId
     * @return array
     */
    public static function getFinancialEntityTrxnByFinancialItemId($financialItemId)
    {
        $searchParams = array(
            'entity_table' => CRM_Financial_DAO_FinancialItem::getTableName(),
            'entity_id' => $financialItemId,
        );

        try{
            $result = civicrm_api3('EntityFinancialTrxn', 'get', $searchParams);
        }
        catch (CiviCRM_API3_Exception $e) {
            // Handle error here.
            $errorMessage = $e->getMessage();
            $errorCode = $e->getErrorCode();
            $errorData = $e->getExtraParams();
            return [
                'is_error' => 1,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
                'error_data' => $errorData,
            ];
        }

        return $result;
    }

    /**
     * Returns the Entity Transaktion for contribution entity
     * @param $contributionId
     * @return array
     */
    public static function getFinancialEntityIdTrxnByContributionId($contributionId)
    {
        $searchParams = array(
            'entity_table' => CRM_Contribute_DAO_Contribution::getTableName(),
            'entity_id' => $contributionId,
        );

        try{
            $result = civicrm_api3('EntityFinancialTrxn', 'get', $searchParams);
        }
        catch (CiviCRM_API3_Exception $e) {
            // Handle error here.
            $errorMessage = $e->getMessage();
            $errorCode = $e->getErrorCode();
            $errorData = $e->getExtraParams();
            return [
                'is_error' => 1,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
                'error_data' => $errorData,
            ];
        }

        return $result;
    }

    /**
     *
     *  Collects all related account infos together with the financial item by for line items
     *
     * @param $lineItemId
     * @return array
     */
    public static function getFinancialFullRecordsByLineItemId($lineItemId)
    {
        $financeitems = array();
        $itemsdata = self::getFinancialItemsByLineItemId($lineItemId);

        if($itemsdata['is_error']) return $itemsdata;
        foreach ($itemsdata['values'] As $financeitem)
        {
            $accountdata = self::getFinancialAccountInfosByAccountId($financeitem['financial_account_id']);
            if($accountdata['is_error']) return $accountdata;

            $financecollection = array(
              'financeitem' => $financeitem,
              'accountinfo' => $accountdata,
            );

            $financeitems[] = $financecollection;

        }


        return [
            'is_error' => isset($financeitems) ? 0 : 1,
            'values' => $financeitems,
            ];

    }



    /**
     *
     * Retrieves the Financial Account given by ID
     *
     * @param $financialAccountId
     * @return array
     *      Returns the Account Data
     */
    public static function getFinancialAccountInfosByAccountId($financialAccountId)
    {
        try{
            $result = civicrm_api3('FinancialAccount', 'getsingle', array('id' => $financialAccountId));
        }
        catch (CiviCRM_API3_Exception $e) {
            // Handle error here.
            $errorMessage = $e->getMessage();
            $errorCode = $e->getErrorCode();
            $errorData = $e->getExtraParams();
            return [
                'is_error' => 1,
                'error_message' => $errorMessage,
                'error_code' => $errorCode,
                'error_data' => $errorData,
            ];
        }

        return $result;

    }


    /**
     *  Returns an the id of the price set given by the field value id
     *
     * @param $fielvaluedid Id ot the Price Field Value
     * @return array Related Priceset, Related Price Field, Price Field Value
     */
    public static function getPriceSetRefByFieldValueId($fielvaluedid){

        try {
            $pricefieldvalue = civicrm_api3('PriceFieldValue', 'getsingle',
                array('id' => (int)$fielvaluedid,'return' => ['id','price_field_id']));
            if (!isset($pricefieldvalue)) {
                return array(
                    'iserror' => 1,
                    'error'=>'Could not get the price field value '.$fielvaluedid );
            }
            $pricefield = civicrm_api3('PriceField', 'getsingle',
                array('id' => (int)$pricefieldvalue['price_field_id'],'return' => ['id','price_set_id']));
            if (!isset($pricefield)) {
                return array(
                    'iserror' => 1,
                    'error'=>'Could not get the price field '.(int)$pricefieldvalue['price_field_id']);
            }
            $priceset = civicrm_api3('PriceSet', 'getsingle',
                array('id' => (int)$pricefield['price_set_id'],'return' =>'id'));
            if (!isset($priceset)) {
                return array(
                    'iserror' => 1,
                    'error'=>'Could not get the price set '.(int)$pricefield['price_set_id']);
            }

            return array(
                'iserror' => 0,
                'price_id' => $priceset['id'],

            );


        } catch (CiviCRM_API3_Exception $e) {
            return array(
                'iserror' => 1,
                'error'=>$e->getMessage());
        }

    }


    /**
     *  Add a choice to for getChoicesOfPricefieldsByFieldID with references to the price_field and price_field_value
     *
     * @param $choices
     * @param $fieldid
     * @param $periodtype type of the range
     * @param $index
     * @param $periods
     * @param $start_on
     * @param bool $help
     * @param bool $new missing fieldvalues from the successor
     * @param bool $reverse periods are planed reverse
     */
    private static function addChoice(&$choices,$fielvaluedid,$periodidx,
                                      $index,$periods,$start_on,$lastDate,
                                        $help=false, $new = false, $reverse = false)
    {

        $typemap = array();
        $typemap[0] = 'D';
        $typemap[1] = 'D';
        $typemap[2] = 'M';
        $typemap[3] = 'Y';
        $period_selected = $typemap[$periodidx];
        $correctionmap = array();
        $correctionmap[0] = 0;
        $correctionmap[1] = 7;
        $correctionmap[2] = 0;
        $correctionmap[3] = 0;
        $correction_selected = $correctionmap[$periodidx];



        if($fielvaluedid == 0)
        {
            self::addEmptyChoice($choices, $index, 'Price Field Value 0 ist not allowed');
            return;
        }

        try {
            $pricefieldvalue = civicrm_api3('PriceFieldValue', 'getsingle', array('id' => (int)$fielvaluedid));//In case of an error
            if (!isset($pricefieldvalue)) {
                self::addEmptyChoice($choices, $index, 'Can no find the related Price Field Value');
                return;
            }
            $pricefield = civicrm_api3('PriceField', 'getsingle', array('id' => (int)$pricefieldvalue['price_field_id']));//In case of an error
            if (!isset($pricefield)) {
                self::addEmptyChoice($choices, $index, 'Can no find the related Price Field');
                return;
            }
            $priceset = civicrm_api3('PriceSet', 'getsingle', array('id' => (int)$pricefield['price_set_id']));//In case of an error
            if (!isset($priceset)) {
                self::addEmptyChoice($choices, $index, 'Can no find the related Price Field');
                return;
            }
        } catch (CiviCRM_API3_Exception $e) {
            self::addEmptyChoice($choices, $index, $e->getMessage());
            return;
        }

        $choices['help_pre'][$index] =  $pricefield['help_pre'] .'</br>'.
            $pricefieldvalue['help_pre'];

        //calculate the interval price
        $summary_price = $reverse ?  $pricefieldvalue['amount']: $pricefieldvalue['amount']/$periods;
        $summary_display = CRM_Utils_Money::format($summary_price, NULL, NULL, TRUE);
        //just copy field data for info
        $active_on = CRM_Utils_Date::customFormat(date_create( $pricefield['active_on'])->format('Y-m-d'),
            Civi::settings()->get('dateformatshortdate'));
        $expire_on = CRM_Utils_Date::customFormat(date_create( $pricefield['expire_on'])->format('Y-m-d'),
            Civi::settings()->get('dateformatshortdate'));

        $choices['item_selection'][$index] = '('.(int)$pricefield['price_set_id'].') '.$pricefieldvalue['label'].' '.
                                                        $priceset['title'];

        $choices['field_value_selection'][$index] = (int)$fielvaluedid;
        $choices['price_set_selection'][$index] = (int)$pricefield['price_set_id'];
        $choices['periodtype'][$index] = (int)$periodidx;

        //decide the correct start date
        $new_start_timestamp = date_create($start_on);
        $new_month = (int)$new_start_timestamp->format('n');
        $month = $new_start_timestamp->format('m');
        $new_day = $new_start_timestamp->format('d');
        $old_end_timestamp = date_create($lastDate);
        $old_month = (int)$old_end_timestamp -> format('n');
        if ($new_month > $old_month)
            $year = (int)$old_end_timestamp -> format('Y') ;
        else
            $year = (int)$old_end_timestamp -> format('Y') + 1;

        $start_formated = CRM_Utils_Date::customFormat($year.'-'.$month.'-'.$new_day,
            Civi::settings()->get('dateformatshortdate'));

        //create container
        $choices['period_data'][$index] = array();
        $choices['period_selection'][$index] = array();

        for ($i = $periods; $i > 0; $i--) {

            $duration = $i + $correction_selected;
            $end_date = new DateTime($year.'-'.$month.'-'.$new_day);
            $end_date->add(new DateInterval('P'.$duration.$period_selected));
            $end_date->sub(new DateInterval('P1D'));
            $end_formated = CRM_Utils_Date::customFormat($end_date->format('Y-m-d'),
                Civi::settings()->get('dateformatshortdate'));
            $choices['period_selection'][$index][$i] = $i;
            $choices['period_data'][$index][$i] = array(
                'period_iso_start_on' => $year.'-'.$month.'-'.$new_day,
                'period_start_on' => $start_formated,
                'period_end_on' => $end_formated,
                'active_on' => $active_on,
                'expire_on' => $expire_on,
                'interval_price' => $summary_display,
                );


        }

        //add 0
        $choices['period_selection'][$index][$i] = 0;
        $choices['period_data'][$index][$i] = array(
            'period_iso_start_on' => '-',
            'period_start_on' => '-',
            'period_end_on' => '-',
            'active_on' => '-',
            'expire_on' => '-',
            'interval_price' => '-',
        );


        return;
    }

    /**
     *  Creates for getChoicesOfPricefieldsByFieldID an empty set
     *
     * @param $choices
     */
    private static function addEmptyChoice(&$choices,$index=0,$error='')
    {


        if($error == '')
            $choices['item_selection'][$index] = '';
        else
            $choices['item_selection'][$index] = $error;

        $choices['field_value_selection'][$index] = null;
        $choices['price_set_selection'][$index] = null;

        //create container
        $choices['period_data'][$index] = array();
        $choices['period_selection'][$index] = array();

        $choices['period_selection'][$index][0] = 0;
        $choices['period_data'][$index][0] = array(
            'period_iso_start_on' => '-',
            'period_start_on' => '-',
            'period_end_on' => '-',
            'active_on' => '-',
            'expire_on' => '-',
            'interval_price' => '-',
        );
        return;
    }


    public static function getLastPricefieldSuccessor($currentFieldValueId)
    {
        $item = new CRM_Itemmanager_BAO_ItemmanagerSettings();
        $valid = $item->get('price_field_value_id', $currentFieldValueId);

        if (!$valid)
            return $currentFieldValueId;

        if($item->itemmanager_successor_id == 0)
            return $item->price_field_value_id;

        $successor_item = new CRM_Itemmanager_BAO_ItemmanagerSettings();
        $valid = $successor_item->get('id',$item->itemmanager_successor_id);
        if(!$valid)
            return $currentFieldValueId;

        return CRM_Itemmanager_Util::getLastPricefieldSuccessor($successor_item->price_field_value_id);
    }


    /**
     * Returns an array of the successor item settings
     *
     * @param int $priceSetId old price set id
     * @return array
     * @throws CRM_Core_Exception
     * @throws \Civi\API\Exception\UnauthorizedException
     */
    public static function getSuccessorItemsettingsByPriceId(int $priceSetId):array
    {
        $empty = array(array(),array());

        $period_result = \Civi\Api4\ItemmanagerPeriods::get()
            ->addWhere('price_set_id', '=', $priceSetId)
            ->setCheckPermissions(FALSE)
            ->execute();

        $old_selected = $period_result->first();

        if (!isset($old_selected)) {
            return $empty ;
        }

        $old_period = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
        $valid = $old_period->get('id',$old_selected['id']);
        if(!$valid)
        {
            return $empty;
        }

        //switch to the successor
        $new_period = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
        $valid = $new_period->get('id',$old_period->itemmanager_period_successor_id);
        if(!$valid)
        {
            return $empty ;
        }

        // we need just the relevant items
        $period_result = \Civi\Api4\ItemmanagerSettings::get()
            ->addWhere('itemmanager_periods_id', '=', $new_period->id)
            ->addWhere('ignore', '=',FALSE)
            ->addWhere('novitiate','=',FALSE)
            ->setCheckPermissions(FALSE)
            ->execute();
        if(!$period_result or $period_result->count() == 0 )
        {
            return $empty;
        }

        return array($new_period, $period_result->getArrayCopy()) ;

    }

    /**
     *  Returns an array of available Price Fieldvalues for missing or new price field values
     *
     * @param $itemmanager_record missing itemmanager item
     * @param $itemmanager_period related itemmanager period
     * @param $lastDate
     * @return array
     * @throws API_Exception
     * @throws CRM_Core_Exception
     * @throws \Civi\API\Exception\UnauthorizedException
     */
    public static function getMissingChoicesOfPricefieldsByFieldID(array $itemmanager_record,
                                               CRM_Itemmanager_BAO_ItemmanagerPeriods $itemmanager_period,
                                                        $lastDate) : array
    {
        $choices = array(
            'itemmanager_selection' => array(),
            'price_set_selection'=> array(),
            'field_value_selection' => array(),
            'item_selection' => array(),
            'period_selection' => array(),
            'period_data' => array(),
            'help_pre' => array(),
        );

        $choices['itemmanager_selection'][0] = $itemmanager_record['id'];
        self::addChoice(
            $choices,
            $itemmanager_record['price_field_value_id'],
            $itemmanager_period->period_type,
            0,
            $itemmanager_period->periods,
            $itemmanager_period->period_start_on,
            $lastDate,
            true,
            true
        );

        $choices['itemmanager_selection'][1] = null;
        self::addEmptyChoice($choices,1);
        return $choices;




    }

    /**
     *  Returns an array of available Price Fieldvalues and calculates the next period
     *
     * @param $currentFieldValueId
     * @param $lastDate
     * @return array
     * @throws API_Exception
     * @throws CRM_Core_Exception
     * @throws \Civi\API\Exception\UnauthorizedException
     */
    public static function getChoicesOfPricefieldsByFieldID($currentFieldValueId,$lastDate)
    {
        $choices = array(
            'itemmanager_selection' => array(),
            'price_set_selection'=> array(),
            'field_value_selection' => array(),
            'item_selection' => array(),
            'period_selection' => array(),
            'period_data' => array(),
            'help_pre' => array(),
        );
        $successor_item = array();


        $old_item = new CRM_Itemmanager_BAO_ItemmanagerSettings();
        $valid = $old_item->get('price_field_value_id', $currentFieldValueId);
        if(!$valid)
        {
            $choices['itemmanager_selection'][0] = null;
            self::addEmptyChoice($choices,0,'Missing Price Field Value Id');
            return $choices;
        }

        $old_period = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
        $valid = $old_period->get('id',$old_item->itemmanager_periods_id);
        if(!$valid)
        {
            $choices['itemmanager_selection'][0] = null;
            self::addEmptyChoice($choices,0,'Missing Itemmanager Period');
            return $choices;
        }



        //if we are the last given data record
        if($old_item->itemmanager_successor_id == 0)
        {
            $choices['itemmanager_selection'][0] = $old_item->id;
            self::addChoice(
                $choices,
                $old_item->price_field_value_id,
                $old_period->period_type,
                0,
                $old_period->periods,
                $old_period->period_start_on,
                $lastDate,
                true,
                false,
                (bool)$old_period->reverse

            );

            $choices['itemmanager_selection'][1] = null;
            self::addEmptyChoice($choices,1);
            return $choices;
        }

        $successor_item = new CRM_Itemmanager_BAO_ItemmanagerSettings();
        $valid = $successor_item->get('id',$old_item->itemmanager_successor_id);
        if(!$valid)
        {
            $choices['itemmanager_selection'][0] = null;
            self::addEmptyChoice($choices,0,'Missing successor record');
            return $choices;
        }

        $successor_period = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
        $valid = $successor_period->get('id',$successor_item->itemmanager_periods_id);
        if(!$valid)
        {
            $choices['itemmanager_selection'][0] = null;
            self::addEmptyChoice($choices,0,'Missing Itemmanager Successor Period');
            return $choices;
        }

        $choices['itemmanager_selection'][0] = $successor_item->id;
        self::addChoice(
            $choices,
            $successor_item->price_field_value_id,
            $successor_period->period_type,
            0,
            $successor_period->periods,
            $successor_period->period_start_on,
            $lastDate,
            true,
            false,
            (bool)$successor_period->reverse
        );

        $choices['itemmanager_selection'][1] = $old_item->id;
        self::addChoice(
            $choices,
            $old_item->price_field_value_id,
            $old_period-> period_type,
            1,
            $old_period->periods,
            $old_period->period_start_on,
            $lastDate,
            false,
            false,
            (bool)$old_period->reverse

        );

        $choices['itemmanager_selection'][2] = null;
        self::addEmptyChoice($choices,2);
        return $choices;





    }


}