<?php

use CRM_Itemmanager_ExtensionUtil as E;

/**
 * AJAX endpoint for batch analysis and update of future contributions
 * for a single contact. Returns JSON responses for progress tracking.
 */
class CRM_Itemmanager_Page_FutureContributionScanStub extends CRM_Core_Page {

    public function run() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleUpdate();
        } else {
            $this->handleAnalyze();
        }
    }

    private function handleAnalyze() {
        $cid = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
        $psid = (int) CRM_Utils_Request::retrieve('psid', 'Integer');
        $offset = (int) CRM_Utils_Request::retrieve('offset', 'Integer');
        $limit = (int) CRM_Utils_Request::retrieve('limit', 'Integer') ?: 10;
        $filter_sync = (int) CRM_Utils_Request::retrieve('filter_sync', 'Integer');
        $filter_harmonize = (int) CRM_Utils_Request::retrieve('filter_harmonize', 'Integer');
        $filter_orphan = (int) CRM_Utils_Request::retrieve('filter_orphan', 'Integer');
        $filter_composition = (int) CRM_Utils_Request::retrieve('filter_composition', 'Integer');
        $date_from = CRM_Utils_Request::retrieve('date_from', 'String');
        if (!$date_from) {
            $date_from = date('Y-m-d');
        }

        if ($cid <= 0) {
            CRM_Utils_JSON::output(['items' => [], 'processed' => 0, 'total' => 0, 'done' => TRUE]);
            return;
        }

        $total = $this->getTotalContributionCount($cid, $date_from, $psid);
        $contributions = $this->getContributionBatch($cid, $offset, $limit, $date_from, $psid);

        // Build reference line item set per membership (from the earliest contribution in the batch range)
        $referenceItems = [];
        if ($filter_composition == 1 && $offset === 0) {
            $referenceItems = $this->buildReferenceLineItems($cid, $date_from, $psid);
        }
        // Pass reference through from JS on subsequent batches
        $refFromRequest = CRM_Utils_Request::retrieve('reference_items', 'String');
        if ($filter_composition == 1 && !empty($refFromRequest)) {
            $referenceItems = json_decode($refFromRequest, TRUE) ?: [];
        }

        $items = [];
        foreach ($contributions as $contrib) {
            $contribItems = $this->analyzeContribution(
                $cid, $contrib, $filter_sync, $filter_harmonize
            );
            $items = array_merge($items, $contribItems);

            // Check composition: missing or extra line items
            if ($filter_composition == 1 && !empty($referenceItems[$contrib['membership_id']])) {
                $compositionItems = $this->analyzeComposition(
                    $cid, $contrib, $referenceItems[$contrib['membership_id']]
                );
                $items = array_merge($items, $compositionItems);
            }
        }

        // Check for orphaned membership payments (only in first batch)
        if ($offset === 0 && $filter_orphan == 1) {
            $orphans = $this->findOrphanedPayments($cid);
            $items = array_merge($items, $orphans);
        }

        $processed = min($offset + $limit, $total);

        $output = [
            'items' => $items,
            'processed' => $processed,
            'total' => $total,
            'done' => $processed >= $total,
        ];
        if ($filter_composition == 1 && !empty($referenceItems)) {
            $output['reference_items'] = $referenceItems;
        }
        CRM_Utils_JSON::output($output);
    }

    /**
     * Count future contributions linked to memberships for this contact.
     * Optionally scoped to a specific price set.
     */
    private function getTotalContributionCount($cid, $date_from, $psid = 0) {
        $query = "
            SELECT COUNT(DISTINCT c.id)
            FROM civicrm_contribution c
            INNER JOIN civicrm_membership_payment mp ON mp.contribution_id = c.id
            INNER JOIN civicrm_membership m ON mp.membership_id = m.id
            WHERE m.contact_id = %1
            AND c.receive_date >= %2
        ";
        $params = [
            1 => [$cid, 'Integer'],
            2 => [$date_from, 'String'],
        ];
        if ($psid > 0) {
            $query .= "
            AND EXISTS (
                SELECT 1 FROM civicrm_line_item li
                INNER JOIN civicrm_price_field pf ON li.price_field_id = pf.id
                WHERE li.contribution_id = c.id AND pf.price_set_id = %3
            )";
            $params[3] = [$psid, 'Integer'];
        }
        return (int) CRM_Core_DAO::singleValueQuery($query, $params);
    }

    /**
     * Get a batch of future contribution IDs for the contact.
     * Optionally scoped to a specific price set.
     */
    private function getContributionBatch($cid, $offset, $limit, $date_from, $psid = 0) {
        $query = "
            SELECT DISTINCT c.id AS contribution_id, c.receive_date,
                   m.id AS membership_id
            FROM civicrm_contribution c
            INNER JOIN civicrm_membership_payment mp ON mp.contribution_id = c.id
            INNER JOIN civicrm_membership m ON mp.membership_id = m.id
            WHERE m.contact_id = %1
            AND c.receive_date >= %4
        ";
        $params = [
            1 => [$cid, 'Integer'],
            2 => [$limit, 'Integer'],
            3 => [$offset, 'Integer'],
            4 => [$date_from, 'String'],
        ];
        if ($psid > 0) {
            $query .= "
            AND EXISTS (
                SELECT 1 FROM civicrm_line_item li
                INNER JOIN civicrm_price_field pf ON li.price_field_id = pf.id
                WHERE li.contribution_id = c.id AND pf.price_set_id = %5
            )";
            $params[5] = [$psid, 'Integer'];
        }
        $query .= "
            ORDER BY c.receive_date ASC
            LIMIT %2 OFFSET %3
        ";
        $dao = CRM_Core_DAO::executeQuery($query, $params);

        $results = [];
        while ($dao->fetch()) {
            $results[] = [
                'contribution_id' => (int) $dao->contribution_id,
                'receive_date' => $dao->receive_date,
                'membership_id' => (int) $dao->membership_id,
            ];
        }
        return $results;
    }

    /**
     * Analyze a single contribution's line items for inconsistencies.
     */
    private function analyzeContribution($cid, $contrib, $filter_sync, $filter_harmonize) {
        $contact = \Civi\Api4\Contact::get(FALSE)
            ->addSelect('display_name')
            ->addWhere('id', '=', $cid)
            ->execute()->first();
        $display_name = $contact['display_name'] ?? '';

        $membership = \Civi\Api4\Membership::get(FALSE)
            ->addSelect('membership_type_id:label')
            ->addWhere('id', '=', $contrib['membership_id'])
            ->execute()->first();
        $member_name = $membership['membership_type_id:label'] ?? '';

        $item_query = "
            SELECT
                line_item.label AS item_label,
                line_item.id AS line_id,
                line_item.qty AS item_quantity,
                line_item.unit_price AS item_price,
                line_item.line_total AS item_total,
                line_item.tax_amount AS item_tax,
                line_item.financial_type_id AS item_ftype,
                line_item.price_field_value_id AS field_value_id,
                price_field.id AS field_id,
                price_field_value.label AS field_label,
                price_field_value.amount AS field_amount,
                price_field_value.financial_type_id AS field_finance_type,
                contribution.receive_date AS contrib_date,
                price_set.id AS set_id
            FROM civicrm_line_item line_item
                LEFT JOIN civicrm_price_field_value price_field_value
                    ON line_item.price_field_value_id = price_field_value.id
                LEFT JOIN civicrm_price_field price_field
                    ON line_item.price_field_id = price_field.id
                LEFT JOIN civicrm_contribution contribution
                    ON line_item.contribution_id = contribution.id
                LEFT JOIN civicrm_price_set price_set
                    ON price_field.price_set_id = price_set.id
            WHERE line_item.contribution_id = %1
            ORDER BY line_item.id
        ";

        $line_items = CRM_Core_DAO::executeQuery($item_query, [
            1 => [$contrib['contribution_id'], 'Integer'],
        ]);

        $items = [];

        while ($line_items->fetch()) {
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

            $change_unit_price = CRM_Itemmanager_Util::roundMoney($line_items->field_amount / $periods);
            $tax = 0.0;
            if (CRM_Itemmanager_Util::isTaxEnabledInFinancialType($line_items->field_finance_type))
                $tax = CRM_Itemmanager_Util::getTaxRateInFinancialType($line_items->field_finance_type);
            $changed_total = CRM_Itemmanager_Util::roundMoney($line_items->item_quantity * $change_unit_price);
            $changed_tax = CRM_Itemmanager_Util::roundMoney($line_items->item_quantity * $change_unit_price * $tax / 100.0);

            $priceVisiblyChanged = $filter_sync == 1
                && (!CRM_Itemmanager_Util::moneyEquals($line_items->item_price, $change_unit_price)
                    || !CRM_Itemmanager_Util::moneyEquals($line_items->item_total, $changed_total)
                    || !CRM_Itemmanager_Util::moneyEquals($line_items->item_tax, $changed_tax));

            $base = [
                'contact_id' => $cid,
                'display_name' => $display_name,
                'member_name' => $member_name,
                'line_id' => $line_items->line_id,
                'set_id' => $line_items->set_id,
                'field_id' => $line_items->field_id,
                'item_label' => $line_items->item_label,
                'item_quantity' => $line_items->item_quantity,
                'item_price' => (float) $line_items->item_price,
                'item_total' => (float) $line_items->item_total,
                'item_tax' => (float) $line_items->item_tax,
                'periods' => $periods,
                'contrib_date' => $line_date,
                'update_date' => $line_date != $changed_date && $filter_harmonize == 1,
                'change_date' => $changed_date,
                'update_label' => $line_items->item_label != $line_items->field_label,
                'change_label' => $line_items->field_label,
                'update_price' => $priceVisiblyChanged,
                'change_price' => $change_unit_price,
                'change_total' => $changed_total,
                'change_tax' => $changed_tax,
                'empty_relation_id' => null,
                'change_error' => null,
            ];

            if ($base['update_date'] || $base['update_label'] || $base['update_price']) {
                $items[] = $base;
            }
        }

        return $items;
    }

    /**
     * Build a reference set of line items per membership.
     * Uses the earliest contribution in the date range as reference.
     * Optionally scoped to a specific price set.
     *
     * @return array membership_id => [price_field_value_id => [...item data]]
     */
    private function buildReferenceLineItems($cid, $date_from, $psid = 0) {
        $query = "
            SELECT m.id AS membership_id, c.id AS contribution_id,
                   c.receive_date
            FROM civicrm_contribution c
            INNER JOIN civicrm_membership_payment mp ON mp.contribution_id = c.id
            INNER JOIN civicrm_membership m ON mp.membership_id = m.id
            WHERE m.contact_id = %1 AND c.receive_date >= %2
        ";
        $params = [
            1 => [$cid, 'Integer'],
            2 => [$date_from, 'String'],
        ];
        if ($psid > 0) {
            $query .= "
            AND EXISTS (
                SELECT 1 FROM civicrm_line_item li
                INNER JOIN civicrm_price_field pf ON li.price_field_id = pf.id
                WHERE li.contribution_id = c.id AND pf.price_set_id = %3
            )";
            $params[3] = [$psid, 'Integer'];
        }
        $query .= " ORDER BY c.receive_date ASC";
        $dao = CRM_Core_DAO::executeQuery($query, $params);

        // Pick the first contribution per membership as reference
        $refContribIds = [];
        while ($dao->fetch()) {
            $mid = (int) $dao->membership_id;
            if (!isset($refContribIds[$mid])) {
                $refContribIds[$mid] = (int) $dao->contribution_id;
            }
        }

        $reference = [];
        foreach ($refContribIds as $mid => $contribId) {
            $liDao = CRM_Core_DAO::executeQuery("
                SELECT li.price_field_value_id, li.label, li.qty, li.unit_price,
                       li.line_total, li.tax_amount, li.financial_type_id,
                       li.price_field_id
                FROM civicrm_line_item li
                WHERE li.contribution_id = %1 AND li.qty > 0
            ", [1 => [$contribId, 'Integer']]);

            $reference[$mid] = [];
            while ($liDao->fetch()) {
                $pfvId = (int) $liDao->price_field_value_id;
                if ($pfvId > 0) {
                    $reference[$mid][$pfvId] = [
                        'label' => $liDao->label,
                        'qty' => (int) $liDao->qty,
                        'unit_price' => CRM_Itemmanager_Util::roundMoney($liDao->unit_price),
                        'line_total' => CRM_Itemmanager_Util::roundMoney($liDao->line_total),
                        'tax_amount' => CRM_Itemmanager_Util::roundMoney($liDao->tax_amount),
                        'financial_type_id' => (int) $liDao->financial_type_id,
                        'price_field_id' => (int) $liDao->price_field_id,
                    ];
                }
            }
        }

        return $reference;
    }

    /**
     * Compare a contribution's line items against the reference set.
     * Reports missing items (in reference but not in contribution)
     * and extra items (in contribution but not in reference).
     */
    private function analyzeComposition($cid, $contrib, $refItems) {
        $contact = \Civi\Api4\Contact::get(FALSE)
            ->addSelect('display_name')
            ->addWhere('id', '=', $cid)
            ->execute()->first();
        $display_name = $contact['display_name'] ?? '';

        $membership = \Civi\Api4\Membership::get(FALSE)
            ->addSelect('membership_type_id:label')
            ->addWhere('id', '=', $contrib['membership_id'])
            ->execute()->first();
        $member_name = $membership['membership_type_id:label'] ?? '';

        // Get current line items of this contribution
        $liDao = CRM_Core_DAO::executeQuery("
            SELECT li.id, li.price_field_value_id, li.label, li.qty
            FROM civicrm_line_item li
            WHERE li.contribution_id = %1
        ", [1 => [$contrib['contribution_id'], 'Integer']]);

        $currentPfvIds = [];
        $currentItems = [];
        while ($liDao->fetch()) {
            $pfvId = (int) $liDao->price_field_value_id;
            if ($pfvId > 0) {
                $currentPfvIds[$pfvId] = TRUE;
                $currentItems[$pfvId] = [
                    'line_id' => (int) $liDao->id,
                    'label' => $liDao->label,
                    'qty' => (int) $liDao->qty,
                ];
            }
        }

        $items = [];
        $refPfvIds = array_keys($refItems);

        // Missing items: in reference but not in contribution (or qty=0)
        foreach ($refPfvIds as $pfvId) {
            $missing = !isset($currentPfvIds[$pfvId]);
            $cancelled = isset($currentItems[$pfvId]) && $currentItems[$pfvId]['qty'] == 0;
            if ($missing || $cancelled) {
                $ref = $refItems[$pfvId];
                $items[] = [
                    'contact_id' => $cid,
                    'display_name' => $display_name,
                    'member_name' => $member_name,
                    'line_id' => $cancelled ? $currentItems[$pfvId]['line_id'] : null,
                    'set_id' => null,
                    'field_id' => null,
                    'item_label' => $cancelled ? $currentItems[$pfvId]['label'] : null,
                    'item_quantity' => $cancelled ? 0 : null,
                    'item_price' => null,
                    'item_total' => null,
                    'item_tax' => null,
                    'periods' => null,
                    'contrib_date' => $contrib['receive_date'],
                    'update_date' => false,
                    'change_date' => null,
                    'update_label' => false,
                    'change_label' => $ref['label'],
                    'update_price' => false,
                    'change_price' => (float) $ref['unit_price'],
                    'change_total' => (float) $ref['line_total'],
                    'change_tax' => (float) $ref['tax_amount'],
                    'empty_relation_id' => null,
                    'missing_item' => [
                        'contribution_id' => $contrib['contribution_id'],
                        'price_field_value_id' => $pfvId,
                        'price_field_id' => $ref['price_field_id'],
                        'label' => $ref['label'],
                        'qty' => $ref['qty'],
                        'unit_price' => $ref['unit_price'],
                        'line_total' => $ref['line_total'],
                        'tax_amount' => $ref['tax_amount'],
                        'financial_type_id' => $ref['financial_type_id'],
                    ],
                    'extra_item' => null,
                    'change_error' => $missing
                        ? E::ts('Missing item: %1', [1 => $ref['label']])
                        : E::ts('Cancelled item: %1 (exists in reference)', [1 => $ref['label']]),
                ];
            }
        }

        // Qty mismatch: item exists in both but qty differs
        foreach ($refPfvIds as $pfvId) {
            if (isset($currentItems[$pfvId]) && $currentItems[$pfvId]['qty'] > 0) {
                $cur = $currentItems[$pfvId];
                $ref = $refItems[$pfvId];
                if ($cur['qty'] != $ref['qty']) {
                    $newLineTotal = CRM_Itemmanager_Util::roundMoney($ref['qty'] * $ref['unit_price']);
                    $tax = 0.0;
                    if (CRM_Itemmanager_Util::isTaxEnabledInFinancialType($ref['financial_type_id'])) {
                        $tax = CRM_Itemmanager_Util::getTaxRateInFinancialType($ref['financial_type_id']);
                    }
                    $newTaxAmount = CRM_Itemmanager_Util::roundMoney($newLineTotal * $tax / 100.0);

                    $items[] = [
                        'contact_id' => $cid,
                        'display_name' => $display_name,
                        'member_name' => $member_name,
                        'line_id' => $cur['line_id'],
                        'set_id' => null,
                        'field_id' => null,
                        'item_label' => $cur['label'],
                        'item_quantity' => $cur['qty'],
                        'item_price' => (float) $ref['unit_price'],
                        'item_total' => null,
                        'item_tax' => null,
                        'periods' => null,
                        'contrib_date' => $contrib['receive_date'],
                        'update_date' => false,
                        'change_date' => null,
                        'update_label' => false,
                        'change_label' => null,
                        'update_price' => false,
                        'change_price' => null,
                        'change_total' => $newLineTotal,
                        'change_tax' => $newTaxAmount,
                        'empty_relation_id' => null,
                        'missing_item' => null,
                        'extra_item' => null,
                        'qty_sync' => [
                            'line_id' => $cur['line_id'],
                            'target_qty' => $ref['qty'],
                            'unit_price' => $ref['unit_price'],
                            'line_total' => $newLineTotal,
                            'tax_amount' => $newTaxAmount,
                            'financial_type_id' => $ref['financial_type_id'],
                        ],
                        'change_error' => E::ts('Qty mismatch: %1 (is %2, should be %3)', [
                            1 => $cur['label'],
                            2 => $cur['qty'],
                            3 => $ref['qty'],
                        ]),
                    ];
                }
            }
        }

        // Extra items: in contribution but not in reference (and qty > 0)
        foreach ($currentItems as $pfvId => $cur) {
            if (!in_array($pfvId, $refPfvIds) && $cur['qty'] > 0) {
                $items[] = [
                    'contact_id' => $cid,
                    'display_name' => $display_name,
                    'member_name' => $member_name,
                    'line_id' => $cur['line_id'],
                    'set_id' => null,
                    'field_id' => null,
                    'item_label' => $cur['label'],
                    'item_quantity' => $cur['qty'],
                    'item_price' => null,
                    'item_total' => null,
                    'item_tax' => null,
                    'periods' => null,
                    'contrib_date' => $contrib['receive_date'],
                    'update_date' => false,
                    'change_date' => null,
                    'update_label' => false,
                    'change_label' => null,
                    'update_price' => false,
                    'change_price' => null,
                    'change_total' => null,
                    'change_tax' => null,
                    'empty_relation_id' => null,
                    'missing_item' => null,
                    'extra_item' => $cur['line_id'],
                    'qty_sync' => null,
                    'change_error' => E::ts('Extra item: %1 (not in reference)', [1 => $cur['label']]),
                ];
            }
        }

        return $items;
    }

    /**
     * Find orphaned membership payment records (contribution missing).
     */
    private function findOrphanedPayments($cid) {
        $query = "
            SELECT mp.id AS pay_id, mt.name AS member_name
            FROM civicrm_membership_payment mp
            INNER JOIN civicrm_membership m ON mp.membership_id = m.id
            LEFT JOIN civicrm_membership_type mt ON mt.id = m.membership_type_id
            LEFT JOIN civicrm_contribution c ON mp.contribution_id = c.id
            WHERE m.contact_id = %1
            AND c.id IS NULL
        ";
        $dao = CRM_Core_DAO::executeQuery($query, [
            1 => [$cid, 'Integer'],
        ]);

        $contact = \Civi\Api4\Contact::get(FALSE)
            ->addSelect('display_name')
            ->addWhere('id', '=', $cid)
            ->execute()->first();

        $items = [];
        while ($dao->fetch()) {
            $items[] = [
                'contact_id' => $cid,
                'display_name' => $contact['display_name'] ?? '',
                'member_name' => $dao->member_name,
                'line_id' => null,
                'set_id' => null,
                'field_id' => null,
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
                'empty_relation_id' => (int) $dao->pay_id,
                'change_error' => 'membership contribution relation ' . (int) $dao->pay_id . ' is missing',
            ];
        }
        return $items;
    }

    /**
     * Handle update of selected line items.
     * Adapted from ItemMaintenanceStub::handleUpdate.
     */
    private function handleUpdate() {
        $input = json_decode(file_get_contents('php://input'), TRUE);
        $filter_sync = (int) ($input['filter_sync'] ?? 0);
        $filter_harmonize = (int) ($input['filter_harmonize'] ?? 0);
        $selected_items = $input['viewlist'] ?? [];
        $delete_list = $input['deletelist'] ?? [];
        $add_list = $input['addlist'] ?? [];
        $cancel_list = $input['cancellist'] ?? [];
        $syncqty_list = $input['syncqtylist'] ?? [];

        $results = ['updated' => 0, 'deleted' => 0, 'added' => 0, 'cancelled' => 0, 'synced' => 0, 'errors' => []];

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

            $change_unit_price = CRM_Itemmanager_Util::roundMoney($priceFieldValueInfo['amount'] / $periods);
            $tax = 0.0;
            if (CRM_Itemmanager_Util::isTaxEnabledInFinancialType((int) $priceFieldValueInfo['financial_type_id']))
                $tax = CRM_Itemmanager_Util::getTaxRateInFinancialType((int) $priceFieldValueInfo['financial_type_id']);
            $changed_total = CRM_Itemmanager_Util::roundMoney($lineitemInfo['qty'] * $change_unit_price);
            $changed_tax = CRM_Itemmanager_Util::roundMoney($lineitemInfo['qty'] * $change_unit_price * $tax / 100.0);

            // Update date
            if ($line_date != $changed_date && $filter_harmonize == 1) {
                $update_contribution = TRUE;
                try {
                    CRM_Core_DAO::executeQuery($update_date_query, [
                        1 => [$changed_date, 'String'],
                        2 => [(int) $lineitemInfo['contribution_id'], 'Integer'],
                    ]);
                } catch (\Exception $e) {
                    $results['errors'][] = E::ts('Error updating contribution date for %1', [1 => $line_item]);
                }
            }

            // Update label
            if ($lineitemInfo['label'] != $priceFieldValueInfo['label']) {
                $update_label = TRUE;
                try {
                    CRM_Core_DAO::executeQuery($update_label_query, [
                        1 => [$priceFieldValueInfo['label'], 'String'],
                        2 => [(int) $line_item, 'Integer'],
                    ]);
                } catch (\Exception $e) {
                    $results['errors'][] = E::ts('Error updating label for %1', [1 => $line_item]);
                }
            }

            // Update price
            if (!CRM_Itemmanager_Util::moneyEquals($lineitemInfo['unit_price'], $change_unit_price) && $filter_sync == 1) {
                $update_price = TRUE;

                try {
                    CRM_Core_DAO::executeQuery($update_lineitem_query, [
                        1 => [CRM_Itemmanager_Util::toMachineMoney($change_unit_price), 'Money'],
                        2 => [CRM_Itemmanager_Util::toMachineMoney($changed_total), 'Money'],
                        3 => [CRM_Itemmanager_Util::toMachineMoney($changed_tax), 'Money'],
                        4 => [(int) $line_item, 'Integer'],
                    ]);
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

                        CRM_Core_DAO::executeQuery($update_financial_item, [
                            1 => [CRM_Itemmanager_Util::toMachineMoney($amount), 'Money'],
                            2 => [(int) $financeitem['financeitem']['id'], 'Integer'],
                        ]);

                        $transaction = CRM_Itemmanager_Util::getFinancialEntityTrxnByFinancialItemId(
                            (int) $financeitem['financeitem']['id']
                        );
                        if (!empty($transaction['id'])) {
                            CRM_Core_DAO::executeQuery($update_financial_transaktion_query, [
                                1 => [CRM_Itemmanager_Util::toMachineMoney($amount), 'Money'],
                                2 => [(int) $transaction['id'], 'Integer'],
                            ]);
                        }
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
                    CRM_Core_DAO::executeQuery($update_contribution_query, [
                        1 => [CRM_Itemmanager_Util::toMachineMoney($tax_total + $total), 'Money'],
                        2 => [(int) $lineitemInfo['contribution_id'], 'Integer'],
                    ]);

                    $transaction = CRM_Itemmanager_Util::getFinancialEntityIdTrxnByContributionId(
                        (int) $lineitemInfo['contribution_id']
                    );
                    if (!empty($transaction['id'])) {
                        CRM_Core_DAO::executeQuery($update_financial_transaktion_query, [
                            1 => [CRM_Itemmanager_Util::toMachineMoney($tax_total + $total), 'Money'],
                            2 => [(int) $transaction['id'], 'Integer'],
                        ]);
                    }
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

        // Handle adding missing line items
        foreach ($add_list as $addItem) {
            if (!is_array($addItem) || empty($addItem['contribution_id'])) continue;

            try {
                $contribId = (int) $addItem['contribution_id'];
                $contributionInfo = \Civi\Api4\Contribution::get(FALSE)
                    ->addWhere('id', '=', $contribId)
                    ->execute()->single();

                // Determine entity_table and entity_id from existing line items
                $existingLi = CRM_Core_DAO::executeQuery(
                    "SELECT entity_table, entity_id FROM civicrm_line_item WHERE contribution_id = %1 LIMIT 1",
                    [1 => [$contribId, 'Integer']]
                );
                $entityTable = 'civicrm_contribution';
                $entityId = $contribId;
                if ($existingLi->fetch()) {
                    $entityTable = $existingLi->entity_table;
                    $entityId = (int) $existingLi->entity_id;
                }

                $params = [
                    'contribution_id' => $contribId,
                    'entity_table' => $entityTable,
                    'entity_id' => $entityId,
                    'price_field_id' => (int) $addItem['price_field_id'],
                    'price_field_value_id' => (int) $addItem['price_field_value_id'],
                    'label' => $addItem['label'],
                    'qty' => (int) $addItem['qty'],
                    'unit_price' => CRM_Itemmanager_Util::roundMoney($addItem['unit_price']),
                    'line_total' => CRM_Itemmanager_Util::roundMoney($addItem['line_total']),
                    'tax_amount' => CRM_Itemmanager_Util::roundMoney($addItem['tax_amount']),
                    'financial_type_id' => (int) $addItem['financial_type_id'],
                ];

                CRM_Price_BAO_LineItem::create($params);

                // Update contribution totals
                $tax_total = CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID($contribId);
                $total = CRM_Itemmanager_Util::getAmountTotalFromContributionID($contribId);
                CRM_Core_DAO::executeQuery(
                    "UPDATE " . $contribution_table . " SET total_amount = %1, net_amount = %1 WHERE id = %2",
                    [1 => [CRM_Itemmanager_Util::toMachineMoney($tax_total + $total), 'Money'], 2 => [$contribId, 'Integer']]
                );

                $transaction = CRM_Itemmanager_Util::getFinancialEntityIdTrxnByContributionId($contribId);
                if (!empty($transaction['id'])) {
                    CRM_Core_DAO::executeQuery(
                        "UPDATE " . $financial_transaktion_table . " SET amount = %1 WHERE id = %2",
                        [1 => [CRM_Itemmanager_Util::toMachineMoney($tax_total + $total), 'Money'], 2 => [(int) $transaction['id'], 'Integer']]
                    );
                }

                $results['added']++;
            } catch (\Exception $e) {
                $results['errors'][] = E::ts('Error adding line item to contribution %1: %2', [
                    1 => $addItem['contribution_id'] ?? '?',
                    2 => $e->getMessage(),
                ]);
            }
        }

        // Handle qty synchronisation
        foreach ($syncqty_list as $syncItem) {
            if (!is_array($syncItem) || empty($syncItem['line_id'])) continue;

            $lineId = (int) $syncItem['line_id'];
            if ($lineId <= 0) continue;

            try {
                $previousLineItem = civicrm_api3('LineItem', 'getsingle', ['id' => $lineId]);
                $contribId = (int) $previousLineItem['contribution_id'];

                $targetQty = (int) $syncItem['target_qty'];
                $unitPrice = CRM_Itemmanager_Util::roundMoney((float) $syncItem['unit_price']);
                $lineTotal = CRM_Itemmanager_Util::roundMoney((float) $syncItem['line_total']);
                $taxAmount = CRM_Itemmanager_Util::roundMoney((float) $syncItem['tax_amount']);

                if ($targetQty === 0) {
                    // Qty=0 → follow LineItemCancel::postProcess() pattern
                    // Only cancel entity when no other active line items reference it.
                    if (!empty($previousLineItem['entity_id']) && !empty($previousLineItem['entity_table'])) {
                        $shouldCancel = TRUE;
                        if ($previousLineItem['entity_table'] === 'civicrm_membership') {
                            $otherActive = civicrm_api3('LineItem', 'getcount', [
                                'entity_table' => 'civicrm_membership',
                                'entity_id' => $previousLineItem['entity_id'],
                                'line_total' => ['>' => 0],
                                'id' => ['!=' => $lineId],
                            ]);
                            $shouldCancel = ($otherActive == 0);
                        }
                        if ($shouldCancel) {
                            CRM_Itemmanager_Util_LineItemEditor::cancelEntity(
                                (int) $previousLineItem['entity_id'],
                                $previousLineItem['entity_table']
                            );
                        }
                    }

                    civicrm_api3('LineItem', 'create', [
                        'id' => $lineId,
                        'qty' => 0,
                        'participant_count' => 0,
                        'line_total' => 0.00,
                        'tax_amount' => 0.00,
                    ]);

                    $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($contribId);
                    $taxTotal = CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID($contribId);
                    CRM_Itemmanager_Util_LineItemEditor::recordAdjustedAmt(
                        $updatedAmount,
                        $contribId,
                        $taxTotal,
                        FALSE
                    );

                    CRM_Itemmanager_Util_LineItemEditor::insertFinancialItemOnEdit(
                        $lineId,
                        $previousLineItem
                    );
                } else {
                    // Normal qty sync: update line item and financial records
                    CRM_Core_DAO::executeQuery(
                        "UPDATE " . $line_item_table . " SET qty = %1, unit_price = %2, line_total = %3, tax_amount = %4 WHERE id = %5",
                        [
                            1 => [$targetQty, 'Integer'],
                            2 => [CRM_Itemmanager_Util::toMachineMoney($unitPrice), 'Money'],
                            3 => [CRM_Itemmanager_Util::toMachineMoney($lineTotal), 'Money'],
                            4 => [CRM_Itemmanager_Util::toMachineMoney($taxAmount), 'Money'],
                            5 => [$lineId, 'Integer'],
                        ]
                    );

                    // Update financial records
                    $financeitems = CRM_Itemmanager_Util::getFinancialFullRecordsByLineItemId($lineId);
                    if (!$financeitems['is_error']) {
                        foreach ($financeitems['values'] as $financeitem) {
                            $amount = $financeitem['accountinfo']['is_tax'] ? $taxAmount : $lineTotal;
                            CRM_Core_DAO::executeQuery(
                                "UPDATE " . $financial_item_table . " SET amount = %1 WHERE id = %2",
                                [1 => [CRM_Itemmanager_Util::toMachineMoney($amount), 'Money'], 2 => [(int) $financeitem['financeitem']['id'], 'Integer']]
                            );
                            $trxn = CRM_Itemmanager_Util::getFinancialEntityTrxnByFinancialItemId(
                                (int) $financeitem['financeitem']['id']
                            );
                            if (!empty($trxn['id'])) {
                                CRM_Core_DAO::executeQuery(
                                    "UPDATE " . $financial_transaktion_table . " SET amount = %1 WHERE id = %2",
                                    [1 => [CRM_Itemmanager_Util::toMachineMoney($amount), 'Money'], 2 => [(int) $trxn['id'], 'Integer']]
                                );
                            }
                        }
                    }

                    // Update contribution totals
                    $tax_total = CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID($contribId);
                    $total = CRM_Itemmanager_Util::getAmountTotalFromContributionID($contribId);
                    CRM_Core_DAO::executeQuery(
                        "UPDATE " . $contribution_table . " SET total_amount = %1, net_amount = %1 WHERE id = %2",
                        [1 => [CRM_Itemmanager_Util::toMachineMoney($tax_total + $total), 'Money'], 2 => [$contribId, 'Integer']]
                    );

                    $transaction = CRM_Itemmanager_Util::getFinancialEntityIdTrxnByContributionId($contribId);
                    if (!empty($transaction['id'])) {
                        CRM_Core_DAO::executeQuery(
                            "UPDATE " . $financial_transaktion_table . " SET amount = %1 WHERE id = %2",
                            [1 => [CRM_Itemmanager_Util::toMachineMoney($tax_total + $total), 'Money'], 2 => [(int) $transaction['id'], 'Integer']]
                        );
                    }
                }

                $results['synced']++;
            } catch (\Exception $e) {
                $results['errors'][] = E::ts('Error syncing qty for line item %1: %2', [
                    1 => $lineId,
                    2 => $e->getMessage(),
                ]);
            }
        }

        // Handle cancelling extra line items — follows LineItemCancel::postProcess() pattern
        foreach ($cancel_list as $lineId) {
            $lineId = (int) $lineId;
            if ($lineId <= 0) continue;

            try {
                $previousLineItem = civicrm_api3('LineItem', 'getsingle', ['id' => $lineId]);
                $contribId = (int) $previousLineItem['contribution_id'];

                // Only cancel entity when no other active line items reference it.
                if (!empty($previousLineItem['entity_id']) && !empty($previousLineItem['entity_table'])) {
                    $shouldCancel = TRUE;
                    if ($previousLineItem['entity_table'] === 'civicrm_membership') {
                        $otherActive = civicrm_api3('LineItem', 'getcount', [
                            'entity_table' => 'civicrm_membership',
                            'entity_id' => $previousLineItem['entity_id'],
                            'line_total' => ['>' => 0],
                            'id' => ['!=' => $lineId],
                        ]);
                        $shouldCancel = ($otherActive == 0);
                    }
                    if ($shouldCancel) {
                        CRM_Itemmanager_Util_LineItemEditor::cancelEntity(
                            (int) $previousLineItem['entity_id'],
                            $previousLineItem['entity_table']
                        );
                    }
                }

                // Zero out the line item
                civicrm_api3('LineItem', 'create', [
                    'id' => $lineId,
                    'qty' => 0,
                    'participant_count' => 0,
                    'line_total' => 0.00,
                    'tax_amount' => 0.00,
                ]);

                // Update contribution status and totals
                $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($contribId);
                $taxAmount = CRM_Itemmanager_Util::getTaxAmountTotalFromContributionID($contribId);
                CRM_Itemmanager_Util_LineItemEditor::recordAdjustedAmt(
                    $updatedAmount,
                    $contribId,
                    $taxAmount,
                    FALSE
                );

                // Record financial adjustment
                CRM_Itemmanager_Util_LineItemEditor::insertFinancialItemOnEdit(
                    $lineId,
                    $previousLineItem
                );

                $results['cancelled']++;
            } catch (\Exception $e) {
                $results['errors'][] = E::ts('Error cancelling line item %1: %2', [
                    1 => $lineId,
                    2 => $e->getMessage(),
                ]);
            }
        }

        CRM_Utils_JSON::output($results);
    }
}
