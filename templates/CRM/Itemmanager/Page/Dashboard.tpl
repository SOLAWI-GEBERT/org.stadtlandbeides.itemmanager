<h3>{ts domain="org.stadtlandbeides.itemmanager"}Item Dashboard{/ts}</h3>

{* add new mandate button *}
<div>
    <a id="items_update_extra_button" class="button crm-popup" href="{crmURL p="civicrm/items/update" q="action=update&cid=$contact_id&backtrace=1&smartyDebug=1"}">
        <span>
            <div class="icon add-icon edit-icon"></div>{ts domain="org.stadtlandbeides.itemmanager"}Update Items{/ts}
        </span>
    </a>
    <br/>
    <br/>
</div>

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
    });
    {/literal}
</script>
