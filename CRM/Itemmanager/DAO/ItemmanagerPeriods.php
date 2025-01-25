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
 * @property string $price_set_id 
 * @property string $period_start_on 
 * @property string $periods 
 * @property string $period_type 
 * @property string $itemmanager_period_successor_id 
 * @property bool|string $hide
 * @property bool|string $reverse
 */
class CRM_Itemmanager_DAO_ItemmanagerPeriods extends CRM_Itemmanager_DAO_Base {

  /**
   * Required by older versions of CiviCRM (<5.74).
   * @var string
   */
  public static $_tableName = 'civicrm_itemmanager_periods';

}
