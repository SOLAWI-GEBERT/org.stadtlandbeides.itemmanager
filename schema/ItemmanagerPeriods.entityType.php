<?php
use CRM_Itemmanager_ExtensionUtil as E;
return [
  'name' => 'ItemmanagerPeriods',
  'table' => 'civicrm_itemmanager_periods',
  'class' => 'CRM_Itemmanager_DAO_ItemmanagerPeriods',
  'getInfo' => fn() => [
    'title' => E::ts('Itemmanager Periods'),
    'title_plural' => E::ts('Itemmanager Periodses'),
    'description' => E::ts('Stores the common data, how often the items will be repeated'),
    'log' => TRUE,
  ],
  'getFields' => fn() => [
    'id' => [
      'title' => E::ts('ID'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'required' => TRUE,
      'description' => E::ts('Unique ItemmanagerPeriods ID'),
      'primary_key' => TRUE,
      'auto_increment' => TRUE,
    ],
    'price_set_id' => [
      'title' => E::ts('Price Set'),
      'sql_type' => 'int unsigned',
      'input_type' => 'EntityRef',
      'description' => E::ts('FK to civicrm_price_set'),
      'entity_reference' => [
        'entity' => 'PriceSet',
        'key' => 'id',
        'on_delete' => 'CASCADE',
      ],
    ],
    'period_start_on' => [
      'title' => E::ts('Booking Period Start on'),
      'sql_type' => 'date',
      'input_type' => 'Select Date',
      'description' => E::ts('If non-zero, do not show this field before the date specified'),
      'usage' => [
        'import',
        'export',
        'duplicate_matching',
      ],
      'input_attrs' => [
        'format_type' => 'activityDate',
      ],
    ],
    'periods' => [
      'title' => E::ts('Periods'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Text',
      'description' => E::ts('Number of periods at start'),
      'default' => NULL,
    ],
    'period_type' => [
      'title' => E::ts('Period Type'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Text',
      'description' => E::ts('Period interval type'),
      'default' => NULL,
    ],
    'itemmanager_period_successor_id' => [
      'title' => E::ts('Successor'),
      'sql_type' => 'int unsigned',
      'input_type' => 'Number',
      'description' => E::ts('ID to itemmanager period entry which is the successor'),
      'add' => '4.2',
      'default' => 0,
    ],
    'hide' => [
      'title' => E::ts('Hide'),
      'sql_type' => 'boolean',
      'input_type' => 'CheckBox',
      'description' => E::ts('Don\'t show the period anymore'),
      'add' => '4.2.1',
      'default' => TRUE,
    ],
    'reverse' => [
        'title' => E::ts('Reverse'),
        'sql_type' => 'boolean',
        'input_type' => 'CheckBox',
        'description' => E::ts('Reverse the period'),
        'add' => '4.3',
        'default' => FALSE,
     ],
  ],
];
