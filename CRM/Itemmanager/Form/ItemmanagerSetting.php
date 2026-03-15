<?php

require_once 'CRM/Admin/Form/Setting.php';
use CRM_Itemmanager_ExtensionUtil as E;

/**
 * Setting form to define successor relations for each price field item
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Itemmanager_Form_ItemmanagerSetting extends CRM_Core_Form {

    private $_itemSettings;
    private $_errormessages;
    private $_duration_options;

    /**
     * Constructor
     *
     * CRM_Itemmanager_Form_ItemmanagerSetting constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->_itemSettings = array();
        $this->_errormessages = array();
        $this->_duration_options = CRM_Core_OptionGroup::values('recur_frequency_units');

    }

    private function getIndexFromDurationKey($duration_key)
    {
        $keys = array_keys($this->_duration_options);
        return array_search($duration_key,$keys);
    }

    /**
     * fill the form
     *
     */
    public function buildQuickForm() {


        $this->setTitle(E::ts('Itemmanager Settings'));

        foreach ($this->_itemSettings as $period)
        {
            $periodsattributes = array(
                'placeholder' => 1,
                'size'=>"5",
                'value' => $period['periods'] ,
            );

            $this->add(
              'text',
                $period['element_period_periods']  ,
                E::ts('Periods'),
                $periodsattributes,
                False,
            );


//            $dt_min = new DateTime();
//            $dt_min->sub(new DateInterval('P5Y'));
//            $dt_min->setDate((int)$dt_min->format('Y'),1,1);

            $dt_max = new DateTime();
            $dt_max->add(new DateInterval('P5Y'));
            $dt_max->setDate((int)$dt_max->format('Y'),12,31);

            $params = [
                'date' => 'yy-mm-dd',
                'minDate' => '2000-01-01',
                //CRM-18487 - max date should be the last date of the year.
                'maxDate' => $dt_max->format('Y-m-d'),
                'time' =>  FALSE,
            ];

            $this->add(
                'datepicker',
                $period['element_period_start_on'],
                ts('Start On'),
                ['placeholder' => 'select','value' => $period['period_start_on_raw']->format('Y-m-d')],
                False,
                $params);

            $this->add(
                'select',
                $period['element_period_type'],
                E::ts('Duration Unit'),
                $this->_duration_options, // list of options
                False, // is required
                [],
            )->setSelected(array_keys($this->_duration_options)[$period['period_type']]);

            $hideparam = array('value' => $period['hide']);

            if($period['hide'] == 1)
            {
                $hideparam['checked'] = 1;
            }

            $this->addElement(
                'advcheckbox',
                $period['element_period_hide'],
                E::ts('Hide'),
            )->setChecked($period['hide'] == 1);

            $reverseparam = array('value' => $period['reverse']);

            if($period['reverse'] == 1)
            {
                $reverseparam['checked'] = 1;
            }

            $this->addElement(
                'advcheckbox',
                $period['element_period_reverse'],
                E::ts('Reverse'),
            )->setChecked($period['reverse'] == 1);

            $this->add(
                'select',
                $period['element_period_successor'],
                E::ts('Successor'),
                $period['selection'], // list of options
                False, // is required
                ['flex-grow'=> 1,'style'=>'width:300px',],
            )->setSelected($period['successor']);

        }


    $this->addButtons(array(
        array(
            'type' => 'submit',
            'name' => E::ts('Submit'),
            'js' => ['onclick' => "CRM.status(ts('Saving...'));"],
            'isDefault' => TRUE,
        ),
        [
            'type' => 'done',
            'name' => ts('Cancel'),
            'subName' => 'cancel',
        ],
    ));

    $this->assign('sync_url', CRM_Utils_System::url('civicrm/admin/setting/itemmanagersync', '', FALSE, NULL, FALSE));
    $this->assign('settings_url', CRM_Utils_System::url('civicrm/admin/setting/itemmanager', 'reset=1'));

    CRM_Core_Resources::singleton()
         ->addStyleFile('org.stadtlandbeides.itemmanager', 'css/setting.css', 200, 'html-header');

    $this->assign('elementNames', $this->getRenderableElementNames());

    $breadCrumb = array(
        'title' => E::ts('Itemmanager Settings'),
        'url' => CRM_Utils_System::url('civicrm/admin/setting/itemmanager', 'reset=1'),
    );
    CRM_Utils_System::appendBreadCrumb(array($breadCrumb));



    parent::buildQuickForm();
  }


    /**
     * Returns a drop down selection for the possible successor of a period
     *
     * @param $itemmanager_period array curren period
     * @param $priceset array current priceset array
     */
    private function getPeriodSelection($itemmanager_period, $priceset)
    {
        $selection = $this->getEmptySelection();

        try {

                $priceset_records = \Civi\Api4\PriceSet::get(FALSE)
                    ->addWhere('financial_type_id', '=', $priceset['financial_type_id'])
                    ->execute();

                if ($priceset_records->count() <= 1)
                    return $this->getEmptySelection();


                //Now generate the selections
                foreach ($priceset_records as $selectedpriceset)
                {
                    //ignore own dataset
                    if($priceset['id'] == $selectedpriceset['id'])
                        continue;

                    $itemmperiod_selected = \Civi\Api4\ItemmanagerPeriods::get(FALSE)
                        ->addWhere('price_set_id', '=', $selectedpriceset['id'])
                        ->execute()->first();

                    if (!isset($itemmperiod_selected)) {
                        $this->_errormessages[] = E::ts('Could not get the period from price set %1', [1 => (int)$selectedpriceset['id']]);
                        continue;
                    }


                    $selection[(int)$itemmperiod_selected['id']] = $selectedpriceset['title'];



                }


        } catch (\CRM_Core_Exception $e) {
            $this->_errormessages[] = $e->getMessage();
            return $this->getEmptySelection();
        }


        return $selection;
    }

    /**
     *  Just make a single selection entry
     *
     * @return array
     */
  private function getEmptySelection()
    {
        return array(0=>ts('No Successor'));

    }

    /**
     *  Collect all items to be shown for the form
     */
  public function preProcess()
  {


      try {

          $hiddenshowoption = CRM_Itemmanager_Util::getSetting('itemmanager_show_hiden_periods');

          $itemmanager_periods = \Civi\Api4\ItemmanagerPeriods::get(FALSE)
              ->execute();

          foreach ($itemmanager_periods as $itemmanager_period) {

              $itemmanager_period_id = $itemmanager_period['id'];

              $hide = $itemmanager_period['hide'];

              if($hide and !$hiddenshowoption)
              {
                  continue;
              }

              $reverse = $itemmanager_period['reverse'];

              $priceset = \Civi\Api4\PriceSet::get(FALSE)
                  ->addWhere('id', '=', (int)$itemmanager_period['price_set_id'])
                  ->execute()->first();
              if (!$priceset) {
                  $this->_errormessages[] = E::ts('Could not get the price set %1', [1 => (int)$itemmanager_period['price_set_id']]);
                  continue;
              }

              $successor_selection = $this->getPeriodSelection($itemmanager_period,$priceset);

              $period_start_on_raw = date_create($itemmanager_period['period_start_on']);

              $form_collection = array(
                  'price_label' => $priceset['title'],
                  'periods_id' => (int)$itemmanager_period_id,
                  'period_start_on_raw' => $period_start_on_raw,
                  'periods' => $itemmanager_period['periods'],
                  'period_type' => $itemmanager_period['period_type'],
                  'successor' => $itemmanager_period['itemmanager_period_successor_id'],
                  'hide' => (int)$hide,
                  'reverse' => (int)$reverse,
                  'selection' => $successor_selection,
                  'fields' => [],
                  'element_period_start_on' => 'period_'.$itemmanager_period_id.'_period_start_on',
                  'element_period_periods' => 'period_'.$itemmanager_period_id.'_periods',
                  'element_period_interval' => 'period_'.$itemmanager_period_id.'_interval',
                  'element_period_type' => 'period_'.$itemmanager_period_id.'_type',
                  'element_period_successor' => 'period_'.$itemmanager_period_id.'_successor',
                  'element_period_hide' => 'period_'.$itemmanager_period_id.'_hide',
                  'element_period_reverse' => 'period_'.$itemmanager_period_id.'_reverse',
                  'stub_url' => CRM_Utils_System::url('civicrm/admin/setting/itemmanagerstub',
                      "action=browse&period_id={$itemmanager_period_id}"),
              );

              $this->_itemSettings[$itemmanager_period_id] = $form_collection;
          }
      } catch (Exception $e) {
          $this->_errormessages[] = $e->getMessage();
      }

      $priceset_count = \Civi\Api4\PriceSet::get(FALSE)->selectRowCount()->execute()->countMatched();
      $period_count = \Civi\Api4\ItemmanagerPeriods::get(FALSE)->selectRowCount()->execute()->countMatched();
      $unsynced_count = $priceset_count - $period_count;
      $this->assign('unsynced_count', $unsynced_count > 0 ? $unsynced_count : 0);

      $this->assign('errormessages',$this->_errormessages);
      $this->assign('itemsettings',$this->_itemSettings);

      parent::preProcess();

  }

    /**
     * Update the settings records from form
     */
    public function postProcess() {
        $formvalues = $this->controller->exportValues($this->_name);

        try {
            foreach ($this->_itemSettings as $period) {

                $periods = isset($formvalues[$period['element_period_periods']]) ?
                    (int)$formvalues[$period['element_period_periods']] : (int)$period['periods'];

                $type = isset($formvalues[$period['element_period_type']]) ?
                    $this->getIndexFromDurationKey($formvalues[$period['element_period_type']])
                        : (int)$period['period_type'];

                $hide = isset($formvalues[$period['element_period_hide']]) ?
                    (int)$formvalues[$period['element_period_hide']] : (int)$period['hide'];

                $reverse = isset($formvalues[$period['element_period_reverse']]) ?
                    (int)$formvalues[$period['element_period_reverse']] : (int)$period['reverse'];

                if (isset($formvalues[$period['element_period_start_on']])) {
                    $start_on = date_create($formvalues[$period['element_period_start_on']]);
                } else {
                    $start_on = $period['period_start_on_raw'];
                }

                $period_successor = isset($formvalues[$period['element_period_successor']])? (int)$formvalues[$period['element_period_successor']]: (int)$period['successor'];

                $update_period = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
                $update_period->id = (int)$period['periods_id'];
                $update_period->periods = $periods;
                $update_period->period_start_on = $start_on->format('Ymd');
                $update_period->period_type = $type;
                $update_period->hide = $hide == 1;
                $update_period->reverse = $reverse == 1;
                $update_period->itemmanager_period_successor_id = $period_successor;
                $update_period->update();

            }

            $stub_field_ids = $_POST['stub_field_ids'] ?? [];
            foreach ($stub_field_ids as $field_id) {
                $field_id = (int) $field_id;
                if ($field_id <= 0) {
                    continue;
                }

                $prefix = 'period_' . $field_id . '_field_' . $field_id . '_';

                $successor = (int) ($_POST[$prefix . 'successor'] ?? 0);
                $ignore = isset($_POST[$prefix . 'ignore']) ? 1 : 0;
                $extend = isset($_POST[$prefix . 'extend']) ? 1 : 0;
                $novitiate = isset($_POST[$prefix . 'novitiate']) ? 1 : 0;
                $bidding = isset($_POST[$prefix . 'bidding']) ? 1 : 0;
                $enable_period_exception = isset($_POST[$prefix . 'enable_period_exception']) ? 1 : 0;
                $exception_periods = (int) ($_POST[$prefix . 'exception_periods'] ?? 0);

                $update_manager = new CRM_Itemmanager_BAO_ItemmanagerSettings();
                $update_manager->id = $field_id;
                $update_manager->itemmanager_successor_id = $successor;
                $update_manager->ignore = $ignore == 1;
                $update_manager->extend = $extend == 1;
                $update_manager->novitiate = $novitiate == 1;
                $update_manager->bidding = $bidding == 1;
                $update_manager->enable_period_exception = $enable_period_exception == 1;
                $update_manager->exception_periods = $exception_periods;
                $update_manager->update();
            }
        } catch (Exception $e) {

            $this->_errormessages[] = $e->getMessage();
            $this->assign('errormessages',$this->_errormessages);
        }


        CRM_Core_Session::setStatus(ts("Saved"), ts('Success', array('domain' => 'org.stadtlandbeides.itemmanager')),
            'success');

        parent::postProcess();

    }


  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

}
