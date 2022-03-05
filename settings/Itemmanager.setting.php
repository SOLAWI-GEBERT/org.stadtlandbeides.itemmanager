<?php
/*
 * ------------------------------------------------------------+
 * | stadt, land, beides - CSA Support                                       |
 * | Copyright (C) 2021 Stadt, Land, Beides                               |
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

return array(
    'itemmanager_keep_receive_date' => array(
        'group_name' => 'Itemmanager Options',
        'group' => 'org.stadtlandbeides.itemmanager',
        'name' => 'itemmanager_keep_receive_date',
        'type' => 'Boolean',
        'add' => '5.0',
        'default' => '0',
        'quick_form_type' => 'YesNo',
        'html_type' => 'checkbox',
        'is_domain' => 1,
        'is_contact' => 0,
        'description' => E::ts('Keep receive date after payment relation'),
        'title' => E::ts('Keep receive date of the contribution after processing the payment'),
        'help_text' => E::ts('Select Yes to keep the origin receive date untouched after a payment relation'),
    ),
    'itemmanager_filter_open_payments' => array(
        'group_name' => 'Itemmanager Options',
        'group' => 'org.stadtlandbeides.itemmanager',
        'name' => 'itemmanager_filter_open_payments',
        'type' => 'Boolean',
        'add' => '5.0',
        'default' => '1',
        'quick_form_type' => 'YesNo',
        'html_type' => 'checkbox',
        'is_domain' => 1,
        'is_contact' => 0,
        'description' => E::ts('Filter the SEPA relation for open payments only'),
        'title' => E::ts('Filter Open payments only'),
        'help_text' => E::ts('Select Yes to hide already paid contributions for payments'),
    ),
    'itemmanager_filter_past_payments' => array(
        'group_name' => 'Itemmanager Options',
        'group' => 'org.stadtlandbeides.itemmanager',
        'name' => 'itemmanager_filter_past_payments',
        'type' => 'Boolean',
        'add' => '5.0',
        'default' => '1',
        'quick_form_type' => 'YesNo',
        'html_type' => 'checkbox',
        'is_domain' => 1,
        'is_contact' => 0,
        'description' => E::ts('Filter the SEPA relation for past payments only'),
        'title' => E::ts('Filter Past payments only'),
        'help_text' => E::ts('Select Yes to hide future contributions for payments'),
    ),
);



