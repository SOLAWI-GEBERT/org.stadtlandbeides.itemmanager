{literal}
<style>
  .lineitemedit-form {
    max-width: 420px;
  }
  .lineitemedit-form .crm-section {
    display: flex;
    align-items: center;
    padding: 3px 0;
  }
  .lineitemedit-form .label {
    width: 120px;
    min-width: 120px;
    font-weight: bold;
    padding-right: 8px;
    text-align: right;
    font-size: 0.9em;
  }
  .lineitemedit-form .content {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 4px;
  }
  .lineitemedit-form .currency-symbol {
    color: #666;
    font-weight: bold;
    min-width: 16px;
  }
  .lineitemedit-form .field-group-header {
    font-weight: bold;
    color: #555;
    border-bottom: 2px solid #d3d3d3;
    padding: 6px 0 2px;
    margin-top: 6px;
    font-size: 0.9em;
  }
  .lineitemedit-form input[type="text"][readonly] {
    background: #f5f5f5;
    color: #888;
    border-color: #ddd;
  }
  .lineitemedit-form input[type="text"],
  .lineitemedit-form select {
    padding: 2px 4px;
  }
  .lineitemedit-help {
    margin-top: 10px;
    padding: 6px 10px;
    background: #f7f7f7;
    border-left: 3px solid #b0b0b0;
    font-size: 0.85em;
    color: #555;
    line-height: 1.4;
  }
</style>
{/literal}

<div class="lineitemedit-form">

  <div class="field-group-header">{ts domain="org.stadtlandbeides.itemmanager"}Item{/ts}</div>

  <div class="crm-section">
    <div class="label">{$form.label.label}</div>
    <div class="content">{$form.label.html}</div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.financial_type_id.label}</div>
    <div class="content">{$form.financial_type_id.html}</div>
  </div>

  <div class="field-group-header">{ts domain="org.stadtlandbeides.itemmanager"}Pricing{/ts}</div>

  <div class="crm-section">
    <div class="label">{$form.qty.label}</div>
    <div class="content">{$form.qty.html}</div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.unit_price.label}</div>
    <div class="content">
      <span class="currency-symbol">{$currency}</span>
      {$form.unit_price.html}
    </div>
  </div>

  <div class="crm-section">
    <div class="label">{$form.line_total.label}</div>
    <div class="content">
      <span class="currency-symbol">{$currency}</span>
      {$form.line_total.html}
    </div>
  </div>

  {if $isTaxEnabled}
  <div class="crm-section" id="crm-section-tax-amount">
    <div class="label">{$form.tax_amount.label}</div>
    <div class="content">
      <span class="currency-symbol">{$currency}</span>
      {$form.tax_amount.html}
    </div>
  </div>
  {/if}

  {if $futureCount > 0}
  <div class="field-group-header">{ts domain="org.stadtlandbeides.itemmanager"}Future Contributions{/ts}</div>
  <div class="crm-section">
    <div class="label">&nbsp;</div>
    <div class="content">
      <label style="cursor:pointer; display:flex; align-items:center; gap:6px;">
        {$form.apply_future.html}
        <span>{ts domain="org.stadtlandbeides.itemmanager" 1=$futureCount}Apply changes to %1 future contribution(s){/ts}</span>
      </label>
    </div>
  </div>
  {/if}

  {if $helpTexts|@count > 0}
  <div class="lineitemedit-help">
    {foreach from=$helpTexts item=helpText}
      <div>{$helpText}</div>
    {/foreach}
  </div>
  {/if}

</div>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{include file="CRM/Itemmanager/Form/CalculateLineItemFields.tpl"}
