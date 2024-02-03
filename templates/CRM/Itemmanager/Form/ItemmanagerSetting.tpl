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
    <div class="crm-accordion-wrapper open">
        {assign var="element_period_periods" value=$priceset.element_period_periods}
        {assign var="element_period_start_on" value=$priceset.element_period_start_on}
        {assign var="element_period_type" value=$priceset.element_period_type}

        <div class="crm-accordion-header">
        {ts}Priceset{/ts} {$priceset.price_label}
      </div>
      <div class="crm-accordion-body">
        <div class="crm-block crm-form-block crm-form-title-here-form-block" style="margin: auto">

            <span class="label">{$form.$element_period_periods.label}</span>
            <span class="content">{$form.$element_period_periods.html}</span>
            <span class="label">{$form.$element_period_type.label}</span>
            <span class="content">{$form.$element_period_type.html}</span>
            <span class="label">{$form.$element_period_start_on.label}</span>
            <span class="content">{$form.$element_period_start_on.html}</span>
        </div>
          <table>
            <thead>
            <tr class="columnheader">
              <th width="20%">{ts}Item Name{/ts}</th>
              <th width="2%">{ts}Active On{/ts}</th>
              <th width="2%">{ts}Expire On{/ts}</th>
              <th width="2%">{ts}Active{/ts}</th>
              <th width="2%">{ts}Ignore{/ts}</th>
              <th width="2%">{ts}Extend{/ts}</th>
              <th width="2%">{ts}Novitiate{/ts}</th>
              <th width="2%">{ts}Exception{/ts}</th>
              <th width="2%">{ts}Exception Periods{/ts}</th>
              <th>{ts}Successor{/ts}</th>
            </tr>

            </thead>

            <tbody>
            {foreach from=$priceset.fields item=field}
              {assign var="element_period_field_ignore" value=$field.element_period_field_ignore}
              {assign var="element_period_field_extend" value=$field.element_period_field_extend}
              {assign var="element_period_field_novitiate" value=$field.element_period_field_novitiate}
              {assign var="element_enable_period_exception" value=$field.element_enable_period_exception}
              {assign var="element_exception_periods" value=$field.element_exception_periods}
              {assign var="element_period_field_successor" value=$field.element_period_field_successor}
              <tr class="{cycle values="odd-row,even-row"}">
                <td width="20%">{$field.field_label}</td>
                <td width="2%">{$field.active_on}</td>
                <td width="2%">{$field.expire_on}</td>
                <td width="2%">{$field.isactive}</td>
                <td width="2%">{$form.$element_period_field_ignore.html}</td>
                <td width="2%">{$form.$element_period_field_extend.html}</td>
                <td width="2%">{$form.$element_period_field_novitiate.html}</td>
                <td width="2%">{$form.$element_enable_period_exception.html}</td>
                <td width="2%">{$form.$element_exception_periods.html}</td>
                <td>{$form.$element_period_field_successor.html}</td>

              </tr>
            {/foreach}

            </tbody>

          </table>





        </div>
    </div>
  {/foreach}


{/if}

{* FOOTER *}
<div class="clear"></div>
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
