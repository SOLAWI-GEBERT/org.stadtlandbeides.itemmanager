-- /*******************************************************
-- *
-- * add fields to settings
-- *
-- *******************************************************/
ALTER TABLE `civicrm_itemmanager_settings`
    ADD COLUMN extend tinyint DEFAULT FALSE COMMENT 'The item should be added';




