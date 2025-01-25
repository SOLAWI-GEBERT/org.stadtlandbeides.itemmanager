<?php
use CRM_Itemmanager_ExtensionUtil as E;

class CRM_Itemmanager_BAO_ItemmanagerPeriods extends CRM_Itemmanager_DAO_ItemmanagerPeriods {

  /**
   * Create a new ItemmanagerPeriods based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Itemmanager_DAO_ItemmanagerPeriods|NULL
   *
   */
  public static function create($params) {
    $className = 'CRM_Itemmanager_DAO_ItemmanagerPeriods';
    $entityName = 'ItemmanagerPeriods';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

}
