{* HEADER *}

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="top"}
</div>

<div class="crm-accordion-wrapper open">

  {if $memberships}
  {foreach from=$memberships item=membership}
  <div class="crm-accordion-header">
    {ts domain="org.stadtlandbeides.itemmanager"}Membership {/ts}
    {$membership.name}
  </div>
  <div class="crm-accordion-body">
    <div class="crm-block crm-form-block crm-form-title-here-form-block">



    </div>
    {foreach from=$membership.line_items item=lineitem}
      {assign var="element_item_name" value=$lineitem.element_item_name}
      {assign var="element_quantity_name" value=$lineitem.element_quantity_name}
      {assign var="element_period_name" value=$lineitem.element_period_name}

      <table>
        <thead>
        <tr>
          <th></th>
          <th width="40%"><span class="label">{$form.$element_item_name.label}</span></th>
          <th>Start</th>
          <th>End</th>
          <th><span class="label">{$form.$element_quantity_name.label}</span></th>
          <th><span class="label">{$form.$element_period_name.label}</span></th>
          <th>Interval</th>
          <th>Price per Interval</th>
          <th>Active ON</th>
          <th>Active Until</th>
        </tr>
        </thead>
        <tbody>

          <tr>
            <td><div>{ts domain="org.stadtlandbeides.itemmanager"}Old{/ts}</div></td>
            <td width="40%">{$lineitem.name}</td>
            <td><div>{$membership.start_date}</div></td>
            <td><div>{$membership.last_date}</div></td>
            <td>{$lineitem.last_qty}</td>
            <td></td>
            <td></td>
            <td>{$lineitem.last_price_per_interval}</td>

          </tr>
          <!-- Here we want the input from the user regarding a item -->
          <tr>
            <td><div>{ts}New{/ts}</div></td>
            <td><span class="content">{$form.$element_item_name.html}</span></td>
            <td>{$lineitem.new_period_start_on}</td>
            <td>{$lineitem.new_period_end_on}</td>
            <td><span class="content">{$form.$element_quantity_name.html}</span></td>
            <td><span class="content">{$form.$element_period_name.html}</span></td>
            <td>Interval</td>
            <td>{$lineitem.new_interval_price}</td>
            <td>{$lineitem.new_active_on}</td>
            <td>{$lineitem.new_expire_on}</td>
          </tr>

        </tbody>

      </table>
      {if $lineitem.help_pre}
        </br>
        <div class="help">{$lineitem.help_pre}</div>
      {/if}

    {/foreach}

  </div>
</div>
{/foreach}
{/if}



{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
