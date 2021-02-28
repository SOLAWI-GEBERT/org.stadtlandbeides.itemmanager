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

}