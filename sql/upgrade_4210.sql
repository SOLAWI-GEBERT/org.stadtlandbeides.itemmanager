-- /*******************************************************
-- *
-- * add fields to itemmanager periods
-- *
-- *******************************************************/
ALTER TABLE `civicrm_itemmanager_periods`
    ADD COLUMN `hide` tinyint DEFAULT FALSE COMMENT 'The period is not visible anymore.';




