<?php

use CRM_Itemmanager_ExtensionUtil as E;

/**
 * AJAX endpoint for batch analysis and update of line items across all contacts.
 * Returns JSON responses for progress tracking.
 */
class CRM_Itemmanager_Page_ItemMaintenanceStub extends CRM_Core_Page {

    public function run() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleUpdate();
        } else {
            $this->handleAnalyze();
        }
    }

    private function handleAnalyze() {
        $offset = (int) CRM_Utils_Request::retrieve('offset', 'Integer');
        $limit = (int) CRM_Utils_Request::retrieve('limit', 'Integer') ?: 10;
        $filter_sync = (int) CRM_Utils_Request::retrieve('filter_sync', 'Integer');
        $filter_harmonize = (int) CRM_Utils_Request::retrieve('filter_harmonize', 'Integer');
        $filter_orphan = (int) CRM_Utils_Request::retrieve('filter_orphan', 'Integer');
        $date_from = CRM_Utils_Request::retrieve('date_from', 'String');
        $exclude_tag_ids_raw = CRM_Utils_Request::retrieve('exclude_tag_ids', 'String');

        if (!$date_from) {
            $date_from = date('Y-m-d', strtotime('-1 year'));
        }

        $exclude_tag_ids = [];
        if (!empty($exclude_tag_ids_raw)) {
            $exclude_tag_ids = array_filter(array_map('intval', explode(',', $exclude_tag_ids_raw)));
        }

        $total = $this->getTotalContactCount($date_from, $exclude_tag_ids);
        $contact_ids = $this->getContactBatch($date_from, $offset, $limit, $exclude_tag_ids);

        $items = [];
        foreach ($contact_ids as $contact_id) {
            $contactItems = $this->analyzeContact(
                $contact_id, $filter_sync, $filter_harmonize, $filter_orphan, $date_from
            );
            $items = array_merge($items, $contactItems);
        }

        $processed = min($offset + $limit, $total);

        CRM_Utils_JSON::output([
            'items' => $items,
            'processed' => $processed,
            'total' => $total,
            'done' => $processed >= $total,
        ]);
    }

    private function getTotalContactCount($date_from, $exclude_tag_ids = []) {
        $tagWhere = '';
        if (!empty($exclude_tag_ids)) {
            $tagPlaceholders = implode(',', $exclude_tag_ids);
            $tagWhere = "AND membership.contact_id NOT IN (
                SELECT et.entity_id FROM civicrm_entity_tag et
                WHERE et.entity_table = 'civicrm_contact'
                AND et.tag_id IN ($tagPlaceholders)
            )";
        }

        $query = "
            SELECT COUNT(DISTINCT membership.contact_id)
            FROM civicrm_membership membership
            INNER JOIN civicrm_membership_payment mp ON mp.membership_id = membership.id
            LEFT JOIN civicrm_contribution c ON mp.contribution_id = c.id
            WHERE (c.receive_date >= %1 OR c.id IS NULL)
            $tagWhere
        ";
        return (int) CRM_Core_DAO::singleValueQuery($query, [
            1 => [$date_from, 'String'],
        ]);
    }

    private function getContactBatch($date_from, $offset, $limit, $exclude_tag_ids = []) {
        $tagWhere = '';
        if (!empty($exclude_tag_ids)) {
            $tagPlaceholders = implode(',', $exclude_tag_ids);
            $tagWhere = "AND membership.contact_id NOT IN (
                SELECT et.entity_id FROM civicrm_entity_tag et
                WHERE et.entity_table = 'civicrm_contact'
                AND et.tag_id IN ($tagPlaceholders)
            )";
        }

        $query = "
            SELECT DISTINCT membership.contact_id
            FROM civicrm_membership membership
            INNER JOIN civicrm_membership_payment mp ON mp.membership_id = membership.id
            LEFT JOIN civicrm_contribution c ON mp.contribution_id = c.id
            WHERE (c.receive_date >= %1 OR c.id IS NULL)
            $tagWhere
            ORDER BY membership.contact_id
            LIMIT %2 OFFSET %3
        ";
        $dao = CRM_Core_DAO::executeQuery($query, [
            1 => [$date_from, 'String'],
            2 => [$limit, 'Integer'],
            3 => [$offset, 'Integer'],
        ]);

        $ids = [];
        while ($dao->fetch()) {
            $ids[] = (int) $dao->contact_id;
        }
        return $ids;
    }

    /**
     * Analyze a single contact's line items for potential updates.
     * Adapted from UpdateItems::prepareCreateForm.
     */
    private function analyzeContact($contact_id, $filter_sync, $filter_harmonize, $filter_orphan, $date_from) {
        $contact = \Civi\Api4\Contact::get(FALSE)
            ->addSelect('display_name')
            ->addWhere('id', '=', $contact_id)
            ->execute()->first();

        if (!$contact) {
            return [];
        }

        $display_name = $contact['display_name'];

        $contactTags = $this->getContactTags($contact_id);

        $base_query = "
            SELECT
                member_type.name AS member_name,
                member_pay.contribution_id AS contrib_id,
                membership.start_date as member_start,
                membership.end_date as member_end,
                membership.status_id as member_status,
                member_pay.id as pay_id
            FROM civicrm_membership membership
             LEFT JOIN civicrm_membership_payment member_pay ON member_pay.membership_id = membership.id
             LEFT JOIN civicrm_contribution contribution ON contribution.contact_id = %1 AND member_pay.contribution_id = contribution.id
             LEFT JOIN civicrm_membership_type member_type ON member_type.id = membership.membership_type_id
            WHERE membership.contact_id = %1
        ";

        $item_query = "
            SELECT
                line_item.label As item_label,
                line_item.id As line_id,
                line_item.qty As item_quantity,
                line_item.unit_price As item_price,
                line_item.line_total As item_total,
                line_item.tax_amount As item_tax,
                line_item.financial_type_id As item_ftype,
                line_item.price_field_value_id as field_value_id,
                price_field.id As field_id,
                price_field.is_active As item_active,
                price_field.active_on As item_startdate,
                price_field.expire_on As item_enddate,
                price_field.help_pre As item_help,
                price_field_value.label As field_label,
                price_field_value.amount As field_amount,
                price_field_value.financial_type_id As field_finance_type,
                contribution.receive_date As contrib_date,
                price_set.id As set_id,
                price_set.name As set_name,
                price_set.is_active As set_active,
                price_set.help_pre As set_help
            FROM civicrm_line_item line_item
                 LEFT JOIN civicrm_price_field_value price_field_value ON line_item.price_field_value_id = price_field_value.id
                 LEFT JOIN civicrm_price_field price_field ON line_item.price_field_id = price_field.id
                 LEFT JOIN civicrm_contribution contribution ON line_item.contribution_id = contribution.id
                 LEFT JOIN civicrm_price_set price_set ON price_field.price_set_id = price_set.id
            WHERE line_item.contribution_id = %1
            ORDER BY contribution.receipt_date DESC
        ";

        $base_items = CRM_Core_DAO::executeQuery($base_query, [
            1 => [$contact_id, 'Integer'],
        ]);

        $items = [];

        while ($base_items->fetch()) {
            $testcount = \Civi\Api4\Contribution::get(FALSE)
                ->addWhere('id', '=', (int) $base_items->contrib_id)
                ->selectRowCount()
                ->execute()
                ->countMatched();

            if ($testcount == 0) {
                if ($filter_orphan == 1) {
                    $items[] = [
                        'contact_id' => $contact_id,
                        'display_name' => $display_name,
                        'tags' => $contactTags,
                        'line_id' => null,
                        'set_id' => null,
                        'field_id' => null,
                        'member_name' => $base_items->member_name,
                        'item_label' => null,
                        'item_quantity' => null,
                        'item_price' => null,
                        'item_total' => null,
                        'item_tax' => null,
                        'periods' => null,
                        'contrib_date' => null,
                        'update_date' => null,
                        'change_date' => null,
                        'update_label' => null,
                        'change_label' => null,
                        'update_price' => null,
                        'change_price' => null,
                        'change_total' => null,
                        'change_tax' => null,
                        'empty_relation_id' => (int) $base_items->pay_id,
                        'change_error' => 'membership contribution relation ' . (int) $base_items->pay_id . ' is missing',
                    ];
                }
                continue;
            }

            $line_items = CRM_Core_DAO::executeQuery($item_query, [
                1 => [$base_items->contrib_id, 'Integer'],
            ]);

            while ($line_items->fetch()) {
                // Apply date filter
                if ($line_items->contrib_date < $date_from) {
                    continue;
                }

                $line_timestamp = date_create($line_items->contrib_date);
                $line_date = $line_timestamp->format('Y-m-d H:i:s');
                $change_timestamp = $line_items->contrib_date;
                $periods = 1;

                try {
                    $manager_item = new CRM_Itemmanager_BAO_ItemmanagerSettings();
                    $valid = $manager_item->get('price_field_value_id', $line_items->field_value_id);

                    $period_item = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
                    $valid = $period_item->get('id', $manager_item->itemmanager_periods_id);

                    $periods = $period_item->periods;
                    if ($manager_item->enable_period_exception)
                        $periods = $manager_item->exception_periods;
                    if (!$valid or $periods == 0 or $period_item->reverse) $periods = 1;

                    $change_timestamp = $period_item->period_start_on;
                    if (!$valid) $change_timestamp = $line_items->contrib_date;
                } catch (\Civi\API\Exception\UnauthorizedException $e) {
                } catch (API_Exception $e) {
                } catch (CRM_Core_Exception $e) {
                }

                $raw_date = date_create($change_timestamp);
                $new_date = new DateTime($line_timestamp->format('Y-m') . $raw_date->format('-d'));
                $new_date->setTime(0, 0);
                $changed_date = $new_date->format('Y-m-d H:i:s');

                $change_unit_price = $line_items->field_amount / $periods;
                $tax = 1.0;
                if (CRM_Itemmanager_Util::isTaxEnabledInFinancialType($line_items->field_finance_type))
                    $tax = CRM_Itemmanager_Util::getTaxRateInFinancialType($line_items->field_finance_type);
                $changed_total = $line_items->item_quantity * $change_unit_price;
                $changed_tax = $line_items->item_quantity * $change_unit_price * $tax / 100.0;

                $base = [
                    'contact_id' => $contact_id,
                    'display_name' => $display_name,
                    'tags' => $contactTags,
                    'line_id' => $line_items->line_id,
                    'set_id' => $line_items->set_id,
                    'field_id' => $line_items->field_id,
                    'member_name' => $base_items->member_name,
                    'item_label' => $line_items->item_label,
                    'item_quantity' => $line_items->item_quantity,
                    'item_price' => CRM_Itemmanager_Util::roundMoney($line_items->item_price),
                    'item_total' => CRM_Itemmanager_Util::roundMoney($line_items->item_total),
                    'item_tax' => CRM_Itemmanager_Util::roundMoney($line_items->item_tax),
                    'periods' => $periods,
                    'contrib_date' => $line_date,
                    'update_date' => $line_date != $changed_date && $filter_harmonize == 1,
                    'change_date' => $changed_date,
                    'update_label' => $line_items->item_label != $line_items->field_label,
                    'change_label' => $line_items->field_label,
                    'update_price' => !CRM_Itemmanager_Util::moneyEquals($line_items->item_price, $change_unit_price)
                        && $filter_sync == 1,
                    'change_price' => CRM_Itemmanager_Util::roundMoney($change_unit_price),
                    'change_total' => CRM_Itemmanager_Util::roundMoney($changed_total),
                    'change_tax' => CRM_Itemmanager_Util::roundMoney($changed_tax),
                    'empty_relation_id' => null,
                    'change_error' => null,
                ];

                if ($base['update_date'] || $base['update_label'] || $base['update_price']) {
                    $items[] = $base;
                }
            }
        }

        return $items;
    }

    /**
     * Handle update of selected line items.
     * Adapted from UpdateItems::updateData.
     */
    private function handleUpdate() {
        $input = json_decode(file_get_contents('php://input'), TRUE);
        $filter_sync = (int) ($input['filter_sync'] ?? 0);
        $filter_harmonize = (int) ($input['filter_harmonize'] ?? 0);
        $selected_items = $input['viewlist'] ?? [];
        $delete_list = $input['deletelist'] ?? [];

        $results = ['updated' => 0, 'deleted' => 0, 'errors' => []];

        // Handle orphan deletions
        foreach ($delete_list as $pay_id) {
            $pay_id = (int) $pay_id;
            if ($pay_id <= 0) continue;

            $payment = CRM_Core_DAO::executeQuery(
                "SELECT id, contribution_id FROM civicrm_membership_payment WHERE id = %1",
                [1 => [$pay_id, 'Integer']]
            );
            if (!$payment->fetch()) continue;

            $contribCount = \Civi\Api4\Contribution::get(FALSE)
                ->addWhere('id', '=', (int) $payment->contribution_id)
                ->selectRowCount()
                ->execute()
                ->countMatched();
            if ($contribCount > 0) continue;

            CRM_Core_DAO::executeQuery(
                "DELETE FROM civicrm_membership_payment WHERE id = %1",
                [1 => [$pay_id, 'Integer']]
            );
            $results['deleted']++;
        }

        // Handle line item updates
        $contribution_table = CRM_Contribute_DAO_Contribution::getTableName();
        $line_item_table = CRM_Price_DAO_LineItem::getTableName();
        $financial_item_table = CRM_Financial_DAO_FinancialItem::getTableName();
        $financial_transaktion_table = CRM_Financial_DAO_EntityFinancialTrxn::getTableName();

        $update_date_query = "UPDATE " . $contribution_table . " SET receive_date = %1 WHERE id = %2";
        $update_label_query = "UPDATE " . $line_item_table . " SET label = %1 WHERE id = %2";
        $update_financial_item = "UPDATE " . $financial_item_table . " SET amount = %1 WHERE id = %2";
        $update_contribution_query = "UPDATE " . $contribution_table . " SET total_amount = %1, net_amount = %1 WHERE id = %2";
        $update_lineitem_query = "UPDATE " . $line_item_table . " SET unit_price = %1, line_total = %2, tax_amount = %3 WHERE id = %4";
        $update_financial_transaktion_query = "UPDATE " . $financial_transaktion_table . " SET amount = %1 WHERE id = %2";

        foreach ($selected_items as $line_item) {
            if ($line_item == null) continue;

            $update_contribution = FALSE;
            $update_label = FALSE;
            $update_price = FALSE;

            try {
                $lineitemInfo = \Civi\Api4\LineItem::get(FALSE)
                    ->addWhere('id', '=', (int) $line_item)
                    ->execute()->single();
                $priceFieldValueInfo = \Civi\Api4\PriceFieldValue::get(FALSE)
                    ->addWhere('id', '=', (int) $lineitemInfo['price_field_value_id'])
                    ->execute()->single();
                $contributionInfo = \Civi\Api4\Contribution::get(FALSE)
                    ->addWhere('id', '=', (int) $lineitemInfo['contribution_id'])
                    ->execute()->single();
            } catch (\CRM_Core_Exception $e) {
                $results['errors'][] = E::ts('Could not load data for line item %1', [1 => $line_item]);
                continue;
            }

            $line_timestamp = date_create($contributionInfo['receive_date']);
            $line_date = $line_timestamp->format('Y-m-d H:i:s');

            $manager_item = new CRM_Itemmanager_BAO_ItemmanagerSettings();
            $valid = $manager_item->get('price_field_value_id', (int) $lineitemInfo['price_field_value_id']);

            $period_item = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
            $valid = $period_item->get('id', $manager_item->itemmanager_periods_id);

            $periods = (int) $period_item->periods;
            if ($manager_item->enable_period_exception)
                $periods = $manager_item->exception_periods;
            if (!$valid or $periods == 0 or $period_item->reverse) $periods = 1;

            $change_timestamp = $period_item->period_start_on;
            if (!$valid) $change_timestamp = $contributionInfo['receive_date'];

            $raw_date = date_create($change_timestamp);
            $new_date = new DateTime($line_timestamp->format('Y-m') . $raw_date->format('-d'));
            $new_date->setTime(0, 0);
            $changed_date = $new_date->format('Y-m-d H:i:s');

            $change_unit_price = $priceFieldValueInfo['amount'] / $periods;
            $tax = 0.0;
            if (CRM_Itemmanager_Util::isTaxEnabledInFinancialType((int) $priceFieldValueInfo['financial_type_id']))
                $tax = CRM_Itemmanager_Util::getTaxRateInFinancialType((int) $priceFieldValueInfo['financial_type_id']);
            $changed_total = $lineitemInfo['qty'] * $change_unit_price;
            $changed_tax = $lineitemInfo['qty'] * $change_unit_price * $tax / 100.0;

            // Update date
            if ($line_date != $changed_date && $filter_harmonize == 1) {
                $update_contribution = TRUE;
                try {
                    CRM_Core_DAO::executeUnbufferedQuery(
                        CRM_Core_DAO::composeQuery($update_date_query, [
                            1 => [$changed_date, 'String'],
                            2 => [(int) $lineitemInfo['contribution_id'], 'Integer'],
                        ])
                    );
                } catch (\Exception $e) {
                    $results['errors'][] = E::ts('Error updating contribution date for %1', [1 => $line_item]);
                }
            }

            // Update label
            if ($lineitemInfo['label'] != $priceFieldValueInfo['label']) {
                $update_label = TRUE;
                try {
                    CRM_Core_DAO::executeUnbufferedQuery(
                        CRM_Core_DAO::composeQuery($update_label_query, [
                            1 => [$priceFieldValueInfo['label'], 'String'],
                            2 => [(int) $line_item, 'Integer'],
                        ])
                    );
                } catch (\Exception $e) {
                    $results['errors'][] = E::ts('Error updating label for %1', [1 => $line_item]);
                }
            }

            // Update price
            if (!CRM_Itemmanager_Util::moneyEquals($lineitemInfo['unit_price'], $change_unit_price) && $filter_sync == 1) {
                $update_price = TRUE;

                try {
                    CRM_Core_DAO::executeUnbufferedQuery(
                        CRM_Core_DAO::composeQuery($update_lineitem_query, [
                            1 => [$change_unit_price, 'Float'],
                            2 => [$changed_total, 'Float'],
                            3 => [$changed_tax, 'Float'],
                            4 => [(int) $line_item, 'Integer'],
                        ])
                    );
                } catch (\Exception $e) {
                    $results['errors'][] = E::ts('Error updating line item %1', [1 => $line_item]);
                    continue;
                }

                // Update financial records
                $financeitems = CRM_Itemmanager_Util::getFinancialFullRecordsByLineItemId((int) $line_item);
                if ($financeitems['is_error']) {
                    $results['errors'][] = E::ts('Error retrieving financial info for %1', [1 => $line_item]);
                    continue;
                }

                foreach ($financeitems['values'] as $financeitem) {
                    try {
                        $amount = $financeitem['accountinfo']['is_tax'] ? $changed_tax : $changed_total;

                        CRM_Core_DAO::executeUnbufferedQuery(
                            CRM_Core_DAO::composeQuery($update_financial_item, [
                                1 => [$amount, 'Float'],
                                2 => [(int) $financeitem['financeitem']['id'], 'Integer'],
                            ])
                        );

                        $transaction = CRM_Itemmanager_Util::getFinancialEntityTrxnByFinancialItemId(
                            (int) $financeitem['financeitem']['id']
                        );
                        CRM_Core_DAO::executeUnbufferedQuery(
                            CRM_Core_DAO::composeQuery($update_financial_transaktion_query, [
                                1 => [$amount, 'Float'],
                                2 => [(int) $transaction['id'], 'Integer'],
                            ])
                        );
                    } catch (\Exception $e) {
                        $results['errors'][] = E::ts('Error updating financial item %1', [
                            1 => $financeitem['financeitem']['id'],
                        ]);
                    }
                }

                // Update contribution totals
                $tax_total = CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID(
                    (int) $lineitemInfo['contribution_id']
                );
                $total = CRM_Itemmanager_Util::getAmountTotalFromContributionID(
                    (int) $lineitemInfo['contribution_id']
                );

                if (isset($contributionInfo['contribution_recur_id'])) {
                    \Civi\Api4\ContributionRecur::update(FALSE)
                        ->addWhere('id', '=', (int) $contributionInfo['contribution_recur_id'])
                        ->addValue('amount', $total)
                        ->execute();
                }

                try {
                    CRM_Core_DAO::executeUnbufferedQuery(
                        CRM_Core_DAO::composeQuery($update_contribution_query, [
                            1 => [$tax_total + $total, 'Float'],
                            2 => [(int) $lineitemInfo['contribution_id'], 'Integer'],
                        ])
                    );

                    $transaction = CRM_Itemmanager_Util::getFinancialEntityIdTrxnByContributionId(
                        (int) $lineitemInfo['contribution_id']
                    );
                    CRM_Core_DAO::executeUnbufferedQuery(
                        CRM_Core_DAO::composeQuery($update_financial_transaktion_query, [
                            1 => [$tax_total + $total, 'Float'],
                            2 => [(int) $transaction['id'], 'Integer'],
                        ])
                    );
                } catch (\Exception $e) {
                    $results['errors'][] = E::ts('Error updating contribution %1', [
                        1 => $lineitemInfo['contribution_id'],
                    ]);
                }
            }

            if ($update_label || $update_contribution || $update_price) {
                $results['updated']++;
            }
        }

        CRM_Utils_JSON::output($results);
    }

    private function getContactTags($contact_id) {
        $tags = [];
        $entityTags = \Civi\Api4\EntityTag::get(FALSE)
            ->addSelect('tag_id', 'tag_id:label')
            ->addWhere('entity_table', '=', 'civicrm_contact')
            ->addWhere('entity_id', '=', $contact_id)
            ->execute();

        foreach ($entityTags as $et) {
            $tags[] = [
                'id' => (int) $et['tag_id'],
                'label' => $et['tag_id:label'],
            ];
        }
        return $tags;
    }
}
