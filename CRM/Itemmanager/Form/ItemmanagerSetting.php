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

            foreach ($period['fields'] as $field)
            {
                $ignoreparam = array('value' => $field['ignore']);

                if($field['ignore'] == 1)
                {
                    $ignoreparam['checked'] = 1;
                }

                 $this->add(
                    'checkbox',
                    $field['element_period_field_ignore'],
                    ts('Ignore'),
                    Null,
                    False,
                     $ignoreparam
                );

                $novitiate = array('value' => $field['novitiate']);
                if($field['novitiate'] == 1)
                {
                    $novitiate['checked'] = 1;
                }


                $this->add(
                    'checkbox',
                    $field['element_period_field_novitiate'],
                    ts('Novitiate'),
                    Null,
                    False,
                    $novitiate
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

    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }


    /**
     * Returns a drop down selection for the possible successor
     *
     * @param $priceset array current priceset array
     * @param $pricefield array current price field array
     * @param $pricefieldvalue array current price field value array
     */
  private function getItemSelection($priceset,$pricefield,$pricefieldvalue)
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

          //get all periods first
          $itemmanager_periods = \Civi\Api4\ItemmanagerPeriods::get()
              ->setCheckPermissions(FALSE)
              ->execute();//now we build the nested array for the form
          foreach ($itemmanager_periods as $itemmanager_period) {

              $itemmanager_period_id = CRM_Utils_Array::value('id', $itemmanager_period);

              $itemmanager_price_fields = \Civi\Api4\ItemmanagerSettings::get()
                  ->addWhere('itemmanager_periods_id', '=', $itemmanager_period_id)
                  ->setCheckPermissions(FALSE)
                  ->execute();

              $priceset = civicrm_api3('PriceSet', 'getsingle', array('id' => (int)$itemmanager_period['price_set_id']));
              if (!isset($priceset)) {
                  $this->_errormessages[] = 'Could not get the price set ' . (int)$itemmanager_period['price_set_id'];
                  continue;
              }

              $period_start_on_raw = date_create(CRM_Utils_Array::value('period_start_on',$itemmanager_period));

              $period_start_on = CRM_Utils_Date::customFormat($period_start_on_raw->format('Y-m-d'),
                  Civi::settings()->get('dateformatshortdate'));

              $field_list = array();
              foreach ($itemmanager_price_fields As $itemmanager_price_field)
              {
                  $itemmanager_id = CRM_Utils_Array::value('id', $itemmanager_price_field);

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
                      'novitiate' => (int)$itemmanager_price_field['novitiate'],
                      'successor' => (int)$itemmanager_price_field['itemmanager_successor_id'],
                      'selection' => $this->getItemSelection($priceset,$pricefield,$pricefieldvalue),
                      'element_period_field_successor' => 'period_'.$itemmanager_id.
                          '_field_'.$itemmanager_id.'_successor',
                     'element_period_field_ignore' => 'period_'.$itemmanager_id.
                         '_field_'.$itemmanager_id.'_ignore',
                     'element_period_field_novitiate' => 'period_'.$itemmanager_id.
                         '_field_'.$itemmanager_id.'_novitiate',


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
                  'fields' => $field_list,
                  'element_period_start_on' => 'period_'.$itemmanager_id.'_period_start_on',
                  'element_period_periods' => 'period_'.$itemmanager_id.'_periods',
                  'element_period_interval' => 'period_'.$itemmanager_id.'_interval',
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

                if (isset($formvalues[$period['element_period_start_on']])) {
                    $start_on = date_create($formvalues[$period['element_period_start_on']]);
                } else {
                    $start_on = $period['period_start_on_raw'];
                }


                $update_period = new CRM_Itemmanager_BAO_ItemmanagerPeriods();
                $update_period->id = (int)$period['periods_id'];
                $update_period->periods = $periods;
                $update_period->period_start_on = $start_on->format('Ymd');
                $update_period->update();

                foreach ($period['fields'] as $field) {






                    $successor = isset($formvalues[$field['element_period_field_successor']])? (int)$formvalues[$field['element_period_field_successor']]: (int)$field['successor'];

                    $ignore = isset($formvalues[$field['element_period_field_ignore']]) ?
                        (int)$formvalues[$field['element_period_field_ignore']] : (int)$field['ignore'];

                    $novitiate = isset($formvalues[$field['element_period_field_novitiate']]) ?
                        (int)$formvalues[$field['element_period_field_novitiate']] : (int)$field['novitiate'];

                    $update_manager = new CRM_Itemmanager_BAO_ItemmanagerSettings();
                    $update_manager->id = (int)$field['manager_id'];
                    $update_manager->itemmanager_successor_id = $successor;
                    $update_manager->ignore = $ignore == 1;
                    $update_manager->novitiate = $novitiate == 1;
                    $update_manager->update();


                }

            }
        } catch (Exception $e) {

            $this->_errormessages[] = $e->getMessage();
            $this->assign('errormessages',$this->_errormessages);
        }
        parent::postProcess();

        CRM_Core_Session::setStatus(ts("Saved"), ts('Success', array('domain' => 'org.stadtlandbeides.itemmanager')),
            'success');

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
