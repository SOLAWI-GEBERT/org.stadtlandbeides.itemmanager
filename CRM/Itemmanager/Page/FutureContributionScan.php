<?php

use CRM_Itemmanager_ExtensionUtil as E;

/**
 * Page controller for the future contribution scan.
 *
 * Renders the scan UI for a single contact and provides the
 * AJAX stub URL for batch analysis.
 *
 * Accepts either:
 *   - contrib_id + cid  (from contribution list action link — derives psid and date)
 *   - cid + psid        (direct call with explicit price set)
 */
class CRM_Itemmanager_Page_FutureContributionScan extends CRM_Core_Page {

    public function run() {
        $cid = (int) CRM_Utils_Request::retrieve('cid', 'Integer', $this, TRUE);
        $contribId = (int) CRM_Utils_Request::retrieve('contrib_id', 'Integer');
        $psid = (int) CRM_Utils_Request::retrieve('psid', 'Integer');
        $defaultDate = date('Y-m-d');

        // Derive price set and start date from contribution
        if ($contribId > 0) {
            $contribution = \Civi\Api4\Contribution::get(FALSE)
                ->addSelect('receive_date')
                ->addWhere('id', '=', $contribId)
                ->execute()->first();
            if ($contribution) {
                $defaultDate = date('Y-m-d', strtotime($contribution['receive_date']));
            }

            // Determine price set from the contribution's line items
            if ($psid <= 0) {
                $psid = (int) CRM_Core_DAO::singleValueQuery("
                    SELECT pf.price_set_id
                    FROM civicrm_line_item li
                    INNER JOIN civicrm_price_field pf ON li.price_field_id = pf.id
                    WHERE li.contribution_id = %1 AND li.qty > 0
                    LIMIT 1
                ", [1 => [$contribId, 'Integer']]);
            }
        }

        $contact = \Civi\Api4\Contact::get(FALSE)
            ->addSelect('display_name')
            ->addWhere('id', '=', $cid)
            ->execute()->first();

        $titleParts = [$contact['display_name'] ?? ''];
        if ($psid > 0) {
            $priceSetTitle = CRM_Core_DAO::getFieldValue('CRM_Price_DAO_PriceSet', $psid, 'title');
            if ($priceSetTitle) {
                $titleParts[] = $priceSetTitle;
            }
        }
        CRM_Utils_System::setTitle(E::ts('Future Item Scan: %1', [1 => implode(' — ', $titleParts)]));

        $stubQuery = 'cid=' . $cid;
        if ($psid > 0) {
            $stubQuery .= '&psid=' . $psid;
        }

        $this->assign('contact_id', $cid);
        $this->assign('display_name', $contact['display_name'] ?? '');
        $this->assign('price_set_id', $psid);
        $this->assign('analyze_url', CRM_Utils_System::url(
            'civicrm/items/futurescanstub', $stubQuery, TRUE, NULL, FALSE
        ));
        $this->assign('default_date', $defaultDate);

        parent::run();
    }
}
