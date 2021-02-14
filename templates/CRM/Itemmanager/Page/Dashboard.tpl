<h3>Dashboard</h3>

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

    {foreach from=$group_sets item=group}
        <h3>{ts domain="org.stadtlandbeides.itemmanager"}Booked items from {$group.date_min} to {$group.date_max} {/ts}</h3>
        <table>
        <thead>
            <tr class="columnheader">
                <td width="45%">{ts domain="org.stadtlandbeides.itemmanager"}Referred to{/ts}</td>
                <td width="40%">{ts domain="org.stadtlandbeides.itemmanager"}Item{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Amount{/ts}</td>
            </tr>
        </thead>

        <tbody>
        {foreach from=$group.list item=ritem}
            <tr class="{cycle values="odd-row,even-row"}">
                <td width="45%">{$ritem.member_name}</td>
                <td width="40%">{$ritem.item_label}</td>
                <td width="5%">{$ritem.item_quantity}</td>

            </tr>
        {/foreach}
        </tbody>
        </table>
    {/foreach}
{else}
    <div id="help">
        {ts domain="org.stadtlandbeides.itemmanager"}This contact has no recorded line items.{/ts}
    </div>
{/if}

