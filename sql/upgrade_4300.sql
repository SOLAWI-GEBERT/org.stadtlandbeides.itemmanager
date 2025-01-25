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
-- * add fields to itemmanager periods
-- *
-- *******************************************************/
ALTER TABLE `civicrm_itemmanager_periods`
    ADD COLUMN `reverse` tinyint DEFAULT FALSE COMMENT 'Reverse the period.';

-- /*******************************************************
-- *
-- * add fields to settings
-- *
-- *******************************************************/
ALTER TABLE `civicrm_itemmanager_settings`
    ADD COLUMN `bidding` tinyint DEFAULT FALSE COMMENT 'Item is bidding round';