<?php

use CRM_Itemmanager_ExtensionUtil as E;

class CRM_Itemmanager_Page_ItemMaintenance extends CRM_Core_Page {

    public function run() {
        CRM_Utils_System::setTitle(E::ts('Item Maintenance'));

        $defaultDate = date('Y-m-d', strtotime('-1 year'));

        $this->assign('default_date', $defaultDate);
        $this->assign('analyze_url', CRM_Utils_System::url(
            'civicrm/items/maintenancestub', '', TRUE, NULL, FALSE
        ));

        $tags = \Civi\Api4\Tag::get(FALSE)
            ->addSelect('id', 'name', 'label', 'color')
            ->addWhere('used_for', 'CONTAINS', 'civicrm_contact')
            ->addOrderBy('label', 'ASC')
            ->execute();

        $tagList = [];
        foreach ($tags as $tag) {
            $tagList[] = [
                'id' => (int) $tag['id'],
                'label' => $tag['label'] ?? $tag['name'],
                'color' => $tag['color'] ?? '',
            ];
        }
        $this->assign('tags', $tagList);

        parent::run();
    }
}
