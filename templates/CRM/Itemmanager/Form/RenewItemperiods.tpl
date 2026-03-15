{* HEADER *}

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="top"}
</div>

{if $errormessages}
  <div class="messages status no-popup crm-not-you-message">
    <div class="icon inform-icon"></div>
    {foreach from=$errormessages item=message}
      <p>{$message}</p>
    {/foreach}
  </div>
{/if}


  {if $memberships}
  {foreach from=$memberships item=membership}
    {if $membership.show}
    <div class="crm-accordion-wrapper open" data-member-id="{$membership.member_id}">
      <div class="crm-accordion-header">
        {ts domain="org.stadtlandbeides.itemmanager"}Membership{/ts}
        {$membership.name}
      </div>
      <div class="crm-accordion-body">
        <div class="crm-block crm-form-block crm-form-title-here-form-block">
          {ts domain="org.stadtlandbeides.itemmanager"}Status{/ts}
          {if $membership.active}
            <span style="font-weight: bold; color: #60A237;">{$membership.status}</span>
          {else}
            <span style="font-weight: bold; color: #cc0000;">{$membership.status}</span>
          {/if}
          <button type="button" class="crm-button renew-skip-btn" data-member-id="{$membership.member_id}" style="float:right;">
            <i class="crm-i fa-forward"></i>
            {ts domain="org.stadtlandbeides.itemmanager"}Skip{/ts}
          </button>
        </div>
        {foreach from=$membership.line_items item=lineitem}
          {assign var="element_item_name" value=$lineitem.element_item_name}
          {assign var="element_hidden_name" value=$lineitem.element_hidden_name}
          {assign var="element_quantity_name" value=$lineitem.element_quantity_name}
          {assign var="element_period_name" value=$lineitem.element_period_name}

          <table>
            <thead>
            <tr>
              <th></th>
              <th style="min-width: 300px; width: 40%;"><span class="label">{$form.$element_item_name.label}</span></th>
              <th>{ts domain="org.stadtlandbeides.itemmanager"}Start{/ts}</th>
              <th>{ts domain="org.stadtlandbeides.itemmanager"}End{/ts}</th>
              <th><span class="label">{$form.$element_quantity_name.label}</span></th>
              <th><span class="label">{$form.$element_period_name.label}</span></th>
              <th>{ts domain="org.stadtlandbeides.itemmanager"}Interval{/ts}</th>
              <th>{ts domain="org.stadtlandbeides.itemmanager"}Price per Interval{/ts}</th>
              <th>{ts domain="org.stadtlandbeides.itemmanager"}Active ON{/ts}</th>
              <th>{ts domain="org.stadtlandbeides.itemmanager"}Active Until{/ts}</th>
            </tr>
            </thead>
            <tbody>
            {if not $lineitem.new_field}
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
            {/if}
              <!-- Here we want the input from the user regarding a item -->
              <tr
                      {if $lineitem.new_field and not $lineitem.extend}
                style="background-color:lightgrey"
                      {/if}
                      {if $lineitem.new_field and $lineitem.extend}
                        style="background-color:lightpink"
                      {/if}
              >
                <td><div>{ts domain="org.stadtlandbeides.itemmanager"}New{/ts}</div></td>
                <td><span class="content">{$form.$element_item_name.html}</span></td>
                <td><span id="{$element_item_name}_start_on">{$lineitem.new_period_start_on}</span></td>
                <td><span id="{$element_item_name}_end_on">{$lineitem.new_period_end_on}</span></td>
                <td><span class="content">{$form.$element_quantity_name.html}</span></td>
                <td><span class="content">{$form.$element_period_name.html}</span></td>
                <td>{ts domain="org.stadtlandbeides.itemmanager"}Interval{/ts}</td>
                <td><span id="{$element_item_name}_interval_price">{$lineitem.new_interval_price}</span></td>
                <td><span id="{$element_item_name}_active_on">{$lineitem.new_active_on}</span></td>
                <td><span id="{$element_item_name}_expire_on">{$lineitem.new_expire_on}</span></td>
              </tr>
            {if $lineitem.bidding}
              <tr style="background-color:lightsalmon">
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td><span class="content">{ts domain="org.stadtlandbeides.itemmanager"}Bidding round{/ts}</span></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>

              </tr>
            {/if}

            </tbody>

          </table>
          {if $lineitem.help_pre}
            </br>
            <div class="help">{$lineitem.help_pre}</div>
          {/if}

        {/foreach}

      </div>
</div>
    {/if}
{/foreach}
{/if}


