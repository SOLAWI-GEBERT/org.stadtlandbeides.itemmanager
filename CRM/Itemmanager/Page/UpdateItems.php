<?php
use CRM_Itemmanager_ExtensionUtil as E;

class CRM_Itemmanager_Page_UpdateItems extends CRM_Core_Page {

  public function run() {
    // Example: Set the page-title dynamically; alternatively, declare a static title in xml/Menu/*.xml
    CRM_Utils_System::setTitle(E::ts('UpdateItems'));

    // Example: Assign a variable for use in a template
    $this->assign('currentTime', date('Y-m-d H:i:s'));
      $contact_id = CRM_Utils_Request::retrieve('cid', 'Integer');
      $this->assign('contact_id', $contact_id);

    parent::run();
  }

}
