{* HEADER *}
<div class="help">
  {ts domain="org.stadtlandbeides.itemmanager"}
    Edit here the successor of each price field item.
    The given start date just defines day and month. The year has no meaning.
  {/ts}</div>
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="top"}
</div>
{if $errormessages}
  <div class="crm-error">
    {foreach from=$errormessages item=message}
      <span>{$message}</br></span>
    {/foreach}
  </div>

{/if}

{if $itemsettings}
  {foreach from=$itemsettings item=priceset}
    {assign var="element_period_periods" value=$priceset.element_period_periods}
    {assign var="element_period_start_on" value=$priceset.element_period_start_on}

    <div class="crm-accordion-header">
    {ts}Priceset{/ts} {$priceset.price_label}
  </div>
  <div class="crm-accordion-body">
    <div class="crm-block crm-form-block crm-form-title-here-form-block" style="margin: auto">

        <span class="label">{$form.$element_period_periods.label}</span>
        <span class="content">{$form.$element_period_periods.html}</span>
        <span class="label">{$form.$element_period_start_on.label}</span>
        <span class="content">{$form.$element_period_start_on.html}</span>
    </div>
      <table>
        <thead>
        <th width="20%">{ts}Item Name{/ts}</th>
        <th width="2%">{ts}Active On{/ts}</th>
        <th width="2%">{ts}Expire On{/ts}</th>
        <th width="2%">{ts}Active{/ts}</th>
        <th width="2%">{ts}Ignore{/ts}</th>
        <th width="2%">{ts}Novitiate{/ts}</th>
        <th>{ts}Successor{/ts}</th>


        </thead>

        <tbody>
        {foreach from=$priceset.fields item=field}
          {assign var="element_period_field_ignore" value=$field.element_period_field_ignore}
          {assign var="element_period_field_novitiate" value=$field.element_period_field_novitiate}
          {assign var="element_period_field_successor" value=$field.element_period_field_successor}
          <tr>
            <td width="20%">{$field.field_label}</td>
            <td width="2%">{$field.active_on}</td>
            <td width="2%">{$field.expire_on}</td>
            <td width="2%">{$field.isactive}</td>
            <td width="2%">{$form.$element_period_field_ignore.html}</td>
            <td width="2%">{$form.$element_period_field_novitiate.html}</td>
            <td>{$form.$element_period_field_successor.html}</td>

          </tr>
        {/foreach}

        </tbody>

      </table>





    </div>

  {/foreach}


{/if}

{* FOOTER *}
<div class="clear"></div>
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