<script type="application/javascript">
  {literal}

  // Set tooltip on item selector to show selected option text
  function updateItemTooltip(selector) {
    if (selector && selector.options && selector.selectedIndex >= 0) {
      selector.title = selector.options[selector.selectedIndex].text;
    }
  }

  // Initialize tooltips on all item selectors after page load
  CRM.$(function($) {
    $('select[id^="member_"]').each(function() {
      var parts = this.id.split('_');
      // Match selectors whose id is like member_X_item_Y (no further suffix)
      if (parts.length === 4 && parts[2] === 'item') {
        updateItemTooltip(this);
      }
    });
  });

  //UpdateSettingsInfos
  function UpdateSettings($, _,membership_id,price_field_id,price_field_value_id,isitemselector)
  {
    //Example member_27_item_11_period_14"
    try {
      var item_name = 'member_' + membership_id + '_item_' + price_field_id;
      var hidden_name = 'member_' + membership_id + '_item_' + price_field_id + '_hidden';
      var hidden_field = document.getElementById(hidden_name);
      var item_selector = document.getElementById(item_name);
      var period_selector = document.getElementById('member_' + membership_id + '_item_' + price_field_id +
                                                    '_period_' + price_field_value_id);
      var line_item = CRM.vars.RenewItemperiods[membership_id]['line_items'][price_field_value_id];
      //first set period selector
      var options = line_item['choices']['period_selection'][item_selector.value];
      var priceset = line_item['choices']['price_set_selection'][item_selector.value];
      var length = period_selector.options.length;

      if(isitemselector)
      {
        hidden_field.value = priceset;
        updateItemTooltip(item_selector);

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

<script type="application/javascript">
  {literal}
  CRM.$(function($) {
    // Skip/unskip membership toggle
    $('.renew-skip-btn').on('click', function() {
      var $btn = $(this);
      var memberId = $btn.data('member-id');
      var $wrapper = $btn.closest('.crm-accordion-wrapper');
      var $body = $wrapper.find('.crm-accordion-body');
      var isSkipped = $btn.hasClass('renew-skipped');
      var prefix = 'member_' + memberId + '_item_';

      // Find all quantity inputs for this membership
      $body.find('input[id^="' + prefix + '"][id*="_quantity_"]').each(function() {
        if (!isSkipped) {
          // Store original value and set to 0
          $(this).data('orig-qty', this.value);
          this.value = '0';
        } else {
          // Restore original value
          this.value = $(this).data('orig-qty') || this.placeholder || '0';
        }
      });

      if (!isSkipped) {
        $btn.addClass('renew-skipped');
        $btn.html('<i class="crm-i fa-undo"></i> {/literal}{ts domain="org.stadtlandbeides.itemmanager" escape="js"}Unskip{/ts}{literal}');
        $body.find('table, .help').css('opacity', '0.4');
        $body.find('select, input[type="text"]').prop('disabled', true);
      } else {
        $btn.removeClass('renew-skipped');
        $btn.html('<i class="crm-i fa-forward"></i> {/literal}{ts domain="org.stadtlandbeides.itemmanager" escape="js"}Skip{/ts}{literal}');
        $body.find('table, .help').css('opacity', '1');
        $body.find('select, input[type="text"]').prop('disabled', false);
      }
    });

    $('#RenewItemperiods').on('submit', function() {
      // Re-enable skipped fields so their values (0) get submitted
      $(this).find('.renew-skipped').each(function() {
        $(this).closest('.crm-accordion-wrapper').find('select, input[type="text"]').prop('disabled', false);
      });
      var membershipCount = {/literal}{$memberships|@count}{literal} || 0;
      if (membershipCount === 0) return;

      var msg = '{/literal}{ts domain="org.stadtlandbeides.itemmanager" escape="js"}Processing renewals, please wait...{/ts}{literal}';
      msg += '<br><small>' + membershipCount + ' {/literal}{ts domain="org.stadtlandbeides.itemmanager" escape="js"}membership(s) to process. This may take a while.{/ts}{literal}</small>';

      // Show blocking overlay with CiviCRM spinner
      var $overlay = $('<div id="renewitemperiods-overlay"></div>').css({
        position: 'fixed', top: 0, left: 0, width: '100%', height: '100%',
        backgroundColor: 'rgba(0,0,0,0.4)', zIndex: 10000, cursor: 'wait'
      });
      var $box = $('<div></div>').css({
        position: 'fixed', top: '40%', left: '50%', transform: 'translate(-50%,-50%)',
        backgroundColor: '#fff', padding: '30px 40px', borderRadius: '4px',
        boxShadow: '0 2px 10px rgba(0,0,0,0.3)', textAlign: 'center', zIndex: 10001
      }).html(
        '<div><i class="crm-i fa-spinner fa-spin" style="font-size:28px;margin-bottom:12px;"></i></div>' +
        '<div>' + msg + '</div>'
      );
      $overlay.append($box).appendTo('body');

      // Disable submit buttons to prevent double-clicks
      $(this).find('input[type="submit"], .crm-button').prop('disabled', true);
    });
  });
  {/literal}
</script>

{* FOOTER *}
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
