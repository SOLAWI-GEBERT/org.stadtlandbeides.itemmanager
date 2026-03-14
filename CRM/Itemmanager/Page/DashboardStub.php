<?php
/*
 * ------------------------------------------------------------+
 * | stadt, land, beides - CSA Support                                       |
 * | Copyright (C) 2022 Stadt, Land, Beides                               |
 * | Author: A. Gebert (webmaster -at- stadt-land-beides.de)  |
 * | https://stadt-land-beides.de/                                              |
 * +-------------------------------------------------------------+
 * | This program is released as free software under the          |
 * | Affero GPL license. You can redistribute it and/or               |
 * | modify it under the terms of this license which you            |
 * | can read by viewing the included agpl.txt or online             |
 * | at www.gnu.org/licenses/agpl.html. Removal of this            |
 * | copyright header is strictly prohibited without                    |
 * | written permission from the original author(s).                   |
 * +-------------------------------------------------------------
 */

use CRM_Itemmanager_ExtensionUtil as E;

/**
 * Stub page that loads line-item details for a single membership.
 * Called via AJAX from the Dashboard accordion.
 */
class CRM_Itemmanager_Page_DashboardStub extends CRM_Core_Page {

    public function run() {
        $contact_id = (int) CRM_Utils_Request::retrieve('cid', 'Integer');
        $membership_id = (int) CRM_Utils_Request::retrieve('mid', 'Integer');

        if ($membership_id <= 0 || $contact_id <= 0) {
            $this->assign('data_error', TRUE);
            $this->processError("ERROR", E::ts('Invalid parameters'), 'Missing cid or mid');
            parent::run();
            return;
        }

        // Handle cleanup POST request
        $cleanup_action = CRM_Utils_Request::retrieve('cleanup_orphans', 'String');
        if ($cleanup_action === '1') {
            $this->cleanupOrphanedPayments($membership_id);
            CRM_Utils_JSON::output(['status' => 'ok']);
            return;
        }

        $error = FALSE;
        $max_time = max(ini_get('max_execution_time'), 30);
        $max_time_member = 0.75 * $max_time;
        $time_start = microtime(true);

        $field_data = [];
        $orphaned_payments = [];

        $member_array = CRM_Itemmanager_Util::getLastMemberShipsFullRecordByContactId($contact_id);

        if ($member_array['is_error']) {
            $error = TRUE;
            $this->assign('data_error', $error);
            $this->processError("ERROR", E::ts('Retrieve memberships'), $member_array['error_message']);
            parent::run();
            return;
        }

        $membership = NULL;
        foreach ($member_array['values'] as $m) {
            if ((int) $m['memberdata']['id'] === $membership_id) {
                $membership = $m;
                break;
            }
        }

        if ($membership === NULL) {
            $error = TRUE;
            $this->assign('data_error', $error);
            $this->processError("ERROR", E::ts('Membership not found'), 'ID: ' . $membership_id);
            parent::run();
            return;
        }

        foreach ($membership['payinfo'] as $contribution_link) {
            // Skip payment records with no contribution (valid state after contribution deletion)
            if (empty($contribution_link['contribution_id'])) {
                continue;
            }

            $linerecords = CRM_Itemmanager_Util::getLineitemFullRecordByContributionId((int) $contribution_link['contribution_id']);
            if ($linerecords['is_error']) {
                $error = TRUE;
                $this->assign('data_error', $error);
                $this->processError("ERROR", E::ts('Retrieve line items'), $linerecords['error_message']);
                $this->processDetail($membership['typeinfo']['name'], (int) $contribution_link['contribution_id']);
                continue;
            }

            $testcount = \Civi\Api4\Contribution::get(FALSE)
                ->addWhere('id', '=', (int) $contribution_link['contribution_id'])
                ->selectRowCount()
                ->execute()
                ->countMatched();
            if ($testcount == 0) {
                $orphaned_payments[] = [
                    'pay_id' => (int) $contribution_link['id'],
                    'contribution_id' => (int) $contribution_link['contribution_id'],
                ];
                continue;
            }

            $contribution = \Civi\Api4\Contribution::get(FALSE)
                ->addSelect('receive_date')
                ->addWhere('id', '=', (int) $contribution_link['contribution_id'])
                ->execute()->single();
            $contrib_date = $contribution['receive_date'];
            $line_timestamp = date_create($contrib_date);

            if (!$line_timestamp) {
                $error = TRUE;
                $this->assign('data_error', $error);
                $this->processError("ERROR", E::ts('Invalid contribution date'),
                    E::ts('Contribution %1 has no valid date', [1 => (int) $contribution_link['contribution_id']]));
                $this->processDetail($membership['typeinfo']['name'], (int) $contribution_link['contribution_id']);
                continue;
            }

            foreach ($linerecords as $lineitem) {
                try {
                    $max_field_id = CRM_Itemmanager_Util::getLastPricefieldSuccessor(
                        $lineitem['valuedata']['id']);

                    $line_date = $line_timestamp->format('Y-M');
                    $item_quantity = $lineitem['linedata']['qty'];

                    if (!array_key_exists($max_field_id, $field_data))
                        $field_data[$max_field_id] = [];
                    $_field = &$field_data[$max_field_id];
                    if (!array_key_exists($item_quantity, $_field)) {
                        $_details = [
                            'item_quantity' => (string) $item_quantity,
                            'item_label' => $lineitem['linedata']['label'],
                            'item_dates' => [],
                            'min' => null,
                            'max' => null,
                        ];
                        $_field[$item_quantity] = $_details;
                    }
                    $_dates = &$_field[$item_quantity]['item_dates'];
                    $_dates[] = $line_date;
                    $_field[$item_quantity]['min'] = min($_dates);
                    $_field[$item_quantity]['max'] = max($_dates);

                } catch (\Exception $e) {
                    $error = TRUE;
                    $this->assign('data_error', $error);
                    $this->processError("ERROR", E::ts('Combine line items'), $e->getMessage());
                    $this->processDetail(
                        $membership['typeinfo']['name'],
                        (int) $contribution_link['contribution_id'],
                        $lineitem['linedata']['label']
                    );
                    continue;
                }

                $time_end = microtime(true);
                $execution_time = ($time_end - $time_start);
                if ($execution_time > $max_time_member)
                    break;
            }

            $time_end = microtime(true);
            $execution_time = ($time_end - $time_start);
            if ($execution_time > $max_time_member)
                break;
        }

        $this->assign('field_data', $field_data);
        $this->assign('data_error', $error);
        $this->assign('orphaned_payments', $orphaned_payments);
        $this->assign('cleanup_url', CRM_Utils_System::url('civicrm/items/tabstub',
            "cid=$contact_id&mid=$membership_id&cleanup_orphans=1"));

        parent::run();
    }

