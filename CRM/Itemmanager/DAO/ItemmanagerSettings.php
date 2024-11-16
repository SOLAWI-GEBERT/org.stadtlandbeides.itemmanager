<?php

/**
 * DAOs provide an OOP-style facade for reading and writing database records.
 *
 * DAOs are a primary source for metadata in older versions of CiviCRM (<5.74)
 * and are required for some subsystems (such as APIv3).
 *
 * This stub provides compatibility. It is not intended to be modified in a
 * substantive way. Property annotations may be added, but are not required.
 * @property string $id 
 * @property string $price_field_value_id 
 * @property string $itemmanager_periods_id 
 * @property string $itemmanager_successor_id 
 * @property bool|string $ignore 
 * @property bool|string $extend 
 * @property bool|string $novitiate 
 * @property bool|string $enable_period_exception 
 * @property string $exception_periods 
 */
class CRM_Itemmanager_DAO_ItemmanagerSettings extends CRM_Itemmanager_DAO_Base {

  /**
   * Required by older versions of CiviCRM (<5.74).
   * @var string
   */
  public static $_tableName = 'civicrm_itemmanager_settings';

}
