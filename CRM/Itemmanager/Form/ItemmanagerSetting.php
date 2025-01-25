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

            foreach ($period['fields'] as $field)
            {
                $ignoreparam = array('value' => $field['ignore']);

                if($field['ignore'] == 1)
                {
                    $ignoreparam['checked'] = 1;
                }

                 $this->addElement(
                    'advcheckbox',
                    $field['element_period_field_ignore'],
                     E::ts('Ignore'),
                )->setChecked($field['ignore'] == 1);


                $extendparam = array('value' => $field['extend']);

                if($field['extend'] == 1)
                {
                    $extendparam['checked'] = 1;
                }

                $this->addElement(
                    'advcheckbox',
                    $field['element_period_field_extend'],
                    E::ts('Extend'),
                )->setChecked($field['extend'] == 1);


                $novitiate = array('value' => $field['novitiate']);
                if($field['novitiate'] == 1)
                {
                    $novitiate['checked'] = 1;
                }


                $this->addElement(
                    'advcheckbox',
                    $field['element_period_field_novitiate'],
                    E::ts('Novitiate'),
                )->setChecked($field['novitiate'] == 1);


                $bidding = array('value' => $field['bidding']);
                if($field['bidding'] == 1)
                {
                    $bidding['checked'] = 1;
                }

                $this->addElement(
                    'advcheckbox',
                    $field['element_period_field_bidding'],
                    E::ts('Bidding round'),
                )->setChecked($field['bidding'] == 1);

                $enable_period_exception = array('value' => $field['enable_period_exception']);
                if($field['enable_period_exception'] == 1)
                {
                    $enable_period_exception['checked'] = 1;
                }


                $this->addElement(
                    'advcheckbox',
                    $field['element_enable_period_exception'],
                    E::ts('Enable Period Exception Case'),
                )->setChecked($field['enable_period_exception'] == 1);

                $exception_periodsattributes = array(
                    'placeholder' => 1,
                    'size'=>"5",
                    'value' => $field['exception_periods'] ,
                );

                $this->add(
                    'text',
                    $field['element_exception_periods']  ,
                    E::ts('Exception Periods'),
                    $exception_periodsattributes,
                    False,
                );

                $this->add(
                    'select',
                    $field['element_period_field_successor'],
                    E::ts('Successor'),
                    $field['selection'], // list of options
                    False, // is required
                    ['flex-grow'=> 1,'style'=>'width:300px',],
                )->setSelected($field['successor']);

            }
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
            'name' => ts('Synchronize'),
            'js' => ['onclick' => "CRM.status(ts('Synchronizing...')); location.href='civicrm/admin/setting/itemmanager';"],
            'subName' => 'sync',
        ],
        [
            'type' => 'done',
            'name' => ts('Cancel'),
            'subName' => 'cancel',
        ],
    ));

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
     * Returns a drop down selection for the possible successor
     *
     * @param $priceset array current priceset array
     * @param $pricefield array current price field array
     * @param $pricefieldvalue array current price field value array
     */
  private function getItemSelection($priceset,$pricefield,$pricefieldvalue,$itemmanager_period)
  {
      $selection = $this->getEmptySelection();

      try {
          $priceset_records = civicrm_api3('PriceSet', 'get',
              array('sequential' => 1,
                  'financial_type_id' => CRM_Utils_Array::value('financial_type_id', $priceset),
              ));
          if ($priceset_records['is_error'] or $priceset_records['count'] <= 1)
              return $this->getEmptySelection();

          $successor_id = CRM_Utils_Array::value('itemmanager_period_successor_id',$itemmanager_period);

          $period_successor_result = \Civi\Api4\ItemmanagerPeriods::get()
              ->addWhere('id', '=', $successor_id)
              ->setCheckPermissions(FALSE)
              ->execute();

          $itemmperiod_successor = $period_successor_result->first();

          //Now generate the selections
          foreach ($priceset_records['values'] as $selectedpriceset)
          {
              //ignore own dataset or successor price set
              if($priceset['id'] == $selectedpriceset['id'] or
                  (isset($itemmperiod_successor) and
                      $selectedpriceset['id'] != $itemmperiod_successor['price_set_id'])
                        and $successor_id != 0 )
                  continue;

              $pricefield_records = civicrm_api3('PriceField', 'get',
                  array('sequential' => 1,
                      'price_set_id'=>$selectedpriceset['id']));

              if( $pricefield_records['is_error']) return $selection;

              foreach ($pricefield_records['values'] as $selectedpricefield)
              {
                  $pricefield_values_records = civicrm_api3('PriceFieldValue', 'get',
                      array('sequential' => 1,
                          'price_field_id' => $selectedpricefield['id'],
                          'financial_type_id' => CRM_Utils_Array::value('financial_type_id', $pricefieldvalue),
                          'membership_type_id' => CRM_Utils_Array::value('membership_type_id', $pricefieldvalue),
                          ));

                  if( $pricefield_values_records['is_error']) return $selection;

                  foreach ($pricefield_values_records['values'] as $selectedpricefieldvalue)
                  {
                      $settings = new CRM_Itemmanager_BAO_ItemmanagerSettings();
                      $settings->get('price_field_value_id',CRM_Utils_Array::value('id', $selectedpricefieldvalue));

                      $selection[(int)$settings->id] = '('.$selectedpriceset['title'].') '.$selectedpricefieldvalue['label'];
                  }


              }


          }



      } catch (CiviCRM_API3_Exception $e) {
          $this->_errormessages[] = $e->getMessage();
          return $this->getEmptySelection();
      }


      return $selection;
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

                $priceset_records = civicrm_api3('PriceSet', 'get',
                    array('sequential' => 1,
                        'financial_type_id' => CRM_Utils_Array::value('financial_type_id', $priceset),
                    ));

                if ($priceset_records['is_error'] or $priceset_records['count'] <= 1)
                    return $this->getEmptySelection();


                //Now generate the selections
                foreach ($priceset_records['values'] as $selectedpriceset)
                {
                    //ignore own dataset
                    if($priceset['id'] == $selectedpriceset['id'])
                        continue;

                    $period_result = \Civi\Api4\ItemmanagerPeriods::get()
                        ->addWhere('price_set_id', '=', $selectedpriceset['id'])
                        ->setCheckPermissions(FALSE)
                        ->execute();

                    $itemmperiod_selected = $period_result->first();

                    if (!isset($itemmperiod_selected)) {
                        $this->_errormessages[] = 'Could not get the period from price set ' . (int)$selectedpriceset['id'];
                        continue;
                    }


                    $selection[(int)$itemmperiod_selected['id']] = $selectedpriceset['title'];



                }


        } catch (CiviCRM_API3_Exception $e) {
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
     *
     * @throws API_Exception
     * @throws \Civi\API\Exception\UnauthorizedException
     */
  public function preProcess()
  {
     if(isset($this->_submitValues['_qf_ItemmanagerSetting_done_sync']))
     {
         parent::preProcess();
         return;
     }



      try {

          $hiddenshowoption = CRM_Itemmanager_Util::getSetting('itemmanager_show_hiden_periods');


          //get all periods first
          $itemmanager_periods = \Civi\Api4\ItemmanagerPeriods::get()
              ->setCheckPermissions(FALSE)
              ->execute();//now we build the nested array for the form


          foreach ($itemmanager_periods as $itemmanager_period) {




              $itemmanager_period_id = CRM_Utils_Array::value('id', $itemmanager_period);

              $hide = $itemmanager_period['hide'];

              if($hide and !$hiddenshowoption)
              {

                  continue;
              }

              $reverse = $itemmanager_period['reverse'];

              $itemmanager_price_fields = \Civi\Api4\ItemmanagerSettings::get()
                  ->addWhere('itemmanager_periods_id', '=', $itemmanager_period_id)
                  ->setCheckPermissions(FALSE)
                  ->execute();

              $priceset = civicrm_api3('PriceSet', 'getsingle', array('id' => (int)$itemmanager_period['price_set_id']));
              if (!isset($priceset)) {
                  $this->_errormessages[] = 'Could not get the price set ' . (int)$itemmanager_period['price_set_id'];
                  continue;
              }


              //populate the priceset successor selection
              $successor_selection = $this->getPeriodSelection($itemmanager_period,$priceset);

              $period_start_on_raw = date_create(CRM_Utils_Array::value('period_start_on',$itemmanager_period));

              $period_start_on = CRM_Utils_Date::customFormat($period_start_on_raw->format('Y-m-d'),
                  Civi::settings()->get('dateformatshortdate'));

              $field_list = array();
              foreach ($itemmanager_price_fields As $itemmanager_price_field)
              {
                  $itemmanager_id = CRM_Utils_Array::value('id', $itemmanager_price_field);

                  //check still if exists
                  $fieldcount = civicrm_api3('PriceFieldValue', 'getcount',
                      array('id' => (int)$itemmanager_price_field['price_field_value_id']));

                  if($fieldcount == 0)
                  {
                      continue;
                  }

                  $pricefieldvalue = civicrm_api3('PriceFieldValue', 'getsingle',
                      array('id' => (int)$itemmanager_price_field['price_field_value_id']));
                  if (!isset($pricefieldvalue)) {
                      $this->_errormessages[] = 'Could not get the price field value ' .
                                                        (int)$itemmanager_price_field['price_field_value_id'];
                      continue;
                  }

                  $pricefield = civicrm_api3('PriceField', 'getsingle',
                      array('id' => (int)CRM_Utils_Array::value('price_field_id', $pricefieldvalue)));
                  if (!isset($pricefield)) {
                      $this->_errormessages[] = 'Could not get the price field ' .
                          (int)CRM_Utils_Array::value('price_field_id', $pricefieldvalue);
                      continue;
                  }

                  //just copy field data for info
                  if(isset($pricefield['active_on']))
                      $active_on = CRM_Utils_Date::customFormat(date_create(
                          CRM_Utils_Array::value('active_on',$pricefield))->format('Y-m-d'),
                          Civi::settings()->get('dateformatshortdate'));
                  else
                      $active_on = "-";
                  if(isset($pricefield['expire_on']))
                      $expire_on = CRM_Utils_Date::customFormat(date_create(
                          CRM_Utils_Array::value('expire_on',$pricefield))->format('Y-m-d'),
                          Civi::settings()->get('dateformatshortdate'));
                  else
                      $expire_on = "-";

                  $field_collection = array(
                      'manager_id' => (int)$itemmanager_id,
                      'field_label' => CRM_Utils_Array::value('label',$pricefieldvalue),
                      'active_on' => $active_on,
                      'expire_on' => $expire_on,
                      'isactive' => CRM_Utils_Array::value('is_active',$pricefield) == 1? ts('Active'):'',
                      'ignore' => (int)$itemmanager_price_field['ignore'],
                      'extend' => (int)$itemmanager_price_field['extend'],
                      'novitiate' => (int)$itemmanager_price_field['novitiate'],
                      'bidding' => (int)$itemmanager_price_field['bidding'],
                      'enable_period_exception' => (int)$itemmanager_price_field['enable_period_exception'],
                      'exception_periods' => (int)$itemmanager_price_field['exception_periods'],
                      'successor' => (int)$itemmanager_price_field['itemmanager_successor_id'],
                      'selection' => $this->getItemSelection($priceset,$pricefield,$pricefieldvalue, $itemmanager_period),
                      'element_period_field_successor' => 'period_'.$itemmanager_id.
                          '_field_'.$itemmanager_id.'_successor',
                     'element_period_field_ignore' => 'period_'.$itemmanager_id.
                         '_field_'.$itemmanager_id.'_ignore',
                     'element_period_field_extend' => 'period_'.$itemmanager_id.
                          '_field_'.$itemmanager_id.'_extend',
                     'element_period_field_novitiate' => 'period_'.$itemmanager_id.
                         '_field_'.$itemmanager_id.'_novitiate',
                      'element_period_field_bidding' => 'period_'.$itemmanager_id.
                          '_field_'.$itemmanager_id.'_bidding',
                      'element_enable_period_exception' => 'period_'.$itemmanager_id.
                          '_field_'.$itemmanager_id.'_enable_period_exception',
                      'element_exception_periods' => 'period_'.$itemmanager_id.
                          '_field_'.$itemmanager_id.'_exception_periods',


                 );

                  $field_list[$itemmanager_id] = $field_collection;
              }



              $form_collection = array(
                  'price_label' => $priceset['title'],
                  'periods_id' => (int)$itemmanager_period_id,
                  'period_start_on' => $period_start_on,
                  'period_start_on_raw' => $period_start_on_raw,
                  'periods' => CRM_Utils_Array::value('periods',$itemmanager_period),
                  'period_type' => CRM_Utils_Array::value('period_type',$itemmanager_period),
                  'successor' => CRM_Utils_Array::value('itemmanager_period_successor_id',$itemmanager_period),
                  'hide' => (int)$hide,
                  'reverse' => (int)$reverse,
                  'selection' => $successor_selection,
                  'fields' => $field_list,
                  'element_period_start_on' => 'period_'.$itemmanager_id.'_period_start_on',
                  'element_period_periods' => 'period_'.$itemmanager_id.'_periods',
                  'element_period_interval' => 'period_'.$itemmanager_id.'_interval',
                  'element_period_type' => 'period_'.$itemmanager_id.'_type',
                  'element_period_successor' => 'period_'.$itemmanager_id.'_successor',
                  'element_period_hide' => 'period_'.$itemmanager_id.'_hide',
                  'element_period_reverse' => 'period_'.$itemmanager_id.'_reverse',
              );

              $this->_itemSettings[$itemmanager_period_id] = $form_collection;


          }
      } catch (\Civi\API\Exception\UnauthorizedException $e) {
          $this->_errormessages[] = $e->$this->_errormessages;
      } catch (API_Exception $e) {
          $this->_errormessages[] = $e->$this->_errormessages;
      } catch (CiviCRM_API3_Exception $e) {
          $this->_errormessages[] = $e->$this->_errormessages;
      }
     catch (Exception $e)
     {
         $this->_errormessages[] = $e->$this->_errormessages;
     }

      $this->assign('errormessages',$this->_errormessages);
      $this->assign('itemsettings',$this->_itemSettings);

      parent::preProcess();

  }

    /**
     *  Install an exact representation of the price set and price fields
     *
     * @throws API_Exception
     * @throws \Civi\API\Exception\UnauthorizedException
     */
    private function syncItemmanager()
    {


        try {

            $pricefield_values_records = civicrm_api3('PriceFieldValue', 'get',
                array(
                    'sequential' => 1,
                    'return' => 'id',
                    'options' => [
                        'limit' => 'NULL']));

            if( $pricefield_values_records['is_error']) return;

            $priceset_records = civicrm_api3('PriceSet', 'get',
                array('sequential' => 1,'return' => 'id',
                    'options' => [
                        'limit' => 'NULL']));
            if( $priceset_records['is_error']) return;

            $itemmanager_periods = \Civi\Api4\ItemmanagerPeriods::get()
                ->addSelect('price_set_id')
                ->setCheckPermissions(FALSE)
                ->execute()
                ->indexBy('price_set_id');

            $pricefield_value_ids = array_column($pricefield_values_records['values'],'id');
            $priceset_ids = array_column($priceset_records['values'],'id');
            $itemmanager_price_set_ids = array_column($itemmanager_periods->getArrayCopy(),'price_set_id');


            //now we wanna sync the pricesets with our extension
            foreach ($itemmanager_price_set_ids as $itemmanager_price_set_id) {

                if(!in_array((string)$itemmanager_price_set_id,$priceset_ids))
                {
                    \Civi\Api4\ItemmanagerPeriods::delete()
                        ->addWhere('id','=',$itemmanager_price_set_id)
                        ->setCheckPermissions(FALSE)
                        ->execute();
                }

            }

            $transaction = new CRM_Core_Transaction();
            try {
                foreach ($priceset_ids as $set_id) {
                    if (!in_array((int)$set_id, $itemmanager_price_set_ids)) {

                        $newperiod = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
                        $newperiod->price_set_id = (int)$set_id;
                        $newperiod->period_start_on = date_create('2000-01-01')->format('Ymd');
                        $newperiod->periods = 1;
                        $newperiod->period_type = 1;
                        $newperiod->save();

                    }

                }
            } catch (Exception $e) {
                $transaction->rollback();
                $this->_errormessages[] = "An error occurred renewing a payment plan: " . $e->getMessage();
            }
            $transaction->commit();

            $itemmanager_price_fields = \Civi\Api4\ItemmanagerSettings::get()
                ->addSelect('price_field_value_id')
                ->setCheckPermissions(FALSE)
                ->execute()
                ->indexBy('price_field_value_id');

            $itemmanager_price_fields_ids = array_column($itemmanager_price_fields->getArrayCopy(),'price_field_value_id');

            foreach ($itemmanager_price_fields_ids as $field_id)
            {
                if(!in_array((string)$field_id,$pricefield_value_ids))
                {
                    \Civi\Api4\ItemmanagerSettings::delete()
                        ->addWhere('id',"=","$field_id")
                        ->setCheckPermissions(FALSE)
                        ->execute();
                }

            }


            foreach ($pricefield_value_ids as $field_id)
            {
                if(!in_array((int)$field_id,$itemmanager_price_fields_ids)) {

                    $field_infos = CRM_Itemmanager_Util::getPriceSetRefByFieldValueId($field_id);

                    if ($field_infos['iserror'] == 1) {
                        $this->_errormessages[] = 'Could not get the full record for price field value '.$field_id;
                        continue;
                    }


                    if (!isset($field_infos['price_id']))
                    {
                        $this->_errormessages[] = 'Could not get the full record for price field value '.$field_id;
                        continue;
                    }

                    $price_set = $field_infos['price_id'];

                    $period = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
                    $valid = $period->get('price_set_id',(int)$price_set);
                    if(!$valid or $period->id == 0)
                    {
                        $this->_errormessages[] = 'No Itemmanager periods found with id '.(int)$price_set;
                        continue;
                    }

                    $itemsetting = new CRM_Itemmanager_BAO_ItemmanagerSettings();
                    $itemsetting->price_field_value_id = (int)$field_id;
                    $itemsetting->itemmanager_periods_id = (int)$period->id;
                    $itemsetting->save();
                }

            }


        } catch (CiviCRM_API3_Exception $e) {

            $this->_errormessages[] = $e->$this->_errormessages;
        }

    }


    /**
     * Update the settings records from form
     */
    public function postProcess() {
        $formvalues = $this->controller->exportValues($this->_name);

        if(isset($this->_submitValues['_qf_ItemmanagerSetting_done_sync']))
        {
            $this->syncItemmanager();
            CRM_Core_Session::setStatus(ts("Synchronized."), ts('Start', array('domain' => 'org.stadtlandbeides.itemmanager')),
                'success');
            parent::postProcess();
            $admin_url = CRM_Utils_System::url('civicrm/admin', "reset=1");
            CRM_Utils_System::redirect($admin_url);

            return;
        }


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

                foreach ($period['fields'] as $field) {

                    $successor = isset($formvalues[$field['element_period_field_successor']])? (int)$formvalues[$field['element_period_field_successor']]: (int)$field['successor'];

                    $ignore = isset($formvalues[$field['element_period_field_ignore']]) ?
                        (int)$formvalues[$field['element_period_field_ignore']] : (int)$field['ignore'];

                    $extend = isset($formvalues[$field['element_period_field_extend']]) ?
                        (int)$formvalues[$field['element_period_field_extend']] : (int)$field['extend'];

                    $novitiate = isset($formvalues[$field['element_period_field_novitiate']]) ?
                        (int)$formvalues[$field['element_period_field_novitiate']] : (int)$field['novitiate'];
                    $bidding = isset($formvalues[$field['element_period_field_bidding']]) ?
                        (int)$formvalues[$field['element_period_field_bidding']] : (int)$field['bidding'];
                    $enable_period_exception = isset($formvalues[$field['element_enable_period_exception']]) ?
                        (int)$formvalues[$field['element_enable_period_exception']] : (int)$field['enable_period_exception'];
                    $exception_periods = isset($formvalues[$field['element_exception_periods']]) ?
                        (int)$formvalues[$field['element_exception_periods']] : (int)$field['exception_periods'];

                    $update_manager = new CRM_Itemmanager_BAO_ItemmanagerSettings();
                    $update_manager->id = (int)$field['manager_id'];
                    $update_manager->itemmanager_successor_id = $successor;
                    $update_manager->ignore = $ignore == 1;
                    $update_manager->extend = $extend == 1;
                    $update_manager->novitiate = $novitiate == 1;
                    $update_manager->bidding = $bidding == 1;
                    $update_manager->enable_period_exception = $enable_period_exception == 1;
                    $update_manager->exception_periods = $exception_periods;
                    $update_manager->update();


                }

            }
        } catch (Exception $e) {

            $this->_errormessages[] = $e->getMessage();
            $this->assign('errormessages',$this->_errormessages);
        }


        CRM_Core_Session::setStatus(ts("Saved"), ts('Success', array('domain' => 'org.stadtlandbeides.itemmanager')),
            'success');

        parent::postProcess();
        sleep(10);

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
