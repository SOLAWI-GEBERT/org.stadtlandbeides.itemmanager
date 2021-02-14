<h3>Dashboard</h3>

{* Example: Display a variable directly *}
<p>Updated time is {$currentTime}</p>

{* Example: Display a translated string -- which happens to include a variable *}
<p>{ts 1=$currentTime}(In your native language) The current time is %1.{/ts}</p>

{if $group_sets}

    {foreach from=$group_sets item=group}
        <h3>{ts domain="org.stadtlandbeides.itemmanager"}Booked items from {$group.date_min} to {$group.date_max} {/ts}</h3>
        <table>
        <thead>
            <tr class="columnheader">
                <td width="40%">{ts domain="org.stadtlandbeides.itemmanager"}Item{/ts}</td>
                <td width="5%">{ts domain="org.stadtlandbeides.itemmanager"}Amount{/ts}</td>
                <td>{ts domain="org.stadtlandbeides.itemmanager"}Referred to{/ts}</td>
            </tr>
        </thead>

        <tbody>
        {foreach from=$group.list item=ritem}
            <tr class="{cycle values="odd-row,even-row"}">
            <td width="40%">{$ritem.item_label}</td>
            <td width="5%">{$ritem.item_quantity}</td>
            <td>{$ritem.member_name}</td>
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

