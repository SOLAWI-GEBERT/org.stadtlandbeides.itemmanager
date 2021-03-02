{* HEADER *}

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="top"}
</div>

<div class="crm-accordion-wrapper open">

  <div class="crm-accordion-header">
    {ts domain="org.stadtlandbeides.itemmanager"}Membership {/ts}
  </div>
  <div class="crm-accordion-body">
    <div class="crm-block crm-form-block crm-form-title-here-form-block">



    </div>

    {foreach from=$elementNames item=elementName}
      <div class="crm-section">
        <div class="label">{$form.$elementName.label}</div>
        <div class="content">{$form.$elementName.html}</div>
        <div class="clear"></div>
      </div>
    {/foreach}


  </div>
</div>




<div class="crm-content">
  <div class="crm-accordion-wrapper open">

    <div class="crm-accordion-header">
      {ts domain="org.stadtlandbeides.itemmanager"}Membership {/ts}
    </div>
    <div class="crm-accordion-body">
      <div class="crm-block crm-form-block crm-form-title-here-form-block">
      </div>
    </div>
  </div>
</div>

{* FIELD EXAMPLE: OPTION 1 (AUTOMATIC LAYOUT) *}



{* FIELD EXAMPLE: OPTION 2 (MANUAL LAYOUT)

  <div>
    <span>{$form.favorite_color.label}</span>
    <span>{$form.favorite_color.html}</span>
  </div>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
