<?php
use CRM_Itemmanager_ExtensionUtil as E;

class CRM_Itemmanager_BAO_ItemmanagerSettings extends CRM_Itemmanager_DAO_ItemmanagerSettings {

  /**
   * Create a new ItemmanagerSettings based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_Itemmanager_DAO_ItemmanagerSettings|NULL
   */
  public static function create($params) {
    $className = 'CRM_Itemmanager_DAO_ItemmanagerSettings';
    $entityName = 'ItemmanagerSettings';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, $params['id'], $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

}
