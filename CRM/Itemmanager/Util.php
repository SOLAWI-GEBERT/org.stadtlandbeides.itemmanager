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
    public static function getTaxAmountTotalFromContributionID($contributionID) {
        $taxAmount = CRM_Core_DAO::singleValueQuery("SELECT SUM(COALESCE(tax_amount,0)) FROM civicrm_line_item WHERE contribution_id = $contributionID AND qty > 0 ");
        return CRM_Utils_Money::format($taxAmount, NULL, NULL, TRUE);
    }



    /**
     * Function used to return total amount of a contribution, calculated from associated line item records
     *
     * @param int $contributionID
     *
     * @return money
     *       total tax amount in money format
     */
    public static function getAmountTotalFromContributionID($contributionID) {
        $taxAmount = CRM_Core_DAO::singleValueQuery("SELECT SUM(COALESCE(line_total,0)) FROM civicrm_line_item WHERE contribution_id = $contributionID AND qty > 0 ");
        return CRM_Utils_Money::format($taxAmount, NULL, NULL, TRUE);
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
        return CRM_Utils_Array::value('financial_account_id', $result);
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
    public static function getMemberShipPaymentByMembershipId($membership_id) {
        $params = [
        'membership_id' => $membership_id,
        ];

        try{
        $result = civicrm_api3('MembershipPayment', 'get', $params);
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
     *  Get the full record of the line item regarding to priceset
     *
     * @param $contribution_id
     * @return array
     */
    public static function getLineitemFullRecordByContributionId($contribution_id)
    {

        $params = [
            'contribution_id' => $contribution_id,
        ];

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
     *
     * Fetch the membership by the contact id
     *
     * @param $contactId
     * @return array Returns the membership together with type and payment
     */
    public static function getLastMemberShipsFullRecordByContactId($contactId)
    {

        $memberarray = array();

        try{
            $memberdata = self::getLastMembershipsByContactId($contactId);

            if($memberdata['is_error']) return $memberdata;
            foreach ($memberdata['values'] As $memberitem)
            {
                $typedata = self::getMembershipTypeById($memberitem['membership_type_id']);
                if($typedata['is_error']) return $typedata;
                $paydata = self::getMemberShipPaymentByMembershipId($memberitem['id']);
                if($paydata['is_error']) return $paydata;

                $membercollection = array(
                    'memberdata' => $memberitem,
                    'typeinfo' => reset($typedata['values']),
                    'payinfo' => $paydata['values'],
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
     *  Add a choice to for getChoicesOfPricefieldsByFieldID with references to the price_field and price_field_value
     *
     * @param $choices
     * @param $fieldid
     * @param $index
     * @param $periods
     * @param $start_on
     */
    private static function addChoice(&$choices,$fielvaluedid,$index,$periods,$start_on,$lastDate,$help=false)
    {
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

        $choices['help_pre'][$index] =  CRM_Utils_Array::value('help_pre',$pricefield) .'</br>'.
            CRM_Utils_Array::value('help_pre',$pricefieldvalue);

        //calculate the interval price
        $summary_price = CRM_Utils_Array::value('amount',$pricefieldvalue)/$periods;
        $summary_display = CRM_Utils_Money::format($summary_price, NULL, NULL, TRUE);
        //just copy field data for info
        $active_on = CRM_Utils_Date::customFormat(date_create( CRM_Utils_Array::value('active_on',$pricefield))->format('Y-m-d'),
            Civi::settings()->get('dateformatshortdate'));
        $expire_on = CRM_Utils_Date::customFormat(date_create( CRM_Utils_Array::value('expire_on',$pricefield))->format('Y-m-d'),
            Civi::settings()->get('dateformatshortdate'));

        $choices['item_selection'][$index] = CRM_Utils_Array::value('label',$pricefieldvalue).' '.
                                                        CRM_Utils_Array::value('title',$priceset);

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

            $end_date = new DateTime($year.'-'.$month.'-'.$new_day);
            $end_date->add(new DateInterval('P'.$i.'M'));
            $end_formated = CRM_Utils_Date::customFormat($end_date->format('Y-m-d'),
                Civi::settings()->get('dateformatshortdate'));
            $choices['period_selection'][$index][$i] = $i;
            $choices['period_data'][$index][$i] = array(
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

        //create container
        $choices['period_data'][$index] = array();
        $choices['period_selection'][$index] = array();

        $choices['period_selection'][$index][0] = 0;
        $choices['period_data'][$index][0] = array(
            'period_start_on' => '-',
            'period_end_on' => '-',
            'active_on' => '-',
            'expire_on' => '-',
            'interval_price' => '-',
        );
        return;
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
            'item_selection' => array(),
            'period_selection' => array(),
            'period_data' => array(),
            'help_pre' => array(),
        );
        $olditem = array();
        $successor_item = array();



        $old_id = CRM_Itemmanager_BAO_ItemmanagerSettings::getFieldValue('CRM_Itemmanager_DAO_ItemmanagerSettings',
            $currentFieldValueId , 'id','price_field_value_id',True);
        if(!isset($old_id))
        {

            self::addEmptyChoice($choices,0,'Missing Price Field Value Id');
            return $choices;
        }



        $old_record = \Civi\Api4\ItemmanagerSettings::get()
            ->addWhere('id', '=', $old_id)
            ->setCheckPermissions(FALSE)
            ->execute();

        $olditem = reset($old_record);



        //if we are the last given data record
        if($olditem['itemmanager_successor_id'] == 0)
        {

            self::addChoice(
                $choices,
                $currentFieldValueId,
                0,
                CRM_Utils_Array::value('periods',$olditem),
                CRM_Utils_Array::value('period_start_on',$olditem),
                $lastDate,
                true
                );

            self::addEmptyChoice($choices,1);
            return $choices;
        }

        $successor_record = \Civi\Api4\ItemmanagerSettings::get()
            ->addWhere('id', '=', $olditem['itemmanager_successor_id'])
            ->setCheckPermissions(FALSE)
            ->execute();

        $successor_item = reset($successor_record);

        if(!isset($successor_item))
        {

            self::addEmptyChoice($choices,0,'Missing successor record');
            return $choices;
        }


        self::addChoice(
            $choices,
            CRM_Utils_Array::value('price_field_value_id',$successor_item),
            0,
            CRM_Utils_Array::value('periods',$successor_item),
            CRM_Utils_Array::value('period_start_on',$successor_item),
            $lastDate,
            true
        );


        self::addChoice(
            $choices,
            $currentFieldValueId,
            1,
            CRM_Utils_Array::value('periods',$olditem),
            CRM_Utils_Array::value('period_start_on',$olditem),
            $lastDate

        );

        self::addEmptyChoice($choices,2);
        return $choices;





    }


}