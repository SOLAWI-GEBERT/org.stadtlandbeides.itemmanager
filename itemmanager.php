<?php

require_once 'itemmanager.civix.php';
// phpcs:disable
use CRM_Itemmanager_ExtensionUtil as E;
// phpcs:enable

/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function itemmanager_civicrm_config(&$config) {
  _itemmanager_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function itemmanager_civicrm_xmlMenu(&$files) {
  _itemmanager_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function itemmanager_civicrm_install() {
  _itemmanager_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function itemmanager_civicrm_postInstall() {
  _itemmanager_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function itemmanager_civicrm_uninstall() {
  _itemmanager_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function itemmanager_civicrm_enable() {
  _itemmanager_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function itemmanager_civicrm_disable() {
  _itemmanager_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function itemmanager_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _itemmanager_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function itemmanager_civicrm_managed(&$entities) {
  _itemmanager_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function itemmanager_civicrm_caseTypes(&$caseTypes) {
  _itemmanager_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function itemmanager_civicrm_angularModules(&$angularModules) {
  _itemmanager_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function itemmanager_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _itemmanager_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function itemmanager_civicrm_entityTypes(&$entityTypes) {
  _itemmanager_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_themes().
 */
function itemmanager_civicrm_themes(&$themes) {
  _itemmanager_civix_civicrm_themes($themes);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
function itemmanager_civicrm_preProcess($formName, &$form) {

}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function itemmanager_civicrm_navigationMenu(&$menu) {

    $item_maintenance_url = 'civicrm/items/maintenance';

    _sepa_civix_insert_navigation_menu($menu,'CiviMember',array(
        'label' => ts('Itemmanager Maintenance',array('domain' => 'org.stadtlandbeides.itemmanager')),
        'name' => 'Itemmanager Maintenance',
        'url' => $item_maintenance_url,
        'permission' => 'administer CiviMember',
        'operator' => NULL,
        'separator' => 2,
        'active' => 1
    ));

    //add menu entry for Itemmanager settings to Administer>CiviContribute menu
    $items_settings_url = 'civicrm/admin/setting/itemmanager';

    _sepa_civix_insert_navigation_menu($menu, 'Administer/CiviContribute',array (
        'label' => ts('Itemmanager Settings',array('domain' => 'org.stadtlandbeides.itemmanager')),
        'name' => 'Itemmanager Settings',
        'url' => $items_settings_url,
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => 2,
        'active' => 1
    ));

  _itemmanager_civix_navigationMenu($menu);
}

/**
 * Implements hook_civicrm_tabset()
 *
 * Will inject the Itemmanager Dashboard tab
 */
function itemmanager_civicrm_tabset($tabsetName, &$tabs, $context) {
    if ($tabsetName == 'civicrm/contact/view' && !empty($context['contact_id'])) {
        $tabs[] = [
            'id'     => 'itemmanager',
            'url'    => CRM_Utils_System::url('civicrm/items/tab', "reset=1&force=1&cid={$context['contact_id']}&backtrace=1&smartyDebug=1"),
            'title'  => E::ts('Items Dashboard'),
            'weight' => 20
        ];
    }
}
