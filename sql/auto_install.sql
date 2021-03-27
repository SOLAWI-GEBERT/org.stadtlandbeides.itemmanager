-- +--------------------------------------------------------------------+
-- | Copyright CiviCRM LLC. All rights reserved.                        |
-- |                                                                    |
-- | This work is published under the GNU AGPLv3 license with some      |
-- | permitted exceptions and without any warranty. For full license    |
-- | and copyright information, see https://civicrm.org/licensing       |
-- +--------------------------------------------------------------------+
--
-- Generated from schema.tpl
-- DO NOT EDIT.  Generated by CRM_Core_CodeGen
--


-- +--------------------------------------------------------------------+
-- | Copyright CiviCRM LLC. All rights reserved.                        |
-- |                                                                    |
-- | This work is published under the GNU AGPLv3 license with some      |
-- | permitted exceptions and without any warranty. For full license    |
-- | and copyright information, see https://civicrm.org/licensing       |
-- +--------------------------------------------------------------------+
--
-- Generated from drop.tpl
-- DO NOT EDIT.  Generated by CRM_Core_CodeGen
--
-- /*******************************************************
-- *
-- * Clean up the exisiting tables
-- *
-- *******************************************************/

SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `civicrm_itemmanager_settings`;
DROP TABLE IF EXISTS `civicrm_itemmanager_periods`;

SET FOREIGN_KEY_CHECKS=1;
-- /*******************************************************
-- *
-- * Create new tables
-- *
-- *******************************************************/

-- /*******************************************************
-- *
-- * civicrm_itemmanager_periods
-- *
-- * Stores the common data, how often the items will be repeated
-- *
-- *******************************************************/
CREATE TABLE `civicrm_itemmanager_periods` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Unique ItemmanagerPeriods ID',
     `price_set_id` int unsigned    COMMENT 'FK to civicrm_price_set',
     `period_start_on` datetime   DEFAULT NULL COMMENT 'If non-zero, do not show this field before the date specified',
     `periods` int unsigned   DEFAULT NULL COMMENT 'Number of periods at start',
     `period_type` int unsigned   DEFAULT NULL COMMENT 'Period interval type' 
,
        PRIMARY KEY (`id`)
 
 
,          CONSTRAINT FK_civicrm_itemmanager_periods_price_set_id FOREIGN KEY (`price_set_id`) REFERENCES `civicrm_price_set`(`id`) ON DELETE CASCADE  
)    ;

-- /*******************************************************
-- *
-- * civicrm_itemmanager_settings
-- *
-- * Stores the successor of an item
-- *
-- *******************************************************/
CREATE TABLE `civicrm_itemmanager_settings` (


     `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Unique ItemmanagerSettings ID',
     `price_field_value_id` int unsigned NOT NULL   COMMENT 'FK to civicrm_price_field_value',
     `itemmanager_periods_id` int unsigned NOT NULL   COMMENT 'FK to civicrm_itemmanager_periods',
     `itemmanager_successor_id` int unsigned   DEFAULT 0 COMMENT 'ID to itemmanager entry which is the successor',
     `ignore` tinyint   DEFAULT false COMMENT 'Ignore item for next period',
     `novitiate` tinyint   DEFAULT false COMMENT 'This item is for try out only' 
,
        PRIMARY KEY (`id`)
 
 
,          CONSTRAINT FK_civicrm_itemmanager_settings_price_field_value_id FOREIGN KEY (`price_field_value_id`) REFERENCES `civicrm_price_field_value`(`id`) ON DELETE CASCADE,          CONSTRAINT FK_civicrm_itemmanager_settings_itemmanager_periods_id FOREIGN KEY (`itemmanager_periods_id`) REFERENCES `civicrm_itemmanager_periods`(`id`) ON DELETE CASCADE  
)    ;

 