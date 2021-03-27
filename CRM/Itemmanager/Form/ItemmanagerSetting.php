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

        CRM_Utils_System::setTitle(E::ts('Itemmanager Settings'));

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
                True,
            );


            $dateparams = [
                'date' => $period['period_start_on'],
                'format' => 'd M',
            ];

            $this->add(
                'date',
                $period['element_period_start_on'],
                ts('Start On'),
                [],
                True,
                $dateparams);


            foreach ($period['fields'] as $field)
            {
                $this->add(
                    'checkbox',
                    $field['element_period_field_ignore'],
                    ts('Ignore'),
                    Null,
                    False,
                    ['value' => $field['ignore']]
                );

                $this->add(
                    'checkbox',
                    $field['element_period_field_novitiate'],
                    ts('Novitiate'),
                    Null,
                    False,
                    ['value' => $field['novitiate']]
                );

                $this->add(
                    'select',
                    $field['element_period_field_successor'],
                    E::ts('Successor'),
                    $field['selection'], // list of options
                    FALSE, // is required
                    ['flex-grow'=> 1,'value'=>$field['successor']],
                );

            }
        }


    $this->addButtons(array(
        array(
            'type' => 'submit',
            'name' => E::ts('Submit'),
            'isDefault' => TRUE,
        ),
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
                      $selection[] = '('.$selectedpriceset['title'].') '.$selectedpricefieldvalue['label'];
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

      //before we start, we sync with the price definitions
      $this->syncItemmanager();


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


              $period_start_on = CRM_Utils_Date::customFormat(date_create(CRM_Utils_Array::value('period_start_on',
                  $itemmanager_period))->format('Y-m-d'),Civi::settings()->get('dateformatshortdate'));

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
                  'period_start_on' => $period_start_on,
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
        //Declarations
        $todeleteSettings = array();
        $toinsertSettings = array();
        $todeletePeriods = array();
        $toinsertPeriods = array();

        try {
            $pricefield_values_records = civicrm_api3('PriceFieldValue', 'get',
                array('sequential' => 1,'return' => 'id'));

            if( $pricefield_values_records['is_error']) return;

            $pricefield_records = civicrm_api3('PriceField', 'get',array('sequential' => 1,'return' => 'id'));
            if( $pricefield_records['is_error']) return;

            $priceset_records = civicrm_api3('PriceSet', 'get',
                array('sequential' => 1,'financial_type_id'=>array('IS NOT NULL'=>1),'return' => 'id'));
            if( $priceset_records['is_error']) return;

            $itemmanager_periods = \Civi\Api4\ItemmanagerPeriods::get()
                ->addSelect('price_set_id')
                ->setCheckPermissions(FALSE)
                ->execute()
                ->indexBy('price_set_id');

            $pricefield_value_ids = array_column($pricefield_values_records['values'],'id');
            //$pricefield_ids = array_column($pricefield_records['values'],'id');
            $priceset_ids = array_column($priceset_records['values'],'id');
            $itemmanager_price_set_ids = array_column($itemmanager_periods->getArrayCopy(),'price_set_id');


            //now we wanna sync the pricesets with our extension
            foreach ($itemmanager_price_set_ids as $itemmanager_price_set_id) {

                if(!in_array((string)$itemmanager_price_set_id,$priceset_ids))
                {
                    $todeletePeriods[] = $itemmanager_price_set_id;
                    \Civi\Api4\ItemmanagerPeriods::delete()
                        ->addWhere('id','=',$itemmanager_price_set_id)
                        ->setCheckPermissions(FALSE)
                        ->execute();
                }

            }

            foreach ($priceset_ids as $set_id)
            {
                if(!in_array((int)$set_id,$itemmanager_price_set_ids))
                {
                    $toinsertPeriods[] = $set_id;



                    \Civi\Api4\ItemmanagerPeriods::create()
                        ->setValues(array(
                            'price_set_id' => $set_id,
                            'period_start_on' => '2000-01-01',
                            'periods' => 1,
                            'period_type' => 1,
                        ))
                        ->setCheckPermissions(FALSE)
                        ->execute();
                }

            }


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
                    $todeleteSettings[] = $field_id;
                    \Civi\Api4\ItemmanagerSettings::delete()
                        ->addWhere('id',"=","$field_id")
                        ->setCheckPermissions(FALSE)
                        ->execute();
                }

            }


            foreach ($pricefield_value_ids as $field_id)
            {
                if(!in_array((int)$field_id,$itemmanager_price_fields_ids)) {
                    $toinsertSettings[] = $field_id;

                    $field_infos = CRM_Itemmanager_Util::getPriceInfosFullRecordByFieldValueId($field_id);

                    if ($field_infos['iserror'] == 1) {
                        $this->_errormessages[] = 'Could not get the full record for price field value '.$field_id;
                        continue;
                    }

                    $price_set = $field_infos['values']['set'];

                    if (!isset($price_set['financial_type_id'] )) continue;

                    $itemmanager_period_rec = \Civi\Api4\ItemmanagerPeriods::get()
                        ->addWhere('price_set_id','=',$price_set['id'])
                        ->setCheckPermissions(FALSE)
                        ->execute();
                    $itemmanager_period = reset($itemmanager_period_rec);
                    \Civi\Api4\ItemmanagerSettings::create()
                        ->setValues(array(
                            'price_field_value_id' => $field_id,
                            'itemmanager_periods_id' => $itemmanager_period['id'],
                        ))
                        ->setCheckPermissions(FALSE)
                        ->execute();
                }

            }


        } catch (CiviCRM_API3_Exception $e) {

            $this->_errormessages[] = $e->$this->_errormessages;
        }

    }


    public function postProcess() {
    $values = $this->exportValues();

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
