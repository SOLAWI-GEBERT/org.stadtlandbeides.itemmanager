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
        {assign var="element_period_hide" value=$priceset.element_period_hide}
        {assign var="element_period_reverse" value=$priceset.element_period_reverse}
        {assign var="element_period_successor" value=$priceset.element_period_successor}

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
            <span class="label">{$form.$element_period_hide.label}</span>
            <span class="content">{$form.$element_period_hide.html}</span>
            <span class="label">{$form.$element_period_reverse.label}</span>
            <span class="content">{$form.$element_period_reverse.html}</span>
            <span class="label">{$form.$element_period_successor.label}</span>
            <span class="content">{$form.$element_period_successor.html}</span>
        </div>
          <table>
            <thead>
            <tr class="columnheader">
              <th class="medium">{ts}Item Name{/ts}</th>
              <th class="small">{ts}Active On{/ts}</th>
              <th class="small">{ts}Expire On{/ts}</th>
              <th class="small">{ts}Active{/ts}</th>
              <th class="small">{ts}Ignore{/ts}</th>
              <th class="small">{ts}Extend{/ts}</th>
              <th class="small">{ts}Novitiate{/ts}</th>
              <th class="small">{ts}Bidding Round{/ts}</th>
              <th class="small">{ts}Exception{/ts}</th>
              <th class="small">{ts}Exception Periods{/ts}</th>
              <th>{ts}Successor{/ts}</th>
            </tr>

            </thead>

            <tbody>
            {foreach from=$priceset.fields item=field}
              {assign var="element_period_field_ignore" value=$field.element_period_field_ignore}
              {assign var="element_period_field_extend" value=$field.element_period_field_extend}
              {assign var="element_period_field_novitiate" value=$field.element_period_field_novitiate}
              {assign var="element_period_field_bidding" value=$field.element_period_field_bidding}
              {assign var="element_enable_period_exception" value=$field.element_enable_period_exception}
              {assign var="element_exception_periods" value=$field.element_exception_periods}
              {assign var="element_period_field_successor" value=$field.element_period_field_successor}
              <tr class="{cycle values="odd-row,even-row"}">
                <td class="medium">{$field.field_label}</td>
                <td class="small">{$field.active_on}</td>
                <td class="small">{$field.expire_on}</td>
                <td class="small">{$field.isactive}</td>
                <td class="small">{$form.$element_period_field_ignore.html}</td>
                <td class="small">{$form.$element_period_field_extend.html}</td>
                <td class="small">{$form.$element_period_field_novitiate.html}</td>
                <td class="small">{$form.$element_period_field_bidding.html}</td>
                <td class="small">{$form.$element_enable_period_exception.html}</td>
                <td class="small">{$form.$element_exception_periods.html}</td>
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
