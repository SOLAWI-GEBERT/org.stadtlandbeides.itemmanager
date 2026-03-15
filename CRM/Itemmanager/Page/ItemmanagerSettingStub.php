<?php

use CRM_Itemmanager_ExtensionUtil as E;

/**
 * Stub page that loads field details for a single ItemmanagerPeriod.
 * Called via AJAX from the ItemmanagerSetting form accordion.
 */
class CRM_Itemmanager_Page_ItemmanagerSettingStub extends CRM_Core_Page {

    private $_errormessages = [];

    public function run() {
        $period_id = (int) CRM_Utils_Request::retrieve('period_id', 'Integer');
        if ($period_id <= 0) {
            $this->assign('errormessages', ['Invalid period_id']);
            $this->assign('fields', []);
            parent::run();
            return;
        }

        $fields = [];

        try {
            $itemmanager_period = \Civi\Api4\ItemmanagerPeriods::get(FALSE)
                ->addWhere('id', '=', $period_id)
                ->execute()->first();

            if (!$itemmanager_period) {
                $this->_errormessages[] = E::ts('Period not found: %1', [1 => $period_id]);
                $this->assign('errormessages', $this->_errormessages);
                $this->assign('fields', []);
                parent::run();
                return;
            }

            $priceset = \Civi\Api4\PriceSet::get(FALSE)
                ->addWhere('id', '=', (int) $itemmanager_period['price_set_id'])
                ->execute()->first();

            if (!$priceset) {
                $this->_errormessages[] = E::ts('Could not get the price set %1', [1 => (int) $itemmanager_period['price_set_id']]);
                $this->assign('errormessages', $this->_errormessages);
                $this->assign('fields', []);
                parent::run();
                return;
            }

            $itemmanager_price_fields = \Civi\Api4\ItemmanagerSettings::get(FALSE)
                ->addWhere('itemmanager_periods_id', '=', $period_id)
                ->execute();

            foreach ($itemmanager_price_fields as $itemmanager_price_field) {
                $itemmanager_id = $itemmanager_price_field['id'];

                $pricefieldvalue = \Civi\Api4\PriceFieldValue::get(FALSE)
                    ->addWhere('id', '=', (int) $itemmanager_price_field['price_field_value_id'])
                    ->execute()->first();
                if (!$pricefieldvalue) {
                    continue;
                }

                $pricefield = \Civi\Api4\PriceField::get(FALSE)
                    ->addWhere('id', '=', (int) $pricefieldvalue['price_field_id'])
                    ->execute()->first();
                if (!$pricefield) {
                    $this->_errormessages[] = E::ts('Could not get the price field %1', [1 => (int) $pricefieldvalue['price_field_id']]);
                    continue;
                }

                if (isset($pricefield['active_on'])) {
                    $active_on = CRM_Utils_Date::customFormat(
                        date_create($pricefield['active_on'])->format('Y-m-d'),
                        Civi::settings()->get('dateformatshortdate'));
                } else {
                    $active_on = "-";
                }

                if (isset($pricefield['expire_on'])) {
                    $expire_on = CRM_Utils_Date::customFormat(
                        date_create($pricefield['expire_on'])->format('Y-m-d'),
                        Civi::settings()->get('dateformatshortdate'));
                } else {
                    $expire_on = "-";
                }

                $selection = $this->getItemSelection($priceset, $pricefield, $pricefieldvalue, $itemmanager_period);

                $fields[] = [
                    'manager_id' => (int) $itemmanager_id,
                    'field_label' => $pricefieldvalue['label'],
                    'active_on' => $active_on,
                    'expire_on' => $expire_on,
                    'isactive' => $pricefield['is_active'] == 1 ? ts('Active') : '',
                    'ignore' => (int) $itemmanager_price_field['ignore'],
                    'extend' => (int) $itemmanager_price_field['extend'],
                    'novitiate' => (int) $itemmanager_price_field['novitiate'],
                    'bidding' => (int) $itemmanager_price_field['bidding'],
                    'enable_period_exception' => (int) $itemmanager_price_field['enable_period_exception'],
                    'exception_periods' => (int) $itemmanager_price_field['exception_periods'],
                    'successor' => (int) $itemmanager_price_field['itemmanager_successor_id'],
                    'selection' => $selection,
                    'element_period_field_successor' => 'period_' . $itemmanager_id .
                        '_field_' . $itemmanager_id . '_successor',
                    'element_period_field_ignore' => 'period_' . $itemmanager_id .
                        '_field_' . $itemmanager_id . '_ignore',
                    'element_period_field_extend' => 'period_' . $itemmanager_id .
                        '_field_' . $itemmanager_id . '_extend',
                    'element_period_field_novitiate' => 'period_' . $itemmanager_id .
                        '_field_' . $itemmanager_id . '_novitiate',
                    'element_period_field_bidding' => 'period_' . $itemmanager_id .
                        '_field_' . $itemmanager_id . '_bidding',
                    'element_enable_period_exception' => 'period_' . $itemmanager_id .
                        '_field_' . $itemmanager_id . '_enable_period_exception',
                    'element_exception_periods' => 'period_' . $itemmanager_id .
                        '_field_' . $itemmanager_id . '_exception_periods',
                ];
            }
        } catch (Exception $e) {
            $this->_errormessages[] = $e->getMessage();
        }

        $this->assign('errormessages', $this->_errormessages);
        $this->assign('fields', $fields);
        $this->assign('period_id', $period_id);

        parent::run();
    }

