-- /*******************************************************
-- *
-- * add fields to settings
-- *
-- *******************************************************/
ALTER TABLE `civicrm_itemmanager_settings`
    ADD COLUMN enable_period_exception tinyint DEFAULT FALSE COMMENT 'The parent period should not used',
    ADD COLUMN exception_periods int unsigned DEFAULT NULL COMMENT 'The exceptional periods';




