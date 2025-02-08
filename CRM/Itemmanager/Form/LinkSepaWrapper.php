<?php

use CRM_Itemmanager_ExtensionUtil as E;






/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Itemmanager_Form_LinkSepaWrapper extends CRM_Core_Form {

    public $_contact_id;
    public $_relations;
    private $_errormessages;
    private $_back_ward_search;

    function __construct()
    {
        parent::__construct();
        $this->_relations = array();
        $this->_errormessages = array();
        $this->_back_ward_search = array();
    }

    public function preProcess()
    {
        $this->_contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');

        $contact = civicrm_api('Contact', 'getsingle', array('version' => 3, 'id' => $this->_contact_id));
        if (isset($contact['is_error']) && $contact['is_error']) {
            CRM_Core_Session::setStatus(sprintf(ts("Couldn't find contact #%s", array('domain' => 'org.stadtlandbeides.itemmanager')),
                $this->_contact_id), ts('Error', array('domain' => 'org.stadtlandbeides.itemmanager')), 'error');
            $this->assign("display_name", "ERROR");
            return;
        }

        CRM_Utils_System::setTitle(E::ts('Payments relation for').' '.$contact['display_name']);

        $this->assign('contact_id', $this->_contact_id);

        $itemmanager_price_fields = \Civi\Api4\ItemmanagerSettings::get()
            ->setCheckPermissions(TRUE)
            ->execute();

        $currency = Civi::settings()->get('defaultCurrency');

        //region Fetch Financial types as root object
        foreach ($itemmanager_price_fields as $item)
        {

            $price_id = $item['price_field_value_id'];
            $ignore = $item['ignore'];
            $novitiate = $item['novitiate'];
            //avoid general contribution amount etc.
            if($ignore && !$novitiate) continue;

            $price_origins  = civicrm_api3('PriceFieldValue', 'get',
                array('price_field_id' => (int)$price_id));
            if ($price_origins['is_error']) {
                $this->_errormessages[] = 'Could not get the price field ' .(int)$price_id;
                continue;
            }

            $price_origin = reset($price_origins['values']);

            if(!$price_origin)
                continue;

            $financial_id = $price_origin['financial_type_id'];

            //check further efforts first
            if(array_key_exists($financial_id, $this->_relations)) continue;

            $finance_type  = civicrm_api3('FinancialType', 'getsingle',
                array('id' => (int)$financial_id));
            if (!isset($finance_type)) {
                $this->_errormessages[] = 'Could not get the financial type ' .(int)$financial_id;
                continue;
            }

            $finance_name = $finance_type['name'];

            //basic dictionary with default settings
            $this->_relations[(int)$financial_id] = array(
                'financial_id' => $financial_id,
                'financial_name' => $finance_name,
                'element_link_name' => 'grouplink_'.$financial_id,
            );

            //fill form backward search
            $this->_back_ward_search[$this->_relations[(int)$financial_id]['element_link_name']] = array(
                'entity' => 'financial',
                'financial_id' => $financial_id,
            );

        }
        //endregion

    }

    public function buildQuickForm() {

        $filter_open = CRM_Itemmanager_Util::getSetting('itemmanager_filter_open_payments');
        $filter_past = CRM_Itemmanager_Util::getSetting('itemmanager_filter_past_payments');

        $this->addButtons(array(
          array(
            'type' => 'cancel',
            'name' => E::ts('Complete'),
            'isDefault' => TRUE,
          ),
        ));

        // Erweiterungsname oder relativer Pfad
        $extensionName = 'org.stadtlandbeides.itemmanager';

        // export form elements
        CRM_Core_Resources::singleton()
            ->addScriptFile($extensionName, 'js/expandAccordion.js', 999, 'html-header')
            ->addScriptFile($extensionName,'js/filterSEPAOptions.js', 999, 'html-header')
            ->addStyleFile($extensionName, 'css/handlePayment.js', 999, 'html-header')
            ->addStyleFile($extensionName, 'css/sepaLink.css', 999, 'html-header');

        $this->assign('relations', $this->_relations);
        $this->assign('SEPAFilterOptions','filteropen='.$filter_open.'&filterfuture='.$filter_past);
        $this->assign('filteropencheck',$filter_open ? 'checked':'');
        $this->assign('filterpastcheck',$filter_past ? 'checked': '');
        $this->assign('elementNames', $this->getRenderableElementNames());



        parent::buildQuickForm();
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

    /**
     * report error data
     */
    protected function processError($status, $title, $message) {
        CRM_Core_Session::setStatus($status . "<br/>" . $message, ts('Error', array('domain' => 'org.stadtlandbeides.itemmanager')), 'error');
        $this->assign("error_title",   $title);
        $this->assign("error_message", $message);


    }

    protected function processSuccess($message) {
        CRM_Core_Session::setStatus($message, ts('Success', array('domain' => 'org.stadtlandbeides.itemmanager')), 'success');



    }

    protected function processInfo($message) {
        CRM_Core_Session::setStatus($message, ts('Info', array('domain' => 'org.stadtlandbeides.itemmanager')), 'info');



    }
}