    /**
     * Returns successor selection for a price field value within a period context.
     */
    private function getItemSelection($priceset, $pricefield, $pricefieldvalue, $itemmanager_period) {
        $selection = [0 => ts('No Successor')];

        try {
            $priceset_records = \Civi\Api4\PriceSet::get(FALSE)
                ->addWhere('financial_type_id', '=', $priceset['financial_type_id'])
                ->execute();
            if ($priceset_records->count() <= 1) {
                return $selection;
            }

            $successor_id = $itemmanager_period['itemmanager_period_successor_id'];

            $itemmperiod_successor = \Civi\Api4\ItemmanagerPeriods::get(FALSE)
                ->addWhere('id', '=', $successor_id)
                ->execute()->first();

            foreach ($priceset_records as $selectedpriceset) {
                if ($priceset['id'] == $selectedpriceset['id'] or
                    (isset($itemmperiod_successor) and
                        $selectedpriceset['id'] != $itemmperiod_successor['price_set_id'])
                    and $successor_id != 0) {
                    continue;
                }

                $pricefield_records = \Civi\Api4\PriceField::get(FALSE)
                    ->addWhere('price_set_id', '=', $selectedpriceset['id'])
                    ->execute();

                foreach ($pricefield_records as $selectedpricefield) {
                    $pfvQuery = \Civi\Api4\PriceFieldValue::get(FALSE)
                        ->addWhere('price_field_id', '=', $selectedpricefield['id'])
                        ->addWhere('financial_type_id', '=', $pricefieldvalue['financial_type_id']);
                    if (!empty($pricefieldvalue['membership_type_id'])) {
                        $pfvQuery->addWhere('membership_type_id', '=', $pricefieldvalue['membership_type_id']);
                    } else {
                        $pfvQuery->addWhere('membership_type_id', 'IS EMPTY');
                    }
                    $pricefield_values_records = $pfvQuery->execute();

                    foreach ($pricefield_values_records as $selectedpricefieldvalue) {
                        $settings = new CRM_Itemmanager_BAO_ItemmanagerSettings();
                        $settings->get('price_field_value_id', $selectedpricefieldvalue['id']);

                        $selection[(int) $settings->id] = '(' . $selectedpriceset['title'] . ') ' . $selectedpricefieldvalue['label'];
                    }
                }
            }
        } catch (\CRM_Core_Exception $e) {
            $this->_errormessages[] = $e->getMessage();
            return [0 => ts('No Successor')];
        }

        return $selection;
    }

}
