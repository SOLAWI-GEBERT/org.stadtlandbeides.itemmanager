-- /*******************************************************
-- *
-- * add fields to itemmanager periods
-- *
-- *******************************************************/
ALTER TABLE `civicrm_itemmanager_periods`
    ADD COLUMN `itemmanager_period_successor_id` int unsigned DEFAULT 0  COMMENT 'Successor ItemmanagerPeriods ID';




