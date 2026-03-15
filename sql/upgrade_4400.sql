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

-- /*******************************************************
-- *
-- * Extend line item money columns to 4 decimal places
-- * to match MONEY_PRECISION used in itemmanager calculations
-- *
-- *******************************************************/
ALTER TABLE `civicrm_line_item`
    MODIFY COLUMN `unit_price` decimal(20,4) NOT NULL DEFAULT 0 COMMENT 'Unit price of line item',
    MODIFY COLUMN `line_total` decimal(20,4) NOT NULL DEFAULT 0 COMMENT 'Line total of line item',
    MODIFY COLUMN `tax_amount` decimal(20,4) DEFAULT NULL COMMENT 'Tax amount of line item';
