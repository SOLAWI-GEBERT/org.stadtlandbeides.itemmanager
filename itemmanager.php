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
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function itemmanager_civicrm_install() {
  _itemmanager_civix_civicrm_install();
  CRM_Itemmanager_Util_LineItemEditor::generatePriceField();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function itemmanager_civicrm_enable() {
  _itemmanager_civix_civicrm_enable();
  CRM_Itemmanager_Util_LineItemEditor::disableEnablePriceField(TRUE);
}

/**
 * Implements hook_civicrm_disable().
 */
function itemmanager_civicrm_disable() {
  CRM_Itemmanager_Util_LineItemEditor::disableEnablePriceField();
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_permission().
 *
 * Registers line item editing permissions (integrated from lineitemedit).
 */
function itemmanager_civicrm_permission(&$permissions) {
  $permissions['add line item'] = [
    'label' => E::ts('Itemmanager: add line item'),
    'description' => E::ts('Allows non-admin users to add line item(s) associated to a contribution.'),
  ];
  $permissions['edit line item'] = [
    'label' => E::ts('Itemmanager: edit line item'),
    'description' => E::ts('Allows non-admin users to edit line item(s) associated to a contribution.'),
  ];
  $permissions['cancel line item'] = [
    'label' => E::ts('Itemmanager: cancel line item'),
    'description' => E::ts('Allows non-admin users to cancel line item(s) associated to a contribution.'),
  ];
}

/**
 * Implements hook_civicrm_container().
 *
 * Registers cache service for line item editor (integrated from lineitemedit).
 */
function itemmanager_civicrm_container(\Symfony\Component\DependencyInjection\ContainerBuilder $container) {
  $container->setDefinition("cache.lineitemEditor", new Symfony\Component\DependencyInjection\Definition(
    'CRM_Utils_Cache_Interface',
    [
      [
        'name' => 'lineitem-editor',
        'type' => ['*memory*', 'SqlGroup', 'ArrayCache'],
      ],
    ]
  ))->setFactory('CRM_Utils_Cache::create')->setPublic(TRUE);
}

/**
 * Implements hook_civicrm_pre().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_pre
 */
function itemmanager_civicrm_pre($op, $objectName, $id, &$params) {

    if ($objectName === 'Membership' && $op == 'edit') {

    }

    // Line item editor: process line item params on contribution create/edit
    if ($objectName == 'Contribution') {
      if ($op == 'create' && CRM_Core_Permission::check('add line item') && empty($params['price_set_id'])) {
        $lineItemParams = [];
        $financialTypes = [];
        $taxEnabled = (bool) Civi::settings()->get('invoicing');
        for ($i = 0; $i <= Civi::settings()->get('line_item_number'); $i++) {
          $lineItemParams[$i] = [];
          $notFound = TRUE;
          foreach (['item_label', 'item_financial_type_id', 'item_qty', 'item_unit_price', 'item_line_total', 'item_price_field_value_id'] as $attribute) {
            if (!empty($params[$attribute]) && !empty($params[$attribute][$i])) {
              if (in_array($attribute, ['item_line_total', 'item_unit_price'])) {
                $notFound = FALSE;
                $params[$attribute][$i] = CRM_Utils_Rule::cleanMoney($params[$attribute][$i]);
              }
              $lineItemParams[$i][str_replace('item_', '', $attribute)] = $params[$attribute][$i];
              if ($attribute == 'item_price_field_value_id') {
                $lineItemParams[$i]['price_field_id'] = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $params[$attribute][$i], 'price_field_id');
              }
            }
          }
          if ($notFound) {
            unset($lineItemParams[$i]);
          }
          else {
            if ($taxEnabled) {
              $lineItemParams[$i]['tax_amount'] = (float) $params['item_tax_amount'][$i] ?? 0.00;
              $params['tax_amount'] += $lineItemParams[$i]['tax_amount'];
            }
            if (!in_array($params['item_financial_type_id'], $financialTypes) && !empty($params['item_financial_type_id'][$i])) {
              $financialTypes[] = $params['item_financial_type_id'][$i];
            }
            $params['total_amount'] = $params['amount'] += ((float) ($lineItemParams[$i]['line_total'] ?? 0.00) + (float) ($lineItemParams[$i]['tax_amount'] ?? 0.00));
            $params['net_amount'] = $params['total_amount'] - $params['fee_amount'] ?? 0;
            if (!empty($lineItemParams[$i]['line_total']) && !empty($lineItemParams[$i]['price_field_id'])) {
              $priceSetID = CRM_Core_DAO::getFieldValue('CRM_Price_BAO_PriceField', $lineItemParams[$i]['price_field_id'], 'price_set_id');
              if (!empty($params['line_item'][$priceSetID])) {
                $params['line_item'][$priceSetID][$lineItemParams[$i]['price_field_id']] = $lineItemParams[$i];
              }
            }
          }
        }
        if (count($financialTypes) > 0) {
          $params['financial_type_id'] = $financialTypes[0];
        }
      }
      elseif ($op == 'edit' && CRM_Core_Permission::check('edit line item')) {
        $newLineItemParams = $lineItemParams = $newLineItem = [];
        for ($i = 0; $i <= 10; $i++) {
          $lineItemParams[$i] = [];
          $notFound = TRUE;
          foreach (['item_label', 'item_financial_type_id', 'item_qty', 'item_unit_price', 'item_line_total', 'item_price_field_value_id', 'item_tax_amount'] as $attribute) {
            if (!empty($params[$attribute]) && !empty($params[$attribute][$i])) {
              if ($attribute == 'item_line_total') {
                $notFound = FALSE;
              }
              $lineItemParams[$i][str_replace('item_', '', $attribute)] = $params[$attribute][$i];
              if ($attribute == 'item_price_field_value_id') {
                $lineItemParams[$i]['price_field_id'] = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $params[$attribute][$i], 'price_field_id');
              }
            }
          }
          if ($notFound) {
            unset($lineItemParams[$i]);
          }
        }

        $newLineItemParams = [];
        foreach ($lineItemParams as $key => $lineItem) {
          if ($lineItem['price_field_value_id'] == 'new') {
            list($lineItem['price_field_id'], $lineItem['price_field_value_id']) = CRM_Itemmanager_Util_LineItemEditor::createPriceFieldByContributionID($id);
          }
          else {
            $lineItem['price_field_id'] = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceFieldValue', $lineItem['price_field_value_id'], 'price_field_id');
          }
          [$lineEntityTable, $lineEntityID] = CRM_Itemmanager_Util_LineItemEditor::addEntity(
            $lineItem['price_field_value_id'],
            $id,
            $lineItem['qty']
          );
          $newLineItemParams[] = array(
            'entity_table' => $lineEntityTable,
            'entity_id' => $lineEntityID,
            'contribution_id' => $id,
            'price_field_id' => $lineItem['price_field_id'],
            'label' => $lineItem['label'],
            'qty' => $lineItem['qty'],
            'unit_price' => CRM_Utils_Rule::cleanMoney($lineItem['unit_price']),
            'line_total' => CRM_Utils_Rule::cleanMoney($lineItem['line_total']),
            'price_field_value_id' => $lineItem['price_field_value_id'],
            'financial_type_id' => $lineItem['financial_type_id'],
            'tax_amount' => $lineItem['tax_amount'] ?? '0.00',
          );
        }

        foreach ($newLineItemParams as $lineItem) {
          $newLineItem[] = civicrm_api3('LineItem', 'create', $lineItem)['id'];
        }

        if (!empty($lineItemParams)) {
          $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($id);
          $taxAmount = CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID($id);

          list($trxn, $contriParams) = CRM_Itemmanager_Util_LineItemEditor::recordAdjustedAmt(
            $updatedAmount,
            $id,
            $taxAmount,
            TRUE, TRUE
          );
          $entityID = (string) $id;
          Civi::cache('lineitemEditor')->set($entityID, $contriParams);

          if ($trxn) {
            foreach ($newLineItem as $lineItemID) {
              $lineItem = civicrm_api3('LineItem', 'getsingle', array('id' => $lineItemID));
              CRM_Itemmanager_Util_LineItemEditor::insertFinancialItemOnAdd($lineItem, $trxn);
            }
          }
        }

        $params['skipLineItem'] = TRUE;
      }
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

        $i = 0;
        foreach ($links as $link) {

            $qs = $link['qs'];
            if( strpos( $qs, 'renew' ) !== false) {
               unset($links[$i]);
            }

            $i++;

        }

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

    if ($op == 'contribution.selector.row' && $objectName == 'Contribution') {
        $links[] = [
            'name' => ts('Scan Future Items', ['domain' => 'org.stadtlandbeides.itemmanager']),
            'url' => 'civicrm/items/futurescan',
            'qs' => 'contrib_id=%%id%%&cid=%%cid%%',
            'title' => ts('Scan future contributions based on this one', ['domain' => 'org.stadtlandbeides.itemmanager']),
            'class' => 'crm-popup',
        ];
    }
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function itemmanager_civicrm_navigationMenu(&$menu) {


    _sepa_civix_insert_navigation_menu($menu, 'Administer/CiviMember', array(
        'label' => ts('Itemmanager Maintenance', array('domain' => 'org.stadtlandbeides.itemmanager')),
        'name' => 'Itemmanager Maintenance',
        'url' => 'civicrm/items/maintenance',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => 0,
        'active' => 1,
    ));

    _sepa_civix_insert_navigation_menu($menu, 'Administer/CiviMember', array(
        'label' => ts('Itemmanager Settings', array('domain' => 'org.stadtlandbeides.itemmanager')),
        'name' => 'Itemmanager Settings',
        'url' => 'civicrm/admin/setting/itemmanager',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => 0,
        'active' => 1,
    ));

    _sepa_civix_insert_navigation_menu($menu, 'Administer/CiviMember', array(
        'label' => ts('Itemmanager Options', array('domain' => 'org.stadtlandbeides.itemmanager')),
        'name' => 'Itemmanager Options',
        'url' => 'civicrm/admin/setting/itemmanageroptions',
        'permission' => 'administer CiviCRM',
        'operator' => NULL,
        'separator' => 0,
        'active' => 1,
    ));

  _itemmanager_civix_navigationMenu($menu);
}

/**
 * Implements hook_civicrm_buildForm().
 *
 * Hooks into the contribution form to add line item editing UI (integrated from lineitemedit).
 */
function itemmanager_civicrm_buildForm($formName, &$form) {
  if ($formName == 'CRM_Contribute_Form_Search') {
    Civi::resources()->addBundle('bootstrap3');
  }

  if ($formName == 'CRM_Contribute_Form_Contribution') {
    $contributionID = NULL;
    if (!empty($form->_id) && ($form->_action & CRM_Core_Action::UPDATE) && CRM_Core_Permission::check('edit line item')) {
      $contributionID = $form->_id;
      $pricesetFieldsCount = NULL;
      $isQuickConfig = empty($form->_lineItems) ? TRUE : FALSE;
      if ($isQuickConfig) {
        $order = civicrm_api3('Order', 'getsingle', array('id' => $contributionID));
        $lineItemTable = CRM_Itemmanager_Util_LineItemEditor::getLineItemTableInfo($order);
        $form->assign('lineItemTable', $lineItemTable);

        $templatePath = realpath(dirname(__FILE__) . "/templates");
        $form->assign('pricesetFieldsCount', FALSE);
      }
      else {
        $pricesetFieldsCount = CRM_Core_Smarty::singleton()->getTemplateVars('pricesetFieldsCount');
        CRM_Itemmanager_Util_LineItemEditor::formatLineItemList($form->_lineItems, $pricesetFieldsCount);
        $form->assign('lineItem', $form->_lineItems);
        $form->assign('pricesetFieldsCount', TRUE);
      }
      if (!empty($form->_values['total_amount'])) {
        $form->setDefaults('total_amount', $form->_values['total_amount']);
      }
    }

    if (!($form->_action & CRM_Core_Action::DELETE) && CRM_Core_Permission::check('cancel line item')) {
      $form->assign('contribution_id', $contributionID);
      Civi::service('angularjs.loader')->addModules(['afLineItems', 'afLineItemsTax']);

      $form->assign('editUrl', CRM_Utils_System::url('civicrm/lineitem/edit?reset=1&id=', NULL, FALSE, NULL, FALSE));
      $form->assign('cancelUrl', CRM_Utils_System::url('civicrm/lineitem/cancel?reset=1&id=', NULL, FALSE, NULL, FALSE));

      CRM_Itemmanager_Util_LineItemEditor::buildLineItemRows($form, $contributionID);
      $form->assign('lineItemNumber', Civi::settings()->get('line_item_number'));
      CRM_Core_Region::instance('page-body')->add([
        'template' => "CRM/Itemmanager/Form/AddLineItems.tpl",
      ]);
    }
  }
}

/**
 * Implements hook_civicrm_postProcess().
 *
 * Adjusts qty ratios after contribution edit and manages price fields for batch entry
 * (integrated from lineitemedit).
 */
function itemmanager_civicrm_postProcess($formName, &$form) {
  if ($formName == 'CRM_Contribute_Form_Contribution' &&
    !empty($form->_id) &&
    ($form->_action & CRM_Core_Action::UPDATE) &&
    CRM_Core_Permission::check('edit line item')
  ) {
    $lineItems = CRM_Price_BAO_LineItem::getLineItemsByContributionID($form->_id);
    foreach ($lineItems as $id => $lineItem) {
      if ($lineItem['qty'] == 0 && $lineItem['line_total'] != 0) {
        $qtyRatio = ($lineItem['line_total'] / $lineItem['unit_price']);
        if ($lineItem['html_type'] == 'Text') {
          $qtyRatio = round($qtyRatio, 2);
        }
        else {
          $qtyRatio = (int) $qtyRatio;
        }
        civicrm_api3('LineItem', 'create', array(
          'id' => $id,
          'qty' => $qtyRatio ? $qtyRatio : 1,
        ));
      }
    }
  }
  if ('CRM_Batch_Form_Entry' == $formName) {
    CRM_Itemmanager_Util_LineItemEditor::disableEnablePriceField(TRUE);
  }
}

/**
 * Implements hook_civicrm_validateForm().
 *
 * Manages price field enable/disable during batch entry (integrated from lineitemedit).
 */
function itemmanager_civicrm_validateForm($formName, &$fields, &$files, &$form, &$errors) {
  if ('CRM_Batch_Form_Entry' == $formName && empty($errors)) {
    CRM_Itemmanager_Util_LineItemEditor::disableEnablePriceField();
  }
}

/**
 * Implements hook_civicrm_post().
 *
 * Updates contribution after line item creation via cache (integrated from lineitemedit).
 */
function itemmanager_civicrm_post($op, $objectName, $objectId, &$obj) {
  if ($objectName == 'Contribution' && $op == 'edit' && CRM_Core_Permission::check('edit line item')) {
    $entityID = (string) $objectId;
    $contriParams = Civi::cache('lineitemEditor')->get($entityID);
    if (!empty($contriParams)) {
      \CRM_Utils_Hook::pre('edit', 'Contribution', $entityID, $contriParams);
      $obj->copyValues($contriParams);
      $obj->save();
      Civi::cache('lineitemEditor')->delete($entityID);
      \CRM_Utils_Hook::post('edit', 'Contribution', $entityID, $obj);
    }
  }
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
