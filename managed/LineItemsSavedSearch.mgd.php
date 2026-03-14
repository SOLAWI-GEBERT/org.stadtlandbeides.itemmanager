<?php

use CRM_Itemmanager_ExtensionUtil as E;

return [
  [
    'name' => 'Line_Items',
    'entity' => 'SavedSearch',
    'cleanup' => 'always',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Line_Items',
        'label' => E::ts('Contribution Amount'),
        'form_values' => NULL,
        'mapping_id' => NULL,
        'search_custom_id' => NULL,
        'api_entity' => 'LineItem',
        'api_params' => [
          'version' => 4,
          'select' => [
            'entity_id',
            'label',
            'financial_type_id:label',
            'qty',
            'unit_price',
            'line_total',
            'tax_amount',
          ],
          'orderBy' => [],
          'where' => [],
          'groupBy' => [],
          'join' => [
            [
              'Contribution AS LineItem_Contribution_contribution_id_01',
              'INNER',
              [
                'contribution_id',
                '=',
                'LineItem_Contribution_contribution_id_01.id',
              ],
            ],
          ],
          'having' => [],
        ],
      ],
    ],
  ],
  [
    'name' => 'SavedSearch_Line_Items_SearchDisplay_Contribution_Amount_Tax',
    'entity' => 'SearchDisplay',
    'cleanup' => 'always',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Line_Items_Table_Tax',
        'label' => E::ts('Contribution Tax Amounts'),
        'saved_search_id.name' => 'Line_Items',
        'type' => 'table',
        'settings' => [
          'description' => NULL,
          'limit' => 25,
          'classes' => [
            'table',
            'table-striped',
            'table-bordered',
          ],
          'pager' => [
            'show_count' => FALSE,
            'expose_limit' => FALSE,
            'hide_single' => TRUE,
          ],
          'sort' => [
            [
              'id',
              'ASC',
            ],
          ],
          'columns' => [
            [
              'type' => 'field',
              'key' => 'label',
              'dataType' => 'String',
              'label' => E::ts('Item'),
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'financial_type_id:label',
              'dataType' => 'Integer',
              'label' => E::ts('Financial Type'),
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'qty',
              'dataType' => 'Integer',
              'label' => E::ts('Qty'),
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'unit_price',
              'dataType' => 'Money',
              'label' => E::ts('Unit Price'),
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'line_total',
              'dataType' => 'Money',
              'label' => E::ts('Total Price'),
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'tax_amount',
              'dataType' => 'Money',
              'label' => E::ts('Tax Amount'),
              'sortable' => FALSE,
            ],
            [
              'size' => 'btn-xs',
              'links' => [
                [
                  'path' => 'civicrm/lineitem/edit?reset=1&id=[id]',
                  'icon' => 'fa-pencil',
                  'text' => '',
                  'style' => 'default',
                  'conditions' => [
                    [
                      'check user permission',
                      '=',
                      [
                        'edit memberships',
                      ],
                    ],
                  ],
                  'task' => '',
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'path' => 'civicrm/lineitem/cancel?reset=1&id=[id]',
                  'icon' => 'fa-rotate-left',
                  'text' => '',
                  'style' => 'default',
                  'conditions' => [
                    [
                      'check user permission',
                      '=',
                      [
                        'edit memberships',
                      ],
                    ],
                  ],
                  'task' => '',
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
              ],
              'type' => 'buttons',
              'alignment' => 'text-right',
            ],
          ],
          'cssRules' => [
            [
              'disabled',
              'qty',
              '=',
              0,
            ],
          ],
          'actions' => FALSE,
          'headerCount' => FALSE,
          'button' => NULL,
        ],
      ],
    ],
  ],
  [
    'name' => 'SavedSearch_Line_Items_SearchDisplay_Line_Items_Table',
    'entity' => 'SearchDisplay',
    'cleanup' => 'always',
    'update' => 'always',
    'params' => [
      'version' => 4,
      'values' => [
        'name' => 'Line_Items_Table',
        'label' => E::ts('Contribution Line Items'),
        'saved_search_id.name' => 'Line_Items',
        'type' => 'table',
        'settings' => [
          'description' => NULL,
          'sort' => [
            [
              'id',
              'ASC',
            ],
          ],
          'limit' => 25,
          'classes' => [
            'table',
            'table-striped',
            'table-bordered',
          ],
          'pager' => [
            'show_count' => FALSE,
            'expose_limit' => FALSE,
            'hide_single' => TRUE,
          ],
          'columns' => [
            [
              'type' => 'field',
              'key' => 'label',
              'dataType' => 'String',
              'label' => E::ts('Item'),
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'financial_type_id:label',
              'dataType' => 'Integer',
              'label' => E::ts('Financial Type'),
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'qty',
              'dataType' => 'Integer',
              'label' => E::ts('Qty'),
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'unit_price',
              'dataType' => 'Money',
              'label' => E::ts('Unit Price'),
              'sortable' => FALSE,
            ],
            [
              'type' => 'field',
              'key' => 'line_total',
              'dataType' => 'Money',
              'label' => E::ts('Total Price'),
              'sortable' => FALSE,
            ],
            [
              'size' => 'btn-xs',
              'links' => [
                [
                  'path' => 'civicrm/lineitem/edit?reset=1&id=[id]',
                  'icon' => 'fa-pencil',
                  'text' => '',
                  'style' => 'default',
                  'conditions' => [
                    [
                      'check user permission',
                      '=',
                      [
                        'edit memberships',
                      ],
                    ],
                  ],
                  'task' => '',
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
                [
                  'path' => 'civicrm/lineitem/cancel?reset=1&id=[id]',
                  'icon' => 'fa-rotate-left',
                  'text' => '',
                  'style' => 'default',
                  'conditions' => [
                    [
                      'check user permission',
                      '=',
                      [
                        'edit memberships',
                      ],
                    ],
                  ],
                  'task' => '',
                  'entity' => '',
                  'action' => '',
                  'join' => '',
                  'target' => 'crm-popup',
                ],
              ],
              'type' => 'buttons',
              'alignment' => 'text-right',
            ],
          ],
          'cssRules' => [
            [
              'disabled',
              'qty',
              '=',
              0,
            ],
          ],
          'actions' => FALSE,
          'headerCount' => FALSE,
          'button' => NULL,
        ],
      ],
    ],
  ],
];
