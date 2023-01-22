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
 * Implements hook_civicrm_pre().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_pre
 */
function itemmanager_civicrm_pre($op, $objectName, $id, &$params) {

    if ($objectName === 'Membership' && $op == 'edit') {

    }

}


/**
 * Implements hook_civicrm_preProcess().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_preProcess
 */
function itemmanager_civicrm_preProcess($formName, &$form) {

 if($formName == 'CRM_Member_Form_MembershipRenewal')
 {
     CRM_Core_Session::setStatus("Standard renew feature has been disabled. Use renew period instead.",
         ts('Warning', array('domain' => 'org.stadtlandbeides.itemmanager')), 'warning');


 }



}

function itemmanager_civicrm_renewPeriods( &$actions, $contactID ) {
    // add "create SEPA mandate action"
    if (CRM_Core_Permission::check('edit contributions')) {
        $actions['renew_item_periods'] = array(
            'title'           => ts("Renew Items Periods", array('domain' => 'org.stadtlandbeides.itemmanager')),
            'weight'          => 5,
            'ref'             => 'renew-items-periods',
            'key'             => 'renew_item_periods',
            'component'       => 'CiviContribute',
            'href'            => CRM_Utils_System::url('civicrm/items/renewperiods', "reset=1&cid={$contactID}"),
            'permissions'     => array('access CiviContribute', 'edit contributions')
        );
    }
}

/**
 * Implements hook_civicrm_links().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_links
 */
function itemmanager_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {



    if (($op == 'membership.selector.row' || $op == 'membership.tab.row') && $objectName == 'Membership') {

        $links[] = array(
            'name' => ts('Renew periods'),
            'url' => 'civicrm/items/renewperiods',
            'qs' => 'reset=1&cid=%%cid%%',
            'title' => ts("Renew Items Periods", array('domain' => 'org.stadtlandbeides.itemmanager')),
        );
    }


/* is now doubled
    if ($op == 'contribution.selector.row' && $objectName == 'Contribution') {
        $links[] = array(
            'name' => ts('Duplicate contribution'),
            'url' => 'civicrm/items/repaircontribution',
            'qs' => 'id=%%id%%&cid=%%cid%%&context=%%cxt%%',
            'title' => 'Repair missing contribution.',
        );
    } */
}


/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function itemmanager_civicrm_navigationMenu(&$menu) {


//    $item_maintenance_url = 'civicrm/items/maintenance';
//
//    _sepa_civix_insert_navigation_menu($menu,'CiviMember',array(
//        'label' => ts('Itemmanager Maintenance',array('domain' => 'org.stadtlandbeides.itemmanager')),
//        'name' => 'Itemmanager Maintenance',
//        'url' => $item_maintenance_url,
//        'permission' => 'administer CiviMember',
//        'operator' => NULL,
//        'separator' => 2,
//        'active' => 1
//    ));

    //add menu entry for Itemmanager settings to Administer>CiviContribute menu
    $items_settings_url = 'civicrm/admin/setting/itemmanager';

    _sepa_civix_insert_navigation_menu($menu, 'Administer/CiviMember',array (
        'label' => ts('Itemmanager Settings',array('domain' => 'org.stadtlandbeides.itemmanager')),
        'name' => 'Itemmanager Settings',
        'url' => $items_settings_url,
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => 2,
        'active' => 1
    ));

    $items_options_url = 'civicrm/admin/setting/itemmanageroptions';

    _sepa_civix_insert_navigation_menu($menu, 'Administer/CiviMember',array (
        'label' => ts('Itemmanager Options',array('domain' => 'org.stadtlandbeides.itemmanager')),
        'name' => 'Itemmanager Options',
        'url' => $items_options_url,
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
            'url'    => CRM_Utils_System::url('civicrm/items/tab', "reset=1&force=1&cid={$context['contact_id']}"),
            'title'  => E::ts('Items Dashboard'),
            'weight' => 20
        ];
    }
}
