<?php

use CRM_Itemmanager_ExtensionUtil as E;

/**
 * AJAX endpoint that synchronizes ItemmanagerPeriods and ItemmanagerSettings
 * with the current PriceSet / PriceFieldValue records.
 * Returns a JSON response with status and error messages.
 */
class CRM_Itemmanager_Page_SyncStub extends CRM_Core_Page {

    private $_errormessages = [];

    public function run() {
        $this->syncItemmanager();

        $response = [
            'is_error' => !empty($this->_errormessages) ? 1 : 0,
            'messages' => $this->_errormessages,
        ];

        CRM_Utils_JSON::output($response);
    }

    private function syncItemmanager() {
        try {
            $pricefield_values_records = \Civi\Api4\PriceFieldValue::get(FALSE)
                ->addSelect('id')
                ->setLimit(0)
                ->execute();

            $priceset_records = \Civi\Api4\PriceSet::get(FALSE)
                ->addSelect('id')
                ->setLimit(0)
                ->execute();

            $itemmanager_periods = \Civi\Api4\ItemmanagerPeriods::get()
                ->addSelect('price_set_id')
                ->setCheckPermissions(FALSE)
                ->execute()
                ->indexBy('price_set_id');

            $pricefield_value_ids = $pricefield_values_records->column('id');
            $priceset_ids = $priceset_records->column('id');
            $itemmanager_price_set_ids = array_column($itemmanager_periods->getArrayCopy(), 'price_set_id');

            // Delete orphaned periods
            foreach ($itemmanager_price_set_ids as $itemmanager_price_set_id) {
                if (!in_array((int) $itemmanager_price_set_id, $priceset_ids)) {
                    \Civi\Api4\ItemmanagerPeriods::delete()
                        ->addWhere('id', '=', $itemmanager_price_set_id)
                        ->setCheckPermissions(FALSE)
                        ->execute();
                }
            }

            // Create missing periods
            $transaction = new CRM_Core_Transaction();
            try {
                foreach ($priceset_ids as $set_id) {
                    if (!in_array((int) $set_id, $itemmanager_price_set_ids)) {
                        $newperiod = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
                        $newperiod->price_set_id = (int) $set_id;
                        $newperiod->period_start_on = date_create('2000-01-01')->format('Ymd');
                        $newperiod->periods = 1;
                        $newperiod->period_type = 1;
                        $newperiod->save();
                    }
                }
            } catch (\Exception $e) {
                $transaction->rollback();
                $this->_errormessages[] = "An error occurred syncing periods: " . $e->getMessage();
            }
            $transaction->commit();

            // Delete orphaned settings
            $itemmanager_price_fields = \Civi\Api4\ItemmanagerSettings::get()
                ->addSelect('price_field_value_id')
                ->setCheckPermissions(FALSE)
                ->execute()
                ->indexBy('price_field_value_id');

            $itemmanager_price_fields_ids = array_column($itemmanager_price_fields->getArrayCopy(), 'price_field_value_id');

            foreach ($itemmanager_price_fields_ids as $field_id) {
                if (!in_array((int) $field_id, $pricefield_value_ids)) {
                    \Civi\Api4\ItemmanagerSettings::delete()
                        ->addWhere('id', '=', "$field_id")
                        ->setCheckPermissions(FALSE)
                        ->execute();
                }
            }

            // Create missing settings
            foreach ($pricefield_value_ids as $field_id) {
                if (!in_array((int) $field_id, $itemmanager_price_fields_ids)) {
                    $field_infos = CRM_Itemmanager_Util::getPriceSetRefByFieldValueId($field_id);

                    if ($field_infos['iserror'] == 1) {
                        $this->_errormessages[] = 'Could not get the full record for price field value ' . $field_id;
                        continue;
                    }

                    if (!isset($field_infos['price_id'])) {
                        $this->_errormessages[] = 'Could not get the full record for price field value ' . $field_id;
                        continue;
                    }

                    $price_set = $field_infos['price_id'];

                    $period = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
                    $valid = $period->get('price_set_id', (int) $price_set);
                    if (!$valid or $period->id == 0) {
                        $this->_errormessages[] = 'No Itemmanager periods found with id ' . (int) $price_set;
                        continue;
                    }

                    $itemsetting = new CRM_Itemmanager_BAO_ItemmanagerSettings();
                    $itemsetting->price_field_value_id = (int) $field_id;
                    $itemsetting->itemmanager_periods_id = (int) $period->id;
                    $itemsetting->save();
                }
            }

        } catch (\CRM_Core_Exception $e) {
            $this->_errormessages[] = $e->getMessage();
        }
    }
}
