<div class="crm-content">

<h3>{ts domain="org.stadtlandbeides.itemmanager"}Item Dashboard{/ts}</h3>

<div>
    <a id="items_renew_extra_button" class="button crm-popup" href="{crmURL p="civicrm/items/renewperiods" q="action=renew_item_periods&cid=$contact_id"}">
        <span>
            <div class="icon crm-i fa-plus-circle"></div>{ts domain="org.stadtlandbeides.itemmanager"}Renew Periods{/ts}
        </span>
    </a>
    <a id="items_update_extra_button" class="button crm-popup" href="{crmURL p="civicrm/items/update" q="action=update&cid=$contact_id&harm=1&sync=1"}">
        <span>
            <div class="icon add-icon edit-icon"></div>{ts domain="org.stadtlandbeides.itemmanager"}Update Items{/ts}
        </span>
    </a>
    <a title="{ts domain="org.project60.sepa"}Refresh{/ts}"
       id="refresh_dashboard"
       class="refresh button"
       href="{crmURL p="civicrm/contact/view" q="action=refresh&reset=1&cid=$contact_id&selectedChild=itemmanager"}">
                    <span>
                      <div class="icon refresh-icon ui-icon-refresh"></div>
                      {ts domain="org.stadtlandbeides.itemmanager"}Refresh{/ts}
                    </span>
    </a>
    <br/>
    <br/>
</div>

</br>
<div class="help">{ts domain="org.stadtlandbeides.itemmanager"}To start with a new set of monthly contributions click Renew Periods{/ts}</div>

{if $group_sets}

    <div class="crm-accordion-wrapper open">

        {foreach from=$group_sets item=group}
            <div class="crm-accordion-header">
                {ts domain="org.stadtlandbeides.itemmanager"}Booked items from {$group.date_min} to {$group.date_max} {/ts}
            </div>
            <div class="crm-accordion-body">
                <div class="crm-block crm-form-block crm-form-title-here-form-block">

                    <table>
                    <thead>
                        <tr class="columnheader">
                            <td width="45%">{ts domain="org.stadtlandbeides.itemmanager"}Referred to{/ts}</td>
                            <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Quantity{/ts}</td>
                            <td width="40%">{ts domain="org.stadtlandbeides.itemmanager"}Item{/ts}</td>
                        </tr>
                    </thead>

                    <tbody>
                    {foreach from=$group.list item=ritem}
                        <tr class="{cycle values="odd-row,even-row"}">
                            <td width="45%">{$ritem.member_name}</td>
                            <td width="5%">{$ritem.item_quantity}</td>
                            <td width="40%">{$ritem.item_label}</td>

                        </tr>
                    {/foreach}
                    </tbody>
                    </table>

                </div>
            </div>
        {/foreach}

    </div>
{else}
    <div id="help">
        {ts domain="org.stadtlandbeides.itemmanager"}This contact has no recorded line items.{/ts}
    </div>
{/if}


<script type="application/javascript">
    {literal}
        // trigger reload of tab
        // || cj(event.target).attr('href').includes('civicrm/sepa/xmandate')
        cj(document).ready(function() {
            cj(document).on('crmPopupClose', function(event) {
                if(cj(event.target).attr('href').includes('civicrm/items/update')) {
                    cj("#items_update_extra_button").closest("div.crm-ajax-container").crmSnippet('refresh');
                }
            });

            $('#crm-content')
                .on('click', 'a.button, a.action-item[href*="action=refresh"]', CRM.popup)
                .on('crmPopupFormSuccess', 'a.button, a.action-item[href*="action=refresh"], function() {
                    // Refresh datatable when form completes
                    $('#crm-ajax-container').crmSnippet('refresh');
                });

        });


    {/literal}
</script>
</div>