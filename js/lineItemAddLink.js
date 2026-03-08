CRM.$(function($) {
  if (CRM.vars.itemmanager.isQuickConfig && $('#totalAmount').length) {
    $('#lineitem-block').insertAfter('#totalAmount');
    $('#totalAmount').append(CRM.vars.itemmanager.add_link);
  }
  else {
    $('div.total_amount-section').prepend(CRM.vars.itemmanager.add_link);
    if (CRM.vars.itemmanager.hideHeader) {
      $('tr.columnheader').find('th:last').html('');
    }
  }
});
