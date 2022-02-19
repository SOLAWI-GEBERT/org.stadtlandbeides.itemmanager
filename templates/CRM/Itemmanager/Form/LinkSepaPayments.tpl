{* HEADER *}


<h3>{ts domain="org.stadtlandbeides.itemmanager"}SEPA Payment Assignments{/ts}</h3>

<div class="help">
  {ts domain="org.stadtlandbeides.itemmanager"}
    Link here the SEPA payments to the payment plan contributions.
    You can also set the payment contributions manually to paid here.
  {/ts}
</div>

<div class="crm-block crm-form-block crm-form-title-here-form-block" style="margin: auto">

  <span class="label">{$form.$element_period_periods.label}</span>
  <span class="content">{$form.$element_period_periods.html}</span>
  <span class="label">{$form.$element_period_start_on.label}</span>
  <span class="content">{$form.$element_period_start_on.html}</span>

</div>


{* FIELD EXAMPLE: OPTION 1 (AUTOMATIC LAYOUT) *}

{foreach from=$elementNames item=elementName}
  <div class="crm-section">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

{* FIELD EXAMPLE: OPTION 2 (MANUAL LAYOUT)

  <div>
    <span>{$form.favorite_color.label}</span>
    <span>{$form.favorite_color.html}</span>
  </div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
