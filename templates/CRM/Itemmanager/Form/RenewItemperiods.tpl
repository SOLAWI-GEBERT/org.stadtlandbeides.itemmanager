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
            <td><span id="{$element_item_name}_start_on">{$lineitem.new_period_start_on}</span></td>
            <td><span id="{$element_item_name}_end_on">{$lineitem.new_period_end_on}</span></td>
            <td><span class="content">{$form.$element_quantity_name.html}</span></td>
            <td><span class="content">{$form.$element_period_name.html}</span></td>
            <td>Interval</td>
            <td><span id="{$element_item_name}_interval_price">{$lineitem.new_interval_price}</span></td>
            <td><span id="{$element_item_name}_active_on">{$lineitem.new_active_on}</span></td>
            <td><span id="{$element_item_name}_expire_on">{$lineitem.new_expire_on}</span></td>
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

<script type="application/javascript">
  {literal}

  //UpdateSettingsInfos
  function UpdateSettings($, _,membership_id,price_field_id,price_field_value_id,isitemselector)
  {
    //Example member_27_item_11_period_14"
    try {
      var item_name = 'member_' + membership_id + '_item_' + price_field_id;
      var item_selector = document.getElementById(item_name);
      var period_selector = document.getElementById('member_' + membership_id + '_item_' + price_field_id +
                                                    '_period_' + price_field_value_id);
      var line_item = CRM.vars.RenewItemperiods[membership_id]['line_items'][price_field_value_id];
      //first set period selector
      var options = line_item['choices']['period_selection'][item_selector.value];
      var length = period_selector.options.length;

      if(isitemselector)
      {
        for (i = length-1; i >= 0; i--) {
          period_selector.options[i] = null;
        }

        var data = "-";
        var keys = Object.keys(options);
        for (var i = keys.length - 1; i >= 0; i--) {
          var option = document.createElement("option");
          option.value = keys[i];
          option.text = options[keys[i]];
          period_selector.appendChild(option);
        }


      }



      var choice = line_item['choices']['period_data'][item_selector.value][period_selector.value]
      var start_on  = document.getElementById(item_name + '_start_on');
      var end_on  = document.getElementById(item_name + '_end_on');
      var interval_price  = document.getElementById(item_name + '_interval_price');
      var active_on  = document.getElementById(item_name + '_active_on');
      var expire_on  = document.getElementById(item_name + '_expire_on');

      //refresh related record data
      start_on.innerHTML = choice.period_start_on;
      end_on.innerHTML = choice.period_end_on;
      interval_price.innerHTML = choice.interval_price;
      active_on.innerHTML = choice.active_on;
      expire_on.innerHTML = choice.expire_on;

    }
    catch(e)
    {
      CRM.alert(e);
    }

  }

  {/literal}
</script>


{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