    protected function processError($status, $title, $message) {
        CRM_Core_Session::setStatus(
            $status . "<br/>" . $message,
            ts('Error', ['domain' => 'org.stadtlandbeides.itemmanager']),
            'error'
        );
        $this->assign("error_title", $title);
        $this->assign("error_message", $message);
    }

    protected function processDetail($membership, $contribution, $lineitem = null) {
        $this->assign("detail_member", $membership);
        $this->assign("detail_contribution", $contribution);
        $this->assign("detail_lineitem", $lineitem);
    }

    /**
     * Delete orphaned membership_payment records for a given membership
     * where the linked contribution no longer exists.
     */
    private function cleanupOrphanedPayments($membership_id) {
        $dao = CRM_Core_DAO::executeQuery(
            "SELECT mp.id, mp.contribution_id
             FROM civicrm_membership_payment mp
             WHERE mp.membership_id = %1",
            [1 => [$membership_id, 'Integer']]
        );

        $deleted = 0;
        while ($dao->fetch()) {
            $contribCount = \Civi\Api4\Contribution::get(FALSE)
                ->addWhere('id', '=', (int) $dao->contribution_id)
                ->selectRowCount()
                ->execute()
                ->countMatched();

            if ($contribCount == 0) {
                CRM_Core_DAO::executeQuery(
                    "DELETE FROM civicrm_membership_payment WHERE id = %1",
                    [1 => [(int) $dao->id, 'Integer']]
                );
                $deleted++;
            }
        }

        CRM_Core_Session::setStatus(
            E::ts('Deleted %1 orphaned membership payment relation(s).', [1 => $deleted]),
            E::ts('Success'), 'success'
        );
    }
}
